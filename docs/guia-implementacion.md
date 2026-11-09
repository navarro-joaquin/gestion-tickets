# Guía de implementación — Gestión de Tickets

**Proyecto:** `gestion-tickets`  
**Estado:** Fases 1 a 4 completadas; prioridades, servicios, autenticación y administración base en entorno local.  
**Última actualización:** 2026-11-06

---

## 1. Objetivo de esta guía

Documentar cómo está montado el proyecto, cómo levantarlo y en qué orden implementar el sistema de generación y gestión de tickets descrito en el diseño técnico. Es un documento vivo: debe actualizarse cuando se cierren decisiones pendientes o cambie la infraestructura.

Esta guía describe el estado real del repositorio. A la fecha están implementadas las fases 1 a 6: fundaciones de secciones/categorías/matriz, núcleo de tickets, ventanillas y transferencias, prioridades y servicios, administración y auditoría, reportes y retención, multimedia y pantallas, y el endurecimiento de la Fase 6. **Pendiente de ejecución:** cargas en staging, simulacros de caos, restauración en producción, UAT y piloto real.

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
| Frontend | Vite 8 + Tailwind 4 + Vue 3 + TypeScript; apps `kiosk`, `console`, `display` y `admin` |
| Redis / Reverb / Echo | Reverb instalado (`dev-main`) y Echo/Pusher JS en el frontend; Redis no habilitado todavía |
| Módulos de dominio | Fases 1 a 4: matriz de códigos, núcleo de tickets, ajuste de hora de cierre, ventanillas, sesiones, transferencias, prioridades con aging, servicios exclusivos, autenticación y administración base |
| Migraciones ejecutadas | Base de Laravel + `sections`, `categories`, `section_ticket_codes`, `section_code_category`, `users.role`/`is_active`, `ticket_sequences`, `tickets`, `ticket_assignments`, `ticket_events`, `settings`, `stations`, `operator_sessions`, `station_assignments`, `ticket_transfers`, `tickets.current_section_id`, clave única de ventanilla activa por sección, `priority_profiles`, `services`, `service_user` y columnas de prioridad/servicio en `tickets` |

### Archivos relevantes creados

