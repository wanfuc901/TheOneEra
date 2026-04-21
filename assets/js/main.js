// ══════════════════════════════════════════════════════════
// ONE ERA — Main JavaScript
// ══════════════════════════════════════════════════════════

gsap.registerPlugin(ScrollTrigger);

// ── CURSOR — disable on touch devices ──
const isTouchDevice = window.matchMedia('(hover: none)').matches || 'ontouchstart' in window;
const cursor = document.getElementById('cursor');
const ring = document.getElementById('cursorRing');
let mx = 0, my = 0, rx = 0, ry = 0;

if (!isTouchDevice) {
  window.addEventListener('mousemove', e => {
    mx = e.clientX;
    my = e.clientY;
    cursor.style.left = mx + 'px';
    cursor.style.top = my + 'px';
  });
  (function animRing() {
    rx += (mx - rx) * 0.13;
    ry += (my - ry) * 0.13;
    ring.style.left = rx + 'px';
    ring.style.top = ry + 'px';
    requestAnimationFrame(animRing);
  })();
} else {
  cursor.style.display = 'none';
  ring.style.display = 'none';
  document.body.style.cursor = 'auto';
}

// ── LOADER ANIMATION ──
const letters = document.querySelectorAll('.loader-letter');
const divider = document.getElementById('loaderDivider');
const sub = document.getElementById('loaderSub');
const fill = document.getElementById('progressFill');
const num = document.getElementById('progressNum');
const loader = document.getElementById('loader');

setTimeout(() => {
  fill.style.width = '100%';
}, 100);

let prog = 0;
const progInt = setInterval(() => {
  prog = Math.min(prog + Math.random() * 4, 100);
  num.textContent = Math.floor(prog) + '%';
  if (prog >= 100) clearInterval(progInt);
}, 60);

// STEP 1: Reset - letters hidden
gsap.set(letters, { opacity: 0, y: 0 });

// STEP 2: Logo fades in
const loaderLogo = document.getElementById('loaderLogo');
gsap.to(loaderLogo, {
  opacity: 1,
  scale: 1,
  duration: 0.75,
  ease: 'back.out(1.5)',
  delay: 0.2,
  onComplete: () => {
    setTimeout(() => {
      // Logo slides to the right and fades out
      gsap.to(loaderLogo, {
        x: 260,
        opacity: 0,
        duration: 1.3,
        ease: 'power2.inOut'
      });
      // Letters fade in with stagger
      gsap.to(letters, {
        opacity: 1,
        duration: 0.6,
        stagger: 0.08,
        ease: 'power2.out',
        delay: 0.15,
        onComplete: () => {
          gsap.to(divider, { width: 200, duration: 0.6, ease: 'power2.out', delay: 0.1 });
          gsap.to(sub, { opacity: 1, duration: 0.5, delay: 0.3 });
          setTimeout(() => {
            gsap.to(loader, {
              yPercent: -100,
              duration: 1,
              ease: 'power3.inOut',
              delay: 0.6,
              onComplete: () => {
                loader.style.display = 'none';
                startPage();
              }
            });
          }, 1400);
        }
      });
    }, 400);
  }
});

// ── START PAGE ANIMATIONS ──
function startPage() {
  gsap.to('#topbar', { opacity: 1, duration: 0.6, ease: 'power2.out' });
  gsap.to('#header', { opacity: 1, duration: 0.6, delay: 0.1, ease: 'power2.out' });
  gsap.to('#heroPattern', { opacity: 1, duration: 1.2, delay: 0.3 });
  gsap.to('.hero-img', { opacity: 0.15, duration: 1.5, delay: 0.2 });
  gsap.to('#heroBadge', { opacity: 1, y: 0, duration: 0.7, delay: 0.4, ease: 'power2.out' });
  gsap.to('#heroTitle', { opacity: 1, y: 0, duration: 0.9, delay: 0.55, ease: 'power3.out' });
  gsap.to('#heroDesc', { opacity: 1, y: 0, duration: 0.7, delay: 0.7, ease: 'power2.out' });
  gsap.to('#heroActions', { opacity: 1, y: 0, duration: 0.6, delay: 0.85, ease: 'power2.out' });
  gsap.to('#heroPanel', { opacity: 1, x: 0, duration: 0.7, delay: 0.9, ease: 'power2.out' });
  gsap.to('#scrollInd', { opacity: 1, duration: 0.6, delay: 1.2 });
  gsap.to('.float-cta', { opacity: 1, delay: 1.5, duration: 0.5 });
}

