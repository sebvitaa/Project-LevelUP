# Project LevelUp

Convierte la descripción de un proyecto, escrita en lenguaje natural, en un cronograma
**CPM** con ruta crítica, holguras y dependencias calculadas. La IA identifica las
actividades; el servidor resuelve la malla.

**IIP323W — Tecnologías y Aplicaciones Web y Móviles** · Unidad 3 (Laravel) · Sección 1
Gabriel Marín ([gmarinr@udd.cl](mailto:gmarinr@udd.cl)) · Sebastián Ramírez ([seramirezo@udd.cl](mailto:seramirezo@udd.cl))

---

## El problema

Planificar un proyecto grande a mano es lento y estimar dependencias entre decenas de
actividades es propenso al error. Las herramientas que sí lo hacen bien exigen que el
usuario arme la malla nodo por nodo.

Project LevelUp cierra esa brecha: describes el proyecto en un párrafo y obtienes el
cronograma en minutos.

---

## El flujo

```
01 Login/Registro → 02 Dashboard → 03 Tipo de proyecto → 04 Prompt
                         ↑                                    ↓
                         └──── 06 Malla CPM/Gantt ←── 05 Generando
```

| # | Pantalla | Qué hace |
|---|---|---|
| 01 | Login / Registro | Sesión con cuota mensual de generaciones |
| 02 | Dashboard | Proyectos con % de avance, fecha límite y estado |
| 03 | Tipo de proyecto | Ajusta el vocabulario y las actividades que sugerirá la IA |
| 04 | Prompt | Descripción libre + fechas y tamaño del equipo |
| 05 | Generando | Análisis en cola, preguntas opcionales y polling del estado cada 2 s |
| 06 | Malla CPM/Gantt | Nodos, ruta crítica, fechas, barras y ficha de cada actividad |

Los mockups de las seis pantallas están en
[`docs/mockups/project-levelup-mockups.html`](docs/mockups/project-levelup-mockups.html).

---

## Arquitectura

```
Prompt del usuario
      │
      ▼
PromptBuilder ──► GeminiClient ──► Gemini 3.1 Flash-Lite
      │                                   │
      │            actividades, duraciones, precedentes (JSON)
      ▼                                   │
ProjectPlanGenerator ◄────────────────────┘
      │
      ├─► valida la forma de la respuesta
      ├─► CpmCalculator  ES/EF · LS/LF · holguras · ruta crítica · layout
      └─► guarda en una transacción: projects, activities, activity_dependencies
```

**La decisión que define el proyecto:** a la IA se le piden solo actividades, duraciones y
precedentes. El CPM lo calcula PHP. Un modelo de lenguaje no es una herramienta de cálculo
confiable; el CPM es un algoritmo determinista, y hacerlo en el servidor lo vuelve
reproducible, testeable sin red y gratis.

### Capas

| Carpeta | Responsabilidad |
|---|---|
| `app/Services/Cpm/` | Algoritmo CPM puro, sin Eloquent ni base de datos |
| `app/Services/Ai/` | Prompt, cliente HTTP de Gemini y orquestación del plan |
| `app/Jobs/` | Generación en cola, fuera del ciclo de request |
| `app/Http/Controllers/` | Una clase por pantalla del flujo |
| `app/Policies/` | Un proyecto solo lo ve y lo toca su dueño |
| `resources/views/` | Blade + Tailwind v4, sin framework de frontend |

**La documentación completa de la arquitectura está en
[`docs/arquitectura.md`](docs/arquitectura.md)**: decisiones y su porqué, estructura de
carpetas archivo por archivo, modelo de datos, tabla de rutas, el algoritmo paso a paso y
la cobertura de pruebas. Ese documento se mantiene al día con cada cambio del proyecto.

---

## Stack

- PHP 8.4 · Laravel 13
- MySQL en despliegue, SQLite en desarrollo y pruebas
- Google Gemini 3.1 Flash-Lite vía el cliente HTTP de Laravel
- Blade + Tailwind CSS v4
- Pest 5 para las pruebas · Pint para el formato

---

## Puesta en marcha

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# Agregar GEMINI_API_KEY al .env

php artisan migrate --seed
composer run dev
```

`composer run dev` levanta el servidor, la cola y Vite a la vez. **La cola tiene que estar
corriendo**, o la pantalla 05 se queda esperando para siempre.

El seed crea un proyecto de demostración con la misma malla de los mockups
(ruta crítica de 39 días), accesible con `demo@levelup.test` / `password`.

---

## Pruebas

```bash
php artisan test --compact
php artisan test --filter=CpmCalculatorTest
vendor/bin/pint                     # formato antes de cerrar cualquier cambio
```

La malla de referencia — A→C→D→F→G→H, 39 días, con 6 días de holgura en B y E — se usa
como caso de verificación en los tests unitarios, en el seeder y en los mockups. Si los
tres coinciden, el cálculo está bien.

---

## Estado

Funcionando de punta a punta: registro, creación asistida, aclaraciones opcionales, generación
con IA, cálculo CPM, malla navegable, Gantt y edición de actividades con recálculo automático de
la ruta crítica. Las generaciones consumen una consulta solo cuando la malla final queda lista.

Pendiente: vista Lista, exportar, compartir, notificaciones y reinicio mensual automático de
créditos. La lista completa está al final de
[`docs/arquitectura.md`](docs/arquitectura.md).
