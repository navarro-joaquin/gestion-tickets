# Runbook de operación — Gestión de Tickets

**Versión:** Fase 6 (2026-11-06)
**Audiencia:** soporte técnico, infraestructura y responsables de la oficina.

## 1. Servicios y dependencias

| Servicio | Comando / ubicación | Puerto | Crítico |
|---|---|---|---|
| MySQL 8.4 | `docker compose -f compose.yaml up -d mysql` | 3308 (host) | Sí |
| Aplicación Laravel | Nginx + PHP-FPM o `php artisan serve` en desarrollo | 8000 / 443 | Sí |
| Reverb (WebSocket) | `php artisan reverb:start` | 8080 | No; hay polling de respaldo |
| Worker de cola | `php artisan queue:work --tries=3 --timeout=120` | — | Para exportaciones CSV |
| Scheduler | `php artisan schedule:work` o cron `* * * * *` | — | Sí (cierre, no-show, purga) |
| Impresión local | Impresora configurada por quiosco en `/admin/impresoras` | 9100 | No; el ticket se muestra en pantalla |

Orden de arranque recomendado: MySQL → aplicación → Reverb → worker → scheduler.
En producción, Nginx y PHP-FPM los gestiona el sistema operativo o el contenedor
de la aplicación; este repositorio solo incluye el contenedor de MySQL.

## 2. Rutina diaria

### Antes de abrir

- [ ] `php artisan tickets:health` sin fallos.
- [ ] `docker ps` muestra `gestion-tickets-mysql` como `healthy`.
- [ ] La hora de cierre en `/admin/ajustes` es la correcta.
- [ ] Hay al menos una ventanilla activa por sección que abrirá.
- [ ] Los operadores tienen usuario activo y rol correcto.
- [ ] El quiosco y la pantalla cargan con su token.
- [ ] La pantalla tiene audio activo (botón “Activar audio”).
- [ ] Hay papel en la impresora y la impresora del quiosco está configurada y responde.

### Durante la jornada

- [ ] Revisar la cola de cada sección y los tickets en espera.
- [ ] Verificar que no haya tickets llamados sin operador asignado.
- [ ] Revisar `storage/logs/laravel.log` por errores repetidos.
- [ ] Revisar que el worker de cola esté vivo si se usan exportaciones.

### Al cierre

- [ ] Confirmar que la hora de cierre sea la configurada.
- [ ] El scheduler ejecuta `tickets:day:close` cada minuto; si no corre, ejecutarlo a mano.
- [ ] Verificar que no queden sesiones de operador abiertas.
- [ ] Ejecutar el respaldo (`scripts/backup.ps1` o `scripts/backup.sh`).
- [ ] Anotar incidentes en el registro del piloto.

## 3. Incidentes frecuentes

### 3.1 Reverb no responde

**Síntoma:** la consola y la pantalla tardan en actualizar; la pantalla muestra
“Actualización automática cada 5 segundos”.

**Impacto:** bajo. El frontend usa polling cada 5 segundos cuando Echo no está
disponible. Las acciones siguen funcionando.

**Acciones:**

1. Revisar el proceso `php artisan reverb:start`.
2. Reiniciarlo con el mismo usuario y variables de `.env`.
3. Verificar `REVERB_HOST`, `REVERB_PORT` y `VITE_REVERB_*`.
4. Confirmar que el puerto 8080 esté accesible desde los navegadores.
5. Si no se restaura, continuar con polling y registrar el incidente.

### 3.2 Worker de cola detenido

**Síntoma:** las exportaciones quedan en estado “En cola” y `tickets:health`
advierte trabajos pendientes.

**Acciones:**

```bash
php artisan queue:work --tries=3 --timeout=120
```

Verificar que `QUEUE_CONNECTION=database` y que la tabla `jobs` existe. Si hay
muchos trabajos, iniciar más de un worker o revisar el job que falla en
`failed_jobs`.

### 3.3 Scheduler detenido

**Síntoma:** no se cierran los tickets al final del día, no se marcan los
no-show o no se ejecuta la purga.

**Acciones:**

```bash
php artisan schedule:work
```

En producción con cron:

```cron
* * * * * cd /ruta/app && php artisan schedule:run >> /dev/null 2>&1
```

Ejecutar manualmente si ya pasó la hora:

```bash
php artisan tickets:day:close
php artisan tickets:no-show
```

### 3.4 MySQL no disponible

