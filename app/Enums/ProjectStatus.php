<?php

namespace App\Enums;

/**
 * Ciclo de vida de un proyecto.
 *
 * Draft      → creado, todavía sin malla (el usuario abandonó el asistente).
 * Clarifying → la IA evalúa si el brief necesita más contexto.
 * AwaitingInput → hay preguntas persistidas que esperan respuesta del usuario.
 * Generating → job en cola llamando a Gemini; la pantalla 05 hace polling.
 * Ready      → malla generada y CPM calculado.
 * Failed     → la generación falló; el usuario puede reintentar sin gastar crédito.
 */
enum ProjectStatus: string
{
    case Draft = 'draft';
    case Clarifying = 'clarifying';
    case AwaitingInput = 'awaiting_input';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Clarifying => 'Analizando aclaraciones',
            self::AwaitingInput => 'Esperando respuestas',
            self::Generating => 'Generando',
            self::Ready => 'Listo',
            self::Failed => 'Falló la generación',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed], true);
    }

    public function needsUserInput(): bool
    {
        return $this === self::AwaitingInput;
    }
}
