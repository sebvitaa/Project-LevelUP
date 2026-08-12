# Guía de estudio: Project LevelUp y Laravel

## 1. Propósito y alcance

Esta guía explica el repositorio **Project LevelUp** y lo conecta con las clases 9, 10 y 11 de la Unidad 3 de Laravel. Está pensada para personas que entienden variables, funciones, clases, HTTP y bases de datos, pero que todavía no dominan Laravel.

La revisión cubre:

- El código propio del proyecto y los archivos de configuración que determinan su comportamiento.
- Las pantallas Blade, los modelos, controladores, servicios, jobs, migraciones y pruebas.
- El recorrido completo de las clases 9, 10 y 11, incluidas todas sus presentaciones.
- Las carpetas generadas (`vendor/`, `node_modules/`, cachés y logs) como grupos; no se enumeran sus miles de archivos porque no son código mantenido por el equipo.
- El archivo `.env` solo por su función. No se reproducen sus valores porque puede contener secretos. La referencia segura es `.env.example`.

### Versión y diferencias respecto del material

Hay tres diferencias importantes que conviene tener presentes:

1. La clase 9 presenta Laravel 12, mientras que `composer.json` fija **Laravel 13.8 o superior dentro de la versión 13** y PHP `^8.3`. Algunas diapositivas posteriores ya hablan de Laravel 13.
2. El traductor de la clase 9 usa la API de **Groq**. Project LevelUp aplica el mismo patrón, pero usa la API de **Google Gemini** y solicita JSON estructurado.
3. La clase 11 explica que en Laravel 13 el controlador base puede venir sin `authorize()`. Este proyecto agrega el trait `AuthorizesRequests` en `app/Http/Controllers/Controller.php`, por lo que los controladores sí pueden usar `$this->authorize(...)`.

## 2. Qué hace Project LevelUp

Project LevelUp transforma una descripción escrita en lenguaje natural en un cronograma de proyecto. La IA propone actividades, duraciones y precedencias; PHP valida esa respuesta, calcula la ruta crítica mediante CPM y guarda el resultado.

La decisión central es separar dos trabajos:

- **Gemini interpreta** el texto y propone la estructura del proyecto.
- **El servidor calcula** ES, EF, LS, LF, holguras, ruta crítica y posiciones del grafo con un algoritmo determinista.

Esto evita confiar en un modelo de lenguaje para cálculos que deben ser exactos, repetibles y fáciles de probar.

### Flujo funcional

```text
Registro o login
    ↓
Dashboard de proyectos
    ↓
Elegir tipo de proyecto
    ↓
Escribir nombre, descripción, fechas y tamaño del equipo
    ↓
Job 1: Gemini decide si necesita aclaraciones
    ├─ Sí → guarda preguntas → usuario responde
    └─ No ─────────────────────────────────────┐
                                               ↓
Job 2: Gemini propone actividades y dependencias
    ↓
Validación de la respuesta
    ↓
Cálculo CPM en PHP
    ↓
Transacción: actividades + dependencias + resultados
    ↓
Vista de malla o carta Gantt
```

Mientras los jobs trabajan, el navegador consulta cada dos segundos un endpoint de estado. Por eso el usuario puede ver progreso sin mantener abierta una petición HTTP larga.

## 3. Conceptos importantes antes de leer el proyecto

### 3.1 Framework y convención

Laravel es un framework de PHP: no reemplaza el lenguaje, sino que organiza la aplicación y entrega herramientas ya resueltas para rutas, vistas, base de datos, autenticación, validación, colas, configuración y pruebas.

La convención permite deducir ubicaciones:

- `App\Models\Project` vive en `app/Models/Project.php`.
- `view('projects.show')` busca `resources/views/projects/show.blade.php`.
- Una ruta con `{project}` y un parámetro `Project $project` activa el *route model binding*: Laravel busca el registro automáticamente o responde 404.

### 3.2 Ciclo petición-respuesta y MVC

Una visita normalmente recorre:

```text
Navegador → routes/web.php → controlador → modelo/servicio → vista Blade → HTML
```

- **Ruta:** decide qué código atiende una URL y con qué verbo HTTP.
- **Controlador:** coordina la operación; no debería contener algoritmos grandes.
- **Modelo Eloquent:** representa una tabla y sus relaciones.
- **Vista Blade:** produce el HTML que recibe el navegador.
- **Servicio:** encapsula lógica de negocio reutilizable o integración externa.

Project LevelUp mantiene esta separación con claridad. El CPM no vive en un controlador y el cliente Gemini no sabe guardar proyectos.

### 3.3 Verbos HTTP y rutas con nombre

- `GET` consulta o muestra una pantalla.
- `POST` crea o ejecuta una acción.
- `PATCH` modifica parcialmente.
- `DELETE` elimina.

Las rutas tienen nombres como `projects.show`. Las vistas usan `route('projects.show', $project)` en lugar de escribir `/projects/15/malla` a mano. Si cambia la URL, los enlaces siguen funcionando mientras el nombre se mantenga.

### 3.4 Blade, layouts, componentes y parciales

Blade es HTML con directivas de servidor:

- `{{ $valor }}` imprime escapando HTML para reducir XSS.
- `@if`, `@foreach`, `@error` y `@csrf` agregan lógica de presentación.
- `<x-layouts.app>` usa un componente de layout.
- `@include(...)` reutiliza un parcial.
- Los slots como `<x-slot:topbar>` rellenan una zona del componente.

En este proyecto los layouts son componentes Blade, no el estilo `@extends/@yield` del ejemplo inicial. Ambos resuelven el mismo problema: no repetir el marco de la página.

### 3.5 Eloquent: modelo, fila y relaciones

Un objeto Eloquent representa una fila:

- `User` ↔ tabla `users`.
- `Project` ↔ tabla `projects`.
- `Activity` ↔ tabla `activities`.
- `ProjectClarification` ↔ tabla `project_clarifications`.

Relaciones principales:

```text
User 1 ─── * Project 1 ─── * Activity
                   │
                   └────── * ProjectClarification

Activity * ─── * Activity
mediante activity_dependencies
```

La última relación representa el grafo: una actividad puede tener varios precedentes y ser precedente de varias sucesoras.

### 3.6 `$fillable`, casts y asignación masiva

`$fillable` define qué campos se pueden llenar en grupo mediante `create()` o `update()`. No es validación; es una barrera contra campos inesperados enviados desde el navegador.

Los *casts* convierten valores de base de datos a tipos útiles. Por ejemplo:

