# Revisión de seguridad — Fase 6

**Fecha:** 2026-11-06
**Alcance:** aplicación Laravel + Vue, endpoints públicos y autenticados, almacenamiento multimedia, exportaciones, respaldos y configuración de despliegue.
**Base revisada:** commit `a0eac3d` (Fase 5C) más los endurecimientos de esta fase.

## Resumen

| ID | Hallazgo | Severidad | Estado |
|---|---|---|---|
| S-01 | Endpoints públicos sin límite de tasa | Alta | Corregido: `throttle` por IP en catálogo, emisión y snapshot |
| S-02 | Respuestas sin cabeceras de seguridad | Media | Corregido: `SecurityHeaders` global |
| S-03 | Inyección de fórmulas en exportaciones CSV | Media | Corregido: neutralización de celdas que empiezan con `= + - @` |
| S-04 | Emisión pública sin token de quiosco | Media | Corregido: `POST /api/v1/tickets` exige un token de quiosco activo y la sección habilitada |
| S-05 | Tokens de quiosco y pantalla viajan en la URL | Baja | Aceptado; `Referrer-Policy: no-referrer` y rotación de token |
| S-06 | Cookies de sesión sin `Secure` por defecto | Media | Requiere configuración de producción (`SESSION_SECURE_COOKIE=true`) |
| S-07 | Secretos de Reverb de ejemplo | Alta si se reutilizan | Requiere rotación en producción; documentado en el runbook |
| S-08 | Sin Content-Security-Policy | Baja | Pendiente; requiere probar con los assets de Vite |
| S-09 | Respaldos con datos personales sin cifrar | Media | Requiere permisos restringidos y cifrado en el destino |
| S-10 | Auditoría modificable por el usuario de base de datos | Media | Mitigado con permisos de base de datos y respaldos; no hay inmutabilidad a nivel motor |

## Detalle

### S-01 Límites de tasa en endpoints públicos

Antes de esta fase, `POST /api/v1/tickets`, `GET /api/v1/sections` y
`GET /api/v1/display/snapshot` no tenían límite. Un cliente podía agotar la
numeración o saturar el snapshot.

Ahora `config/tickets.php` define límites por IP y `AppServiceProvider` los
registra:

- `public-catalog`: 120 req/min (secciones y quiosco).
- `public-tickets`: 30 req/min (emisión).
- `public-display`: 240 req/min (snapshot; 12 req/min por pantalla cada 5 s).

Son configurables por variables de entorno `TICKETS_RATE_LIMIT_*`. En redes con
varias pantallas detrás de una misma IP/NAT hay que ajustar
`TICKETS_RATE_LIMIT_DISPLAY` o separar por proxy.

**Verificación:** `tests/Feature/Hardening/SystemHardeningTest.php`.

### S-02 Cabeceras de seguridad

`App\Http\Middleware\SecurityHeaders` se aplica globalmente y añade:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: no-referrer`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `Strict-Transport-Security` solo en peticiones HTTPS.

### S-03 Inyección de fórmulas en CSV

Los reportes exportan nombres de sección, servicio, ventanilla y operador. Una
celda como `=HYPERLINK(...)` podía ejecutarse al abrir el archivo en Excel o
LibreOffice. `TicketCsvWriter::neutralizeFormula()` antepone una comilla simple
a los valores que empiezan con `=`, `+`, `-`, `@`, tabulador o retorno de carro.

### S-04 Emisión sin token de quiosco

`POST /api/v1/tickets` exigía solo `section`, `category` o `service`; cualquier
cliente con acceso a la red podía emitir tickets. Ahora la emisión requiere el
campo `kiosk` con el token de un quiosco activo y la sección solicitada debe
estar habilitada para ese quiosco (`IssueTicketRequest`). El quiosco envía su
token desde el enlace `?quiosco={token}`.

La validación devuelve `422` con un mensaje genérico tanto para tokens
inexistentes como inactivos, y no crea el ticket. Se mantienen además:

1. El límite de 30 emisiones/min por IP.
2. La recomendación de publicar el quiosco solo en la red interna o VLAN de
   atención.
3. La revisión de tickets emitidos fuera de horario o en volumen anómalo.

**Limitación:** el token viaja en la URL del quiosco (ver S-05). Rotarlo exige
eliminar y recrear el quiosco, o actualizar el registro en base de datos.

**Verificación:** `Tests\Feature\Api\V1\TicketApiTest` (token ausente, inválido,
inactivo y sección no asignada).

### S-05 Tokens en la URL

Los enlaces `/quiosco?quiosco={token}` y `/pantalla?pantalla={token}` exponen el
token en el historial y en registros de proxy. Mitigaciones aplicadas:

- `Referrer-Policy: no-referrer` evita filtrarlo por referer.
- El token de pantalla se puede regenerar desde administración; el de quiosco
  se rota eliminando y recreando el quiosco.
- El token de pantalla solo permite leer el snapshot de sus secciones; no da
  acceso a la consola ni a administración.

No se deben registrar las URLs completas en logs de acceso sin sanear el query
string.

### S-06 Cookies de sesión

En producción configurar:

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
```

