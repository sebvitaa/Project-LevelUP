<div class="mx-auto flex w-full max-w-2xl flex-col gap-7 p-9">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-brand-500">Antes de armar la malla</p>
        <h1 class="mt-2 text-[27px] font-semibold leading-tight tracking-tight">Necesitamos afinar algunos detalles</h1>
        <p class="mt-2 text-sm leading-relaxed text-ink-500">
            Tus respuestas ayudan a que las actividades, duraciones y dependencias queden ajustadas al proyecto.
        </p>
    </div>

    <form method="POST" action="{{ route('projects.clarifications.store', $project) }}" class="flex flex-col gap-6">
        @csrf

        @foreach ($clarifications as $clarification)
            <fieldset class="flex flex-col gap-2.5 rounded-xl border border-ink-200 p-5">
                <legend class="sr-only">Pregunta {{ $loop->iteration }}</legend>
                <label id="clarification-question-{{ $clarification->getKey() }}" @if ($clarification->input_type !== 'select') for="clarification-{{ $clarification->getKey() }}" @endif class="text-sm font-semibold text-ink-900">{{ $clarification->question }}</label>

                @if ($clarification->rationale)
                    <p class="text-xs leading-relaxed text-ink-500">{{ $clarification->rationale }}</p>
                @endif

                @if ($clarification->input_type === 'select')
                    <div class="mt-1 flex flex-col gap-2" role="radiogroup" aria-labelledby="clarification-question-{{ $clarification->getKey() }}">
                        @foreach ($clarification->options ?? [] as $option)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-ink-200 px-3 py-2.5 text-sm text-ink-700 hover:border-brand-300">
                                <input
                                    id="clarification-{{ $clarification->getKey() }}-{{ $loop->index }}"
                                    type="radio"
                                    name="answers[{{ $clarification->getKey() }}]"
                                    value="{{ $option }}"
                                    @checked(old('answers.'.$clarification->getKey()) === $option)
                                    required
                                    class="text-brand-500 focus:ring-brand-500"
                                >
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea
                        id="clarification-{{ $clarification->getKey() }}"
                        name="answers[{{ $clarification->getKey() }}]"
                        rows="4"
                        maxlength="2000"
                        required
                        aria-describedby="clarification-question-{{ $clarification->getKey() }} @if ($errors->has('answers.'.$clarification->getKey())) clarification-error-{{ $clarification->getKey() }} @endif"
                        class="mt-1 rounded-xl border-[1.5px] border-ink-300 p-3 text-sm leading-relaxed focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100"
                    >{{ old('answers.'.$clarification->getKey()) }}</textarea>
                @endif

                @error('answers.'.$clarification->getKey())
                    <p id="clarification-error-{{ $clarification->getKey() }}" class="text-xs text-critical">{{ $message }}</p>
                @enderror
            </fieldset>
        @endforeach

        @error('answers')
            <p class="text-sm text-critical">{{ $message }}</p>
        @enderror

        <div class="flex justify-end border-t border-ink-200 pt-4">
            <button type="submit" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">
                Continuar con la generación
            </button>
        </div>
    </form>
</div>
