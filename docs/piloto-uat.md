# Plan de UAT y piloto — Fase 6

**Fecha:** 2026-11-06
**Estado:** planificado. **Ninguna actividad de este documento fue ejecutada
todavía**; los resultados deben registrarse a medida que se realicen.

## 1. Objetivo

Validar el sistema en una sola sección antes de extenderlo a las tres secciones
(Plataforma, ODECO y Cajas), con criterios de aceptación, métricas y un registro
de incidentes que permita decidir la expansión.

## 2. Alcance propuesto

**Sección piloto recomendada: Cajas (G/H/I).** Es la sección con menor
complejidad porque solo gestiona cola (Q-13) y no tiene servicios exclusivos.
La elección debe confirmarse con la institución; si se prefiere validar la
sección con más demanda, usar Plataforma.

**Fuera del alcance del piloto:**

- Las otras dos secciones (permanecen en el sistema legacy o en paralelo).
- Impresión en red centralizada (se usa impresora local USB/QZ Tray).
- Integración con sistemas externos.
- App móvil o notificaciones al ciudadano.

## 3. Roles

| Rol | Responsabilidad |
|---|---|
| Aprobador del piloto | Autoriza el inicio, el pase a producción y la expansión |
| Responsable de operación | Define horarios, ventanillas y personal |
| Soporte técnico | Atiende incidentes, revisa logs y ejecuta el runbook |
| Operadores | Usan la consola y registran observaciones |
| Quiosco / recepción | Apoya a los ciudadanos y reporta problemas de emisión |
| Observador UAT | Ejecuta los casos y firma los resultados |

## 4. Criterios de entrada

- [ ] Fase 5 completa y suite de pruebas en verde.
- [ ] Revisión de seguridad revisada y checklist de producción cumplido.
- [ ] Pruebas de carga ejecutadas en staging y resultados registrados.
- [ ] Pruebas de caos ejecutadas (Reverb, cola, impresora).
- [ ] Ensayo de respaldo y restauración completado.
- [ ] Runbook de operación aprobado y contactos completados.
- [ ] Usuarios, ventanillas, quiosco y pantalla del piloto configurados.
- [ ] Capacitación breve a operadores y recepción.
- [ ] Canal de incidentes disponible (grupo, teléfono o mesa de ayuda).

## 5. Casos de UAT

Registrar `OK`, `FALLA` u `OBSERVADO` y la evidencia (captura, ticket, log).

| ID | Caso | Pasos | Resultado esperado |
|---|---|---|---|
| T-UAT-01 | Emisión general | En el quiosco elegir Cajas → General | Ticket con código G y número correlativo; aparece en pantalla |
| T-UAT-02 | Emisión preferencial | Elegir Tercera edad o Discapacidad | Ticket H (misma numeración de preferencial), marcado preferencial |
| T-UAT-03 | Embarazo | Elegir Embarazo | Ticket I, categoría separada, cola preferencial |
| T-UAT-04 | Servicio exclusivo | Elegir un servicio de la sección | Ticket con código general y etiqueta del servicio |
| T-UAT-05 | Apertura de jornada | Operador inicia sesión y elige ventanilla | Sesión activa, ventanilla asignada |
| T-UAT-06 | Llamar siguiente | Pulsar “Siguiente” | Se llama el ticket con prioridad 2:1 y aging; pantalla y voz lo anuncian |
| T-UAT-07 | Rellamado | Pulsar “Rellamar” | Aumenta `call_count`; la pantalla vuelve a anunciar |
| T-UAT-08 | Iniciar y finalizar | Pulsar “Iniciar” y luego “Finalizar” | Estados `in_service` y `finished`; tiempos registrados |
| T-UAT-09 | No-show | Llamar dos veces y esperar 1 minuto | Pasa a `no_show` sin reencolar |
| T-UAT-10 | Transferencia | Transferir un ticket `waiting` o `called` a otra sección | Conserva código y número; aparece en la sección destino |
| T-UAT-11 | Reimpresión | Pulsar “Reimprimir” | Se imprime de nuevo; se registra el evento sin cambiar estado |
| T-UAT-12 | Fallo de impresión | Simular impresora apagada y registrar fallo | Se registra `print_failed`; el ticket sigue visible |
| T-UAT-13 | Cierre de jornada | Ejecutar el cierre a la hora configurada | `waiting → expired`, `called → no_show`, `in_service → finished`; sesiones cerradas |
| T-UAT-14 | Pantalla y audio | Abrir la pantalla con token, activar audio y llamar | Muestra solo las secciones asignadas; timbre y voz con código, número y ventanilla |
| T-UAT-15 | Pantalla inactiva | Desactivar la pantalla y recargar | Responde 404 o deja de actualizar; el enlace no sirve |
| T-UAT-16 | Quiosco con token | Abrir el quiosco con su token | Solo muestra las secciones habilitadas |
| T-UAT-17 | Reportes | Consultar resumen y detalle por fecha, sección y ventanilla | Totales coherentes con los tickets del día |
| T-UAT-18 | Exportación CSV | Solicitar exportación y descargar | Archivo con BOM, encabezados y filas; se abre correctamente |
| T-UAT-19 | Permisos | Un operador intenta abrir administración | 403; no puede ver usuarios, configuración ni reportes |
| T-UAT-20 | Operador de otra sección | Intentar operar un ticket ajeno | 403; no puede llamar, iniciar ni finalizar |
| T-UAT-21 | Reverb caído | Detener Reverb durante la operación | La UI sigue actualizando por polling; no se pierden acciones |
| T-UAT-22 | Respaldo | Ejecutar el respaldo al cierre | Archivo generado y verificable |
| T-UAT-23 | Restauración | Restaurar en una base temporal | Conteos y datos coinciden; la aplicación funciona |
| T-UAT-24 | Reinicio | Reiniciar aplicación y worker | La sesión del operador se recupera o se reabre; no hay tickets corruptos |

