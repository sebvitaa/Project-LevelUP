# Arquitectura — Project LevelUp

> **Este documento es el registro vivo de la arquitectura.** Toda carpeta, archivo, ruta,
> tabla o decisión de diseño que se agregue al proyecto se documenta aquí, en el mismo
> commit que la introduce. Si algo existe en el código y no está en este archivo, el
> archivo está desactualizado y hay que corregirlo. Así seguirá siendo durante todo el
> desarrollo.

**Proyecto:** Project LevelUp — IIP323W, Unidad 3 (Laravel)
**Equipo:** Gabriel Marín (frontend) · Sebastián Ramírez (backend)
**Última actualización:** 12 de agosto de 2026

---

## 1. Qué hace la aplicación

El usuario describe un proyecto en lenguaje natural. La IA lo descompone en actividades
con duraciones y precedencias, y el servidor resuelve el **método de la ruta crítica
(CPM)** sobre ese grafo. El resultado es una malla navegable con ruta crítica, holguras
y fechas.

El flujo son seis pantallas, diseñadas en
[`docs/mockups/project-levelup-mockups.html`](mockups/project-levelup-mockups.html):

```
01 Login/Registro → 02 Dashboard → 03 Tipo de proyecto → 04 Prompt
                         ↑                                    ↓
                         └──────── 06 Malla CPM ←──── 05 Generando
```

---

## 2. Decisiones de arquitectura

Las decisiones que explican por qué el código está organizado así.

### 2.1 El CPM se calcula en el servidor, no en el prompt

A la IA se le piden **solo** actividades, duraciones y precedentes. Los tiempos ES/EF/LS/LF,
las holguras y la ruta crítica los calcula `CpmCalculator` en PHP.

**Por qué:** un modelo de lenguaje no es una herramienta de cálculo confiable y daría
resultados distintos ante la misma entrada. El CPM es un algoritmo determinista de
libro; hacerlo en PHP lo vuelve reproducible, testeable sin red y gratis.

### 2.2 `CpmCalculator` no conoce Eloquent

Recibe arreglos planos y devuelve objetos `ScheduledActivity` de solo lectura. Persistir
es responsabilidad de quien lo llama.

**Por qué:** es la pieza con más lógica del proyecto y así se prueba sin base de datos
(`tests/Unit/CpmCalculatorTest.php` corre en milisegundos). También permite reutilizarlo
para simular escenarios sin escribir nada.

### 2.3 La generación va en cola, no en el request

`ProjectController@store` crea el proyecto en estado `generating` y despacha
`GenerateProjectSchedule`. La pantalla 05 hace *polling* a `projects.status` cada 2 s.

**Por qué:** la llamada a Gemini tarda unos 30 segundos, muy por encima de lo razonable
para una respuesta HTTP. Además permite mostrar progreso con nombre en vez de un spinner
mudo.

### 2.4 El crédito de IA se descuenta al final, no al principio

`GenerateProjectSchedule` cobra dentro de la misma transacción que completa el proyecto. La
transacción bloquea el proyecto y el usuario, verifica saldo, marca `charged_generation_attempt`
y aumenta `ai_credits_used`; una repetición del mismo intento no puede cobrar dos veces.

**Por qué:** si la API de Google se cae o devuelve basura, el error no es del usuario y no
tiene por qué costarle una de sus 20 consultas mensuales.

### 2.5 El tipo de proyecto viaja en sesión hasta el POST final

El paso 1 del asistente guarda el tipo en `session('project_wizard.type')`. El registro en
la tabla `projects` recién nace en el paso 2.

**Por qué:** si el usuario abandona el asistente, no quedan filas huérfanas en la base.

### 2.6 El progreso de generación se registra como metadata del proyecto

El proyecto conserva el hito actual, el número de intento y las marcas de tiempo de inicio y
progreso. `ProjectGenerationStage` limita los valores posibles a hitos observables del backend.
El endpoint de estado traduce cada hito a un paso visible, timestamp, mensaje, necesidad de input
y alerta de estancamiento sin inventar progreso temporal.

**Por qué:** el polling necesita distinguir un trabajo activo, un intento antiguo y un proceso
estancado sin inventar progreso basado en tiempo ni duplicar el estado en otra tabla.

### 2.7 Cada generación se protege por proyecto e intento

`GenerateProjectSchedule` recibe el identificador del proyecto y el `generation_attempt` que lo
originó. Implementa unicidad por proyecto, abandona sin efectos los jobs antiguos y solo marca
fallos si el intento sigue vigente. La creación y la regeneración actualizan el intento dentro de
una transacción y despachan el job con `afterCommit()`; la persistencia de la malla vuelve a
bloquear el proyecto antes de reemplazar sus actividades.

