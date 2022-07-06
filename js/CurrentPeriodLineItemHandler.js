CRM.RecurringContribution = CRM.RecurringContribution || {};

var NOTIFICATION_EXPIRE_TIME_IN_MS = 5000;

/**
 * This class handles front-end events and logic associated to managing line
 * items for a recurring contribution.
 */
CRM.RecurringContribution.CurrentPeriodLineItemHandler = (function($) {

  /**
   * Constructor.
   */
  function CurrentPeriodLineItemHandler(recurringContributionID) {
    this.recurringContributionID = recurringContributionID;

    this.currentTab = null;
    this.clickedRow = false;

    this.newMembershipRow = null;
    this.newMembershipRowBGColor = null;
    this.newMembershipTypeField = null;
    this.newMembershipStartDateField = null;
    this.newMembershipEndDateField = null;
    this.newMembershipAutoRenewField = null;
    this.newMembershipAmountField = null;

    this.newDonationRow = null;
    this.newDonationRowBGColor = null;
    this.newDonationItemField = null;
    this.newDonationStartDateField = null;
    this.newDonationAutoRenewField = null;
    this.newDonationFinancialTypeField = null;
    this.newDonationAmountField = null;

    this.membershipTypes = {};
    this.financialTypes = [];
    this.recurringContribution = {};
  }

  /**
   * Setter.
   *
   * @param varName
   * @param value
   */
  CurrentPeriodLineItemHandler.prototype.set = function (varName, value) {
    this[varName] = value;
  };

  /**
   * Initializes form.
   */
  CurrentPeriodLineItemHandler.prototype.initializeForm = function (currentTab) {
    var that = this;
    this.currentTab = currentTab;

    this.newMembershipRow = CRM.$('#new_membership_line_item', this.currentTab);
    this.newMembershipRowBGColor = this.newMembershipRow.css('backgroundColor');
    this.newMembershipTypeField = CRM.$('#newline_membership_type', this.newMembershipRow);
    this.newMembershipStartDateField = CRM.$('#newline_start_date', this.newMembershipRow);
    this.newMembershipEndDateField = CRM.$('#newline_end_date', this.newMembershipRow);
    this.newMembershipAutoRenewField = CRM.$('#newline_auto_renew', this.newMembershipRow);
    this.newMembershipAmountField = CRM.$('#newline_amount', this.newMembershipRow);
    this.newMembershipRow.css('display', 'none');

    this.newDonationRow = CRM.$('#new_donation_line_item');
    this.newDonationRowBGColor = this.newDonationRow.css('backgroundColor');
    this.newDonationItemField = CRM.$('#newline_donation_item', this.newDonationRow);
    this.newDonationStartDateField = CRM.$('#newline_donation_start_date', this.newDonationRow);
    this.newDonationAutoRenewField = CRM.$('#newline_donation_auto_renew', this.newDonationRow);
    this.newDonationFinancialTypeField = CRM.$('#newline_donation_financial_type_id', this.newDonationRow);
    this.newDonationAmountField = CRM.$('#newline_donation_amount', this.newDonationRow);
    this.newDonationRow.css('display', 'none');

    this.currentTab.block();
    CRM.api3('ContributionRecur', 'getsingle', {
      'id': this.recurringContributionID
    }).done(function (result) {
      that.currentTab.unblock({message: null});
      that.recurringContribution = result;
    });
  };

  /**
   * Adds event handlers to form's elements.
   */
  CurrentPeriodLineItemHandler.prototype.addEventHandlers = function () {
    this.setUpRowFlashingOnOutClick();
    this.setLineItemEvents();
    this.setNewMembershipEvents();
    this.setNewDonationEvents();
  };

  /**
   * Adds events to the form to handle line item removal.
   */
  CurrentPeriodLineItemHandler.prototype.setLineItemEvents = function () {
    var that = this;

    // Processes auto-renew check-box events.
    CRM.$('.auto-renew-line-checkbox', this.currentTab).change(function() {
      var itemData = CRM.$(this).closest('tr').data('item-data');
      if (!this.checked) {
        showNextPeriodLineItemRemovalConfirmation(itemData);
      } else {
        that.processSetLineItemAutoRenewal(itemData);
      }
    });

    // Remove line item.
    CRM.$('.remove-line-button', this.currentTab).each(function () {
      CRM.$(this).click(function () {
        var itemID = CRM.$(this).data('itemid');
        that.showLineItemRemovalConfirmation(itemID);

        return false;
      });
    });
  };

  /**
   * Shows confirmation form to set a line item to auto renew, and thus add to
   * next period.
   *
   * @param itemData
   */
  CurrentPeriodLineItemHandler.prototype.processSetLineItemAutoRenewal = function(itemData) {
    var that = this;

    if (itemData.entity_table !== 'civicrm_membership') {
      this.showLineItemAutoRenewConfirmation(itemData);

      return;
    }

    var params = this.buildNextPeriodLineItemCallParameters();

    var apiCalls = {
      'nextPeriodLineItems': ['ContributionRecurLineItem', 'get', params],
      'membership': ['Membership', 'getsingle', {'id': itemData.entity_id}]
    };

    this.currentTab.block({message: null});
    CRM.api3(apiCalls)
    .done(function (results) {
      var isMembershipTypeOnNextPeriod = that.isMembershipTypeOnNextPeriod(
        results.membership.membership_type_id,
        results.nextPeriodLineItems
      );

      that.currentTab.unblock();
      if (itemData.entity_table === 'civicrm_membership' && isMembershipTypeOnNextPeriod) {
        CRM.alert(ts('This membership type is already enrolled in next period.'), 'Duplicate Membership Type in Next Period', 'alert', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});
        CRM.refreshParent('#periodsContainer');
      } else {
        that.showLineItemAutoRenewConfirmation(itemData);
      }
    });
  };

  /**
   * Show confirmation to set a line item to auto-renew.
   *
   * @param lineItemData
   */
  CurrentPeriodLineItemHandler.prototype.showLineItemAutoRenewConfirmation = function (lineItemData) {
    CRM.confirm({
      title: ts('Set ' + lineItemData.label + ' to auto-renew?'),
      message: ts('Please note the changes should take effect immediately after \'Apply\'.'),
      options: {
        no: ts('Cancel'),
        yes: ts('Apply')
      }
    })
    .on('crmConfirm:yes', function() {
      var apiCalls = {
        'membership': ['Membership', 'create', {'id': lineItemData.entity_id, 'contribution_recur_id': lineItemData.contribution_recur_id}],
        'line_item': ['ContributionRecurLineItem', 'create', {'id': lineItemData.id, 'auto_renew': 1}]
      };

      CRM.api3(apiCalls).done(function () {
        CRM.refreshParent('#periodsContainer');
      });
    })
    .on('crmConfirm:no', function () {
      CRM.refreshParent('#periodsContainer');
    });
  };

  /**
   * Checks if the membership type for the membership already exists on given
   * list of line items.
   *
   * @param lineMembershipTypeID
   * @param lineItemsResult
   *
   * @returns {boolean}
   */
  CurrentPeriodLineItemHandler.prototype.isMembershipTypeOnNextPeriod = function (lineMembershipTypeID, lineItemsResult) {
    if (lineItemsResult.count === 0) {
      return false;
    }

    var currentLineMembershipType, currentLine, currentPriceFieldValue;
    var nextPeriodLineItems = lineItemsResult.values;

    for (var i = 0; i < nextPeriodLineItems.length; i++) {
      if (nextPeriodLineItems[i]['api.LineItem.get']['count'] === 0) {
        continue;
      }

      currentLine = nextPeriodLineItems[i]['api.LineItem.get']['values'][0];
      currentPriceFieldValue = currentLine['api.PriceFieldValue.getsingle'];
      currentLineMembershipType = currentPriceFieldValue.membership_type_id;

      if (lineMembershipTypeID == currentLineMembershipType) {
        return true;
      }
    }

    return false;
  };

  /**
   * Adds events that handle new membership addition to the recurring
   * contribution.
   */
  CurrentPeriodLineItemHandler.prototype.setNewMembershipEvents = function () {
    var that = this;

    // Shows new line in table to add new membership.
    CRM.$('#add_membership_btn', this.currentTab).click(function () {
      that.newMembershipRow.css('display', 'table-row');
      CRM.$('#periodsContainer').tabs({ disabled: true });
      CRM.$('.clickable').addClass('disabled-click');
      CRM.$('.rc-line-item').addClass('disabled-row');
      CRM.$('.auto-renew-line-checkbox').attr('disabled', true);

      return false;
    });

    // Hides line in table to add new membership.
    CRM.$('#cancel_add_membership_btn', this.currentTab).click(function () {
      that.newMembershipRow.css('display', 'none');
      CRM.$('#periodsContainer').tabs({ disabled: false });
      CRM.$('.clickable').removeClass('disabled-click');
      CRM.$('.rc-line-item').removeClass('disabled-row');
      CRM.$('.auto-renew-line-checkbox').attr('disabled', false);

      return false;
    });

    // Makes row flash if nother part of the form is clicked.
    this.setUpRowFlashingOnOutClick(that.newMembershipRow);

    // Loads membership type information
    this.newMembershipTypeField.change(function () {
      var membershipTypeID = CRM.$(this).val();
      that.loadMembershipTypeData(membershipTypeID);
    });

    // Adds line item to recurring contribution and all pending installments.
    CRM.$('#apply_add_membership_btn', this.currentTab).click(function () {
      that.processMembershipCreation();

      return false;
    });
  };

  CurrentPeriodLineItemHandler.prototype.processMembershipCreation = function () {
    this.currentTab.block({message: null});

    var that = this;
    var params = this.buildNextPeriodLineItemCallParameters();

    CRM.api3('ContributionRecurLineItem', 'get', params)
    .done(function (nextPeriodLineItemsResult) {
      that.currentTab.unblock();

      var isMembershipTypeOnNextPeriod = that.isMembershipTypeOnNextPeriod(
        that.newMembershipTypeField.val(),
        nextPeriodLineItemsResult
      );

      var autoRenew = that.newMembershipAutoRenewField.prop('checked') === true ? 1 : 0;
      if (isMembershipTypeOnNextPeriod) {
        autoRenew = 1;
      }

      if (that.validateNewMembership()) {
        that.callNewLineConfirmationForm('civicrm/recurring-contribution/add-membership-lineitem', {
          reset: 1,
          contribution_recur_id: that.recurringContributionID,
          line_item: {
            membership_type_id: that.newMembershipTypeField.val(),
            start_date: that.newMembershipStartDateField.val(),
            end_date: that.newMembershipEndDateField.val(),
            auto_renew: autoRenew,
            amount: that.newMembershipAmountField.val()
          }
        });
      }
    });
  };

  /**
   * Sets events to make row flash when a click is done outside the row.
   *
   * @param row
   */
  CurrentPeriodLineItemHandler.prototype.setUpRowFlashingOnOutClick = function (row) {
    var that = this;

    if (typeof row === 'undefined') {
      CRM.$(window).click(function () {
        if (!that.clickedRow) {
          that.newMembershipRow.stop()
            .css('background-color', '#AAA')
            .animate({backgroundColor: that.newMembershipRowBGColor}, 1000);

          that.newDonationRow.stop()
            .css('background-color', '#AAA')
            .animate({backgroundColor: that.newDonationRowBGColor}, 1000);
        }

        that.clickedRow = false;
      });
    } else {
      row.click(function () {
        that.clickedRow = true;
      });
    }
  };

  /**
   * Adds events to handle the addition of a new donation line item to the
   * recurring contribution.
   */
  CurrentPeriodLineItemHandler.prototype.setNewDonationEvents = function () {
    var that = this;

    // Show new row on table to add donation.
    CRM.$('#add_other_btn', this.currentTab).click(function () {
      that.newDonationRow.css('display', 'table-row');
      CRM.$('#periodsContainer').tabs({ disabled: true });
      CRM.$('.clickable').addClass('disabled-click');
      CRM.$('.rc-line-item').addClass('disabled-row');
      CRM.$('.auto-renew-line-checkbox').attr('disabled', true);

      return false;
    });

    // Shows tax rate if set when changing financial type.
    this.newDonationFinancialTypeField.on('change', function() {
      var selectedId = CRM.$(this).val();
      var financialType = that.getFinancialType(selectedId);

      if (!financialType) {
        throw new Error('Invalid financial type id passed');
      }

      var rate = financialType.tax_rate || 'N/A';
      if (rate != 'N/A') {
        rate += ' %';
      }

      CRM.$('#newline_donation_tax_rate').text(rate);
    });

    // Hides new row to add new donation.
    CRM.$('#cancel_add_donation_btn', this.currentTab).click(function () {
      that.newDonationRow.css('display', 'none');
      CRM.$('#periodsContainer').tabs({ disabled: false });
      CRM.$('.clickable').removeClass('disabled-click');
      CRM.$('.rc-line-item').removeClass('disabled-row');
      CRM.$('.auto-renew-line-checkbox').attr('disabled', false);

      return false;
    });

    // Makes row flash if another part of the form is clicked.
    this.setUpRowFlashingOnOutClick(that.newDonationRow, that.newDonationRowBGColor);

    // Adds line item to recurring contribution and all pending installments.
    CRM.$('#apply_add_donation_btn', this.currentTab).click(function () {
      if (that.validateNewDonation()) {
        that.callNewLineConfirmationForm('civicrm/recurring-contribution/add-donation-lineitem', {
          reset: 1,
          contribution_recur_id: that.recurringContributionID,
          line_item: {
            item: that.newDonationItemField.val(),
            start_date: that.newDonationStartDateField.val(),
            auto_renew: that.newDonationAutoRenewField.prop('checked') === true ? 1 : 0,
            financial_type_id: that.newDonationFinancialTypeField.val(),
            amount: that.newDonationAmountField.val()
          }
        });
      }

      return false;
    });
  };

  /**
   * Returns financial type data for given ID.
   * @param id
   *
   * @return (object)
   */
  CurrentPeriodLineItemHandler.prototype.getFinancialType = function(id) {
    return this.financialTypes.filter(function(financialType) {
      return financialType.id === id;
    })[0];
  };

  /**
   * Shows confimation dialog to add new donation line item if there are
   * sufficient pending installments.
   *
   * @param path
   * @param parameters
   */
  CurrentPeriodLineItemHandler.prototype.callNewLineConfirmationForm = function (path, parameters) {
    var that = this;
    var startDate = parameters.line_item.start_date;

    this.currentTab.block({message: null});

    CRM.api3('Contribution', 'getcount', {
      'contribution_recur_id': that.recurringContributionID,
      'contribution_status_id': 'Pending',
      'receive_date': {'>=': startDate},
    }).done(function (result) {
      that.currentTab.unblock();

      if (result.result < 1) {
        CRM.alert(
          'There are no instalments left for this period. Suggest to follow the steps below:' +
          '<ul>' +
          '<li>Add the the item to next period instead.</li>' +
          '<li>(optional) Create the membership or contribution outside the recurring order.</li>' +
          '</ul>',
          null,
          'alert',
          {expires: NOTIFICATION_EXPIRE_TIME_IN_MS}
        );

        return;
      }

      var formURL = CRM.url(path, parameters);
      CRM.loadForm(formURL, {
        dialog: {width: 480, height: 0}
      }).on('crmFormSuccess', function () {
        createActivity('Update Payment Plan Current Period', 'update_payment_plan_current_period')
        CRM.refreshParent('#periodsContainer');
      });
    });
  };

  /**
   * Validates fields for new donation line item.
   *
   * @returns {boolean}
   */
  CurrentPeriodLineItemHandler.prototype.validateNewDonation = function () {
    let errors = '';

    if (!this.newDonationItemField.val().length) {
      this.newDonationItemField.addClass('required');
      errors += '<li>Item is required.</li>';
    }

    if (!this.newDonationStartDateField.val().length) {
      this.newDonationStartDateField.addClass('required');
      errors += '<li>Start date is required.</li>';
    }

    if (!this.newDonationFinancialTypeField.val().length) {
      this.newDonationFinancialTypeField.addClass('required');
      errors += '<li>Financial type is required.</li>';
    }

    if (!this.newDonationAmountField.val().length) {
      this.newDonationAmountField.addClass('required');
      errors += '<li>Amount is required.</li>';
    } else if (isNaN(this.newDonationAmountField.val())) {
      this.newDonationAmountField.addClass('required');
      errors += '<li>Amount must be a valid number.</li>';
    }

    if (errors.length > 0) {
      CRM.alert('<p>Required fields are missing:</p> <ul>' + errors + '</ul>', 'Missing Fields', 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return false;
    }

    return true;
  };

  /**
   * Validates new membership line item.
   *
   * @return (boolean)
   */
  CurrentPeriodLineItemHandler.prototype.validateNewMembership = function () {
    if (!this.validateMembershipRequiredFields()) {
      return false;
    }

    return this.validateDates();
  };

  /**
   * Validates fields required to add a membership.
   *
   * @return {boolean}
   */
  CurrentPeriodLineItemHandler.prototype.validateMembershipRequiredFields = function () {
    let errors = '';

    if (!this.newMembershipTypeField.val().length) {
      this.newMembershipTypeField.addClass('required');
      errors += '<li>Membership type is required.</li>';
    }

    if (!this.newMembershipStartDateField.val().length) {
      this.newMembershipStartDateField.addClass('required');
      errors += '<li>Start date is required.</li>';
    }

    if (!this.newMembershipEndDateField.val().length) {
      this.newMembershipEndDateField.addClass('required');
      errors += '<li>End date is required.</li>';
    }

    if (!this.newMembershipAmountField.val().length) {
      this.newMembershipAmountField.addClass('required');
      errors += '<li>Amount is required.</li>';
    } else if (isNaN(this.newMembershipAmountField.val())) {
      this.newMembershipAmountField.addClass('required');
      errors += '<li>Amount must be a valid number.</li>';
    }

    if (errors.length > 0) {
      CRM.alert('<p>Required fields are missing:</p> <ul>' + errors + '</ul>', 'Missing Fields', 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return false;
    }

    return true;
  };

  /**
   * Validates dates for membership line item.
   *
   * @return {boolean}
   */
  CurrentPeriodLineItemHandler.prototype.validateDates = function () {
    const startDate = new Date(this.newMembershipStartDateField.val());
    const endDate = new Date(this.newMembershipEndDateField.val());

    if (endDate < startDate) {
      this.newMembershipStartDateField.addClass('required');
      this.newMembershipEndDateField.addClass('required');
      CRM.alert('<p>Start date cannot be larger than end date!</p>', 'Dates Validation', 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

      return false;
    }

    var periodEndDate = new Date(CRM.$('#current_period_end_date').html());
    if (endDate > periodEndDate) {
      CRM.alert('<p>Additional memberships must end on or before the end date of the period. Please select a different end date for the membership.</p>', 'Dates Validation', 'error', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});
      return false;
    }

    return true;
  };

  /**
   * Shows form in a new modal dialog to remove selected line item.
   *
   * @param lineItemID
   */
  CurrentPeriodLineItemHandler.prototype.showLineItemRemovalConfirmation = function (lineItemID) {
    var that = this;

    this.currentTab.block({message: null});

    CRM.api3('ContributionRecurLineItem', 'getcount', {
      'contribution_recur_id': that.recurringContributionID,
      'end_date': {'IS NULL': 1}
    }).done(function (result) {
      that.currentTab.unblock();

      if (result.result < 2) {
        CRM.alert('Cannot remove the last item in an order!', null, 'alert', {expires: NOTIFICATION_EXPIRE_TIME_IN_MS});

        return;
      }

      var formUrl = CRM.url('civicrm/recurring-contribution/remove-lineitems', {
        reset: 1,
        contribution_recur_id: that.recurringContributionID,
        line_item_id: lineItemID
      });

      CRM.loadForm(formUrl, {
        dialog: {width: 480, height: 0}
      }).on('crmFormSuccess', function () {
        createActivity('Update Payment Plan Current Period', 'update_payment_plan_current_period');
        CRM.refreshParent('#periodsContainer');
      });
    });
  };

  /**
   * Loads data for the given membership ID.
   *
   * @param typeID
   */
  CurrentPeriodLineItemHandler.prototype.loadMembershipTypeData = function (typeID) {
    var that = this;

    if (isNaN(typeID)) {
      return;
    }

    if (typeof this.membershipTypes[typeID] !== 'undefined') {
      this.showMembershipTypeInfo(this.membershipTypes[typeID]);
    } else {
      this.currentTab.block({message: null});
      var callParameters = this.buildMembershipTypesCallParameters(typeID);

      CRM.api3(callParameters).done(function(results) {
        that.currentTab.unblock();
        that.membershipTypes[typeID] = results['membershipType'];

        if (typeof results['recurringContribution'] !== 'undefined') {
          that.recurringContribution = results['recurringContribution'];
        }

        that.showMembershipTypeInfo(that.membershipTypes[typeID]);
      });
    }
  };

  CurrentPeriodLineItemHandler.prototype.buildNextPeriodLineItemCallParameters = function () {
    var params = {
      'sequential': 1,
      'contribution_recur_id': this.recurringContribution.id,
      'auto_renew': true,
      'is_removed': 0,
      'options': {'limit': 0},
      'api.LineItem.get': {
        'sequential': 1,
        'id': '$value.line_item_id',
        'entity_table': {'IS NOT NULL': 1},
        'entity_id': {'IS NOT NULL': 1},
        'api.PriceFieldValue.getsingle': {
          'id': '$value.price_field_value_id',
          'context': 'Membershipextras'
        }
      }
    };

    if (typeof this.recurringContribution.installments === 'undefined' || this.recurringContribution.installments <= 1) {
      params.end_date = {'IS NULL': 1};
    }

    return params;
  };

  /**
   * Builds API parameter arrays to obtain information required to show data
   * for selected membership type.
   */
  CurrentPeriodLineItemHandler.prototype.buildMembershipTypesCallParameters = function (typeID) {
    var membershipTypeParams = {
      'sequential': 1,
      id: typeID,
      'api.EntityFinancialAccount.getsingle': {
        'entity_id': '$value.financial_type_id',
        'entity_table': 'civicrm_financial_type',
        'context': 'Membershipextras',
        'account_relationship': {
          'IN': ['Sales Tax Account is']
        },
        'api.FinancialAccount.getsingle': {
          'id': '$value.financial_account_id',
          'context': 'Membershipextras'
        }
      },
      'api.FinancialType.getsingle': {
        'id': '$value.financial_type_id',
        'context': 'Membershipextras'
      }
    };

    var apiCalls = {
      'membershipType': ['MembershipType', 'getsingle', membershipTypeParams]
    };

    if (typeof this.recurringContribution.id === 'undefined') {
      apiCalls.recurringContribution = [
        'ContributionRecur',
        'getsingle',
        {'id': this.recurringContributionID}
      ];
    }

    return apiCalls;
  };

  /**
   * Shows info for the given membership on the form.
   *
   * @param membershipTypeData
   */
  CurrentPeriodLineItemHandler.prototype.showMembershipTypeInfo = function (membershipTypeData) {
    const that = this;
    const financialType = membershipTypeData['api.FinancialType.getsingle'];
    const taxAccount = membershipTypeData['api.EntityFinancialAccount.getsingle']['api.FinancialAccount.getsingle'];
    const numberOfInstallments = this.getNumberOfInstallments();
    let minAmount;

    if (typeof membershipTypeData.minimum_fee !== 'undefined') {
      minAmount = Math.round((membershipTypeData.minimum_fee / numberOfInstallments) * 100) / 100;
    }

    this.newMembershipAmountField.val(minAmount);
    CRM.$('#newline_financial_type', this.newMembershipRow).html(financialType.name);

    if (typeof taxAccount !== 'undefined') {
      var taxRate = Math.round(taxAccount.tax_rate * 100) / 100;
      CRM.$('#newline_tax_rate', this.newMembershipRow).html(taxRate + ' %');
    } else {
      CRM.$('#newline_tax_rate', this.newMembershipRow).html('N/A');
    }

    const params = this.buildNextPeriodLineItemCallParameters();
    this.currentTab.block();
    CRM.api3('ContributionRecurLineItem', 'get', params)
    .done(function (nextPeriodLineItemsResult) {
      that.currentTab.unblock({message: null});
      const isMembershipTypeOnNextPeriod = that.isMembershipTypeOnNextPeriod(
        that.newMembershipTypeField.val(),
        nextPeriodLineItemsResult
      );

      if (isMembershipTypeOnNextPeriod) {
        that.newMembershipAutoRenewField.prop('checked', true);
        that.newMembershipAutoRenewField.prop('disabled', true);
      }
    });
  };

  /**
   * Calculates number of installments the current recurring contribution has.
   *
   * @return {number}
   */
  CurrentPeriodLineItemHandler.prototype.getNumberOfInstallments = function () {
    let numberOfInstallments = 1;

    if (typeof this.recurringContribution.installments != 'undefined') {
      numberOfInstallments = parseInt(this.recurringContribution.installments);
    }

    return numberOfInstallments > 0 ? numberOfInstallments : 1;
  }

  return CurrentPeriodLineItemHandler;
})(CRM.$);
