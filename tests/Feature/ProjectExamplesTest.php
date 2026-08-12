<?php

use App\Enums\AiModel;
use App\Enums\ProjectType;
use App\Http\Requests\StoreProjectRequest;
use App\Models\User;
use App\Services\ProjectExamples;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

it('entrega tres ejemplos para cada tipo de proyecto', function (ProjectType $type) {
    $examples = (new ProjectExamples)->forType($type);

    expect($examples)->toHaveCount(3);

    foreach ($examples as $example) {
        expect($example)->toHaveKeys(['name', 'prompt', 'starts_on', 'deadline', 'team_size']);
    }
})->with(ProjectType::cases());

it('entrega ejemplos distintos entre sí dentro de un mismo tipo', function (ProjectType $type) {
    $prompts = array_column((new ProjectExamples)->forType($type), 'prompt');

    expect(array_unique($prompts))->toHaveCount(3);
})->with(ProjectType::cases());

/**
 * Si un ejemplo no pasa las reglas del formulario, el botón deja al usuario
 * con un error de validación que él no escribió.
 */
it('entrega ejemplos que pasan la validación del formulario', function (ProjectType $type) {
    $request = StoreProjectRequest::create(route('projects.store'), 'POST');
    $request->setUserResolver(fn (): User => User::factory()->make());

    $rules = $request->rules();

    foreach ((new ProjectExamples)->forType($type) as $example) {
        $validator = Validator::make([
            ...$example,
            'type' => $type->value,
            'ai_model' => AiModel::Standard->value,
        ], $rules);

        expect($validator->errors()->all())->toBe([], "Ejemplo [{$example['name']}] inválido");
    }
})->with(ProjectType::cases());

it('resuelve las fechas relativas al día de hoy, nunca en el pasado', function () {
    Carbon::setTestNow('2027-03-15');

    foreach (ProjectType::cases() as $type) {
        foreach ((new ProjectExamples)->forType($type) as $example) {
            expect($example['starts_on'])->toBeGreaterThanOrEqual('2027-03-15')
                ->and($example['deadline'])->toBeGreaterThan($example['starts_on']);
        }
    }
});

it('manda al formulario los ejemplos del tipo elegido', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['project_wizard.type' => ProjectType::Construction->value])
        ->get(route('projects.create.prompt'))
        ->assertOk()
        ->assertSee('Probar con un ejemplo')
        ->assertSee('Edificio Los Aromos', escape: false)
        ->assertViewHas('examples', fn (array $examples): bool => count($examples) === 3);
});

/**
 * El JSON viaja dentro de un atributo HTML y lo parsea el módulo JS. Si el
 * escapado se rompe, el botón queda muerto sin ningún error visible.
 */
it('deja los ejemplos parseables dentro del atributo data-examples', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->withSession(['project_wizard.type' => ProjectType::Event->value])
        ->get(route('projects.create.prompt'))
        ->getContent();

    expect(preg_match('/data-examples="([^"]*)"/', $html, $matches))->toBe(1);

    $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), associative: true);

    expect($decoded)->toBeArray()
        ->toHaveCount(3)
        ->and($decoded[0]['name'])->toBe('Titulación 2026')
        ->and($decoded[0]['team_size'])->toBe(4);
});
