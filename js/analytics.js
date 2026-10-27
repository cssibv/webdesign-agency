(function () {
  'use strict';

  // ID Google Analytics 4
  var GA_MEASUREMENT_ID = 'G-QNJCL221JH';

  var STORAGE_KEY = 'cookie-consent';
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

  function idIsConfigured() {
    return GA_MEASUREMENT_ID && GA_MEASUREMENT_ID.indexOf('XXXX') === -1;
  }

  function isLocalEnv() {
    if (location.protocol === 'file:') return true;
    var h = location.hostname;
    return h === 'localhost' || h === '127.0.0.1' || h === '::1' ||
      h === '0.0.0.0' || h.slice(-6) === '.local' ||
      /^192\.168\./.test(h) || /^10\./.test(h) ||
      /^172\.(1[6-9]|2\d|3[01])\./.test(h);
  }

  // Consent Mode v2: fără acord nu se setează cookie-uri; după „Accept” se activează statistica completă.
  function loadGA(granted) {
    if (gaLoaded || !idIsConfigured() || isLocalEnv()) return;
    gaLoaded = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', {
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: granted ? 'granted' : 'denied'
    });
    window.gtag('js', new Date());
    window.gtag('config', GA_MEASUREMENT_ID);

    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_MEASUREMENT_ID;
    document.head.appendChild(s);
  }

  function updateConsent(granted) {
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', { analytics_storage: granted ? 'granted' : 'denied' });
    }
  }

  function trackEvent(name, params) {
    if (typeof window.gtag === 'function') {
      window.gtag('event', name, params || {});
    }
  }

  window.bwTrack = trackEvent;

  function grant() { setConsent('granted'); hideBanner(); updateConsent(true); }
  function deny()  { setConsent('denied');  hideBanner(); updateConsent(false); }

  var consent = getConsent();
  loadGA(consent === 'granted');
  if (consent !== 'granted' && consent !== 'denied') {
    showBanner();
  }

  var acceptBtn = document.getElementById('cookieAccept');
  var rejectBtn = document.getElementById('cookieReject');
  if (acceptBtn) acceptBtn.addEventListener('click', grant);
  if (rejectBtn) rejectBtn.addEventListener('click', deny);

  var reopenBtn = document.getElementById('cookieReopen');
  if (reopenBtn) reopenBtn.addEventListener('click', showBanner);

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
