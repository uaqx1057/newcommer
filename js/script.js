/* ============================================================
   NEWCOMER CONNECT  Premium JS 2026
   Features: Sticky Nav | Mobile Menu | Scroll Reveal 
             | Counters | Sliders | FAQ | Form
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  syncThemeState();
  initThemeToggle();
  initNav();
  initMobileMenu();
  initScrollReveal();
  initDeferredImages();
  initSliders();
  initDeferredVideos();
  initCounters();
  initFAQ();
  initContactForm();
  initContactFormSwitcher();
  initReviewForms();
  scheduleNonCriticalWork(() => {
    initHeroLeafRain();
    initParallaxMedia();
    initGalleryAutoScroll();
    scheduleApprovedReviews();
  }, 1200);
});

window.addEventListener('pageshow', () => {
  syncThemeState();
});

function getStoredTheme() {
  try {
    return localStorage.getItem('theme');
  } catch (error) {
    return null;
  }
}

function scheduleNonCriticalWork(callback, timeout = 800) {
  if (typeof callback !== 'function') return;

  if ('requestIdleCallback' in window) {
    window.requestIdleCallback(() => callback(), { timeout });
    return;
  }

  window.setTimeout(callback, Math.min(timeout, 300));
}

function syncThemeState() {
  const root = document.documentElement;
  const savedTheme = getStoredTheme();

  if (savedTheme === 'dark') {
    root.setAttribute('data-theme', 'dark');
  } else {
    root.removeAttribute('data-theme');
  }

  applyThemeLogoAssets();
}

/*  THEME TOGGLE  */
function initThemeToggle() {
  const root = document.documentElement;
  const switcher = document.getElementById('themeSwitcher');
  syncThemeState();

  if (!switcher) return;

  const moonIconMarkup = '<svg viewBox="0 0 24 24" class="theme-switcher-glyph" aria-hidden="true" focusable="false"><path d="M20.5 15.3A8.5 8.5 0 0 1 8.7 3.5a9 9 0 1 0 11.8 11.8Z" fill="currentColor"/></svg>';
  const sunIconMarkup = '<svg viewBox="0 0 24 24" class="theme-switcher-glyph" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.25" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 2.5v2.5M12 19v2.5M21.5 12H19M5 12H2.5M18.7 5.3l-1.8 1.8M7.1 16.9l-1.8 1.8M18.7 18.7l-1.8-1.8M7.1 7.1 5.3 5.3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/></svg>';

  let iconHost = switcher.querySelector('.theme-switcher-icon');
  if (!iconHost) {
    const legacyIonIcon = switcher.querySelector('ion-icon');
    if (legacyIonIcon) {
      legacyIonIcon.classList.add('theme-switcher-icon');
      iconHost = legacyIonIcon;
    } else {
      switcher.innerHTML = '<span class="theme-switcher-icon" aria-hidden="true"></span>';
      iconHost = switcher.querySelector('.theme-switcher-icon');
    }
  }

  const setToggleState = () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    switcher.setAttribute('aria-pressed', String(isDark));
    switcher.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    if (iconHost instanceof Element) {
      if (iconHost.tagName === 'ION-ICON') {
        iconHost.setAttribute('name', isDark ? 'sunny-outline' : 'moon-outline');
      } else {
        iconHost.innerHTML = isDark ? sunIconMarkup : moonIconMarkup;
      }
    }
    applyThemeLogoAssets();
  };

  setToggleState();

  switcher.addEventListener('click', () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    if (isDark) {
      root.removeAttribute('data-theme');
      try {
        localStorage.removeItem('theme');
      } catch (error) {
        // Ignore storage access issues.
      }
    } else {
      root.setAttribute('data-theme', 'dark');
      try {
        localStorage.setItem('theme', 'dark');
      } catch (error) {
        // Ignore storage access issues.
      }
    }
    setToggleState();
  });
}

