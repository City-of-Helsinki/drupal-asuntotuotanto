((Drupal) => {
  Drupal.behaviors.applicantFormToggle = {
    attach: function attach() {
      const applicantFormWrapperElement = document.getElementById('applicant-wrapper');
      const checkboxToggleElement = document.getElementById('has-additional-applicant');

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
    },
  };
})(Drupal);