`SESSION_ENCRYPT=true` cifra el contenido de la sesión; es recomendable si se
guarda información sensible en ella.

### S-07 Secretos de Reverb y base de datos

`.env.example` contiene valores de ejemplo (`REVERB_APP_SECRET`,
`DB_PASSWORD`, `DB_ROOT_PASSWORD`). En producción:

- Generar secretos aleatorios distintos a los de ejemplo.
- No reutilizar la contraseña de root para la aplicación.
- Rotar credenciales antes de habilitar el piloto.
- Verificar que `.env` no esté versionado (ya está en `.gitignore`).

### S-08 Content-Security-Policy

No se añadió CSP para no romper los assets de Vite ni los estilos de Vue. Antes
de añadirla, probar en staging con:

```
default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self' wss://<host-reverb>
```

### S-09 Respaldos

Los respaldos contienen tickets, nombres de operadores y auditoría. Almacenarlos
en un volumen con permisos restringidos, cifrado en reposo y retención definida.
No copiarlos al repositorio (`/backups` está en `.gitignore`).

### S-10 Auditoría

`audit_logs` es de solo inserción desde la aplicación, pero el usuario de base de
datos puede modificarla. Restringir el usuario de la aplicación a `SELECT`,
`INSERT`, `UPDATE`, `DELETE` sobre las tablas operativas y evitar credenciales
de root en la aplicación. Los respaldos permiten reconstruir la auditoría ante
una alteración.

## Controles verificados

- Login con `throttle:6,1` y contraseña mínima de 8 caracteres.
- Rutas de consola exigen sesión y rol `admin`, `supervisor` u `operador`.
- Rutas de administración exigen rol `admin`.
- Un operador solo puede operar tickets de su sección y asignados a él
  (`TicketPolicy`).
- La pantalla pública no recibe datos personales.
- La emisión exige un token de quiosco activo y que la sección esté habilitada
  para ese quiosco.
- Los archivos multimedia se validan por MIME y el tipo declarado debe
  coincidir con el archivo.
- Las exportaciones y descargas exigen rol `admin`.
- Los tokens de pantalla inactivos o inexistentes responden 404.
- Las pruebas cubren autorización, transferencias, prioridades, reportes,
  multimedia y endurecimiento.

## Checklist antes del piloto

- [ ] `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] `SESSION_SECURE_COOKIE=true` y HTTPS con certificado válido.
- [ ] Rotar `APP_KEY` solo antes de la primera puesta en marcha (rotarla después
      invalida sesiones y datos cifrados).
- [ ] Rotar contraseñas de base de datos y secretos de Reverb.
- [ ] Verificar permisos del usuario de base de datos.
- [ ] Restringir el acceso de red a MySQL (no exponer 3308 públicamente).
- [ ] Configurar `Referrer-Policy` también en Nginx si hay proxies intermedios.
- [ ] Revisar que los logs no incluyan tokens, contraseñas ni cuerpos completos.
- [ ] Probar la restauración de un respaldo (ver runbook).
- [ ] Definir responsables y canal de incidentes del piloto.

## Pendientes recomendados

1. Evaluar CSP en staging (S-08).
2. Añadir alertas automáticas de volumen anómalo de emisión y de tickets
   emitidos fuera de horario.
3. Revisar permisos de base de datos con el equipo de infraestructura (S-10).
