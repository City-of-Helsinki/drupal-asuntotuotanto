(($, Drupal) => {
  Drupal.behaviors.showcaseGallery = {
    attach: function attach() {
      const $carousels = $("#showcase_gallery, .js-homepage-highlights-carousel")
        .not(".slick-initialized");

      $carousels.on("init", function initHomepageHighlights(event) {
        const $carousel = $(event.currentTarget);

        if ($carousel.hasClass("js-homepage-highlights-carousel")) {
          $carousel.css("width", "100%");
          $carousel.parent().css("width", "100%");
          $carousel.find(".slick-list, .slick-track").css("width", "100%");
          $carousel.find(".slick-list").css("max-height", "600px");
          $carousel.find(".slick-prev").css("left", "-64px");
          $carousel.find(".slick-next").css("right", "-64px");
        }
      });

      $carousels.slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        infinite: false,
      });
    },
  };
})(jQuery, Drupal);
