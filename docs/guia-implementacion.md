# Guía de implementación — Gestión de Tickets

**Proyecto:** `gestion-tickets`  
**Estado:** Fases 1 y 2 completadas; núcleo de tickets operativo en entorno local.  
**Última actualización:** 2026-11-06

---

## 1. Objetivo de esta guía

Documentar cómo está montado el proyecto, cómo levantarlo y en qué orden implementar el sistema de generación y gestión de tickets descrito en el diseño técnico. Es un documento vivo: debe actualizarse cuando se cierren decisiones pendientes o cambie la infraestructura.

Esta guía describe el estado real del repositorio. A la fecha están implementadas las fundaciones de secciones/categorías/matriz (Fase 1) y el núcleo de emisión, llamado, atención y cierre de tickets (Fase 2). Los módulos de operación avanzada, prioridades, administración, reportes y multimedia siguen pendientes (Fases 3 a 6).

---

## 2. Estado actual del repositorio

| Componente | Versión / estado |
|---|---|
| PHP | 8.4.27 |
| Laravel Framework | 13.40.0 |
| Composer | 2.10.3 |
| MySQL | 8.4.13 LTS (contenedor Docker) |
| Docker Engine | 29.8.2 |
| Docker Compose | 5.5.1 |
| Node.js / npm | 24.19.0 / 12.0.2 |
| Laravel Boost | 2.10.4 (dev) |
| Frontend | Vite 8 + Tailwind 4 + Vue 3 + TypeScript; apps `kiosk` y `console` |
| Redis / Reverb / Echo | Reverb instalado (`dev-main`) y Echo/Pusher JS en el frontend; Redis no habilitado todavía |
| Módulos de dominio | Fases 1 y 2: matriz de códigos y núcleo de tickets |
| Migraciones ejecutadas | Base de Laravel + `sections`, `categories`, `section_ticket_codes`, `section_code_category`, `users.role`/`is_active`, `ticket_sequences`, `tickets`, `ticket_assignments`, `ticket_events` |

### Archivos relevantes creados

```text
gestion-tickets/
├── compose.yaml                  # Contenedor MySQL 8.4 LTS
├── .env                          # Configuración local (no se versiona)
├── .env.example                  # Plantilla versionada (Reverb, Vite, impresión)
├── app/
│   ├── Domain/
│   │   ├── Sections/             # Fase 1: secciones, categorías, matriz y gate
│   │   └── Ticketing/            # Fase 2: modelos, acciones y formateador ESC/POS
│   ├── Console/Commands/         # tickets:no-show, tickets:day:close
│   ├── Events/TicketQueueChanged.php
│   └── Http/
│       ├── Controllers/Api/V1/   # SectionController, Ticket, Console, Display
│       ├── Requests/Api/V1/
│       └── Resources/TicketResource.php
├── database/
│   ├── migrations/               # Fase 1 + Fase 2
│   ├── factories/Domain/Ticketing/
│   └── seeders/                  # CategorySeeder, SectionSeeder
├── resources/
│   ├── js/
│   │   ├── apps/kiosk/           # Quiosco Vue 3
│   │   ├── apps/console/         # Consola Vue 3
│   │   └── shared/               # API, tipos, Echo e impresión
│   └── views/                    # kiosk.blade.php, console.blade.php
├── routes/
│   ├── api.php                   # API v1
│   ├── channels.php              # Canal sections.{slug}
│   └── console.php               # Scheduler
├── tests/
│   ├── Feature/                  # T-MAP, T-SEQ, API
│   └── Unit/Ticketing/           # Máquina de estados
└── docs/
    ├── guia-implementacion.md              # Este documento
    ├── formulario-decisiones.html          # Formulario interactivo de Fase 0
    └── decisiones-tickets-2026-11-06.md    # Acta firmada de decisiones
```

La carpeta `database/database.sqlite` generada por el instalador fue eliminada: la fuente de verdad es MySQL.

---

## 3. Requisitos

- PHP 8.4+ con `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `redis` (para fases posteriores).
- Composer 2.
- Docker Desktop en ejecución.
- Node.js 22+ y npm (para las interfaces Vue).
- Git.
- Opcional: Herd para servir el proyecto como `http://gestion-tickets.test`.

Verificación rápida:

```powershell
php -v
composer -V
docker version
docker compose version
node -v
```

---

## 4. Puesta en marcha

### 4.1 Primera vez (clon nuevo)

```powershell
cd C:\Users\anavarro\Herd\gestion-tickets
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
docker compose up -d
php artisan migrate --seed
```

Antes de `docker compose up -d`, editar `.env` y definir credenciales reales. Los valores `change-me` de `.env.example` son solo marcadores.

> **Importante:** las variables `MYSQL_*` del contenedor solo se aplican la **primera vez** que se crea el volumen. Si el volumen ya existe, cambiarlas en `.env` no modifica usuarios ni contraseñas.

### 4.2 Día a día

```powershell
docker compose up -d          # asegurar MySQL
php artisan serve             # http://localhost:8000
# o, si Herd está activo: http://gestion-tickets.test
npm run dev                   # solo si se trabaja el frontend
php artisan schedule:work     # opcional: no-show y cierre de jornada
php artisan reverb:start      # opcional: tiempo real, con BROADCAST_CONNECTION=reverb
```

