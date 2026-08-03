{{-- Pantalla 01 — Registro --}}
<x-layouts.guest title="Crear cuenta · Project LevelUp">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold tracking-tight">Crea tu cuenta</h1>
        <p class="text-sm text-ink-500">Empieza con 20 generaciones de malla al mes, gratis.</p>
    </div>

    <div class="flex gap-1 rounded-lg bg-ink-100 p-1">
        <a href="{{ route('login') }}" class="flex h-8 flex-1 items-center justify-center rounded-md text-sm font-semibold text-ink-500 hover:text-ink-900">
            Iniciar sesión
        </a>
        <span class="flex h-8 flex-1 items-center justify-center rounded-md bg-white text-sm font-semibold shadow-sm">
            Crear cuenta
        </span>
    </div>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-xs font-semibold text-ink-700">Nombre</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
            @error('name')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-xs font-semibold text-ink-700">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
            @error('email')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-xs font-semibold text-ink-700">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
            @error('password')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password_confirmation" class="text-xs font-semibold text-ink-700">Repite la contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
        </div>

        <button type="submit" class="h-11 rounded-lg bg-brand-500 text-sm font-semibold text-white hover:bg-brand-600">
            Crear cuenta
        </button>
    </form>

    <p class="text-center text-sm text-ink-500">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="font-semibold text-brand-500 hover:text-brand-600">Inicia sesión</a>
    </p>
</x-layouts.guest>
