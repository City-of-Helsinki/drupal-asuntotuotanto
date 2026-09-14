(($, Drupal) => {
  Drupal.behaviors.applicationPayments = {
    attach: function attach() {
      const getApplicationIdFromElement = (element) => {
        return $(element).closest('[data-application]').data('application');
      };

      const escapeHtml = (value) => {
        return String(value ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      };

      const renderPayments = (applicationId, payments) => {
        const table = document.querySelector(`.application__payments-table[data-application="${applicationId}"]`);
        if (!table) {
          return;
        }

        const tbody = table.querySelector('tbody');
        if (!tbody) {
          return;
        }

        const emptyLabel = table.dataset.emptyLabel || 'No payments';

        if (!Array.isArray(payments) || payments.length === 0) {
          tbody.innerHTML = `<tr><td colspan="5">${escapeHtml(emptyLabel)}</td></tr>`;
          return;
        }

        tbody.innerHTML = payments.map((payment) => {
          return `<tr>
            <td>${escapeHtml(payment.installment_type || '-')}</td>
            <td>${escapeHtml(payment.amount || '-')}</td>
            <td>${escapeHtml(payment.due_date || '-')}</td>
            <td>${escapeHtml(payment.account_number || '-')}</td>
            <td>${escapeHtml(payment.reference_number || '-')}</td>
          </tr>`;
        }).join('');
      };

      const fetchPayments = (applicationId, callback) => {
        $.ajax({
          url: 'application/payments',
          method: 'POST',
          dataType: 'json',
          data: {
            application_id: applicationId,
            no_cache: 1,
          },
        })
          .done((payments) => {
            renderPayments(applicationId, payments);
          })
          .always(() => {
            if (typeof callback === 'function') {
              callback();
            }
          });
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
          $(event.currentTarget).addClass('throbber');
          fetchPayments(applicationId, () => {
            $(event.currentTarget).removeClass('throbber');
            showPayments(applicationId);
          });
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