**Síntoma:** errores 500, `SQLSTATE[HY000] [2002] Connection refused`.

**Acciones:**

```bash
docker compose -f compose.yaml ps
docker compose -f compose.yaml up -d mysql
docker compose -f compose.yaml logs --tail=100 mysql
```

Esperar a que el healthcheck pase y ejecutar `php artisan tickets:health`. Si el
volumen está corrupto, restaurar el último respaldo (sección 5).

### 3.5 Impresora o servicio de impresión caído

**Síntoma:** el quiosco muestra “No se pudo imprimir. Conserve este código en
pantalla.”

**Impacto:** el ticket ya fue emitido y aparece en la pantalla; la operación
continúa. La consola del operador no imprime ni permite reimprimir.

**Acciones:**

1. Verificar papel, cable USB y energía.
2. Revisar que la impresora esté asociada al quiosco en `/admin/impresoras`.
3. Verificar el destino configurado en la impresora y, si usa el driver local,
   que el servicio escuche en ese destino o en `VITE_PRINT_SERVICE_URL`.
4. Si usa QZ Tray, confirmar que esté abierto y conectado.
5. Si la impresora no vuelve, anotar el código y número para atención manual.
6. Al restablecer la impresora, emitir un ticket de prueba desde el quiosco para
   confirmar que vuelve a imprimir.

### 3.6 Pantalla sin audio o sin contenido

**Acciones:**

1. Pulsar “Activar audio” (los navegadores bloquean el audio sin interacción).
2. Verificar que el sistema operativo tenga una voz española instalada.
3. Revisar `php artisan storage:link` y que las imágenes/videos existan en
   `storage/app/public/media`.
4. Confirmar que la pantalla tenga secciones y playlists activas.
5. Si el enlace dejó de funcionar, el token fue regenerado: copiar el enlace
   actualizado desde `/admin/pantallas`.

### 3.7 Tickets duplicados o numeración rara

**Acciones:**

1. No borrar tickets manualmente.
2. Revisar `ticket_sequences` de la sección y el día afectado.
3. Revisar los logs por errores de transacción o bloqueos.
4. Documentar los tickets afectados y consultar antes de corregir datos.

### 3.8 Disco lleno

**Síntoma:** errores al subir multimedia, generar exportaciones o escribir logs.

**Acciones:**

1. Revisar `df -h` y el tamaño de `storage/logs`, `storage/app/private/archives`
   y `storage/app/public/media`.
2. Rotar o comprimir logs antiguos.
3. Mover respaldos a otro volumen.
4. No borrar archivos de `media` referenciados por playlists activas.

## 4. Copias de seguridad

### 4.1 Crear un respaldo

Linux/macOS:

```bash
RETENTION_DAYS=30 ./scripts/backup.sh
```

Windows:

```powershell
pwsh scripts/backup.ps1 -RetentionDays 30
```

Genera:

- `backups/gestion-tickets-<fecha>.sql` (o `.sql.gz`) con la base completa.
- `backups/media-<fecha>.zip` / `.tar.gz` con los archivos multimedia.

El script de Windows fue probado localmente el 2026-11-06: generó un volcado
válido con las 36 tablas de la base `gestion_tickets`.

### 4.2 Verificar el respaldo

```bash
# Linux
gzip -t backups/gestion-tickets-<fecha>.sql.gz
grep -c 'CREATE TABLE' <(gzip -dc backups/gestion-tickets-<fecha>.sql.gz)

# Windows
Select-String -Path backups\gestion-tickets-<fecha>.sql -Pattern 'CREATE TABLE' |
  Measure-Object
```

Un respaldo válido debe contener las tablas principales (`tickets`, `sections`,
`users`, `audit_logs`, `media_assets`).

### 4.3 Restaurar

**Antes de restaurar:** detener la aplicación y el worker para evitar escrituras.

Linux:

```bash
docker compose exec -T mysql sh -c \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS gestion_tickets; CREATE DATABASE gestion_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"'

gzip -dc backups/gestion-tickets-<fecha>.sql.gz | \
  docker compose exec -T mysql sh -c \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" gestion_tickets'
```

Windows (PowerShell, usando `cmd` para preservar los bytes):

