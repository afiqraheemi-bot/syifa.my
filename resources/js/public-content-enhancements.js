document.querySelectorAll('[data-gallery-dialog]').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) return;

    const image = dialog.querySelector('[data-gallery-dialog-image]');
    const caption = dialog.querySelector('[data-gallery-dialog-caption]');
    const closeButton = dialog.querySelector('[data-gallery-close]');
    if (!(image instanceof HTMLImageElement) || !(caption instanceof HTMLElement)) return;

    const gallery = dialog.closest('#gallery');
    if (!(gallery instanceof HTMLElement)) return;

    const close = () => dialog.close();
    gallery.querySelectorAll('[data-gallery-open]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        button.addEventListener('click', () => {
            image.src = button.dataset.gallerySrc ?? '';
            image.alt = button.dataset.galleryAlt ?? '';
            caption.textContent = button.dataset.galleryCaption ?? '';
            caption.hidden = caption.textContent === '';
            dialog.showModal();
        });
    });
    closeButton?.addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
});

document.querySelectorAll('[data-blog-share]').forEach((share) => {
    const copyButton = share.querySelector('[data-blog-copy-link]');
    const status = share.querySelector('[data-blog-share-status]');
    if (!(copyButton instanceof HTMLButtonElement) || !(status instanceof HTMLElement)) return;

    copyButton.addEventListener('click', async () => {
        const url = copyButton.dataset.shareUrl ?? window.location.href;

        try {
            await navigator.clipboard.writeText(url);
        } catch {
            const input = document.createElement('textarea');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.append(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        }

        copyButton.textContent = 'Link copied';
        status.textContent = 'Article link copied successfully.';
        window.setTimeout(() => {
            copyButton.textContent = 'Copy link';
            status.textContent = '';
        }, 2500);
    });
});

if (window.matchMedia('(min-width: 64rem) and (prefers-reduced-motion: no-preference)').matches) {
    const specialistHero = document.querySelector("[data-template='syifa-specialist'] .hero");
    const content = specialistHero?.querySelector('.hero__content');
    const media = specialistHero?.querySelector('.hero__media');

    [...(content?.children ?? []), media].filter(Boolean).forEach((element, index) => {
        element.animate(
            [
                { opacity: 0, transform: 'translateY(12px)' },
                { opacity: 1, transform: 'translateY(0)' },
            ],
            {
                duration: 600,
                delay: Math.min(index * 70, 210),
                easing: 'cubic-bezier(0.2, 0, 0, 1)',
                fill: 'backwards',
            },
        );
    });
}
