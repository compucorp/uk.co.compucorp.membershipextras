CRM.$(function () {

  CRM.$('.remove-next-period-line-button').each(function () {
    CRM.$(this).click(function (e) {
      e.preventDefault();
      var itemData = CRM.$(this).closest('tr').data('item-data');
      showNextPeriodLineItemRemovalConfirmation(itemData);

      CRM.$('#periodsContainer').closest('.ui-dialog-content').data('selectedTab', 'next');
    });
  });

  CRM.$('#next_buttons #addOtherAmount').on('click', function(e) {
    e.preventDefault();
    CRM.$('#addLineItemRow').show();
    CRM.$('#periodsContainer').find('tr').not(CRM.$('#addLineItemRow')).addClass('disabled-row');
    CRM.$('#periodsContainer').find('a').not(CRM.$('#addLineItemRow').find('a')).addClass('disabled-click');
  });

  CRM.$('#next_buttons #addMembership').on('click', function(e) {
    e.preventDefault();
    CRM.$('#addMembershipRow').show();
    CRM.$('#periodsContainer').find('tr').not(CRM.$('#addMembershipRow')).addClass('disabled-row');
    CRM.$('#periodsContainer').find('a').not(CRM.$('#addMembershipRow').find('a')).addClass('disabled-click');
  });

  CRM.$('.cancel-add-next-period-line-button').on('click', function(e) {
    e.preventDefault();
    CRM.$('#addLineItemRow').hide();
    CRM.$('#periodsContainer').find('tr').removeClass('disabled-row');
    CRM.$('#periodsContainer').find('a').removeClass('disabled-click');
  });

  CRM.$('.cancel-add-next-period-membership-button').on('click', function(e) {
    e.preventDefault();
    CRM.$('#addMembershipRow').hide();
    CRM.$('#periodsContainer').find('tr').removeClass('disabled-row');
    CRM.$('#periodsContainer').find('a').removeClass('disabled-click');
  });

  CRM.$('#financialType').on('change', function() {
    var selectedId = CRM.$(this).val();
    var financialType = getFinancialType(selectedId);

    if (!financialType) {
      throw new Error(ts('Invalid financial type id passed'));
    }

    CRM.$('#financialTypeTaxRate').text(financialType.tax_rate || 'N/A');
  });

  CRM.$('#newMembershipItem').on('change', function() {
    var selectedId = CRM.$(this).val();

    if (selectedId) {
      var membershipType = getMembershipType(selectedId);
      var financialType = getFinancialType(membershipType.financial_type_id);
      var defaultAmount = Number(membershipType.minimum_fee) / Number(recurringContribution.installments);

      if (!financialType) {
        throw new Error(ts('Invalid financial type id passed'));
      }

      CRM.$('#newMembershipFinancialType').text(financialType.name);
      CRM.$('#newMembershipFinTypeTaxRate').text(
        financialType.tax_rate ? financialType.tax_rate + '%': 'N/A'
      );
      CRM.$('#newMembershipAmount').val(roundUp(defaultAmount));
    }
  });

  CRM.$('.confirm-add-next-period-line-button').on('click', function(e) {
    e.preventDefault();

    var label = CRM.$('#item').val(),
        amount = CRM.$('#amount').val(),
        financial_type_id = CRM.$('#financialType').val();

    if (!label.length) {
      CRM.alert(ts('Item label is required'), null, 'error');

      return;
    }

    if (!amount.length) {
      CRM.alert(ts('Item amount is required'), null, 'error');

      return;
    } else {
      try {
        amount = parseInt(amount);
      } catch(error) {
        CRM.alert(ts('Amount you entered is not valid'), null, 'error');

        return;
      }
    }

    showAddLineItemConfirmation(label, amount, financial_type_id);
    CRM.$('#periodsContainer').closest('.ui-dialog-content').data('selectedTab', 'next');
  });

  CRM.$('.confirm-add-next-period-membership-button').on('click', function(e) {
    e.preventDefault();
    
    var membershipTypeId = CRM.$('#newMembershipItem').val(),
        membershipType = getMembershipType(membershipTypeId),
        newMembershipAmount = CRM.$('#newMembershipAmount').val();

    if (!membershipTypeId || !membershipTypeId.length) {
      CRM.alert(ts('Item label is required'), null, 'error');
      
      return;
    }

    if (!newMembershipAmount.length) {
      CRM.alert(ts('Item amount is required'), null, 'error');

      return;
    } else {
      try {
        newMembershipAmount = parseInt(newMembershipAmount);
      } catch(error) {
        CRM.alert(ts('Amount you entered is not valid'), null, 'error');

        return;
      }
    }

    showAddLineItemConfirmation(membershipType.name, newMembershipAmount, membershipType.financial_type_id);
    CRM.$('#periodsContainer').closest('.ui-dialog-content').data('selectedTab', 'next');
  });
});

