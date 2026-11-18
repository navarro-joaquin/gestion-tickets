# Gestión de Tickets

Sistema de turnos para la atención al público en tres secciones: Plataforma de Atención al Cliente, ODECO y Cajas. Emite tickets desde quioscos, los reparte entre ventanillas según prioridad y los anuncia en pantallas en tiempo real.

## Aplicaciones

| Ruta | Uso | Acceso |
|---|---|---|
| `/quiosco?quiosco=<token>` | Emisión de tickets e impresión ESC/POS (QZ Tray) | Token de quiosco |
| `/pantalla?pantalla=<token>` | Llamados, historial y multimedia | Público o token de pantalla |
| `/consola` | Jornada del operador: llamar, rellamar, atender, finalizar y transferir | Admin, supervisor y operador |
| `/admin` | Usuarios, secciones y matriz de códigos, ventanillas, quioscos, impresoras, pantallas, multimedia, prioridades, servicios, reportes, auditoría y ajustes | Admin |

La API REST está en `/api/v1` ([routes/api.php](routes/api.php)).

## Stack

- PHP 8.4, Laravel 13, MySQL 8.4 LTS (Docker)
- Vue 3 + TypeScript, Tailwind CSS 4, Vite 8
- Laravel Reverb + Echo para el tiempo real; las pantallas y la consola también consultan periódicamente como respaldo

## Reglas principales

- **Numeración diaria** por sección y código; se reinicia a las 00:00 (America/La_Paz).
- **Prioridad:** reparto general/preferencial por ratio, con un tope de preferenciales seguidos y un "aging" que adelanta a quien espera demasiado. Cada decisión queda en `priority_snapshot`.
- **No presentados:** tras los llamados y minutos configurados en *Admin → Ajustes* (por defecto, 2 llamados y 1 minuto).
- **Cierre de jornada** a la hora configurada (por defecto 16:30): no se emiten más tickets, los que esperan expiran, los llamados pasan a no presentados y los que estaban en atención se finalizan. Las jornadas anteriores que hayan quedado abiertas se cierran automáticamente.

Las decisiones de negocio están en [docs/decisiones-tickets-2026-11-06.md](docs/decisiones-tickets-2026-11-06.md).

## Puesta en marcha

Requisitos: PHP 8.4, Composer 2, Docker Desktop, Node.js 22+ y npm. Herd es opcional (`http://gestion-tickets.test`).

```powershell
composer install
npm install
Copy-Item .env.example .env   # definir credenciales reales antes de levantar MySQL
php artisan key:generate
docker compose up -d
php artisan migrate --seed
```

Día a día:

```powershell
docker compose up -d
composer run dev              # servidor, Vite y procesos de desarrollo
php artisan schedule:work     # no presentados, cierre de jornada y purga
php artisan reverb:start      # tiempo real (BROADCAST_CONNECTION=reverb)
php artisan queue:work        # exportaciones CSV
```

## Comandos

| Comando | Qué hace |
|---|---|
| `tickets:no-show` | Cierra como no presentados los tickets que agotaron sus llamados (cada minuto) |
| `tickets:day:close [--force]` | Cierra la jornada y las anteriores que quedaron abiertas (cada minuto) |
| `tickets:purge` | Elimina el historial que supera la retención configurada (03:00) |
| `tickets:codes:verify --strict` | Verifica que la matriz de códigos de cada sección sea válida |
| `tickets:health` | Revisa el estado del sistema (cola de jobs, entre otros) |

## Tests

```powershell
php artisan test --compact
npm run typecheck
```

Los tests usan la base `gestion_tickets_test` en MySQL ([phpunit.xml](phpunit.xml)). Las pruebas de carga con k6 están en [tests/load](tests/load/README.md).

## Documentación

- [Guía de implementación](docs/guia-implementacion.md): arquitectura, modelo de datos y entorno
- [Runbook de operación](docs/runbook-operacion.md): procesos, incidencias y recuperación
- [Seguridad](docs/seguridad.md)
- [Pruebas de caos](docs/pruebas-caos.md) y [piloto / UAT](docs/piloto-uat.md)
