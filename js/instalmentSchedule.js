CRM.$(function ($) {
  $(".schedule-row").on('click', function (e) {
    e.preventDefault();
    $className = 'expanded';
    if ($(this).hasClass($className)) {
      $(this).removeClass($className);
      $(this).closest('tr').next('tr').hide();
    } else {
      $(this).addClass($className);
      $(this).closest('tr').next('tr').show();
    }
  });
});
