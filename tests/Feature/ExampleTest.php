<?php

use App\Models\User;

it('manda la raíz al dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect('/dashboard');
});
