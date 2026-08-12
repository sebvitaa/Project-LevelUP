/**
 * Botón «Probar con un ejemplo» de la pantalla 04.
 *
 * Rellena nombre, descripción, fechas y tamaño de equipo con uno de los tres
 * briefs que el servidor manda para el tipo de proyecto elegido. El botón nace
 * oculto en el Blade y se muestra acá, así no queda un botón muerto si el
 * bundle no cargó.
 */
function mountPromptExamples() {
    const root = document.getElementById('prompt-examples');

    if (!root) return;

    let examples;

    try {
        examples = JSON.parse(root.dataset.examples ?? '[]');
    } catch {
        return;
    }

    if (!Array.isArray(examples) || examples.length === 0) return;

    const trigger = root.querySelector('[data-example-trigger]');
    const status = root.querySelector('[data-example-status]');
    const form = root.closest('form');

    if (!trigger || !form) return;

    const fields = {
        name: form.querySelector('#name'),
        prompt: form.querySelector('#prompt'),
        starts_on: form.querySelector('#starts_on'),
        deadline: form.querySelector('#deadline'),
        team_size: form.querySelector('#team_size'),
    };

    let lastIndex = -1;

    /** Índice al azar, evitando repetir el ejemplo recién cargado. */
    const pickIndex = () => {
        if (examples.length === 1) return 0;

        let index = lastIndex;

        while (index === lastIndex) {
            index = Math.floor(Math.random() * examples.length);
        }

        return index;
    };

    const applyExample = () => {
        const index = pickIndex();
        const example = examples[index];
        lastIndex = index;

        Object.entries(fields).forEach(([key, field]) => {
            if (!field || example[key] === undefined || example[key] === null) return;

            field.value = String(example[key]);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });

        if (status) {
            status.textContent = `Ejemplo cargado: ${example.name}`;
        }

        fields.prompt?.focus({ preventScroll: true });
    };

    trigger.classList.remove('hidden');
    trigger.addEventListener('click', applyExample);
}

document.addEventListener('DOMContentLoaded', mountPromptExamples);

export { mountPromptExamples };
