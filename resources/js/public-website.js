import '../css/public-website.css';

document.documentElement.classList.add('js');

const toggle = document.querySelector('[data-public-menu-toggle]');
const menu = document.querySelector('[data-public-menu]');

if (toggle instanceof HTMLButtonElement && menu instanceof HTMLElement) {
    const compactNavigation = window.matchMedia('(max-width: 63.999rem)');
    const close = () => {
        toggle.setAttribute('aria-expanded', 'false');
        menu.hidden = compactNavigation.matches;
    };

    close();

    toggle.addEventListener('click', () => {
        const opening = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(opening));
        menu.hidden = !opening;
        if (opening) {
            menu.querySelector('a')?.focus();
        }
    });

    menu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            close();
            toggle.focus();
        }
    });

    compactNavigation.addEventListener('change', close);
}

const bookingForm = document.querySelector('[data-booking-form]');
if (bookingForm instanceof HTMLFormElement) {
    bookingForm.addEventListener('submit', (event) => {
        const submitButton = bookingForm.querySelector('[data-submit-booking]');
        if (!(submitButton instanceof HTMLButtonElement) || submitButton.disabled) {
            event.preventDefault();

            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Submitting…';
    });
}