function applyThemeLogoAssets() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

  document.querySelectorAll('.logo img, .footer-modern-logo img').forEach((img) => {
    const isFooterLogo = Boolean(img.closest('.footer-modern-logo'));
    const defaultLightSrc = isFooterLogo ? 'assets/icons/footer-logo-light.png' : 'assets/icons/logo-real.png';
    const defaultDarkSrc = isFooterLogo ? 'assets/icons/footer-logo-dark.png' : 'assets/icons/logo-real-dark.png';
    const lightSrc = img.getAttribute('data-light-src') || defaultLightSrc;
    const darkSrc = img.getAttribute('data-dark-src') || defaultDarkSrc;
    const lightSrcset = img.getAttribute('data-light-srcset') || '';
    const darkSrcset = img.getAttribute('data-dark-srcset') || '';
    const logoSrc = isDark ? darkSrc : lightSrc;
    const logoSrcset = isDark ? darkSrcset : lightSrcset;
    const targetPath = new URL(logoSrc, window.location.href).pathname;
    const currentPath = new URL(img.getAttribute('src') || img.src, window.location.href).pathname;

    if (currentPath !== targetPath) {
      img.src = logoSrc;
    }

    if (logoSrcset) {
      if (img.getAttribute('srcset') !== logoSrcset) {
        img.srcset = logoSrcset;
      }
    } else if (img.hasAttribute('srcset')) {
      img.removeAttribute('srcset');
    }

    img.alt = 'Newcomer Connect';
  });
}

function loadDeferredImage(image) {
  if (!(image instanceof HTMLImageElement)) return;
  if (image.dataset.imageLoaded === '1') return;

  const deferredSrc = image.getAttribute('data-src');
  if (deferredSrc) {
    image.src = deferredSrc;
    image.removeAttribute('data-src');
  }

  const deferredSrcset = image.getAttribute('data-srcset');
  if (deferredSrcset) {
    image.srcset = deferredSrcset;
    image.removeAttribute('data-srcset');
  }

  image.dataset.imageLoaded = '1';
}

function loadDeferredImagesWithin(root) {
  if (!root) return;
  root.querySelectorAll('img[data-deferred-image]').forEach((image) => {
    loadDeferredImage(image);
  });
}

function initDeferredImages() {
  const deferredImages = Array.from(document.querySelectorAll('img[data-deferred-image]'));
  if (!deferredImages.length) return;

  const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      loadDeferredImage(entry.target);
      imageObserver.unobserve(entry.target);
    });
  }, { rootMargin: '300px 0px' });

  deferredImages.forEach((image) => imageObserver.observe(image));
}

function loadDeferredVideo(video) {
  if (!(video instanceof HTMLVideoElement)) return;
  if (video.dataset.videoLoaded === '1') return;

  const deferredMedia = Array.from(video.querySelectorAll('source[data-src], track[data-src]'));
  if (!deferredMedia.length) return;

  deferredMedia.forEach((node) => {
    const deferredSrc = node.getAttribute('data-src');
    if (!deferredSrc) return;
    node.src = deferredSrc;
    node.removeAttribute('data-src');
  });

  video.dataset.videoLoaded = '1';
  video.load();
}

function loadDeferredVideosWithin(root) {
  if (!root) return;
  root.querySelectorAll('video[data-deferred-video]').forEach((video) => {
    loadDeferredVideo(video);
  });
}

function playDeferredVideo(video) {
  if (!(video instanceof HTMLVideoElement)) return;

  loadDeferredVideo(video);
  video.muted = true;
  video.playsInline = true;

  const playPromise = video.play();
  if (playPromise && typeof playPromise.catch === 'function') {
    playPromise.catch(() => {
      // Ignore autoplay rejections from stricter browser policies.
    });
  }
}

function initDeferredVideos() {
  const deferredVideos = Array.from(document.querySelectorAll('video[data-deferred-video]'));
  if (!deferredVideos.length) return;

  const deferredInView = deferredVideos.filter((video) => !video.closest('.video-carousel') && !video.closest('.faq-answer'));
  if (deferredInView.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        loadDeferredVideo(entry.target);
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '240px 0px' });

    deferredInView.forEach((video) => observer.observe(video));
  }

  const autoplayVideos = deferredVideos.filter((video) => video.hasAttribute('data-autoplay-on-view'));
  if (autoplayVideos.length) {
    const autoplayObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const video = entry.target;
        if (!(video instanceof HTMLVideoElement)) return;

        if (entry.isIntersecting && entry.intersectionRatio >= 0.45) {
          playDeferredVideo(video);
          return;
        }

        video.pause();
      });
    }, {
      threshold: [0.45, 0.7],
      rootMargin: '0px 0px -6% 0px'
    });

    autoplayVideos.forEach((video) => autoplayObserver.observe(video));
  }

  deferredVideos.forEach((video) => {
    const prepareVideo = () => loadDeferredVideo(video);
    video.addEventListener('mouseenter', prepareVideo, { once: true });
    video.addEventListener('touchstart', prepareVideo, { once: true, passive: true });
  });
}

