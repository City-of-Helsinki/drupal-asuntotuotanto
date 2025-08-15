(function ($, Drupal, drupalSettings) {
  const init = function () {
    if (!Drupal.cookieConsent.getConsentStatus(['statistics']) || !drupalSettings.matomo_site_id) {
      return;
    }

    var _paq = window._paq = window._paq || [];
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);

    var base = (drupalSettings.matomo_base_url || 'https://matomo.hel.fi/').replace(/\/+$/, '') + '/js/';
    _paq.push(['setTrackerUrl', base + 'tracker.php']);
    _paq.push(['setSiteId', String(drupalSettings.matomo_site_id)]);

    var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
    g.async = true; g.src = base + 'piwik.min.js';
    s.parentNode.insertBefore(g, s);
  };

  if (Drupal.cookieConsent.initialized()) {
    init();
  } else {
    Drupal.cookieConsent.loadFunction(init);
  }
})(jQuery, Drupal, drupalSettings);
