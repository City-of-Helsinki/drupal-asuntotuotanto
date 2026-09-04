(($, Drupal) => {
  Drupal.behaviors.applicationPayments = {
    attach: function attach() {
      const getApplicationIdFromElement = (element) => {
        return $(element).closest('[data-application]').data('application');
      };

      const showPayments = (applicationId) => {
        $(`.application__payments-table[data-application="${applicationId}"]`).removeClass('is-hidden');
        $(`.application__payments-link--toggle[data-application="${applicationId}"]`)
          .closest('.application--action')
          .addClass('is-hidden');
        $(`#application__payments--hide--submitted[data-application="${applicationId}"]`).removeClass('is-hidden');
      };

      const hidePayments = (applicationId) => {
        $(`.application__payments-table[data-application="${applicationId}"]`).addClass('is-hidden');
        $(`.application__payments-link--toggle[data-application="${applicationId}"]`)
          .closest('.application--action')
          .removeClass('is-hidden');
        $(`#application__payments--hide--submitted[data-application="${applicationId}"]`).addClass('is-hidden');
      };

      document.querySelectorAll('.application__payments-link--toggle').forEach((element) => {
        element.addEventListener('click', (event) => {
          const applicationId = getApplicationIdFromElement(event.currentTarget);
          showPayments(applicationId);
        });
      });

      document.querySelectorAll('.application__payments-link--hide').forEach((element) => {
        element.addEventListener('click', (event) => {
          const applicationId = getApplicationIdFromElement(event.currentTarget);
          hidePayments(applicationId);
        });
      });
    },
  };
})(jQuery, Drupal);
