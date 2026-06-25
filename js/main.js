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
    var EMAIL_RE = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
    var PHONE_RE = /^(0\d{9}|(\+|00)\d{8,15})$/;
    var NAME_RE  = /^[\p{L} .'-]+$/u;
    var FIRMA_RE = /^[\p{L}\p{N} .,&'()\/-]+$/u;
    var MSG_RE   = /^[\p{L}\p{N}\p{P}\p{S}\s]*$/u;

    function emailValid(v) {
      v = v.trim();
      if (v.length > 254 || !EMAIL_RE.test(v)) return false;
      var labels = v.split('@')[1].split('.');
      for (var i = 1; i < labels.length; i++) {
        if (labels[i].toLowerCase() === labels[i - 1].toLowerCase()) return false;
      }
      return true;
    }

    var checks = [
      { el: form.querySelector('#nume'),    err: document.getElementById('numeError'),    ok: function (v) { v = v.trim(); return v.length >= 2 && NAME_RE.test(v); } },
      { el: form.querySelector('#telefon'), err: document.getElementById('telefonError'), clean: function (v) { return v.replace(/[^0-9+]/g, ''); }, ok: function (v) { return PHONE_RE.test(v); } },
      { el: form.querySelector('#email'),   err: document.getElementById('emailError'),   ok: function (v) { return emailValid(v); } },
      { el: form.querySelector('#firma'),   err: document.getElementById('firmaError'),   ok: function (v) { v = v.trim(); return v === '' || FIRMA_RE.test(v); } },
      { el: form.querySelector('#mesaj'),   err: document.getElementById('mesajError'),   ok: function (v) { return MSG_RE.test(v); } }
    ].filter(function (c) { return c.el; });

    function checkField(c, force) {
      var valid = c.ok(c.el.value);
      var show = !valid && (force || c.el.value.trim() !== '');
      var f = c.el.closest('.field');
      if (f) f.classList.toggle('field--invalid', show);
      if (c.err) c.err.hidden = !show;
      return valid;
    }

    function validateAll() {
      var allOk = true, firstBad = null;
      checks.forEach(function (c) {
        if (!checkField(c, true)) { allOk = false; if (!firstBad) firstBad = c.el; }
      });
      if (firstBad) firstBad.focus();
      return allOk;
    }

    checks.forEach(function (c) {
      c.el.addEventListener('input', function () {
        if (c.clean) { var cl = c.clean(c.el.value); if (cl !== c.el.value) c.el.value = cl; }
        checkField(c, false);
      });
      c.el.addEventListener('blur', function () { checkField(c, false); });
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

      if (!validateAll()) return;

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
