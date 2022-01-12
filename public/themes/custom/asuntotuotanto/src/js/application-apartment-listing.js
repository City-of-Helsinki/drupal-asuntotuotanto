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

      if (applicationLotteryLinkShow !== null) {
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
      }

      if (applicationLotteryLinkHide !== null) {
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
      }

      if (applicationLotteryLinkShowSubmitted !== null) {
        // Functionality for Submitted Applications
        applicationLotteryLinkShowSubmitted.addEventListener("click", (event) => {
          // Show lottery results (or: Array.from(applicationLotteryResults)...)
          [...applicationLotteryResultsSubmitted].forEach((el) => {
              getApartmentResults(event, function(){ el.classList.remove("is-hidden"); });
            });

          // Hide 'Show apartment listing'
          applicationLotteryDivShowSubmitted.classList.add("is-hidden");
          // Show 'Hide apartment listing'
          applicationLotteryDivHideSubmitted.classList.remove("is-hidden");
          event.stopPropagation()
        });
      }

      if (applicationLotteryLinkHideSubmitted !== null) {
        applicationLotteryLinkHideSubmitted.addEventListener("click", (event) => {
          // Hide lottery results
          [...applicationLotteryResultsSubmitted].forEach((el) => el.classList.add("is-hidden"));

          // Show 'Show apartment listing'
          applicationLotteryDivShowSubmitted.classList.remove("is-hidden");

          // Hide 'Hide apartment listing'
          applicationLotteryDivHideSubmitted.classList.add("is-hidden");
        });
      }

      const getApartmentResults = (event, cb) => {
        const element = jQuery(event.target)

        if (element.data('loaded') == 1) {
          cb();
          return;
        }
        element.data('loaded', 1);
        const id = element.data('application');

        jQuery.ajax({
          url: "application/results",
          method : 'POST',
          dataType: 'json',
          data: {
            'application_id': id
          },
          success: function(results) {
            // Update elements
            if (results && results.length) {
              Array.from(results[0]).forEach(function(apartment_result, index, array) {
                let apartment_class = '.application-apartment-' + apartment_result.apartment_id;
                Array.from(jQuery('.lottery-result' + apartment_class)).forEach(function(result_row) {
                  jQuery(result_row).find('.result').first().html(apartment_result.position);
                  jQuery(result_row).find('.current-position').first().html(apartment_result.current_position);
                  jQuery(result_row).find('.status').first().html(apartment_result.status);
                });
              });
            }
            cb();
          },
          failed: function(result){
            cb();
          },
        });

      };
    },
  };
})(jQuery, Drupal);