**Por qué:** un reintento, doble submit o worker duplicado no debe sobrescribir una generación más
nueva ni dejar un proyecto listo en estado fallido.

---

### 2.8 Las aclaraciones pertenecen a un proyecto y a un intento

Las preguntas que la IA necesite hacer se persisten en `project_clarifications`, relacionadas
con el proyecto y numeradas por `generation_attempt` y `round`. La clave `key` identifica cada
pregunta dentro de una ronda; `answer` y `answered_at` distinguen una pregunta pendiente de una
ya respondida.

**Por qué:** las preguntas de un intento descartado no deben mezclarse con las del intento
vigente. La unicidad `(project_id, generation_attempt, round, key)` también evita duplicar la
misma pregunta al reintentar una operación.

El análisis usa una sola ronda de entre 1 y 3 preguntas. Si no hace falta aclarar, el proyecto
transita directamente de `Clarifying`/`analyzing_brief` a `Generating`/`requesting_plan`; si hace
falta, queda en `AwaitingInput`/`awaiting_answers`. Ninguna llamada de análisis consume crédito:
solo se cobra cuando la malla final llega a `Ready`.

### 2.9 El calendario y el Gantt se derivan del CPM

`Activity::startDate()` y `finishDate()` usan intervalos inclusivos: una actividad de un día que
empieza el 1 de enero termina el 1 de enero. `Project::projectedFinishDate()` aplica la misma regla
a la duración total. `GanttTimelineBuilder` recibe el proyecto y sus actividades ya cargadas,
calcula el horizonte hasta el mayor entre el término CPM y el deadline, y devuelve un contrato de
presentación sin escribir en la base ni recalcular el grafo.

La pantalla 06 acepta `?view=network|gantt`; ambas vistas conservan `?activity={code}`. La vista
Gantt cambia de escala diaria a semanal y mensual según el horizonte, agrupa meses, marca hoy y el
deadline, y distingue fines de semana. Las barras no dibujan flechas: las precedencias ya están
representadas por la malla CPM y en el Gantt se muestran como datos de fila.

### 2.10 El avance es independiente de la planificación

`completed_at` solo representa avance del usuario. Marcar una actividad como hecha no modifica ES,
EF, holgura ni ruta crítica. La malla y el Gantt muestran el estado completado con semántica verde,
check y texto, incluso si la actividad sigue siendo crítica. Una actividad no completada cuyo último
día previsto ya pasó se marca como `isOverdue()` y usa semántica roja; el día actual todavía no se
considera vencido.

### 2.11 Dashboard de cartera

El dashboard resume siempre la cartera completa del usuario autenticado y aplica filtros,
orden y búsqueda solamente a la grilla visible. Los parámetros GET son `filtro`
(`todos`, `riesgo`, `completados`), `orden` (`fecha-limite`, `avance`, `nombre`) y `q`.
La búsqueda cubre nombre de proyecto y nombre/descripción de actividad, restringida
desde la consulta al propietario.

Los KPI se calculan desde proyectos y actividades persistidos. `DashboardFilter` y
`DashboardSort` concentran la semántica; `Project::isCompleted()`, `isAtRisk()` y
`healthTone()` son la fuente común para tarjetas y barra lateral. Un view composer carga
los seis proyectos laterales con conteos, evitando lazy loading dentro de Blade.

La interfaz incorpora `avatar`, `nav-icon` y los parciales `filter-tabs`, `sort-menu`,
`kpi-row` y `sparkline`. Los controles conservan los demás parámetros activos y muestran
estados vacíos distintos para una cartera vacía y una consulta sin coincidencias.
`DashboardDemoSeeder` crea una cartera reproducible y reutiliza `DemoProjectSeeder`; no
realiza llamadas a Gemini y puede ejecutarse repetidamente mediante `updateOrCreate`.

---

## 3. Estructura de carpetas

Solo se listan las carpetas y archivos propios del proyecto. Lo que no aparece aquí es
esqueleto estándar de Laravel sin modificar.

