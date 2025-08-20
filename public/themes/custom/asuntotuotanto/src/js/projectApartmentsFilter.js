(function (Drupal, once, $) {
  "use strict";

  Drupal.behaviors.projectApartmentsFilter = {
    attach(context) {
      /* ---------- DROPDOWNS (open/close with proper a11y) ---------- */
      once("project-apartments-filter-ui", "[data-dropdown]", context).forEach(
        (dd) => {
          const $dd = $(dd);
          const $btn = $dd.find("[data-dropdown-toggle]");
          const contentId = $btn.attr("aria-controls");
          const $content = contentId
            ? $("#" + contentId)
            : $dd.find(".filter-dropdown__content");

          function isOpen() {
            return $dd.hasClass("is-open");
          }
          function open() {
            // Close other dropdowns
            $("[data-dropdown].is-open")
              .not($dd)
              .each(function () {
                const $x = $(this);
                const $xb = $x.find("[data-dropdown-toggle]");
                const cid = $xb.attr("aria-controls");
                const $xc = cid
                  ? $("#" + cid)
                  : $x.find(".filter-dropdown__content");
                $x.removeClass("is-open").attr("aria-expanded", "false");
                $xb
                  .attr("aria-expanded", "false")
                  .attr("aria-pressed", "false");
                $xc.attr("aria-hidden", "true").attr("hidden", true);
              });

            $dd.addClass("is-open").attr("aria-expanded", "true");
            $btn.attr("aria-expanded", "true").attr("aria-pressed", "true");
            $content.attr("aria-hidden", "false").removeAttr("hidden");

            // Focus first focusable inside panel
            const focusable = $content
              .find('input,button,select,[tabindex]:not([tabindex="-1"])')
              .get(0);
            if (focusable) focusable.focus();
          }
          function close() {
            // Return focus to button if it is somewhere inside the panel
            if ($content.has(document.activeElement).length) {
              $btn.focus();
            }
            $dd.removeClass("is-open").attr("aria-expanded", "false");
            $btn.attr("aria-expanded", "false").attr("aria-pressed", "false");
            $content.attr("aria-hidden", "true").attr("hidden", true);
          }
          function toggle() {
            isOpen() ? close() : open();
          }

          // Initial closed state
          $content.attr("aria-hidden", "true").attr("hidden", true);

          $btn.on("click", (e) => {
            e.preventDefault();
            toggle();
          });

          $(document).on("click", (ev) => {
            if (
              !$dd.is(ev.target) &&
              $dd.has(ev.target).length === 0 &&
              isOpen()
            ) {
              close();
            }
          });

          $(document).on("keydown", (ev) => {
            if (ev.key === "Escape" && isOpen()) close();
          });
        }
      );

      /* -------------------------- FILTER LOGIC -------------------------- */
      once("project-apartments-filter", "[data-filter-form]", context).forEach(
        (formEl) => {
          const $form = $(formEl);

          const $apply = $form.find("[data-filter-apply]");
          const $roomsBox = $form.find("[data-filter-rooms]");
          const $areaMin = $form.find("[data-filter-area-min]");
          const $areaMax = $form.find("[data-filter-area-max]");
          const $priceMax = $form.find("[data-filter-price-max]");

          // clear buttons
          const $roomsClear = $form.find("[data-rooms-clear]");
          const $areaClear = $form.find("[data-area-clear]");
          const $priceClear = $form.find("[data-price-clear]");

          // Scope to listing container
          const $listing = $form.closest(".project-apartments-listing");
          const $rows = $listing.find(".project__apartments--desktop tbody tr");
          const $cards = $listing.find(
            ".project__apartments--mobile .project__apartments-item"
          );
          const $count = $listing.find("#project-apartments-count");

          function parseFloatSafe(v) {
            if (!v && v !== 0) return NaN;
            return parseFloat(String(v).replace(/\s/g, "").replace(",", "."));
          }
          function parseIntSafe(v) {
            if (!v && v !== 0) return NaN;
            return parseInt(String(v).replace(/\s/g, ""), 10);
          }

          function getSelectedRooms() {
            const vals = [];
            $roomsBox.find('input[type="checkbox"]:checked').each(function () {
              vals.push($(this).val());
            });
            return vals;
          }

          function passRooms(rooms, selected) {
            if (!selected.length) return true;
            if (rooms >= 5 && selected.includes("5+")) return true;
            return selected.includes(String(rooms));
          }

          function passArea(area, min, max) {
            const hasMin = isFinite(min);
            const hasMax = isFinite(max);
            if (!hasMin && !hasMax) return true;
            if (hasMin && !(area >= min)) return false;
            if (hasMax && !(area <= max)) return false;
            return true;
          }

          function passPrice(price, max) {
            if (!isFinite(max)) return true;
            return price <= max;
          }

          function filterOnce() {
            const selectedRooms = getSelectedRooms();
            const minArea = parseFloatSafe($areaMin.val());
            const maxArea = parseFloatSafe($areaMax.val());
            const maxPrice = parseIntSafe($priceMax.val());

            let visible = 0;

            function applyTo($el) {
              const rooms = parseIntSafe($el.data("rooms"));
              const area = parseFloatSafe($el.data("area"));
              const price = parseIntSafe($el.data("price"));
              const ok =
                passRooms(rooms, selectedRooms) &&
                passArea(area, minArea, maxArea) &&
                passPrice(price, maxPrice);

              $el.toggle(!!ok);
              if (ok) visible++;
            }

            $rows.each(function () {
              applyTo($(this));
            });
            $cards.each(function () {
              applyTo($(this));
            });

            if ($count.length) {
              const html = $count.html().replace(/\d+/, String(visible));
              $count.html(html);
            }

            // update clear buttons state after apply
            updateRoomsClearState();
            updateAreaClearState();
            updatePriceClearState();
          }

          // -------- Clear buttons state + handlers --------
          function updateRoomsClearState() {
            $roomsClear.prop("disabled", getSelectedRooms().length === 0);
          }
          function updateAreaClearState() {
            const has =
              String($areaMin.val()).trim() !== "" ||
              String($areaMax.val()).trim() !== "";
            $areaClear.prop("disabled", !has);
          }
          function updatePriceClearState() {
            const has = String($priceMax.val()).trim() !== "";
            $priceClear.prop("disabled", !has);
          }

          $roomsClear.on("click", (e) => {
            e.preventDefault();
            $roomsBox.find('input[type="checkbox"]').prop("checked", false);
            updateRoomsClearState();
            // filterOnce(); // uncomment to apply immediately
          });
          $areaClear.on("click", (e) => {
            e.preventDefault();
            $areaMin.val("");
            $areaMax.val("");
            updateAreaClearState();
            // filterOnce();
          });
          $priceClear.on("click", (e) => {
            e.preventDefault();
            $priceMax.val("");
            updatePriceClearState();
            // filterOnce();
          });

          $roomsBox.on("change", updateRoomsClearState);
          $areaMin.on("input change", updateAreaClearState);
          $areaMax.on("input change", updateAreaClearState);
          $priceMax.on("input change", updatePriceClearState);

          // init states
          updateRoomsClearState();
          updateAreaClearState();
          updatePriceClearState();

          $apply.on("click", filterOnce);
        }
      );
    },
  };
})(Drupal, once, jQuery);
