<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class StoreProjectClarificationAnswersRequest extends FormRequest
{
    /**
     * @var Collection<int, \App\Models\ProjectClarification>|null
     */
    private ?Collection $pending = null;

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('update', $project) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $pending = $this->pendingClarifications();
        $rules = [
            'answers' => ['required', 'array', 'size:'.$pending->count()],
        ];

        foreach ($pending as $clarification) {
            $answerRules = ['required', 'string', 'min:1', 'max:2000'];

            if ($clarification->input_type === 'select') {
                $answerRules[] = Rule::in($clarification->options ?? []);
            }

            $rules['answers.'.$clarification->getKey()] = $answerRules;
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers');

        if (is_array($answers)) {
            $this->merge([
                'answers' => array_map(
                    static fn (mixed $answer): mixed => is_string($answer) ? trim($answer) : $answer,
                    $answers,
                ),
            ]);
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $submitted = $this->input('answers', []);

            if (! is_array($submitted)) {
                return;
            }

            $expectedKeys = array_map('strval', $this->pendingClarifications()->modelKeys());
            $submittedKeys = array_map('strval', array_keys($submitted));
            sort($expectedKeys);
            sort($submittedKeys);

            if ($expectedKeys !== $submittedKeys) {
                $validator->errors()->add(
                    'answers',
                    'Debes responder exactamente todas las preguntas pendientes de esta ronda.',
                );
            }
        }];
    }

    /**
     * @return Collection<int, \App\Models\ProjectClarification>
     */
    private function pendingClarifications(): Collection
    {
        return $this->pending ??= $this->project()->pendingClarifications()->get();
    }

    private function project(): Project
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project;
    }
}