- `status` se convierte en `ProjectStatus`.
- `starts_on` se convierte en una fecha Carbon.
- `is_critical` se convierte en booleano.
- `password` se hashea al asignarse en `User`.

### 3.7 Migraciones, factories y seeders

- **Migración:** versión del esquema de base de datos escrita en PHP.
- **Factory:** receta para fabricar registros de prueba.
- **Seeder:** carga un conjunto conocido de datos de demostración.

El desarrollo local usa SQLite en `database/database.sqlite`. En pruebas, `phpunit.xml` configura SQLite en memoria.

### 3.8 Form Requests y validación

Las clases de `app/Http/Requests/` concentran autorización, reglas y mensajes. Esto evita que cada controlador repita validaciones y permite detener la petición antes de ejecutar la operación.

La validación del navegador (`required`, `type="email"`) mejora la experiencia, pero la seguridad depende de la validación del servidor porque el HTML puede alterarse.

### 3.9 Autenticación y autorización

- **Autenticación:** determina quién inició sesión.
- **Autorización:** determina qué puede hacer esa persona.

El middleware `auth` protege el grupo principal de rutas. `ProjectPolicy` agrega la regla de propiedad: un proyecto solo puede ser visto, modificado o eliminado por su dueño. Las acciones sobre actividades autorizan contra el proyecto al que pertenecen.

Ocultar un botón no es seguridad. La comprobación debe estar en el servidor, antes de leer o cambiar datos sensibles.

### 3.10 Sesión, CSRF y contraseñas

- Después del login se regenera el identificador de sesión para evitar fijación de sesión.
- Al salir se cierra el usuario, se invalida la sesión y se renueva el token CSRF.
- Todo formulario que cambia estado incluye `@csrf`.
- `User` usa el cast `hashed`; la contraseña en texto plano no se guarda.
- `LoginRequest` limita intentos por combinación de correo e IP.

### 3.11 Inyección de dependencias, contenedor y providers

Laravel crea automáticamente objetos pedidos en las firmas. Por ejemplo, inyecta `CpmCalculator` o `GanttTimelineBuilder` en un método de controlador.

`AppServiceProvider` registra `GeminiClient` como *singleton* porque necesita parámetros obtenidos desde `config/services.php`. También comparte los proyectos recientes con el sidebar.

### 3.12 Fachadas

Clases como `Auth`, `DB`, `Http`, `Route` y `Vite` son fachadas: ofrecen una interfaz estática corta a servicios administrados por Laravel. No significan que toda la aplicación sea realmente estática.

### 3.13 Colas y jobs

Llamar a Gemini puede tardar. Los jobs implementan `ShouldQueue`, se guardan en la tabla `jobs` y un worker los procesa fuera de la petición web.

El proyecto usa:

- Un job para analizar si el brief necesita aclaraciones.
- Otro job para generar, validar, calcular y persistir la malla final.

Ambos son únicos por proyecto y verifican `generation_attempt`. Esto evita que un job antiguo sobrescriba el resultado de un reintento nuevo.

### 3.14 Transacciones y bloqueos

`DB::transaction()` hace que varias escrituras se confirmen juntas o se deshagan juntas. `lockForUpdate()` evita que dos procesos cobren el mismo crédito o persistan dos versiones al mismo tiempo.

Este detalle es importante porque la cola y las respuestas del usuario pueden ejecutarse de forma concurrente.

### 3.15 Máquina de estados

`ProjectStatus` representa el ciclo de vida:

```text
Draft
  → Clarifying
      → AwaitingInput → Generating
      └──────────────→ Generating
  → Ready
  ↘ Failed → reintento
```

`ProjectGenerationStage` agrega hitos más finos para la barra de progreso: análisis, solicitud, validación, CPM, persistencia y finalización.

### 3.16 Integración con Gemini

El patrón es equivalente al traductor con Groq de la clase 9:

1. La clave vive en `.env`.
2. `config/services.php` la expone mediante `config('services.gemini.key')`.
3. Un cliente usa `Http` para hacer la llamada.
4. Se separa instrucción de sistema y contenido del usuario.
5. Se revisan errores y se transforma la respuesta.

Aquí además se envía un `responseSchema` para obligar a Gemini a devolver JSON con una forma conocida. Aun así, el servidor vuelve a validar cada campo; nunca confía ciegamente en la IA.

### 3.17 CPM y Gantt

El método de la ruta crítica calcula:

- **ES:** inicio temprano.
- **EF:** fin temprano.
- **LS:** inicio tardío.
- **LF:** fin tardío.
- **Holgura:** `LS - ES`.
- **Crítica:** holgura igual a cero.

`CpmCalculator` realiza una pasada hacia adelante y otra hacia atrás sobre un grafo ordenado topológicamente. También detecta ciclos, códigos repetidos y precedentes inexistentes.

La carta Gantt no recalcula el plan: convierte offsets CPM a fechas de calendario y elige escala diaria, semanal o mensual según el horizonte.

### 3.18 Frontend progresivo

La mayor parte de la interfaz funciona con Blade y formularios normales. JavaScript se usa solo donde aporta:

- Polling y actualización visual del progreso.
- Centrado de la actividad seleccionada en el grafo.

Vite compila `resources/css/app.css` y `resources/js/app.js`. Tailwind v4 genera las clases utilizadas en las vistas.

### 3.19 Pruebas

- Las pruebas **unitarias** revisan algoritmos y objetos aislados.
- Las pruebas **feature** levantan Laravel, usan la base de datos y recorren rutas completas.
- `Http::fake()` sustituye Gemini para que las pruebas no consuman red ni cuota.
- `RefreshDatabase` reinicia la base entre pruebas feature.

## 4. Carpetas principales y su función

| Carpeta | Función en el proyecto |
| --- | --- |
| `app/` | Código PHP propio: modelos, controladores, validación, políticas, jobs, servicios y enums. |
| `bootstrap/` | Arranque de Laravel, rutas registradas, middleware, excepciones y providers. |
| `config/` | Configuración derivada del entorno: base, sesiones, colas, Gemini, logs y correo. |
| `database/` | SQLite local, migraciones, factories y datos de demostración. |
| `docs/` | Arquitectura, experimentos de prompts, mockups y propuesta del proyecto. |
| `public/` | Raíz pública del servidor; contiene el front controller y assets compilados. |
| `resources/` | Código de presentación: Blade, CSS Tailwind y JavaScript fuente. |
| `routes/` | Mapa de URLs web y comandos de consola. |
| `storage/` | Logs, sesiones, caché, vistas compiladas y archivos generados en ejecución. |
| `tests/` | Pruebas unitarias y feature con Pest. |
| `vendor/` | Dependencias PHP instaladas por Composer; no se editan manualmente. |
| `node_modules/` | Dependencias del frontend instaladas por npm; no se editan manualmente. |
| `.git/` | Historial y metadatos de Git; no forma parte de la lógica de Laravel. |
| `.claude/` | Guías locales para agentes de desarrollo; no participan al ejecutar la app. |