/*  GLOBAL MAPLE LEAF RAIN  */
function initSiteLeafRain() {
  return;
}

/*  HERO MAPLE LEAF RAIN  */
function initHeroLeafRain() {
  if (!document.body.classList.contains('home-page')) return;

  const hero = document.querySelector('.hero');
  if (!hero) return;

  const rainLayer = hero.querySelector('.hero-leaf-rain');
  if (!rainLayer) return;
  if (rainLayer.children.length) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isLowPowerViewport = window.matchMedia('(max-width: 900px)').matches;
  if (prefersReducedMotion || isLowPowerViewport) return;

  const leafCount = 14;
  const leafSrc = 'assets/images/maple-leaf-home.png';
  const frag = document.createDocumentFragment();

  for (let i = 0; i < leafCount; i += 1) {
    const leaf = document.createElement('img');
    const size = 24 + Math.random() * 22;
    const scale = 0.8 + Math.random() * 0.34;
    const drift = -96 + Math.random() * 192;
    const duration = 12 + Math.random() * 7;
    const delay = -Math.random() * duration;
    const rotateStart = -60 + Math.random() * 120;
    const rotateEnd = rotateStart + 420 + Math.random() * 260;

    leaf.className = 'hero-leaf';
    leaf.src = leafSrc;
    leaf.alt = '';
    leaf.decoding = 'async';
    leaf.style.left = `${Math.random() * 100}%`;
    leaf.style.setProperty('--leaf-size', `${size.toFixed(1)}px`);
    leaf.style.setProperty('--leaf-scale', scale.toFixed(2));
    leaf.style.setProperty('--leaf-drift', `${drift.toFixed(1)}px`);
    leaf.style.setProperty('--leaf-fall-duration', `${duration.toFixed(2)}s`);
    leaf.style.setProperty('--leaf-delay', `${delay.toFixed(2)}s`);
    leaf.style.setProperty('--leaf-rotate-start', `${rotateStart.toFixed(1)}deg`);
    leaf.style.setProperty('--leaf-rotate-end', `${rotateEnd.toFixed(1)}deg`);
    leaf.style.setProperty('--leaf-opacity', `${(0.3 + Math.random() * 0.22).toFixed(2)}`);

    frag.appendChild(leaf);
  }

  rainLayer.appendChild(frag);

  const updateLeafFade = () => {
    const viewportHeight = window.innerHeight || 1;
    const fadeDistance = Math.max(viewportHeight * 0.95, 560);
    const fadeProgress = Math.min(window.scrollY / fadeDistance, 1);
    const fadeValue = Math.max(0, 1 - fadeProgress);
    rainLayer.style.setProperty('--leaf-scroll-opacity', fadeValue.toFixed(3));
  };

  updateLeafFade();
  window.addEventListener('scroll', updateLeafFade, { passive: true });
}

