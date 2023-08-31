((Drupal) => {
  Drupal.behaviors.applicantFormToggle = {
    attach: function attach() {
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
    },
  };
})(Drupal);