```text
gestion-tickets/
├── compose.yaml                  # Contenedor MySQL 8.4 LTS
├── .env                          # Configuración local (no se versiona)
├── .env.example                  # Plantilla versionada (Reverb, Vite, impresión)
├── app/
│   ├── Domain/
│   │   ├── Sections/             # Fase 1: secciones, categorías, matriz y gate
│   │   ├── Settings/             # Hora de cierre configurable
│   │   ├── Priorities/           # Fase 4: perfiles, selector 2:1, aging y simulador
│   │   ├── Services/             # Fase 4: catálogo de servicios y ventanas
│   │   ├── Operations/           # Fase 3: ventanillas, sesiones y transferencias
│   │   └── Ticketing/            # Fase 2: modelos, acciones y formateador ESC/POS
│   ├── Console/Commands/         # tickets:no-show, tickets:day:close
│   ├── Events/TicketQueueChanged.php
│   └── Http/
│       ├── Controllers/Api/V1/   # Section, Ticket, Console, OperatorSession, Display, AdminSettings, Auth, PriorityProfile, Service, QueueSimulator, AdminUser
│       ├── Controllers/Auth/     # Login/logout web
│       ├── Middleware/           # EnsureUserIsActive, EnsureUserHasRole
│       ├── Requests/Api/V1/
│       ├── Requests/Auth/
│       └── Resources/            # Ticket, TicketTransfer, Operator, OperatorSession, Station, PriorityProfile, Service y AuthenticatedUser
├── database/
│   ├── migrations/               # Fases 1 a 4 + settings
│   ├── factories/Domain/         # Ticketing, Operations, Priorities y Services
│   └── seeders/                  # Category, Section, Station, PriorityProfile y DemoOperator (solo local)
├── resources/
│   ├── js/
│   │   ├── apps/kiosk/           # Quiosco Vue 3 con servicios exclusivos
│   │   ├── apps/console/         # Consola autenticada con sesiones y transferencias
│   │   ├── apps/display/         # Pantalla de llamados
│   │   ├── apps/admin/           # Administración: ajustes, prioridades, servicios y simulador
│   │   └── shared/               # API, tipos, Echo e impresión
│   └── views/                    # login, kiosk, console, display y admin (Blade)
├── routes/
│   ├── api.php                   # API v1
│   ├── channels.php              # Canal sections.{slug}
│   └── console.php               # Scheduler
├── tests/
│   ├── Feature/                  # T-MAP, T-SEQ, T-OPS, T-PRIO, T-SVC, T-AUTH, API
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

Las tablas 1 a 4 están implementadas desde la Fase 1 (2026-11-06), las tablas 12 a 15 desde la Fase 2, las tablas 17 y 19 desde la Fase 5A, la tabla 20 desde la Fase 5B y la tabla 18 desde la Fase 5C. Detalle real:

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
- `default_section_id` FK `sections` nullable y `default_station_id` FK `stations` nullable; obligatorias para operadores y supervisores al crear o editar el usuario. La consola las preselecciona al abrir la jornada; el cambio manual sigue permitido cuando sea necesario.
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
| `current_section_id` | FK `sections` nullable | sección que atiende el ticket; cambia con las transferencias |
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

Único `(section_id, code, period_key, number)`. Índices `(section_id, status, issued_at)` para la cola, `(current_section_id, status, issued_at)` para la cola de la sección que atiende y `(section_id, category_id, issued_at)` para reportes.

Dos columnas generadas almacenadas impiden la doble atención:

- `active_station_key` = `concat(current_section_id, ':', station_label)` cuando el estado es `called` o `in_service`, con índice único. La clave incluye la sección actual para que varias secciones puedan usar “Ventanilla 1” a la vez. Referencia `current_section_id` y no `section_id` porque MySQL no permite `ON DELETE CASCADE` sobre una columna base de una columna generada almacenada.
- `active_operator_key` = `operator_id` en los mismos estados, con índice único.

`section_id` es la sección de origen y no cambia; `current_section_id` determina la cola, el snapshot y el llamado actual. La unicidad de la numeración sigue anclada a `(section_id, code, period_key)`.

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
| `type` | varchar(40) | `issued`, `called`, `recalled`, `started`, `finished`, `no_show`, `cancelled`, `expired`, `transferred`, `reprinted`, `print_failed` |
| `actor_id` | FK `users` nullable | operador que ejecutó la acción |
| `payload` | json nullable | datos del evento |
| `occurred_at` | timestamp | momento del evento |
| `created_at` | timestamp nullable | sin `updated_at` |

Índices `(ticket_id, occurred_at)` y `type`. Es un registro append-only: la aplicación no lo actualiza ni lo borra.

Detalles críticos implementados en las Fases 2 y 3:

- `ticket_sequences` con único `(section_id, code, period_key)`.
- `tickets` con único `(section_id, code, period_key, number)`.
- Índice `(section_id, status, issued_at)` para la cola.
- Columnas generadas únicas para impedir doble ticket activo por operador y por ventanilla.
- `ticket_events` append-only; `audit_logs` queda para la Fase 5.
- `section_id` representa la sección de origen; `current_section_id` y la tabla `ticket_transfers` se incorporaron en la Fase 3.

### 9.10 `settings`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `key` | varchar(100) | único; por ejemplo `tickets.day_closing_time` |
| `value` | text nullable | valor serializado como texto |
| `timestamps` | — | — |

Almacén clave-valor para configuración global. La hora de cierre se guarda como `HH:MM` en `tickets.day_closing_time`; si no existe o es inválida, el sistema usa `16:30`. La zona horaria es la de la aplicación (`America/La_Paz`).

### 9.11 `stations`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `section_id` | FK `sections` | `cascadeOnDelete` |
| `name` | varchar(50) | etiqueta visible, por ejemplo “Ventanilla 1” |
| `is_active` | boolean | por defecto `true`; se desactiva, no se elimina si tiene historial |
| `sort_order` | unsigned smallint | orden de presentación |
| `timestamps` | — | — |

Único `(section_id, name)`; índice `(section_id, is_active, sort_order)`. Cada sección tiene sus propias ventanillas, de modo que “Ventanilla 1” puede existir en Plataforma, ODECO y Cajas.

### 9.12 `operator_sessions`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `operator_id` | FK `users` | operador o supervisor |
| `section_id` | FK `sections` | sección de la jornada; `cascadeOnDelete` |
| `started_at` | timestamp | apertura |
| `ended_at` | timestamp nullable | cierre; `null` indica sesión activa |
| `timestamps` | — | — |

Índices `(operator_id, started_at)` y `(section_id, ended_at)`. La columna generada `active_operator_key` = `operator_id` cuando `ended_at` es `null`, con índice único: un operador no puede tener dos sesiones abiertas. Una sesión fija la sección de la jornada; el cambio de ventanilla se registra en `station_assignments`.

### 9.13 `station_assignments`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `operator_session_id` | FK `operator_sessions` | `cascadeOnDelete` |
| `station_id` | FK `stations` | sin borrado en cascada por la columna generada |
| `assigned_at` | timestamp | inicio de la asignación |
| `released_at` | timestamp nullable | fin de la asignación |
| `timestamps` | — | — |

Índices `(operator_session_id, assigned_at)` y `(station_id, released_at)`. La columna generada `active_station_key` = `station_id` cuando `released_at` es `null`, con índice único: una ventanilla no puede estar asignada a dos sesiones abiertas. Cada cambio de ventanilla cierra la asignación anterior y crea una nueva, conservando el historial.

### 9.14 `ticket_transfers`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `ticket_id` | FK `tickets` | `cascadeOnDelete` |
| `from_section_id` | FK `sections` | sección que remite |
| `to_section_id` | FK `sections` | sección de destino |
| `from_operator_id` | FK `users` nullable | operador que remite |
| `to_operator_id` | FK `users` nullable | operador de destino, si se conoce |
| `reason` | varchar(255) nullable | motivo |
| `transferred_at` | timestamp | momento de la transferencia |
| `timestamps` | — | — |

Índices `(ticket_id, transferred_at)` y `(to_section_id, transferred_at)`. Es un historial append-only de transferencias; cada transferencia también genera un evento `transferred` en `ticket_events`. La transferencia conserva `code` y `number`, actualiza `current_section_id` y deja `section_id` intacto.

### 9.15 `priority_profiles`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `name` | varchar(80) | único |
| `description` | varchar(255) nullable | — |
| `general_ratio` | tinyint unsigned | generales por ciclo; por defecto 2 |
| `preferential_ratio` | tinyint unsigned | preferenciales por ciclo; por defecto 1 |
| `aging_minutes` | smallint unsigned | minutos para forzar el grupo más antiguo; por defecto 30 |
| `max_consecutive_preferential` | tinyint unsigned | máximo de preferenciales seguidos; por defecto 1 |
| `is_active` | boolean | — |
| `effective_from` / `effective_to` | date nullable | vigencia |
| `timestamps` | — | — |

Índice `(is_active, effective_from, effective_to)`. Los perfiles son **globales** (no por sección). El resolvedor usa el primer perfil activo y vigente; si no existe, aplica el perfil fallback “Estándar 2:1” sin persistir.

### 9.16 `services`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `section_id` | FK `sections` | sección dueña del servicio |
| `slug` | varchar(80) | único por sección |
| `name` | varchar(120) | nombre visible |
| `label` | varchar(20) | etiqueta que acompaña al código general |
| `description` | varchar(255) nullable | — |
| `is_active` | boolean | — |
| `available_days` | json nullable | días ISO 1–7; vacío = todos |
| `available_from` / `available_to` | time nullable | ventana horaria |
| `effective_from` / `effective_to` | date nullable | vigencia |
| `sort_order` | smallint unsigned | orden de presentación |
| `timestamps` | — | — |

Índice `(section_id, is_active, sort_order)`. Un ticket de servicio usa la categoría `general`, el código general de la sección y guarda la etiqueta en `tickets.service_label`.

### 9.17 `service_user`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned | PK |
| `service_id` | FK `services` | `cascadeOnDelete` |
| `user_id` | FK `users` | `cascadeOnDelete` |
| `timestamps` | — | — |

Único `(service_id, user_id)` e índice por `user_id`. Autoriza a un operador o supervisor a llamar tickets del servicio. Los tickets sin servicio no requieren autorización.

### 9.18 Columnas de prioridad y servicio en `tickets`

| Columna | Tipo | Notas |
|---|---|---|
| `service_id` | FK `services` nullable | `nullOnDelete` |
| `service_label` | varchar(20) nullable | copia de la etiqueta al emitir |
| `is_preferential` | boolean | derivado de la categoría o del servicio |
| `priority_profile_id` | FK `priority_profiles` nullable | perfil aplicado en el último llamado |
| `priority_score` | int unsigned | score calculado |
| `priority_snapshot` | json nullable | ratios, grupo, motivo, espera y score |

Índice `(current_section_id, status, is_preferential, issued_at)` para la cola.

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
  - `CloseTicketDay`: expira los tickets en espera, marca como no presentados los llamados sin atención y finaliza los que estaban en atención.
- `QueueNotifier` y evento `TicketQueueChanged` (`ShouldBroadcastNow`) en el canal `sections.{slug}` con evento `.queue.changed`. Los fallos de broadcast se capturan y no rompen la operación.
- Impresión `EscPosTicketFormatter`: payload ESC/POS en CP850 con inicialización, tamaño doble, alimentación y corte; el endpoint devuelve `print.escpos_base64` y `print.text`.
- API v1:
  - `POST /api/v1/tickets` → emisión; exige `kiosk` (token activo) y una sección habilitada para ese quiosco; `201` con `data` y `print`.
  - `GET /api/v1/console/queue?section=&station=` → contador en espera y ticket activo de la ventanilla.
  - `POST /api/v1/console/tickets/next` → siguiente ticket; `data: null` si la cola está vacía.
  - `POST /api/v1/console/tickets/{ticket}/recall|start|finish`.
  - `GET /api/v1/display/snapshot` → secciones activas, contador y llamados en curso.
  - `GET /api/v1/admin/settings` → hora de cierre, próximo cierre y zona horaria.
  - `PUT /api/v1/admin/settings` → actualiza `closing_time` (`HH:MM`).
- Comandos y scheduler:
  - `php artisan tickets:no-show`: cada minuto; cierra como no presentado tras 2 llamados y 1 minuto desde el último.
  - `php artisan tickets:day:close`: se evalúa cada minuto y ejecuta cuando la hora local alcanza la hora configurada (por defecto `16:30`). Acepta `--force` para ejecutar el cierre de inmediato.
  - Al cierre: los tickets en espera pasan a `expired`, los llamados sin atención a `no_show` y los que estaban en atención a `finished`.
- Frontend:
  - `resources/js/apps/kiosk/`: selector de sección, categorías derivadas de la matriz y emisión con impresión.
  - `resources/js/apps/console/`: selección de sección/ventanilla, ticket activo, llamar, rellamar, iniciar y finalizar; polling cada 5 s y suscripción a Echo si Reverb está configurado.
  - `resources/js/apps/admin/`: ajuste de la hora de cierre y visualización del próximo cierre.
  - Cliente API y tipos compartidos en `resources/js/shared/`.
  - Adaptador de impresión con drivers `local` (HTTP a `VITE_PRINT_SERVICE_URL`) y `qz` (QZ Tray).
- Rutas Blade `/quiosco`, `/consola` y `/admin/ajustes`; Vite con entradas múltiples.
- Pruebas: **71 pruebas, 226 aserciones en verde**, incluidas T-SEQ, T-CONC (6 procesos × 5 tickets = 30 números únicos), flujo completo por API y ajuste de hora de cierre.

**Limitaciones conocidas de la Fase 2:**

- Los endpoints de consola y de administración todavía no tienen autenticación ni autorización por operador/servicio; la Fase 4 debe añadir políticas y `403` reales. La pantalla `/admin/ajustes` debe usarse solo en local hasta entonces.
- El servicio local de impresión (`http://127.0.0.1:9100/print`) no está implementado; el driver existe pero requiere ese servicio externo. QZ Tray se carga dinámicamente.
- `BROADCAST_CONNECTION=log` por defecto y `null` en pruebas; para tiempo real hay que ejecutar `php artisan reverb:start` y cambiar la conexión a `reverb`.
- El quiosco muestra todas las secciones activas; la asignación por dispositivo es Fase 5 (Q-12).
- La transferencia entre secciones y el `current_section_id` estaban fuera de la Fase 2; se implementaron en la Fase 3 (Q-11).
- La proporción 2:1, el aging y los servicios exclusivos son Fase 4.
- `laravel/reverb` se instaló como `dev-main` porque las versiones `v1.x` exigen `guzzlehttp/psr7 ^2.6` y el proyecto usa `^3.2`; `dev-main` acepta `^2.6|^3.0`. Debe reemplazarse por un release etiquetado cuando esté disponible.

