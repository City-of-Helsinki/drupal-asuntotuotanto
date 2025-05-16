(function ($, Drupal, once) {
  Drupal.behaviors.applicationSubmit = {
    attach: function (context, settings) {
      if (!settings.asuApplication?.hasExistingApplication) {
        return;
      }

      once(
        "application-submit-init",
        '[name="submit-application"]',
        context
      ).forEach(function (button) {
        $(button).on("click", function (e) {
          const $form = $(this).closest("form");
          const backendId = $form.find(
            'input[name="confirm_application_deletion"]'
          ).length;
          const $confirmInput = $form.find(
            'input[name="confirm_application_deletion"]'
          );
          const confirmValue = $confirmInput.val();

          if (backendId && confirmValue != "1") {
            e.preventDefault();
            e.stopImmediatePropagation();

            const $dialog = $("#asu-application-delete-confirm-dialog");
            const continueLabel = Drupal.t("Continue");
            const cancelLabel = Drupal.t("Cancel");

            $dialog.dialog({
              modal: true,
              width: 450,
              buttons: {
                [continueLabel]: function () {
                  $form
                    .find('input[name="confirm_application_deletion"]')
                    .val("1");
                  $(this).dialog("close");
                  $form.find('[name="submit-application"]').get(0).click();
                },
                [cancelLabel]: function () {
                  $(this).dialog("close");
                },
              },
            });
          }
        });
      });
    },
  };
})(jQuery, Drupal, once);