## 5. Inventario detallado, archivo por archivo

### 5.1 Raíz del repositorio

| Archivo | Función |
| --- | --- |
| `README.md` | Presentación del producto, flujo de pantallas, arquitectura resumida, instalación, pruebas y estado actual. |
| `CLAUDE.md` | Reglas de trabajo para asistentes de código: versiones, convenciones, pruebas y obligación de mantener `docs/arquitectura.md`. No lo lee la aplicación. |
| `plandetrabajo.md` | Plan interno, no versionado, con fases de implementación, riesgos y criterios de aceptación. |
| `artisan` | Entrada de línea de comandos de Laravel. Carga Composer, arranca la aplicación y ejecuta comandos. |
| `composer.json` | Dependencias PHP, autoload PSR-4 y scripts `setup`, `dev` y `test`. |
| `composer.lock` | Fija las versiones exactas de dependencias PHP para instalaciones reproducibles. |
| `package.json` | Dependencias y scripts del frontend: Vite, Tailwind y `concurrently`. |
| `package-lock.json` | Fija las versiones exactas de dependencias npm. |
| `vite.config.js` | Conecta Vite con Laravel, Tailwind e Instrument Sans; compila CSS/JS y activa refresco. |
| `phpunit.xml` | Define suites Unit/Feature y el entorno de pruebas con SQLite en memoria, cola síncrona y sesiones en arreglo. |
| `.env` | Configuración local real y secretos. Está ignorado por Git y no debe compartirse. |
| `.env.example` | Plantilla segura: SQLite, cola y sesiones en base, `GEMINI_API_KEY`, modelo y umbral de estancamiento. |
| `.gitignore` | Impide versionar secretos, dependencias, builds, logs y archivos locales. |
| `.gitattributes` | Normaliza finales de línea y mejora los diffs según tipo de archivo. |
| `.editorconfig` | Convenciones de codificación, sangría y saltos de línea. |
| `.npmrc` | Desactiva scripts automáticos de paquetes npm y mantiene auditoría. |
| `.mcp.json` | Configura el servidor MCP local de Laravel Boost. No afecta usuarios de la aplicación. |
| `boost.json` | Configura Laravel Boost y las guías de Laravel, Pest y Tailwind usadas durante desarrollo. |

Nota sobre `composer run dev`: el `composer.json` actual inicia servidor PHP, worker de cola y Vite. Aunque comentarios y documentación hablan también de logs en vivo, no hay un comando de logs incluido en el arreglo actual.

### 5.2 `.claude/`

Estos archivos son documentación para herramientas de desarrollo, no código ejecutado por Laravel.

| Archivo | Función |
| --- | --- |
| `.claude/skills/laravel-best-practices/SKILL.md` | Índice de buenas prácticas de Laravel. |
| `rules/advanced-queries.md` | Consultas Eloquent avanzadas. |
| `rules/architecture.md` | Separación de responsabilidades y estructura. |
| `rules/blade-views.md` | Convenciones para vistas Blade. |
| `rules/caching.md` | Uso de caché. |
| `rules/collections.md` | Trabajo con Collections. |
| `rules/config.md` | Configuración y variables de entorno. |
| `rules/db-performance.md` | Rendimiento de consultas y base de datos. |
| `rules/eloquent.md` | Modelos, relaciones y Eloquent. |
| `rules/error-handling.md` | Manejo de errores y excepciones. |
| `rules/events-notifications.md` | Eventos y notificaciones. |
| `rules/http-client.md` | Cliente HTTP de Laravel. |
| `rules/mail.md` | Envío de correo. |
| `rules/migrations.md` | Diseño de migraciones. |
| `rules/queue-jobs.md` | Jobs, colas, reintentos e idempotencia. |
| `rules/routing.md` | Rutas y route model binding. |
| `rules/scheduling.md` | Tareas programadas. |
| `rules/security.md` | Reglas de seguridad. |
| `rules/style.md` | Estilo de PHP y Laravel. |
| `rules/testing.md` | Estrategia de pruebas. |
| `rules/validation.md` | Validación de requests. |
| `.claude/skills/pest-testing/SKILL.md` | Guía local para pruebas con Pest. |
| `.claude/skills/tailwindcss-development/SKILL.md` | Guía local para Tailwind CSS. |

### 5.3 `app/Enums/`

| Archivo | Función |
| --- | --- |
| `DashboardFilter.php` | Define filtros `todos`, `riesgo` y `completados`, sus etiquetas y cómo filtrar una colección. |
| `DashboardSort.php` | Ordena proyectos por fecha límite, avance o nombre. |
| `ProjectGenerationStage.php` | Hitos observables del procesamiento y sus mensajes. Solo `Complete` es terminal. |
| `ProjectStatus.php` | Estados de negocio del proyecto y ayudas como `needsUserInput()` e `isTerminal()`. |
| `ProjectType.php` | Seis dominios, etiquetas, descripciones, rango permitido de actividades y contexto enviado a Gemini. |

### 5.4 `app/Exceptions/`

| Archivo | Función |
| --- | --- |
| `PlanGenerationException.php` | Convierte fallos de API, JSON, aclaraciones, grafo o créditos en mensajes comprensibles para el usuario. |

### 5.5 `app/Http/Controllers/`

| Archivo | Función |
| --- | --- |
| `Controller.php` | Clase base. Agrega `AuthorizesRequests` para habilitar `$this->authorize()`. |
| `Auth/AuthenticatedSessionController.php` | Muestra login, autentica mediante `LoginRequest`, regenera sesión y realiza logout seguro. |
| `Auth/RegisteredUserController.php` | Muestra registro, crea el usuario, dispara `Registered`, inicia sesión y lo lleva al asistente. |
| `DashboardController.php` | Carga solo proyectos del usuario, busca por proyecto/actividad, filtra, ordena y calcula KPIs, tendencia, tareas críticas y próxima entrega. |
| `ProjectWizardController.php` | Maneja los pasos de tipo y descripción. Guarda el tipo temporalmente en sesión para no crear proyectos abandonados. |
| `ProjectController.php` | Crea proyectos y despacha aclaraciones; muestra malla/Gantt; regenera intentos fallidos; elimina proyectos. |
| `ProjectClarificationController.php` | Valida y guarda exactamente las respuestas pendientes bajo bloqueo; cambia el estado y despacha el plan final. |
| `ProjectGenerationController.php` | Renderiza la pantalla de espera y entrega el JSON de progreso, terminalidad, estancamiento y redirección. |
| `ActivityController.php` | Actualiza actividades y recalcula todo el CPM dentro de una transacción; también marca o desmarca completadas. |

