(($, Drupal) => {
  Drupal.behaviors.apartmentsListItemToggle = {
    attach: function attach() {
      const apartmentsListingElement = document.getElementsByClassName(
        "project__apartments"
      );
      const applicationListingElement = document.getElementsByClassName(
        "application__apartments"
      );
      const buttonElements = document.getElementsByClassName(
        "project__apartments-item-button"
      );
      let currentWindowWidth = window.innerWidth;

      const handleWindowWidthChange = () => {
        currentWindowWidth = window.innerWidth;

        [...apartmentsListingElement].forEach((element) => {
          if (element.classList.contains("project__apartments--mobile")) {
            if (currentWindowWidth < 1248) {
              element.setAttribute("aria-hidden", false);
            } else {
              element.setAttribute("aria-hidden", true);
            }
          }

          if (element.classList.contains("project__apartments--desktop")) {
            if (currentWindowWidth >= 1248) {
              element.setAttribute("aria-hidden", false);
            } else {
              element.setAttribute("aria-hidden", true);
            }
          }
        });

        [...applicationListingElement].forEach((element) => {
          if (element.classList.contains("application__apartments--mobile")) {
            if (currentWindowWidth < 1248) {
              element.setAttribute("aria-hidden", false);
            } else {
              element.setAttribute("aria-hidden", true);
            }
          }

          if (element.classList.contains("application__apartments--desktop")) {
            if (currentWindowWidth >= 1248) {
              element.setAttribute("aria-hidden", false);
            } else {
              element.setAttribute("aria-hidden", true);
            }
          }
        });
      };

      const handleClick = ({ target }) => {
        const { nextElementSibling } = target;
        const nextElementSiblingClassList = nextElementSibling.classList;

        if (nextElementSiblingClassList.contains("is-hidden")) {
          nextElementSiblingClassList.remove("is-hidden");
          nextElementSibling.setAttribute("aria-hidden", false);
          target.setAttribute("aria-expanded", true);
        } else {
          nextElementSiblingClassList.add("is-hidden");
          nextElementSibling.setAttribute("aria-hidden", true);
          target.setAttribute("aria-expanded", false);
        }
      };

      [...buttonElements].forEach((button) =>
        button.addEventListener("click", handleClick)
      );

      handleWindowWidthChange();
      window.addEventListener("resize", handleWindowWidthChange);
    },
  };
})(jQuery, Drupal);