/*  PARALLAX MEDIA  */
function initParallaxMedia() {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion || window.matchMedia('(max-width: 900px)').matches) return;

  const mediaTargets = [];
  const registerTargets = (selector, speed, maxOffset) => {
    document.querySelectorAll(selector).forEach((el) => {
      el.classList.add('parallax-media');
      el.dataset.parallaxSpeed = String(speed);
      el.dataset.parallaxMaxOffset = String(maxOffset);
      mediaTargets.push(el);
    });
  };

  registerTargets('.hero-bg-img img', 0.18, 60);
  registerTargets('.slide img', 0.12, 36);
  registerTargets('.split-img-main img', 0.08, 28);
  registerTargets('.gallery-item img', 0.06, 22);
  registerTargets('.img-band img', 0.08, 26);
  registerTargets('.blog-thumb img', 0.05, 18);
  registerTargets('.team-photo img', 0.05, 18);

  if (!mediaTargets.length) return;

  let ticking = false;
  const update = () => {
    ticking = false;
    const viewportHeight = window.innerHeight || 1;
    const viewportCenter = viewportHeight / 2;

    mediaTargets.forEach((el) => {
      const rect = el.getBoundingClientRect();
      if (rect.bottom < -120 || rect.top > viewportHeight + 120) return;

      const elementCenter = rect.top + (rect.height / 2);
      const distanceFromCenter = viewportCenter - elementCenter;
      const speed = parseFloat(el.dataset.parallaxSpeed || '0.08');
      const maxOffset = parseFloat(el.dataset.parallaxMaxOffset || '24');

      const offset = Math.max(-maxOffset, Math.min(maxOffset, distanceFromCenter * speed));
      el.style.setProperty('--parallax-offset', `${offset.toFixed(2)}px`);
    });
  };

  const requestUpdate = () => {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(update);
  };

  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate, { passive: true });
  requestUpdate();
}

/*  GALLERY AUTO SCROLL  */
function initGalleryAutoScroll() {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isLowPowerViewport = window.matchMedia('(max-width: 900px)').matches;
  if (prefersReducedMotion || isLowPowerViewport) return;

  document.querySelectorAll('.gallery-carousel').forEach((strip) => {
    if (strip.scrollWidth <= strip.clientWidth + 8) return;

    let direction = 1;
    let paused = false;

    const speed = 0.24;
    const getLimit = () => Math.max(0, strip.scrollWidth - strip.clientWidth);

    const tick = () => {
      const limit = getLimit();
      if (!paused && limit > 0) {
        let next = strip.scrollLeft + (direction * speed);
        if (next >= limit) {
          next = limit;
          direction = -1;
        } else if (next <= 0) {
          next = 0;
          direction = 1;
        }
        strip.scrollLeft = next;
      }
      window.requestAnimationFrame(tick);
    };

    strip.addEventListener('mouseenter', () => { paused = true; });
    strip.addEventListener('mouseleave', () => { paused = false; });
    strip.addEventListener('focusin', () => { paused = true; });
    strip.addEventListener('focusout', () => { paused = false; });
    strip.addEventListener('touchstart', () => { paused = true; }, { passive: true });
    strip.addEventListener('touchend', () => { paused = false; }, { passive: true });

    window.requestAnimationFrame(tick);
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
      if (!Number.isFinite(end)) {
        observer.unobserve(el);
        return;
      }

      const initialValue = parseInt(el.textContent, 10);
      let start = Number.isFinite(initialValue) ? initialValue : 0;

      if (start >= end) {
        el.textContent = `${end}${end >= 25 && end <= 500 ? '+' : ''}`;
        observer.unobserve(el);
        return;
      }

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
    const dotsWrap = slider.querySelector('.slider-dots');
    const prev  = slider.querySelector('.slider-control.prev');
    const next  = slider.querySelector('.slider-control.next');
    if (!track) return;

    const slides = slider.querySelectorAll('.slide');
    const total   = slides.length;
    const dotLabel = slider.classList.contains('video-carousel') ? 'video' : 'slide';
    let dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.slider-dot')) : [];

    if (dotsWrap && dots.length !== total) {
      dotsWrap.innerHTML = '';
      for (let i = 0; i < total; i += 1) {
        const dot = document.createElement('button');
        dot.className = `slider-dot${i === 0 ? ' active' : ''}`;
        dot.type = 'button';
        dot.setAttribute('aria-label', `Go to ${dotLabel} ${i + 1}`);
        dotsWrap.appendChild(dot);
      }
      dots = Array.from(dotsWrap.querySelectorAll('.slider-dot'));
    } else {
      dots.forEach((dot, index) => {
        dot.setAttribute('type', 'button');
        if (!dot.getAttribute('aria-label')) {
          dot.setAttribute('aria-label', `Go to ${dotLabel} ${index + 1}`);
        }
      });
    }

    let current   = 0;
    let autoInterval = null;
    let canLoadActiveVideo = !slider.classList.contains('video-carousel');
    let sliderImagesPrimed = slider.getBoundingClientRect().top <= ((window.innerHeight || 0) + 300);
    const delay = parseInt(slider.getAttribute('data-autoplay') || '4500', 10);
    const autoplayEnabled = Number.isFinite(delay) && delay > 0;
    const loadSlideImages = (idx) => {
      if (!total) return;

      [idx, idx + 1, idx - 1].forEach((candidate) => {
        loadDeferredImagesWithin(slides[(candidate + total) % total]);
      });
    };

    if (!sliderImagesPrimed && 'IntersectionObserver' in window) {
      const imagePrimeObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          sliderImagesPrimed = true;
          loadSlideImages(current);
          imagePrimeObserver.unobserve(slider);
        });
      }, { rootMargin: '300px 0px' });

      imagePrimeObserver.observe(slider);
    } else if (sliderImagesPrimed) {
      loadSlideImages(current);
    }

    if (!canLoadActiveVideo) {
      const viewObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          canLoadActiveVideo = true;
          loadDeferredVideosWithin(slides[current]);
          viewObserver.unobserve(slider);
        });
      }, { rootMargin: '240px 0px' });

      viewObserver.observe(slider);
    }

    const goTo = (idx) => {
      current = (idx + total) % total;
      const sliderWidth = slider.clientWidth;
      track.style.transform = `translateX(-${current * sliderWidth}px)`;
      dots.forEach((d, i) => d.classList.toggle('active', i === current));

      if (!sliderImagesPrimed && slider.getBoundingClientRect().top <= ((window.innerHeight || 0) + 300)) {
        sliderImagesPrimed = true;
      }

      if (sliderImagesPrimed) {
        loadSlideImages(current);
      }

      // Prevent overlapping audio when slider includes video elements.
      slider.querySelectorAll('video').forEach((videoEl) => {
        const isActive = videoEl.closest('.slide') === slides[current];
        if (!isActive) {
          videoEl.pause();
        }
      });

      if (canLoadActiveVideo) {
        loadDeferredVideosWithin(slides[current]);
      }
    };

    if (prev) prev.addEventListener('click', () => { goTo(current - 1); resetAuto(); });
    if (next) next.addEventListener('click', () => { goTo(current + 1); resetAuto(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); resetAuto(); }));

    const startAuto = () => {
      if (!autoplayEnabled) return;
      autoInterval = setInterval(() => goTo(current + 1), delay);
    };
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

    window.addEventListener('resize', () => {
      goTo(current);
    }, { passive: true });

    startAuto();
    goTo(0);
  });
}