### 4.3 Verificación

```powershell
docker compose ps
php artisan migrate:status
php artisan about
php artisan tickets:codes:verify --strict
php artisan test --compact
npm run build
npm run typecheck
```

`docker compose ps` debe mostrar el contenedor como `healthy`. `tickets:codes:verify --strict` debe terminar con código `0` y las tres secciones en `Matriz OK` / `Operativa Sí`.

---

## 5. Contenedor MySQL 8.4 LTS

### 5.1 Servicio

| Propiedad | Valor |
|---|---|
| Archivo | `compose.yaml` |
| Servicio | `mysql` |
| Imagen | `mysql:8.4` (línea LTS; en esta máquina resolvió a 8.4.13) |
| Contenedor | `gestion-tickets-mysql` |
| Red | `gestion-tickets_default` |
| Puerto host | `127.0.0.1:3308` → `3306` del contenedor |
| Volumen | `gestion-tickets-mysql-data` → `/var/lib/mysql` |
| Charset | `utf8mb4` |
| Collation | `utf8mb4_0900_ai_ci` |
| Healthcheck | `mysqladmin ping` cada 10 s |
| Reinicio | `unless-stopped` |

**¿Por qué el puerto 3308?** En esta máquina el 3306 y el 3307 ya están ocupados por otros contenedores (`mariadb-db` y `alojamiento-mysql`). El puerto se publica solo en `127.0.0.1` para no exponer MySQL a la red local.

### 5.2 Usuarios

| Usuario | Contraseña | Uso | Alcance |
|---|---|---|---|
| `root` | `root` | Administración local del contenedor | Todos los privilegios |
| `gestion_tickets` | Definida en `.env` (`DB_PASSWORD`) | Conexión de Laravel | Solo `gestion_tickets.*` |

El usuario de la aplicación **no** es root. Laravel nunca debe usar `root` en `.env`.

> La contraseña `root` es aceptable únicamente en desarrollo local. Debe cambiarse antes de compartir el entorno, publicarlo o desplegarlo.

### 5.3 Comandos útiles

```powershell
# Estado y salud
docker compose ps
docker inspect --format "{{.State.Health.Status}}" gestion-tickets-mysql

# Logs
docker compose logs -f mysql

# Consola como root
docker compose exec mysql mysql -u root -proot

# Consola como usuario de la aplicación
docker compose exec mysql mysql -u gestion_tickets -p gestion_tickets

# Detener sin borrar datos
docker compose down

# Detener y borrar datos (DESTRUCTIVO)
docker compose down -v
```

### 5.4 Conexión desde un cliente gráfico

| Campo | Valor |
|---|---|
| Host | `127.0.0.1` |
| Puerto | `3308` |
| Base de datos | `gestion_tickets` |
| Usuario | `gestion_tickets` |
| Contraseña | La de `DB_PASSWORD` en `.env` |

### 5.5 Cambiar el puerto

1. Editar `compose.yaml`: `"127.0.0.1:3308:3306"`.
2. Editar `.env`: `DB_PORT=3308`.
3. Ejecutar `docker compose up -d`.

### 5.6 Cambiar contraseñas

Con el volumen ya inicializado, los cambios en `.env` no se propagan solos.

```sql
-- Dentro del contenedor, como root
ALTER USER 'gestion_tickets'@'%' IDENTIFIED BY 'nueva-contraseña';
ALTER USER 'root'@'localhost' IDENTIFIED BY 'nueva-contraseña-root';
FLUSH PRIVILEGES;
```

Después actualizar `.env` y ejecutar `php artisan config:clear`.

Alternativa destructiva en local: `docker compose down -v` y volver a levantar con las nuevas variables.

### 5.7 Respaldo rápido

```powershell
docker compose exec -T mysql mysqldump -u root -proot --single-transaction --routines --triggers gestion_tickets > respaldo.sql
```

Restauración:

```powershell
Get-Content respaldo.sql | docker compose exec -T mysql mysql -u root -proot gestion_tickets
```

---

## 6. Variables de entorno

### 6.1 Un solo `.env`

Laravel y Docker Compose leen el mismo archivo `.env`:

- Laravel usa `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Compose usa además `DB_ROOT_PASSWORD` para inicializar el contenedor.

`DB_ROOT_PASSWORD` no la usa Laravel. Mantenerla en `.env` es una comodidad local; en un entorno serio debe provenir de un gestor de secretos.

### 6.2 Reverb, Echo e impresión

- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`: servidor Reverb.
- `VITE_REVERB_*`: mismas credenciales expuestas al frontend por Vite (la clave pública, nunca el secreto).
- `BROADCAST_CONNECTION`: `log` o `null` por defecto; usar `reverb` para tiempo real.
- `VITE_PRINT_DRIVER`: `local` o `qz`; el quiosco permite cambiarlo y lo guarda en `localStorage`.
- `VITE_PRINT_SERVICE_URL`: URL del servicio local de impresión; por defecto `http://127.0.0.1:9100`.

### 6.3 Reglas

- `.env` está en `.gitignore`: nunca se versiona.
- `.env.example` solo contiene marcadores.
- No imprimir contraseñas en logs, capturas o documentación.
- Antes de desplegar, rotar `root` y el usuario de la aplicación.

---

## 7. Arquitectura objetivo

Monolito modular:

```text
Vue 3 + TypeScript
  ├── Quiosco
  ├── Consola de operador
  ├── Pantallas
  └── Administración
          │ HTTPS / WebSocket
          ▼
Laravel 13 (API REST + broadcasting)
  ├── MySQL 8.4
  ├── Redis (caché, colas, sesiones, pub/sub)
  ├── Reverb + Echo (tiempo real)
  └── Storage (multimedia)
```

Decisiones clave:

- La base de datos es la fuente de verdad; Redis no lo es.
- La numeración y el llamado se resuelven con transacciones y bloqueos en MySQL.
- Los eventos de tiempo real se publican después del commit y se complementan con un endpoint de snapshot.
- Cada interfaz es una aplicación Vue separada dentro del mismo repositorio, compartiendo tipos y cliente API.

---

## 8. Reglas de negocio no negociables

| ID | Regla |
|---|---|
| RN-01 | El quiosco nunca envía el código; el backend lo resuelve desde la matriz confirmada. |
| RN-02 | Una sección no opera si su matriz código→categoría no está confirmada y validada. |
| RN-03 | La numeración es independiente por `(sección, código, periodo)`. |
| RN-04 | Los números no se reutilizan; la emisión es transaccional. |
| RN-05 | Tercera edad y discapacidad comparten código, pero conservan `category_id` distinto. |
| RN-06 | Las estadísticas se agrupan por categoría, nunca derivando la categoría del código. |
| RN-07 | Un operador solo llama tickets de sus secciones habilitadas. |
| RN-08 | Un operador y una ventanilla no pueden tener más de un ticket activo. |
| RN-09 | Todo llamado y todo cambio de ventanilla queda en historial. |
| RN-10 | Los servicios exclusivos se autorizan en backend en cada llamado. |
| RN-11 | Existe una regla dura contra la postergación indefinida de tickets generales. |
| RN-12 | La auditoría es append-only. |

### 8.1 Matriz de códigos: confirmada

La asignación de letras quedó confirmada en el acta [decisiones-tickets-2026-11-06.md](decisiones-tickets-2026-11-06.md), firmada por Alvaro Navarro el **2026-11-06**:

| Sección | General | Embarazo | Preferencial compartido |
|---|---|---|---|
| Plataforma de Atención al Cliente | A | C | B |
| ODECO | D | F | E |
| Cajas | G | I | H |

Notas de vigencia:

- La categoría preferencial compartida cubre **tercera edad** y **discapacidad** con el mismo código, pero con `category_id` distinto para estadísticas.
- El código no determina la categoría: el backend resuelve la categoría desde esta matriz y la guarda en el ticket.
- Cualquier cambio futuro de esta matriz requiere una nueva acta y una migración de datos; no se edita en caliente.
- Si una sección quedara sin matriz válida, el sistema debe fallar cerrado: no emitir ni llamar tickets de esa sección.

---

## 9. Modelo de datos a implementar

Orden recomendado de migraciones (después de las tablas base de Laravel):

1. `sections`
2. `categories`
3. `section_ticket_codes`
4. `section_code_category`
5. `stations`
6. `section_user`
7. `operator_sessions`
8. `station_assignments`
9. `services`
10. `service_windows`
11. `service_operator`
12. `ticket_sequences`
13. `tickets`
14. `ticket_assignments`
15. `ticket_events`
16. `priority_profiles`
17. `kiosks`, `printers`, `print_jobs`
18. `media_assets`, `playlists`, `playlist_items`, `displays`, `display_playlist`, `announcements`
19. `audit_logs`
20. `daily_metrics`
21. `idempotency_keys`

Las tablas 1 a 4 están implementadas desde la Fase 1 (2026-11-06) y las tablas 12 a 15 desde la Fase 2. Detalle real:

### 9.1 `sections`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `slug` | varchar(50) | único; identificador de URL |
| `name` | varchar | nombre visible |
| `is_active` | boolean | por defecto `true` |
| `sort_order` | smallint unsigned | orden de presentación |
| `matrix_confirmed_at` | timestamp nullable | fecha de firma de la matriz |
| `matrix_confirmed_by` | varchar nullable | responsable de la firma |

Índice `(is_active, sort_order)`.

### 9.2 `categories`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `slug` | varchar(50) | único: `general`, `pregnancy`, `elderly`, `disability` |
| `name` | varchar | nombre visible |
| `is_preferential` | boolean | `false` solo para `general` |
| `sort_order` | smallint unsigned | orden de presentación |

Índice `(is_preferential, sort_order)`.

### 9.3 `section_ticket_codes`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `section_id` | FK `sections` | `cascadeOnDelete` |
| `code` | char(1) | A–I según la sección |
| `sort_order` | smallint unsigned | orden del código |
| `is_active` | boolean | por defecto `true` |

Único `(section_id, code)`; índice `(section_id, is_active, sort_order)`.

### 9.4 `section_code_category`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `section_ticket_code_id` | FK `section_ticket_codes` | `cascadeOnDelete` |
| `category_id` | FK `categories` | `cascadeOnDelete` |

Único `(section_ticket_code_id, category_id)`; índice `category_id`. Es la tabla pivote que materializa la matriz.

### 9.5 `users` (ampliación)

