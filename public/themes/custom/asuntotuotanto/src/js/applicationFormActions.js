(($, Drupal) => {
      jQuery(document).ajaxStop(() => {
        // Prevent race condition by keeping buttons disabled as long as necessary.
        const button = $('.application-form-apartment__apartment-add-button');
        const customApartmentSelect = $('[data-drupal-selector="custom_apartment_select"]');
        if (button && customApartmentSelect.length == 0) {
          button.first().attr("disabled", false);
        }

        // Prevent add new button from staying disabled after ajax.
        const el = $("[id^=apartment_list_select_]");
        if (el.length > 0 && el.is(':disabled')) {
          el.prop('disabled', false);
        }
      });

      const lang = drupalSettings.path.currentLanguage;
      const translation = {
        "Delete": {
          "fi": "Poista",
          "en": "Delete",
        },
        "Open": {
          "fi": "Katso huoneistoa",
          "en": "Open apartment page",
        },
        "Apartment": {
          "fi": "huoneisto",
          "en": "apartment",
        },
        "Add": {
          "fi": "Lisää uusi huoneisto",
          "en": "Add an apartment to the list"
        },
        "Updated": {
          "fi": "Huoneistojen järjestys on päivitetty",
          "en": "Apartment list order has been updated."
        },
        "ApartmentAdded": {
          "fi": "Lisätty huoneisto listaukseen.",
          "en": "Added to the apartment list."
        },
        "ApartmentRemoved": {
          "fi": "Poistettu huoneisto listauksesta.",
          "en": "Removed from the apartment list."
        }
      };

      const screenReaderInformationBoxElement = document.getElementsByClassName(
        "sr-information-box"
      )[0];

      const applicationFormApartmentListElement = document.getElementById(
        "application_form_apartments_list"
      );

      const getApplicationFormApartmentListElementCount = () =>
        applicationFormApartmentListElement.getElementsByClassName(
          "application-form__apartments-item"
        ).length;

      const getLastOriginalApartmentSelectElement = () => {
        const originalApartmentSelectElement = document.querySelector(
          '[data-drupal-selector="edit-apartment-0-id"]'
        );

        const originalApartmentSelectElementWrapper =
          originalApartmentSelectElement.parentElement.parentElement
            .parentElement.parentElement.parentElement;

        const selectCount =
          originalApartmentSelectElementWrapper.children.length;

        const lastSelectParent =
          originalApartmentSelectElementWrapper.children[selectCount - 1];

        return lastSelectParent.getElementsByTagName("select")[0];
      };

      const getOriginalSelectElementValues = () => {
        const selectElements = document.querySelectorAll(
          '[data-drupal-selector="edit-apartment"] > table select'
        );

        return [...selectElements]
          .filter((select) =>
            select.getAttribute("data-drupal-selector").includes("-id")
          )
          .map((select) => select.value)
          .filter((selectValue) => selectValue !== "0");
      };

      function setActionButtonState(actionButtonElements, isFirst, isLast, isNextWithSelect) {
        if (!actionButtonElements[0] || !actionButtonElements[1]) return;
        if (isFirst && !isNextWithSelect) {
          actionButtonElements[0].disabled = true;
          actionButtonElements[1].disabled = false;
        } else if (isLast) {
          actionButtonElements[0].disabled = false;
          actionButtonElements[1].disabled = true;
        } else if (isNextWithSelect) {
          actionButtonElements[0].disabled = false;
          actionButtonElements[1].disabled = true;
        } else {
          actionButtonElements[0].disabled = false;
          actionButtonElements[1].disabled = false;
        }
      }

      const detectMutations = (mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.type !== "childList") return;
          const listItem = document.getElementsByClassName("application-form__apartments-item");
          const total = getApplicationFormApartmentListElementCount();

          [...listItem].forEach((item, index) => {
            if (item.classList.contains("application-form__apartments-item--with-select")) return;

            const actionButtonElements = item.querySelectorAll(
              "button[data-list-position-action-button]"
            );
            const isFirst = index === 0;
            const isLast = index === total - 1;
            const isNextWithSelect =
              item.nextElementSibling &&
              item.nextElementSibling.classList &&
              item.nextElementSibling.classList.contains(
                "application-form__apartments-item--with-select"
              );

            setActionButtonState(actionButtonElements, isFirst, isLast, isNextWithSelect);
          });
        });
      };



      const applicationFormApartmentListObserver = new MutationObserver(
        detectMutations
      );

      applicationFormApartmentListObserver.observe(
        applicationFormApartmentListElement,
        { childList: true }
      );

      const createParagraphElementWithVisuallyHiddenText = (
        classes,
        hiddenTextString,
        visibleString
      ) => {
        const p = document.createElement("p");
        p.classList.add(...classes);

        const span1 = document.createElement("span");
        const span1Content = document.createTextNode(
          Drupal.t(hiddenTextString)
        );
        span1.classList.add("visually-hidden");
        span1.appendChild(span1Content);

        const span2 = document.createElement("span");
        const span2Content = document.createTextNode(visibleString);
        span2.appendChild(span2Content);

        p.append(span1, span2);

        return p;
      };

      const createButtonElement = (classes, content, disabled = false) => {
        const button = document.createElement("button");
        button.classList.add(...classes);
        const span = document.createElement("span");
        const text = document.createTextNode(Drupal.t(content));

        span.append(text);
        button.append(span);

        button.setAttribute("type", "button");

        if (disabled) button.disabled = true;

        return button;
      };

      const createListItemElementWithText = (description, value) => {
        const liElement = document.createElement("li");
        const span1 = document.createElement("span");
        const text1 = document.createTextNode(Drupal.t(description));
        span1.appendChild(text1);

        const span2 = document.createElement("span");
        const text2 = document.createTextNode(Drupal.t(value));
        span2.appendChild(text2);

        liElement.append(span1, span2);

        return liElement;
      };

      const setFocusToLastSelectElement = () => {
        const allCustomSelectElements = document.querySelectorAll(
          '[data-drupal-selector="custom_apartment_select"]'
        );

        const customSelectCount = allCustomSelectElements.length;

        const lastCustomSelect = allCustomSelectElements[customSelectCount - 1];

        lastCustomSelect.focus();
      };

      const createCustomSelectElement = () => {
        const apartmentListElementWrapper = document.createElement("div");
        apartmentListElementWrapper.classList.add(
          "application-form-apartment__apartment-add-actions-wrapper"
        );

        const selectCount = getApplicationFormApartmentListElementCount() - 1;
        const selectElementId = `apartment_list_select_${selectCount}`;

        const apartmentListElement = document.createElement("div");
        apartmentListElement.classList.add("hds-select-element");

        const apartmentSelectElementLabel = document.createElement("label");
        const apartmentSelectElementLabelText = document.createTextNode(
          translation["Apartment"][lang]
        );
        apartmentSelectElementLabel.appendChild(
          apartmentSelectElementLabelText
        );

        apartmentSelectElementLabel.setAttribute("for", selectElementId);

        const apartmentSelectElementWrapper = document.createElement("div");
        apartmentSelectElementWrapper.classList.add(
          "hds-select-element__select-wrapper"
        );

        const selectedApartments = getOriginalSelectElementValues();

        const apartmentSelectElement = getLastOriginalApartmentSelectElement().cloneNode(
          true
        );

        // eslint-disable-next-line array-callback-return
        [...apartmentSelectElement.options].map((option, index) => {
          if (index > 0) {
            const originalTextSplitted = option.innerHTML.split(" | ");
            option.innerHTML = `${originalTextSplitted[0]} | ${originalTextSplitted[1]} | ${originalTextSplitted[3]}`;
          }
        });

        apartmentSelectElement.classList.add("hds-select-element__select");
        apartmentSelectElement.setAttribute("id", selectElementId);
        apartmentSelectElement.setAttribute("data-id", selectCount);
        apartmentSelectElement.setAttribute(
          "data-drupal-selector",
          "custom_apartment_select"
        );

        if (
          apartmentSelectElement.id != "apartment_list_select_0" &&
          $.active != 0
        ) {
          apartmentSelectElement.setAttribute("disabled", true);
        }

        const earlierSelectedOptions = [
          ...apartmentSelectElement.options,
        ].filter((option) => selectedApartments.includes(option.value));

        earlierSelectedOptions.forEach((option) => {
          apartmentSelectElement.removeChild(option);
        });

        apartmentSelectElement.addEventListener("change", ({ target }) => {
          const originalSelectElementTarget = document.querySelector(
            `[data-drupal-selector="edit-apartment-${selectCount}-id"]`
          );

          const apartmentAddButton = document.getElementsByClassName(
            "application-form-apartment__apartment-add-button"
          )[0];

          const targetParent =
            target.parentElement.parentElement.parentElement.parentElement
              .parentElement.parentElement;
          targetParent.setAttribute("data-id", selectCount);

          originalSelectElementTarget.value = target.value;
          originalSelectElementTarget.dispatchEvent(new Event("change"));

          targetParent.classList.remove(
            "application-form__apartments-item--with-select"
          );

          const selectedValueTextArray = originalSelectElementTarget.options[
            originalSelectElementTarget.selectedIndex
          ].text.split(" | ");
          const listItemValues = {
            apartment_number: selectedValueTextArray[0],
            apartment_structure: selectedValueTextArray[1],
            apartment_floor: selectedValueTextArray[2],
            apartment_living_area_size: selectedValueTextArray[3],
            apartment_debt_free_sales_price: selectedValueTextArray[4],
            apartment_sales_price: selectedValueTextArray[5],
          };

          // eslint-disable-next-line no-use-before-define
          targetParent.innerHTML = createApartmentListItem(
            listItemValues,
            target.value
          ).innerHTML;

          const information = document.createElement("p");
          information.appendChild(
            document.createTextNode(
              translation["ApartmentAdded"][lang]
            )
          );
          screenReaderInformationBoxElement.append(information);

          const index = [...targetParent.parentElement.children].indexOf(
            targetParent
          );

          if (index === 0) {
            targetParent.querySelector("button").disabled = true;
          }

          if (index === getApplicationFormApartmentListElementCount() - 1) {
            targetParent.querySelector(
              'button[data-list-position-action-button="lower"]'
            ).disabled = true;
          }

          if (index > 0) {
            targetParent.previousElementSibling.querySelector(
              'button[data-list-position-action-button="lower"]'
            ).disabled = false;
          }

          if (
            targetParent.nextElementSibling &&
            targetParent.nextElementSibling.classList &&
            targetParent.nextElementSibling.classList.contains(
              "application-form__apartments-item--with-select"
            )
          ) {
            targetParent.querySelector(
              'button[data-list-position-action-button="lower"]'
            ).disabled = true;
          }

          if (apartmentAddButton) {
            if ($.active == 0) {
              apartmentAddButton.removeAttribute("disabled");
            }
            apartmentAddButton.focus();
          }
        });

        apartmentSelectElementWrapper.appendChild(apartmentSelectElement);

        apartmentListElement.append(
          apartmentSelectElementLabel,
          apartmentSelectElementWrapper
        );
        apartmentListElementWrapper.appendChild(apartmentListElement);

        return apartmentListElementWrapper;
      };

      const createElementWithClasses = (tag, classes = []) => {
        const element = document.createElement(tag);
        element.classList.add(...classes);

        return element;
      };

      const swapOriginalSelectWeights = (select1Id, select2Id) => {
        const select1WeigthElement = document.querySelector(
          `[name="apartment[${select1Id}][_weight]"]`
        );
        const select2WeigthElement = document.querySelector(
          `[name="apartment[${select2Id}][_weight]"]`
        );

        const select1Weigth = select1WeigthElement.value;
        select1WeigthElement.value = select2WeigthElement.value;
        select2WeigthElement.value = select1Weigth;
      };

      const handleListPositionRaiseClick = (target) => {
        const parent = target.parentElement.parentElement.parentElement;
        const sibling = parent.previousElementSibling;

        if (sibling !== null) {
          sibling.before(parent);

          const apartment = findMatchingApartmentName(target);
          const originalSelectElementTarget = findMatchingOriginalSelectElementByName(apartment);

          // After updating the new list, update the whole old list.
          jQuery('#application_form_apartments_list').children('li').each((index, element) => {
            const id = `#edit-apartment-${index}-weight`

            // Find the number of the apartment.
            const numberElement = jQuery(element)
              .first()
              .find('.application-form-apartment__apartment-number')
              .first()[0]

            if (numberElement != undefined) {
              const number = $(numberElement)
                .find('span')
                .last()[0].textContent;
              const originalSelectElements = getOriginalSelectedElements()
              const originalDropdown = originalSelectElements.filter(function(){
                return jQuery(this).find('option:selected').text().includes(number)
              });
              // Find data-drupal-selector attribute with value ending "-weight"
              originalDropdown.first().closest('tr').find('[data-drupal-selector$=-weight]')[0].value = index;
            }
          });

          originalSelectElementTarget.change();

          setTimeout(() => {
            if (target.disabled && target.nextElementSibling) {
              target.nextElementSibling.focus();
            } else {
              target.focus();
            }
          }, 50);

          const information = document.createElement("p");
          information.appendChild(
            document.createTextNode(
              translation["Updated"][lang]
            )
          );
          screenReaderInformationBoxElement.append(information);
        }
      };

      const handleListPositionLowerClick = (target) => {
        const parent = target.parentElement.parentElement.parentElement;
        const sibling = parent.nextElementSibling;

        if (sibling !== null) {
          if (
            !sibling.classList.contains(
              "application-form__apartments-item--with-select"
            )
          ) {
            sibling.after(parent);

            const apartment = findMatchingApartmentName(target);
            const originalSelectElementTarget = findMatchingOriginalSelectElementByName(apartment);

            // After updating the new list, update the whole old list.
            jQuery('#application_form_apartments_list').children('li').each((index, element) => {
              const id = `#edit-apartment-${index}-weight`

              // Find the number of the apartment.
              const numberElement = jQuery(element)
                .first()
                .find('.application-form-apartment__apartment-number')
                .first()[0]

              if (numberElement != undefined) {
                const number = $(numberElement)
                  .find('span')
                  .last()[0].textContent;
                const originalSelectElements = getOriginalSelectedElements()
                const originalDropdown = originalSelectElements.filter(function(){
                  return jQuery(this).find('option:selected').text().includes(number)
                });
                // Find data-drupal-selector attribute with value ending "-weight"
                originalDropdown.first().closest('tr').find('[data-drupal-selector$=-weight]')[0].value = index;
              }
            });

            originalSelectElementTarget.change();

            setTimeout(() => {
              if (target.disabled && target.previousElementSibling) {
                target.previousElementSibling.focus();
              } else {
                target.focus();
              }
            }, 10);

            const information = document.createElement("p");
            information.appendChild(
              document.createTextNode(
                Drupal.t("Apartment list order has been updated.")
              )
            );
            screenReaderInformationBoxElement.append(information);
          }
        }
      };

      const handleApartmentDeleteButtonClick = (target) => {
        jQuery('.application-form-apartment__apartment-delete-button').each((index, element) => {
          jQuery(element).prop("disabled", true );
        })

        const parentLiElement =
          target.parentElement.parentElement.parentElement;
        const apartment = findMatchingApartmentName(target)
        if (!apartment) return;
        const originalDropdown = findMatchingOriginalSelectElementByName(apartment)
        const removeButton = originalDropdown.closest('tr').find(':submit')[0];
        const information = document.createElement("p");
        information.appendChild(
          document.createTextNode(
            translation["ApartmentRemoved"][lang]
          )
        );
        screenReaderInformationBoxElement.append(information);

        originalDropdown.val(0);
        originalDropdown.change()

        parentLiElement.innerHTML =
           "<div class='application-form-apartment-loader-wrapper'><div class='application-form-apartment-loader'></div></div>";

        setTimeout(() => {
          jQuery(removeButton).trigger('mousedown');
          jQuery('#application_form_apartments_list').empty();
          rebuildTable();
          jQuery('.application-form-apartment__apartment-delete-button').each((index, element) => {
            jQuery(element).prop("disabled", false);
          })

        }, 750);
      };

      const handleListItemInnerClicks = ({ target }) => {
        if (
          target.getAttribute("data-list-position-action-button") === "raise"
        ) {
          handleListPositionRaiseClick(target);
        }

        if (
          target.getAttribute("data-list-position-action-button") === "lower"
        ) {
          handleListPositionLowerClick(target);
        }

        if (
          target.getAttribute("class") ===
          "application-form-apartment__apartment-delete-button"
        ) {
          handleApartmentDeleteButtonClick(target);
        }
      };

      const handleApartmentAddButtonClick = ({ target }) => {
        const ajaxButton = $(
          '[data-drupal-selector="edit-apartment-add-more"]'
        );

        if (
          getApplicationFormApartmentListElementCount() <= 5 &&
          getApplicationFormApartmentListElementCount() > 1 &&
          getLastOriginalApartmentSelectElement().value !== "0"
        ) {
          ajaxButton.mousedown();
        }

        const formHeader = target.parentElement;

        const parentLiElement =
          target.parentElement.parentElement.parentElement.parentElement;

        parentLiElement.addEventListener("click", handleListItemInnerClicks);

        formHeader.appendChild(createCustomSelectElement());
        target.remove();
        setFocusToLastSelectElement();

        if (getApplicationFormApartmentListElementCount() < 5) {
          // eslint-disable-next-line no-use-before-define
          appendListItemToApartmentList();
        }
      };

      const createApartmentListItem = (
        values,
        id,
        withSelectElement = false
      ) => {
        let {
          apartment_number: apartmentNumberValue,
          apartment_structure: apartmentStructureValue,
          apartment_floor: apartmentFloorValue,
          apartment_living_area_size: apartmentLivingAreaSizeValue,
          apartment_sales_price: apartmentSalesPriceValue,
          apartment_debt_free_sales_price: apartmentDebtFreeSalesPriceValue,
        } = values;

        const project_type_element = document.querySelectorAll("[data-drupal-selector='asu-application-haso-form']");
        const project_type = project_type_element.length > 0 ? "HASO" : "hitas";

        const li = createElementWithClasses("li", [
          "application-form__apartments-item",
        ]);

        if (withSelectElement) {
          li.classList.add("application-form__apartments-item--with-select");
        }

        const article = createElementWithClasses("article", [
          "application-form-apartment",
        ]);

        const listPositionDesktop = createParagraphElementWithVisuallyHiddenText(
          ["application-form-apartment__list-position", "is-desktop"],
          "List position",
          ""
        );

        const formHeader = createElementWithClasses("div", [
          "application-form-apartment__header",
        ]);

        const listPositionMobile = createParagraphElementWithVisuallyHiddenText(
          ["application-form-apartment__list-position", "is-mobile"],
          "List position",
          ""
        );

        const apartmentNumber = createParagraphElementWithVisuallyHiddenText(
          ["application-form-apartment__apartment-number"],
          "Apartment",
          apartmentNumberValue
        );

        const apartmentStructure = createParagraphElementWithVisuallyHiddenText(
          ["application-form-apartment__apartment-structure"],
          "Apartment structure",
          apartmentStructureValue
        );

        const apartmentAddButton = createButtonElement(
          ["application-form-apartment__apartment-add-button"],
          translation["Add"][lang]
        );

        apartmentAddButton.addEventListener(
          "click",
          handleApartmentAddButtonClick
        );

        if (getApplicationFormApartmentListElementCount() > 0) {
          apartmentAddButton.setAttribute("disabled", true);
        }

        if (withSelectElement) {
          formHeader.append(listPositionMobile, apartmentAddButton);
        } else {
          formHeader.append(
            listPositionMobile,
            apartmentNumber,
            apartmentStructure
          );
        }

        const listPositionActions = document.createElement("div");
        listPositionActions.classList.add(
          "application-form-apartment__list-position-actions"
        );

        const listPositionActionsRaiseButton = createButtonElement(
          "",
          `Raise on the list, apartment ${apartmentNumberValue}`,
          withSelectElement && true
        );

        listPositionActionsRaiseButton.setAttribute(
          "data-list-position-action-button",
          "raise"
        );

        const listPositionActionsLowerButton = createButtonElement(
          "",
          `Lower on the list, apartment ${apartmentNumberValue}`,
          withSelectElement && true
        );

        listPositionActionsLowerButton.setAttribute(
          "data-list-position-action-button",
          "lower"
        );

        listPositionActions.append(
          listPositionActionsRaiseButton,
          listPositionActionsLowerButton
        );

        const formApartmentInformation = createElementWithClasses("ul", [
          "application-form-apartment__information",
        ]);

        const formApartmentInformationFloor = createListItemElementWithText(
          "Floor",
          apartmentFloorValue
        );

        const formApartmentInformationLivingAreaSize = createListItemElementWithText(
          "Living area size",
          apartmentLivingAreaSizeValue
        );

        let firstPriceTitle = ""
        let secondPriceTitle = ""
        if (project_type === "HASO") {
          firstPriceTitle = Drupal.t("Right of occupancy payment");
          secondPriceTitle = '';
          apartmentDebtFreeSalesPriceValue = '';
        } else {
          firstPriceTitle = Drupal.t("Sales price");
          secondPriceTitle = Drupal.t("Debt free sales price");
        }

        const formApartmentInformationSalesPrice = createListItemElementWithText(
          firstPriceTitle,
          apartmentSalesPriceValue
        );
        const formApartmentInformationDebtFreeSalesPrice = createListItemElementWithText(
          secondPriceTitle,
          apartmentDebtFreeSalesPriceValue
        );

        formApartmentInformation.append(
          formApartmentInformationFloor,
          formApartmentInformationLivingAreaSize,
          formApartmentInformationSalesPrice,
          formApartmentInformationDebtFreeSalesPrice
        );

        const formActions = createElementWithClasses("div", [
          "application-form-apartment__actions",
        ]);

        const formActionsDeleteButton = createButtonElement(
          ["application-form-apartment__apartment-delete-button"],
          translation['Delete'][lang]
        );

        formActionsDeleteButton.setAttribute(
          "aria-label",
          `${translation['Delete'][lang]}, ${translation['Apartment'][lang]} ${apartmentNumberValue}`
        );

        const formActionsLink = document.createElement("a");
        const formActionsLinkText = document.createTextNode(
          translation["Open"][lang]
        );
        formActionsLink.appendChild(formActionsLinkText);
        formActionsLink.setAttribute(
          "href",
          `${window.location.origin}/node/${id}`
        );
        formActionsLink.setAttribute(
          "aria-label",
          `${translation["Open"][lang]}, ${translation["Apartment"][lang]} ${apartmentNumberValue}`
        );

        formActions.append(formActionsDeleteButton, formActionsLink);

        if (withSelectElement) {
          article.append(listPositionDesktop, formHeader, listPositionActions);
        } else {
          article.append(
            listPositionDesktop,
            formHeader,
            listPositionActions,
            formApartmentInformation,
            formActions
          );
        }

        li.appendChild(article);

        return li;
      };

      const emptyTable = () => {
        jQuery('#application_form_apartments_list').children().not(':last').remove();
      }

      const appendListItemToApartmentList = () => {
        applicationFormApartmentListElement.append(
          createApartmentListItem({}, null, true)
        );
      };

      const getOriginalSelectedElements = () => {
        return $(
          '[data-drupal-selector="edit-apartment"] > table select'
        );
      }

      const findMatchingApartmentName = (target) => {
        return jQuery(target.parentElement.parentElement.parentElement)
          .first()
          .find('.application-form-apartment__apartment-number')
          .first()
          .find('span')
          .last()[0].textContent;
      }

      const findMatchingOriginalSelectElementByName = (apartment) => {
        const originalSelectElements = $(
          '[data-drupal-selector="edit-apartment"] > table select'
        );
        const originalSelect = originalSelectElements.filter(function(){
          return jQuery(this).find('option:selected').text().includes(apartment)
        });
        return originalSelect.first();
      }

      const buildTable = () => {
        if (getOriginalSelectElementValues().length > 0) {
          const selectElements = document.querySelectorAll(
            '[data-drupal-selector="edit-apartment"] > table select'
          );

          const selectElementsArray = [...selectElements]
            .filter((select) =>
              select.getAttribute("data-drupal-selector").includes("-id")
            )
            .filter((select) => select.value !== "0");

          selectElementsArray.map((select) => {
            const selectedValueTextArray = select.options[
              select.selectedIndex
            ].text.split(" | ");
            const listItemValues = {
              apartment_number: selectedValueTextArray[0],
              apartment_structure: selectedValueTextArray[1],
              apartment_floor: selectedValueTextArray[2],
              apartment_living_area_size: selectedValueTextArray[3],
              apartment_debt_free_sales_price: selectedValueTextArray[4],
              apartment_sales_price: selectedValueTextArray[5],
            };

            applicationFormApartmentListElement.append(
              createApartmentListItem(listItemValues, select.value, false)
            );
          });

          if (getApplicationFormApartmentListElementCount() === 1) {
            const listItem = document.getElementsByClassName(
              "application-form__apartments-item"
            )[0];

            const listItemActionButtons = listItem.querySelectorAll(
              "[data-list-position-action-button]"
            );

            [...listItemActionButtons].map((button) => {
              button.disabled = true;
            });
          }

          if (getApplicationFormApartmentListElementCount() < 5) {
            appendListItemToApartmentList();

            const apartmentAddButton = document.getElementsByClassName(
              "application-form-apartment__apartment-add-button"
            )[0];

            if (apartmentAddButton) {
              apartmentAddButton.removeAttribute("disabled");
            }
          }
        }

        if (getApplicationFormApartmentListElementCount() === 0) {
          appendListItemToApartmentList();
        }

        const alreadyExistingLiElements = document.getElementsByClassName(
          "application-form__apartments-item"
        );

        if (alreadyExistingLiElements.length > 0) {
          [...alreadyExistingLiElements].map((item, index) => {
            item.setAttribute("data-id", index);
            item.addEventListener("click", handleListItemInnerClicks);
          });
        }
      };

      const rebuildTable = () => {
        emptyTable();
        buildTable();
      }

      window.onload = buildTable;
})(jQuery, Drupal);
