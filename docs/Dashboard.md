# Dashboard — Pantalla 02

> Documento de referencia del dashboard: qué muestra, de dónde sale cada dato, qué
> archivo dibuja cada cosa y con qué se conecta. Igual que `arquitectura.md`, se mantiene
> al día en el mismo cambio que modifica el dashboard.

**Ruta:** `GET /dashboard` · **Nombre:** `dashboard` · **Middleware:** `auth`
**Mockup de referencia:** [`mockups/project-levelup-mockups.html`](mockups/project-levelup-mockups.html), pantalla 02
**Última actualización:** 3 de agosto de 2026

---

## 1. Qué es esta pantalla

Es el hogar del usuario y el único punto desde donde se llega a todo lo demás. Responde
dos preguntas, en ese orden:

1. **¿Cómo voy?** — la fila de KPI, que resume la cartera completa.
2. **¿Qué tengo?** — la grilla de tarjetas, una por proyecto.

Todo lo que se ve sale de la base de datos. No hay cifras de adorno: si un número aparece,
hay una consulta detrás.

---

## 2. Anatomía

```
┌──────────────┬──────────────────────────────────────────────────────────────┐
│  BARRA       │  TOPBAR   Mis proyectos · [5 activos] ····· 🔍 · [+ Nuevo] · │
│  LATERAL     ├──────────────────────────────────────────────────────────────┤
│  220 px      │  ┌────────┐┌────────┐┌────────┐┌────────┐                    │
│              │  │ Avance ││ Activ. ││ Ruta   ││ Próx.  │   fila de KPI      │
│  · logo      │  │ 57 %   ││ 24/42  ││ crít.14││ entrega│                    │
│  · nav       │  └────────┘└────────┘└────────┘└────────┘                    │
│  · proyectos │                                                              │
│  · cuenta    │  Proyectos activos      [Todos|Riesgo|Compl.] [Ordenar: ▾]   │
│  · consultas │  ┌─────────┐ ┌─────────┐ ┌─────────┐                         │
│  · salir     │  │ tarjeta │ │ tarjeta │ │ tarjeta │   grilla 1/2/3 columnas │
│              │  └─────────┘ └─────────┘ └─────────┘                         │
└──────────────┴──────────────────────────────────────────────────────────────┘
```

---

## 3. Archivos

| Archivo | Qué hace |
|---|---|
| `app/Http/Controllers/DashboardController.php` | Única acción. Resuelve filtro, orden, consulta y las cuatro métricas |
| `app/Enums/DashboardFilter.php` | Pestañas `todos` · `riesgo` · `completados` |
| `app/Enums/DashboardSort.php` | Orden `fecha-limite` · `avance` · `nombre` |
| `app/Models/Project.php` | `completionPercentage()`, `isAtRisk()`, `isOverdue()`, `healthTone()`, `daysBehindSchedule()` |
| `resources/views/dashboard/index.blade.php` | Estructura: topbar, KPI, encabezado, grilla |
| `resources/views/dashboard/partials/kpi-row.blade.php` | Las cuatro tarjetas de métricas |
| `resources/views/dashboard/partials/sparkline.blade.php` | Miniatura de tendencia (SVG) |
| `resources/views/dashboard/partials/filter-tabs.blade.php` | Pestañas de filtro |
| `resources/views/dashboard/partials/sort-menu.blade.php` | Menú de orden (`<details>` nativo) |
| `resources/views/dashboard/partials/project-card.blade.php` | Tarjeta de proyecto |
| `resources/views/layouts/sidebar.blade.php` | Barra lateral, compartida con las demás pantallas |
| `resources/views/components/layouts/app.blade.php` | Layout con barra lateral |
| `resources/views/components/logo.blade.php` | Logo de puntos |
| `resources/views/components/nav-icon.blade.php` | Iconos de 16×16, trazo 1.6 |
| `resources/views/components/avatar.blade.php` | Iniciales con color estable por persona |
| `resources/css/app.css` | Tokens de color y tipografía |
| `database/seeders/DashboardDemoSeeder.php` | Base de datos provisional |
| `tests/Feature/DashboardTest.php` | 15 pruebas |

