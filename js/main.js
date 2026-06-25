(function () {
  'use strict';

  var yearEl = document.getElementById('year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  var navbar = document.getElementById('navbar');
  var toTop = document.getElementById('toTop');

  var scrollTicking = false;

  function applyScroll() {
    scrollTicking = false;
    var y = window.scrollY || window.pageYOffset;
    if (navbar) {
      navbar.classList.toggle('is-scrolled', y > 20);
    }
    if (toTop) {
      toTop.classList.toggle('is-visible', y > 400);
    }
  }

  function onScroll() {
    if (!scrollTicking) {
      scrollTicking = true;
      window.requestAnimationFrame(applyScroll);
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.requestAnimationFrame(applyScroll);

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
  var formLoaded = Date.now();

  if (form) {
    var emailInput = form.querySelector('#email');
    var phoneInput = form.querySelector('#telefon');
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var PHONE_RE = /^(0\d{9}|(\+|00)\d{8,14})$/;

    function mark(input, valid) {
      var f = input.closest('.field');
      if (f) f.classList.toggle('field--invalid', !valid);
      return valid;
    }

    function checkFormats() {
      if (!emailInput || !phoneInput) return true;
      var okPhone = mark(phoneInput, PHONE_RE.test(phoneInput.value.replace(/[\s().-]/g, '')));
      var okEmail = mark(emailInput, EMAIL_RE.test(emailInput.value.trim()));
      if (!okPhone || !okEmail) {
        setStatus('✗ ' + (!okPhone ? 'Numărul de telefon nu pare valid (ex: 07xx xxx xxx sau +40…).' : 'Adresa de email nu pare validă.'), 'is-error');
        (!okPhone ? phoneInput : emailInput).focus();
      }
      return okPhone && okEmail;
    }

    [emailInput, phoneInput].filter(Boolean).forEach(function (inp) {
      inp.addEventListener('input', function () {
        var f = inp.closest('.field');
        if (f) f.classList.remove('field--invalid');
      });
    });

    var mesaj = form.querySelector('#mesaj');
    var mesajCount = document.getElementById('mesajCount');
    var mesajLimit = document.getElementById('mesajLimit');
    if (mesaj && mesajCount) {
      var mesajMax = parseInt(mesaj.getAttribute('maxlength'), 10) || 300;
      var updateCount = function () {
        var len = mesaj.value.length;
        mesajCount.textContent = len + '/' + mesajMax;
        if (mesajLimit) mesajLimit.hidden = len < mesajMax;
      };
      mesaj.addEventListener('input', updateCount);
      updateCount();
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!checkFormats()) return;

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      var btn = form.querySelector('button[type="submit"]');
      var originalText = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Se trimite...'; }
      setStatus('', '');

      var tsField = document.getElementById('formTs');
      if (tsField) tsField.value = Date.now() - formLoaded;

      var data = new FormData(form);

      fetch(form.action, {
        method: 'POST',
        body: data,
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) {
          if (response.ok) {
            form.reset();
            if (mesaj) mesaj.dispatchEvent(new Event('input'));
            if (window.turnstile) window.turnstile.reset();
            setStatus('✓ Gata! Ți-am trimis un email de confirmare, dă click pe link ca să continuăm.', 'is-success');
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

    function loadTurnstile() {
      if (window.tsLoaded) return;
      window.tsLoaded = true;
      var s = document.createElement('script');
      s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
      s.async = true;
      s.defer = true;
      document.head.appendChild(s);
    }
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) { loadTurnstile(); io.disconnect(); }
      }, { rootMargin: '400px' });
      io.observe(form);
    } else {
      loadTurnstile();
    }
    form.addEventListener('focusin', loadTurnstile, { once: true });
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
