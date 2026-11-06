# Matriz de pruebas de caos — Fase 6

**Estado:** planificada. **No ejecutada todavía.** Ejecutar en staging, nunca en
producción, con un respaldo reciente y una persona observando la operación.

## Objetivo

Comprobar que el sistema degrada de forma controlada cuando falla una
dependencia y que la operación puede continuar o recuperarse sin perder tickets.

## Preparación

- [ ] Base de staging respaldada y recreable.
- [ ] Al menos un operador, una ventanilla, un quiosco y una pantalla configurados.
- [ ] Worker de cola y scheduler en ejecución.
- [ ] Acceso a los logs (`storage/logs/laravel.log`) y a `php artisan tickets:health`.
- [ ] Canal de comunicación para anunciar el inicio y el fin de cada simulacro.
- [ ] Registro de evidencia abierto (fecha, commit, resultado, capturas).

## Casos

### C-01 — Reverb caído

**Procedimiento:**

1. Abrir consola y pantalla; confirmar que actualizan en tiempo real.
2. Detener Reverb (`Ctrl+C` o matar el proceso `reverb:start`).
3. Emitir un ticket y llamarlo desde la consola.
4. Observar la pantalla durante 15 segundos.
5. Reiniciar Reverb.

**Esperado:**

- La consola y la pantalla siguen actualizando por polling cada 5 segundos.
- El ticket se emite, se llama y se atiende sin errores 500.
- La pantalla muestra “Actualización automática cada 5 segundos”.
- Al volver Reverb, la actualización en tiempo real se recupera sin recargar.

**Evidencia:** captura de pantalla, logs, hora de recuperación.

### C-02 — Worker de cola detenido

**Procedimiento:**

1. Detener `php artisan queue:work`.
2. Solicitar una exportación CSV desde administración.
3. Verificar que queda en “En cola”.
4. Reiniciar el worker.
5. Descargar el archivo.

**Esperado:**

- La solicitud responde 202 y queda `queued`.
- Al reiniciar el worker, pasa a `completed` y el CSV se descarga.
- `tickets:health` advierte trabajos pendientes mientras el worker está caído.

**Evidencia:** estado de la exportación antes y después, archivo descargado.

### C-03 — Impresora apagada o sin conexión

**Procedimiento:**

1. Apagar la impresora o detener el servicio local/QZ Tray.
2. Emitir un ticket desde el quiosco.
3. Verificar el mensaje en pantalla.
4. Llamar el ticket desde la consola y reimprimir.
5. Registrar el fallo de impresión si corresponde.
6. Encender la impresora y reimprimir.

**Esperado:**

- El ticket se emite y aparece en la pantalla aunque no imprima.
- El quiosco muestra “No se pudo imprimir. Conserve este código en pantalla.”
- La reimpresión falla de forma controlada y se registra `print_failed`.
- Al volver la impresora, la reimpresión funciona.
- El estado del ticket no cambia por un fallo de impresión.

**Evidencia:** ticket en pantalla, evento `print_failed`, reimpresión exitosa.

### C-04 — Redis caído (solo si está habilitado)

La configuración actual usa `CACHE_STORE=database` y
`QUEUE_CONNECTION=database`, por lo que Redis no es obligatorio. Si se habilita
Redis para caché, sesiones o colas:

1. Detener Redis.
2. Emitir y llamar tickets.
3. Revisar si la aplicación falla, degrada o pierde sesiones.
4. Reiniciar Redis.

**Esperado (con la configuración actual):** no aplica; el sistema no usa Redis.
**Esperado (si se habilita):** definir y documentar el comportamiento; no se
recomienda usarlo como dependencia única sin plan de contingencia.

### C-05 — Reinicio de MySQL

**Procedimiento:**

1. Con la operación activa, reiniciar el contenedor MySQL.
2. Emitir un ticket durante la indisponibilidad.
3. Esperar a que el healthcheck pase.
4. Emitir y llamar de nuevo.

**Esperado:**

- Durante la caída, la API responde error controlado (5xx) y no crea tickets
  parciales.
- Al recuperarse, la numeración continúa sin duplicados ni saltos inexplicables.
- `tickets:health` vuelve a `OK`.
- Las sesiones de operador pueden requerir reapertura.

**Evidencia:** logs de error, numeración antes/después, estado de salud.

### C-06 — Pantalla sin red

**Procedimiento:**

1. Desconectar la red de una pantalla durante 30 segundos.
2. Llamar tickets en ese intervalo.
3. Reconectar.

**Esperado:**

- La pantalla conserva el último snapshot visible.
- Al reconectar, el polling recupera el estado actual y muestra los llamados
  pendientes.
- El audio se reactiva si el navegador lo permite; si no, se pulsa el botón.

**Evidencia:** captura antes/después, hora de reconexión.

### C-07 — Disco lleno (opcional, staging)

**Procedimiento:**

1. Llenar un volumen temporal hasta el 95 %.
2. Intentar subir un contenido y generar una exportación.
3. Liberar espacio.

**Esperado:**

- La subida falla con un mensaje claro y no deja archivos huérfanos.
- La exportación falla y queda en estado `failed`.
- La operación de tickets continúa.
- Al liberar espacio, ambas funciones se recuperan.

**Evidencia:** mensajes, estado de la exportación, espacio liberado.

## Registro de ejecución

| Caso | Fecha/hora | Commit | Resultado | Observaciones | Responsable |
|---|---|---|---|---|---|
| C-01 Reverb | | | | | |
| C-02 Cola | | | | | |
| C-03 Impresora | | | | | |
| C-04 Redis | | | | | |
| C-05 MySQL | | | | | |
| C-06 Pantalla sin red | | | | | |
| C-07 Disco lleno | | | | | |

## Limpieza

- Reiniciar todos los servicios detenidos.
- Verificar `php artisan tickets:health`.
- Revisar que no queden tickets de prueba activos; cerrar la jornada de staging
  o recrear la base.
- Registrar los incidentes y las mejoras en el runbook.
