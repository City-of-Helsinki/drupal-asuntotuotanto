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
      // wräppäävä divi - näytä tulokset napille
      let applicationLotteryDivShowSubmitted = document.querySelector(
        "#application__lottery--show--submitted"
      );

      // wräppäävä divi - piilota tulokset napille
      let applicationLotteryDivHideSubmitted = document.querySelector(
        "#application__lottery--hide--submitted"
      );

      // näytä napin LINKKI
      let applicationLotteryLinkShowSubmitted = document.querySelector(
        "#application__lottery-link--show--submitted"
      );

      // poistan napin LINKKI
      let applicationLotteryLinkHideSubmitted = document.querySelector(
        "#application__lottery-link--hide--submitted"
      );

      // kaikki tulokset
      let applicationLotteryResultsSubmitted = document.querySelectorAll(
        "#application__lottery-results--submitted"
      );

      // when show result is clicked
      applicationLotteryLinkShowSubmitted.addEventListener("click", (event) => {
        // hide/show buttons states
        // applicationLotteryLinkShowSubmitted.classList.add("is-hidden");
        // applicationLotteryLinkHideSubmitted.classList.remove("is-hidden");
        applicationLotteryLinkShowSubmitted.classList.add('throbber');
        // applicationLotteryDivShowSubmitted.classList.add('is-hidden');

        // After results are here.data('loaded') == 1
        if (applicationLotteryLinkShowSubmitted.data('loaded') != 1) {
          getApartmentResults(event,
            (text) => {
              console.log(text);
              [...document.querySelectorAll(
                "#application__lottery-results--submitted"
              )].forEach(
                (element)=>{element.classList.remove('is-hidden')}
              );
              applicationLotteryDivShowSubmitted.classList.add('is-hidden');
              applicationLotteryDivHideSubmitted.classList.remove('is-hidden');
              applicationLotteryLinkShowSubmitted.classList.remove('throbber');
            });
        }
      });

      applicationLotteryLinkHideSubmitted.addEventListener("click", () => {
        applicationLotteryDivShowSubmitted.classList.remove('is-hidden');
        applicationLotteryLinkHideSubmitted.classList.add('is-hidden');
        [...applicationLotteryResultsSubmitted.classList()].forEach((element) => {element.classList.add('is-hidden')});
      })

      /*
      jQuery('#application__lottery-link--show--submitted').on('click', function() {

        getApartmentResults();

      })
      */

      /*
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
      */
      /*
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
      */

      console.log('is updated');

      if (applicationLotteryLinkShowSubmitted !== null) {
        // Functionality for Submitted Applications
        applicationLotteryLinkShowSubmitted.addEventListener("click", (event) => {
          [...applicationLotteryResultsSubmitted].forEach((el) => {
              //getApartmentResults(event, function(){ el.classList.remove("is-hidden"); });
            });

          // Hide 'Show apartment listing'
         //applicationLotteryDivShowSubmitted.classList.add("is-hidden");
          // Show 'Hide apartment listing'
          //applicationLotteryDivHideSubmitted.classList.remove("is-hidden");
          event.stopPropagation()
        });
      }

      if (applicationLotteryLinkHideSubmitted !== null) {
        applicationLotteryLinkHideSubmitted.addEventListener("click", (event) => {
          // Hide lottery results
          //[...applicationLotteryResultsSubmitted].forEach((el) => el.classList.add("is-hidden"));

          // Show 'Show apartment listing'
          //applicationLotteryDivShowSubmitted.classList.remove("is-hidden");

          // Hide 'Hide apartment listing'
          //applicationLotteryDivHideSubmitted.classList.add("is-hidden");
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
              Array.from(results).forEach(function(apartment_result, index, array) {
                let apartment_class = '.application-apartment-' + apartment_result.apartment_id;
                Array.from(jQuery('.lottery-result' + apartment_class)).forEach(function(result_row) {
                  jQuery(result_row).find('.result').first().html(apartment_result.position);
                  jQuery(result_row).find('.current-position').first().html(apartment_result.current_position);
                  jQuery(result_row).find('.status').first().html(apartment_result.status);
                });
              });
            }
            // Hide 'Show apartment listing'
            document.querySelector(
              "#application__lottery--show--submitted"
            ).classList.add("is-hidden");

            // Show 'Hide apartment listing'
            document.querySelector(
              "#application__lottery--show--submitted"
            ).classList.remove("is-hidden");
            cb('success');
          },
          failed: function(results){
            cb('failed');
          },
          complete: function() {
            // Hide 'Show apartment listing'
            document.querySelector(
              "#application__lottery--show--submitted"
            ).classList.add("is-hidden");

            // Show 'Hide apartment listing'
            document.querySelector(
              "#application__lottery--show--submitted"
            ).classList.remove("is-hidden");
            cb('finished');
          }
        });

      };
    },
  };
})(jQuery, Drupal);