- `role` varchar(20), por defecto `operator`; valores `admin`, `supervisor`, `operator`.
- `is_active` boolean, por defecto `true`; un usuario inactivo no pasa el `Gate::before` de administración.
- Índice `role`.

### 9.6 `ticket_sequences`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `section_id` | FK `sections` | `cascadeOnDelete` |
| `code` | char(1) | código de la sección (A–I) |
| `period_key` | date | día de la numeración (`America/La_Paz`) |
| `last_number` | unsigned int | último número entregado; por defecto `0` |
| `timestamps` | — | — |

Único `(section_id, code, period_key)`. La emisión incrementa `last_number` dentro de la transacción con bloqueo `FOR UPDATE`; el número nunca se reutiliza aunque se borre un ticket.

### 9.7 `tickets`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `section_id` | FK `sections` | sección de origen/propietaria |
| `section_ticket_code_id` | FK `section_ticket_codes` | código que resolvió la matriz |
| `category_id` | FK `categories` | categoría declarada |
| `code` | char(1) | copia del código para reportes |
| `period_key` | date | día de emisión |
| `number` | unsigned int | consecutivo por sección y código |
| `status` | varchar(20) | `waiting`, `called`, `in_service`, `finished`, `no_show`, `cancelled`, `expired` |
| `station_label` | varchar(50) nullable | ventanilla del llamado actual |
| `operator_id` | FK `users` nullable | operador del llamado actual; sin `SET NULL` (los usuarios se desactivan) |
| `issued_at` | timestamp | emisión |
| `called_at` | timestamp nullable | último llamado |
| `call_count` | tinyint unsigned | llamados acumulados (máximo 2) |
| `started_at` / `finished_at` / `closed_at` | timestamp nullable | hitos de atención y cierre |
| `timestamps` | — | — |

Único `(section_id, code, period_key, number)`. Índices `(section_id, status, issued_at)` para la cola y `(section_id, category_id, issued_at)` para reportes.

Dos columnas generadas almacenadas impiden la doble atención:

- `active_station_key` = `station_label` cuando el estado es `called` o `in_service`, con índice único.
- `active_operator_key` = `operator_id` en los mismos estados, con índice único.

### 9.8 `ticket_assignments`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `ticket_id` | FK `tickets` | `cascadeOnDelete` |
| `operator_id` | FK `users` nullable | `nullOnDelete` |
| `station_label` | varchar(50) nullable | ventanilla asignada |
| `reason` | varchar(30) | `called`, `recalled` |
| `assigned_at` | timestamp | inicio de la asignación |
| `released_at` | timestamp nullable | fin de la asignación |
| `timestamps` | — | — |

Índices `(ticket_id, assigned_at)` y `(station_label, released_at)`.

### 9.9 `ticket_events`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `ticket_id` | FK `tickets` | `cascadeOnDelete` |
| `type` | varchar(40) | `issued`, `called`, `recalled`, `started`, `finished`, `no_show`, `cancelled`, `expired` |
| `actor_id` | FK `users` nullable | operador que ejecutó la acción |
| `payload` | json nullable | datos del evento |
| `occurred_at` | timestamp | momento del evento |
| `created_at` | timestamp nullable | sin `updated_at` |

Índices `(ticket_id, occurred_at)` y `type`. Es un registro append-only: la aplicación no lo actualiza ni lo borra.

Detalles críticos ya implementados en la Fase 2:

- `ticket_sequences` con único `(section_id, code, period_key)`.
- `tickets` con único `(section_id, code, period_key, number)`.
- Índice `(section_id, status, issued_at)` para la cola.
- Columnas generadas únicas para impedir doble ticket activo por operador y por ventanilla.
- `ticket_events` append-only; `audit_logs` queda para la Fase 5.
- `section_id` representa la sección de origen; `current_section_id` y la transferencia llegan en la Fase 3.

---

## 10. Estructura de carpetas objetivo

```text
app/
├── Domain/
│   ├── Sections/
│   ├── Ticketing/
│   ├── Services/
│   ├── Operations/
│   ├── Displays/
│   ├── Reporting/
│   └── Auditing/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Providers/
└── Support/

routes/
├── api.php
├── channels.php
└── console.php

resources/js/
├── apps/
│   ├── kiosk/
│   ├── console/
│   ├── display/
│   └── admin/
└── shared/

tests/
├── Unit/
├── Feature/
└── Concurrency/
```

Regla: `Http` depende de `Domain`; `Domain` no conoce HTTP. Los controladores solo orquestan casos de uso.

---

## 11. Fases de implementación

### Fase 0 — Validación con negocio (completada)

Formulario interactivo: abrir [formulario-decisiones.html](formulario-decisiones.html) en el navegador. Al completarlo se descargan el JSON y el Markdown del acta; el JSON puede usarse después para alimentar la configuración inicial.

- [x] Confirmar la matriz código→categoría de las tres secciones.
- [x] Confirmar si A/D/G son general (no asumido).
- [x] Definir reinicio de numeración (diario, por turno o continuo).
- [x] Definir código/serie de servicios exclusivos.
- [x] Definir política de no presentado y reencolado.
- [x] Definir si un operador puede atender varias secciones.
- [x] Definir horario de operación y cierre de jornada.
- [x] Registrar todo en un acta o documento firmado.

**Salida:** matriz completa, con `confirmed_by` y fecha. Sin una matriz válida no se habilita ninguna sección.

