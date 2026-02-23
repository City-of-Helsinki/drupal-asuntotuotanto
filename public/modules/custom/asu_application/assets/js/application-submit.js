(function ($, Drupal, once) {
  const decorateDialogButtons = function (dialogElement) {
    const $buttons = $(dialogElement)
      .closest(".ui-dialog")
      .find(".ui-dialog-buttonset button");

    $buttons.eq(0).addClass("hds-button hds-button--primary");
    $buttons.eq(1).addClass("hds-button hds-button--secondary");
  };

  Drupal.behaviors.applicationSubmit = {
    attach: function (context, settings) {
      if (!settings.asuApplication?.hasExistingApplication) {
        return;
      }

      once(
        "application-submit-init",
        '[name="submit-application"], .application-delete-link',
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

          const isApplicationForm = $(button).is('[name="submit-application"]');
          const isDeleteAction = $(button).hasClass("application-delete-link");

          if (isApplicationForm && backendId && confirmValue !== "1") {
            e.preventDefault();
            e.stopImmediatePropagation();

            const $dialog = $("#asu-application-delete-confirm-dialog");
            const continueLabel = Drupal.t("Continue");
            const cancelLabel = Drupal.t("Cancel");

            $dialog.dialog({
              modal: true,
              width: 450,
              dialogClass: "asu-application-confirm-dialog",
              open: function () {
                decorateDialogButtons(this);
              },
              buttons: {
                [continueLabel]: function () {
                  $confirmInput.val("1");
                  $(this).dialog("close");
                  $form.find('[name="submit-application"]').get(0).click();
                },
                [cancelLabel]: function () {
                  $(this).dialog("close");
                },
              },
            });
          }

          if (isDeleteAction) {
            e.preventDefault();
            e.stopImmediatePropagation();

            const $dialog = $("#asu-application-delete-confirm-dialog");
            const continueLabel = Drupal.t("Continue");
            const cancelLabel = Drupal.t("Cancel");

            $dialog.dialog({
              modal: true,
              width: 450,
              dialogClass: "asu-application-confirm-dialog",
              open: function () {
                decorateDialogButtons(this);
              },
              buttons: {
                [continueLabel]: function () {
                  $(this).dialog("close");
                  $form.submit();
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
