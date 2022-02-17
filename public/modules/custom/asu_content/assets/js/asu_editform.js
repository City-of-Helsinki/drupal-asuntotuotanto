/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

'use strict';
// Gin theme's gin_editform.js to handle archived button.
(($, Drupal, drupalSettings) => {
  Drupal.behaviors.asuEditForm = {
    attach: function attach() {
      const form = document.querySelector('.region-content form');
      const sticky = document.querySelector('.gin-sticky').cloneNode(true);
      const newParent = document.querySelector('.region-sticky__items__inner');

      if (newParent.querySelectorAll('.gin-sticky').length === 0) {
        newParent.appendChild(sticky);

        // Input Elements
        newParent.querySelectorAll('input[type="submit"]')
          .forEach((el) => {
            el.setAttribute('form', form.getAttribute('id'));
            el.setAttribute('id', el.getAttribute('id') + '--gin-edit-form');
          });

        // Make Archived Status reactive
        document.querySelectorAll('.field--name-field-archived [name="field_archived[value]"]').forEach((archivedStatus) => {
          archivedStatus.addEventListener('click', (event) => {
            const value = event.target.checked;
            // Sync value
            document.querySelectorAll('.field--name-field-archived [name="field_archived[value]"]').forEach((archivedStatus) => {
              archivedStatus.checked = value;
            });
          });
        });

        setTimeout(() => {
          sticky.classList.add('gin-sticky--visible');
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