Acta: [decisiones-tickets-2026-11-06.md](decisiones-tickets-2026-11-06.md) — firmada por Alvaro Navarro, 2026-11-06.

Las preguntas no bloqueantes **Q-07 a Q-14** también quedaron resueltas en la sesión del 2026-11-06; ver sección 15.2.

### Fase 1 — Fundaciones (completada el 2026-11-06)

- [x] Migraciones `sections`, `categories`, `section_ticket_codes`, `section_code_category`.
- [x] Seeder de las 4 categorías y las 3 secciones.
- [x] Validador de matriz y comando `tickets:codes:verify --strict`.
- [x] Gate de activación: sección sin matriz confirmada no opera.
- [x] Roles y permisos base.
- [x] API versionada `/api/v1`.
- [x] Base de datos de pruebas en MySQL (sustituir SQLite de `phpunit.xml`).
- [x] Pruebas T-MAP.

**Salida:** una sección se bloquea si la matriz es inválida; pruebas de matriz en verde.

**Entregables de Fase 1:**

- Modelos en `app/Domain/Sections/`: `Section`, `Category`, `SectionTicketCode`.
- `SectionMatrixValidator` y `MatrixValidationResult`: exige exactamente 3 códigos activos, combinaciones permitidas (`general`, `pregnancy`, `elderly + disability`) y una sola asignación por categoría.
- `SectionActivationGate::isOperational()`: requiere `is_active`, `matrix_confirmed_at` y matriz válida.
- Comando `php artisan tickets:codes:verify [--strict]`: sin `--strict` falla solo por matriz inválida; con `--strict` también falla si una sección está inactiva o sin matriz confirmada.
- Endpoints públicos (solo secciones activas):
  - `GET /api/v1/sections` → lista ordenada por `sort_order`.
  - `GET /api/v1/sections/{slug}` → detalle; `404` si no existe o está inactiva.
  - Cada sección incluye `codes[].code` y `codes[].categories[]` con `slug`, `name` e `is_preferential`.
- Roles base en `App\Domain\Users\UserRole` (`admin`, `supervisor`, `operator`) y `Gate::before` para administradores activos.
- Pruebas T-MAP en `tests/Feature/Sections/` y `tests/Feature/Api/V1/`: 19 pruebas, 45 aserciones, todas en verde.
- `phpunit.xml` usa MySQL (`gestion_tickets_test`); `tests/TestCase.php` aplica `LazilyRefreshDatabase`.
- Zona horaria `America/La_Paz` (`APP_TIMEZONE`) y locale `es`.

### Fase 2 — Núcleo de tickets (completada el 2026-11-06)

- [x] `ticket_sequences` y emisión transaccional.
- [x] `tickets`, `ticket_assignments`, `ticket_events`.
- [x] Máquina de estados.
- [x] Endpoint de emisión para quiosco.
- [x] Quiosco mínimo en Vue 3 + TypeScript.
- [x] Impresión ESC/POS mediante adaptador configurable (QZ Tray o servicio local).
- [x] Consola mínima: llamar siguiente, rellamar, iniciar, finalizar.
- [x] Reverb + Echo y endpoint de snapshot.
- [x] Pruebas T-SEQ y T-CONC.

**Salida:** emisión concurrente sin duplicados y llamado atómico; flujo quiosco → consola → pantalla funcionando en un entorno local.

**Entregables de Fase 2:**

- Modelos en `app/Domain/Ticketing/`: `Ticket`, `TicketSequence`, `TicketAssignment`, `TicketEvent`; enums `TicketStatus`, `TicketEventType`, `AssignmentReason`.
- Acciones de dominio en `app/Domain/Ticketing/Actions/`:
  - `IssueTicket`: valida la sección con `SectionActivationGate`, resuelve el código desde la matriz, crea/incrementa la secuencia del día con `lockForUpdate` y registra el evento `issued`.
  - `CallNextTicket`: bloquea la sección, impide dos tickets activos por ventanilla u operador, toma el ticket en espera más antiguo y registra asignación y evento.
  - `RecallTicket`: máximo 2 llamados; cierra la asignación anterior y crea una nueva de motivo `recalled`.
  - `StartTicket`, `FinishTicket`, `MarkTicketNoShow`: transiciones de estado con evento y liberación de asignación.
  - `CloseTicketDay`: expira los tickets en espera y finaliza los activos de la jornada.
- `QueueNotifier` y evento `TicketQueueChanged` (`ShouldBroadcastNow`) en el canal `sections.{slug}` con evento `.queue.changed`. Los fallos de broadcast se capturan y no rompen la operación.
- Impresión `EscPosTicketFormatter`: payload ESC/POS en CP850 con inicialización, tamaño doble, alimentación y corte; el endpoint devuelve `print.escpos_base64` y `print.text`.
- API v1:
  - `POST /api/v1/tickets` → emisión; `201` con `data` y `print`.
  - `GET /api/v1/console/queue?section=&station=` → contador en espera y ticket activo de la ventanilla.
  - `POST /api/v1/console/tickets/next` → siguiente ticket; `data: null` si la cola está vacía.
  - `POST /api/v1/console/tickets/{ticket}/recall|start|finish`.
  - `GET /api/v1/display/snapshot` → secciones activas, contador y llamados en curso.
