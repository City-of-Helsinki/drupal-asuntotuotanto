(($, Drupal) => {
  Drupal.behaviors.applicationApartmentListing = {
    attach: function attach() {
      // Variables for Drafted Applications
      const applicationLotteryDivShow = document.querySelector(
        "#application__lottery--show--draft"
      );
      const applicationLotteryDivHide = document.querySelector(
        "#application__lottery--hide--draft"
      );
      const applicationLotteryLinkShow = document.querySelector(
        "#application__lottery-link--show--draft"
      );
      const applicationLotteryLinkHide = document.querySelector(
        "#application__lottery-link--hide--draft"
      );
      const applicationLotteryResults = document.querySelectorAll(
        "#application__lottery-results--draft"
      );

      // Variables for Submitted Applications
      const applicationLotteryDivShowSubmitted = document.querySelector(
        "#application__lottery--show--submitted"
      );
      const applicationLotteryDivHideSubmitted = document.querySelector(
        "#application__lottery--hide--submitted"
      );
      const applicationLotteryLinkShowSubmitted = document.querySelector(
        "#application__lottery-link--show--submitted"
      );
      const applicationLotteryLinkHideSubmitted = document.querySelector(
        "#application__lottery-link--hide--submitted"
      );
      const applicationLotteryResultsSubmitted = document.querySelectorAll(
        "#application__lottery-results--submitted"
      );

      // Functionality for Drafted Applications
      applicationLotteryLinkShow.addEventListener("click", () => {
        // Show lottery results (or: Array.from(applicationLotteryResults)...)
        [...applicationLotteryResults].forEach((el) =>
          el.classList.remove("is-hidden")
        );

        // Hide 'Show apartment listing'
        applicationLotteryDivShow.classList.add("is-hidden");

        // Show 'Hide apartment listing'
        applicationLotteryDivHide.classList.remove("is-hidden");
      });

      applicationLotteryLinkHide.addEventListener("click", () => {
        // Hide lottery results
        [...applicationLotteryResults].forEach((el) =>
          el.classList.add("is-hidden")
        );

        // Show 'Show apartment listing'
        applicationLotteryDivShow.classList.remove("is-hidden");

        // Hide 'Hide apartment listing'
        applicationLotteryDivHide.classList.add("is-hidden");
      });

      // Functionality for Submitted Applications
      applicationLotteryLinkShowSubmitted.addEventListener("click", () => {
        // Show lottery results (or: Array.from(applicationLotteryResults)...)
        [...applicationLotteryResultsSubmitted].forEach((el) =>
          el.classList.remove("is-hidden")
        );

        // Hide 'Show apartment listing'
        applicationLotteryDivShowSubmitted.classList.add("is-hidden");

        // Show 'Hide apartment listing'
        applicationLotteryDivHideSubmitted.classList.remove("is-hidden");
      });

      applicationLotteryLinkHideSubmitted.addEventListener("click", () => {
        // Hide lottery results
        [...applicationLotteryResultsSubmitted].forEach((el) =>
          el.classList.add("is-hidden")
        );

        // Show 'Show apartment listing'
        applicationLotteryDivShowSubmitted.classList.remove("is-hidden");

        // Hide 'Hide apartment listing'
        applicationLotteryDivHideSubmitted.classList.add("is-hidden");
      });
    },
  };
})(jQuery, Drupal);
