
jQuery(document).ready(function () {
  jQuery('#mlp-elmnts-button').on('click', function (e) {
    e.preventDefault();

    jQuery('#mlp-flyout').toggleClass('opened');
    jQuery('#mlp-overlay').toggle();

    return false;
  }); // open/close menu

  jQuery('#mlp-overlay').on('click', function (e) {
    e.preventDefault();

    jQuery(this).hide();
    jQuery('#mlp-flyout').removeClass('opened');

    return false;
  }); // click on overlay - hide menu
}); // jQuery ready
  