function getFinancialType(finTypeId) {
  return financialTypes.filter(function(financialType) {
    return financialType.id === finTypeId;
  })[0];
}

function roundUp(num, decimalPlaces) {
  if (!decimalPlaces) {
    decimalPlaces = 2;
  }

  return +(Math.round(num + "e+" + decimalPlaces)  + "e-" + decimalPlaces);
}

function getMembershipType(memTypeId) { 
  return membershipTypes[memTypeId];
}

function showNextPeriodLineItemRemovalConfirmation(lineItemData) {
  CRM.confirm({
    title: ts('Remove ' + lineItemData.label + '?'),
    message: ts('Please note the changes should take effect immediately after "Apply"'),
    options: {
      no: ts('Cancel'),
      yes: ts('Apply')
    }
  }).on('crmConfirm:yes', function() {
    CRM.api3('ContributionRecurLineItem', 'create', {
      'id': lineItemData.id,
      'auto_renew': 0,
    }).done(function (lineRemovalRes) {
      
      if (lineRemovalRes.is_error) {
        CRM.alert(ts('Cannot remove the last item in an order!'), null, 'error');

        return;
      }

      if (lineItemData.entity_table === 'civicrm_membership') {
        CRM.api3('Membership', 'create', {
          'id': lineItemData.entity_id,
          'contribution_recur_id': '',
        }).done(function (membershipUnlinkRes) {
          
          if (membershipUnlinkRes.is_error) {
            CRM.alert(ts('Cannot unlink the associated membership'), null, 'alert');

            return;
          }
          
          CRM.refreshParent('#periodsContainer');
          CRM.alert(
            ts(lineItemData.label + ' should no longer be continued in the next period.'),
            null,
            'success'
          );

          return;
        });
      } else {
        CRM.refreshParent('#periodsContainer');
        CRM.alert(
          ts(lineItemData.label + ' should no longer be continued in the next period.'),
          null,
          'success'
        );

        return;
      }

    });
  }).on('crmConfirm:no', function() {
    return;
  });
}

function showAddLineItemConfirmation(label, amount, finTypeId) {
  CRM.confirm({
      title: ts('Add ' + label + '?'),
      message: ts('Please note the changes should take effect immediately after "Apply".'),
      options: {
        no: ts('Cancel'),
        yes: ts('Apply')
      }
    }).on('crmConfirm:yes', function() {
      var financialType = getFinancialType(finTypeId),
          taxAmount = Number(financialType.tax_rate) * amount;
      CRM.api3('LineItem', 'create', {
        label: label,
        entity_id: recurringContributionID,
        qty: 1.0,
        unit_price: amount,
        line_total: amount,
        tax_amount: taxAmount,
        financial_type_id: finTypeId,
        entity_table: 'civicrm_contribution_recur',
      }).done(function(lineItemResult) {
        if (lineItemResult.is_error) {
          CRM.alert(lineItemResult.error_message, null, 'error');

          return;
        }

        var createdLineItemId = lineItemResult.id;
        CRM.api3('ContributionRecurLineItem', 'create', {
          contribution_recur_id: recurringContributionID,
          line_item_id: createdLineItemId,
          auto_renew: true,
        }).done(function(contribRecurResult) {
          if (contribRecurResult.is_error) {
            CRM.alert(contribRecurResult.error_message, null, 'error');

            return;
          }

          CRM.alert(
            ts(label + ' will now be continued in the next period.'),
            null,
            'success'
          );
          CRM.refreshParent('#periodsContainer');
        });
      });
    }).on('crmConfirm:no', function() {
      return;
    });
}