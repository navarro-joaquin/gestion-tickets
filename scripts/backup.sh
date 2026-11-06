#!/usr/bin/env bash
#
# Respaldo de base de datos y multimedia (Linux/macOS + Docker Compose).
#
# Uso:
#   ./scripts/backup.sh
#   BACKUP_DIR=/var/backups/gestion-tickets RETENTION_DAYS=30 ./scripts/backup.sh
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/compose.yaml"
BACKUP_DIR="${BACKUP_DIR:-${ROOT_DIR}/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "${BACKUP_DIR}"

echo "Respaldando la base de datos en ${BACKUP_DIR} ..."

# Las variables MYSQL_ROOT_PASSWORD y MYSQL_DATABASE viven en el contenedor,
# así que la contraseña no aparece en la línea de comandos del host.
docker compose -f "${COMPOSE_FILE}" exec -T mysql sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" \
        --single-transaction --routines --triggers --events \
        --default-character-set=utf8mb4 "$MYSQL_DATABASE"' \
    | gzip -9 > "${BACKUP_DIR}/gestion-tickets-${TIMESTAMP}.sql.gz"

MEDIA_DIR="${ROOT_DIR}/storage/app/public/media"

if [ -d "${MEDIA_DIR}" ]; then
    echo "Respaldando multimedia ..."
    tar -czf "${BACKUP_DIR}/media-${TIMESTAMP}.tar.gz" -C "${ROOT_DIR}/storage/app/public" media
fi

find "${BACKUP_DIR}" -maxdepth 1 -type f \
    \( -name 'gestion-tickets-*.sql.gz' -o -name 'media-*.tar.gz' \) \
    -mtime "+${RETENTION_DAYS}" -print -delete

echo "Respaldo completado. Retención: ${RETENTION_DAYS} días."
