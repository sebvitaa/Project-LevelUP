<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Pantalla 01 — creación de cuenta. */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only('name', 'email', 'password'));

        // Recarga para traer los valores por defecto de la base (cuota de IA).
        $user->refresh();

        event(new Registered($user));
        Auth::login($user);

        // Cuenta nueva: no tiene proyectos, así que parte directo en el asistente.
        return redirect()->route('projects.create.type');
    }
}
