# Pruebas de carga con k6

Escenarios de carga para validar la Fase 6 antes del piloto. **No se ejecutan
contra producción**: usan un entorno de staging con una base de datos
desechable, porque la emisión genera tickets reales y consume la numeración.

## Requisitos

- [k6](https://k6.io/docs/get-started/installation/) instalado en la máquina
  que ejecuta la prueba.
- Aplicación desplegada en staging con `APP_ENV=staging` y `APP_DEBUG=false`.
- Usuario operador de prueba (`OPERATOR_EMAIL` / `OPERATOR_PASSWORD`).
- Base de datos respaldada o recreable al terminar.

## Límites de tasa

Los endpoints públicos tienen límites por IP (ver `config/tickets.php`). Una
prueba de carga desde una sola IP los alcanzaría de inmediato. En staging,
antes de ejecutar k6, se elevan temporalmente:

```bash
TICKETS_RATE_LIMIT_ISSUE=100000
TICKETS_RATE_LIMIT_DISPLAY=100000
TICKETS_RATE_LIMIT_CATALOG=100000
php artisan config:clear
```

Al terminar, se restauran los valores normales y se vuelve a ejecutar
`php artisan config:clear`.

## Escenarios

| Archivo | Objetivo | Métrica clave |
|---|---|---|
| `ticket-issuance.js` | Emisión concurrente; numeración y transacciones | p95 < 800 ms, errores < 1 % |
| `display-polling.js` | Varias pantallas consultando el snapshot cada 5 s | p95 < 400 ms, errores < 0,5 % |
| `console-polling.js` | Operadores autenticados consultando la cola | p95 < 500 ms, errores < 1 % |

## Ejecución

```bash
# Emisión de tickets (sección y categoría configurables)
k6 run -e BASE_URL=https://staging.example.test \
  -e SECTION=plataforma -e CATEGORY=general \
  tests/load/ticket-issuance.js

# Pantallas; si se indica token, se valida el filtrado por pantalla
k6 run -e BASE_URL=https://staging.example.test \
  -e DISPLAY_VUS=20 -e DISPLAY_TOKEN=<token> \
  tests/load/display-polling.js

# Consola de operador
k6 run -e BASE_URL=https://staging.example.test \
  -e OPERATOR_EMAIL=operador@demo.local -e OPERATOR_PASSWORD=password \
  tests/load/console-polling.js
```

## Interpretación y registro

- k6 imprime el resumen al final; guardar la salida en el registro del piloto
  (`docs/piloto-uat.md`) junto con fecha, commit desplegado y hardware.
- Si aparecen `429`, revisar los límites de tasa antes de concluir que hay un
  problema de capacidad.
- Si la emisión devuelve `409` o números duplicados, detener la prueba y
  revisar los bloqueos de `ticket_sequences`.
- Los escenarios de emisión no se repiten sobre la misma base sin limpiar:
  la numeración continúa y el volumen distorsiona los reportes.

## Después de la prueba

1. Restaurar los límites de tasa y limpiar la caché de configuración.
2. Revisar `storage/logs/laravel.log` por excepciones y timeouts.
3. Revisar la cola (`php artisan queue:monitor`) y las exportaciones pendientes.
4. Anotar los resultados y cualquier desviación en el registro del piloto.