```powershell
$env:MYSQL_PWD = '<contraseña-root>'
cmd /c "docker exec -i -e MYSQL_PWD gestion-tickets-mysql mysql -u root -e ""DROP DATABASE IF EXISTS gestion_tickets; CREATE DATABASE gestion_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"""
cmd /c "docker exec -i -e MYSQL_PWD gestion-tickets-mysql mysql -u root gestion_tickets < ""backups\gestion-tickets-<fecha>.sql"""
Remove-Item Env:MYSQL_PWD
```

Restaurar multimedia:

```bash
# Linux
tar -xzf backups/media-<fecha>.tar.gz -C storage/app/public

# Windows
Expand-Archive -Path backups\media-<fecha>.zip -DestinationPath storage\app\public -Force
```

Después: `php artisan migrate --force` (por si el respaldo es anterior),
`php artisan config:clear`, `php artisan tickets:health` y una prueba funcional
de emisión y llamado.

### 4.4 Objetivos propuestos

| Métrica | Objetivo propuesto | Nota |
|---|---|---|
| RPO (pérdida máxima de datos) | 24 horas | Respaldo diario al cierre |
| RTO (tiempo de restauración) | 2 horas | Ensayo trimestral obligatorio |
| Retención local | 14 días | Configurable con `RETENTION_DAYS` |

Estos valores deben ser aprobados por la institución antes del piloto.

### 4.5 Ensayo de restauración

1. Crear un respaldo.
2. Levantar una base temporal `gestion_tickets_restore` (nunca sobre la base productiva).
3. Restaurar allí y ejecutar `php artisan migrate:status`.
4. Comparar conteos de `tickets`, `users` y `audit_logs` con producción.
5. Registrar fecha, responsable y resultado en el registro del piloto.

El usuario de la aplicación solo tiene permisos sobre `gestion_tickets` y
`gestion_tickets_test` (mínimo privilegio). Para validar la base temporal con
`migrate:status` o `tickets:health` hay que otorgarle permisos temporales y
revocarlos al terminar:

```sql
GRANT ALL PRIVILEGES ON gestion_tickets_restore.* TO 'gestion_tickets'@'%';
-- validar...
REVOKE ALL PRIVILEGES ON gestion_tickets_restore.* FROM 'gestion_tickets'@'%';
DROP DATABASE gestion_tickets_restore;
FLUSH PRIVILEGES;
```

El ensayo local del 2026-11-06 confirmó el procedimiento: 36/36 tablas y conteos
idénticos; `migrate:status` y `tickets:health` en verde. El ensayo formal en el
entorno de producción sigue pendiente y debe hacerse sobre una base temporal,
nunca sobre la base productiva.

## 5. Despliegue y reversión

### 5.1 Despliegue

```bash
php artisan down --retry=60
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Verificar `php artisan tickets:health`, `/up` y una emisión de prueba.

### 5.2 Reversión

1. Volver al commit anterior: `git checkout <commit-anterior>`.
2. Reinstalar dependencias y reconstruir assets.
3. Si la migración nueva no es compatible, evaluar `php artisan migrate:rollback --step=1`
   **solo** si no hay datos nuevos que se perderían.
4. Restaurar el respaldo si hubo corrupción de datos.
5. Limpiar cachés: `php artisan optimize:clear`.
6. Verificar `/up`, `tickets:health` y una emisión.

## 6. Monitoreo y alertas

| Señal | Umbral sugerido | Acción |
|---|---|---|
| `/up` | 2 fallos consecutivos | Revisar PHP-FPM, Nginx y base |
| `tickets:health` | cualquier `FALL` | Ejecutar la sección de incidentes correspondiente |
| Cola pendiente | > 100 jobs | Revisar worker y `failed_jobs` |
| Errores 5xx | > 1 % en 5 min | Revisar logs y base |
| Latencia snapshot | p95 > 1 s | Revisar base, Reverb y cantidad de pantallas |
| Disco | > 80 % | Liberar logs/archivos o ampliar volumen |
| Tickets emitidos fuera de horario | > 0 | Revisar acceso al quiosco |

## 7. Contactos y escalamiento

Completar antes del piloto:

| Rol | Nombre | Teléfono / canal |
|---|---|---|
| Responsable de operación | | |
| Soporte técnico | | |
| Infraestructura / base de datos | | |
| Proveedor de red / impresoras | | |
| Aprobador del piloto | | |

## 8. Registro de cambios del runbook

| Fecha | Cambio | Responsable |
|---|---|---|
| 2026-11-06 | Versión inicial de la Fase 6 | Equipo de implementación |
