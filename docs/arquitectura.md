# Arquitectura — Project LevelUp

> **Este documento es el registro vivo de la arquitectura.** Toda carpeta, archivo, ruta,
> tabla o decisión de diseño que se agregue al proyecto se documenta aquí, en el mismo
> commit que la introduce. Si algo existe en el código y no está en este archivo, el
> archivo está desactualizado y hay que corregirlo. Así seguirá siendo durante todo el
> desarrollo.

**Proyecto:** Project LevelUp — IIP323W, Unidad 3 (Laravel)
**Equipo:** Gabriel Marín (frontend) · Sebastián Ramírez (backend)
**Última actualización:** 3 de agosto de 2026

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

Las cinco decisiones que explican por qué el código está organizado así.

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

`GenerateProjectSchedule` llama a `consumeAiCredit()` recién cuando la generación resultó.

**Por qué:** si la API de Google se cae o devuelve basura, el error no es del usuario y no
tiene por qué costarle una de sus 20 consultas mensuales.

### 2.5 El tipo de proyecto viaja en sesión hasta el POST final

El paso 1 del asistente guarda el tipo en `session('project_wizard.type')`. El registro en
la tabla `projects` recién nace en el paso 2.

**Por qué:** si el usuario abandona el asistente, no quedan filas huérfanas en la base.

---

## 3. Estructura de carpetas

Solo se listan las carpetas y archivos propios del proyecto. Lo que no aparece aquí es
esqueleto estándar de Laravel sin modificar.

```
app/
├── Enums/
│   ├── ProjectStatus.php          Draft · Generating · Ready · Failed
│   └── ProjectType.php            6 tipos + su contexto de dominio para el prompt
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
│   └── GenerateProjectSchedule.php   Job en cola: IA → CPM → persistencia
├── Models/
│   ├── Activity.php                  Nodo de la malla
│   ├── Project.php                   Proyecto y sus métricas de avance
│   └── User.php                      + relación projects() y cuota de IA
├── Policies/
│   └── ProjectPolicy.php             Un proyecto solo lo ve y lo toca su dueño
├── Providers/
│   └── AppServiceProvider.php        Binding de GeminiClient + modo estricto de Eloquent
└── Services/
    ├── Ai/
    │   ├── GeminiClient.php          Cliente HTTP delgado de la API de Google
    │   ├── ProjectPlanGenerator.php  Orquesta prompt → validación → CPM → guardado
    │   └── PromptBuilder.php         Arma system prompt, user prompt y responseSchema
    └── Cpm/
        ├── CpmCalculator.php         Pasadas hacia adelante y atrás, holguras, layout
        └── ScheduledActivity.php     Resultado inmutable por actividad

database/
├── factories/
│   ├── ActivityFactory.php           + estados critical() y completed()
│   ├── ProjectFactory.php            + estados draft() generating() ready() failed()
│   └── UserFactory.php               + estado withoutAiCredits()
├── migrations/
│   ├── 2026_08_03_195333_create_projects_table.php
│   ├── 2026_08_03_195334_create_activities_table.php
│   ├── 2026_08_03_195335_create_activity_dependencies_table.php
│   └── 2026_08_03_195336_add_ai_credits_to_users_table.php
└── seeders/
    ├── DatabaseSeeder.php
    └── DemoProjectSeeder.php         Proyecto demo con la malla de los mockups

docs/
├── arquitectura.md                   Este archivo
├── mockups/
│   └── project-levelup-mockups.html  Las 6 pantallas + sistema de diseño
└── plantilla-propuesta-laravel.docx  Propuesta entregada al ramo

resources/
├── css/app.css                       Tokens de marca en @theme (Tailwind v4)
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
            └── stepper.blade.php           Indicador de paso 1/2/3

tests/
├── Feature/
│   ├── AuthenticationTest.php        Login, registro, logout, rutas protegidas
│   ├── DashboardTest.php             Aislamiento por usuario y cálculo de avance
│   ├── ExampleTest.php               Redirección de la raíz
│   ├── ProjectScreenTest.php         Humo sobre la malla + recálculo al editar
│   ├── ProjectWizardTest.php         Asistente, validación y autorización
│   └── ScheduleGenerationTest.php    Integración con Gemini vía Http::fake()
└── Unit/
    └── CpmCalculatorTest.php         12 casos del algoritmo CPM
```

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
| `generation_error` | text, nullable | Mensaje que se le muestra al usuario |
| `generated_at` | timestamp, nullable | |
| `total_duration_days` | smallint, nullable | Largo de la ruta crítica, cacheado |