### 5.6 `app/Http/Requests/`

| Archivo | Función |
| --- | --- |
| `Auth/LoginRequest.php` | Valida credenciales, llama `Auth::attempt`, conserva `remember` y limita a cinco intentos por correo/IP. |
| `Auth/RegisterRequest.php` | Valida nombre, correo único, contraseña y confirmación. |
| `StoreProjectRequest.php` | Autoriza creación según créditos y valida tipo, prompt, fechas y equipo. |
| `StoreProjectClarificationAnswersRequest.php` | Autoriza al dueño, genera reglas dinámicas por pregunta y exige exactamente las respuestas vigentes. |
| `UpdateActivityRequest.php` | Autoriza contra el proyecto y valida nombre, descripción y duración. |

### 5.7 `app/Jobs/`

| Archivo | Función |
| --- | --- |
| `GenerateProjectClarifications.php` | Job único que analiza el brief. Persiste preguntas o despacha el job final. Ignora proyectos o intentos obsoletos y marca fallos. |
| `GenerateProjectSchedule.php` | Job único que verifica crédito e intento, ejecuta `ProjectPlanGenerator` y registra errores sin cobrar por fallos. |

### 5.8 `app/Models/`

| Archivo | Función |
| --- | --- |
| `User.php` | Usuario autenticable, contraseña hasheada, proyectos y cuota de IA. |
| `Project.php` | Proyecto y sus casts, actividades, críticas, aclaraciones, porcentajes, fechas, riesgo y helpers de intento. |
| `Activity.php` | Actividad CPM; relaciones con proyecto, precedentes y sucesoras; estado completado/atrasado y fechas derivadas. |
| `ProjectClarification.php` | Pregunta de aclaración con opciones JSON, respuesta, fecha y scope `pending`. |

### 5.9 `app/Policies/`

| Archivo | Función |
| --- | --- |
| `ProjectPolicy.php` | Permite listar; restringe ver, actualizar y borrar al dueño; permite crear solo con crédito disponible. |

No existe `ActivityPolicy`: una actividad hereda la autorización del proyecto que la contiene.

### 5.10 `app/Providers/`

| Archivo | Función |
| --- | --- |
| `AppServiceProvider.php` | Construye el singleton `GeminiClient`, activa Eloquent estricto fuera de producción, configura prefetch de Vite y comparte proyectos recientes con el sidebar. |

### 5.11 `app/Services/Ai/`

| Archivo | Función |
| --- | --- |
| `GeminiClient.php` | Cliente HTTP delgado: endpoint, clave, timeout, reintentos, schema JSON y traducción de errores. |
| `ClarificationPromptBuilder.php` | Construye instrucciones, contexto y schema para decidir si hacen falta 1–3 preguntas. |
| `ProjectClarificationGenerator.php` | Valida tipos, claves, longitudes y opciones; bajo transacción persiste preguntas o pasa a generación. |
| `PromptBuilder.php` | Construye prompt de planificación con dominio, rango, paralelismo, duraciones, deadline, equipo y aclaraciones respondidas. |
| `ProjectPlanGenerator.php` | Orquesta Gemini → validación → CPM → transacción. Reemplaza la malla, crea dependencias y cobra el crédito una sola vez al terminar. |

### 5.12 `app/Services/Cpm/` y `app/Services/Gantt/`

| Archivo | Función |
| --- | --- |
| `Cpm/CpmCalculator.php` | Construye y valida el grafo, ordena con Kahn, hace pasadas CPM, calcula holgura/críticos y asigna filas/columnas. No usa Eloquent. |
| `Cpm/ScheduledActivity.php` | Objeto inmutable con el resultado por actividad y conversión a columnas persistibles. |
| `Gantt/GanttTimelineBuilder.php` | Convierte offsets a fechas, filas y marcadores; extiende hasta el deadline, marca fines de semana y elige escala. |

### 5.13 `bootstrap/`

| Archivo | Función |
| --- | --- |
| `bootstrap/app.php` | Crea la aplicación, registra rutas web/consola, endpoint `/up` y respuesta JSON para `/api/*`. |
| `bootstrap/providers.php` | Lista `AppServiceProvider` para su carga. |
| `bootstrap/cache/.gitignore` | Conserva la carpeta sin versionar los archivos de caché generados. |

### 5.14 `config/`

| Archivo | Función |
| --- | --- |
| `app.php` | Nombre, entorno, debug, URL, idioma, cifrado y mantenimiento. |
| `auth.php` | Guard web, proveedor Eloquent de usuarios y restablecimiento de contraseña. |
| `cache.php` | Store de caché; el `.env.example` usa base de datos. |
| `database.php` | Conexiones SQLite, MySQL y otras; selecciona según `DB_CONNECTION`. |
| `filesystems.php` | Discos local, público y S3. |
| `levelup.php` | Umbral `GENERATION_STALLED_AFTER` usado por la pantalla de progreso. |
| `logging.php` | Canales de log y nivel. |
| `mail.php` | Transportes de correo; localmente se escribe al log. |
| `queue.php` | Conexiones de cola, tabla `jobs`, `retry_after`, fallidos y batching. |
| `services.php` | Credenciales de terceros; agrega Gemini con clave, modelo, URL base y timeout. |
| `session.php` | Driver, duración, cookie, seguridad y dominio de sesión. |

Regla de estudio: `env()` debe quedar dentro de `config/`; el resto del código usa `config(...)` para ser compatible con `config:cache`.

### 5.15 `database/`

| Archivo/carpeta | Función |
| --- | --- |
| `database.sqlite` | Base SQLite local real. Es un archivo binario generado y no se versiona. |
| `.gitignore` | Conserva la carpeta y excluye la base local. |

#### Migraciones