// ── SCROLL TRIGGER ANIMATIONS ──
document.querySelectorAll('.reveal-up').forEach(el => {
  gsap.to(el, {
    opacity: 1,
    y: 0,
    duration: 0.8,
    ease: 'power2.out',
    scrollTrigger: { trigger: el, start: 'top 88%', once: true }
  });
});

document.querySelectorAll('.location-point').forEach((el, i) => {
  gsap.to(el, {
    opacity: 1,
    x: 0,
    duration: 0.7,
    delay: i * 0.12,
    ease: 'power2.out',
    scrollTrigger: { trigger: el, start: 'top 88%', once: true }
  });
});

gsap.to('.location-visual', {
  opacity: 1,
  x: 0,
  duration: 0.9,
  ease: 'power2.out',
  scrollTrigger: { trigger: '.location-visual', start: 'top 80%', once: true }
});

document.querySelectorAll('.dev-card').forEach((el, i) => {
  gsap.to(el, {
    opacity: 1,
    y: 0,
    duration: 0.6,
    delay: i * 0.1,
    ease: 'power2.out',
    scrollTrigger: { trigger: el, start: 'top 88%', once: true }
  });
});

document.querySelectorAll('.benefit-item').forEach((el, i) => {
  gsap.to(el, {
    opacity: 1,
    duration: 0.5,
    delay: i * 0.12,
    ease: 'power2.out',
    scrollTrigger: { trigger: el, start: 'top 88%', once: true }
  });
});

// ── HEADER SCROLL EFFECT ──
window.addEventListener('scroll', () => {
  document.getElementById('header').classList.toggle('scrolled', window.scrollY > 60);
});

// ── POPUP FUNCTIONS ──
const popupOverlay = document.getElementById('popup-overlay');

function openPopup() {
  popupOverlay.classList.add('show');
}

function closePopup() {
  popupOverlay.classList.remove('show');
}

// Close on overlay backdrop click
popupOverlay.addEventListener('click', (e) => {
  if (e.target === popupOverlay) closePopup();
});

// Close on X button
document.querySelectorAll('.js-close-popup').forEach(el => {
  el.addEventListener('click', closePopup);
});

// Open on trigger buttons
document.querySelectorAll('.js-open-popup').forEach(el => {
  el.addEventListener('click', openPopup);
});

// Auto-show popup after 8 seconds — only once per session
if (!sessionStorage.getItem('oe_popup_shown')) {
  setTimeout(() => {
    openPopup();
    sessionStorage.setItem('oe_popup_shown', '1');
  }, 8000);
}

// Block scroll on body when popup open
const popupObs = new MutationObserver(() => {
  const isOpen = popupOverlay.classList.contains('show');
  document.body.style.overflow = isOpen ? 'hidden' : '';
});
popupObs.observe(popupOverlay, { attributes: true, attributeFilter: ['class'] });

// ── PRODUCT TABS ──
document.querySelectorAll('.tab-btn[data-tab]').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.tab;
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
  });
});

// ── MOBILE MENU ──
const menuBtn = document.getElementById('menuBtn');
const mobileMenu = document.getElementById('mobile-menu');
let menuOpen = false;