Índices: `(user_id, status)` y `deadline`.

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
| `POST` | `/projects/{project}/regenerar` | `projects.regenerate` | 05 |
| `GET` | `/projects/{project}/malla` | `projects.show` | 06 |
| `DELETE` | `/projects/{project}` | `projects.destroy` | 06 |
| `PATCH` | `/activities/{activity}` | `activities.update` | 06 |
| `POST` | `/activities/{activity}/completar` | `activities.toggle` | 06 |

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

Tres clases con una responsabilidad cada una:

- **`PromptBuilder`** arma el *system prompt* (rol, reglas, contexto según
  `ProjectType::domainHint()`), el *user prompt* (descripción + fechas + equipo) y el
  `responseSchema`.
- **`GeminiClient`** solo habla HTTP: `POST {base_url}/models/{model}:generateContent`
  con la clave en el header `x-goog-api-key`, y devuelve el JSON decodificado. Se falsea
  en los tests con `Http::fake()`.
- **`ProjectPlanGenerator`** valida la forma de la respuesta, llama al `CpmCalculator` y
  guarda todo en una transacción.

Se usa **respuesta estructurada** (`responseMimeType: application/json` +
`responseSchema`) para no tener que parsear texto libre, con `temperature: 0.2` para que
la salida sea lo más estable posible.

Regenerar **reemplaza** la malla completa: borrar y volver a crear, no mezclar. Mezclar
dos grafos distintos daría dependencias inconsistentes.

### Configuración

En `config/services.php`, leyendo del `.env`:

```
GEMINI_API_KEY=          # obligatoria en producción
GEMINI_MODEL=gemini-2.5-flash
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
- **JavaScript, lo mínimo:** solo el *polling* de la pantalla 05. No hay framework de
  frontend.
- **Accesibilidad:** la ruta crítica se distingue por color **y** por grosor de trazo, más
  la etiqueta textual "crítica" en cada nodo. El color por sí solo no basta.

---

## 9. Pruebas

46 pruebas, 126 aserciones. `php artisan test --compact`.

| Archivo | Qué cubre |
|---|---|
| `Unit/CpmCalculatorTest.php` | Ambas pasadas, holguras, ruta crítica, layout, ramas paralelas, y los tres errores del grafo (ciclo, precedente inexistente, código repetido) |
| `Feature/AuthenticationTest.php` | Login correcto e incorrecto, registro, logout, rutas protegidas |
| `Feature/DashboardTest.php` | Aislamiento por usuario, cálculo del % de avance, estado vacío |
| `Feature/ProjectWizardTest.php` | Los dos pasos, validaciones, encolado del job, cuota agotada, proyecto ajeno |
| `Feature/ScheduleGenerationTest.php` | Integración completa con `Http::fake()`: malla guardada, dependencias, crédito descontado solo al resultar, API caída, plan circular, regeneración |
| `Feature/ProjectScreenTest.php` | Renderizado de la malla, selección de actividad, marcar hecha, recálculo del CPM al editar una duración |

**Cuidado con `Model::shouldBeStrict()`** (activo fuera de producción): detecta *lazy
loading* y atributos faltantes. Donde un modelo llega por *route model binding* y necesita
una relación, hay que pedirla explícitamente con `loadMissing()`.

---

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
- [ ] Vistas Gantt y Lista de la pantalla 06 (las pestañas están diseñadas, no
      implementadas).
- [ ] Exportar y compartir el proyecto.
- [ ] Reinicio mensual automático de `ai_credits_used` (tarea programada).
- [ ] Recuperación de contraseña.
