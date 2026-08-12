<?php

namespace App\Services;

use App\Enums\ProjectType;
use Illuminate\Support\Carbon;

/**
 * Briefs de ejemplo que rellenan el formulario de la pantalla 04.
 *
 * Están escritos con el patrón que mejor funcionó al probar el prompt contra la
 * API real (ver docs/prompt.md): entregables concretos, proveedores e
 * integraciones con nombre, límites de alcance explícitos y varios frentes que
 * pueden avanzar en paralelo. Un brief así rinde una malla con holgura real en
 * vez de una cadena lineal, y suele evitar la ronda de aclaraciones.
 *
 * Las fechas se guardan como desplazamientos en días y se resuelven al
 * renderizar, para que un ejemplo nunca quede con una fecha pasada.
 */
class ProjectExamples
{
    /**
     * Ejemplos listos para el formulario, con las fechas ya resueltas.
     *
     * @return array<int, array{name: string, prompt: string, starts_on: string, deadline: string, team_size: int}>
     */
    public function forType(ProjectType $type): array
    {
        $today = Carbon::today();

        return array_map(
            static fn (array $example): array => [
                'name' => $example['name'],
                'prompt' => $example['prompt'],
                'starts_on' => $today->copy()->addDays($example['starts_in_days'])->toDateString(),
                'deadline' => $today->copy()
                    ->addDays($example['starts_in_days'] + $example['horizon_days'])
                    ->toDateString(),
                'team_size' => $example['team_size'],
            ],
            $this->catalog($type),
        );
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function catalog(ProjectType $type): array
    {
        return match ($type) {
            ProjectType::Software => $this->software(),
            ProjectType::Construction => $this->construction(),
            ProjectType::Event => $this->event(),
            ProjectType::Research => $this->research(),
            ProjectType::Marketing => $this->marketing(),
            ProjectType::Blank => $this->blank(),
        };
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function software(): array
    {
        return [
            [
                'name' => 'Portal de pagos',
                'prompt' => 'Portal web para pagar cuentas de servicios básicos. Login con Clave Única, integración con Transbank Webpay, panel de administración para conciliación bancaria, emisión de boleta electrónica al SII y reportería de recaudación. Backend en Laravel y frontend en Vue, ya definidos. Los requerimientos están escritos y aprobados por el cliente. No incluye app móvil ni migración de datos históricos.',
                'starts_in_days' => 14,
                'horizon_days' => 200,
                'team_size' => 6,
            ],
            [
                'name' => 'App de reservas',
                'prompt' => 'App móvil de reservas para una cadena de tres peluquerías. Alcance cerrado: login por SMS con Twilio (cuenta ya contratada), catálogo de servicios, calendario de disponibilidad por profesional, pago con Stripe (cuenta verificada y sandbox habilitado), notificaciones push con Firebase y un panel web de administración. Solo Android, mínimo API 26. Flutter para la app y Laravel para el backend. El diseño UI está entregado en Figma y aprobado. QA manual interno, sin certificaciones externas. Publicación en Google Play con la cuenta de desarrollador ya creada.',
                'starts_in_days' => 7,
                'horizon_days' => 110,
                'team_size' => 4,
            ],
            [
                'name' => 'Flota GPS',
                'prompt' => 'Plataforma web para seguir en tiempo real la ruta de una flota de 25 camiones. Los equipos GPS ya están instalados y exponen una API REST del proveedor, así que no hay desarrollo de hardware ni app para conductores. Incluye ingesta y almacenamiento del histórico de posiciones, mapa en vivo con Leaflet, geocercas con alertas por correo cuando un camión se sale de su ruta, informe semanal de kilómetros por vehículo y gestión de usuarios con dos roles. Backend Laravel sobre PostgreSQL.',
                'starts_in_days' => 7,
                'horizon_days' => 95,
                'team_size' => 3,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function construction(): array
    {
        return [
            [
                'name' => 'Edificio Los Aromos',
                'prompt' => 'Construcción de un edificio habitacional de 8 pisos con 2 subterráneos de estacionamiento en Santiago, 48 departamentos en total. El terreno está comprado y el proyecto de arquitectura aprobado. Contempla la tramitación del permiso de edificación, socalzado y excavación de los subterráneos, fundaciones, obra gruesa de hormigón armado, albañilerías, instalaciones sanitarias y eléctricas, terminaciones, urbanización del frontis y recepción municipal final.',
                'starts_in_days' => 21,
                'horizon_days' => 545,
                'team_size' => 40,
            ],
            [
                'name' => 'Bodega industrial',
                'prompt' => 'Construcción de una bodega de 400 m2 en un terreno ya nivelado de un parque industrial. Incluye permiso de obra menor, excavación de fundaciones, radier de hormigón con su curado, estructura metálica prefabricada por un proveedor externo, cubierta y revestimiento lateral de zinc alum, portones seccionales, instalación eléctrica industrial y recepción final. La estructura metálica se fabrica en taller mientras avanza la obra civil.',
                'starts_in_days' => 14,
                'horizon_days' => 75,
                'team_size' => 5,
            ],
            [
                'name' => 'Sucursal centro',
                'prompt' => 'Habilitación de un local comercial de 220 m2 en un centro comercial, para abrir una sucursal bancaria. El local se entrega en obra gris. Incluye permiso de la administración del mall, demolición de tabiques existentes, tabiquería nueva en volcanita, climatización, instalación eléctrica y de corrientes débiles, cableado de red certificado, bóveda con puerta de seguridad certificada que se importa, mobiliario a medida fabricado por un tercero, señalética corporativa, pintura y limpieza final. Las faenas ruidosas solo se pueden hacer fuera del horario de atención del mall.',
                'starts_in_days' => 10,
                'horizon_days' => 130,
                'team_size' => 12,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function event(): array
    {
        return [
            [
                'name' => 'Titulación 2026',
                'prompt' => 'Ceremonia de titulación para 300 asistentes en un centro de eventos, con cóctel posterior. La fecha no se puede mover. Hay que cotizar y contratar el salón, el catering, el fotógrafo y la amplificación; confeccionar el listado oficial de titulados con la dirección académica; imprimir diplomas que requieren la firma manuscrita del rector; coordinar el protocolo y el orden de la ceremonia; enviar las invitaciones y controlar las confirmaciones; y hacer un ensayo general la semana previa.',
                'starts_in_days' => 7,
                'horizon_days' => 100,
                'team_size' => 4,
            ],
            [
                'name' => 'Feria laboral',
                'prompt' => 'Feria laboral universitaria con 60 empresas expositoras y charlas en paralelo en dos salas. Incluye la convocatoria y confirmación de las empresas, el cobro de los stands, el diseño y arriendo del montaje modular, la habilitación eléctrica y de conectividad del recinto, la plataforma de postulación en línea para los alumnos, la difusión interna por correo y redes, la coordinación de los relatores de las charlas, el montaje el día previo y el desmontaje al cierre.',
                'starts_in_days' => 5,
                'horizon_days' => 50,
                'team_size' => 15,
            ],
            [
                'name' => 'Congreso técnico',
                'prompt' => 'Congreso técnico de dos días para 400 asistentes, con transmisión en vivo por streaming. Contempla la convocatoria y revisión de ponencias por un comité, la confirmación de tres charlistas internacionales con sus pasajes y alojamiento, el arriendo del centro de convenciones, la plataforma de inscripción con pago en línea, la producción audiovisual y el streaming, la impresión de credenciales y programa, la búsqueda de auspiciadores, el catering de ambos días y la publicación posterior de las grabaciones.',
                'starts_in_days' => 14,
                'horizon_days' => 130,
                'team_size' => 6,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function research(): array
    {
        return [
            [
                'name' => 'Tesis de magíster',
                'prompt' => 'Tesis de magíster sobre el efecto del teletrabajo en la productividad de equipos de desarrollo de software. Contempla la revisión bibliográfica, la aprobación del comité de ética institucional antes de aplicar instrumentos, el diseño y pilotaje de una encuesta, el reclutamiento y la aplicación a 200 desarrolladores, el análisis estadístico en R, la redacción de los capítulos, dos rondas de correcciones con el profesor guía y la defensa final. La desarrolla una sola persona en paralelo a su empleo.',
                'starts_in_days' => 14,
                'horizon_days' => 300,
                'team_size' => 1,
            ],
            [
                'name' => 'Estudio de suelos',
                'prompt' => 'Proyecto de investigación aplicada sobre degradación de suelos agrícolas en el secano costero, con financiamiento ya adjudicado. Incluye la revisión del estado del arte, la selección y caracterización de seis predios, dos campañas de terreno para tomar muestras separadas por estación, los análisis físico-químicos en laboratorio externo con tiempos de espera propios, el procesamiento de imágenes satelitales, el modelamiento estadístico, la redacción del informe para la agencia financiadora y el envío de un paper a revista indexada.',
                'starts_in_days' => 21,
                'horizon_days' => 365,
                'team_size' => 4,
            ],
            [
                'name' => 'Revisión sistemática',
                'prompt' => 'Revisión sistemática sobre intervenciones de salud mental en estudiantes universitarios, siguiendo el protocolo PRISMA, para publicar en una revista indexada. Contempla el registro del protocolo, la definición de la estrategia de búsqueda con una bibliotecóloga, la búsqueda en cuatro bases de datos, el tamizaje de títulos y resúmenes por dos revisores independientes, la resolución de discrepancias, la lectura a texto completo, la extracción de datos, la evaluación de riesgo de sesgo, el metaanálisis en RevMan, la redacción del manuscrito y una ronda de revisión por pares con correcciones.',
                'starts_in_days' => 10,
                'horizon_days' => 250,
                'team_size' => 2,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function marketing(): array
    {
        return [
            [
                'name' => 'Lanzamiento Verano',
                'prompt' => 'Campaña de lanzamiento de una bebida energética nueva, enfocada en público de 18 a 30 años. Incluye la definición de la estrategia y el mensaje, la producción de la sesión fotográfica y de un spot de 30 segundos, las piezas para redes sociales, la negociación con seis influencers y sus entregas de contenido, la compra de vía pública en Santiago con sus plazos de impresión e instalación, activaciones de degustación en playas los fines de semana, la pauta digital en Meta y TikTok, y un informe de resultados con las métricas por canal.',
                'starts_in_days' => 14,
                'horizon_days' => 80,
                'team_size' => 8,
            ],
            [
                'name' => 'Rebranding',
                'prompt' => 'Rebranding completo de una empresa de logística con 20 años en el mercado, incluyendo el sitio web nuevo. Contempla la investigación de marca con entrevistas a clientes, la definición de la nueva identidad verbal y visual, el manual de marca, el rediseño y desarrollo del sitio web corporativo con su migración de contenidos y redirecciones SEO, la aplicación de la marca a la flota de camiones y a las fachadas de sucursales, la papelería y firmas de correo, y una campaña de anuncio del cambio en LinkedIn y prensa del rubro.',
                'starts_in_days' => 10,
                'horizon_days' => 140,
                'team_size' => 6,
            ],
            [
                'name' => 'Cyber retail',
                'prompt' => 'Campaña de retail para el Cyber Monday de una tienda de electrohogar con ocho sucursales y venta en línea. La fecha del evento es fija. Incluye la definición del surtido y los descuentos con el área comercial, la negociación de aportes con cinco marcas proveedoras, la producción de piezas gráficas para el sitio y las redes, la preparación de las landing pages y el stress test de la plataforma de ecommerce, la coordinación con bodega y despacho para el peak de pedidos, la pauta digital y de televisión, el plan de correos a la base de clientes, y el informe de cierre con ventas por canal.',
                'starts_in_days' => 7,
                'horizon_days' => 95,
                'team_size' => 10,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, prompt: string, starts_in_days: int, horizon_days: int, team_size: int}>
     */
    private function blank(): array
    {
        return [
            [
                'name' => 'Mudanza',
                'prompt' => 'Mudanza de un departamento de dos dormitorios a una casa en otra comuna de la misma ciudad. Incluye cotizar y contratar la empresa de mudanza, hacer una limpieza previa para desprenderse de lo que no va, comprar cajas y embalar por ambiente, coordinar el corte y traspaso de los servicios de luz, agua e internet en ambas direcciones, pintar la casa nueva antes de entrar, hacer el aseo profundo del departamento que se entrega, el traslado propiamente tal, el desembalaje y la entrega de llaves al arrendador.',
                'starts_in_days' => 7,
                'horizon_days' => 45,
                'team_size' => 2,
            ],
            [
                'name' => 'Cafetería de barrio',
                'prompt' => 'Apertura de una cafetería de especialidad de 60 m2 en un barrio residencial. El local ya está arrendado. Contempla la obtención de la patente comercial y la resolución sanitaria, el diseño y la remodelación del local, la compra e instalación de la máquina de café y el equipamiento de cocina que se importa, la selección del proveedor de granos y la definición de la carta, la contratación y capacitación de tres personas, el sistema de punto de venta, la marca y la señalética, las redes sociales y la difusión previa, y una semana de marcha blanca antes de la apertura oficial.',
                'starts_in_days' => 14,
                'horizon_days' => 160,
                'team_size' => 3,
            ],
            [
                'name' => 'Certificación ISO 9001',
                'prompt' => 'Implementación de un sistema de gestión de calidad para certificar ISO 9001 en una empresa de servicios de 80 personas. Incluye el diagnóstico de brecha contra la norma, la definición del mapa de procesos, la redacción de los procedimientos y registros con cada área, la capacitación del personal, la formación de auditores internos, un ciclo completo de auditoría interna con sus acciones correctivas, la revisión por la dirección, la selección de la casa certificadora y las dos etapas de la auditoría de certificación, que las agenda un tercero.',
                'starts_in_days' => 21,
                'horizon_days' => 280,
                'team_size' => 5,
            ],
        ];
    }
}
