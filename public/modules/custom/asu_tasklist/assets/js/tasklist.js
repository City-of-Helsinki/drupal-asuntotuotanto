/**
 * @file
 *
 * Script to Show/hide asu tasklist description field if checkbox has been checked.
 */
(function($){
  const tasklistWrapperClass = '.field--type-asu-tasklist';
  $(tasklistWrapperClass).find('.asu_task_wrapper').each(function(index, taskWrapper){
    const checkbox = $(taskWrapper).children().find('input[type="checkbox"]')
    const description = $(taskWrapper).children().find('input[type="text"]')

    //set initial value
    if(checkbox[0] && $(checkbox[0]).is(":checked")){
      $(description).show();
    } else {
      $(description).hide();
    }

    // set eventlistener
    $(checkbox[0]).click(function(event){
      if($(event.target).is(":checked")){
        $(description).show();
      } else {
        $(description).hide();
      }
    });
  });
})(jQuery);
