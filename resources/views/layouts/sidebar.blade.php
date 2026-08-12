@php
    $user = auth()->user();
    $usedCredits = $user?->ai_credits_used ?? 0;
    $creditLimit = $user?->ai_credits_limit ?? 0;
    $creditPercent = $creditLimit > 0 ? (int) round($usedCredits / $creditLimit * 100) : 0;
    $navItem = 'flex h-8 items-center gap-2.5 rounded-lg px-2.5 text-[13px] font-medium';
    $navIdle = 'text-ink-700 hover:bg-ink-100';
    $navActive = 'bg-brand-100 font-semibold text-brand-600';
    $navLabel = 'px-2.5 pb-1.5 pt-3.5 text-[10px] font-bold uppercase tracking-[0.12em] text-ink-400';
    $toneDot = [
        'done' => 'bg-done',
        'critical' => 'bg-critical',
        'slack' => 'bg-slack',
        'brand' => 'bg-brand-500',
    ];
@endphp

<aside class="flex w-[220px] flex-none flex-col gap-1 border-r border-ink-200 bg-ink-50 p-2.5">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2 pb-3.5 pt-1">
        <x-logo class="size-[22px]" />
        <span class="text-[14px] font-bold tracking-tight">LevelUp</span>
    </a>

    <a href="{{ route('dashboard') }}" class="{{ $navItem }} {{ request()->routeIs('dashboard') ? $navActive : $navIdle }}">
        <x-nav-icon name="projects" />
        Mis proyectos
    </a>
    <span class="{{ $navItem }} text-ink-400" title="Disponible en una próxima versión">
        <x-nav-icon name="activities" />
        Actividades
    </span>
    <span class="{{ $navItem }} text-ink-400" title="Disponible en una próxima versión">
        <x-nav-icon name="calendar" />
        Calendario
    </span>

    @if ($sidebarProjects->isNotEmpty())
        <p class="{{ $navLabel }}">Proyectos</p>

        @foreach ($sidebarProjects as $sidebarProject)
            @php $isCurrent = request()->route('project')?->is($sidebarProject) ?? false; @endphp
            <a href="{{ $sidebarProject->status === \App\Enums\ProjectStatus::Ready
                        ? route('projects.show', $sidebarProject)
                        : route('projects.generating', $sidebarProject) }}"
               class="{{ $navItem }} {{ $isCurrent ? $navActive : $navIdle }}">
                <span class="size-1.5 flex-none rounded-full {{ $toneDot[$sidebarProject->healthTone()] }}"></span>
                <span class="truncate">{{ $sidebarProject->name }}</span>
            </a>
        @endforeach
    @endif

    <p class="{{ $navLabel }}">Cuenta</p>
    <a href="{{ route('account.plan') }}" class="{{ $navItem }} {{ request()->routeIs('account.plan') ? $navActive : $navIdle }}">
        <x-nav-icon name="plan" />
        Plan y consultas
    </a>
    <span class="{{ $navItem }} text-ink-400" title="Disponible en una próxima versión">
        <x-nav-icon name="settings" />
        Configuración
    </span>

    <div class="mt-auto flex flex-col gap-[7px] rounded-[10px] border border-brand-100 bg-brand-50 p-3">
        <div class="flex items-baseline justify-between">
            <span class="text-[11px] font-semibold text-ink-700">Consultas IA</span>
            <span class="num text-[11.5px] font-semibold text-brand-600">{{ $usedCredits }} / {{ $creditLimit }}</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-ink-200">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $creditPercent }}%"></div>
        </div>
        <span class="text-[11px] leading-snug text-ink-500">
            Se renuevan el {{ now()->addMonthNoOverflow()->startOfMonth()->translatedFormat('j \d\e F') }}.
        </span>
        @if ($user !== null && ! $user->isOnProPlan())
            <a href="{{ route('account.plan') }}" class="text-[11px] font-semibold text-brand-600 hover:text-brand-700">
                Mejorar plan →
            </a>
        @endif
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full rounded-lg px-2.5 py-2 text-left text-[13px] text-ink-500 hover:bg-ink-100 hover:text-ink-900">
            Cerrar sesión
        </button>
    </form>
</aside>