### Fase 3 — Operación (completada el 2026-11-06)

- [x] Ventanillas.
- [x] Sesiones de operador.
- [x] Cambio de ventanilla con historial.
- [x] Transferencia de tickets entre secciones con historial.
- [x] Pantallas de llamados.
- [x] Reimpresión y manejo de fallos de impresora.

**Salida:** historial completo de asignaciones y pantalla actualizada por WebSocket.

**Entregables de Fase 3:**

- Migraciones `stations`, `operator_sessions`, `station_assignments`, `ticket_transfers`, `tickets.current_section_id` y ampliación de la clave única de ventanilla activa a `(current_section_id, station_label)`.
- Modelos `Station`, `OperatorSession`, `StationAssignment` y `TicketTransfer`, con factories y relaciones en `Section`, `User` y `Ticket`.
- Acciones en `app/Domain/Operations/Actions/`:
  - `OpenOperatorSession`: valida operador activo, rol, sección operativa y ventanilla libre; abre una sola sesión por operador.
  - `CloseOperatorSession`: rechaza el cierre si el operador tiene un ticket activo; libera asignaciones.
  - `ChangeOperatorStation`: valida que la ventanilla pertenezca a la sección y esté libre; conserva historial.
  - `TransferTicket`: solo para tickets `waiting` o `called`; conserva `code` y `number`, actualiza `current_section_id`, registra `ticket_transfers` y un evento `transferred`. Un ticket `called` vuelve a `waiting`, libera la asignación y reinicia `call_count`.
  - `ReprintTicket` y `RegisterPrintFailure`: registran eventos `reprinted` y `print_failed` sin cambiar el estado del ticket.
- `CallNextTicket` usa `current_section_id`, exige que la sección y la ventanilla coincidan con la sesión activa del operador y permite el mismo nombre de ventanilla en secciones distintas.
- `CloseTicketDay` cierra también las sesiones y asignaciones activas al final de la jornada.
- API v1:
  - `GET /api/v1/console/operators` → operadores y supervisores activos.
  - `GET /api/v1/console/stations?section=` → ventanillas activas de la sección.
  - `POST /api/v1/console/session` → apertura de sesión con `operator_id`, `section` y `station_id`.
  - `GET /api/v1/console/session/{session}` → detalle e historial.
  - `DELETE /api/v1/console/session/{session}` → cierre de sesión.
  - `PATCH /api/v1/console/session/{session}/station` → cambio de ventanilla.
  - `POST /api/v1/console/tickets/{ticket}/transfer` → transferencia con `section`, operadores y motivo.
  - `POST /api/v1/console/tickets/{ticket}/reprint` → reimpresión con payload ESC/POS.
  - `POST /api/v1/console/tickets/{ticket}/print-failed` → registro del fallo de impresión.
  - `GET /api/v1/console/queue` incluye `session` y `stations`; `GET /api/v1/display/snapshot` agrupa por `current_section_id`.
- Frontend:
  - Consola con apertura/cierre de sesión, selección de operador, sección y ventanilla, cambio de ventanilla, transferencia, reimpresión y selección de driver.
  - App `display` en `/pantalla` con snapshot, reloj, llamados por sección, llamados anteriores y suscripción a Echo.
  - `TicketResource` expone `current_section`, `is_transferred` y el historial `transfers`.
