/* ============================================================
   NEWCOMER CONNECT  Premium JS 2026
   Features: Sticky Nav | Mobile Menu | Scroll Reveal 
             | Counters | Sliders | FAQ | Form
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initMobileMenu();
  initScrollReveal();
  initSliders();
  initCounters();
  initFAQ();
  initContactForm();
  initUnifiedFooter();
});

/*  STICKY NAVBAR  */
function initNav() {
  const header = document.getElementById('mainHeader');
  if (!header) return;
  const onScroll = () => {
    header.classList.toggle('scrolled', window.scrollY > 60);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

/*  MOBILE MENU  */
function initMobileMenu() {
  const toggle = document.getElementById('navToggle');
  const close  = document.getElementById('navClose');
  const menu   = document.getElementById('navMenu');
  if (!toggle || !menu) return;

  const open  = () => { menu.classList.add('open');  document.body.style.overflow = 'hidden'; };
  const shut  = () => { menu.classList.remove('open'); document.body.style.overflow = ''; };

  toggle.addEventListener('click', open);
  if (close) close.addEventListener('click', shut);

  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', shut));

  menu.addEventListener('click', e => {
    if (e.target === menu) shut();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') shut();
  });
}

/*  SCROLL REVEAL  */
function initScrollReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  els.forEach(el => observer.observe(el));
}

/*  ANIMATED COUNTERS  */
function initCounters() {
  const nums = document.querySelectorAll('.stat-num[data-count]');
  if (!nums.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      const el  = e.target;
      const end = parseInt(el.getAttribute('data-count'), 10);
      const suffix = el.textContent.replace(/[0-9]/g, '').trim();
      let start = 0;
      const duration = 1800;
      const step = Math.ceil(end / (duration / 16));
      const tick = () => {
        start = Math.min(start + step, end);
        el.textContent = start + (end >= 25 && end <= 500 ? '+' : '');
        if (start < end) requestAnimationFrame(tick);
      };
      tick();
      observer.unobserve(el);
    });
  }, { threshold: 0.5 });
  nums.forEach(n => observer.observe(n));
}

/*  SLIDERS  */
function initSliders() {
  document.querySelectorAll('.slider[data-slider]').forEach(slider => {
    const track = slider.querySelector('.slides');
    const dots  = slider.querySelectorAll('.slider-dot');
    const prev  = slider.querySelector('.slider-control.prev');
    const next  = slider.querySelector('.slider-control.next');
    if (!track) return;

    const total   = slider.querySelectorAll('.slide').length;
    let current   = 0;
    let autoInterval = null;
    const delay = parseInt(slider.getAttribute('data-autoplay') || '4500', 10);

    const goTo = (idx) => {
      current = (idx + total) % total;
      track.style.transform = `translateX(-${current * 100}%)`;
      dots.forEach((d, i) => d.classList.toggle('active', i === current));
    };

    if (prev) prev.addEventListener('click', () => { goTo(current - 1); resetAuto(); });
    if (next) next.addEventListener('click', () => { goTo(current + 1); resetAuto(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); resetAuto(); }));

    const startAuto = () => { autoInterval = setInterval(() => goTo(current + 1), delay); };
    const stopAuto  = () => clearInterval(autoInterval);
    const resetAuto = () => { stopAuto(); startAuto(); };

    slider.addEventListener('mouseenter', stopAuto);
    slider.addEventListener('mouseleave', startAuto);

    // Touch support
    let touchStartX = 0;
    slider.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', e => {
      const diff = touchStartX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 50) { goTo(diff > 0 ? current + 1 : current - 1); resetAuto(); }
    });

    startAuto();
    goTo(0);
  });
}

/*  FAQ ACCORDION  */
function initFAQ() {
  document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq-item');
      const isOpen = item.classList.contains('active');
      // Close all
      document.querySelectorAll('.faq-item.active').forEach(i => i.classList.remove('active'));
      if (!isOpen) item.classList.add('active');
    });
  });
}

/*  CONTACT FORM  */
function initContactForm() {
  const form = document.getElementById('contactForm');
  const success = document.getElementById('formSuccess');
  if (!form) return;
  form.addEventListener('submit', e => {
    e.preventDefault();
    form.style.display = 'none';
    if (success) success.style.display = 'block';
  });
}

/*  UNIFIED FOOTER  */
function initUnifiedFooter() {
  const footer = document.querySelector('footer');
  if (!footer) return;

  const ctaBand = document.querySelector('.cta-band');
  if (ctaBand) ctaBand.remove();

  footer.classList.add('footer-modern');
  footer.innerHTML = `
    <div class="container">
      <div class="footer-modern-wrap">
        <a href="index.html" class="footer-modern-logo" aria-label="Newcomer Connect">
          <img src="https://ghostwhite-hornet-258100.hostingersite.com/wp-content/themes/betheme/images/cookies.png" alt="Newcomer Connect">
          <span>new<br>comer<br>connect</span>
        </a>
        <h2 class="footer-modern-title">Ready to Start Your Canadian Journey?</h2>

        <div class="footer-modern-contacts">
          <a href="mailto:noreply@newcomer.com" class="footer-pill">noreply@newcomer.com</a>
          <a href="tel:+610383766284" class="footer-pill">+61 (0) 383 766 284</a>
        </div>

        <div class="footer-modern-line" aria-hidden="true"></div>

        <div class="footer-modern-social">
          <a href="#" class="footer-social-pill">Behance</a>
          <a href="#" class="footer-social-pill">Facebook</a>
          <a href="#" class="footer-social-pill">Twitter</a>
          <a href="#" class="footer-social-pill">Dribbble</a>
          <a href="#" class="footer-social-pill">Instagram</a>
        </div>

        <div class="footer-modern-line" aria-hidden="true"></div>

        <p class="footer-modern-copy">&copy; 2026 Newcomer Connect | All Rights Reserved</p>
      </div>
    </div>
  `;
}
