(($, Drupal) => {
  Drupal.behaviors.showcaseGallery = {
    attach: function attach() {
      $("#showcase_gallery").slick({
        slidesToShow: 1,
        slidesToScroll: 1,
      });
    },
  };
})(jQuery, Drupal);
