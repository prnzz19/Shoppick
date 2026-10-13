const menu = document.querySelector('.sp-mobile-menu');
menu?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { menu.open = false; }));
document.addEventListener('keydown', event => { if (event.key === 'Escape' && menu?.open) { menu.open = false; menu.querySelector('summary').focus(); } });
matchMedia('(min-width: 1024px)').addEventListener('change', event => { if(event.matches && menu) menu.open = false; });

const revealItems = document.querySelectorAll('.sp-section-heading, .sp-promo-card, .sp-product-card, .sp-collection-tile, .sp-shop-mini, .sp-final-cta');
if (revealItems.length && !matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    revealItems.forEach(item => item.classList.add('sp-reveal'));
    const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            currentObserver.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -35px' });
    revealItems.forEach(item => observer.observe(item));
}
