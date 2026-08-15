document.querySelectorAll('[data-blog-slider]').forEach((slider) => {
    if (!(slider instanceof HTMLElement)) return;
    const viewport = slider.querySelector('[data-blog-slider-viewport]');
    const previous = slider.querySelector('[data-blog-slider-previous]');
    const next = slider.querySelector('[data-blog-slider-next]');
    const status = slider.querySelector('[data-blog-slider-status]');
    const cards = [...slider.querySelectorAll('.blog-card')];
    if (
        !(viewport instanceof HTMLElement) ||
        !(previous instanceof HTMLButtonElement) ||
        !(next instanceof HTMLButtonElement)
    )
        return;

    const step = () => {
        if (cards.length > 1) return cards[1].offsetLeft - cards[0].offsetLeft;

        return cards[0]?.getBoundingClientRect().width || viewport.clientWidth;
    };
    const update = () => {
        previous.disabled = viewport.scrollLeft <= 2;
        next.disabled = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 2;
        const current = Math.min(
            cards.length,
            Math.max(1, Math.round(viewport.scrollLeft / step()) + 1),
        );
        if (status instanceof HTMLElement)
            status.textContent = `Article ${current} of ${cards.length}`;
    };
    previous.addEventListener('click', () =>
        viewport.scrollBy({ left: -step(), behavior: 'smooth' }),
    );
    next.addEventListener('click', () => viewport.scrollBy({ left: step(), behavior: 'smooth' }));
    viewport.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
});