```
app/
├── Enums/
│   ├── ProjectStatus.php          Draft · Clarifying · AwaitingInput · Generating · Ready · Failed
│   ├── ProjectType.php            6 tipos + su contexto de dominio para el prompt
│   └── ProjectGenerationStage.php  Hitos observables de la generación
├── Exceptions/
│   └── PlanGenerationException.php  Errores de generación, con mensaje para el usuario
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthenticatedSessionController.php   Pantalla 01 — login/logout
│   │   │   └── RegisteredUserController.php         Pantalla 01 — registro
│   │   ├── ActivityController.php            Panel lateral de la pantalla 06
│   │   ├── Controller.php                    Base, con AuthorizesRequests
│   │   ├── DashboardController.php           Pantalla 02
│   │   ├── ProjectController.php             POST del asistente + pantalla 06
│   │   ├── ProjectGenerationController.php   Pantalla 05 + endpoint de estado
│   │   └── ProjectWizardController.php       Pantallas 03 y 04
│   └── Requests/
│       ├── Auth/
│       │   ├── LoginRequest.php              Validación + rate limiting por correo/IP
│       │   └── RegisterRequest.php
│       ├── StoreProjectRequest.php
│       └── UpdateActivityRequest.php
├── Jobs/
│   ├── GenerateProjectClarifications.php Job en cola: análisis del brief
│   └── GenerateProjectSchedule.php   Job en cola: IA → CPM → persistencia
├── Models/
│   ├── Activity.php                  Nodo de la malla
│   ├── Project.php                   Proyecto, malla, estado y aclaraciones
│   ├── ProjectClarification.php      Pregunta persistida para precisar el brief
│   └── User.php                      + relación projects() y cuota de IA
├── Policies/
│   └── ProjectPolicy.php             Un proyecto solo lo ve y lo toca su dueño
├── Providers/
│   └── AppServiceProvider.php        Binding de GeminiClient + modo estricto de Eloquent
└── Services/
    ├── Ai/
    │   ├── GeminiClient.php          Cliente HTTP delgado de la API de Google
    │   ├── ClarificationPromptBuilder.php Prompt estructurado de aclaraciones
    │   ├── ProjectClarificationGenerator.php Valida y persiste preguntas
    │   ├── ProjectPlanGenerator.php  Orquesta prompt → validación → CPM → guardado
    │   └── PromptBuilder.php         Arma system prompt, user prompt y responseSchema
    ├── Cpm/
        ├── CpmCalculator.php         Pasadas hacia adelante y atrás, holguras, layout
        └── ScheduledActivity.php     Resultado inmutable por actividad
    └── Gantt/
        └── GanttTimelineBuilder.php  Contrato temporal y filas para la vista Gantt

database/
├── factories/
│   ├── ActivityFactory.php           + estados critical() y completed()
│   ├── ProjectFactory.php            + estados draft() clarifying() awaitingInput() generating() ready() failed()
│   ├── ProjectClarificationFactory.php Estados pending(), answered() y select()
│   └── UserFactory.php               + estado withoutAiCredits()
├── migrations/
│   ├── 2026_08_03_195333_create_projects_table.php
│   ├── 2026_08_03_195334_create_activities_table.php
│   ├── 2026_08_03_195335_create_activity_dependencies_table.php
│   ├── 2026_08_03_195336_add_ai_credits_to_users_table.php
│   ├── 2026_08_11_000000_add_generation_metadata_to_projects_table.php
│   └── 2026_08_12_000000_create_project_clarifications_table.php
└── seeders/
    ├── DatabaseSeeder.php
    └── DemoProjectSeeder.php         Proyecto demo con la malla de los mockups

docs/
├── arquitectura.md                   Este archivo
├── prompt.md                         Pruebas de los prompts contra la API y cambios aplicados
├── mockups/
│   └── project-levelup-mockups.html  Las 6 pantallas + sistema de diseño
└── plantilla-propuesta-laravel.docx  Propuesta entregada al ramo

resources/
├── css/app.css                       Tokens de marca, animación y reduced motion
├── js/
│   ├── app.js                         Entradas de watcher y scroll CPM
│   ├── generation-watcher.js          Polling robusto de la pantalla 05
│   └── cpm-graph.js                   Centrado progresivo de la actividad seleccionada
└── views/
    ├── auth/
    │   ├── login.blade.php           Pantalla 01
    │   └── register.blade.php        Pantalla 01
    ├── components/
    │   ├── layouts/
    │   │   ├── app.blade.php         Layout con sidebar, para sesión iniciada
    │   │   └── guest.blade.php       Layout partido con panel de marca
    │   └── logo.blade.php            Logo de puntos
    ├── dashboard/
    │   ├── index.blade.php           Pantalla 02
    │   └── partials/project-card.blade.php
    ├── layouts/
    │   └── sidebar.blade.php         Navegación lateral + medidor de consultas
    └── projects/
        ├── create-type.blade.php     Pantalla 03
        ├── create-prompt.blade.php   Pantalla 04
        ├── generating.blade.php      Pantalla 05
        ├── show.blade.php            Pantalla 06
        └── partials/
            ├── activity-detail.blade.php   Ficha lateral de la actividad
            ├── cpm-graph.blade.php         Nodos + aristas SVG de la malla
            ├── gantt-chart.blade.php       Carta Gantt derivada del contrato temporal
            ├── clarifications-form.blade.php Preguntas text/select accesibles
            └── stepper.blade.php           Indicador de paso 1/2/3

tests/
├── Feature/
│   ├── AuthenticationTest.php        Login, registro, logout, rutas protegidas
│   ├── DashboardTest.php             Aislamiento por usuario y cálculo de avance
│   ├── ExampleTest.php               Redirección de la raíz
│   ├── ProjectScreenTest.php         Humo sobre la malla + recálculo al editar
│   ├── ProjectWizardTest.php         Asistente, validación y autorización
│   ├── ProjectClarificationFlowTest.php Análisis, transición, validación y prompt final
│   ├── ProjectClarificationModelTest.php Estados, casts, relación y filtro por intento vigente
│   ├── ProjectGenerationStatusTest.php Contrato de progreso y estados del watcher
│   ├── ProjectClarificationAnswersTest.php Formulario, selects y respuestas abiertas
│   └── ScheduleGenerationTest.php    Integración con Gemini vía Http::fake()
└── Unit/
    ├── CpmCalculatorTest.php         12 casos del algoritmo CPM
    ├── ClarificationPromptBuilderTest.php Reglas text/select del prompt
    ├── GanttTimelineBuilderTest.php   Escalas, horizonte, filas y markers
    ├── TimelineContractTest.php       Fechas inclusivas y deadlines
    ├── ProjectGenerationStageTest.php  Etapas, terminalidad y casts de metadata
    ├── ProjectStatusTest.php           Estados conversacionales y terminalidad
    └── QueueConfigurationTest.php      Retry de cola superior al timeout del job
```