## 6. Métricas del piloto

| Métrica | Objetivo | Fuente |
|---|---|---|
| Incidentes P1 (operación detenida) | 0 | Registro de incidentes |
| Incidentes P2 (función degradada) | ≤ 2, resueltos en < 1 h | Registro |
| Emisiones exitosas | ≥ 99 % | Logs y reportes |
| Fallos de impresión | ≤ 2 % | Eventos `print_failed` |
| No-show | ≤ 10 % | Reporte de tickets |
| Espera promedio | Comparable al proceso manual | Reporte |
| Latencia de pantalla | p95 < 1 s | k6 / monitoreo |
| Satisfacción de operadores | ≥ 4/5 | Encuesta breve |
| Tickets emitidos fuera de horario | 0 | Reporte |

Los umbrales son propuestas iniciales; deben aprobarse antes del piloto.

## 7. Cronograma propuesto

| Etapa | Duración | Actividades |
|---|---|---|
| Preparación | 3 días | Configuración, capacitación, cargas de prueba, respaldo |
| Sombra | 3 días | Operación en paralelo con el proceso actual, sin reemplazarlo |
| Piloto live | 5 días hábiles | Uso real en Cajas, seguimiento diario y ajustes |
| Cierre | 2 días | Análisis de métricas, informe y decisión go/no-go |

## 8. Registro de incidentes

| Fecha/hora | ID | Severidad | Descripción | Acción tomada | Responsable | Estado |
|---|---|---|---|---|---|---|
| | | | | | | |

Severidad:

- **P1:** no se pueden emitir o llamar tickets; operación detenida.
- **P2:** una función falla pero hay alternativa (impresión, audio, exportación).
- **P3:** molestia menor o consulta.

## 9. Criterios de salida y go/no-go

**Go para expandir si:**

- [ ] Todos los casos T-UAT-01 a T-UAT-24 están `OK` u `OBSERVADO` con mitigación.
- [ ] Cero incidentes P1.
- [ ] Los P2 se resolvieron dentro del plazo.
- [ ] Las métricas cumplen los objetivos acordados.
- [ ] El respaldo y la restauración se ensayaron con éxito.
- [ ] Operadores y recepción aprueban la usabilidad.
- [ ] El runbook y los contactos están actualizados.

**No-go si:**

- Hay pérdida o duplicación de tickets sin explicación.
- La numeración o la prioridad no son confiables.
- No se puede restaurar un respaldo.
- Un incidente de seguridad requiere rediseño.

## 10. Aprobaciones

| Rol | Nombre | Firma | Fecha |
|---|---|---|---|
| Aprobador del piloto | | | |
| Responsable de operación | | | |
| Soporte técnico | | | |
| Observador UAT | | | |

## 11. Resultados

Completar al finalizar. **No inventar resultados**: adjuntar salidas de k6,
capturas, reportes y el registro de incidentes.

| Actividad | Fecha | Resultado | Evidencia |
|---|---|---|---|
| Pruebas de carga | | | |
| Caos: Reverb | | | |
| Caos: cola | | | |
| Caos: impresora | | | |
| Respaldo | 2026-11-06 | OK (local) | `scripts/backup.ps1` generó un volcado válido de 74 507 bytes con 36 tablas. El archivo temporal se eliminó. |
| Restauración | 2026-11-06 | OK (local) | Restaurado en `gestion_tickets_restore`: 36/36 tablas y conteos idénticos (`tickets` 17, `users` 2, `audit_logs` 0, `ticket_events` 30, `migrations` 34). `migrate:status` y `tickets:health` en verde. Base temporal eliminada. |
| UAT | | | |
| Piloto live | | | |
