(($, Drupal) => {
  Drupal.behaviors.applicationApartmentListing = {
    attach: function attach() {
      const offerStrings = drupalSettings.asuApplicationOffer || {};

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

        const dateMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value));
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

      const canRespondToOffer = (apartmentResult) => {
        return apartmentResult.state === 'offered'
          && apartmentResult.offer
          && apartmentResult.offer.state === 'pending'
          && apartmentResult.offer.is_expired !== true;
      };

      const submitOfferAction = (offerId, action, applicationId, apartmentResult) => {
        const baseUrl = (window.drupalSettings && drupalSettings.path && drupalSettings.path.baseUrl) ? drupalSettings.path.baseUrl : '/';
        jQuery.ajax({
          url: `${baseUrl}user/offer/${offerId}/${action}`,
          method: 'POST',
          dataType: 'json',
          data: {
            application_id: applicationId,
          },
          success: function(response) {
            if (response && response.success) {
              apartmentResult.state = action === 'accept' ? 'offer_accepted' : 'canceled';
              if (apartmentResult.offer) {
                apartmentResult.offer.state = action === 'accept' ? 'accepted' : 'rejected';
                apartmentResult.offer.state_label = action === 'accept' ? 'accepted' : 'rejected';
              }
              getResultRows(apartmentResult).forEach(function(result_row) {
                jQuery(result_row).find('.status').first().html(
                  apartmentResult.status ? apartmentResult.status.replace(/_/g, ' ') : '-'
                );
                jQuery(result_row).find('.offer-status').first().html(resolveOfferStatus(apartmentResult));
                const actions = result_row.querySelector('.offer-actions');
                if (actions) {
                  actions.innerHTML = '';
                }
              });
              jQuery(`.application__lottery-link--toggle[data-application="${applicationId}"]`).data('loaded', 0);
            }
          },
        });
      };

      const createHdsButton = (label, type, onClick) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `hds-button hds-button--${type}`;
        const labelSpan = document.createElement('span');
        labelSpan.className = 'hds-button__label';
        labelSpan.textContent = label;
        button.appendChild(labelSpan);
        button.addEventListener('click', onClick);
        return button;
      };

      const renderOfferActions = (apartmentResult, applicationId) => {
        if (!canRespondToOffer(apartmentResult)) {
          return;
        }

        getResultRows(apartmentResult).forEach(function(result_row) {
          let actions = result_row.querySelector('.offer-actions');
          if (!actions) {
            actions = document.createElement('div');
            actions.className = 'offer-actions';
            result_row.appendChild(actions);
          }
          actions.innerHTML = '';

          actions.appendChild(createHdsButton(
            offerStrings.acceptOffer || 'Accept offer',
            'primary',
            () => {
              if (window.confirm(offerStrings.confirmAccept || 'Are you sure you want to accept this offer?')) {
                submitOfferAction(apartmentResult.offer.id, 'accept', applicationId, apartmentResult);
              }
            }
          ));

          actions.appendChild(createHdsButton(
            offerStrings.rejectOffer || 'Reject offer',
            'secondary',
            () => {
              if (window.confirm(offerStrings.confirmReject || 'Are you sure you want to reject this offer?')) {
                submitOfferAction(apartmentResult.offer.id, 'reject', applicationId, apartmentResult);
              }
            }
          ));
        });
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

      const showSubmittedLotteryResults = (id) => {
        document.querySelectorAll(`[data-application="${id}"]`).forEach((el) => {
          $(el).removeClass('is-hidden');
        });
        $(`.application__lottery-results-submitted[data-application="${id}"]`).removeClass('is-hidden');
        $(`.application__lottery-link--toggle[data-application="${id}"]`).closest('.application--action').addClass('is-hidden');
        $(`#application__lottery--hide--submitted[data-application="${id}"]`).removeClass('is-hidden');
      };

      // For submitted applications: use the HDS "Show lottery results" button only.
      document.querySelectorAll('.application__lottery-link--toggle').forEach((element) => {
        element.addEventListener('click', (event) => {
          const id = getApplicationIdFromElement(event.currentTarget);
          $(event.currentTarget).addClass('throbber');

          if ($(event.currentTarget).data('loaded') != 1) {
            getApartmentResults(event, () => {
              showSubmittedLotteryResults(id);
            });
          }
          else {
            $(event.currentTarget).removeClass('throbber');
            showSubmittedLotteryResults(id);
          }
        });
      });

      let hideButtonLinks = document.querySelectorAll('.application__lottery-link--hide');
      hideButtonLinks.forEach(element=>{
        // hide functionality on all hide buttons
        element.addEventListener("click", (event) => {
          let id = getApplicationIdFromElement(event.currentTarget);
          $(`.application__lottery-results-submitted[data-application="${id}"]`).addClass('is-hidden');
          $(`.application__lottery-link--toggle[data-application="${id}"]`).closest('.application--action').removeClass('is-hidden');
          $(event.currentTarget).parent().addClass('is-hidden');
        });
      })

      const getApartmentResults = (event, callback) => {
        const trigger = jQuery(event.currentTarget);
        const id = getApplicationIdFromElement(event.currentTarget);

        jQuery.ajax({
          url: 'application/results',
          method: 'POST',
          dataType: 'json',
          data: {
            application_id: id,
          },
        })
          .done(function(results) {
            if (results && results.length) {
              Array.from(results).forEach(function(apartment_result) {
                getResultRows(apartment_result).forEach(function(result_row) {
                  jQuery(result_row).find('.result').first().html(apartment_result.position);
                  jQuery(result_row).find('.status').first().html(apartment_result.status);
                  jQuery(result_row).find('.queue-position').first().html(resolveQueuePosition(apartment_result));
                  jQuery(result_row).find('.offer-status').first().html(resolveOfferStatus(apartment_result));
                  jQuery(result_row).find('.offer-valid-until').first().html(resolveOfferValidUntil(apartment_result));
                  jQuery(result_row).find('.cancellation-info').first().html(resolveCancellationInfo(apartment_result));
                  jQuery(result_row).find('.cancellation-time').first().html(resolveCancellationTime(apartment_result));
                  renderOfferActions(apartment_result, id);
                });
              });
            }
            trigger.data('loaded', 1);
          })
          .fail(function() {
            trigger.data('loaded', 0);
          })
          .always(function() {
            trigger.removeClass('throbber');
            if (typeof callback === 'function') {
              callback();
            }
          });
      };

    },
  };
})(jQuery, Drupal);
