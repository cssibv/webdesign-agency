(function () {
  'use strict';

  /* ============================================================
   * ⚙️  CONFIGURARE — pune aici ID-ul tău din Google Analytics 4.
   *     Îl iei gratuit de pe analytics.google.com (forma G-XXXXXXXXXX).
   *     Cât timp rămâne placeholder-ul de mai jos, GA NU se încarcă.
   * ============================================================ */
  var GA_MEASUREMENT_ID = 'G-XXXXXXXXXX';

  var STORAGE_KEY = 'cookie-consent'; // valori: 'granted' | 'denied'
  var banner = document.getElementById('cookieBanner');
  var gaLoaded = false;

  function getConsent() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function setConsent(value) {
    try { localStorage.setItem(STORAGE_KEY, value); } catch (e) {}
  }

  function showBanner() { if (banner) banner.classList.add('is-visible'); }
  function hideBanner() { if (banner) banner.classList.remove('is-visible'); }

  // Verifică dacă ID-ul a fost configurat (nu mai e placeholder-ul).
  function idIsConfigured() {
    return GA_MEASUREMENT_ID && GA_MEASUREMENT_ID.indexOf('XXXX') === -1;
  }

  // Încarcă Google Analytics 4 — doar după consimțământ și doar dacă ID-ul e setat.
  function loadGA() {
    if (gaLoaded || !idIsConfigured()) return;
    gaLoaded = true;

    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_MEASUREMENT_ID;
    document.head.appendChild(s);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA_MEASUREMENT_ID);
  }

  // Trimite un eveniment către GA (no-op dacă userul nu a acceptat).
  function trackEvent(name, params) {
    if (typeof window.gtag === 'function') {
      window.gtag('event', name, params || {});
    }
  }

  // Expus global, ca să poată fi apelat și din main.js (ex. la trimiterea formularului).
  window.bwTrack = trackEvent;

  function grant() { setConsent('granted'); hideBanner(); loadGA(); }
  function deny()  { setConsent('denied');  hideBanner(); }

  // La încărcare: respectă alegerea anterioară, altfel arată banner-ul.
  var consent = getConsent();
  if (consent === 'granted') {
    loadGA();
  } else if (consent !== 'denied') {
    showBanner();
  }

  var acceptBtn = document.getElementById('cookieAccept');
  var rejectBtn = document.getElementById('cookieReject');
  if (acceptBtn) acceptBtn.addEventListener('click', grant);
  if (rejectBtn) rejectBtn.addEventListener('click', deny);

  /* ---- Tracking conversii (click-uri importante) ---- */
  function bindConversion(selector, eventName, params) {
    var els = document.querySelectorAll(selector);
    for (var i = 0; i < els.length; i++) {
      els[i].addEventListener('click', function () {
        trackEvent(eventName, params);
      });
    }
  }

  bindConversion('a[href*="wa.me"]',    'click_whatsapp', { method: 'whatsapp' });
  bindConversion('a[href^="tel:"]',     'click_telefon',  { method: 'telefon' });
  bindConversion('a[href^="mailto:"]',  'click_email',    { method: 'email' });

})();