- Seeders `StationSeeder` (3 ventanillas por sección) y `DemoOperatorSeeder` (solo `APP_ENV=local`, usuario `operador@demo.local` / `password`).
- Pruebas: **96 pruebas, 352 aserciones en verde**, incluidas T-OPS de sesiones, ventanillas, transferencias y API.

**Limitaciones conocidas de la Fase 3:**

- Sigue sin autenticación ni autorización: la consola, las sesiones y la administración deben usarse solo en local hasta la Fase 4. Cualquier persona con acceso HTTP puede abrir una sesión indicando `operator_id`.
- La pantalla `/pantalla` no reproduce audio ni anuncia por voz; el ring + TTS es Fase 5 (Q-10).
- El servicio local de impresión sigue sin implementarse; los drivers `local` y `qz` ya existen y la reimpresión registra fallos, pero no hay reintentos ni fallback automático.
- La transferencia no recalcula prioridad ni autoriza por rol; esas reglas llegan con la Fase 4.
- Reverb sigue siendo `dev-main` y `BROADCAST_CONNECTION=log` por defecto; sin Reverb la pantalla y la consola usan polling cada 5 segundos.

### Fase 4 — Prioridades, servicios y autenticación (completada el 2026-11-06)

- [x] Perfiles de prioridad globales con vigencia.
- [x] Proporción configurable de llamados (actual 2 generales : 1 preferencial).
- [x] Cálculo de score con aging y snapshot en ticket.
- [x] Regla dura de generales y límite de consecutivos.
- [x] Simulador de cola.
- [x] Servicios permanentes/temporales con ventanas.
- [x] Autorización de operadores por servicio en backend.
- [x] Autenticación con login propio por sesión y roles.
- [x] Pruebas T-PRIO, T-SVC y T-AUTH.

**Salida:** configuración aprobada por negocio y autorización exclusiva validada con 403 real.

**Entregables de Fase 4:**

- Migraciones `priority_profiles`, `services`, `service_user` y columnas `service_id`, `service_label`, `is_preferential`, `priority_profile_id`, `priority_score` y `priority_snapshot` en `tickets`.
- Modelos `PriorityProfile` y `Service` con factories; relaciones `Section::services()`, `User::authorizedServices()`, `Ticket::service()` y `Ticket::priorityProfile()`.
- Motor de prioridad en `app/Domain/Priorities/`:
  - `PriorityProfileResolver`: primer perfil activo y vigente; fallback “Estándar 2:1”.
  - `QueuePrioritySelector`: ratio configurable, aging sobre el ticket más antiguo, máximo de preferenciales consecutivos y snapshot completo (ratios, llamados del día, grupo, motivo, espera y score).
  - `QueueSimulator`: secuencia sin tocar la base de datos.
- `IssueTicket` acepta `service` como alternativa a `category`; los servicios se validan por día, horario, vigencia y estado, y usan el código general de la sección.
- `CallNextTicket` usa el selector 2:1, registra el snapshot y solo considera servicios autorizados para el operador.
- Autenticación:
  - Login web por sesión en `/login` con rate limiting y `remember`.
  - Middleware `active` y `role`; alias registrados en `bootstrap/app.php`.
  - Grupo API con sesión y CSRF (`XSRF-TOKEN` / `X-XSRF-TOKEN`).
  - `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`.
  - Rutas web protegidas: `/consola` (admin, supervisor u operador) y `/admin/*` (solo admin); quiosco y pantalla siguen públicos.
  - API pública: secciones, emisión de tickets y snapshot de pantalla. Consola y administración requieren autenticación.
- Administración:
  - CRUD de perfiles de prioridad y de servicios con operadores autorizados.
  - `GET /api/v1/admin/operators` para asignar autorizaciones.
  - Simulador `POST /api/v1/admin/queue/simulate`.
  - `TicketPolicy` para operar, transferir y reimprimir tickets de la sección de la sesión activa.
- Frontend:
  - Consola autenticada, sin selector de operador, con cierre de sesión y badges de preferencial/servicio.
  - Quiosco con bloque de servicios exclusivos disponibles.
  - Administración con pestañas Ajustes, Prioridades, Servicios y Simulador.
- Seeder `PriorityProfileSeeder` (perfil “Estándar 2:1”). El usuario de demostración sigue creándose solo con `APP_ENV=local`.
- Pruebas: **127 pruebas, 494 aserciones en verde**, incluidas T-PRIO, T-SVC y T-AUTH.

**Limitaciones conocidas de la Fase 4:**

- La administración de secciones, ventanillas, operadores, impresoras y matriz de códigos sigue pendiente (Fase 5). Los usuarios se gestionan por base de datos o seeders.
- No hay recuperación de contraseña ni gestión de usuarios desde la interfaz.
- El catálogo de servicios es genérico; los datos reales deben cargarse desde `/admin/servicios` o por seeder.
- El anuncio por voz (ring + TTS) sigue pendiente (Fase 5).
- El simulador no descuenta autorizaciones por servicio ni aging sobre datos reales: simula solo la secuencia de grupos con el perfil elegido.
- Reverb sigue siendo `dev-main` y `BROADCAST_CONNECTION=log` por defecto; sin Reverb la pantalla y la consola usan polling cada 5 segundos.

### Fase 5 — Administración, reportes y multimedia (completada el 2026-11-06)

- [x] CRUD de secciones, ventanillas, operadores y servicios.
- [x] Ajuste de la hora de cierre de jornada y retención de datos.
- [x] Configuración de secciones habilitadas por quiosco.
- [x] Configuración de impresoras y driver por dispositivo.
- [x] Matriz de códigos editable y auditable.
- [x] Contenidos multimedia, playlists y pantallas.
- [x] Anuncio por voz con ring + TTS (código, número, ventanilla).
- [x] Reportes por sección, categoría, servicio y ventanilla.
- [x] Exportaciones en cola.
- [x] Consulta de auditoría.
- [x] Purga o archivo de datos a los 12 meses.

La fase se ejecutó en tres entregas:

**Fase 5A — Administración y auditoría (commit `f95d465`):**

- Migraciones `audit_logs`, `kiosk_devices`, `kiosk_device_section` y `printers`.
- Auditoría append-only en `audit_logs` con `AuditLogger`; cada alta, cambio o baja de catálogos registra antes/después, usuario, IP y fecha.
- CRUD de usuarios con roles y baja lógica; no se eliminan usuarios con historial.
- Secciones editables (nombre, estado y orden) con bloqueo si tienen tickets activos.
- Matriz código→categoría editable solo remapeando las categorías de los tres códigos existentes; se valida con `SectionMatrixValidator` y exige confirmación explícita.
- CRUD de ventanillas por sección, quioscos con token y secciones habilitadas, e impresoras por ventanilla con driver `local` o `qz`.
- Endpoints `GET/PUT /api/v1/admin/sections`, `PUT /api/v1/admin/sections/{slug}/matrix`, `apiResource` de ventanillas, usuarios, quioscos y impresoras, y `GET /api/v1/admin/audit-logs`.
- Paneles Vue: secciones, ventanillas, usuarios, quioscos, impresoras y auditoría.
- Pruebas T-ADMIN (14 pruebas) y suite de **141 pruebas, 592 aserciones** al cierre de 5A.

