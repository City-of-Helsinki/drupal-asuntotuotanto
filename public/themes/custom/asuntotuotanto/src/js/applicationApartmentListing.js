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
        return apartmentResult.offer
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

      const renderOfferActionsInto = (actionsContainer, apartmentResult, applicationId) => {
        if (!actionsContainer) {
          return;
        }
        if (!canRespondToOffer(apartmentResult)) {
          actionsContainer.innerHTML = '';
          return;
        }

        actionsContainer.innerHTML = '';
        actionsContainer.appendChild(createHdsButton(
          offerStrings.acceptOffer || 'Accept offer',
          'primary',
          () => {
            if (window.confirm(offerStrings.confirmAccept || 'Are you sure you want to accept this offer?')) {
              submitOfferAction(apartmentResult.offer.id, 'accept', applicationId, apartmentResult);
            }
          }
        ));

        actionsContainer.appendChild(createHdsButton(
          offerStrings.rejectOffer || 'Reject offer',
          'secondary',
          () => {
            if (window.confirm(offerStrings.confirmReject || 'Are you sure you want to reject this offer?')) {
              submitOfferAction(apartmentResult.offer.id, 'reject', applicationId, apartmentResult);
            }
          }
        ));
      };

      const renderOfferActions = (apartmentResult, applicationId) => {
        if (!canRespondToOffer(apartmentResult)) {
          return;
        }

        getResultRows(apartmentResult).forEach(function(result_row) {
          const actions = result_row.querySelector('.offer-actions');
          // Only render actions in places that explicitly include an actions container
          // (e.g. offer list/table, mobile result cards). Do not inject actions into
          // the per-application results table.
          if (!actions) {
            return;
          }
          renderOfferActionsInto(actions, apartmentResult, applicationId);
        });
      };

      const resolveCancellationInfo = (apartmentResult) => {
        // Cancellation info is confusing/noisy in offer context on /user/applications.
        // If an offer exists for the apartment, hide cancellation details.
        if (apartmentResult.offer) {
          return '-';
        }

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
        if (apartmentResult.offer) {
          return '-';
        }
        return formatDateValue(apartmentResult.cancellation_timestamp);
      };

      const getProjectName = (applicationId) => {
        const article = document.querySelector(`article[data-application="${applicationId}"]`);
        return article ? String(article.getAttribute('data-project-name') || '') : '';
      };

      const parseApartmentNumberForSort = (value) => {
        const raw = String(value || '').trim().toUpperCase();
        if (!raw) {
          return { prefix: '', number: Number.MAX_SAFE_INTEGER, suffix: '', raw: '' };
        }
        const prefixMatch = /^[^\d\s-]+/.exec(raw);
        const prefix = prefixMatch ? prefixMatch[0] : '';
        const rest = raw.slice(prefix.length).trimStart();
        const numberMatch = /^0*(\d+)/.exec(rest);
        const number = numberMatch
          ? Number.parseInt(numberMatch[1], 10)
          : Number.MAX_SAFE_INTEGER;
        const suffix = rest.slice(numberMatch ? numberMatch[0].length : 0);
        return { prefix, number, suffix, raw };
      };

      const compareApartmentNumbers = (a, b) => {
        const pa = parseApartmentNumberForSort(a);
        const pb = parseApartmentNumberForSort(b);

        if (pa.prefix !== pb.prefix) return pa.prefix.localeCompare(pb.prefix);
        if (pa.number !== pb.number) return pa.number - pb.number;
        if (pa.suffix !== pb.suffix) return pa.suffix.localeCompare(pb.suffix);
        return pa.raw.localeCompare(pb.raw);
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

      const offersState = (() => {
        const offersByApartmentUuid = new Map();

        const getOffersTableElements = () => {
          return {
            table: document.querySelector('#application-offers-table'),
            tbody: document.querySelector('#application-offers-table tbody'),
            list: document.querySelector('#application-offers-list'),
            empty: document.querySelector('#application-offers-empty'),
          };
        };

        const getApartmentCellFromDom = (applicationId, apartmentUuid) => {
          const uuid = String(apartmentUuid || '').toLowerCase();
          const selectors = [
            `article[data-application="${applicationId}"] .lottery-result--desktop[data-apartment-uuid="${uuid}"] td`,
            `article[data-application="${applicationId}"] .lottery-result[data-apartment-uuid="${uuid}"] td`,
          ];

          for (const selector of selectors) {
            const cell = document.querySelector(selector);
            if (cell) {
              return { html: cell.innerHTML, text: cell.textContent || '' };
            }
          }
          return { html: '', text: '' };
        };

        const extractApartmentNumber = (apartmentCellText) => {
          const trimmed = String(apartmentCellText || '').trim();
          if (!trimmed) return '';
          return trimmed.split(/\s+/)[0];
        };

        const updateFromResults = (applicationId, results) => {
          const projectName = getProjectName(applicationId);
          if (!Array.isArray(results)) {
            return;
          }

          results.forEach((apartmentResult) => {
            const apartmentUuid = String(apartmentResult.apartment_uuid || '').toLowerCase();
            if (!apartmentUuid) {
              return;
            }

            if (!apartmentResult.offer) {
              offersByApartmentUuid.delete(apartmentUuid);
              return;
            }

            const apartmentCell = getApartmentCellFromDom(applicationId, apartmentUuid);
            offersByApartmentUuid.set(apartmentUuid, {
              applicationId,
              apartmentUuid,
              projectName,
              apartmentNumber: extractApartmentNumber(apartmentCell.text || apartmentResult.apartment),
              apartmentCellHtml: apartmentCell.html,
              apartmentResult,
            });
          });
        };

        const render = () => {
          const { table, tbody, list, empty } = getOffersTableElements();
          if (!table || !tbody || !list || !empty) {
            return;
          }

          const offers = Array.from(offersByApartmentUuid.values());
          if (!offers.length) {
            table.classList.add('is-hidden');
            table.setAttribute('aria-hidden', 'true');
            list.classList.add('is-hidden');
            list.setAttribute('aria-hidden', 'true');
            empty.classList.remove('is-hidden');
            return;
          }

          offers.sort((a, b) => {
            const projectCompare = String(a.projectName || '').localeCompare(String(b.projectName || ''));
            if (projectCompare !== 0) return projectCompare;
            return compareApartmentNumbers(a.apartmentNumber, b.apartmentNumber);
          });

          tbody.innerHTML = '';
          list.innerHTML = '';
          offers.forEach((offer) => {
            const tr = document.createElement('tr');
            tr.className = 'lottery-result lottery-result--desktop';
            tr.setAttribute('data-apartment-uuid', offer.apartmentUuid);

            const tdProject = document.createElement('td');
            tdProject.textContent = offer.projectName || '';

            const tdApartment = document.createElement('td');
            tdApartment.innerHTML = offer.apartmentCellHtml || offer.apartmentNumber || '';

            const tdStatus = document.createElement('td');
            tdStatus.className = 'offer-status';
            tdStatus.textContent = resolveOfferStatus(offer.apartmentResult);

            const tdValidUntil = document.createElement('td');
            tdValidUntil.className = 'offer-valid-until';
            tdValidUntil.textContent = resolveOfferValidUntil(offer.apartmentResult);

            const tdActions = document.createElement('td');
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'offer-actions';
            tdActions.appendChild(actionsContainer);

            tr.appendChild(tdProject);
            tr.appendChild(tdApartment);
            tr.appendChild(tdStatus);
            tr.appendChild(tdValidUntil);
            tr.appendChild(tdActions);
            tbody.appendChild(tr);

            renderOfferActionsInto(actionsContainer, offer.apartmentResult, offer.applicationId);

            // Mobile card variant.
            const li = document.createElement('li');
            li.className = 'lottery-result lottery-result--mobile application__result-card';
            li.setAttribute('data-apartment-uuid', offer.apartmentUuid);

            const h3 = document.createElement('h3');
            h3.className = 'application__result-card-title';
            h3.textContent = offer.projectName || '';

            const p = document.createElement('p');
            p.className = 'application__result-card-subtitle';
            p.textContent = offer.apartmentNumber || '';

            const ul = document.createElement('ul');
            ul.className = 'application__result-attrs';

            const makeAttr = (labelText, valueText, valueClass) => {
              const liAttr = document.createElement('li');
              liAttr.className = 'application__result-attr';

              const label = document.createElement('span');
              label.className = 'application__result-label';
              label.textContent = labelText;

              const value = document.createElement('span');
              value.className = `application__result-value ${valueClass}`;
              value.textContent = valueText;

              liAttr.appendChild(label);
              liAttr.appendChild(value);
              return liAttr;
            };

            ul.appendChild(makeAttr(
              offerStrings.offerStatusLabel || 'Offer status',
              resolveOfferStatus(offer.apartmentResult),
              'offer-status'
            ));
            ul.appendChild(makeAttr(
              offerStrings.offerValidUntilLabel || 'Offer valid until',
              resolveOfferValidUntil(offer.apartmentResult),
              'offer-valid-until'
            ));

            const mobileActions = document.createElement('div');
            mobileActions.className = 'offer-actions';
            renderOfferActionsInto(mobileActions, offer.apartmentResult, offer.applicationId);

            li.appendChild(h3);
            li.appendChild(p);
            li.appendChild(ul);
            li.appendChild(mobileActions);
            list.appendChild(li);
          });

          empty.classList.add('is-hidden');
          table.classList.remove('is-hidden');
          table.setAttribute('aria-hidden', 'false');
          list.classList.remove('is-hidden');
          list.setAttribute('aria-hidden', 'false');
        };

        return { updateFromResults, render };
      })();

      const loadOffersForAllApplications = () => {
        if (!document.querySelector('#application-offers-table')) {
          return;
        }

        const applicationArticles = Array.from(document.querySelectorAll('article[data-application]'));
        if (!applicationArticles.length) {
          offersState.render();
          return;
        }

        let pending = applicationArticles.length;
        applicationArticles.forEach((article) => {
          const id = $(article).data('application');
          if (!id) {
            pending -= 1;
            if (pending === 0) {
              offersState.render();
            }
            return;
          }

          jQuery.ajax({
            url: 'application/results',
            method: 'POST',
            dataType: 'json',
            data: {
              application_id: id,
              no_cache: 1,
            },
          })
            .done(function(results) {
              offersState.updateFromResults(id, results);
            })
            .always(function() {
              pending -= 1;
              if (pending === 0) {
                offersState.render();
              }
            });
        });
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
            offersState.updateFromResults(id, results);
            offersState.render();
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

      // Populate the offers table on initial page load.
      loadOffersForAllApplications();

    },
  };
})(jQuery, Drupal);
