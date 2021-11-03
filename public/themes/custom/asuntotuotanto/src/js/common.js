((Drupal, $) => {
  Drupal.behaviors.headerDesktopSubmenu = {
    attach: function attach() {
      const headerElement = document.getElementsByClassName("page-header")[0];
      const mainMenuDesktopItemElements = document
        .getElementsByClassName("menu--main-menu is-desktop")[0]
        .getElementsByClassName("menu__item");
      const currentWindowWidth = window.innerWidth;

      if (mainMenuDesktopItemElements) {
        const hasActiveHeaderSubmenu = [
          ...mainMenuDesktopItemElements,
        ].some((item) => item.classList.contains("is-active"));

        if (hasActiveHeaderSubmenu && currentWindowWidth >= 992) {
          headerElement.classList.add("has-submenu");
        }
      }
    },
  };

  Drupal.behaviors.mobileMenuToggle = {
    attach: function attach() {
      const bodyElement = document.getElementsByTagName("body")[0];
      const headerBackgroundShadowElement = document.getElementsByClassName(
        "page-header__background-shadow"
      )[0];

      const mobileNavigationToggleButtonElement = document.getElementById(
        "mobile_navigation_toggle_button"
      );

      const mobileNavigationElement = document.getElementsByClassName(
        "page-header__bottom"
      )[0];

      const mobileSubmenuButtonElements = document.getElementsByClassName(
        "sub-menu__button"
      );

      const desktopNavigationElement = document.getElementById(
        "block-menu-main-desktop"
      );

      const userToolsNavigationElement = document.getElementById(
        "block-usertools-desktop"
      );

      const handleMobileNavigationClose = () => {
        mobileNavigationElement.setAttribute("aria-hidden", true);
        mobileNavigationToggleButtonElement.setAttribute(
          "aria-expanded",
          false
        );
        bodyElement.style.overflow = "visible";
        mobileNavigationElement.classList.add("is-hidden");
      };

      const handleMobileNavigationToggleClick = ({ target }) => {
        if (mobileNavigationElement.classList.contains("is-hidden")) {
          mobileNavigationElement.setAttribute("aria-hidden", false);
          target.setAttribute("aria-expanded", true);
          bodyElement.style.overflow = "hidden";
        } else {
          mobileNavigationElement.setAttribute("aria-hidden", true);
          target.setAttribute("aria-expanded", false);
          bodyElement.style.overflow = "visible";
        }

        mobileNavigationElement.classList.toggle("is-hidden");
      };

      const handleSubmenuToggleClick = ({ target }) => {
        [...mobileSubmenuButtonElements].forEach((element) => {
          if (element !== target) {
            element.parentElement.nextElementSibling.classList.add("is-hidden");
            element.parentElement.nextElementSibling.setAttribute(
              "aria-hidden",
              true
            );
            element.setAttribute("aria-expanded", false);
          }
        });

        const submenuElement = target.parentElement.nextElementSibling;

        if (target.getAttribute("aria-expanded") === "false") {
          submenuElement.classList.remove("is-hidden");
          submenuElement.setAttribute("aria-hidden", false);
          target.setAttribute("aria-expanded", true);
        } else {
          submenuElement.classList.add("is-hidden");
          submenuElement.setAttribute("aria-hidden", true);
          target.setAttribute("aria-expanded", false);
        }
      };

      let currentWindowWidth = window.innerWidth;

      if (currentWindowWidth > 992) {
        mobileNavigationToggleButtonElement.setAttribute("aria-hidden", true);
        desktopNavigationElement.setAttribute("aria-hidden", false);
        userToolsNavigationElement.setAttribute("aria-hidden", false);
      } else {
        mobileNavigationToggleButtonElement.setAttribute("aria-hidden", false);
        desktopNavigationElement.setAttribute("aria-hidden", true);
        userToolsNavigationElement.setAttribute("aria-hidden", true);
      }

      window.addEventListener("resize", () => {
        currentWindowWidth = window.innerWidth;

        if (currentWindowWidth > 992) {
          mobileNavigationToggleButtonElement.setAttribute("aria-hidden", true);
          desktopNavigationElement.setAttribute("aria-hidden", false);
          userToolsNavigationElement.setAttribute("aria-hidden", false);
          handleMobileNavigationClose();
        } else {
          mobileNavigationToggleButtonElement.setAttribute(
            "aria-hidden",
            false
          );
          desktopNavigationElement.setAttribute("aria-hidden", true);
          userToolsNavigationElement.setAttribute("aria-hidden", true);
        }
      });

      mobileNavigationToggleButtonElement.addEventListener(
        "click",
        handleMobileNavigationToggleClick
      );

      headerBackgroundShadowElement.addEventListener(
        "click",
        handleMobileNavigationClose
      );

      if (mobileSubmenuButtonElements) {
        [...mobileSubmenuButtonElements].forEach((element) => {
          element.addEventListener("click", handleSubmenuToggleClick);
        });
      }
    },
  };

  Drupal.behaviors.languageSwitcher = {
    attach: function attach(context) {
      const languageSwitcherToggleButton = $(".lang-switcher__button", context);
      const languageSwitcherWrapper = $(".lang-switcher__dropdown", context);

      let currentWindowWidth = window.innerWidth;

      if (currentWindowWidth > 992) {
        languageSwitcherWrapper.attr("aria-hidden", "true");
        languageSwitcherToggleButton.attr("aria-expanded", "false");
        languageSwitcherToggleButton.attr("aria-hidden", "false");
      } else {
        languageSwitcherWrapper.attr("aria-hidden", "false");
        languageSwitcherToggleButton.attr("aria-hidden", "true");
      }

      window.addEventListener("resize", () => {
        currentWindowWidth = window.innerWidth;

        if (currentWindowWidth > 992) {
          languageSwitcherWrapper.attr("aria-hidden", "true");
          languageSwitcherToggleButton.attr("aria-expanded", "false");
          languageSwitcherToggleButton.attr("aria-hidden", "false");
        } else {
          languageSwitcherWrapper.attr("aria-hidden", "false");
          languageSwitcherToggleButton.attr("aria-hidden", "true");
        }
      });

      const outsideClickListener = function outsideClickListener(event) {
        const target = $(event.target);

        if (
          !target.closest(".lang-switcher__dropdown").length &&
          $(".lang-switcher__dropdown").is(":visible")
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

        if (languageSwitcherWrapper.attr("aria-hidden") === "false") {
          languageSwitcherWrapper.attr("aria-hidden", "true");
          languageSwitcherToggleButton.attr("aria-expanded", "false");
        } else {
          languageSwitcherWrapper.attr("aria-hidden", "false");
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
            languageSwitcherWrapper.attr("aria-hidden", "true");
            languageSwitcherToggleButton.attr("aria-expanded", "false");
            removeClickListener();
          }
        },
      });
    },
  };
})(Drupal, jQuery);
