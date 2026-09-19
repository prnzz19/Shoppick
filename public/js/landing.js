const menu = document.querySelector('.sp-mobile-menu');
menu?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { menu.open = false; }));
document.addEventListener('keydown', event => { if (event.key === 'Escape' && menu?.open) { menu.open = false; menu.querySelector('summary').focus(); } });
matchMedia('(min-width: 1024px)').addEventListener('change', event => { if(event.matches && menu) menu.open = false; });
