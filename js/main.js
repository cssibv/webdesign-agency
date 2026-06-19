(function () {
  'use strict';

  var yearEl = document.getElementById('year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  var navbar = document.getElementById('navbar');
  var toTop = document.getElementById('toTop');

  function onScroll() {
    var y = window.scrollY || window.pageYOffset;

    if (navbar) {
      navbar.classList.toggle('is-scrolled', y > 20);
    }

    if (toTop) {
      toTop.classList.toggle('is-visible', y > 400);
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  var navToggle = document.getElementById('navToggle');
  var navMenu = document.getElementById('navMenu');

  function closeMenu() {
    if (!navMenu || !navToggle) return;
    navMenu.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Deschide meniul');
  }

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function () {
      var isOpen = navMenu.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      navToggle.setAttribute('aria-label', isOpen ? 'Închide meniul' : 'Deschide meniul');
    });

    navMenu.addEventListener('click', function (e) {
      if (e.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });
  }

  if (toTop) {
    toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  var revealEls = document.querySelectorAll('.reveal');

  if ('IntersectionObserver' in window && revealEls.length) {
    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry, i) {
        if (entry.isIntersecting) {

          var delay = Math.min(i * 60, 240);
          setTimeout(function () {
            entry.target.classList.add('is-visible');
          }, delay);
          obs.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.12,
      rootMargin: '0px 0px -40px 0px'
    });

    revealEls.forEach(function (el) { observer.observe(el); });
  } else {

    revealEls.forEach(function (el) { el.classList.add('is-visible'); });
  }

  var form = document.getElementById('contactForm');
  var status = document.getElementById('formStatus');

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      var btn = form.querySelector('button[type="submit"]');
      var originalText = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Se trimite...'; }
      setStatus('', '');

      var data = new FormData(form);

      fetch(form.action, {
        method: 'POST',
        body: data,
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) {
          if (response.ok) {
            form.reset();
            setStatus('✓ Mulțumesc! Mesajul a fost trimis. Te contactez în maxim 24h.', 'is-success');
            if (window.bwTrack) window.bwTrack('trimitere_formular', { method: 'formular_contact' });
          } else {
            return response.json().then(function (d) {
              var msg = (d && d.errors) ? d.errors.map(function (x) { return x.message; }).join(', ')
                                        : 'A apărut o eroare. Încearcă din nou sau scrie-mi pe WhatsApp.';
              setStatus('✗ ' + msg, 'is-error');
            });
          }
        })
        .catch(function () {
          setStatus('✗ Conexiune eșuată. Verifică internetul sau scrie-mi pe WhatsApp.', 'is-error');
        })
        .finally(function () {
          if (btn) { btn.disabled = false; btn.textContent = originalText; }
        });
    });
  }

  function setStatus(message, cls) {
    if (!status) return;
    status.textContent = message;
    status.className = 'contact__formnote ' + (cls || '');
  }

  var themeToggle = document.getElementById('themeToggle');
  var root = document.documentElement;

  function applyTheme(theme) {
    var isDark = theme === 'dark';
    if (isDark) {
      root.setAttribute('data-theme', 'dark');
    } else {
      root.removeAttribute('data-theme');
    }
    if (themeToggle) {

      themeToggle.setAttribute('aria-label', isDark ? 'Comută tema luminoasă' : 'Comută tema întunecată');
      themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    }
  }

  applyTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      try { localStorage.setItem('theme', next); } catch (e) {}
    });
  }

  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
      var hasChoice = false;
      try { hasChoice = !!localStorage.getItem('theme'); } catch (err) {}
      if (!hasChoice) applyTheme(e.matches ? 'dark' : 'light');
    });
  }

})();
