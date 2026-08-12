const POLL_INTERVAL = 2000;
const MAX_BACKOFF = 15000;
const REQUEST_TIMEOUT = 8000;

function mountGenerationWatcher() {
    const watcher = document.getElementById('generation-watcher');

    if (!watcher || watcher.dataset.watcherActive !== 'true') return;

    const url = watcher.dataset.statusUrl;
    window.sessionStorage.removeItem(`levelup-awaiting-input:${url}`);
    const liveStatus = document.getElementById('generation-live-status');
    const progressMessage = document.getElementById('generation-progress-message');
    const progressBar = document.getElementById('generation-progressbar');
    const progressFill = document.getElementById('generation-progress-fill');
    const progressSteps = [...watcher.querySelectorAll('[data-generation-step]')];
    let timer = null;
    let controller = null;
    let inFlight = false;
    let stopped = false;
    let failures = 0;

    const announce = (message) => {
        if (liveStatus) liveStatus.textContent = message;
    };
    const renderProgress = (state) => {
        const message = state.message ?? '';
        const stepCount = Math.max(1, Number(state.step_count) || progressSteps.length || 5);
        const rawStep = state.step_index === null || state.step_index === undefined
            ? 0
            : Number(state.step_index);
        const stepIndex = Math.max(0, Math.min(stepCount, Number.isFinite(rawStep) ? rawStep : 0));
        const percentage = `${(stepIndex / stepCount) * 100}%`;

        if (progressMessage) progressMessage.textContent = message;
        if (progressBar) {
            progressBar.setAttribute('aria-valuemax', String(stepCount));
            progressBar.setAttribute('aria-valuenow', String(stepIndex));
            progressBar.setAttribute('aria-valuetext', message);
        }
        if (progressFill) progressFill.style.width = percentage;

        progressSteps.forEach((step) => {
            const stepNumber = Number(step.dataset.generationStep);
            const isCurrent = stepNumber === stepIndex;
            const isComplete = stepNumber <= stepIndex && stepIndex > 0;
            const dot = step.querySelector('[data-generation-step-dot]');

            step.classList.toggle('font-semibold', isCurrent);
            step.classList.toggle('text-brand-600', isCurrent);
            step.classList.toggle('text-ink-500', !isCurrent);
            step.toggleAttribute('aria-current', isCurrent);
            if (isCurrent) step.setAttribute('aria-current', 'step');

            if (dot) {
                dot.classList.toggle('border-brand-500', isComplete);
                dot.classList.toggle('bg-brand-500', isComplete);
                dot.classList.toggle('border-ink-300', !isComplete);
            }
        });
    };
    const clearTimer = () => {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    };
    const stop = () => {
        stopped = true;
        clearTimer();
        controller?.abort();
        controller = null;
    };
    const schedule = (delay) => {
        clearTimer();
        if (!stopped && !document.hidden) timer = window.setTimeout(requestStatus, delay);
    };
    const reloadForInput = () => {
        const key = `levelup-awaiting-input:${url}`;
        if (window.sessionStorage.getItem(key) === '1') return;
        window.sessionStorage.setItem(key, '1');
        announce('Hay preguntas nuevas. Cargando el formulario.');
        window.location.reload();
    };

    async function requestStatus() {
        if (stopped || inFlight || document.hidden) return;
        inFlight = true;
        controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: controller.signal,
            });

            if (response.status === 401 || response.status === 419) {
                announce('Tu sesión expiró. Recarga la página e inicia sesión nuevamente.');
                stop();
                return;
            }
            if (!response.ok) throw new Error(`status-${response.status}`);

            const state = await response.json();
            failures = 0;
            watcher.dataset.stage = state.stage ?? '';
            watcher.dataset.stepIndex = state.step_index ?? '';
            renderProgress(state);
            if (state.message) announce(state.message);

            if (state.redirect_to) {
                stop();
                window.location.href = state.redirect_to;
                return;
            }
            if (state.needs_input || state.status === 'awaiting_input') {
                stop();
                reloadForInput();
                return;
            }
            if (state.is_terminal || state.status === 'failed') {
                stop();
                window.location.reload();
                return;
            }
            schedule(POLL_INTERVAL);
        } catch (error) {
            if (error.name === 'AbortError' && document.hidden) return;
            failures += 1;
            announce('Conexión interrumpida; seguimos intentando.');
            schedule(Math.min(MAX_BACKOFF, POLL_INTERVAL * 2 ** Math.min(failures, 3)));
        } finally {
            window.clearTimeout(timeout);
            controller = null;
            inFlight = false;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearTimer();
            controller?.abort();
        } else if (!stopped) {
            requestStatus();
        }
    });
    window.addEventListener('pagehide', stop, { once: true });
    announce('Consultando el progreso…');
    requestStatus();
}

document.addEventListener('DOMContentLoaded', mountGenerationWatcher);

export { mountGenerationWatcher };