- Comandos y scheduler:
  - `php artisan tickets:no-show`: cada minuto; cierra como no presentado tras 2 llamados y 1 minuto desde el último.
  - `php artisan tickets:day:close`: diario a las 16:30; expira en espera y finaliza activos.
- Frontend:
  - `resources/js/apps/kiosk/`: selector de sección, categorías derivadas de la matriz y emisión con impresión.
  - `resources/js/apps/console/`: selección de sección/ventanilla, ticket activo, llamar, rellamar, iniciar y finalizar; polling cada 5 s y suscripción a Echo si Reverb está configurado.
  - Cliente API y tipos compartidos en `resources/js/shared/`.
  - Adaptador de impresión con drivers `local` (HTTP a `VITE_PRINT_SERVICE_URL`) y `qz` (QZ Tray).
- Rutas Blade `/quiosco` y `/consola`; Vite con entradas múltiples.
- Pruebas: **58 pruebas, 182 aserciones en verde**, incluidas T-SEQ, T-CONC (6 procesos × 5 tickets = 30 números únicos) y flujo completo por API.

**Limitaciones conocidas de la Fase 2:**

- Los endpoints de consola todavía no tienen autenticación ni autorización por operador/servicio; la Fase 4 debe añadir políticas y `403` reales.
- El servicio local de impresión (`http://127.0.0.1:9100/print`) no está implementado; el driver existe pero requiere ese servicio externo. QZ Tray se carga dinámicamente.
- `BROADCAST_CONNECTION=log` por defecto y `null` en pruebas; para tiempo real hay que ejecutar `php artisan reverb:start` y cambiar la conexión a `reverb`.
- El quiosco muestra todas las secciones activas; la asignación por dispositivo es Fase 5 (Q-12).
- La transferencia entre secciones y el `current_section_id` son Fase 3 (Q-11).
- La proporción 2:1, el aging y los servicios exclusivos son Fase 4.
- `laravel/reverb` se instaló como `dev-main` porque las versiones `v1.x` exigen `guzzlehttp/psr7 ^2.6` y el proyecto usa `^3.2`; `dev-main` acepta `^2.6|^3.0`. Debe reemplazarse por un release etiquetado cuando esté disponible.

### Fase 3 — Operación

- [ ] Ventanillas.
- [ ] Sesiones de operador.
- [ ] Cambio de ventanilla con historial.
- [ ] Transferencia de tickets entre secciones con historial.
- [ ] Pantallas de llamados.
- [ ] Reimpresión y manejo de fallos de impresora.

**Salida:** historial completo de asignaciones y pantalla actualizada por WebSocket.

### Fase 4 — Prioridades y servicios

- [ ] Perfiles de prioridad por sección con vigencia.
- [ ] Proporción configurable de llamados (actual 2 generales : 1 preferencial).
- [ ] Cálculo de score con aging y snapshot en ticket.
- [ ] Regla dura de generales y límite de consecutivos.
- [ ] Simulador de cola.
- [ ] Servicios permanentes/temporales con ventanas.
- [ ] Autorización de operadores por servicio en backend.
- [ ] Pruebas T-PRIO y T-AUTH.

**Salida:** configuración aprobada por negocio y autorización exclusiva validada con 403 real.

### Fase 5 — Administración, reportes y multimedia

- [ ] CRUD de secciones, ventanillas, operadores y servicios.
- [ ] Configuración de secciones habilitadas por quiosco.
- [ ] Configuración de impresoras y driver por dispositivo.
- [ ] Matriz de códigos editable y auditable.
- [ ] Contenidos multimedia, playlists y pantallas.
- [ ] Anuncio por voz con ring + TTS (código, número, ventanilla).
- [ ] Reportes por sección, categoría, servicio y ventanilla.
- [ ] Exportaciones en cola.
- [ ] Consulta de auditoría.
- [ ] Purga o archivo de datos a los 12 meses.

**Salida:** operación administrable sin tocar base de datos.

### Fase 6 — Endurecimiento y piloto

- [ ] Pruebas de carga con k6.
- [ ] Pruebas de caos: Redis, Reverb, impresora.
- [ ] Revisión de seguridad y permisos.
- [ ] Respaldos y restauración ensayados.
- [ ] Runbooks.
- [ ] UAT y piloto en una sola sección.

**Salida:** piloto aprobado y plan de habilitación de las otras dos secciones.

---

## 12. Convenciones de trabajo

### Backend

- PHP 8.4, tipado estricto y tipos de retorno explícitos.
- Casos de uso en `Domain/*/Actions`.
- Validación con FormRequest; respuestas con API Resources.
- Políticas para autorización; nada de permisos solo en la interfaz.
- Enums para estados y propósitos.
- Transacciones en emisión, llamado y transiciones.
- `vendor/bin/pint --dirty --format agent` antes de cerrar cambios PHP.
- Pruebas con PHPUnit; las de concurrencia, contra MySQL real.

### Frontend

- Vue 3 con `<script setup lang="ts">`.
- Tipos compartidos en `resources/js/shared/types`.
- Cliente API único en `resources/js/shared/api`.
- Cada app con su entrada de Vite.
- No duplicar reglas de negocio: la API es la autoridad.

### Git

