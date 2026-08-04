@php
    /**
     * Miniatura de tendencia del avance. Recibe una serie de porcentajes
     * (0–100) ya calculada por el controlador.
     *
     * El trazo se dibuja en un viewBox de 120×24 con `preserveAspectRatio="none"`
     * para que se estire al ancho de la tarjeta con altura fija. Como esa
     * deformación aplastaría un `<circle>`, el punto final se posiciona con CSS
     * por fuera del SVG y así conserva su forma.
     */
    $points = array_values($points);
    $count = max(count($points), 1);

    $width = 120;
    $height = 24;
    $floor = $height - 2;   // línea base
    $usable = $height - 5;  // aire arriba para que el 100 % no toque el borde

    $coordinates = [];

    foreach ($points as $index => $percent) {
        $x = $count <= 1 ? $width : round($index * ($width / ($count - 1)), 2);
        $y = round($floor - ($percent / 100) * $usable, 2);
        $coordinates[] = "{$x} {$y}";
    }

    $line = 'M'.implode(' L', $coordinates);
    $area = $line." L{$width} {$height} L0 {$height} Z";

    $lastPercent = (int) (end($points) ?: 0);
    $dotTop = round(($floor - ($lastPercent / 100) * $usable) / $height * 100, 2);
@endphp

<div class="relative mt-0.5 h-[22px] w-full pr-[3px]">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none"
         class="h-full w-full" role="img"
         aria-label="Avance de las últimas {{ $count }} semanas: {{ implode('%, ', $points) }}%">
        <path d="{{ $area }}" fill="var(--color-brand-100)" />
        <path d="{{ $line }}" fill="none" stroke="var(--color-brand-500)" stroke-width="1.6"
              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
    </svg>

    {{-- El extremo marcado es el dato de hoy: el que importa. --}}
    <span class="absolute size-[5px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500"
          style="left: 100%; top: {{ $dotTop }}%;" aria-hidden="true"></span>
</div>
