CRM.$(document).on('init.dt', function (event, settings) {
  /**
   * When opening the membership tab, there could be more
   * that one datatable already initialized by other tabs,
   * so we here loop through all the visible datatables
   * to ensure that the nested membership period view is added
   * to the membership tab datatables since when the user open the
   * membership tab, the membership datatable will be the only visible
   * one.
   *
   * I used this method because I couldn't find any other way
   * to obtain reference to the memberships datatable.
   */
  CRM.$.each(CRM.$.fn.dataTable.tables(true), function () {
    var activeMembershipsContainer = CRM.$(this).closest('#memberships').attr('id');
    if (typeof activeMembershipsContainer != 'undefined') {
      addMembershipPeriodsNestedView(CRM.$(this), activeMembershipsContainer);
    }

    var inactiveMembershipsContainer = CRM.$(this).closest('#inactive-memberships').attr('id');
    if (typeof inactiveMembershipsContainer != 'undefined') {
      addMembershipPeriodsNestedView(CRM.$(this), inactiveMembershipsContainer);
    }
  });
});

function addMembershipPeriodsNestedView(membershipTableReference, tableContainerId) {
  initializePeriodsNestedViewExpandIcon(tableContainerId);
  addMembershipPeriodsViewClickListener(membershipTableReference, tableContainerId);
}

function initializePeriodsNestedViewExpandIcon(tableContainerId) {
  CRM.$('#' + tableContainerId + ' table td.crm-membership-membership_type').addClass('membership-period-collapse-icon');
}

function addMembershipPeriodsViewClickListener(membershipTableReference, tableContainerId) {
  var membershipDataTable = membershipTableReference.DataTable();
  CRM.$('#' + tableContainerId + ' table').on('click', 'td.crm-membership-membership_type', function () {
    handleMembershipRowClick(CRM.$(this), membershipDataTable);
  });
}

function handleMembershipRowClick(selectedMembershipRowReference, membershipDataTable) {
  var selectedMembershipRow = selectedMembershipRowReference.closest('tr');
  var selectedMembershipDataTableRow = membershipDataTable.row(selectedMembershipRow);

  if (selectedMembershipDataTableRow.child.isShown()) {
    selectedMembershipDataTableRow.child.hide();
    CRM.$(selectedMembershipRowReference).removeClass('membership-period-expand-icon');
    CRM.$(selectedMembershipRowReference).addClass('membership-period-collapse-icon');
  }
  else {
    var selectedMembershipId = (selectedMembershipRow.attr('id')).replace('crm-membership_', '');
    CRM.api3('MembershipPeriod', 'get', {
      'sequential': 1,
      'membership_id': selectedMembershipId,
      'options': {'sort':'start_date ASC','limit':0}
    }).done(function(periodsAPIResponse) {
      selectedMembershipDataTableRow.child(formatMembershipPeriodsTable(periodsAPIResponse)).show();
      selectedMembershipRowReference.removeClass('membership-period-collapse-icon');
      selectedMembershipRowReference.addClass('membership-period-expand-icon');
    });
  }
}

function formatMembershipPeriodsTable(periodsAPIResponse) {
  var periodTableMarkup = '<table class="periods-nested-view-no-right-border">';
  periodTableMarkup += '<tr><th>Term</th><th>Start Date</th><th>End Date</th><th>Actions</th></tr>';

  for(var i=0; i < periodsAPIResponse.count; i++) {
    var membershipPeriod = periodsAPIResponse.values[i];

    var rowActions = '<a href="/civicrm/view/membershipPeriod?id=' + membershipPeriod.id +'" class="action-item crm-hover-button" title="View Membership Period">View</a>' +
      '<a href="/civicrm/edit/membershipPeriod?id=' + membershipPeriod.id +'" class="action-item crm-hover-button" title="Edit Membership Period">Edit</a>';

    periodTableMarkup +=
      '<tr class="' + getPeriodColorCSSClass(membershipPeriod) + '">'+
      '<td>Term ' + (i+1) + '</td>'+
      '<td>'+ CRM.utils.formatDate(membershipPeriod.start_date) +'</td>'+
      '<td>'+ CRM.utils.formatDate(membershipPeriod.end_date) +'</td>'+
      '<td>' + rowActions +'</td>'+
      '</tr>';
  }

  periodTableMarkup += '</table>';

  return periodTableMarkup;
}

function getPeriodColorCSSClass(membershipPeriod) {
  var periodEndDate = CRM.utils.formatDate(membershipPeriod.end_date, 'yymmdd');
  var currentDate = CRM.utils.formatDate(new Date(), 'yymmdd');
  if (currentDate > periodEndDate) {
    return 'membership-period-in-past';
  }

  if (membershipPeriod.is_active == 0) {
    return 'membership-period-inactive';
  }

  return '';
}
