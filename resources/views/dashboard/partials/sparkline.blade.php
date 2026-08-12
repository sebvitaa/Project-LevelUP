@php
    $points = array_values($points);
    $count = max(count($points), 1);
    $width = 120;
    $height = 24;
    $floor = $height - 2;
    $usable = $height - 5;
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
    <span class="absolute size-[5px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500"
          style="left: 100%; top: {{ $dotTop }}%;" aria-hidden="true"></span>
</div>
