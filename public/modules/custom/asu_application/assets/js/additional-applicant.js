((Drupal, once) => {
  Drupal.behaviors.applicantFormToggle = {
    attach: function attach(context) {
      const applicantFormWrapperElement = document.getElementById('applicant-wrapper');
      const checkboxToggleElement = document.getElementById('edit-applicant-0-has-additional-applicant');

      if (!checkboxToggleElement.checked) {
        applicantFormWrapperElement.style.display = 'none';
      }

      checkboxToggleElement.addEventListener('click', () => {
        if (checkboxToggleElement.checked) {
          applicantFormWrapperElement.style.display = 'block';
        } else {
          applicantFormWrapperElement.style.display = 'none';
        }
      })

      const pidField = document.getElementById('edit-field-personal-id-0-value');
      if (pidField) {
        pidField.setAttribute('minlength', '4');
      }

      once('disableSubmit', 'form', context).forEach((form) => {
        form.addEventListener('submit', function (e) {
          const submitButton = form.querySelector('input[type="submit"]');
          if (submitButton.disabled) {
            e.preventDefault();
            return;
          }
          if (submitButton) {
            submitButton.disabled = true;
          }
        });
      });
    },
  };
})(Drupal, once);
