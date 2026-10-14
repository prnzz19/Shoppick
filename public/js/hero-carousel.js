(() => {
    const root = document.querySelector('[data-hero-carousel]');
    if (!root) return;
    window.shoppickHeroCleanup?.();

    const slides = [...root.querySelectorAll('[data-hero-slide]')];
    const dots = [...root.querySelectorAll('[data-hero-dot]')];
    if (slides.length !== 3) return;

    let index = 0;
    let timer = null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const render = (nextIndex) => {
        index = (nextIndex + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            const active = i === index;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        dots.forEach((dot, i) => {
            const active = i === index;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    };

    const stop = () => { if (timer) { window.clearInterval(timer); timer = null; } };
    const start = () => {
        stop();
        if (reducedMotion.matches) return;
        timer = window.setInterval(() => render(index + 1), 6000);
    };
    const restart = () => { start(); };

    dots.forEach((dot) => dot.addEventListener('click', () => { render(Number(dot.dataset.heroDot)); restart(); }));
    const visibilityHandler = () => { document.hidden ? stop() : start(); };
    document.addEventListener('visibilitychange', visibilityHandler);
    reducedMotion.addEventListener?.('change', () => { render(0); start(); });

    window.shoppickHeroCleanup = () => {
        stop();
        document.removeEventListener('visibilitychange', visibilityHandler);
    };
    render(0);
    start();
})();
