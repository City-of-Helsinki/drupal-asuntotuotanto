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

      const renderMarkedStatus = (table, payment) => {
        if (!payment.is_marked_paid) {
          return '';
        }

        const markedPaidLabel = table.dataset.markedPaidLabel || 'Marked as paid';
        const markedAt = payment.marked_paid_at ? ` ${escapeHtml(payment.marked_paid_at)}` : '';
        return `${escapeHtml(markedPaidLabel)}${markedAt}`;
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
        const markPaidLabel = table.dataset.markPaidLabel || 'Mark as paid';

        if (!Array.isArray(payments) || payments.length === 0) {
          tbody.innerHTML = `<tr><td colspan="6">${escapeHtml(emptyLabel)}</td></tr>`;
          return;
        }

        tbody.innerHTML = payments.map((payment) => {
          const checked = payment.is_marked_paid ? 'checked' : '';
          const paymentId = Number(payment.payment_id || 0);
          return `<tr>
            <td>${escapeHtml(payment.installment_type || '-')}</td>
            <td>${escapeHtml(payment.amount || '-')}</td>
            <td>${escapeHtml(payment.due_date || '-')}</td>
            <td>${escapeHtml(payment.account_number || '-')}</td>
            <td>${escapeHtml(payment.reference_number || '-')}</td>
            <td>
              <label>
                <input
                  type="checkbox"
                  class="application__payment-mark-checkbox"
                  data-payment-id="${paymentId}"
                  ${checked}
                >
                ${escapeHtml(markPaidLabel)}
              </label>
              <div class="application__payment-mark-status">${renderMarkedStatus(table, payment)}</div>
            </td>
          </tr>`;
        }).join('');
      };

      const updateMarkStatusInRow = (table, checkbox, responseData) => {
        const row = checkbox.closest('tr');
        if (!row) {
          return;
        }

        const statusContainer = row.querySelector('.application__payment-mark-status');
        if (!statusContainer) {
          return;
        }

        statusContainer.innerHTML = renderMarkedStatus(table, responseData);
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
        $(`.application__payments-note[data-application="${applicationId}"]`).removeClass('is-hidden');
        $(`.application__payments-link--toggle[data-application="${applicationId}"]`)
          .closest('.application--action')
          .addClass('is-hidden');
        $(`#application__payments--hide--submitted[data-application="${applicationId}"]`).removeClass('is-hidden');
      };

      const hidePayments = (applicationId) => {
        $(`.application__payments-table[data-application="${applicationId}"]`).addClass('is-hidden');
        $(`.application__payments-note[data-application="${applicationId}"]`).addClass('is-hidden');
        $(`.application__payments-link--toggle[data-application="${applicationId}"]`)
          .closest('.application--action')
          .removeClass('is-hidden');
        $(`#application__payments--hide--submitted[data-application="${applicationId}"]`).addClass('is-hidden');
      };

      const bindMarkHandlers = () => {
        document.querySelectorAll('.application__payments-table').forEach((table) => {
          if (table.dataset.markHandlerBound === '1') {
            return;
          }
          table.dataset.markHandlerBound = '1';

          table.addEventListener('change', (event) => {
            const checkbox = event.target.closest('.application__payment-mark-checkbox');
            if (!checkbox) {
              return;
            }

            const applicationId = Number(table.dataset.application || 0);
            const paymentId = Number(checkbox.dataset.paymentId || 0);
            const marked = checkbox.checked;

            if (!applicationId || !paymentId) {
              checkbox.checked = !marked;
              return;
            }

            checkbox.disabled = true;

            $.ajax({
              url: 'application/payments/mark',
              method: 'POST',
              dataType: 'json',
              data: {
                application_id: applicationId,
                payment_id: paymentId,
                marked,
              },
            })
              .done((response) => {
                if (!response || response.success !== true) {
                  checkbox.checked = !marked;
                  return;
                }
                checkbox.checked = !!response.is_marked_paid;
                updateMarkStatusInRow(table, checkbox, response);
              })
              .fail(() => {
                checkbox.checked = !marked;
              })
              .always(() => {
                checkbox.disabled = false;
              });
          });
        });
      };

      bindMarkHandlers();

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
