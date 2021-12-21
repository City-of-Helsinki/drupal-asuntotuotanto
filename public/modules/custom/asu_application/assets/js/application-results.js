((Drupal,$) => {
  Drupal.behaviors.applicantFormToggle = {
    attach: function attach(context, settings) {
      const elements = document.getElementsByClassName('asu-results');
      Array.from(elements).forEach(function(element, index, array) {
        element.addEventListener('click', (event) => {
          const element = $(event.target)
          if (element.data('loaded') == 1) {
            return;
          }
          const id = element.data('application');
          $.ajax({
            url: "application/results",
            method : 'POST',
            dataType: 'json',
            data: {
              'application_id': id
            },
            success: function(results){
              element.data('loaded', 1);
              // Update elements
              Array.from(results[0]).forEach(function(apartment_result, index, array) {
                let apartment_class = '.application-apartment-' + apartment_result.apartment_id;
                Array.from($('.lottery-result' + apartment_class)).forEach(function(result_row) {
                  $(result_row).find('.result').first().html(apartment_result.lottery_position);
                  $(result_row).find('.current-position').first().html(apartment_result.position);
                  $(result_row).find('.status').first().html(apartment_result.status);
                });
              });
            },
            failed: function(result){
              // Do nothing.
            },
          });
        });
      });
    },
  };
})(Drupal, jQuery);
