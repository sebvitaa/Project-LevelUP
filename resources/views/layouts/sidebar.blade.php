@php
    $user = auth()->user();
    $usedCredits = $user?->ai_credits_used ?? 0;
    $creditLimit = $user?->ai_credits_limit ?? 0;
    $creditPercent = $creditLimit > 0 ? (int) round($usedCredits / $creditLimit * 100) : 0;
@endphp

<aside class="flex w-56 flex-none flex-col gap-1 border-r border-ink-200 bg-ink-50 p-3">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-2 pb-3 pt-1">
        <x-logo class="size-5" />
        <span class="text-sm font-bold tracking-tight">LevelUp</span>
    </a>

    <a href="{{ route('dashboard') }}"
       @class([
           'flex h-8 items-center gap-2 rounded-lg px-3 text-sm',
           'bg-brand-100 font-semibold text-brand-600' => request()->routeIs('dashboard'),
           'text-ink-700 hover:bg-ink-100' => ! request()->routeIs('dashboard'),
       ])>
        Mis proyectos
    </a>

    @if ($user?->projects->isNotEmpty())
        <p class="px-3 pb-1 pt-4 text-[10px] font-bold uppercase tracking-widest text-ink-500">Proyectos</p>

        @foreach ($user->projects->take(8) as $sidebarProject)
            <a href="{{ route('projects.show', $sidebarProject) }}"
               @class([
                   'flex h-8 items-center gap-2 truncate rounded-lg px-3 text-sm',
                   'bg-brand-100 font-semibold text-brand-600' => request()->route('project')?->is($sidebarProject),
                   'text-ink-700 hover:bg-ink-100' => ! request()->route('project')?->is($sidebarProject),
               ])>
                <span @class([
                    'size-1.5 flex-none rounded-full',
                    'bg-critical' => $sidebarProject->isOverdue(),
                    'bg-done' => ! $sidebarProject->isOverdue(),
                ])></span>
                <span class="truncate">{{ $sidebarProject->name }}</span>
            </a>
        @endforeach
    @endif

    <div class="mt-auto flex flex-col gap-2 rounded-xl border border-brand-100 bg-brand-50 p-3">
        <div class="flex items-baseline justify-between">
            <span class="text-xs font-semibold text-ink-700">Consultas IA</span>
            <span class="num text-xs font-semibold text-brand-600">{{ $usedCredits }} / {{ $creditLimit }}</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-ink-200">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $creditPercent }}%"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-ink-500 hover:bg-ink-100">
            Cerrar sesión
        </button>
    </form>
</aside>
