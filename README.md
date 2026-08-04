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
                         └──────── 06 Malla CPM ←──── 05 Generando
```

| # | Pantalla | Qué hace |
|---|---|---|
| 01 | Login / Registro | Sesión con cuota mensual de generaciones |
| 02 | Dashboard | Proyectos con % de avance, fecha límite y estado |
| 03 | Tipo de proyecto | Ajusta el vocabulario y las actividades que sugerirá la IA |
| 04 | Prompt | Descripción libre + fechas y tamaño del equipo |
| 05 | Generando | Job en cola; la vista consulta el estado cada 2 s |
| 06 | Malla CPM | Nodos, aristas, ruta crítica y ficha de cada actividad |

Los mockups de las seis pantallas están en
[`docs/mockups/project-levelup-mockups.html`](docs/mockups/project-levelup-mockups.html).

---

## Arquitectura

```
Prompt del usuario
      │
      ▼
PromptBuilder ──► GeminiClient ──► Gemini 2.5 Flash
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
la cobertura de pruebas.

El dashboard tiene además su propia referencia en
[`docs/Dashboard.md`](docs/Dashboard.md): cada elemento, de dónde sale cada dato, las
reglas de estado, las conexiones con las otras pantallas y la BD provisional.

Ambos documentos se mantienen al día en el mismo cambio que modifica el código.

---

## Stack

- PHP 8.4 · Laravel 13
- MySQL en despliegue, SQLite en desarrollo y pruebas
- Google Gemini 2.5 Flash (plan gratuito) vía el cliente HTTP de Laravel
- Blade + Tailwind CSS v4, con la tipografía del sistema (sin fuentes remotas)
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

El seed crea una cartera de demostración con cinco proyectos —uno por cada estado
posible— y mallas CPM reales, accesible con `demo@levelup.test` / `password`.
El detalle está en [`docs/Dashboard.md`](docs/Dashboard.md).

---

## Pruebas

```bash
php artisan test --compact          # 57 pruebas
php artisan test --filter=CpmCalculatorTest
vendor/bin/pint                     # formato antes de cerrar cualquier cambio
```

La malla de referencia — A→C→D→F→G→H, 39 días, con 6 días de holgura en B y E — se usa
como caso de verificación en los tests unitarios, en el seeder y en los mockups. Si los
tres coinciden, el cálculo está bien.

---

## Estado

Funcionando de punta a punta: registro, creación asistida, generación con IA, cálculo CPM,
malla navegable y edición de actividades con recálculo automático de la ruta crítica.

El dashboard está terminado a fidelidad de mockup. Pendiente: llevar las otras cinco
pantallas al mismo nivel visual, vistas Gantt y Lista, buscador, exportar y compartir.
La lista completa está al final de [`docs/arquitectura.md`](docs/arquitectura.md).
