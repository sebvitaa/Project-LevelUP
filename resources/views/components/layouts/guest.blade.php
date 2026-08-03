<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Project LevelUp' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white text-ink-900 antialiased">
    <div class="flex h-full">
        {{-- Panel de marca: fondo azul y el motivo de puntos del logo. --}}
        <div class="hidden w-[46%] flex-none flex-col justify-between bg-brand-500 bg-dots p-11 text-white lg:flex">
            <div class="flex items-center gap-3">
                <x-logo class="size-6" color="text-white" />
                <span class="text-lg font-bold tracking-tight">Project LevelUp</span>
            </div>

            <p class="max-w-[15ch] text-3xl font-semibold leading-tight tracking-tight text-balance">
                Describe tu proyecto.
                <span class="opacity-60">Nosotros armamos la malla.</span>
            </p>

            <p class="max-w-[34ch] text-sm text-white/75">
                Ruta crítica, holguras y dependencias calculadas por IA en menos de un minuto.
            </p>
        </div>

        <div class="flex flex-1 items-center justify-center p-11">
            <div class="flex w-[340px] flex-col gap-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
