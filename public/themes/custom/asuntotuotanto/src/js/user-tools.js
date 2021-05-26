((Drupal, $) => {
  Drupal.behaviors.userTools = {
    attach: function attach(context) {
      const userToolsToggleButtonElement = $(".user-tools__button", context);
      const userToolsWrapperElement = $(".user-tools__dropdown", context);

      const outsideClickListener = function outsideClickListener(event) {
        const target = $(event.target);

        if (
          !target.closest(".user-tools__dropdown").length &&
          $(".user-tools__dropdown").is(":visible")
        ) {
          // eslint-disable-next-line no-use-before-define
          handleInteraction(event);
          // eslint-disable-next-line no-use-before-define
          removeClickListener();
        }
      };

      const removeClickListener = function removeClickListener() {
        document.removeEventListener("click", outsideClickListener);
      };

      function handleInteraction(e) {
        e.stopImmediatePropagation();

        if (userToolsWrapperElement.attr("aria-hidden") === "false") {
          userToolsWrapperElement.attr("aria-hidden", "true");
          userToolsToggleButtonElement.attr("aria-expanded", "false");
        } else {
          userToolsWrapperElement.attr("aria-hidden", "false");
          userToolsToggleButtonElement.attr("aria-expanded", "true");
          document.addEventListener("click", outsideClickListener);
        }
      }

      userToolsToggleButtonElement.on({
        click: function touchstartclick(e) {
          handleInteraction(e);
        },
        keydown: function keydown(e) {
          if (e.which === 27) {
            userToolsWrapperElement.attr("aria-hidden", "true");
            userToolsToggleButtonElement.attr("aria-expanded", "false");
            removeClickListener();
          }
        },
      });
    },
  };
})(Drupal, jQuery);