- Ramas cortas por funcionalidad.
- Commits en formato Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`).
- No versionar `.env`, `vendor`, `node_modules` ni `public/build`.

---

## 13. Estrategia de pruebas

- **Unitarias:** prioridad, validador de matriz, máquina de estados, formateo de números.
- **Feature:** emisión, llamado, autorización, transiciones, auditoría.
- **Concurrencia:** procesos paralelos contra MySQL; nunca SQLite.
- **E2E:** Playwright para quiosco, consola, pantalla y admin.
- **Carga:** k6 para ráfagas de emisión y operadores concurrentes.
- **Impresión:** emulador ESC/POS y pruebas de acentos.
- **Resiliencia:** caída de Redis, Reverb e impresora.

`phpunit.xml` ya usa MySQL (`gestion_tickets_test`) con `LazilyRefreshDatabase`. Estado al cierre de la Fase 2: **58 pruebas, 182 aserciones en verde**.

- T-MAP: matriz código→categoría y bloqueo de secciones inválidas (19 pruebas).
- T-SEQ: emisión, numeración, llamados, rellamados, transiciones, no-show y cierre de jornada.
- T-CONC: 6 procesos en paralelo emiten 5 tickets cada uno; se verifican 30 números únicos y consecutivos contra MySQL real.
- Flujo API completo: emisión → llamado → rellamado → inicio → finalización, más snapshot de pantalla.

La concurrencia se ejecuta contra MySQL porque SQLite no reproduce el comportamiento de `lockForUpdate` ni de las columnas generadas.

---

## 14. Operación

- Logs de Laravel en `storage/logs`.
- Healthcheck del contenedor MySQL ya configurado en `compose.yaml`.
- Scheduler: `tickets:no-show` cada minuto y `tickets:day:close` a las 16:30. En local se ejecuta con `php artisan schedule:work`; en producción, con el cron de Laravel.
- Tiempo real: `php artisan reverb:start` y `BROADCAST_CONNECTION=reverb`. Sin Reverb, la consola sigue funcionando por polling cada 5 segundos.
- Redis: instalado en la arquitectura objetivo pero no habilitado; caché, colas y sesiones usan base de datos en local.
- Pendiente: healthchecks de aplicación y Reverb, Horizon, respaldos automatizados y purga a 12 meses.
- Respaldos locales con `mysqldump`; en producción, binlog + copia externa cifrada.
- RPO/RTO propuestos: 15 minutos / 2 horas, sujetos a validación.

---

## 15. Registro de decisiones

### 15.1 Acta base del 2026-11-06

| ID | Pregunta | Decisión |
|---|---|---|
| Q-01 | Matriz exacta de códigos por sección | A/C/B, D/F/E, G/I/H |
| Q-02 | ¿A, D y G son general? | Sí, confirmado en el acta |
| Q-03 | Reinicio de numeración | Diario a las 00:00, `America/La_Paz` |
| Q-04 | Código/serie de servicios exclusivos | Código general de la sección + etiqueta de servicio |
| Q-05 | Política de no presentado | 2 llamados, cierre 1 minuto después del último, sin reencolado manual |
| Q-06 | Operador en múltiples secciones | Una sola sección por jornada |
| Q-15 | Horario de operación y cierre | 08:00–16:30; en espera expiran, activos se finalizan |

> **Riesgo operativo registrado:** cerrar como no presentado **1 minuto** después del segundo llamado es un margen muy corto para que una persona llegue a la ventanilla. Se recomienda medir el efecto en la Fase 2 y, si hay reclamos, proponer una nueva acta con un margen mayor (por ejemplo, 3–5 minutos) antes de ajustar el sistema.

Fuente: [decisiones-tickets-2026-11-06.md](decisiones-tickets-2026-11-06.md).

### 15.2 Ampliación del 2026-11-06

Respuestas del responsable registradas en la sesión de trabajo del 2026-11-06, posteriores al acta base:

| ID | Pregunta | Decisión |
|---|---|---|
| Q-07 | Acreditación de categoría preferencial | **No se valida.** El quiosco confía en la declaración de la persona. |
| Q-08 | Pesos y umbrales de prioridad | Configuración actual de **2 tickets generales por cada 1 preferencial**; debe ser configurable desde el módulo de administración. |
| Q-09 | Retención de datos | **12 meses.** |
| Q-10 | Audio/TTS y contenido | Al llamar un ticket, después del ring de alarma, una voz anuncia **código, número y ventanilla** a la que debe acercarse. |
| Q-11 | Transferencia entre secciones | **Sí.** Un operador puede transferir un ticket a otra sección; el ticket **conserva su código y número originales**. |
| Q-12 | Quiosco fijo o con selector de sección | **Con selector de sección.** Hoy hay 2 quioscos legacy: uno para Plataforma y otro para ODECO + Cajas. La asignación debe ser configurable desde administración. |
| Q-13 | Integración de pagos en Cajas | **Cajas solo gestiona la cola de atención.** No integra pagos. |
| Q-14 | Impresoras USB/serie o Ethernet | **EPSON TM-T20II conectadas por USB.** Se implementará un adaptador con dos drivers (QZ Tray y servicio local), seleccionable por impresora desde administración. |

**Implicaciones para la implementación:**

- **Q-07:** el ticket guarda la categoría declarada por la persona; no hay paso de verificación documental ni aprobación del operador. El sistema no puede impedir por sí mismo el uso indebido de la categoría preferencial.
- **Q-08:** el motor de colas debe leer la proporción desde configuración, no tenerla fija en código. La proporción aplica al orden de llamado y sigue sujeta a la regla dura contra la postergación indefinida de tickets generales.
- **Q-09:** se requiere una tarea de purga o archivo a los 12 meses. El alcance exacto sobre la auditoría append-only se confirmará al implementar la retención.
- **Q-10:** la secuencia de llamado es **ring → voz**. La pantalla y el audio deben recibir código, número y ventanilla desde el mismo evento de llamado.
- **Q-11:** la transferencia mueve el ticket a la cola de la sección destino y debe registrar sección y operador de origen, sección destino, motivo y fecha. El ticket conserva `code` y `number`; se registran `origin_section_id` y `current_section_id` para historial y reportes. La unicidad de la numeración sigue anclada a la sección de origen, no a la sección destino. No implica que el operador origen atienda tickets de la sección destino.
- **Q-12:** el modelo de quiosco necesita una lista de secciones habilitadas por dispositivo. La configuración inicial reproduce los dos quioscos actuales: uno con Plataforma y otro con ODECO + Cajas.
- **Q-13:** Cajas queda fuera de cualquier integración de cobro; su alcance es emisión, llamado, atención y cierre de tickets.
- **Q-14:** la impresión es ESC/POS sobre USB. El backend genera un único payload y el dispositivo lo entrega al driver configurado: **QZ Tray** (WebSocket local) o **servicio local** (HTTP local). La configuración debe permitir elegir el driver por impresora o por quiosco, sin cambiar el código de negocio.

### 15.3 Detalles de implementación por confirmar

No bloquean el arranque de la Fase 1, pero deben cerrarse antes de las fases indicadas:

| Tema | Detalle | Fase aproximada |
|---|---|---|
| Transferencia (Q-11) | ¿Quién autoriza: operador origen, operador destino o supervisor? ¿Se recalcula la prioridad con el perfil de la sección destino o se conserva la original? ¿El reporte se atribuye a la sección de origen o a la de destino? | Fase 3 (operador) |
| Retención (Q-09) | ¿La auditoría append-only se conserva también 12 meses o tiene un plazo distinto? | Fase 5 (administración) |
| Voz (Q-10) | Idioma, tipo de voz, volumen y si se usa TTS del sistema o un servicio externo. | Fase 5 (multimedia) |
| Impresión (Q-14) | Formato del ticket, política de reintentos y fallback entre drivers, y autenticación del servicio local. El formato ESC/POS y los dos drivers están implementados en la Fase 2; quedan reintentos/fallback y la autenticación del servicio local. | Fase 3 |

---

## 16. Primeros pasos concretos

1. ~~Cerrar la Fase 0 y firmar la matriz de códigos.~~ Completado el 2026-11-06; ver [decisiones-tickets-2026-11-06.md](decisiones-tickets-2026-11-06.md).
2. ~~Crear la rama `feat/fase-1-fundaciones`.~~ El repositorio git se inicializó y se publicó en `https://github.com/navarro-joaquin/gestion-tickets`; `main` sigue a `origin/main`.
3. ~~Generar las migraciones de secciones, categorías y matriz.~~ Completado.
4. ~~Implementar el validador y su comando.~~ Completado.
5. ~~Escribir las pruebas T-MAP antes de habilitar cualquier sección.~~ Completado: 19 pruebas en verde.
6. ~~Configurar la base de datos de pruebas en MySQL.~~ Completado: `gestion_tickets_test`.
7. ~~Implementar la emisión, el llamado, la atención y el cierre de tickets (Fase 2).~~ Completado el 2026-11-06: 58 pruebas en verde y smoke test de `/quiosco`, `/consola` y API.
8. Comenzar la Fase 3: ventanillas, sesiones de operador, transferencia entre secciones y pantalla de llamados.

