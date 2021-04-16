(($, Drupal, drupalSettings) => {
  Drupal.behaviors.asuAdminCommon = {
    attach: function attach() {
      // Code here.
    },
  };

  Drupal.behaviors.languageSwitcher = {
    attach: function attach(context) {
      const languageSwitcherToggleButton = $(
        ".language-switcher__button",
        context
      );
      const languageSwitcherWrapper = $(
        ".language-switcher__dropdown",
        context
      );

      const outsideClickListener = function outsideClickListener(event) {
        const target = $(event.target);

        if (
          !target.closest(".language-switcher__dropdown").length &&
          $(".language-switcher__dropdown").is(":visible")
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

  Drupal.behaviors.stickyNavigation = {
    attach: function attach() {
      const stickyNavigationElement = document.getElementById(
        "sticky_navigation"
      );
      const headerElement = document.getElementsByTagName("header")[0];
      const apartmentHeaderElement = document.getElementsByClassName(
        "apartment__header"
      )[0];
      const apartmentAnchorNavigationElement = document.getElementsByClassName(
        "apartment__anchor-navigation--desktop"
      )[0];

      let currentWindowWidth = window.innerWidth;

      const handleWindowWidthUpdate = () => {
        currentWindowWidth = window.innerWidth;
      };

      const toggleStickyNavigation = () => {
        const headerElementOffsetTop =
          headerElement.offsetTop + headerElement.offsetHeight;
        const apartmentHeaderElementHeight =
          apartmentHeaderElement.offsetHeight;
        const apartmentHeaderElementOffsetTop =
          apartmentHeaderElement.offsetTop + apartmentHeaderElementHeight;

        const apartmentAnchorNavigationElementHeight =
          apartmentAnchorNavigationElement.offsetHeight;
        const apartmentAnchorNavigationElementOffsetTop =
          apartmentAnchorNavigationElement.offsetTop +
          apartmentAnchorNavigationElementHeight;

        // Mobile & Tablet functionality.
        if (currentWindowWidth < 992) {
          if (
            window.scrollY >
            apartmentHeaderElementOffsetTop - apartmentHeaderElementHeight / 2
          ) {
            if (stickyNavigationElement.classList.contains("is-hidden")) {
              stickyNavigationElement.classList.remove("is-hidden");
              stickyNavigationElement.setAttribute("aria-hidden", false);
            }
          }

          if (window.scrollY < headerElementOffsetTop) {
            if (!stickyNavigationElement.classList.contains("is-hidden")) {
              stickyNavigationElement.classList.add("is-hidden");
              stickyNavigationElement.setAttribute("aria-hidden", true);
            }
          }
        }

        // Desktop functionality.
        if (currentWindowWidth > 992) {
          if (window.scrollY > apartmentAnchorNavigationElementOffsetTop) {
            if (stickyNavigationElement.classList.contains("is-hidden")) {
              stickyNavigationElement.classList.remove("is-hidden");
              stickyNavigationElement.setAttribute("aria-hidden", false);
            }
          }

          if (window.scrollY < apartmentAnchorNavigationElementOffsetTop) {
            if (!stickyNavigationElement.classList.contains("is-hidden")) {
              stickyNavigationElement.classList.add("is-hidden");
              stickyNavigationElement.setAttribute("aria-hidden", true);
            }
          }
        }
      };

      window.addEventListener("resize", () => handleWindowWidthUpdate());
      window.addEventListener("scroll", () => toggleStickyNavigation());
    },
  };
})(jQuery, Drupal, drupalSettings);
