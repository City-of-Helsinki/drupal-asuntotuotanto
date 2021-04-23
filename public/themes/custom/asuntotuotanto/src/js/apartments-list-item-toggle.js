(($, Drupal) => {
  Drupal.behaviors.apartmentsListItemToggle = {
    attach: function attach() {
      const buttonElements = document.getElementsByClassName(
        "project__apartments-item-button"
      );

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
    },
  };
})(jQuery, Drupal);