function scheduleApprovedReviews() {
  const target = document.querySelector('[data-review-feed]');
  if (!target) return;

  if (!('IntersectionObserver' in window)) {
    scheduleNonCriticalWork(() => loadApprovedReviews(), 800);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      observer.disconnect();
      scheduleNonCriticalWork(() => loadApprovedReviews(), 800);
    });
  }, { rootMargin: '300px 0px' });

  observer.observe(target);
}

/*  FAQ ACCORDION  */
function initFAQ() {
  const getAnswerPanel = (item) => item ? item.querySelector('.faq-answer') : null;

  const closeItem = (item) => {
    if (!item) return;
    const panel = getAnswerPanel(item);
    item.classList.remove('active');
    if (panel) {
      panel.style.maxHeight = '0px';
    }
  };

  const openItem = (item) => {
    if (!item) return;
    const panel = getAnswerPanel(item);
    item.classList.add('active');
    if (panel) {
      loadDeferredVideosWithin(panel);
      panel.style.maxHeight = `${panel.scrollHeight}px`;
    }
  };

  const updateOpenHeights = () => {
    document.querySelectorAll('.faq-item.active .faq-answer').forEach((panel) => {
      panel.style.maxHeight = `${panel.scrollHeight}px`;
    });
  };

  document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq-item');
      const isOpen = item && item.classList.contains('active');

      document.querySelectorAll('.faq-item.active').forEach(closeItem);
      if (!isOpen) {
        openItem(item);
      }
    });
  });

  document.querySelectorAll('.faq-answer video').forEach((video) => {
    video.addEventListener('loadedmetadata', updateOpenHeights);
  });

  window.addEventListener('resize', updateOpenHeights, { passive: true });
}

