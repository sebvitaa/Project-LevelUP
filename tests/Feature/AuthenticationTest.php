<?php

use App\Models\User;

it('muestra la pantalla de login', function () {
    $this->get(route('login'))->assertOk()->assertSee('Bienvenido de vuelta');
});

it('deja entrar con credenciales correctas', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rechaza credenciales incorrectas', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'contraseña-equivocada',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('crea la cuenta y lleva directo al asistente', function () {
    $this->post(route('register'), [
        'name' => 'Benjamín Soto',
        'email' => 'benjamin@empresa.cl',
        'password' => 'contraseña-segura',
        'password_confirmation' => 'contraseña-segura',
    ])->assertRedirect(route('projects.create.type'));

    $this->assertAuthenticated();

    // Toda cuenta nueva parte con la cuota gratis del plan.
    expect(User::where('email', 'benjamin@empresa.cl')->sole()->ai_credits_limit)->toBe(20);
});

it('cierra la sesión', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('manda al login a quien no ha entrado', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