Archivos de configuración relevantes para esta unidad: `config/queue.php` define los tiempos de
visibilidad de la cola; `.env.example` expone `DB_QUEUE_RETRY_AFTER`; `composer.json` ejecuta el
worker de desarrollo con timeout finito.

Archivos propios de la Fase 3: `app/Http/Controllers/ProjectClarificationController.php`,
`app/Http/Requests/StoreProjectClarificationAnswersRequest.php` y
`resources/views/projects/partials/clarifications-form.blade.php`.

Configuración de Fase 4: `config/levelup.php` define `GENERATION_STALLED_AFTER`, el umbral en
segundos para informar que una generación activa no ha registrado progreso.

---

## 4. Modelo de datos

```
users ──1:N──> projects ──1:N──> activities ──N:M──> activities
                                        (activity_dependencies)
```

### `projects`

| Columna | Tipo | Notas |
|---|---|---|
| `user_id` | FK → users | En cascada al borrar |
| `name` | string | |
| `type` | string | Casteado a `ProjectType` |
| `prompt` | text | La descripción que escribió el usuario |
| `starts_on` | date | Día 0 de la malla |
| `deadline` | date, nullable | Fecha deseada, para comparar con lo proyectado |
| `team_size` | smallint, nullable | Contexto para la IA |
| `status` | string | Casteado a `ProjectStatus` |
| `generation_stage` | string, nullable | Casteado a `ProjectGenerationStage`; hito observable actual |
| `generation_attempt` | unsigned integer | Intento vigente; default `0` |
| `charged_generation_attempt` | unsigned integer, nullable | Intento que ya consumió crédito |
| `generation_error` | text, nullable | Mensaje que se le muestra al usuario |
| `generated_at` | timestamp, nullable | |
| `generation_started_at` | timestamp, nullable | Inicio del intento vigente |
| `generation_progressed_at` | timestamp, nullable | Último hito persistido |
| `total_duration_days` | smallint, nullable | Largo de la ruta crítica, cacheado |

Índices: `(user_id, status)` y `deadline`.

### `project_clarifications`

Preguntas que la IA genera para precisar el brief antes de construir la malla.

| Columna | Tipo | Notas |
|---|---|---|
| `project_id` | FK → projects | En cascada al borrar |
| `round` | tinyint | Ronda conversacional, default `1` |
| `generation_attempt` | unsigned integer | Intento al que pertenece la pregunta |
| `key` | string(64) | Identificador estable dentro de la ronda |
| `question` | text | Texto mostrado al usuario |
| `rationale` | text, nullable | Motivo de la aclaración |
| `input_type` | string | Tipo de control esperado, default `text` |
| `options` | JSON, nullable | Opciones para controles cerrados |
| `answer` | text, nullable | Respuesta del usuario |
| `answered_at` | timestamp, nullable | Momento en que se respondió |

Restricciones: único `(project_id, generation_attempt, round, key)` e índice
`(project_id, generation_attempt, answered_at)`. `Project::pendingClarifications()` filtra las
preguntas sin respuesta del intento vigente.

### `activities`

