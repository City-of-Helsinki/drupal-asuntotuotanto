(function ($, Drupal) {
  Drupal.behaviors.projectTabs = {
    attach() {
      const tabs = $(".projects__tab a");
      const projectList = $(".projects__list");

      tabs.on("click", function (e) {
        e.preventDefault();

        // Remove active status from tabs.
        tabs.each(function () {
          $(this).attr("aria-selected", "false");
          $(this).removeClass("is-active");
        });

        // Remove active status from lists.
        projectList.each(function () {
          $(this).removeClass("is-active");
        });

        // Add active status to clicked tab.
        $(this).attr("aria-selected", "true");
        $(this).addClass("is-active");

        // Add active status to clicked list.
        const activeProjectList = $(e.target).data("project");
        projectList.each(function () {
          if ($(this).data("projectId") === activeProjectList) {
            $(this).addClass("is-active");
          }
        });
      });
    }
  };
})(jQuery, Drupal);
