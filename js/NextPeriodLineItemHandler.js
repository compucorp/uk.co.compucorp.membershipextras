var NOTIFICATION_EXPIRE_TIME_IN_MS = 5000;

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
    CRM.$('#addLineItemRow').show();
    CRM.$('#periodsContainer').tabs({ disabled: true });
    CRM.$('#periodsContainer').find('tr').not(CRM.$('#addLineItemRow')).addClass('disabled-row');
    CRM.$('#periodsContainer').find('a').not(CRM.$('#addLineItemRow').find('a')).addClass('disabled-click');

    return false;
  });

  CRM.$('#next_buttons #addMembership').on('click', function(e) {
    CRM.$('#addMembershipRow').show();
    CRM.$('#periodsContainer').tabs({ disabled: true });
    CRM.$('#periodsContainer').find('tr').not(CRM.$('#addMembershipRow')).addClass('disabled-row');
    CRM.$('#periodsContainer').find('a').not(CRM.$('#addMembershipRow').find('a')).addClass('disabled-click');

    return false;
  });

  CRM.$('.cancel-add-next-period-line-button').on('click', function(e) {
    e.preventDefault();
    CRM.$('#addLineItemRow').hide();
    CRM.$('#periodsContainer').tabs({ disabled: false });
    CRM.$('#periodsContainer').find('tr').removeClass('disabled-row');
    CRM.$('#periodsContainer').find('a').removeClass('disabled-click');
  });

  CRM.$('.cancel-add-next-period-membership-button').on('click', function(e) {
    e.preventDefault();
    CRM.$('#addMembershipRow').hide();
    CRM.$('#periodsContainer').tabs({ disabled: false });
    CRM.$('#periodsContainer').find('tr').removeClass('disabled-row');
    CRM.$('#periodsContainer').find('a').removeClass('disabled-click');
  });

  CRM.$('#financialType').on('change', function() {
    var selectedId = CRM.$(this).val();
    var financialType = getFinancialType(selectedId);

    if (!financialType) {
      throw new Error(ts('Invalid financial type id passed'));
    }

    var taxRate = financialType.tax_rate || 'N/A';
    if (taxRate != 'N/A') {
      taxRate += ' %';
    }

    CRM.$('#financialTypeTaxRate').text(taxRate);
  });

  CRM.$('#newMembershipItem').on('change', function() {
    var selectedId = CRM.$(this).val();

    if (selectedId) {
      var membershipType = getMembershipType(selectedId);
      var financialType = getFinancialType(membershipType.financial_type_id);
      var installments = getNumberOfInstallments(recurringContribution);
      var defaultAmount = Number(membershipType.minimum_fee) / Number(installments);

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
      CRM.alert(ts('Item label is required'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return;
    }

    if (!amount.length) {
      CRM.alert(ts('Item amount is required'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return;
    } else {
      try {
        amount = parseInt(amount);
      } catch(error) {
        CRM.alert(ts('Amount you entered is not valid'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

        return;
      }
    }

    showAddLineItemConfirmation(label, amount, financial_type_id);
    CRM.$('#periodsContainer').closest('.ui-dialog-content').data('selectedTab', 'next');
  });

  CRM.$('.confirm-add-next-period-membership-button').on('click', function(e) {
    e.preventDefault();

    if (validateNewMembershipLineItem()) {
      showMembershipAddLineItemConfirmation();
    }

    CRM.$('#periodsContainer').closest('.ui-dialog-content').data('selectedTab', 'next');
  });
});

/**
 * Returns number of installments in given recurring contribution, or 1 if no
 * installments.
 *
 * @param recurringContribution
 *
 * @return {number}
 */
function getNumberOfInstallments(recurringContribution) {
  var numberOfInstallments = 1;

  if (typeof recurringContribution.installments !== 'undefined') {
    numberOfInstallments = parseInt(recurringContribution.installments);
  }

  return numberOfInstallments > 0 ? numberOfInstallments : 1;
}

/**
 * Validates the data being used to create a neww membership.
 *
 * @return {boolean}
 */
function validateNewMembershipLineItem() {
  var membershipTypeId = CRM.$('#newMembershipItem').val(),
    newMembershipAmount = CRM.$('#newMembershipAmount').val();

  if (!membershipTypeId || !membershipTypeId.length) {
    CRM.alert(ts('Item label is required'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

    return false;
  }

  if (!newMembershipAmount.length) {
    CRM.alert(ts('Item amount is required'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

    return false;
  } else {
    try {
      newMembershipAmount = parseInt(newMembershipAmount);
    } catch(error) {
      CRM.alert(ts('Amount you entered is not valid'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return false;
    }
  }

  return true;
}

function showMembershipAddLineItemConfirmation() {
  var membershipTypeId = CRM.$('#newMembershipItem').val();
  var membershipType = getMembershipType(membershipTypeId);

  CRM.confirm({
    title: ts('Add ' + membershipType.name + '?'),
    message: ts('Please note the changes should take effect immediately after \'Apply\'.'),
    options: {
      no: ts('Cancel'),
      yes: ts('Apply')
    }
  }).on('crmConfirm:yes', function() {
    var membershipTypeId = CRM.$('#newMembershipItem').val(),
      newMembershipAmount = CRM.$('#newMembershipAmount').val();

    var membershipType = getMembershipType(membershipTypeId),
      financialType = getFinancialType(membershipType.financial_type_id),
      taxAmount = Number(financialType.tax_rate) * amount;

    CRM.api3('PriceFieldValue', 'get', {
      sequential: 1,
      membership_type_id: membershipTypeId,
      'price_field_id.price_set_id.name': 'default_membership_type_amount',
      'context': 'Membershipextras'
    }).done(function(priceFieldValueResult) {
      if (priceFieldValueResult.count > 0) {
        var priceFieldValue = priceFieldValueResult.values[0];

        createLineItem({
          label: membershipType.name,
          entity_id: recurringContributionID,
          qty: 1.0,
          unit_price: newMembershipAmount,
          line_total: newMembershipAmount,
          tax_amount: taxAmount,
          financial_type_id: membershipType.financial_type_id,
          price_field_id: priceFieldValue.price_field_id,
          price_field_value_id: priceFieldValue.id,
          entity_table: 'civicrm_contribution_recur',
        });
      } else {
        CRM.alert('Could not determine price field value for the select membership type.', null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});
      }
    });
  }).on('crmConfirm:no', function() {
    return;
  });
}

function createLineItem(params) {
  CRM.api3('LineItem', 'create', params).done(function(lineItemResult) {
    if (lineItemResult.is_error) {
      CRM.alert(lineItemResult.error_message, null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return;
    }

    var createdLineItemId = lineItemResult.id;
    CRM.api3('ContributionRecurLineItem', 'create', {
      contribution_recur_id: recurringContributionID,
      line_item_id: createdLineItemId,
      auto_renew: true,
    }).done(function(contribRecurResult) {
      if (contribRecurResult.is_error) {
        CRM.alert(contribRecurResult.error_message, null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

        return;
      }

      CRM.alert(
        ts(params.label + ' will now be continued in the next period.'),
        null,
        'success',
        {expires: NOTIFICATION_EXPIRE_TIME_IN_MS}
      );
      createActivity('Update Payment Plan Next Period', 'update_payment_plan_next_period');
      CRM.refreshParent('#periodsContainer');
    });
  });
}

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

/**
 * @param {string} memTypeId
 *
 * @returns {Object}
 */
function getMembershipType(memTypeId) {
  if (memTypeId in nextPeriodMembershipTypes) {
    return nextPeriodMembershipTypes[memTypeId];
  }

  return  {};
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
    var params = {
      'id': lineItemData.id,
      'auto_renew': 0
    };

    if (typeof lineItemData.start_date === 'undefined' || lineItemData.start_date === '') {
      params.is_removed = 1;
    }

    CRM.api3('ContributionRecurLineItem', 'create', params)
      .done(function (lineRemovalRes) {
        if (lineRemovalRes.is_error) {
          CRM.alert(ts('Cannot remove the last item in an order!'), null, 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

          return;
        }

        if (lineItemData.entity_table === 'civicrm_membership') {
          CRM.api3('Membership', 'create', {
            'id': lineItemData.entity_id,
            'contribution_recur_id': '',
          }).done(function (membershipUnlinkRes) {
            if (membershipUnlinkRes.is_error) {
              CRM.alert(ts('Cannot unlink the associated membership'), null, 'alert', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

              return;
            }
          });
        }

        createActivity('Update Payment Plan Next Period', 'update_payment_plan_next_period', function(res) {
          CRM.alert(
            ts(lineItemData.label + ' should no longer be continued in the next period.'),
            null,
            'success',
            {expires: NOTIFICATION_EXPIRE_TIME_IN_MS}
          );
          CRM.refreshParent('#periodsContainer');

          return;
        });
      });
  }).on('crmConfirm:no', function() {
    CRM.refreshParent('#periodsContainer');
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
  }).on('crmConfirm:yes', function () {
    var financialType = getFinancialType(finTypeId),
      taxAmount = Number(financialType.tax_rate) * amount;
    createLineItem({
      label: label,
      entity_id: recurringContributionID,
      qty: 1.0,
      unit_price: amount,
      line_total: amount,
      tax_amount: taxAmount,
      financial_type_id: finTypeId,
      entity_table: 'civicrm_contribution_recur',
    });
  }).on('crmConfirm:no', function () {
    return;
  });
}

function createActivity(subject, typeId, callback) {
  CRM.api3('Activity', 'create', {
    'source_contact_id': 'user_contact_id',
    'source_record_id': recurringContributionID,
    'target_id': recurringContribution.contact_id,
    'activity_type_id': typeId,
    'subject': subject,
    'added_by': 'admin',
  }).done(function (res) {
    if (callback && typeof(callback) === 'function') {
      callback();
    }

    return;
  });
}