/*  CONTACT FORM  */
function initContactForm() {
  const forms = document.querySelectorAll('[data-async-contact-form]');
  if (!forms.length) return;

  const today = new Date();
  const todayString = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  document.querySelectorAll('[data-booking-date]').forEach((input) => {
    input.setAttribute('min', todayString);
  });

  let browserTimezone = '';
  try {
    browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
  } catch (error) {
    browserTimezone = '';
  }

  forms.forEach((form) => {
    const successSelector = form.getAttribute('data-success-target') || '';
    const errorSelector = form.getAttribute('data-error-target') || '';
    const success = successSelector ? document.querySelector(successSelector) : null;
    const error = errorSelector ? document.querySelector(errorSelector) : null;
    const submitButton = form.querySelector('button[type="submit"]');
    const defaultButtonText = submitButton ? submitButton.innerHTML : '';

    const browserTimezoneInput = form.querySelector('input[name="browser_timezone"]');
    if (browserTimezoneInput) {
      browserTimezoneInput.value = browserTimezone;
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();

      if (success) success.style.display = 'none';
      if (error) error.style.display = 'none';

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';
      }

      try {
        const endpoint = form.getAttribute('action');
        if (!endpoint) throw new Error('Missing form endpoint');

        const formData = new FormData(form);
        if (!formData.get('page_title')) {
          formData.set('page_title', document.title || 'Website');
        }
        if (!formData.get('page_url')) {
          formData.set('page_url', window.location.href || '');
        }
        if (!formData.get('submission_source')) {
          const currentPath = (window.location.pathname || '').split('/').pop();
          formData.set('submission_source', currentPath || 'website');
        }
        if (!formData.get('browser_timezone') && browserTimezone) {
          formData.set('browser_timezone', browserTimezone);
        }

        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) {
          const message = payload && payload.message ? payload.message : 'Request failed';
          throw new Error(message);
        }

        form.reset();
        form.style.display = 'none';
        if (success) {
          if (payload.message) {
            success.textContent = payload.message;
          }
          success.style.display = 'block';
        }
      } catch (submitError) {
        if (error) {
          if (submitError && submitError.message) {
            error.textContent = submitError.message;
          }
          error.style.display = 'block';
        }
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.innerHTML = defaultButtonText;
        }
      }
    });
  });
}

function initContactFormSwitcher() {
  const triggers = Array.from(document.querySelectorAll('[data-contact-panel-trigger]'));
  const panels = Array.from(document.querySelectorAll('[data-contact-panel]'));
  if (!triggers.length || !panels.length) return;

  const setActivePanel = (panelName) => {
    triggers.forEach((button) => {
      const isActive = button.getAttribute('data-contact-panel-trigger') === panelName;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', String(isActive));
    });

    panels.forEach((panel) => {
      const isActive = panel.getAttribute('data-contact-panel') === panelName;
      panel.classList.toggle('is-active', isActive);
      panel.hidden = !isActive;
    });
  };

  triggers.forEach((button) => {
    button.addEventListener('click', () => {
      const panelName = button.getAttribute('data-contact-panel-trigger');
      if (!panelName) return;
      setActivePanel(panelName);
    });
  });

  const activeTrigger = triggers.find((button) => button.classList.contains('is-active')) || triggers[0];
  const initialPanel = activeTrigger.getAttribute('data-contact-panel-trigger') || 'booking';
  setActivePanel(initialPanel);
}

