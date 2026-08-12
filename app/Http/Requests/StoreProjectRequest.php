<?php

namespace App\Http\Requests;

use App\Enums\AiModel;
use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * Un formulario sin el campo equivale a pedir el modelo estándar, que está
     * incluido en todos los planes.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('ai_model')) {
            $this->merge(['ai_model' => AiModel::Standard->value]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'prompt' => ['required', 'string', 'min:40', 'max:4000'],
            'starts_on' => ['required', 'date'],
            'deadline' => ['nullable', 'date', 'after:starts_on'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:500'],

            /*
             * El candado de la interfaz es cosmético: acá se rechaza de verdad un
             * modelo que el plan del usuario no habilita.
             */
            'ai_model' => [
                'required',
                Rule::enum(AiModel::class)->only($this->user()->currentPlan()->models()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre al proyecto.',
            'prompt.required' => 'Describe el proyecto para que la IA pueda armar la malla.',
            'prompt.min' => 'Cuéntanos un poco más: con menos de 40 caracteres la malla queda muy pobre.',
            'deadline.after' => 'La fecha límite tiene que ser posterior al inicio.',
            'ai_model.enum' => 'Ese modelo no está incluido en tu plan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del proyecto',
            'prompt' => 'descripción',
            'starts_on' => 'fecha de inicio',
            'deadline' => 'fecha límite',
            'team_size' => 'tamaño del equipo',
            'ai_model' => 'modelo de IA',
        ];
    }
}
