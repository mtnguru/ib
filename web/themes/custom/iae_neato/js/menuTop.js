!function (o) {
  'use strict';
  Drupal.menuTopC = function () {
    var e = o('#js-navigation-menu'), n = o('#js-mobile-menu'),
      s = o('.js-navigation-more');

    function u() {
      var n;
      0 < s.length && ((n = o(window).width() - s.offset().left) < 330 && (o('.js-navigation-more .submenu .submenu').removeClass('fly-out-right'), o('.js-navigation-more .submenu .submenu').addClass('fly-out-left')), 330 < n && (o('.js-navigation-more .submenu .submenu').removeClass('fly-out-left'), o('.js-navigation-more .submenu .submenu').addClass('fly-out-right')));
    }

    return { init: function () {u(), o(window).resize(function () {u();}), e.removeClass('show'), n.unbind(), s.click(function () {o(this).hasClass('menu-open') ? o(this).removeClass('menu-open').find('.submenu').slideUp() : (s.each(function () {o(this).hasClass('menu-open') && o(this).removeClass('menu-open').find('.submenu').slideUp();}), o(this).addClass('menu-open').find('.submenu').slideDown());}), n.on('click', function (n) {n.preventDefault(), e.hasClass('nav-open') ? e.slideUp().removeClass('nav-open') : e.slideDown().addClass('nav-open');});} };
  }, Drupal.behaviors.menuTop = { attach: function (n, e) {o(n).find('#site-header').once('menuTopAttached').each(function () {setTimeout(n => {Drupal.menuTop || (Drupal.menuTop = Drupal.menuTopC()), Drupal.menuTop.init(this);}, 1e3);});} };
}(jQuery);
//# sourceMappingURL=maps/menuTop.js.map