| Columna | Tipo | Notas |
|---|---|---|
| `project_id` | FK → projects | |
| `code` | string(4) | A, B, C… único dentro del proyecto |
| `name`, `description` | string / text | Generados por la IA |
| `duration_days` | smallint | Días corridos |
| `early_start` `early_finish` | smallint, nullable | Pasada hacia adelante |
| `late_start` `late_finish` | smallint, nullable | Pasada hacia atrás |
| `slack` | smallint, nullable | `late_start − early_start` |
| `is_critical` | boolean | `slack === 0` |
| `grid_column` `grid_row` | smallint | Posición en el lienzo, la calcula el CPM |
| `completed_at` | timestamp, nullable | Avance, independiente de la planificación |

Índices: único `(project_id, code)`, e índice `(project_id, is_critical)`.

Los tiempos CPM son intervalos de días corridos con inicio incluido: `early_start = 0` y
`duration_days = 1` producen la misma fecha de inicio y término. `finishDate()` y
`projectedFinishDate()` restan un día al final temprano o a la duración total para conservar esa
semántica.

### `activity_dependencies`

Aristas del grafo. `activity_id` no puede empezar hasta que `predecessor_id` termine
(relación fin-comienzo). Único `(activity_id, predecessor_id)`.

### `users` (columnas agregadas)

`ai_credits_limit` (default 20), `ai_credits_used` (default 0), `ai_credits_reset_at`.
Son la base del plan de suscripción por consultas.

**Nota:** al agregar una columna con valor por defecto en la base, hay que reflejarla
también en la factory correspondiente. Con `Model::shouldBeStrict()` activo, un modelo
creado en memoria que no tenga ese atributo lanza `MissingAttributeException`.

---

## 5. Rutas

Todas en `routes/web.php`. Las URL están en español porque son visibles para el usuario.

| Método | URI | Nombre | Pantalla |
|---|---|---|---|
| `GET` | `/` | `home` | Redirige a `/dashboard` |
| `GET` | `/login` | `login` | 01 |
| `POST` | `/login` | — | 01 |
| `GET` | `/register` | `register` | 01 |
| `POST` | `/register` | — | 01 |
| `POST` | `/logout` | `logout` | — |
| `GET` | `/dashboard` | `dashboard` | 02 |
| `GET` | `/projects/new/tipo` | `projects.create.type` | 03 |
| `POST` | `/projects/new/tipo` | `projects.store.type` | 03 |
| `GET` | `/projects/new/descripcion` | `projects.create.prompt` | 04 |
| `POST` | `/projects` | `projects.store` | 04 → 05 |
| `GET` | `/projects/{project}/generando` | `projects.generating` | 05 |
| `GET` | `/projects/{project}/estado` | `projects.status` | 05 (JSON) |
| `POST` | `/projects/{project}/clarifications` | `projects.clarifications.store` | 05 (respuestas) |
| `POST` | `/projects/{project}/regenerar` | `projects.regenerate` | 05 |
| `GET` | `/projects/{project}/malla` | `projects.show` | 06 |
| `DELETE` | `/projects/{project}` | `projects.destroy` | 06 |
| `PATCH` | `/activities/{activity}` | `activities.update` | 06 |
| `POST` | `/activities/{activity}/completar` | `activities.toggle` | 06 |

`projects.show` admite `view=network|gantt`; `activity={code}` conserva la selección al cambiar
de vista y después de editar o completar una actividad. El endpoint de estado de la pantalla 05
es consultado por el watcher solo en estados `Clarifying` y `Generating`.

Middleware: `guest` en login y registro, `auth` en todo el resto.
Autorización: `ProjectPolicy` mediante `$this->authorize()` en los controladores.

---

## 6. El algoritmo CPM

`App\Services\Cpm\CpmCalculator::calculate()` recibe
`[['code' => 'A', 'duration_days' => 5, 'predecessors' => []], …]` y devuelve un arreglo
de `ScheduledActivity` indexado por código.

**Pasos:**

1. **Construir el grafo** y validar: códigos únicos, precedentes existentes, duraciones no
   negativas.
2. **Orden topológico** por el algoritmo de Kahn. Si sobran nodos con precedentes
   pendientes, hay un ciclo y se lanza `InvalidArgumentException`.
3. **Pasada hacia adelante:** `ES = max(EF de los precedentes)`, `EF = ES + duración`.
4. **Duración del proyecto:** el mayor `EF` de la malla.
5. **Pasada hacia atrás:** `LF = min(LS de las sucesoras)`, `LS = LF − duración`. Sin
   sucesoras, `LF` es la duración del proyecto.
6. **Holgura:** `LS − ES`. Holgura 0 significa ruta crítica.
7. **Layout:** la columna es la profundidad en el grafo; la fila ordena por holgura
   ascendente, de modo que la ruta crítica queda como una línea horizontal arriba.

