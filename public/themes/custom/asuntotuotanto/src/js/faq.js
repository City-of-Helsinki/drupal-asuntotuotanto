((Drupal) => {
  Drupal.behaviors.faq = {
    attach: function attach() {
      const faqButtonElements = document.getElementsByClassName(
        "faq-teaser__button"
      );

      const handleClick = ({ target }) => {
        if (target.getAttribute("aria-expanded") === "false") {
          // Reset all FAQ elements.
          [...faqButtonElements].forEach((button) => {
            button.setAttribute("aria-expanded", false);
            button.nextElementSibling.setAttribute("aria-hidden", true);
          });

          target.setAttribute("aria-expanded", true);
          target.nextElementSibling.setAttribute("aria-hidden", false);
        } else {
          target.setAttribute("aria-expanded", false);
          target.nextElementSibling.setAttribute("aria-hidden", true);
        }
      };

      if (faqButtonElements) {
        [...faqButtonElements].map((button) =>
          button.addEventListener("click", handleClick)
        );
      }
    },
  };
})(Drupal);