| Archivo | Función |
| --- | --- |
| `0001_01_01_000000_create_users_table.php` | Crea usuarios, tokens de recuperación y sesiones. |
| `0001_01_01_000001_create_cache_table.php` | Crea caché y locks de caché. |
| `0001_01_01_000002_create_jobs_table.php` | Crea jobs, batches y jobs fallidos. |
| `2026_08_03_195333_create_projects_table.php` | Crea proyectos, FK a usuarios, prompt, fechas, estado, error y duración total. |
| `2026_08_03_195334_create_activities_table.php` | Crea actividades, resultados CPM, ubicación visual y fecha de completado. |
| `2026_08_03_195335_create_activity_dependencies_table.php` | Tabla pivote de aristas actividad–precedente, sin duplicados. |
| `2026_08_03_195336_add_ai_credits_to_users_table.php` | Agrega límite, uso y fecha de reinicio de créditos. |
| `2026_08_11_000000_add_generation_metadata_to_projects_table.php` | Agrega etapa, intentos, intento cobrado y marcas de progreso. |
| `2026_08_12_000000_create_project_clarifications_table.php` | Guarda ronda, intento, pregunta, tipo, opciones, respuesta y fecha. |

Las FK principales usan `cascadeOnDelete()`: borrar un usuario borra proyectos y borrar un proyecto borra actividades/aclaraciones; las dependencias desaparecen al borrar sus actividades.

#### Factories

| Archivo | Función |
| --- | --- |
| `ActivityFactory.php` | Fabrica actividades y ofrece estados `critical()` y `completed()`. |
| `ProjectFactory.php` | Fabrica proyectos y estados `draft`, `clarifying`, `awaitingInput`, `generating`, `ready` y `failed`. |
| `ProjectClarificationFactory.php` | Fabrica preguntas pendientes, respondidas y select. |
| `UserFactory.php` | Fabrica usuarios con contraseña conocida y estado sin créditos. |

#### Seeders

| Archivo | Función |
| --- | --- |
| `DatabaseSeeder.php` | Punto de entrada; carga `DashboardDemoSeeder`. |
| `DemoProjectSeeder.php` | Crea usuario demo y malla A–H de 39 días, calcula CPM y dependencias. |
| `DashboardDemoSeeder.php` | Amplía el demo con cartera, avances, riesgos y proyectos completados. |

### 5.16 `docs/`

| Archivo | Función |
| --- | --- |
| `arquitectura.md` | Documento vivo: decisiones, estructura, datos, rutas, algoritmo, Gemini, frontend, pruebas y pendientes. |
| `prompt.md` | Registro de pruebas A/B de prompts, métricas, prompt injection, paralelismo, plazos, cuotas y fallos conocidos. |
| `mockups/project-levelup-mockups.html` | Prototipo visual de seis pantallas y sistema de diseño. No es la aplicación en producción. |
| `plantilla-propuesta-laravel.docx` | Documento de propuesta entregado o usado como plantilla académica. |

### 5.17 `public/`

| Archivo/carpeta | Función |
| --- | --- |
| `index.php` | Front controller: recibe todas las peticiones públicas, carga Composer y arranca Laravel. |
| `.htaccess` | Reglas Apache para headers, barras finales y redirección de URLs a `index.php`. |
| `robots.txt` | No bloquea rastreo por defecto. |
| `favicon.ico` | Icono del sitio; actualmente es un archivo vacío. |
| `build/` | CSS/JS compilados por Vite; es generado y se reconstruye con npm. |

El servidor web debe apuntar a `public/`, nunca a la raíz del repositorio, para no exponer `.env`, código o configuración.

### 5.18 `resources/css/` y `resources/js/`

| Archivo | Función |
| --- | --- |
| `css/app.css` | Importa Tailwind, define tipografías, paleta, utilidades, trama y animaciones con soporte `prefers-reduced-motion`. |
| `js/app.js` | Entrada de Vite que importa los dos módulos JavaScript. |
| `js/generation-watcher.js` | Polling cada 2 s, timeout, backoff, estados accesibles, reload para preguntas y redirección al terminar. |
| `js/cpm-graph.js` | Centra suavemente el nodo seleccionado dentro del lienzo, respetando reducción de movimiento. |

### 5.19 `resources/views/components/` y layouts

| Archivo | Función |
| --- | --- |
| `components/layouts/app.blade.php` | Layout autenticado: sidebar, topbar opcional, mensajes de sesión y slot principal. |
| `components/layouts/guest.blade.php` | Layout de login/registro con panel de marca y formulario centrado. |
| `components/avatar.blade.php` | Calcula iniciales y color reproducible del avatar. |
| `components/logo.blade.php` | SVG de puntos que representa ascenso y nodos CPM. |
| `components/nav-icon.blade.php` | Componente SVG con variantes de iconos del menú. |
| `layouts/sidebar.blade.php` | Menú, seis proyectos recientes, estado visual, consumo de créditos y logout. |

### 5.20 `resources/views/auth/`

| Archivo | Función |
| --- | --- |
| `login.blade.php` | Formulario POST con CSRF, correo, contraseña, recordar y errores. |
| `register.blade.php` | Formulario POST con nombre, correo, contraseña y confirmación. |

### 5.21 `resources/views/dashboard/`

| Archivo | Función |
| --- | --- |
| `index.blade.php` | Pantalla principal: buscador, filtros, orden, KPIs, tarjetas y estado vacío. |
| `partials/filter-tabs.blade.php` | Links de filtro conservando búsqueda y orden. |
| `partials/kpi-row.blade.php` | Cuatro tarjetas: avance, completadas, críticas y próxima entrega. |
| `partials/project-card.blade.php` | Tarjeta con estado, progreso, deadline, duración y ruta correcta según estado. |
| `partials/sort-menu.blade.php` | Menú de orden basado en `DashboardSort`. |
| `partials/sparkline.blade.php` | SVG de tendencia de avance generado en Blade. |

### 5.22 `resources/views/projects/`

| Archivo | Función |
| --- | --- |
| `create-type.blade.php` | Paso 1: radio cards para elegir `ProjectType`. |
| `create-prompt.blade.php` | Paso 2: nombre, prompt, inicio, deadline, equipo y contador de créditos. |
| `generating.blade.php` | Paso 3: progreso, estados, error/reintento o formulario de aclaraciones. Expone datos que consume el watcher. |
| `show.blade.php` | Pantalla final: resumen, tabs Malla/Gantt, eliminación y panel de actividad. |
| `partials/stepper.blade.php` | Indicador Tipo → Descripción → Malla. |
| `partials/clarifications-form.blade.php` | Renderiza preguntas abiertas o select y envía `answers[id]`. |
| `partials/cpm-graph.blade.php` | Dibuja aristas SVG y nodos posicionados con `grid_column/grid_row`; diferencia críticos, atrasados y completados. |
| `partials/gantt-chart.blade.php` | Dibuja encabezado temporal, fines de semana, hoy, deadline y barras por actividad. |
| `partials/activity-detail.blade.php` | Muestra descripción, fechas, ES/EF/LS/LF, holgura, relaciones y botón completar/pendiente. |

