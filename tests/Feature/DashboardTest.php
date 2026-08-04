<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Devuelve solo la grilla de tarjetas: todo lo que va después del encabezado
 * «Proyectos activos».
 *
 * Se aísla porque la barra lateral lista todos los proyectos a propósito —es
 * navegación y no depende del filtro— y la fila de KPI también nombra al
 * proyecto de la próxima entrega.
 */
function dashboardCards(TestCase $test, string $filter): string
{
    $content = $test->get(route('dashboard', ['filtro' => $filter]))
        ->assertOk()
        ->getContent();

    return Str::after($content, 'Proyectos activos');
}

it('lista solo los proyectos del usuario autenticado', function () {
    $user = User::factory()->create();
    $mine = Project::factory()->ready()->for($user)->create(['name' => 'App Banca Móvil']);
    $theirs = Project::factory()->ready()->create(['name' => 'Proyecto Ajeno']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($mine->name)
        ->assertDontSee($theirs->name);
});

it('calcula el avance a partir de las actividades completadas', function () {
    $user = User::factory()->create();
    $project = Project::factory()->ready()->for($user)->create();

    Activity::factory()->count(3)->completed()->for($project)->create();
    Activity::factory()->count(1)->for($project)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('75%');
});

it('muestra el aviso de malla faltante cuando el proyecto es borrador', function () {
    $user = User::factory()->create();
    Project::factory()->draft()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Falta generar la malla');
});

it('invita a crear el primero cuando no hay proyectos', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Todavía no tienes proyectos');
});

describe('cartera de demostración', function () {
    beforeEach(function () {
        $this->seed(DashboardDemoSeeder::class);
        $this->user = User::where('email', 'demo@levelup.test')->sole();
        $this->actingAs($this->user);
    });

    it('resume la cartera completa en la fila de KPI', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Avance promedio')
            ->assertSee('57')             // 24 de 42 actividades cerradas
            ->assertSee('Actividades completadas')
            ->assertSee('En ruta crítica')
            ->assertSee('Próxima entrega');
    });

    it('muestra los cinco estados de proyecto en la grilla', function () {
        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $response->assertSee('Completado')        // Portal de Clientes v2
            ->assertSee('En plazo')               // Migración ERP Finanzas
            ->assertSee('Holgura baja')           // App Banca Móvil
            ->assertSee('Atrasado 4 d')           // Rediseño Web Corporativa
            ->assertSee('Borrador');              // Lanzamiento Campaña Q4
    });

    it('filtra por proyectos en riesgo', function () {
        $cards = dashboardCards($this, 'riesgo');

        expect($cards)->toContain('Rediseño Web Corporativa')
            ->and($cards)->toContain('App Banca Móvil')
            ->and($cards)->not->toContain('Portal de Clientes v2')
            ->and($cards)->not->toContain('Migración ERP Finanzas');
    });

    it('filtra por proyectos completados', function () {
        $cards = dashboardCards($this, 'completados');

        expect($cards)->toContain('Portal de Clientes v2')
            ->and($cards)->not->toContain('Rediseño Web Corporativa');
    });

    it('ordena por fecha límite y deja al final los proyectos sin fecha', function () {
        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $response->assertSeeInOrder([
            'Portal de Clientes v2',       // 16 días atrás
            'Rediseño Web Corporativa',    // en 3 días
            'App Banca Móvil',
            'Migración ERP Finanzas',
            'Lanzamiento Campaña Q4',      // sin fecha
        ]);
    });

    it('ordena por nombre cuando se lo piden', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard', ['orden' => 'nombre']))
            ->assertOk()
            ->assertSeeInOrder([
                'App Banca Móvil',
                'Lanzamiento Campaña Q4',
                'Migración ERP Finanzas',
                'Portal de Clientes v2',
                'Rediseño Web Corporativa',
            ]);
    });

    it('ordena por avance de mayor a menor', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard', ['orden' => 'avance']))
            ->assertOk()
            ->assertSeeInOrder([
                'Portal de Clientes v2',       // 100 %
                'App Banca Móvil',             // 75 %
                'Migración ERP Finanzas',      // 43 %
                'Rediseño Web Corporativa',    // 27 %
            ]);
    });

    it('ignora filtros y órdenes inventados en vez de fallar', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard', ['filtro' => 'inventado', 'orden' => 'inventado']))
            ->assertOk()
            ->assertSee('Todos')
            ->assertSee('Ordenar: Fecha límite');
    });

    it('avisa cuando ningún proyecto calza con el filtro', function () {
        Project::query()->delete();

        Project::factory()->ready()->for($this->user)->create(['name' => 'Proyecto Tranquilo', 'deadline' => null]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['filtro' => 'completados']))
            ->assertOk()
            ->assertSee('Ningún proyecto calza');
    });

    /**
     * Str::plural aplica reglas del inglés y convertía «actividad» en
     * «actividads». Las fechas, además, salían en inglés hasta fijar el locale.
     */
    it('escribe plurales y fechas en español', function () {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('9 actividades')
            ->assertDontSee('actividads')
            ->assertSee('18 jul. 2026')      // Portal de Clientes v2
            ->assertDontSee('18 Jul 2026');
    });

    it('muestra el consumo de la cuota de IA en la barra lateral', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Consultas IA')
            ->assertSee('12 / 20');
    });
});
