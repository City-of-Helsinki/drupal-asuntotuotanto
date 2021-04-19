(($, Drupal) => {
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
})(jQuery, Drupal);