---

## 17. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| `Bind for 0.0.0.0:3308 failed` | Puerto ocupado | Cambiar el puerto en `compose.yaml` y `DB_PORT` |
| `Access denied for user` | Contraseña distinta a la del volumen | Ajustar `.env` o recrear el volumen |
| Cambios de `MYSQL_*` sin efecto | El volumen ya estaba inicializado | `ALTER USER` o `docker compose down -v` |
| Laravel sigue usando SQLite | Configuración cacheada o `.env` incorrecto | `php artisan config:clear` |
| Contenedor `unhealthy` | Inicialización lenta o error de arranque | `docker compose logs mysql` |
| WebSocket no conecta | Reverb detenido o `BROADCAST_CONNECTION` distinto de `reverb` | Ejecutar `php artisan reverb:start` y ajustar `.env` |
| La impresión falla | Servicio local no disponible o QZ Tray sin conexión | Revisar `VITE_PRINT_SERVICE_URL`, iniciar el servicio o QZ Tray |
| `npm run build` no refleja cambios | Manifest de Vite desactualizado | Ejecutar `npm run build` o `npm run dev` |

---

**Nota final:** esta guía describe el estado real del repositorio al 2026-11-06, con las Fases 1 y 2 completadas. Las pruebas de negocio se limitan a la suite automatizada y a un smoke test local; no se ha validado el sistema en operación real ni con impresoras físicas. Cualquier avance debe reflejarse aquí y en el acta de decisiones.
