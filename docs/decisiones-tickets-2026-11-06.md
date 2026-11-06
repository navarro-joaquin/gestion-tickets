# Acta de decisiones bloqueantes — Sistema de tickets

**Fecha:** 2026-11-06
**Responsable:** Alvaro Navarro
**Participantes:** Alvaro Navarro

## 1. Matriz de códigos

| Sección | General | Embarazo | Preferencial compartido |
|---|---|---|---|
| Plataforma de Atención al Cliente | A | C | B |
| ODECO | D | F | E |
| Cajas | G | I | H |

## 2. Numeración

- **Modo:** Diario
- **Hora:** 00:00
- **Zona horaria:** America/La_Paz

## 3. Servicios exclusivos

- **Numeración:** Código general de la sección + etiqueta de servicio

## 4. Política de no presentado

- **Llamados antes de cerrar:** 2
- **Minutos tras el último llamado:** 1
- **Acción:** Cerrar como no presentado
- **Reencolado manual:** Nadie (solo el sistema)

## 5. Operadores y secciones

- **Regla:** Una sola sección por jornada

## 6. Cierre de jornada

- **Horario de operación:** 08:00–16:30
- **Tickets en espera:** Expiran al cierre
- **Tickets activos:** Se finalizan

## Confirmación

La persona responsable confirma que esta matriz refleja el acuerdo vigente y que no se asignaron letras por suposición.

## Addendum — Ajuste del cierre de jornada (2026-11-06)

Decisión posterior a la firma original:

- **Horario de operación:** 08:00 hasta la hora de cierre configurable desde el módulo de administración. Valor por defecto: **16:30**.
- **Ejecución:** el cierre se ejecuta automáticamente a la hora configurada.
- **Tickets en espera:** expiran.
- **Tickets llamados sin atención:** se marcan como **no presentados**.
- **Tickets en atención sin finalizar:** se finalizan.
- **Responsable:** Alvaro Navarro.

## Addendum — Operación, ventanillas y transferencias (Fase 3, 2026-11-06)

Decisiones de implementación posteriores al addendum de cierre, coherentes con el acta:

- **Ventanillas:** cada sección administra sus propias ventanillas. Una ventanilla activa pertenece a una sola sesión de operador. El nombre de ventanilla puede repetirse entre secciones; la unicidad operativa es por sección actual y etiqueta.
- **Sesiones de operador:** un operador mantiene una sola sesión activa y una sola sección por jornada. El cierre manual se rechaza si el operador tiene un ticket activo. Al cierre de jornada, las sesiones y asignaciones abiertas se cierran.
- **Cambio de ventanilla:** se permite dentro de la misma sección, sin ticket activo, y queda registrado con hora de inicio y fin.
- **Transferencia entre secciones:** la realiza el operador que atiende. Se permite desde `waiting` o `called`; un ticket llamado vuelve a la cola de destino, libera la asignación y reinicia el contador de llamados. Un ticket en atención no se transfiere. Se conservan código y número originales, y se registran sección y operador de origen, sección y operador de destino, motivo y fecha.
- **Reimpresión y fallos de impresora:** se registran como eventos del ticket (`reprinted`, `print_failed`) sin cambiar su estado; el reintento queda a cargo del operador.
- **Autenticación:** la Fase 3 no incorpora login ni autorización. La consola, las sesiones y la administración deben usarse solo en entorno local hasta la Fase 4.
- **Responsable:** Alvaro Navarro.

_Generado desde docs/formulario-decisiones.html el 2026-11-06T16:17:32.435Z._