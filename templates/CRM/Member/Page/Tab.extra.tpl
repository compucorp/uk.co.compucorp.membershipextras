{literal}
<script type="text/javascript">
  if(!CRM.$('form#Membership').html()) {
    initializeMembershipPeriodViews();
  }

  function initializeMembershipPeriodViews() {
    var membershipTablesContainerIds = ['memberships', 'inactive-memberships'];
    membershipTablesContainerIds.forEach(function (containerId) {
      addMembershipPeriodsView(containerId);
    });

    function addMembershipPeriodsView(containerId) {
      CRM.$('#' + containerId +' table tr').each(function (rowNumber, row) {
        if (rowNumber == 0) {
          // Ignoring the first row that contains the table headers
          return;
        }

        var rowMembershipId = (CRM.$(this).attr('id')).replace('crm-membership_', '');
        var label = CRM.$('td.crm-membership-membership_type', this).text();
        var url = '{/literal}{crmURL p="civicrm/membership/periods"}{literal}' + '?id=' + rowMembershipId;
        var expandPeriodsHTML = '<a class="nowrap bold period-expand-row membership-period-collapse-icon" href="' + url + '">' + label + '</a>';

        CRM.$('td.crm-membership-membership_type', this).html(expandPeriodsHTML);
      });
    }

    /* the code below is copied and slightly altered from CiviCRM core js/crm.expandRow.js
     It provides the mechanism to view, hide and load the content of the membership periods
     as a nested table.
     */
    CRM.$(function($) {
      $('body')
        .off('.periodExpandRow')
        .on('click.periodExpandRow', 'a.period-expand-row', function(e) {
          var $row = $(this).closest('tr');
          if ($(this).hasClass('period-extended')) {
            $row.next('.crm-child-row').children('td').children('div.crm-ajax-container')
              .slideUp('fast', function() {$(this).closest('.crm-child-row').remove();});
          } else {
            var count = $('td', $row).length,
              $newRow = $('<tr class="crm-child-row"><td colspan="' + count + '"><div></div></td></tr>')
                .insertAfter($row);
            CRM.loadPage(this.href, {target: $('div', $newRow).animate({minHeight: '3em'}, 'fast')});
          }
          $(this).toggleClass('period-extended');
          e.preventDefault();
        });

      // Refreshes memberships when a period is modified.
      $('body').on('crmPopupFormSuccess ', function (e, data) {
        let eventTarget = $(e.target);
        if (eventTarget.hasClass('period-action')) {
          CRM.refreshParent(eventTarget.closest('.dataTable'));
        }
      });
    });
  }
</script>
{/literal}