menuBtn.addEventListener('click', () => {
  menuOpen = !menuOpen;
  mobileMenu.classList.toggle('open', menuOpen);
  const spans = menuBtn.querySelectorAll('span');
  if (menuOpen) {
    gsap.to(spans[0], { rotation: 45, y: 7, duration: 0.25 });
    gsap.to(spans[1], { opacity: 0, duration: 0.2 });
    gsap.to(spans[2], { rotation: -45, y: -7, duration: 0.25 });
  } else {
    gsap.to(spans[0], { rotation: 0, y: 0, duration: 0.25 });
    gsap.to(spans[1], { opacity: 1, duration: 0.2 });
    gsap.to(spans[2], { rotation: 0, y: 0, duration: 0.25 });
  }
});

function closeMobileMenu() {
  menuOpen = false;
  mobileMenu.classList.remove('open');
  const spans = menuBtn.querySelectorAll('span');
  gsap.to(spans[0], { rotation: 0, y: 0, duration: 0.25 });
  gsap.to(spans[1], { opacity: 1, duration: 0.2 });
  gsap.to(spans[2], { rotation: 0, y: 0, duration: 0.25 });
}

// Bind mobile nav close links
document.querySelectorAll('.js-close-menu').forEach(el => {
  el.addEventListener('click', closeMobileMenu);
});

// ── LOAD IMAGES FROM CONFIG ──
fetch('assets/config.json')
  .then(response => response.json())
  .then(data => {
    // Load loader logo — only if loader hasn't been dismissed yet
    if (data.loader && data.loader.logo) {
      const loaderLogoEl = document.getElementById('loaderLogo');
      // Only update if loader is still visible (not hidden)
      if (loaderLogoEl && loader.style.display !== 'none') {
        const existingImg = loaderLogoEl.querySelector('img');
        if (existingImg) {
          existingImg.src = data.loader.logo;
        } else {
          const img = document.createElement('img');
          img.className = 'loader-logo-img';
          img.src = data.loader.logo;
          img.alt = 'ONE ERA Logo';
          loaderLogoEl.appendChild(img);
        }
      }
    }

    // Load hero background
    if (data.hero && data.hero.bg) {
      const heroImgEl = document.getElementById('heroImg');
      if (heroImgEl && heroImgEl.tagName !== 'IMG') {
        const img = document.createElement('img');
        img.src = data.hero.bg;
        img.alt = 'ONE ERA';
        img.className = 'hero-img';
        heroImgEl.replaceWith(img);
      } else if (heroImgEl) {
        heroImgEl.src = data.hero.bg;
      }
    }

    // Load masterplan
    if (data.masterplan && data.masterplan.img) {
      const wrap = document.getElementById('masterplanImgWrap');
      const placeholder = document.getElementById('masterplanPlaceholder');
      const img = document.getElementById('masterplanImg');
      if (img) img.src = data.masterplan.img;
      if (wrap) wrap.style.display = 'block';
      if (placeholder) placeholder.style.display = 'none';
    }

    // Load lifestyle gallery
    if (data.lifestyle && data.lifestyle.length > 0) {
      const lifestyleGrid = document.getElementById('lifestyle-grid');
      if (lifestyleGrid) {
        lifestyleGrid.innerHTML = '';
        data.lifestyle.forEach((imgData, index) => {
          const item = document.createElement('div');
          item.className = 'lifestyle-item' + (index % 5 === 0 || index % 5 === 4 ? ' wide' : '') + (index % 7 === 1 ? ' tall' : '');

          const imgEl = document.createElement('img');
          imgEl.src     = imgData.src;
          imgEl.alt     = imgData.alt || '';
          imgEl.loading = 'lazy';
          imgEl.addEventListener('error', () => { item.style.display = 'none'; });

          const caption = document.createElement('div');
          caption.className = 'lifestyle-caption';
          caption.textContent = imgData.caption || '';

          item.appendChild(imgEl);
          item.appendChild(caption);
          lifestyleGrid.appendChild(item);
        });
      }
    }
  })
  .catch(err => console.log('Config not found, using defaults'));
