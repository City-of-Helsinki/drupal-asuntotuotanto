(function ($, Drupal, once) {
  Drupal.behaviors.applicationSubmit = {
    attach: function (context, settings) {
      if (
        !settings.asuApplication ||
        !settings.asuApplication.hasExistingApplication
      ) {
        return;
      }

      once(
        "application-submit-init",
        '[name="submit-application"]',
        context
      ).forEach(function (button) {
        $(button).on("click", function (e) {
          var $form = $(this).closest("form");
          var backendId = $form.find(
            'input[name="confirm_application_deletion"]'
          ).length;
          var confirmValue = $form
            .find('input[name="confirm_application_deletion"]')
            .val();

          if (backendId && confirmValue != "1") {
            e.preventDefault();
            e.stopImmediatePropagation();

            var $dialog = $("#asu-application-delete-confirm-dialog");

            $dialog.dialog({
              modal: true,
              width: 450,
              buttons: {
                [Drupal.t("Continue")]: function () {
                  $form
                    .find('input[name="confirm_application_deletion"]')
                    .val("1");
                  $(this).dialog("close");
                  $form.find('[name="submit-application"]').get(0).click();
                },
                [Drupal.t("Cancel")]: function () {
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
