import './bootstrap';

/**
 * Progressive enhancement for the public survey form.
 *
 * Everything here is optional: the form works without JavaScript. The
 * follow-up box stays visible if this file never runs, which is the safe way
 * to be wrong on a slow captive-portal phone.
 */

// 1. Reveal a follow-up box when its parent dropdown reaches a given value.
document.querySelectorAll('[data-follows]').forEach((box) => {
    const parent = document.querySelector(`[name="answers[${box.dataset.follows}]"]`);

    if (!parent) {
        return;
    }

    const match = () => parent.value === box.dataset.followsValue;

    box.hidden = !match();

    parent.addEventListener('change', () => {
        box.hidden = !match();
    });
});

// 2. Live character counter on free-text boxes, so people are not surprised
//    by a silent cut-off.
document.querySelectorAll('textarea[data-counter]').forEach((area) => {
    const counter = document.getElementById(area.dataset.counter);
    const limit = area.getAttribute('maxlength');

    if (!counter || !limit) {
        return;
    }

    const render = () => {
        const left = limit - area.value.length;

        counter.textContent = left > 40
            ? `${limit} characters maximum.`
            : `${left} characters left.`;
    };

    area.addEventListener('input', render);
    render();
});
