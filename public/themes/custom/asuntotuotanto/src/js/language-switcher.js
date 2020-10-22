"use strict";

(function ($, Drupal) {
  Drupal.behaviors.languageSwitcher = {
    attach: function attach(context) {
      var languageSwitcherToggleButton = $(
        ".language-switcher__button",
        context
      );
      var languageSwitcherWrapper = $(".language-switcher__dropdown", context);

      var outsideClickListener = function outsideClickListener(event) {
        var target = $(event.target);

        if (
          !target.closest(".language-switcher__dropdown").length &&
          $(".language-switcher__dropdown").is(":visible")
        ) {
          handleInteraction(event);
          removeClickListener();
        }
      };

      var removeClickListener = function removeClickListener() {
        document.removeEventListener("click", outsideClickListener);
      };

      function handleInteraction(e) {
        e.stopImmediatePropagation();

        if (languageSwitcherWrapper.hasClass("is-active")) {
          languageSwitcherWrapper
            .removeClass("is-active")
            .attr("aria-hidden", "true");
          languageSwitcherToggleButton.attr("aria-expanded", "false");
        } else {
          languageSwitcherWrapper
            .addClass("is-active")
            .attr("aria-hidden", "false");
          languageSwitcherToggleButton.attr("aria-expanded", "true");
          document.addEventListener("click", outsideClickListener);
        }
      }

      languageSwitcherToggleButton.on({
        click: function touchstartclick(e) {
          handleInteraction(e);
        },
        keydown: function keydown(e) {
          if (e.which === 27) {
            languageSwitcherWrapper
              .removeClass("is-active")
              .attr("aria-hidden", "true");
            languageSwitcherToggleButton.attr("aria-expanded", "false");
            removeClickListener();
          }
        },
      });
    },
  };
})(jQuery, Drupal);
