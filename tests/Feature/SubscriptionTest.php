<?php

use App\Enums\AiModel;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\SubscriptionPlan;
use App\Jobs\GenerateProjectClarifications;
use App\Models\Project;
use App\Models\User;
use App\Services\Ai\ProjectClarificationGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return array<string, mixed>
 */
function projectPayload(array $overrides = []): array
{
    return [
        'name' => 'App Banca Móvil',
        'type' => ProjectType::Software->value,
        'prompt' => 'Necesito lanzar la app móvil de banca personal para iOS y Android, desde requerimientos hasta publicación.',
        'starts_on' => '2026-09-01',
        'deadline' => '2026-12-01',
        'team_size' => 5,
        ...$overrides,
    ];
}

it('parte en el plan gratis, sin acceso al modelo avanzado', function () {
    $user = User::factory()->create();

    expect($user->currentPlan())->toBe(SubscriptionPlan::Free)
        ->and($user->isOnProPlan())->toBeFalse()
        ->and($user->canUseAiModel(AiModel::Standard))->toBeTrue()
        ->and($user->canUseAiModel(AiModel::Advanced))->toBeFalse();
});

it('muestra el modelo avanzado bloqueado a quien está en el plan gratis', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['project_wizard.type' => ProjectType::Software->value])
        ->get(route('projects.create.prompt'))
        ->assertOk()
        ->assertSee('Avanzado')
        ->assertSee('Requiere el plan Pro')
        ->assertSee('disabled', escape: false);
});

it('no bloquea el modelo avanzado a quien tiene el plan Pro', function () {
    $this->actingAs(User::factory()->pro()->create())
        ->withSession(['project_wizard.type' => ProjectType::Software->value])
        ->get(route('projects.create.prompt'))
        ->assertOk()
        ->assertSee('Avanzado')
        ->assertDontSee('Requiere el plan Pro');
});

it('contratar el plan activa Pro, sube la cuota y fija el vencimiento', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account.plan.store'))
        ->assertRedirect(route('account.plan'));

    $user->refresh();

    expect($user->plan)->toBe(SubscriptionPlan::Pro)
        ->and($user->isOnProPlan())->toBeTrue()
        ->and($user->ai_credits_limit)->toBe(SubscriptionPlan::Pro->monthlyCredits())
        ->and($user->plan_expires_at->isSameDay(now()->addDays(SubscriptionPlan::PRO_PERIOD_DAYS)))->toBeTrue();
});

it('renovar extiende desde el vencimiento vigente y no desde hoy', function () {
    $user = User::factory()->pro()->create();
    $originalExpiry = $user->plan_expires_at;

    $this->actingAs($user)->post(route('account.plan.store'));

    expect($user->refresh()->plan_expires_at->isSameDay(
        $originalExpiry->copy()->addDays(SubscriptionPlan::PRO_PERIOD_DAYS)
    ))->toBeTrue();
});

it('cancelar devuelve al plan gratis y baja la cuota', function () {
    $user = User::factory()->pro()->create();

    $this->actingAs($user)
        ->delete(route('account.plan'))
        ->assertRedirect(route('account.plan'));

    $user->refresh();

    expect($user->plan)->toBe(SubscriptionPlan::Free)
        ->and($user->plan_expires_at)->toBeNull()
        ->and($user->ai_credits_limit)->toBe(SubscriptionPlan::Free->monthlyCredits());
});

it('trata una contratación vencida como plan gratis', function () {
    $user = User::factory()->expiredPro()->create();

    expect($user->plan)->toBe(SubscriptionPlan::Pro)
        ->and($user->planHasExpired())->toBeTrue()
        ->and($user->currentPlan())->toBe(SubscriptionPlan::Free)
        ->and($user->canUseAiModel(AiModel::Advanced))->toBeFalse();
});

/**
 * El candado de la interfaz es cosmético: lo que importa es que el POST se
 * rechace aunque el usuario mande el campo a mano.
 */
it('rechaza el modelo avanzado si el plan no lo incluye', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), projectPayload(['ai_model' => AiModel::Advanced->value]))
        ->assertSessionHasErrors('ai_model');

    expect(Project::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rechaza el modelo avanzado cuando la contratación venció', function () {
    Queue::fake();

    $this->actingAs(User::factory()->expiredPro()->create())
        ->post(route('projects.store'), projectPayload(['ai_model' => AiModel::Advanced->value]))
        ->assertSessionHasErrors('ai_model');

    expect(Project::count())->toBe(0);
});

it('guarda el modelo avanzado en el proyecto de un usuario Pro', function () {
    Queue::fake();

    $this->actingAs(User::factory()->pro()->create())
        ->post(route('projects.store'), projectPayload(['ai_model' => AiModel::Advanced->value]))
        ->assertRedirect();

    expect(Project::sole()->ai_model)->toBe(AiModel::Advanced);

    Queue::assertPushed(GenerateProjectClarifications::class);
});

it('asume el modelo estándar si el formulario no manda el campo', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), projectPayload())
        ->assertRedirect();

    expect(Project::sole()->ai_model)->toBe(AiModel::Standard);
});

it('llama al modelo de Gemini que corresponde al del proyecto', function () {
    $user = User::factory()->pro()->create();
    $project = Project::factory()
        ->clarifying()
        ->for($user)
        ->create(['ai_model' => AiModel::Advanced]);

    Http::fake(['*' => Http::response([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'needs_clarification' => false,
                'questions' => [],
            ])]]],
        ]],
    ])]);

    (new GenerateProjectClarifications($project->getKey(), $project->generation_attempt))
        ->handle(app(ProjectClarificationGenerator::class));

    Http::assertSent(
        fn ($request): bool => str_contains($request->url(), AiModel::Advanced->geminiModel())
    );
});

/**
 * Regenerar es una generación nueva, así que no puede seguir usando un modelo
 * que el plan ya no cubre.
 */
it('baja el proyecto al modelo estándar al regenerar con el plan vencido', function () {
    Queue::fake();

    $user = User::factory()->expiredPro()->create();
    $project = Project::factory()
        ->failed()
        ->for($user)
        ->create(['ai_model' => AiModel::Advanced, 'generation_attempt' => 1]);

    $this->actingAs($user)
        ->post(route('projects.regenerate', $project))
        ->assertRedirect();

    expect($project->refresh()->ai_model)->toBe(AiModel::Standard)
        ->and($project->status)->toBe(ProjectStatus::Clarifying);
});

it('mantiene el modelo avanzado al regenerar con el plan vigente', function () {
    Queue::fake();

    $user = User::factory()->pro()->create();
    $project = Project::factory()
        ->failed()
        ->for($user)
        ->create(['ai_model' => AiModel::Advanced, 'generation_attempt' => 1]);

    $this->actingAs($user)->post(route('projects.regenerate', $project));

    expect($project->refresh()->ai_model)->toBe(AiModel::Advanced);
});

it('muestra la pantalla de plan con ambos planes y el aviso de cobro simulado', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('account.plan'))
        ->assertOk()
        ->assertSee('US$10')
        ->assertSee('Contratar por US$10')
        ->assertSee('No hay pasarela de pago conectada', escape: false)
        ->assertViewHas('currentPlan', SubscriptionPlan::Free);
});

it('exige sesión iniciada para ver o contratar el plan', function () {
    $this->get(route('account.plan'))->assertRedirect(route('login'));
    $this->post(route('account.plan.store'))->assertRedirect(route('login'));
});
