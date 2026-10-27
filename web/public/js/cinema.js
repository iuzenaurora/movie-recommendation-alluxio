document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) window.lucide.createIcons();
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    document.querySelectorAll('[data-shelf]').forEach(shelf => {
        const rail = shelf.querySelector('[data-rail]');
        const previous = shelf.querySelector('[data-shelf-prev]');
        const next = shelf.querySelector('[data-shelf-next]');
        const update = () => {
            previous.disabled = rail.scrollLeft <= 2;
            next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2;
        };
        const move = direction => rail.scrollBy({left: direction * rail.clientWidth * .8, behavior: reducedMotion.matches ? 'instant' : 'smooth'});
        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        rail.addEventListener('scroll', update, {passive: true});
        rail.addEventListener('keydown', event => {
            if (event.target !== rail || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            move(event.key === 'ArrowRight' ? 1 : -1);
        });
        window.addEventListener('resize', update);
        update();
    });
    document.querySelectorAll('[data-spotlight]').forEach(hero => {
        const slides = [...hero.querySelectorAll('[data-slide]')];
        const dots = [...hero.querySelectorAll('[data-hero-dot]')];
        const pause = hero.querySelector('[data-hero-pause]');
        if (slides.length < 2) return;
        let current = 0;
        let paused = false;
        let hovering = false;
        let timer;
        const show = index => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => {
                slide.hidden = i !== current;
                slide.inert = i !== current;
                slide.classList.toggle('is-active', i === current);
            });
            dots.forEach((dot, i) => {
                dot.classList.toggle('is-active', i === current);
                dot.setAttribute('aria-pressed', String(i === current));
            });
        };
        const schedule = () => {
            clearInterval(timer);
            if (paused || hovering || document.hidden || reducedMotion.matches || hero.contains(document.activeElement)) return;
            timer = setInterval(() => show(current + 1), 7000);
        };
        const select = index => { show(index); schedule(); };
        hero.querySelector('[data-hero-prev]').addEventListener('click', () => select(current - 1));
        hero.querySelector('[data-hero-next]').addEventListener('click', () => select(current + 1));
        dots.forEach((dot, index) => dot.addEventListener('click', () => select(index)));
        pause.addEventListener('click', () => {
            paused = !paused;
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? 'Tiếp tục chuyển phim' : 'Tạm dừng chuyển phim');
            pause.textContent = paused ? '▶' : 'Ⅱ';
            schedule();
        });
        hero.addEventListener('pointerenter', () => { hovering = true; schedule(); });
        hero.addEventListener('pointerleave', () => { hovering = false; schedule(); });
        hero.addEventListener('focusin', schedule);
        hero.addEventListener('focusout', () => setTimeout(schedule, 0));
        document.addEventListener('visibilitychange', schedule);
        reducedMotion.addEventListener('change', schedule);
        schedule();
    });
});