### Malla de referencia

Es la misma que aparece en los mockups, en `DemoProjectSeeder` y en los tests unitarios.
Sirve como caso de verificación a ojo:

| Código | Actividad | Dur. | Precedentes | ES | EF | LS | LF | Holgura |
|---|---|---:|---|---:|---:|---:|---:|---:|
| A | Levantamiento de requerimientos | 5 | — | 0 | 5 | 0 | 5 | **0** |
| B | Diseño UX/UI | 8 | A | 5 | 13 | 11 | 19 | 6 |
| C | Arquitectura backend | 6 | A | 5 | 11 | 5 | 11 | **0** |
| D | Desarrollo de la API | 12 | C | 11 | 23 | 11 | 23 | **0** |
| E | Desarrollo frontend | 10 | B | 13 | 23 | 19 | 29 | 6 |
| F | Integración pasarela de pagos | 6 | D | 23 | 29 | 23 | 29 | **0** |
| G | QA y pruebas de carga | 7 | E, F | 29 | 36 | 29 | 36 | **0** |
| H | Publicación en stores | 3 | G | 36 | 39 | 36 | 39 | **0** |

**Ruta crítica:** A → C → D → F → G → H = **39 días**.

---

## 7. Integración con Gemini

Clases con una responsabilidad cada una:

- **`PromptBuilder`** arma el *system prompt* (rol, reglas, contexto según
  `ProjectType::domainHint()`), el *user prompt* (descripción + fechas + equipo + días
  disponibles hasta el plazo) y el `responseSchema`.
- **`GeminiClient`** solo habla HTTP: `POST {base_url}/models/{model}:generateContent`
  con la clave en el header `x-goog-api-key`, y devuelve el JSON decodificado. Se falsea
  en los tests con `Http::fake()`.
- **`ProjectPlanGenerator`** valida la forma de la respuesta, llama al `CpmCalculator` y
  guarda todo en una transacción.
- **`ClarificationPromptBuilder`** y **`ProjectClarificationGenerator`** hacen la primera
  llamada estructurada: validan una decisión consistente con 0 o 1–3 preguntas y persisten la
  ronda si el brief necesita información adicional.

Se usa **respuesta estructurada** (`responseMimeType: application/json` +
`responseSchema`) para no tener que parsear texto libre, con `temperature: 0.2` para que
la salida sea lo más estable posible.

Los días disponibles hasta la fecha límite se calculan en PHP (`PromptBuilder::availableDays()`)
y se le pasan al modelo como dato, por la misma razón que el CPM no se le pide a la IA: se
equivoca haciendo aritmética de fechas. El texto de los dos prompts se ajustó contra la API real
con un banco de escenarios; el registro de esas pruebas, lo que se encontró y lo que se cambió
está en [`docs/prompt.md`](prompt.md). Ese archivo hay que actualizarlo cuando se reescriban las
reglas de `PromptBuilder` o `ClarificationPromptBuilder`, junto con las aserciones de
`tests/Unit/ClarificationPromptBuilderTest.php`, que afirman sobre el texto literal del prompt.

Regenerar **reemplaza** la malla completa: borrar y volver a crear, no mezclar. Mezclar
dos grafos distintos daría dependencias inconsistentes.

La creación comienza en `Clarifying`. `GenerateProjectClarifications` es único por proyecto y
por intento; un brief suficiente cambia el estado a `Generating` y encola el job final, mientras
que un brief ambiguo persiste las preguntas y cambia a `AwaitingInput`. Un resultado de un intento
antiguo no puede cambiar el proyecto ni encolar una generación posterior.

La respuesta de aclaraciones exige exactamente todas las preguntas pendientes del intento vigente.
El controlador vuelve a consultar bajo `lockForUpdate()`, guarda `answer` y `answered_at` y solo
despacha `GenerateProjectSchedule` con `afterCommit()` cuando la transición a `Generating` quedó
confirmada.

Las respuestas confirmadas se agregan a `PromptBuilder::userPrompt()` como datos del usuario, en
una sección separada de las instrucciones de sistema. La regeneración conserva las respuestas
existentes copiándolas al nuevo intento antes de encolar el plan final.

El job final recibe `project_id` e `generation_attempt` como escalares y es único por proyecto.
Antes de llamar a Gemini, antes de persistir y al manejar un fallo verifica que el intento y el
estado sigan vigentes. La escritura de actividades toma un lock del proyecto dentro de la
transacción; por eso un job antiguo abandona sin reemplazar una malla posterior.

