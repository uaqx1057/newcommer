/* ============================================================
   NEWCOMER CONNECT  Premium JS 2026
   Features: Sticky Nav | Mobile Menu | Scroll Reveal 
             | Counters | Sliders | FAQ | Form
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();
  initNav();
  initMobileMenu();
  initScrollReveal();
  initSliders();
  initCounters();
  initFAQ();
  initContactForm();
  initUnifiedFooter();
});

/*  THEME TOGGLE  */
function initThemeToggle() {
  const root = document.documentElement;
  const switcher = document.getElementById('themeSwitcher');
  const savedTheme = localStorage.getItem('theme');

  if (savedTheme === 'dark') {
    root.setAttribute('data-theme', 'dark');
  } else {
    root.removeAttribute('data-theme');
  }

  applyThemeLogoAssets();

  if (!switcher) return;

  if (!switcher.querySelector('ion-icon')) {
    switcher.innerHTML = `<ion-icon class="theme-switcher-icon" aria-hidden="true"></ion-icon>`;
  }
  const icon = switcher.querySelector('ion-icon');

  const setToggleState = () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    switcher.setAttribute('aria-pressed', String(isDark));
    switcher.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    if (icon) {
      icon.setAttribute('name', isDark ? 'sunny-outline' : 'moon-outline');
    }
    applyThemeLogoAssets();
  };

  setToggleState();

  switcher.addEventListener('click', () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    if (isDark) {
      root.removeAttribute('data-theme');
      localStorage.removeItem('theme');
    } else {
      root.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
    }
    setToggleState();
  });
}

function applyThemeLogoAssets() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const logoSrc = isDark ? 'assets/icons/logodark.png' : 'assets/icons/logolight.png';

  document.querySelectorAll('.logo img, .footer-modern-logo img').forEach((img) => {
    img.src = logoSrc;
    img.alt = 'Newcomer Connect';
  });
}

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
          <img src="assets/icons/logo.svg" alt="Newcomer Connect">
          <span>Newcomer<br>Connect</span>
        </a>
        <h2 class="footer-modern-title">Ready to Start Your Canadian Journey?</h2>

        <div class="footer-modern-contacts">
          <a href="mailto:info@newcomerconnect.ca" class="footer-pill">info@newcomerconnect.ca</a>
          <a href="tel:+12893000321" class="footer-pill">Canada: +1-289-300-0321</a>
          <a href="tel:+923370222232" class="footer-pill">Pakistan: +92-337-022-2232</a>
          <a href="https://maps.google.com/?q=50+Steeles+Ave+East,+Unit+209,+Milton,+Ontario,+L9T+4W9,+Canada" class="footer-pill" target="_blank" rel="noopener">Canada Office: 50 Steeles Ave East, Unit 209, Milton, ON</a>
          <a href="https://maps.google.com/?q=Suite+35,+Moti+Bazar+Mall,+Safari+Villas+1,+Bahria+Town,+Rawalpindi,+Pakistan" class="footer-pill" target="_blank" rel="noopener">Pakistan Office: Suite#35, Moti Bazar Mall, Bahria Town, Rawalpindi</a>
        </div>

        <div class="footer-modern-line" aria-hidden="true"></div>

        <div class="footer-modern-social">
          <a href="services.html" class="footer-social-pill">Services</a>
          <a href="team.html" class="footer-social-pill">Our Team</a>
          <a href="faq.html" class="footer-social-pill">FAQ</a>
          <a href="contact.html" class="footer-social-pill">Book Consultation</a>
        </div>

        <div class="footer-modern-line" aria-hidden="true"></div>

        <p class="footer-modern-copy">&copy; 2026 Newcomer Connect | All Rights Reserved | Website by <a href="https://codewithusman.com" target="_blank" rel="noopener">codewithusman.com</a></p>
      </div>
    </div>
  `;

  applyThemeLogoAssets();
}
