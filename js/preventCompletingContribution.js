CRM.$(function($) {
  var selectedContributionStatus = $('#contribution_status_id option:selected').text();
  if (selectedContributionStatus == 'Pending') {
    $('#contribution_status_id option:contains(Completed)').hide();
  }
});