El job mantiene timeout de 120 segundos y las conexiones de cola usan `retry_after` de 180
segundos por defecto. En desarrollo, `composer run dev` inicia `queue:listen` con timeout finito
de 120 segundos; `retry_after` debe permanecer por encima de ese límite.

`ProjectGenerationController@status` devuelve `status`, `stage`, `step_index`, `step_count`,
`message`, `is_terminal`, `needs_input`, `started_at`, `last_progress_at`, `is_stalled`, `error`
y `redirect_to`. La respuesta lleva `Cache-Control: no-store`; el estancamiento solo informa, no
despacha jobs adicionales.

### Configuración

En `config/services.php`, leyendo del `.env`:

```
GEMINI_API_KEY=          # obligatoria en producción
GEMINI_MODEL=gemini-3.1-flash-lite
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_TIMEOUT=60
```

---

## 8. Frontend

- **Blade + Tailwind v4.** Los tokens de marca están en `@theme` dentro de
  `resources/css/app.css`: `brand-*` para el azul, `ink-*` para los neutros con sesgo azul,
  y `done` / `slack` / `critical` como colores semánticos, deliberadamente separados del
  acento de marca.
- **Dos layouts:** `<x-layouts.app>` con sidebar para sesión iniciada,
  `<x-layouts.guest>` partido con panel de marca para login y registro.
- **La malla es SVG + posicionamiento absoluto**, sin librería de grafos. Las coordenadas
  salen de `grid_column` / `grid_row`, que ya vienen calculados desde el servidor.
- **JavaScript, lo mínimo:** `generation-watcher.js` hace una consulta inmediata, evita peticiones
  simultáneas, aplica timeout/backoff, pausa con la pestaña oculta, actualiza el mensaje, la barra
  y los cinco pasos visibles, y recarga una vez al detectar `needs_input`. `cpm-graph.js` centra el
  nodo seleccionado dentro del viewport sin desplazar la página y respeta `prefers-reduced-motion`.
- **Estados de la pantalla 05:** Blade renderiza una rama distinta para `AwaitingInput`,
  `Failed`, `Ready` y trabajo activo. El trabajo activo muestra el hito persistido, el paso
  actual y una advertencia si `is_stalled` está activo; la respuesta de preguntas no depende de JS.
- **Scroll de aclaraciones:** cuando hay preguntas, la pantalla usa `min-h-full` y alineación
  superior. Así el contenido puede crecer más que el viewport y el `main` conserva el scroll desde
  la primera pregunta hasta el botón final, sin centrar verticalmente un formulario desbordado.
- **Accesibilidad:** la ruta crítica se distingue por color **y** por grosor de trazo, más
  la etiqueta textual "crítica" en cada nodo. El color por sí solo no basta.
- **Gantt:** la tabla visual usa encabezados agrupados, barras con fecha de inicio/término,
  marcadores de hoy/deadline, sombreado de fines de semana y leyenda de ruta crítica/completitud.
  Tanto el nombre de la actividad como su barra son enlaces a la ficha lateral, conservando
  `view=gantt` y el código seleccionado. La columna de actividades usa un ancho fijo de 320 px;
  el ancho temporal se calcula por escala y el contenedor permite scroll horizontal, evitando
  encimar días y etiquetas cuando el horizonte es largo. En el Gantt, la ruta crítica pendiente
  se muestra en naranja y las actividades atrasadas en rojo; una actividad completada conserva
  prioridad visual verde.
- **Animación:** la pantalla 05 usa una ruta SVG base y una capa de guiones animados, además de
  puntos pulsantes escalonados. Ambos respetan `prefers-reduced-motion`.
- **Completitud:** cada nodo completado usa relleno `done-soft`, cada barra usa `done`, y ambos
  tienen check y texto además del color; el formulario de eliminación usa CSRF, método DELETE y
  confirmación con el nombre del proyecto.

---

## 9. Pruebas

La suite se ejecuta con `php artisan test --compact`.

