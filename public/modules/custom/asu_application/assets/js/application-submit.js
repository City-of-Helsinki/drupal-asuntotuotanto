(function ($, Drupal, once) {
  function ensureNumber(value) {
    const parsed = Number.parseInt(String(value || ""), 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function getGlobalUnreadCount() {
    const tabBadge = document.querySelector(".user-profile__tab .asu-unread-badge");
    if (tabBadge) {
      return ensureNumber((tabBadge.textContent || "").trim());
    }

    const profileBadge = document.querySelector(".user-tools__button .asu-unread-badge--profile");
    if (profileBadge) {
      return ensureNumber((profileBadge.textContent || "").trim());
    }

    return 0;
  }

  function setButtonBadge(button, count) {
    let badge = button.querySelector(".asu-unread-badge");

    if (count > 0) {
      button.classList.add("application-message-button--has-unread");

      if (!badge) {
        badge = document.createElement("span");
        badge.className = "asu-unread-badge";
        button.appendChild(badge);
      }

      badge.textContent = String(count);
      badge.setAttribute("aria-label", Drupal.t("@count unread messages", { "@count": String(count) }));
      return;
    }

    button.classList.remove("application-message-button--has-unread");
    if (badge) {
      badge.remove();
    }
  }

  function syncApplicationMessageBadgesFallback() {
    const globalUnreadCount = getGlobalUnreadCount();
    const buttons = document.querySelectorAll(".application-message-button");

    buttons.forEach(function (button) {
      const badge = button.querySelector(".asu-unread-badge");

      if (globalUnreadCount <= 0) {
        button.classList.remove("application-message-button--has-unread");
        if (badge) {
          badge.remove();
        }
      }
    });
  }

  async function fetchUnreadCounts() {
    const endpoint = Drupal.url("user/application/unread-counts");
    const response = await fetch(endpoint, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
      },
    });

    if (!response.ok) {
      return null;
    }

    return response.json();
  }

  async function syncApplicationMessageBadges() {
    const buttons = Array.from(document.querySelectorAll(".application-message-button[data-application-id]"));
    if (buttons.length === 0) {
      return;
    }

    try {
      const payload = await fetchUnreadCounts();
      if (!payload || typeof payload !== "object" || !payload.counts) {
        syncApplicationMessageBadgesFallback();
        return;
      }

      const counts = payload.counts;
      buttons.forEach(function (button) {
        const applicationId = String(button.dataset.applicationId || "").trim();
        const count = ensureNumber(counts[applicationId]);
        setButtonBadge(button, count);
      });
    }
    catch (error) {
      console.warn("Unable to sync unread message badges", error);
      syncApplicationMessageBadgesFallback();
    }
  }

  Drupal.behaviors.applicationSubmit = {
    attach: function (context) {
      if (!context.querySelector && !document.querySelector) {
        return;
      }

      const root = context.querySelector ? context : document;
      if (!root.querySelector(".application-message-button[data-application-id]")) {
        return;
      }

      once("application-submit-badge-sync", "body", context).forEach(function () {
        syncApplicationMessageBadges();

        window.addEventListener("pageshow", function () {
          syncApplicationMessageBadges();
        });

        document.addEventListener("visibilitychange", function () {
          if (document.visibilityState === "visible") {
            syncApplicationMessageBadges();
          }
        });
      });

      syncApplicationMessageBadges();

      once(
        "application-submit-init",
        '[name="submit-application"], .application-delete-link',
        context,
      ).forEach(function (button) {
        $(button).on("click", function (e) {
          const $form = $(this).closest("form");
          const backendId = $form.find(
            'input[name="confirm_application_deletion"]',
          ).length;
          const $confirmInput = $form.find(
            'input[name="confirm_application_deletion"]',
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
              classes: {
                "ui-dialog": "asu-application-confirm-dialog",
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

            $dialog
              .dialog("widget")
              .addClass("asu-application-confirm-dialog")
              .attr("data-asu-application-confirm-dialog", "1");
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
              classes: {
                "ui-dialog": "asu-application-confirm-dialog",
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

            $dialog
              .dialog("widget")
              .addClass("asu-application-confirm-dialog")
              .attr("data-asu-application-confirm-dialog", "1");
          }
        });
      });
    },
  };
})(jQuery, Drupal, once);