---

## 4. Fidelidad con el mockup

### 4.1 Tipografía

El mockup usa la grotesca del sistema —SF Pro en macOS, Segoe UI en Windows—, que es la
familia "tipo ClickUp" que pidió el cliente. La aplicación usa **exactamente el mismo
stack**:

```css
--font-sans: ui-sans-serif, -apple-system, 'SF Pro Text', 'SF Pro Display',
             'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
--font-mono: ui-monospace, 'SF Mono', SFMono-Regular, 'JetBrains Mono', Menlo, Consolas, monospace;
```

**Se quitó Instrument Sans** del `vite.config.js`. Venía del esqueleto de Laravel, se
descargaba desde Bunny Fonts y no es la fuente del diseño. Ahora no se descarga ninguna
fuente remota: la página no depende de la red para verse bien.

La monoespaciada con `font-variant-numeric: tabular-nums` (utilidad `.num`) se usa en
**toda cifra que se lea en columna**: porcentajes, conteos, duraciones y fechas. Es lo que
hace que las tarjetas se alineen visualmente entre sí.

Escala de tamaños, tal cual el mockup:

| Uso | Tamaño | Peso |
|---|---|---|
| Cifra de KPI | 26 px | 600 |
| Título de topbar | 15.5 px | 700 |
| Encabezado de sección | 15 px | 700 |
| Título de tarjeta | 14.5 px | 600 |
| Cuerpo / navegación | 13 px | 400–500 |
| Etiquetas y notas | 11–11.5 px | 400–600 |
| Etiquetas en mayúscula | 10–11 px | 600–700, `tracking` 0.07–0.12em |

### 4.2 Colores

Definidos en `@theme` dentro de `resources/css/app.css`. Los valores son idénticos a los
del mockup.

| Token Tailwind | Hex | Uso |
|---|---|---|
| `brand-500` | `#2B57F6` | Acento único: botón primario, barras, enlaces, ítem activo |
| `brand-600` | `#1A3FD0` | Hover del primario, texto sobre fondo claro de marca |
| `brand-100` | `#E8EDFF` | Fondo del ítem activo, relleno del sparkline |
| `brand-50` | `#F2F5FF` | Fondo del bloque de consultas, hover suave |
| `page` | `#F4F6FC` | Fondo de página con trama de puntos |
| `ink-50` | `#F7F9FE` | Fondo de la barra lateral y del buscador |
| `ink-100` | `#EEF2FB` | Pistas de barras de progreso, pastillas neutras |
| `ink-200` | `#E3E8F4` | Bordes de tarjetas y separadores |
| `ink-300` | `#D3DBEE` | Bordes de controles y líneas más marcadas |
| `ink-400` | `#949DB6` | Texto terciario, etiquetas, iconos apagados |
| `ink-500` | `#66718C` | Texto secundario |
| `ink-700` | `#3C465E` | Texto de navegación |
| `ink-900` | `#0B1220` | Texto principal |

**Colores semánticos, deliberadamente separados del acento** —el azul nunca significa
"bien" ni "mal", solo "es de la marca":

| Token | Hex | Fondo suave | Significa |
|---|---|---|---|
| `done` | `#12A57A` | `done-soft` `#E2F6EF` | Completado, en plazo |
| `slack` | `#E5A03A` | `slack-soft` `#FDF3E2` | Holgura baja, en curso |
| `critical` | `#E0524A` | `critical-soft` `#FDECEA` | Ruta crítica, atrasado, falla |

Avatares: `avatar-blue` `#2B57F6`, `avatar-violet` `#7B5CF0`, `avatar-amber` `#E5A03A`.

