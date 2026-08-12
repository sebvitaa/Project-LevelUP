<?php

namespace App\Services\Ai;

use App\Enums\AiModel;
use App\Exceptions\PlanGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Cliente HTTP de la API de Google Gemini.
 *
 * Es deliberadamente delgado: solo sabe hablar HTTP y devolver el JSON ya
 * decodificado. Interpretar ese JSON como un plan de proyecto es tarea de
 * ProjectPlanGenerator, así el cliente se puede falsear en los tests con
 * Http::fake() sin arrastrar lógica de dominio.
 */
class GeminiClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    /**
     * Genera contenido estructurado y devuelve el JSON decodificado.
     *
     * El modelo se recibe por llamada y no por constructor porque depende del
     * plan del usuario dueño del proyecto, que cambia entre una generación y la
     * siguiente.
     *
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     *
     * @throws PlanGenerationException
     */
    public function generateJson(
        AiModel $model,
        string $systemInstruction,
        string $userPrompt,
        array $responseSchema,
    ): array {
        $endpoint = "{$this->baseUrl}/models/{$model->geminiModel()}:generateContent";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->retry(2, 500, throw: false)
                ->post($endpoint, [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $userPrompt]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $responseSchema,
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw PlanGenerationException::apiUnavailable($e->getMessage());
        }

        if ($response->failed()) {
            throw PlanGenerationException::apiUnavailable(
                'la API respondió '.$response->status().
                ' — '.$response->json('error.message', $response->body())
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw PlanGenerationException::invalidResponse('la respuesta vino vacía');
        }

        try {
            $decoded = json_decode($text, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw PlanGenerationException::invalidResponse($e->getMessage());
        }

        if (! is_array($decoded)) {
            throw PlanGenerationException::invalidResponse('el JSON no es un objeto');
        }

        return $decoded;
    }
}