Observación: existe el endpoint PATCH, el controlador y las pruebas para editar nombre, descripción y duración, pero el parcial actual `activity-detail.blade.php` no contiene un formulario de edición. La actualización puede ejecutarse por HTTP, pero no está expuesta en la interfaz revisada.

### 5.23 `routes/`

| Archivo | Función |
| --- | --- |
| `web.php` | Rutas públicas, auth y aplicación. Agrupa por `guest` y `auth`, usa nombres y route model binding. |
| `console.php` | Define el comando de ejemplo `inspire`. |

Rutas funcionales propias:

| Método | URL | Nombre | Uso |
| --- | --- | --- | --- |
| GET | `/` | `home` | Redirige al dashboard. |
| GET/POST | `/login` | `login` / sin nombre | Mostrar y procesar login. |
| GET/POST | `/register` | `register` / sin nombre | Mostrar y procesar registro. |
| POST | `/logout` | `logout` | Cerrar sesión. |
| GET | `/dashboard` | `dashboard` | Cartera del usuario. |
| GET/POST | `/projects/new/tipo` | `projects.create.type/store.type` | Paso de tipo. |
| GET | `/projects/new/descripcion` | `projects.create.prompt` | Paso de descripción. |
| POST | `/projects` | `projects.store` | Crear y encolar análisis. |
| GET | `/projects/{project}/generando` | `projects.generating` | Pantalla de espera/preguntas. |
| GET | `/projects/{project}/estado` | `projects.status` | JSON consultado por polling. |
| POST | `/projects/{project}/clarifications` | `projects.clarifications.store` | Guardar respuestas. |
| POST | `/projects/{project}/regenerar` | `projects.regenerate` | Reintentar un fallo. |
| GET | `/projects/{project}/malla` | `projects.show` | Malla o Gantt. |
| DELETE | `/projects/{project}` | `projects.destroy` | Eliminar proyecto. |
| PATCH | `/activities/{activity}` | `activities.update` | Editar y recalcular CPM. |
| POST | `/activities/{activity}/completar` | `activities.toggle` | Alternar completado. |

Laravel también registra `/up` como comprobación de salud.

### 5.24 `storage/`

| Carpeta | Función |
| --- | --- |
| `storage/app/private/` | Archivos privados de aplicación. |
| `storage/app/public/` | Archivos publicables mediante el enlace de storage. |
| `storage/framework/cache/` | Caché del framework. |
| `storage/framework/sessions/` | Sesiones si se usa driver de archivos; este proyecto configura base de datos. |
| `storage/framework/testing/` | Archivos temporales de Pest/Laravel. |
| `storage/framework/views/` | PHP generado al compilar Blade; no se estudia ni edita. |
| `storage/logs/laravel.log` | Logs del backend. Puede contener detalles sensibles y no se versiona. |
| `storage/logs/browser.log` | Logs capturados del navegador durante desarrollo. |

Los `.gitignore` interiores mantienen las carpetas vacías en Git sin subir sus contenidos generados.

### 5.25 `tests/`

| Archivo | Función |
| --- | --- |
| `TestCase.php` | Base de pruebas Laravel. |
| `Pest.php` | Aplica `TestCase` y `RefreshDatabase` a Feature; declara helpers de Pest. |
| `Feature/AuthenticationTest.php` | Login, rechazo, registro, logout y protección de rutas. |
| `Feature/DashboardTest.php` | Aislamiento, avance, vacío, filtros, búsqueda y orden. |
| `Feature/ExampleTest.php` | Redirección de `/` al dashboard. |
| `Feature/ProjectWizardTest.php` | Dos pasos, validaciones, créditos, encolado, regeneración y autorización. |
| `Feature/ProjectClarificationModelTest.php` | Persistencia, casts, relación y pendientes del intento actual. |
| `Feature/ProjectClarificationFlowTest.php` | Generación de preguntas, paso directo, límites, staleness y respuestas en prompt final. |
| `Feature/ProjectClarificationAnswersTest.php` | Formulario, dueño, respuestas completas, selects e ids válidos. |
| `Feature/ProjectGenerationStatusTest.php` | Contrato JSON, caché, estancamiento, watcher y autorización. |
| `Feature/ScheduleGenerationTest.php` | Integración falsa con Gemini, CPM, dependencias, créditos, errores y concurrencia. |
| `Feature/ProjectScreenTest.php` | Malla/Gantt, selección, completado, borrado, edición y recálculo. |
| `Unit/CpmCalculatorTest.php` | Pasadas, holgura, críticos, layout, ramas y errores del grafo. |
| `Unit/GanttTimelineBuilderTest.php` | Filas, escalas, deadline y fines de semana. |
| `Unit/TimelineContractTest.php` | Intervalos inclusivos, cadenas, año bisiesto y atraso. |
| `Unit/ProjectGenerationStageTest.php` | Valores, terminalidad y casts de etapa. |
| `Unit/ProjectStatusTest.php` | Estado que espera input y terminalidad. |
| `Unit/QueueConfigurationTest.php` | Garantiza `retry_after` mayor al timeout del job. |
| `Unit/ClarificationPromptBuilderTest.php` | Reglas text/select escritas en el prompt. |
| `Unit/ExampleTest.php` | Prueba mínima de instalación de Pest. |

`docs/prompt.md` documenta fallos conocidos previos: algunas pruebas del dashboard y estado, inestabilidad por orden en `ScheduleGenerationTest` y pruebas Unit que necesitan arrancar la aplicación. Por eso una suite roja debe interpretarse revisando ese documento, no suponiendo de inmediato que el último cambio rompió todo.

### 5.26 Carpetas de dependencias y ejecución

| Carpeta | Qué contiene | Regla práctica |
| --- | --- | --- |
| `vendor/` | Laravel, Pest y paquetes PHP instalados desde `composer.lock`. | No editar; regenerar con `composer install`. |
| `node_modules/` | Vite, Tailwind y paquetes npm. | No editar; regenerar con `npm install`. |
| `public/build/` | Assets compilados y manifest de Vite. | Regenerar con `npm run build`. |
| `.git/` | Objetos, referencias y configuración Git. | No tocar para cambiar la app. |

## 6. Modelo de datos explicado