> **Cuidado con Tailwind:** los nombres de clase no pueden armarse concatenando en tiempo
> de ejecución (`bg-{{ $tono }}` no compila). Donde el color depende de datos, la vista
> tiene un arreglo con las clases escritas completas —ver `$toneDot` en `sidebar.blade.php`
> y `$badgeClasses` en `project-card.blade.php`—, porque Tailwind escanea el archivo
> buscando literales.

### 4.3 Diferencias conscientes con el mockup

| Mockup | Implementación | Por qué |
|---|---|---|
| Pila de 2–3 avatares por tarjeta | Un solo avatar, el de la persona dueña | Todavía no existe modelo de equipo. Cuando exista, la pila entra aquí |
| "Actividades" y "Calendario" navegables | Ítems visibles pero inertes, con `title` explicativo | No están implementadas; ocultarlas escondería el alcance del producto |
| Buscador funcional | Campo presente, sin búsqueda todavía | Pendiente; ver sección 9 |
| Cifras fijas (58 %, 47/81…) | Cifras calculadas | El mockup ilustra; la app calcula |

---

## 5. De dónde sale cada dato

### 5.1 La consulta

Una sola, con tres `withCount` sobre la misma relación:

```php
Project::where('user_id', $userId)->latest()->withCount([
    'activities',
    'activities as completed_activities_count' => fn ($q) => $q->whereNotNull('completed_at'),
    'activities as critical_activities_count'  => fn ($q) => $q->where('is_critical', true),
])->get();
```

`Project::completionPercentage()` reutiliza esos conteos si vienen cargados; si no, los
consulta. Sin eso, cada tarjeta dispararía dos consultas extra.

### 5.2 Fila de KPI

| Tarjeta | Cifra | Cómo se calcula |
|---|---|---|
| **Avance promedio** | `%` | Actividades cerradas / totales de toda la cartera |
| | Nota | Diferencia contra el primer punto de la tendencia (hace 7 semanas) |
| | Sparkline | 7 puntos semanales de avance acumulado, desde `completed_at` |
| **Actividades completadas** | `n/N` | Suma de los dos conteos |
| | Nota | Actividades con `completed_at` en los últimos 7 días |
| **En ruta crítica** | `n` | Actividades con `is_critical` y sin `completed_at` |
| | Pastillas | *Atrasadas*: su fecha proyectada ya pasó. *En curso*: el resto |
| **Próxima entrega** | Fecha | Proyecto no completado con `deadline` más cercano |
| | Pastilla | `isOverdue()` del proyecto |

La fila **siempre resume la cartera completa**, aunque la grilla esté filtrada: responde
"¿cómo voy?", no "¿qué estoy mirando?".

### 5.3 Tarjeta de proyecto

| Elemento | Origen |
|---|---|
| Título | `projects.name` |
| Subtítulo | `ProjectType::label()` + conteo de actividades |
| Pastilla de estado | Ver tabla 6.1 |
| Porcentaje | `completionPercentage()` |
| Fecha | `projects.deadline`, en rojo si el proyecto va atrasado |
| Barra | Mismo porcentaje; el color repite el estado |
| Avatar | Persona dueña |
| Pie | `total_duration_days` + `critical_activities_count`, o el aviso de malla faltante |

### 5.4 Barra lateral

| Elemento | Origen |
|---|---|
| Lista de proyectos | Los 6 más recientes del usuario, **sin filtrar** — es navegación |
| Punto de color | `Project::healthTone()`, el mismo estado que la pastilla |
| Medidor de consultas | `users.ai_credits_used` / `users.ai_credits_limit` |
| Fecha de renovación | Primer día del mes siguiente |

---

## 6. Reglas de estado

### 6.1 Pastilla de la tarjeta

Se evalúan en orden; gana la primera que se cumple:

| Condición | Pastilla | Barra |
|---|---|---|
| `status = draft` | Borrador | azul |
| `status = generating` | Generando | azul |
| `status = failed` | Falló la generación | rojo |
| Avance 100 % | Completado | verde |
| `isOverdue()` | Atrasado *n* d | rojo |
| `isAtRisk()` | Holgura baja | ámbar |
| resto | En plazo | azul |

### 6.2 Definiciones

```php
// Días entre el término proyectado y la fecha límite. Negativo = margen.
daysBehindSchedule() = (starts_on + total_duration_days) - deadline

isOverdue()  = daysBehindSchedule() > 0
isAtRisk()   = falló la generación
             ∨ (no completado ∧ daysBehindSchedule() > -7)
```

**El umbral de 7 días** (`Project::RISK_THRESHOLD_DAYS`) es la ventana en la que todavía se
puede reaccionar moviendo gente. Por debajo de eso ya no hay margen de maniobra, y por eso
un proyecto con menos de una semana de holgura se marca antes de que sea tarde.

### 6.3 Accesibilidad

**Ningún estado depende solo del color.** La pastilla siempre lleva texto ("Atrasado 4 d",
"Holgura baja") y el punto de la barra lateral acompaña al nombre del proyecto, que es
enlace. El sparkline tiene `aria-label` con la serie completa en texto.

---

## 7. Filtro y orden

Ambos viajan en la URL, así que un estado del dashboard se puede compartir y sobrevive a
recargar la página.

```
/dashboard?filtro=riesgo&orden=avance
```

| Parámetro | Valores | Por defecto |
|---|---|---|
| `filtro` | `todos` · `riesgo` · `completados` | `todos` |
| `orden` | `fecha-limite` · `avance` · `nombre` | `fecha-limite` |

Un valor inválido cae al valor por defecto en vez de fallar (`tryFrom() ?? …`).

Se filtra y ordena **en memoria**, no en SQL: los KPI necesitan la cartera completa de
todos modos, así que filtrar en la consulta obligaría a una segunda consulta para el
resumen. Un usuario del MVP tiene decenas de proyectos, no miles.

Al ordenar por fecha límite, los proyectos **sin fecha van al final** (se ordenan como
`9999-12-31`), no al principio.

El menú de orden usa `<details>` / `<summary>` nativo: abre y cierra con teclado sin
JavaScript propio y funciona aunque Vite no haya compilado nada.

---

## 8. Base de datos provisional

`database/seeders/DashboardDemoSeeder.php` puebla la cartera que se ve en el mockup. Es
data de demostración: se borra y regenera sin consecuencias.

```bash
php artisan migrate:fresh --seed
```

**Usuario:** `demo@levelup.test` / `password` — 12 de 20 consultas usadas.

| Proyecto | Act. | Avance | Duración | Estado |
|---|---:|---:|---:|---|
| App Banca Móvil | 8 | 75 % | 39 d | Holgura baja |
| Migración ERP Finanzas | 14 | 43 % | 77 d | En plazo |
| Rediseño Web Corporativa | 11 | 27 % | 53 d | Atrasado 4 d |
| Portal de Clientes v2 | 9 | 100 % | 31 d | Completado |
| Lanzamiento Campaña Q4 | 0 | 0 % | — | Borrador |

**Los cinco estados están representados a propósito**, para poder revisar de un vistazo que
cada rama de la tabla 6.1 se dibuja bien.

Detalles que importan:

- **Las mallas son reales.** Cada proyecto pasa por `CpmCalculator`: los `ES/EF/LS/LF`,
  las holguras y la ruta crítica están calculados, no inventados.
- **«App Banca Móvil» no se define aquí.** La aporta `DemoProjectSeeder`, que es el dueño
  único de la malla de referencia (A→C→D→F→G→H = 39 días). `DashboardDemoSeeder` lo invoca
  y solo le agrega avance. Así la malla de referencia sigue viviendo en un solo lugar.
