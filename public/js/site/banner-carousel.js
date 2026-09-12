(() => {
  const root = document.querySelector('[data-k-banner]');
  if (!root) return;

  const slides = Array.from(root.querySelectorAll('[data-k-banner-slide]'));
  const dots = Array.from(root.querySelectorAll('[data-k-banner-dot]'));
  const prevBtn = root.querySelector('[data-k-banner-prev]');
  const nextBtn = root.querySelector('[data-k-banner-next]');
  if (slides.length < 2) return;

  const intervalMs = Math.max(2000, Number(root.dataset.interval) || 5500);
  const autoplay = root.dataset.autoplay !== '0';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let index = 0;
  let timer = null;
  let hovering = false;
  let touchStartX = null;

  const show = (next) => {
    const i = ((next % slides.length) + slides.length) % slides.length;
    if (i === index) return;

    slides[index].classList.remove('is-active');
    slides[index].setAttribute('aria-hidden', 'true');
    dots[index]?.classList.remove('is-active');
    dots[index]?.setAttribute('aria-selected', 'false');

    index = i;

    slides[index].classList.add('is-active');
    slides[index].removeAttribute('aria-hidden');
    dots[index]?.classList.add('is-active');
    dots[index]?.setAttribute('aria-selected', 'true');

    const upcoming = slides[(index + 1) % slides.length]?.querySelector('img');
    if (upcoming && upcoming.loading === 'lazy') {
      upcoming.loading = 'eager';
    }
  };

  const stop = () => {
    if (timer !== null) {
      window.clearInterval(timer);
      timer = null;
    }
  };

  const start = () => {
    if (!autoplay || reduceMotion || hovering || document.hidden) return;
    stop();
    timer = window.setInterval(() => show(index + 1), intervalMs);
  };

  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      const target = Number(dot.dataset.index);
      if (Number.isFinite(target)) {
        show(target);
        start();
      }
    });
  });

  prevBtn?.addEventListener('click', () => {
    show(index - 1);
    start();
  });

  nextBtn?.addEventListener('click', () => {
    show(index + 1);
    start();
  });

  root.addEventListener('touchstart', (event) => {
    const touch = event.changedTouches?.[0];
    touchStartX = touch ? touch.clientX : null;
  }, { passive: true });

  root.addEventListener('touchend', (event) => {
    if (touchStartX === null) return;
    const touch = event.changedTouches?.[0];
    if (!touch) {
      touchStartX = null;
      return;
    }
    const delta = touch.clientX - touchStartX;
    touchStartX = null;
    if (Math.abs(delta) < 40) return;
    show(delta < 0 ? index + 1 : index - 1);
    start();
  }, { passive: true });

  root.addEventListener('mouseenter', () => {
    hovering = true;
    stop();
  });
  root.addEventListener('mouseleave', () => {
    hovering = false;
    start();
  });
  root.addEventListener('focusin', () => {
    hovering = true;
    stop();
  });
  root.addEventListener('focusout', (event) => {
    if (!root.contains(event.relatedTarget)) {
      hovering = false;
      start();
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else start();
  });

  start();
})();
