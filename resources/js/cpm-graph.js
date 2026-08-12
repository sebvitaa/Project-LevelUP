function centerSelectedActivity() {
    const viewport = document.getElementById('cpm-scroll-viewport');
    const code = viewport?.dataset.selectedActivity;
    const activity = code ? document.getElementById(`activity-${code}`) : null;
    if (!viewport || !activity) return;

    const smooth = !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    viewport.scrollTo({
        left: Math.max(0, activity.offsetLeft - (viewport.clientWidth - activity.offsetWidth) / 2),
        top: Math.max(0, activity.offsetTop - (viewport.clientHeight - activity.offsetHeight) / 2),
        behavior: smooth ? 'smooth' : 'auto',
    });
}

document.addEventListener('DOMContentLoaded', centerSelectedActivity);

export { centerSelectedActivity };
