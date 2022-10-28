(($, Drupal) => {
  Drupal.behaviors.intenseImages = {
    attach: function attach() {
      // Intensify all slide/carousel images (make them fullscreen on click/touch).
      const elements = document.querySelectorAll(
        ".slick .media picture img"
      );
      Intense(elements);
    },
  };
})(jQuery, Drupal);