- **Las fechas de cierre están escalonadas** en las últimas 7 semanas, de la más antigua a
  hace dos días. Sin eso el sparkline sería una línea plana y "cerradas esta semana"
  saldría en cero.
- **Las fechas son relativas a hoy** (`subDays` / `addDays`), así que la demo no envejece.

---

## 9. Idioma

`APP_LOCALE=es` en `.env` y por defecto en `config/app.php`. Sin eso, `translatedFormat()`
imprime "18 Jul 2026" en vez de "18 jul. 2026".

**No se usa `Str::plural`**: aplica reglas del inglés y convierte «actividad» en
«actividads». Los plurales van con `trans_choice`:

```blade
{{ trans_choice('{1} actividad|[0,*] actividades', $count) }}
```

Hay una prueba que vigila las dos cosas (`escribe plurales y fechas en español`).

---

## 10. Conexiones con el resto de la aplicación

**Entradas al dashboard**

| Desde | Cómo |
|---|---|
| `GET /` | Redirección |
| Login | Tras iniciar sesión |
| Cualquier pantalla | Logo o "Mis proyectos" en la barra lateral |
| Borrar un proyecto | Redirección con mensaje |

**Salidas**

| Elemento | Destino |
|---|---|
| Tarjeta de proyecto listo | `projects.show` — pantalla 06 |
| Tarjeta de proyecto no listo | `projects.generating` — pantalla 05 |
| Botón "Nuevo proyecto" y tarjeta punteada | `projects.create.type` — pantalla 03 |
| Proyecto en la barra lateral | Pantalla 06 o 05 según estado |
| "Cerrar sesión" | `POST /logout` → login |

**Dependencias de datos**

```
DashboardController
   ├── Project        conteos, avance, estado, fechas
   ├── Activity       fechas de cierre, actividades críticas
   ├── User           cuota de consultas IA (barra lateral)
   ├── DashboardFilter / DashboardSort
   └── CpmCalculator  indirecta: llenó is_critical y total_duration_days
```

---

## 11. Rendimiento

- **3 consultas** para la página completa: proyectos con conteos, fechas de cierre,
  actividades críticas. Más una por la barra lateral.
- **Ninguna consulta dentro de un bucle.** `Model::shouldBeStrict()` está activo fuera de
  producción y haría fallar la página si se colara un *lazy load*.
- La tarjeta **no** accede a `$project->user`, justamente para no caer en eso: el dashboard
  solo lista proyectos propios, así que la persona dueña es siempre quien tiene sesión.

---

## 12. Pruebas

`tests/Feature/DashboardTest.php` — 15 pruebas.

| Grupo | Qué verifica |
|---|---|
| Aislamiento | Solo se ven los proyectos propios |
| Avance | El porcentaje sale de las actividades completadas |
| Estados | Las cinco pastillas aparecen con la cartera de demostración |
| KPI | Las cuatro tarjetas y sus cifras |
| Filtros | `riesgo` y `completados` cambian la grilla, no la barra lateral |
| Orden | Fecha límite (sin fecha al final), nombre y avance |
| Robustez | Un filtro u orden inventado no rompe la página |
| Vacíos | Sin proyectos, y sin resultados para el filtro |
| Idioma | Plurales y fechas en español |
| Cuota | El medidor de consultas de la barra lateral |

Las pruebas de filtro aíslan la grilla con `Str::after($content, 'Proyectos activos')`,
porque la barra lateral lista todos los proyectos a propósito y la fila de KPI también
nombra al de la próxima entrega.

---

## 13. Qué falta

- [ ] Buscador funcional (proyectos y actividades).
- [ ] Pila de avatares, cuando exista modelo de equipo.
- [ ] Pantallas "Actividades" y "Calendario".
- [ ] Paginación o carga incremental, si un usuario llega a cientos de proyectos.
- [ ] Refresco automático de las tarjetas en estado `generating`, hoy hay que recargar.
