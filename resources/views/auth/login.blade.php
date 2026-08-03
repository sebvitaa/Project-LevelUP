{{-- Pantalla 01 — Login --}}
<x-layouts.guest title="Iniciar sesión · Project LevelUp">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold tracking-tight">Bienvenido de vuelta</h1>
        <p class="text-sm text-ink-500">Entra para seguir tus proyectos y su avance.</p>
    </div>

    <div class="flex gap-1 rounded-lg bg-ink-100 p-1">
        <span class="flex h-8 flex-1 items-center justify-center rounded-md bg-white text-sm font-semibold shadow-sm">
            Iniciar sesión
        </span>
        <a href="{{ route('register') }}" class="flex h-8 flex-1 items-center justify-center rounded-md text-sm font-semibold text-ink-500 hover:text-ink-900">
            Crear cuenta
        </a>
    </div>

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-xs font-semibold text-ink-700">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
            @error('email')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-xs font-semibold text-ink-700">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
            @error('password')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-100">
            Recordarme
        </label>

        <button type="submit" class="h-11 rounded-lg bg-brand-500 text-sm font-semibold text-white hover:bg-brand-600">
            Iniciar sesión
        </button>
    </form>

    <p class="text-center text-sm text-ink-500">
        ¿Sin cuenta?
        <a href="{{ route('register') }}" class="font-semibold text-brand-500 hover:text-brand-600">Regístrate gratis</a>
    </p>
</x-layouts.guest>
