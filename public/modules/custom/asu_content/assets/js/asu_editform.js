/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

'use strict';
// Gin theme's gin_editform.js to handle archived button.
(($, Drupal, drupalSettings) => {
  Drupal.behaviors.asuEditForm = {
    attach: function attach() {
      jQuery('.horizontal-tabs-panes [data-drupal-selector="edit-field-archived-wrapper').css('visibility', 'hidden');
      jQuery(document).ready(function () {
        document.querySelectorAll('[name="field_archived[value]"]').forEach((archivedStatus) => {
          archivedStatus.addEventListener('click', (event) => {
            const value = event.target.checked;
            // Sync value
            document.querySelectorAll('[name="field_archived[value]"]').forEach((archivedStatus) => {
              archivedStatus.checked = value;
            });
          });
        });
      })
    }
  };
})(jQuery, Drupal, drupalSettings);
