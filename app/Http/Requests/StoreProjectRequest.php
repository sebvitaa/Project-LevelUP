<?php

namespace App\Http\Requests;

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
        ];
    }
}
