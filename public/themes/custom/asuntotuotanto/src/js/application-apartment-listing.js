(($, Drupal) => {
  Drupal.behaviors.applicationApartmentListing = {
    attach: function attach() {
      // For drafts.
      let openDraftResultsLinks = document.querySelectorAll('.application__lottery--show--draft');
      openDraftResultsLinks.forEach(element => {
        element.addEventListener('click', event=> {
          const id = $(event.target).parent().data('application')
          const elements = document.querySelectorAll(`[data-application="${id}"]`)
          elements.forEach(element=>$(element).removeClass('is-hidden'));
          $(event.target).parent().addClass('is-hidden');
        });
      })
      let closeDraftResultsLinks = document.querySelectorAll('.application__lottery--hide--draft  > a');
      closeDraftResultsLinks.forEach(el => el.addEventListener('click', (event) => {
        const id = $(event.target).parent().data('application')
        const elementsToHide = document.querySelectorAll(`[data-application="${id}"]`);
        elementsToHide.forEach(element=>$(element).addClass('is-hidden'));
        $(event.target).parent().addClass('is-hidden');
        $(`.application__lottery--show--draft[data-application="${id}"]`).removeClass('is-hidden');
      }));

      // For submitted applications.
      let openResultsLinks = document.querySelectorAll('.application__lottery-link--show--submitted');
      openResultsLinks.forEach(element=>{
        element.addEventListener("click", (event) => {
          let id = $(event.target).parent().data('application');
          $(event.target).addClass('throbber');

          // Check if we have already loaded the data.
          if ($(event.target).data('loaded') != 1) {
            getApartmentResults(event,
              () => {
                const elements = document.querySelectorAll(`[data-application="${id}"]`);
                elements.forEach(el=>$(el).removeClass('is-hidden'))
                $(event.target).removeClass('throbber');
                $(event.target).parent().addClass('is-hidden');
                $(`#application__lottery--hide--submitted[data-application="${id}"]`).removeClass('is-hidden');
              });
            
          }
          else {
            $(event.target).removeClass('throbber');
            $(event.target).parent().addClass('is-hidden');
            $(`.application__lottery-results-submitted[data-application="${id}"]`).removeClass('is-hidden');
            $(`#application__lottery--hide--submitted[data-application="${id}"]`).removeClass('is-hidden');
          }
        });
      })

      let hideButtonLinks = document.querySelectorAll('.application__lottery-link--hide');
      hideButtonLinks.forEach(element=>{
        // hide functionality on all hide buttons
        element.addEventListener("click", (event) => {
          let id = $(event.target).parent().data('application');
          $(`.application__lottery-results-submitted[data-application="${id}"]`).addClass('is-hidden');
          $(`.application__lottery--show[data-application="${id}"]`).removeClass('is-hidden');
          $(event.target).parent().addClass('is-hidden');
        });
      })

      const getApartmentResults = (event, callback) => {
        const element = jQuery(event.target)

        element.data('loaded', 1);
        const id = element.parent().data('application');
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
            callback();
          },
          failed: function(results){
            callback();
          },
          complete: function() {
            callback();
          }
        });
      };
    },
  };
})(jQuery, Drupal);