### `users`

Guarda identidad, correo único, hash de contraseña, verificación, token de recordar y cuotas de IA. También existen tablas de sesión y recuperación creadas por la migración base.

### `projects`

Cada proyecto pertenece a un usuario. Guarda la entrada original, fechas, tipo, equipo, estado, metadata del intento, error y duración total calculada.

Los campos `generation_attempt` y `charged_generation_attempt` cumplen trabajos distintos:

- El primero identifica el intento vigente.
- El segundo evita cobrar dos veces el mismo intento.

### `project_clarifications`

Cada pregunta pertenece a un proyecto y a un intento. Puede ser texto o select, con opciones JSON. El índice único impide repetir la misma `key` en ronda/intento.

### `activities`

Guarda información de negocio y resultados CPM. Los tiempos son offsets enteros desde el inicio del proyecto; las fechas se derivan cuando se muestran.

### `activity_dependencies`

Es una tabla pivote autorrelacionada. `activity_id` no puede comenzar hasta que `predecessor_id` termine. La pareja es única para evitar aristas duplicadas.

## 7. Recorridos de código recomendados

### 7.1 Login

1. `routes/web.php` recibe GET/POST `/login`.
2. `AuthenticatedSessionController` muestra la vista o procesa el request.
3. `LoginRequest` valida, limita intentos y llama `Auth::attempt`.
4. La sesión se regenera.
5. El usuario vuelve a la URL pretendida o al dashboard.

### 7.2 Creación de proyecto

1. `ProjectWizardController::type()` muestra los enums.
2. `storeType()` guarda el tipo en sesión.
3. `prompt()` muestra contexto y créditos.
4. `StoreProjectRequest` autoriza y valida.
5. `ProjectController::store()` crea mediante la relación del usuario y despacha aclaraciones después del commit.

### 7.3 Aclaraciones

1. El job llama a `ProjectClarificationGenerator`.
2. El generator usa builder + cliente Gemini.
3. Valida el JSON.
4. Si faltan datos, persiste preguntas y cambia a `AwaitingInput`.
5. La pantalla se recarga y muestra `clarifications-form`.
6. El request dinámico valida respuestas y el controlador despacha el plan final.

### 7.4 Generación final y CPM

1. `GenerateProjectSchedule` verifica intento y crédito.
2. `ProjectPlanGenerator` arma el prompt incluyendo aclaraciones.
3. `GeminiClient` devuelve JSON.
4. El generator valida cantidad, códigos, texto, duración y precedentes.
5. `CpmCalculator` valida el grafo y calcula tiempos.
6. Una transacción bloquea proyecto y usuario, reemplaza actividades, sincroniza precedentes, marca `Ready` y cobra.

### 7.5 Visualización

1. `ProjectController::show()` autoriza y carga relaciones sin lazy loading.
2. Elige malla o Gantt según `?view=`.
3. Selecciona una actividad por `?activity=` o la primera crítica.
4. `cpm-graph.blade.php` dibuja el grafo ya calculado.
5. Para Gantt, `GanttTimelineBuilder` construye un modelo de presentación y `gantt-chart.blade.php` lo renderiza.

### 7.6 Cambiar una actividad

1. `UpdateActivityRequest` autoriza y valida.
2. `ActivityController::update()` actualiza dentro de transacción.
3. Recarga todas las actividades y precedentes.
4. Recalcula la malla completa.
5. Guarda nuevos tiempos y duración total.

Marcar completada no recalcula CPM porque cambia el avance real, no el plan.

## 8. Conexión detallada con las clases 9, 10 y 11

### 8.1 Clase 9: introducción, instalación, primer proyecto, rutas y layouts

#### Conceptos de la clase presentes

- **Laravel como framework:** `composer.json` trae el framework y la estructura convencional.
- **MVC:** rutas, controladores, modelos y vistas están separados.
- **Artisan:** el archivo `artisan`, migraciones, jobs y clases siguen el flujo `make:*`, `migrate`, `route:list`, `tinker` y cachés visto en la clase.
- **Blade:** layouts, componentes, `@csrf`, `@error`, `@foreach`, `@if`, `@isset` y helpers `route()`.
- **Vite y Tailwind:** `vite.config.js`, `app.css`, `@vite` y `composer run dev`.
- **Configuración segura:** `.env.example` → `config/services.php` → `config(...)`.
- **API externa:** `GeminiClient` reemplaza al `TraductorController` del ejemplo, con responsabilidades mejor separadas.
- **Cliente HTTP y errores:** timeout, reintentos, respuesta fallida, JSON vacío/inválido y mensajes al usuario.
- **Rutas con nombre:** todas las vistas usan `route(...)`.
- **Reutilización de marco:** los componentes `x-layouts.app` y `x-layouts.guest` cumplen el mismo objetivo que `@extends/@yield`.

#### Diferencia Groq ↔ Gemini

| Traductor de clase 9 | Project LevelUp |
| --- | --- |
| `GROQ_API_KEY` | `GEMINI_API_KEY` |
| `config('services.groq...')` | `config('services.gemini...')` |
| Endpoint chat completions | Endpoint `generateContent` |
| Devuelve una traducción | Devuelve JSON de preguntas o actividades |
| Controlador hace la llamada | Cliente y generators separados |
| Una petición web espera | Trabajo en cola con polling |

La idea transferible es la misma: secreto en entorno, configuración central, validación antes de llamar, instrucción de sistema separada, manejo de errores y respuesta mostrada en Blade.

### 8.2 Clase 10: clases, modelos, base de datos y CRUD

#### Clases y objetos

El repositorio es una aplicación orientada a objetos:

- `class Project extends Model`: hereda lectura/escritura Eloquent.
- `class User extends Authenticatable`: hereda Eloquent más autenticación.
- Los controladores extienden `Controller`.
- Los jobs usan el trait `Queueable`.
- Los modelos usan `HasFactory` y `User` usa `Notifiable`.
- `ScheduledActivity` es un objeto inmutable de resultado.
- `$this` aparece dentro de cada clase para acceder al objeto actual.

#### Modelo ↔ tabla

La equivalencia mostrada con `Animal` se ve en `Project`, `Activity` y `ProjectClarification`. Una fila se convierte en objeto y las relaciones permiten recorrer datos con propiedades o construir consultas con paréntesis.

#### Migraciones y SQLite

El proyecto usa migraciones para crear todo el esquema y un archivo SQLite local. A diferencia del ejemplo `animales`, los nombres ingleses siguen las convenciones, por lo que no necesitan `protected $table`.

