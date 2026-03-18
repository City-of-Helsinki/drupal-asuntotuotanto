(($, Drupal) => {
  Drupal.behaviors.applicationApartmentListing = {
    attach: function attach() {
      const getApplicationIdFromElement = (element) => {
        return $(element).closest('[data-application]').data('application');
      };

      const valueOrDash = (value) => {
        if (value === null || value === undefined || value === '') {
          return '-';
        }
        return value;
      };

      const formatDateValue = (value) => {
        if (!value) {
          return '-';
        }

        const formatDateObject = (date) => {
          const day = String(date.getDate()).padStart(2, '0');
          const month = String(date.getMonth() + 1).padStart(2, '0');
          const year = date.getFullYear();
          return `${day}.${month}.${year}`;
        };

        const dateMatch = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (dateMatch) {
          return `${dateMatch[3]}.${dateMatch[2]}.${dateMatch[1]}`;
        }

        const date = new Date(value);
        if (!Number.isNaN(date.getTime())) {
          return formatDateObject(date);
        }

        return value;
      };

      const resolveQueuePosition = (apartmentResult) => {
        if (apartmentResult.state === 'canceled') {
          return '-';
        }
        return valueOrDash(apartmentResult.queue_position);
      };

      const resolveOfferStatus = (apartmentResult) => {
        if (!apartmentResult.offer) {
          return '-';
        }
        return valueOrDash(apartmentResult.offer.state_label || apartmentResult.offer.state);
      };

      const resolveOfferValidUntil = (apartmentResult) => {
        if (!apartmentResult.offer) {
          return '-';
        }
        return formatDateValue(apartmentResult.offer.valid_until);
      };

      const resolveCancellationInfo = (apartmentResult) => {
        const actorLabel = apartmentResult.cancellation_actor_label || '';
        const reason = apartmentResult.cancellation_reason_label || apartmentResult.cancellation_reason || '';

        if (actorLabel && reason) {
          return `${actorLabel}: ${reason}`;
        }

        if (actorLabel) {
          return actorLabel;
        }

        if (reason) {
          return reason;
        }

        return '-';
      };

      const resolveCancellationTime = (apartmentResult) => {
        return formatDateValue(apartmentResult.cancellation_timestamp);
      };

      const getResultRows = (apartmentResult) => {
        if (apartmentResult.apartment_id) {
          let apartmentClass = '.application-apartment-' + apartmentResult.apartment_id;
          const classRows = Array.from(jQuery('.lottery-result' + apartmentClass));
          if (classRows.length) {
            return classRows;
          }
        }

        if (apartmentResult.apartment_uuid) {
          const apartmentUuid = String(apartmentResult.apartment_uuid).toLowerCase();
          return Array.from(document.querySelectorAll(`.lottery-result[data-apartment-uuid="${apartmentUuid}"]`));
        }

        return [];
      };

      // For drafts.
      let openDraftResultsLinks = document.querySelectorAll('.application__lottery--show--draft');
      openDraftResultsLinks.forEach(element => {
        element.addEventListener('click', event => {
          const id = getApplicationIdFromElement(event.currentTarget);
          const elements = document.querySelectorAll(`[data-application="${id}"]`);
          elements.forEach(element=>$(element).removeClass('is-hidden'));
          $(event.currentTarget).addClass('is-hidden');
        });
      })
      let closeDraftResultsLinks = document.querySelectorAll('.application__lottery--hide--draft > a');
      closeDraftResultsLinks.forEach(el => el.addEventListener('click', (event) => {
        const id = getApplicationIdFromElement(event.currentTarget);
        const elementsToHide = document.querySelectorAll(`[data-application="${id}"]`);
        elementsToHide.forEach(element=>$(element).addClass('is-hidden'));
        $(event.currentTarget).parent().addClass('is-hidden');
        $(`.application__lottery--show--draft[data-application="${id}"]`).removeClass('is-hidden');
      }));

      // For submitted applications.
      let openResultsLinks = document.querySelectorAll('.application__lottery-link--show--submitted');
      openResultsLinks.forEach(element=>{
        element.addEventListener("click", (event) => {
          let id = getApplicationIdFromElement(event.currentTarget);
          $(event.currentTarget).addClass('throbber');

          // Check if we have already loaded the data.
          if ($(event.currentTarget).data('loaded') != 1) {
            getApartmentResults(event,
              () => {
                const elements = document.querySelectorAll(`[data-application="${id}"]`);
                elements.forEach(el=>$(el).removeClass('is-hidden'))
                $(event.currentTarget).removeClass('throbber');
                $(event.currentTarget).parent().addClass('is-hidden');
                $(`#application__lottery--hide--submitted[data-application="${id}"]`).removeClass('is-hidden');
              });

          }
          else {
            $(event.currentTarget).removeClass('throbber');
            $(event.currentTarget).parent().addClass('is-hidden');
            $(`.application__lottery-results-submitted[data-application="${id}"]`).removeClass('is-hidden');
            $(`#application__lottery--hide--submitted[data-application="${id}"]`).removeClass('is-hidden');
          }
        });
      })

      let hideButtonLinks = document.querySelectorAll('.application__lottery-link--hide');
      hideButtonLinks.forEach(element=>{
        // hide functionality on all hide buttons
        element.addEventListener("click", (event) => {
          let id = getApplicationIdFromElement(event.currentTarget);
          $(`.application__lottery-results-submitted[data-application="${id}"]`).addClass('is-hidden');
          $(`.application__lottery--show[data-application="${id}"]`).removeClass('is-hidden');
          $(event.currentTarget).parent().addClass('is-hidden');
        });
      })

      const getApartmentResults = (event, callback) => {
        const element = jQuery(event.currentTarget)

        element.data('loaded', 1);
        const id = getApplicationIdFromElement(event.currentTarget);
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
                getResultRows(apartment_result).forEach(function(result_row) {
                  jQuery(result_row).find('.result').first().html(apartment_result.position);
                  jQuery(result_row).find('.status').first().html(apartment_result.status);
                  jQuery(result_row).find('.queue-position').first().html(resolveQueuePosition(apartment_result));
                  jQuery(result_row).find('.offer-status').first().html(resolveOfferStatus(apartment_result));
                  jQuery(result_row).find('.offer-valid-until').first().html(resolveOfferValidUntil(apartment_result));
                  jQuery(result_row).find('.cancellation-info').first().html(resolveCancellationInfo(apartment_result));
                  jQuery(result_row).find('.cancellation-time').first().html(resolveCancellationTime(apartment_result));
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

      let toggle = document.querySelectorAll('.application__lottery-link--toggle');
      toggle.forEach(element => {
        element.addEventListener('click', event => {
          let id = $(event.currentTarget).data('application');
          $('#result-'+id).each(function(element){
            this.click();
          });
        })
      });

    },
  };
})(jQuery, Drupal);
