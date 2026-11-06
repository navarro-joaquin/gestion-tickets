# Respaldo de base de datos y multimedia (Windows + Docker).
#
# Uso:
#   pwsh scripts/backup.ps1
#   pwsh scripts/backup.ps1 -RetentionDays 30 -OutputDirectory D:\respaldos
#
# Requiere que el contenedor gestion-tickets-mysql esté en ejecución.
param(
    [int]$RetentionDays = 14,
    [string]$OutputDirectory = "backups"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $root "compose.yaml"

if (-not (Test-Path $composeFile)) {
    throw "No se encontró compose.yaml en $root."
}

New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$dumpPath = Join-Path $OutputDirectory "gestion-tickets-$timestamp.sql"

function Get-ContainerEnvironmentValue([string]$name) {
    $value = docker compose -f $composeFile exec -T mysql printenv $name 2>$null

    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($value)) {
        throw "No se pudo leer $name del contenedor MySQL."
    }

    return $value.Trim()
}

$database = Get-ContainerEnvironmentValue "MYSQL_DATABASE"
$rootPassword = Get-ContainerEnvironmentValue "MYSQL_ROOT_PASSWORD"
$container = "gestion-tickets-mysql"

Write-Host "Respaldando la base $database en $dumpPath ..."

# MYSQL_PWD evita exponer la contraseña en la lista de procesos.
$previousPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = $rootPassword

try {
    $arguments = @(
        "exec",
        "-e",
        "MYSQL_PWD",
        $container,
        "mysqldump",
        "-u",
        "root",
        "--single-transaction",
        "--routines",
        "--triggers",
        "--events",
        "--default-character-set=utf8mb4",
        $database
    )

    $process = Start-Process -FilePath "docker" `
        -ArgumentList $arguments `
        -NoNewWindow `
        -Wait `
        -PassThru `
        -RedirectStandardOutput $dumpPath

    if ($process.ExitCode -ne 0) {
        throw "mysqldump falló con código $($process.ExitCode)."
    }
} finally {
    if ($null -eq $previousPassword) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
        $env:MYSQL_PWD = $previousPassword
    }
}

if ((Get-Item $dumpPath).Length -le 0) {
    throw "El respaldo de la base quedó vacío."
}

$mediaPath = Join-Path $root "storage/app/public/media"

if (Test-Path $mediaPath) {
    $mediaArchive = Join-Path $OutputDirectory "media-$timestamp.zip"
    Write-Host "Respaldando multimedia en $mediaArchive ..."
    Compress-Archive -Path $mediaPath -DestinationPath $mediaArchive -CompressionLevel Optimal -Force
}

$cutoff = (Get-Date).AddDays(-$RetentionDays)

Get-ChildItem -Path $OutputDirectory -File |
    Where-Object { $_.LastWriteTime -lt $cutoff } |
    ForEach-Object {
        Write-Host "Eliminando respaldo antiguo: $($_.Name)"
        Remove-Item $_.FullName -Force
    }

Write-Host "Respaldo completado. Retención: $RetentionDays días."
