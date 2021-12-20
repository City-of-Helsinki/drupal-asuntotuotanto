((Drupal) => {
  Drupal.behaviors.applicantFormToggle = {
    attach: function attach() {

      const elements = document.getElementsByClassName('asu-results');
      Array.from(elements).forEach(function(element, index, array) {
        element.addEventListener('click', (event) => {
          console.log('click');
          jQuery.ajax({
            url: "call/ajax/application/results",
            method :'GET',
            dataType: "json",
            success: function(result){
              $a(".region-content").html(result.html);
            }
          })
        })
      })

    },
  };
})(Drupal);