/*  REVIEW FORMS  */
function initReviewForms() {
  const isLocalFileMode = window.location.protocol === 'file:';
  const LOCAL_REVIEW_STORAGE_KEY = 'nc_local_reviews_v1';
  try {
    localStorage.removeItem(LOCAL_REVIEW_STORAGE_KEY);
  } catch (error) {
    // Ignore storage cleanup failures.
  }

  document.querySelectorAll('.review-form').forEach((form) => {
    const success = form.parentElement ? form.parentElement.querySelector('.review-success') : null;
    const error = form.parentElement ? form.parentElement.querySelector('.review-error') : null;
    const submitButton = form.querySelector('button[type="submit"]');
    const defaultButtonText = submitButton ? submitButton.textContent : '';

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (success) success.style.display = 'none';
      if (error) error.style.display = 'none';

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
      }

      try {
        const formData = new FormData(form);
        const formAction = (form.getAttribute('action') || '').trim();
        const endpoint = isLocalFileMode ? 'http://127.0.0.1:8091/review-submit.php' : formAction;
        if (!endpoint) throw new Error('Missing form endpoint');

        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || 'Review submission failed');
        }

        form.reset();
        if (success) {
          success.textContent = payload.message || 'Thank you. Your review is pending approval.';
          success.style.display = 'block';
        }
      } catch (submitError) {
        if (error) {
          const rawMessage = submitError && submitError.message ? submitError.message : '';
          if (rawMessage && /Failed to fetch/i.test(rawMessage)) {
            error.textContent = 'Could not reach the review server at http://127.0.0.1:8091/review-submit.php. Start PHP server, then try again.';
            error.style.display = 'block';
          } else {
            error.textContent = rawMessage || 'Could not submit review.';
            error.style.display = 'block';
          }
        }
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = defaultButtonText;
        }
      }
    });
  });
}

function createReviewCard(review) {
  const card = document.createElement('article');
  card.className = 'testimonial-card review-card-dynamic';

  const starsWrap = document.createElement('div');
  starsWrap.className = 't-stars';
  const rating = Math.max(1, Math.min(5, parseInt(review.rating, 10) || 5));
  for (let i = 0; i < rating; i += 1) {
    const span = document.createElement('span');
    starsWrap.appendChild(span);
  }

  const text = document.createElement('p');
  text.className = 't-text';
  text.textContent = `"${review.message || ''}"`;

  const author = document.createElement('div');
  author.className = 't-author';

  const avatar = document.createElement('div');
  avatar.className = 't-avatar';
  const avatarText = (review.avatar_text || review.display_name || review.name || 'NC').trim() || 'NC';
  avatar.textContent = avatarText.toUpperCase();

  const info = document.createElement('div');
  info.className = 't-info';

  const strong = document.createElement('strong');
  strong.textContent = review.display_name || review.name || 'Verified Client';

  const city = document.createElement('span');
  city.textContent = review.city || 'Canada';

  info.appendChild(strong);
  info.appendChild(city);
  author.appendChild(avatar);
  author.appendChild(info);

  const meta = document.createElement('div');
  meta.className = 't-meta';
  meta.textContent = `Service: ${review.service || 'General Support'}`;

  card.appendChild(starsWrap);
  card.appendChild(text);
  card.appendChild(author);
  card.appendChild(meta);

  return card;
}

async function loadApprovedReviews() {
  const targets = Array.from(document.querySelectorAll('[data-review-feed]'));
  if (!targets.length) return;

  const LOCAL_REVIEW_STORAGE_KEY = 'nc_local_reviews_v1';

  const appendItems = (target, items, limit) => {
    const placeholders = Array.from(target.querySelectorAll('[data-review-placeholder]'));
    target.querySelectorAll('.review-card-dynamic').forEach((card) => card.remove());

    if (items.length) {
      placeholders.forEach((card) => card.remove());
    }

    items.slice(0, limit).forEach((review) => {
      target.appendChild(createReviewCard(review));
    });
  };

  const maxLimit = targets.reduce((max, target) => {
    const n = parseInt(target.getAttribute('data-review-limit') || '8', 10);
    return Math.max(max, Number.isFinite(n) ? n : 8);
  }, 8);

  try {
    localStorage.removeItem(LOCAL_REVIEW_STORAGE_KEY);
  } catch (error) {
    // Ignore storage cleanup failures.
  }

  try {
    const response = await fetch(`reviews-feed.php?limit=${Math.min(60, maxLimit)}`, {
      headers: { Accept: 'application/json' }
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.success || !Array.isArray(payload.reviews)) {
      throw new Error('Remote review feed unavailable.');
    }

    targets.forEach((target) => {
      const limit = parseInt(target.getAttribute('data-review-limit') || '8', 10);
      appendItems(target, payload.reviews, Number.isFinite(limit) ? limit : 8);
    });
  } catch (error) {
    // Keep static testimonials visible if remote feed is unavailable.
  }
}