**Fase 5B — Reportes, exportaciones y retención (commit `ebbcaea`):**

- Migración `report_exports`; estados `queued`, `processing`, `completed` y `failed`.
- `TicketReport` agrega totales y desgloses por sección de origen, categoría, servicio y ventanilla, con promedios de espera y atención.
- Filtros por rango de fechas, sección, categoría, servicio y ventanilla; detalle paginado de tickets.
- Exportación CSV asíncrona con `GenerateReportExport` sobre la cola `database`; el archivo se guarda en `storage/app/private/reports` y se descarga con BOM UTF-8 y separador coma.
- `RetentionPolicy` con la clave `tickets.retention_months` (12 por defecto). `tickets:purge` archiva en CSV y elimina solo tickets terminales con `closed_at` anterior al corte; admite `--months=` y `--dry-run`; el scheduler lo ejecuta a las 03:00.
- Endpoints `GET /api/v1/admin/reports/summary`, `GET /api/v1/admin/reports/tickets`, `GET/POST /api/v1/admin/reports/exports`, `GET /api/v1/admin/reports/exports/{export}/download` y `DELETE /api/v1/admin/reports/exports/{export}`.
- Panel `ReportsPanel.vue` con tarjetas, tablas, detalle paginado y gestión de exportaciones; la retención se edita en `/admin/ajustes`.
- Pruebas T-REP (8 pruebas) y suite de **149 pruebas, 661 aserciones** al cierre de 5B.

**Fase 5C — Multimedia, pantallas y voz (esta entrega):**

- Migraciones `media_assets`, `playlists`, `playlist_items`, `displays`, `display_section` y `display_playlist`.
- Contenidos multimedia en el disco público (`storage/app/public/media`): imágenes JPG/PNG/WEBP/GIF y videos MP4/WEBM, con duración opcional por elemento.
- Playlists ordenadas con posición, duración por contenido y activación individual; una playlist puede asignarse a varias pantallas.
- Pantallas con token propio, secciones visibles, playlists y tres layouts: `queue` (solo llamados), `mixed` (llamados + multimedia) y `media` (solo multimedia).
- `GET /api/v1/display/snapshot?display={token}` filtra las secciones de la pantalla, valida el token activo, actualiza `last_seen_at` como máximo una vez por minuto y devuelve las playlists con sus URLs públicas. Sin token mantiene el comportamiento público anterior.
- `POST /api/v1/admin/displays/{display}/token` regenera el token y deja inservible el enlace anterior.
- Panel Vue: contenidos, playlists y pantallas, con vista previa, reordenamiento, copia del enlace y regeneración de token.
- Anuncio por voz en el navegador: timbre de dos tonos con Web Audio API y síntesis de voz (`es-BO`, con respaldo a cualquier voz española) que dice código, número y ventanilla. Se activa con un botón por el bloqueo de audio de los navegadores y la preferencia se conserva en `localStorage`. Las llamadas nuevas y los rellamados se detectan comparando `called_at` y `call_count` entre snapshots; el primer snapshot no anuncia.
- Pruebas T-MEDIA/T-DISPLAY (10 pruebas) y suite final de **159 pruebas, 722 aserciones**.

**Salida:** operación administrable sin tocar base de datos, con reportes, retención y pantallas multimedia con anuncio por voz.

**Limitaciones conocidas de la Fase 5C:**

- La síntesis de voz depende del navegador y del sistema operativo de la pantalla; no se usa un servicio TTS externo. Si no hay voz española instalada, se usa la voz disponible o solo el timbre.
- El audio requiere una interacción inicial (botón “Activar audio”). Tras recargar la pantalla, si el navegador vuelve a bloquear el audio, hay que pulsar el botón otra vez.
- Los videos se reproducen silenciados para no tapar el anuncio de voz.
- El almacenamiento multimedia es local (`public`); para varias réplicas hay que migrar a un disco compartido (S3 u otro).
- La rotación de tokens no invalida sesiones de operador ni otros tokens; solo afecta al enlace de la pantalla.
- No hay todavía un editor de recortes ni transcodificación de video: se sirve el archivo tal como se sube.
- La exportación CSV requiere `php artisan queue:work`; sin worker queda en `queued`.


### Fase 6 — Endurecimiento y piloto (artefactos entregados)

**Estado:** entrega técnica cerrada el 2026-11-06. La ejecución de cargas en
staging, los simulacros de caos, la restauración en producción y el piloto real
quedan a cargo del equipo operativo, con los procedimientos ya documentados.

- [x] Escenarios de carga con k6 en `tests/load/` (emisión, pantallas y consola).
  **Pendiente:** ejecutarlos en staging y registrar resultados.
- [x] Revisión de seguridad y permisos en `docs/seguridad.md`, con correcciones
  de límites de tasa, cabeceras de seguridad, inyección de fórmulas en CSV y
  exigencia del token de quiosco en la emisión (S-04).
- [x] Respaldos: `scripts/backup.sh` y `scripts/backup.ps1`; el respaldo de
  Windows se probó localmente el 2026-11-06 con un volcado válido de 36 tablas.
  Ensayo de restauración local completado el 2026-11-06 en
  `gestion_tickets_restore`: 36/36 tablas, conteos idénticos, `migrate:status`
  y `tickets:health` en verde. **Pendiente:** repetirlo en el entorno de
  producción con el responsable de infraestructura.
