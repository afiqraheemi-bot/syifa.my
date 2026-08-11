document.documentElement.classList.add('js');

const toggle = document.querySelector('[data-public-menu-toggle]');
const menu = document.querySelector('[data-public-menu]');

if (toggle instanceof HTMLButtonElement && menu instanceof HTMLElement) {
    const compact = window.matchMedia('(max-width: 63.999rem)');
    const close = () => {
        toggle.setAttribute('aria-expanded', 'false');
        menu.hidden = compact.matches;
    };

    close();
    toggle.addEventListener('click', () => {
        const opening = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(opening));
        menu.hidden = !opening;
        if (opening) menu.querySelector('a')?.focus();
    });
    menu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            close();
            toggle.focus();
        }
    });
    compact.addEventListener('change', close);
}

const historyBack = document.querySelector('[data-booking-history-back]');

if (historyBack instanceof HTMLAnchorElement) {
    historyBack.addEventListener('click', (event) => {
        if (document.referrer === '' || window.history.length <= 1) return;
        event.preventDefault();
        window.history.back();
    });
}

document.querySelectorAll('[data-booking-form], [data-booking-step-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;

    form.addEventListener('submit', (event) => {
        const button =
            event.submitter instanceof HTMLButtonElement
                ? event.submitter
                : form.querySelector('[data-submit-booking], [data-booking-submit]');
        if (!(button instanceof HTMLButtonElement) || button.disabled) {
            event.preventDefault();
            return;
        }

        if (button.name !== '') {
            const action = document.createElement('input');
            action.type = 'hidden';
            action.name = button.name;
            action.value = button.value;
            form.append(action);
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent =
            button.dataset.pendingLabel || button.dataset.submitBooking || 'Submitting…';
    });
});
