/* Landing (Cosmic Loop): the FR / EN switch. The page is rendered by PHP, so
   switching reloads it in the other language (remembered in a cookie). */
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var switcher = event.target.closest('[data-lang-switch]');
    if (!switcher) return;
    var url = new URL(window.location.href);
    url.searchParams.set('lang', document.documentElement.lang === 'fr' ? 'en' : 'fr');
    window.location.assign(url.pathname + url.search + url.hash);
  });
})();