- [x] Runbook de operación en `docs/runbook-operacion.md`.
- [x] Plan de UAT y piloto en `docs/piloto-uat.md`.
- [x] Comando `php artisan tickets:health` para verificaciones previas al piloto.
- [ ] Pruebas de caos: Reverb, cola y impresora. Los procedimientos están en el
  runbook y las pruebas automatizadas cubren la degradación de Reverb y la
  emisión cuando falla el broadcast. **Pendiente:** ejecución de los simulacros
  en staging. Redis no es dependencia obligatoria con la configuración actual
  (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`); si se habilita, probar
  también su caída.
- [ ] UAT y piloto real en una sola sección. **Pendiente:** ejecución y firma.

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

`phpunit.xml` ya usa MySQL (`gestion_tickets_test`) con `LazilyRefreshDatabase`. Estado al cierre de la Fase 6: **167 pruebas, 757 aserciones en verde**.

- T-MAP: matriz código→categoría y bloqueo de secciones inválidas (19 pruebas).
- T-SEQ: emisión, numeración, llamados, rellamados, transiciones, no-show y cierre de jornada.
- T-OPS: sesiones de operador, ventanillas, cambio de ventanilla, transferencias, reimpresión, fallos de impresión, cierre de sesiones y aislamiento de ventanillas por sección.
- T-PRIO: secuencia 2:1, aging en ambos sentidos, máximo de preferenciales consecutivos, snapshot y perfil fallback.
- T-SVC: disponibilidad por día, horario y vigencia; código general y etiqueta; autorización por servicio; CRUD de administración y desactivación con historial.
- T-AUTH: login/logout, credenciales inválidas, usuario inactivo, `401`/`403`, `auth/me` y protección de rutas web y API por rol.
- T-CONC: 6 procesos en paralelo emiten 5 tickets cada uno; se verifican 30 números únicos y consecutivos contra MySQL real.
- Flujo API completo: emisión → llamado → rellamado → inicio → finalización, más snapshot de pantalla y transferencia.
- Ajustes: hora de cierre por defecto, cambio persistente, validación, comparación horaria y API de administración.

La concurrencia se ejecuta contra MySQL porque SQLite no reproduce el comportamiento de `lockForUpdate` ni de las columnas generadas.

---

## 14. Operación

- Logs de Laravel en `storage/logs`.
- Healthcheck del contenedor MySQL ya configurado en `compose.yaml`.
- Scheduler: `tickets:no-show` cada minuto y `tickets:day:close` cada minuto, con ejecución efectiva cuando la hora local alcanza la hora configurada en `/admin/ajustes` (por defecto `16:30`). En local se ejecuta con `php artisan schedule:work`; en producción, con el cron de Laravel.
- Cierre manual: `php artisan tickets:day:close --force`.
- Tiempo real: `php artisan reverb:start` y `BROADCAST_CONNECTION=reverb`. Sin Reverb, la consola y la pantalla siguen funcionando por polling cada 5 segundos.
- Operación local: ejecutar `php artisan db:seed` para crear ventanillas, el perfil de prioridad y los usuarios de demostración `operador@demo.local` / `password` y `admin@demo.local` / `password` (solo con `APP_ENV=local`). En producción no se crean esos usuarios.
- Acceso: `/login` para operadores, supervisores y administradores. La consola (`/consola`) requiere sesión y rol admin, supervisor u operador; `/admin/*` requiere rol admin. El quiosco (`/quiosco`) y la pantalla (`/pantalla`) no exigen login; el quiosco necesita su token para emitir.
- Administración: `/admin/ajustes` (hora de cierre), `/admin/prioridades` (perfiles y ratios), `/admin/servicios` (catálogo y operadores autorizados) y `/admin/simulador` (secuencia de llamados). La API equivalente vive bajo `/api/v1/admin/*`.
- La consola abre una sesión por operador y exige una ventanilla; el cambio de ventanilla queda en `station_assignments`.
- La pantalla `/pantalla` se suscribe a todas las secciones activas y muestra el último llamado y los anteriores.
- Redis: instalado en la arquitectura objetivo pero no habilitado; caché, colas y sesiones usan base de datos en local.
- Pendiente de operación: programar los respaldos (`scripts/backup.*`) en cron/systemd, configurar alertas de monitoreo y repetir la restauración en producción. Horizon y Redis no se adoptaron; la cola y la caché usan base de datos.
- Respaldos locales con `mysqldump`; en producción, binlog + copia externa cifrada.
- RPO/RTO propuestos: 24 horas / 2 horas, sujetos a aprobación de la institución.

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
| Q-15 | Horario de operación y cierre | 08:00–16:30; hora de cierre configurable desde administración (ver 15.3); en espera expiran, llamados sin atención pasan a no presentado y atendidos a finalizado |

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

### 15.3 Ajuste del cierre de jornada (2026-11-06)

Decisión posterior al cierre de la Fase 2:

- La hora de cierre es **configurable desde el módulo de administración** (`/admin/ajustes`) y el scheduler ejecuta el cierre a esa hora. Valor por defecto: **16:30**.
- Al cierre, los estados se resuelven así:
  - `waiting` → `expired` (nunca fue llamado).
  - `called` → `no_show` (fue llamado pero no atendido).
  - `in_service` → `finished` (fue atendido pero no se finalizó).
- Un administrador puede forzar el cierre con `php artisan tickets:day:close --force`.

### 15.4 Detalles de implementación por confirmar

No bloquean el arranque de la Fase 1, pero deben cerrarse antes de las fases indicadas:

| Tema | Detalle | Fase aproximada |
|---|---|---|
| Transferencia (Q-11) | Resuelto en lo operativo en la Fase 3: la transfiere el operador que atiende, con motivo y operador de destino opcionales, y se conserva la prioridad original. La Fase 4 añadió la autorización por rol; como el perfil de prioridad es global, no hay recálculo por sección destino. | Resuelto (Fases 3 y 4) |
| Retención (Q-09) | Implementado en 5B: 12 meses por defecto, configurable entre 1 y 120; `tickets:purge` archiva en CSV y elimina solo tickets terminales. La auditoría conserva su propio historial y no entra en la purga de tickets. | Resuelto (Fase 5B) |
| Voz (Q-10) | Implementado en 5C con TTS del navegador (`es-BO`), timbre con Web Audio API y activación por gesto del usuario. No se usa servicio externo. | Resuelto (Fase 5C) |
| Impresión (Q-14) | Formato del ticket, política de reintentos y fallback entre drivers, y autenticación del servicio local. El formato ESC/POS y los dos drivers están implementados; la Fase 3 añadió reimpresión y registro de fallos, pero quedan reintentos/fallback y la autenticación del servicio local. | Fase 4/5 |

### 15.5 Operación de la Fase 3 (2026-11-06)

Decisiones adoptadas al implementar la operación, coherentes con el acta:

- **Ventanillas:** cada sección tiene sus propias ventanillas. Una ventanilla activa pertenece a una sola sesión; la clave única es `(current_section_id, station_label)`, por lo que “Ventanilla 1” puede usarse simultáneamente en secciones distintas.
- **Sesiones:** un operador tiene una sola sesión activa y una sola sección por jornada. La sesión se cierra al final de la jornada o manualmente; no se puede cerrar con un ticket activo.
- **Cambio de ventanilla:** se permite dentro de la misma sección, sin ticket activo, dejando historial en `station_assignments`.
- **Transferencia:** la ejecuta el operador que atiende. Se permite desde `waiting` o `called`; un ticket `called` vuelve a `waiting`, se libera la asignación y `call_count` se reinicia a 0. Un ticket `in_service` no se transfiere. Se conservan código y número originales y se registra origen, destino, operadores, motivo y fecha.
- **Cierre de jornada:** además de resolver los tickets, cierra las sesiones y asignaciones activas.
- **Reimpresión y fallos:** se registran como eventos `reprinted` y `print_failed`; no cambian el estado del ticket. El reintento queda a cargo del operador.
- **Alcance sin autenticación:** la Fase 3 no añade login ni autorización. La consola y la administración siguen siendo de uso local hasta la Fase 4.

### 15.6 Prioridades, servicios y autenticación (Fase 4, 2026-11-06)

Decisiones adoptadas al implementar la Fase 4:

- **Perfil global:** los perfiles de prioridad no tienen `section_id`; el mismo perfil aplica a las tres secciones. Esto simplifica la configuración y evita recalcular prioridad al transferir.
- **Resolución del perfil:** se usa el primer perfil activo cuya vigencia cubra la fecha del llamado. Si no hay ninguno, se aplica el perfil fallback “Estándar 2:1” (2:1, aging 30 min, máximo 1 preferencial consecutivo) sin persistirlo.
- **Ratio 2:1 con aging:** el selector calcula el objetivo de preferenciales con `intdiv((llamados + 1) * preferential_ratio, totalRatio)`. Un ticket del grupo más antiguo puede adelantarse si su espera alcanza `aging_minutes` y supera la del otro grupo. El límite de consecutivos actúa antes del aging.
- **Snapshot:** cada llamado persiste ratios, llamados del día, grupo seleccionado, motivo (`ratio`, `only_general`, `only_preferential`, `aging_general`, `aging_preferential`, `max_consecutive_preferential`), espera y score. El score es la espera en minutos; los preferenciales suman 100000 para desempate.
- **Servicios:** el catálogo es genérico y editable desde `/admin/servicios`. Un servicio pertenece a una sección, usa el código general de esa sección y añade su etiqueta al ticket (`A-001 · VIS`). Se valida por estado, días ISO, ventana horaria y vigencia.
- **Autorización por servicio:** un operador solo puede llamar tickets de servicios para los que está autorizado. Los tickets sin servicio no requieren autorización. La autorización se administra por servicio desde la API de administración.
- **Autenticación:** login propio por sesión, sin paquetes nuevos, con rate limiting. El usuario debe estar activo. Roles: administrador, supervisor y operador. La API usa la misma sesión y exige el token CSRF en las peticiones mutables.
- **Protección de rutas:** quiosco y pantalla no exigen login; la consola exige sesión y rol admin/supervisor/operador; la administración exige rol admin. La emisión de tickets exige el token de un quiosco activo y el snapshot de pantalla es público (filtrado si recibe token de pantalla).
- **Política de tickets:** operar, transferir y reimprimir exigen una sesión activa en la sección actual del ticket. Un administrador conserva acceso total por `Gate::before`, pero las acciones de consola siguen requiriendo sesión de operador.
- **Cierre de jornada:** sin cambios respecto de la Fase 3; las sesiones y asignaciones se cierran junto con los tickets.

### 15.7 Administración y auditoría (Fase 5A, 2026-11-06)

- **Auditoría:** cada mutación de catálogos registra `action`, entidad, `before`, `after`, usuario e IP en `audit_logs`. La tabla es de solo inserción desde la aplicación.
- **Secciones:** el nombre, estado y orden son editables; si la sección tiene tickets activos se bloquea la edición para no romper la operación en curso.
- **Matriz:** solo se permite remapear las categorías de los tres códigos existentes. No se pueden crear códigos nuevos desde la interfaz; el cambio exige confirmación explícita y se valida con `SectionMatrixValidator`.
- **Usuarios:** el alta y la edición usan `AdminUserRequest`; la baja es lógica (`is_active=false`) para conservar el historial de tickets y auditoría. Los operadores y supervisores declaran `default_section_id` y `default_station_id`; la ventanilla debe pertenecer a la sección y estar activa.
- **Quioscos:** `kiosk_devices` guarda un token aleatorio de 48 caracteres y una lista de secciones activas. La URL pública es `/quiosco?quiosco={token}`. La emisión (`POST /api/v1/tickets`) exige ese token y que la sección solicitada esté habilitada para el quiosco.
- **Impresoras:** cada impresora pertenece opcionalmente a una ventanilla y declara driver (`local` o `qz`), destino, ancho de papel y configuración adicional.
- **Autorización:** todos los endpoints de administración exigen rol `admin`; la consola exige sesión y rol admin/supervisor/operador.

### 15.8 Reportes, exportaciones y retención (Fase 5B, 2026-11-06)

- **Agregación:** los reportes agrupan por `section_id` de origen (no por sección actual) y por `station_label` registrado en el ticket; así una transferencia no altera el reporte de la sección que emitió el ticket.
- **Filtros:** rango de fechas inclusivo, sección, categoría, servicio y ventanilla. El detalle se ordena por fecha de emisión descendente y se pagina de 25 en 25 (máximo 100).
- **Exportación:** se crea un registro `report_exports` en estado `queued` y se despacha `GenerateReportExport`. El job escribe un CSV con BOM UTF-8, separador coma y encabezados en español; al completar guarda ruta, tamaño y número de filas. El archivo se descarga por endpoint autenticado.
- **Retención:** `RetentionPolicy` lee `tickets.retention_months` (12 por defecto). `tickets:purge` calcula el corte con `subMonthsNoOverflow`, archiva los tickets terminales en `storage/app/private/archives` y luego los elimina. Se programa a las 03:00 con `withoutOverlapping`.
- **Alcance de la purga:** solo tickets con estado terminal y `closed_at` anterior al corte. Los tickets activos nunca se eliminan. La auditoría no se purga con este comando.
- **Cola:** la exportación requiere un worker (`php artisan queue:work`). Sin worker, el registro permanece en `queued`.

### 15.9 Multimedia, pantallas y voz (Fase 5C, 2026-11-06)

- **Contenidos:** `media_assets` guarda metadatos y la ruta en el disco `public`. La API expone la URL pública (`/storage/media/...`); se requiere `php artisan storage:link`.
- **Playlists:** `playlist_items` ordena por `position` y permite duración por elemento. Una playlist inactiva, un elemento inactivo o un contenido inactivo se excluyen del snapshot público.
- **Pantallas:** `displays` tiene token propio, layout y relaciones con secciones y playlists. El snapshot público solo devuelve las secciones asignadas; un token inactivo o inexistente responde 404. `last_seen_at` se actualiza como máximo una vez por minuto para no escribir en cada polling.
- **Layouts:** `queue` muestra solo llamados, `mixed` muestra llamados y multimedia, `media` muestra solo la playlist.
- **Anuncio:** la pantalla compara cada snapshot con el anterior. Un ticket nuevo en `calling` o un rellamado (cambio de `call_count`) dispara la secuencia timbre → voz. El primer snapshot no anuncia para evitar repetir llamados al abrir o recargar.
- **Audio:** el timbre se genera con Web Audio API (dos tonos) y la voz con `speechSynthesis`; no hay archivos de audio ni dependencias nuevas. El botón “Activar audio” desbloquea el contexto de audio y guarda la preferencia en `localStorage`.
- **Privacidad:** la pantalla pública no recibe datos personales; solo código, número, ventanilla, estado y etiqueta de servicio del ticket.
- **Endpoints:** `apiResource` de `/api/v1/admin/media`, `/api/v1/admin/playlists` y `/api/v1/admin/displays`; `POST /api/v1/admin/displays/{display}/token`; `GET /api/v1/display/snapshot?display={token}`.

### 15.10 Sección y ventanilla por defecto del operador (2026-11-09)

- **Columnas:** `users.default_section_id` y `users.default_station_id` son FKs nullable a `sections` y `stations`, con `nullOnDelete` para no bloquear bajas de catálogo.
- **Validación:** al crear o editar un usuario con rol `operator` o `supervisor`, ambos campos son obligatorios. La sección debe estar activa y la ventanilla debe existir, estar activa y pertenecer a esa sección. Para `admin` se guardan en `null`.
- **API:** `AdminUserResource` y `AuthenticatedUserResource` exponen `default_section` (`slug`, `name`) y `default_station` (`id`, `name`). El alta y la edición reciben `default_section` (slug) y `default_station_id`.
- **Consola:** al abrir la jornada se preseleccionan la sección y la ventanilla por defecto del usuario autenticado. Si no existen o están inactivas, se usa la primera sección y ventanilla activas. El operador conserva la posibilidad de cambiarlas manualmente.
- **Demostración:** `DemoOperatorSeeder` asigna Plataforma y su primera ventanilla activa a `operador@demo.local` cuando corre en `APP_ENV=local`.

---

## 16. Primeros pasos concretos

1. ~~Cerrar la Fase 0 y firmar la matriz de códigos.~~ Completado el 2026-11-06; ver [decisiones-tickets-2026-11-06.md](decisiones-tickets-2026-11-06.md).
2. ~~Crear la rama `feat/fase-1-fundaciones`.~~ El repositorio git se inicializó y se publicó en `https://github.com/navarro-joaquin/gestion-tickets`; `main` sigue a `origin/main`.
3. ~~Generar las migraciones de secciones, categorías y matriz.~~ Completado.
4. ~~Implementar el validador y su comando.~~ Completado.
5. ~~Escribir las pruebas T-MAP antes de habilitar cualquier sección.~~ Completado: 19 pruebas en verde.
6. ~~Configurar la base de datos de pruebas en MySQL.~~ Completado: `gestion_tickets_test`.
7. ~~Implementar la emisión, el llamado, la atención y el cierre de tickets (Fase 2).~~ Completado el 2026-11-06: 71 pruebas en verde y smoke test de `/quiosco`, `/consola`, `/admin/ajustes` y API.
8. ~~Implementar ventanillas, sesiones de operador, transferencia entre secciones y pantalla de llamados (Fase 3).~~ Completado el 2026-11-06: 96 pruebas en verde y smoke test de `/quiosco`, `/consola`, `/pantalla`, `/admin/ajustes`, sesión, llamado, transferencia, reimpresión y fallo de impresión.
9. ~~Implementar prioridades configurables 2:1, aging, servicios exclusivos, autorización por servicio y autenticación (Fase 4).~~ Completado el 2026-11-06: 127 pruebas en verde (T-PRIO, T-SVC y T-AUTH) y smoke test de `/login`, `/quiosco`, `/pantalla`, `/consola`, `/admin/ajustes`, prioridades, servicios y simulador.
10. ~~Implementar la Fase 5: administración completa, reportes, retención, multimedia y anuncio por voz.~~ Completado el 2026-11-06 en tres entregas: 5A (`f95d465`), 5B (`ebbcaea`) y 5C (multimedia, pantallas y voz). Suite final: 159 pruebas y 722 aserciones.
11. ~~Iniciar la Fase 6: pruebas de carga, caos, seguridad, respaldos, runbooks y piloto en una sola sección.~~ Artefactos completados el 2026-11-06: escenarios k6, revisión de seguridad, scripts de respaldo, runbook, plan de UAT/piloto y comando `tickets:health`. **Pendiente de ejecución:** cargas en staging, simulacros de caos, ensayo de restauración, UAT y piloto real.

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
| Las imágenes o videos no cargan | Falta el enlace simbólico del disco público | Ejecutar `php artisan storage:link` |
| La pantalla no anuncia por voz | Audio bloqueado por el navegador o sin voz instalada | Pulsar “Activar audio”; revisar las voces del sistema operativo |
| El enlace de la pantalla dejó de funcionar | Token regenerado desde administración | Copiar el enlace actualizado desde el panel de pantallas |
| La exportación queda en “En cola” | No hay worker de cola | Ejecutar `php artisan queue:work` |
| `npm run build` no refleja cambios | Manifest de Vite desactualizado | Ejecutar `npm run build` o `npm run dev` |
| El cierre no se ejecuta | Scheduler detenido o hora configurada posterior a la actual | Revisar `php artisan schedule:work` y la hora en `/admin/ajustes` |
| El cierre se ejecutó antes de tiempo | Hora mal configurada o uso de `--force` | Corregir la hora; `--force` omite la comprobación horaria |
| La API responde `419` | Falta el token CSRF de la sesión | Iniciar sesión en `/login` y enviar `X-XSRF-TOKEN` con la cookie `XSRF-TOKEN` |
| La API responde `401` o `403` | Sesión ausente, usuario inactivo o rol insuficiente | Verificar `/login`, `is_active` y el rol del usuario |
| No aparece el usuario de demostración | `APP_ENV` no es `local` | Ejecutar con `APP_ENV=local` o crear el usuario manualmente |
| Un operador no recibe tickets de un servicio | No está autorizado para ese servicio | Asignarlo en `/admin/servicios` o en `service_user` |
| La API pública responde `429` | Límite de tasa por IP alcanzado | Ajustar `TICKETS_RATE_LIMIT_*` o esperar al siguiente minuto |
| No se sabe si el sistema está sano | Faltan verificaciones previas | Ejecutar `php artisan tickets:health` y revisar el runbook |
| No hay respaldo del día | No se ejecutó el script | Ejecutar `scripts/backup.ps1` o `scripts/backup.sh`; ver `docs/runbook-operacion.md` |

---

**Nota final:** esta guía describe el estado real del repositorio al 2026-11-06. Las fases 1 a 5 están completadas; la Fase 6 tiene los artefactos de endurecimiento listos (cargas k6, revisión de seguridad, respaldos, runbook y plan de UAT/piloto) y el ensayo local de restauración verificado, pero **las pruebas de carga, los simulacros de caos, el ensayo de restauración en producción y el piloto aún no se ejecutaron**. La suite automatizada está en verde; no se ha validado el sistema en operación real ni con impresoras físicas. El quiosco y la pantalla no exigen login, pero el quiosco necesita su token para emitir. Cualquier avance debe reflejarse aquí, en `docs/seguridad.md`, `docs/runbook-operacion.md` y `docs/piloto-uat.md`.