#### CRUD y rutas

No se usa un `Route::resource` clásico, porque el producto tiene operaciones específicas:

- Crear proyecto.
- Ver cronograma.
- Regenerar.
- Eliminar.
- Actualizar/completar actividades.
- Responder aclaraciones.

Aun así aparecen las ideas de la clase: GET para formularios/vistas, POST para crear/acciones, PATCH para actualizar, DELETE para borrar, route model binding, validación, redirects, mensajes de sesión y CSRF.

#### Relaciones

La relación autorreferente de actividades es más avanzada que el refugio:

- `predecessors()` y `successors()` usan la misma tabla `activities` con una pivote.
- Es una relación muchos-a-muchos dirigida; invertir claves cambia el sentido.

### 8.3 Clase 11: usuarios, dueños y autorización

Project LevelUp sigue casi punto por punto la progresión del refugio seguro:

| Concepto de clase 11 | Implementación en el proyecto |
| --- | --- |
| Tabla `users` de fábrica | Migración `0001...create_users_table.php` |
| Contraseña hasheada | Cast `'password' => 'hashed'` en `User` |
| Registro manual | `RegisteredUserController` + `RegisterRequest` |
| Login manual | `AuthenticatedSessionController` + `LoginRequest` |
| Logout en tres pasos | `Auth::logout`, `invalidate`, `regenerateToken` |
| `guest` y `auth` | Grupos en `routes/web.php` |
| Un dueño tiene muchos registros | `User::projects()` |
| Cada registro pertenece a un dueño | `Project::user()` y FK `user_id` |
| Crear desde la relación | `$request->user()->projects()->create(...)` |
| Filtrar por sesión | Dashboard usa `where('user_id', $userId)` |
| Policy del dueño | `ProjectPolicy` |
| Autorizar antes de actuar | `$this->authorize(...)` y Form Requests |
| `@can` como cortesía visual | No se usa de forma general; la seguridad está en backend |
| Evitar `user_id` del navegador | `user_id` no está en `$fillable` y se asigna por relación |

#### Por qué el dashboard no basta

Filtrar el dashboard oculta proyectos ajenos, pero no protege una URL predecible. La protección real está en `ProjectPolicy` y se ejecuta al abrir la malla, regenerar, eliminar o modificar actividades.

#### `Gate::authorize` vs `$this->authorize`

La clase 11 muestra dos soluciones. Este proyecto eligió:

```php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

Por eso los controladores pueden usar `$this->authorize('view', $project)`. Es funcionalmente equivalente a `Gate::authorize(...)`, con una sintaxis distinta.

#### Defensa en profundidad

El proyecto combina:

- Middleware de autenticación.
- Policy de propiedad.
- Autorización de Form Requests.
- `$fillable`.
- Validación de servidor.
- CSRF.
- Hash de contraseña.
- Regeneración/invalidez de sesión.
- Transacciones y locks para concurrencia.

Ninguna defensa depende de que el navegador envíe solo los campos visibles.

## 9. Qué conviene estudiar primero

Orden recomendado para entender el repositorio sin saltar entre demasiadas capas:

1. `README.md` y esta guía.
2. `routes/web.php` para conocer las pantallas y acciones.
3. Modelos y migraciones juntos: `User`, `Project`, `Activity`, aclaraciones.
4. Auth: controllers, requests y vistas.
5. Asistente: `ProjectWizardController`, sus vistas y `StoreProjectRequest`.
6. Estados/enums y pantalla `generating`.
7. Jobs y servicios de IA.
8. `CpmCalculator` y sus pruebas unitarias.
9. `ProjectPlanGenerator` para ver la orquestación completa.
10. `ProjectController::show`, grafo, Gantt y detalle.
11. `ProjectPolicy` y pruebas de acceso ajeno.
12. `docs/arquitectura.md` y `docs/prompt.md` para decisiones y límites conocidos.

## 10. Comandos útiles

```bash
# instalar dependencias
composer install
npm install

# preparar entorno
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# trabajar
composer run dev

# inspeccionar
php artisan route:list --except-vendor
php artisan about
php artisan tinker

# base de datos
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh --seed  # borra todos los datos locales

# limpiar cachés durante desarrollo
php artisan optimize:clear

# probar y formatear
php artisan test --compact
vendor/bin/pint

# compilar frontend para entrega
npm run build
```

Todos los comandos deben ejecutarse desde la raíz del proyecto. `migrate:fresh` es destructivo para los datos de la base seleccionada; debe usarse solo cuando borrar todo sea aceptable.

## 11. Puntos de atención al revisar o extender

- La cola debe estar ejecutándose; si no, la pantalla de generación queda esperando.
- Cambiar `.env` puede exigir `php artisan config:clear` o `optimize:clear`.
- El modelo Gemini y su cuota son globales para la clave configurada, no por usuario de la app.
- `GeminiClient` actualmente trata 429, 503 y otros fallos HTTP bajo el mismo mensaje general; `docs/prompt.md` lo marca como pendiente.
- Los prompts intentan resistir *prompt injection*, pero la validación posterior sigue siendo obligatoria.
- Eloquent estricto está activo fuera de producción: hay que cargar relaciones de forma explícita para evitar lazy loading y consultas N+1.
- Una regeneración reemplaza toda la malla; no mezcla actividades antiguas con nuevas.
- El avance (`completed_at`) y la planificación CPM son conceptos separados.
- La vista Lista, exportación, compartir, recuperación de contraseña y reinicio mensual automático de créditos siguen pendientes según la documentación.
- El backend de edición de actividades existe, pero la vista actual no muestra el formulario correspondiente.
- La documentación y `composer.json` no coinciden completamente respecto de un proceso de logs dentro de `composer run dev`; al diagnosticar, confiar primero en el script real.

## 12. Resumen mental

Si hay que recordar el proyecto en pocas ideas:

1. Laravel recibe la petición, las rutas dirigen y los controladores coordinan.
2. Eloquent representa usuarios, proyectos, actividades y aclaraciones.
3. Gemini propone lenguaje y estructura; PHP valida y calcula.
4. Las colas evitan bloquear la web y los intentos evitan resultados antiguos.
5. Las transacciones y locks mantienen datos y créditos consistentes.
6. Blade presenta dashboard, formularios, malla y Gantt; JavaScript solo complementa.
7. `auth` identifica al usuario y `ProjectPolicy` protege lo que le pertenece.
8. Los conceptos de las clases 9, 10 y 11 aparecen aplicados en una arquitectura más grande, pero conservan la misma lógica básica.