| Archivo | Qué cubre |
|---|---|
| `Unit/CpmCalculatorTest.php` | Ambas pasadas, holguras, ruta crítica, layout, ramas paralelas, y los tres errores del grafo (ciclo, precedente inexistente, código repetido) |
| `Feature/AuthenticationTest.php` | Login correcto e incorrecto, registro, logout, rutas protegidas |
| `Feature/DashboardTest.php` | Aislamiento por usuario, cálculo del % de avance, estado vacío |
| `Feature/ProjectWizardTest.php` | Los dos pasos, validaciones, encolado con intento, regeneración desde fallo, regeneración activa y autorización |
| `Feature/ScheduleGenerationTest.php` | Integración completa con `Http::fake()`: malla guardada, dependencias, cobro único y atómico, API caída, plan circular, regeneración y jobs antiguos |
| `Feature/ProjectScreenTest.php` | Renderizado de la malla, selección de actividad, marcar hecha, recálculo del CPM al editar una duración |
| `Unit/ProjectGenerationStageTest.php` | Valores ordenados de las etapas, etapa terminal y casts/defaults de metadata |
| `Unit/ProjectStatusTest.php` | Estados conversacionales, detección de respuestas pendientes y terminalidad |
| `Unit/QueueConfigurationTest.php` | `retry_after` superior al timeout del job de generación |
| `Feature/ProjectClarificationModelTest.php` | Estados, casts, relación y filtro de preguntas por intento vigente |
| `Feature/ProjectClarificationFlowTest.php` | Análisis sin cobro, 0 o 1–3 preguntas, transición, intentos antiguos y respuestas en el prompt final |
| `Feature/ProjectClarificationAnswersTest.php` | Formulario, autorización, respuestas completas, selects e ids ajenos |
| `Feature/ProjectGenerationStatusTest.php` | Contrato JSON, no-store, fases, estados de input, estancamiento y autorización |
| `Unit/ClarificationPromptBuilderTest.php` | Distinción entre preguntas abiertas y cerradas |
| `Unit/GanttTimelineBuilderTest.php` | Escalas, horizonte, filas, precedencias y fines de semana |
| `Unit/TimelineContractTest.php` | Actividades de un día, cadenas, años bisiestos y deadlines |

**Cuidado con `Model::shouldBeStrict()`** (activo fuera de producción): detecta *lazy
loading* y atributos faltantes. Donde un modelo llega por *route model binding* y necesita
una relación, hay que pedirla explícitamente con `loadMissing()`.

---

Las pruebas de generación también cubren jobs de intentos antiguos, fallos obsoletos,
persistencia cancelada cuando cambia el intento, unicidad por proyecto y regeneración duplicada.

## 10. Puesta en marcha

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# Agregar GEMINI_API_KEY al .env

php artisan migrate --seed   # crea el proyecto demo con la malla de los mockups
composer run dev             # servidor + cola + Vite
```

Usuario de demostración: `demo@levelup.test` / `password`.

La cola tiene que estar corriendo (`php artisan queue:work`, incluido en `composer run
dev`) o la pantalla 05 se queda esperando para siempre.

---

## 11. Desviaciones respecto de la propuesta

Dos diferencias con lo declarado en `docs/plantilla-propuesta-laravel.docx`, ambas
pendientes de decisión del equipo:

1. **Laravel Breeze no está instalado.** La autenticación se escribió a mano con la
   fachada `Auth` (`LoginRequest::authenticate()`, con *rate limiting* por correo e IP), y
   las vistas siguen los mockups. Agregar Breeze significaría instalar una dependencia
   nueva y sobrescribir esas vistas. Funcionalmente ya está cubierto; si el ramo exige
   Breeze explícitamente, se instala y se migran las vistas.

2. **La base de datos local es SQLite, no MySQL.** Es lo que trae el esqueleto y permite
   correr los tests sin levantar un servidor. Cambiar a MySQL es solo editar el `.env`
   (`DB_CONNECTION=mysql` y sus credenciales); no hay nada en las migraciones ni en los
   modelos que dependa del motor.

---

## 12. Qué falta

- [ ] Llevar las vistas Blade a la fidelidad visual de los mockups (hoy son funcionales,
      con los tokens correctos, pero más sobrias).
- [x] Vista Gantt derivada del CPM, con escalas diaria/semanal/mensual, deadline y progreso.
- [ ] Vista Lista de la pantalla 06.
- [ ] Exportar y compartir el proyecto.
- [ ] Reinicio mensual automático de `ai_credits_used` (tarea programada).
- [ ] Recuperación de contraseña.
- [ ] Notificaciones externas; actualmente la app actualiza la pantalla mediante polling y no
      promete correo ni push.
- [ ] Distinguir en `GeminiClient` la cuota agotada (429) y la sobrecarga del modelo (503) de una
      caída de red, y respetar el `retryDelay` que manda la API en vez de reintentar a los 500 ms.
      Hoy las tres cosas llegan al usuario como «No pudimos contactar al servicio de IA».
      Ver [`docs/prompt.md`](prompt.md) §5.
- [ ] Elegir el modelo de producción con la cuota en mente: el free tier de `gemini-3.5-flash`
      permite 20 requests por día para toda la aplicación y cada proyecto gasta 2.
- [ ] Arreglar los cuatro tests que fallan desde antes del ajuste de prompts, la inestabilidad por
      orden de ejecución de `ScheduleGenerationTest` y el arranque de los tests de `tests/Unit`,
      que hoy no cargan la aplicación. Detalle en [`docs/prompt.md`](prompt.md) §5.
