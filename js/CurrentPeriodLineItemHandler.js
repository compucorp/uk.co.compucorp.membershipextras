CRM.RecurringContribution = CRM.RecurringContribution || {};

/**
 * This class handles fron-end events and logic associated to manging line items
 * for a recurring contribution.
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
  };

  /**
   * Adds event handlers to form's elements.
   */
  CurrentPeriodLineItemHandler.prototype.addEventHandlers = function () {
    this.setUpRowFlashingOnOutClick();
    this.setLineItemRemovalEvents();
    this.setNewMembershipEvents();
    this.setNewDonationEvents();
  };

  /**
   * Adds events to the form to handle line item removal.
   */
  CurrentPeriodLineItemHandler.prototype.setLineItemRemovalEvents = function () {
    var that = this;

    // Shows removal confirmation dialog.
    CRM.$('.auto-renew-line-checkbox', this.currentTab).change(function() {
      var itemData = CRM.$(this).closest('tr').data('item-data');
      if (!this.checked) {
        showNextPeriodLineItemRemovalConfirmation(itemData);
      } else {
        if (Number(itemData.auto_renew)) {
          CRM.alert(ts('This membership type is already enrolled in next period.'), null, 'warning');
        }
        showAddLineItemConfirmation(itemData.label, Number(itemData.line_total), itemData.financial_type_id);
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
   * Adds events tht handle new membership addition to the recurring
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
      if (that.validateNewMembership()) {
        that.callNewLineConfirmationForm('civicrm/recurring-contribution/add-membership-lineitem', {
          reset: 1,
          contribution_recur_id: that.recurringContributionID,
          line_item: {
            membership_type_id: that.newMembershipTypeField.val(),
            start_date: that.newMembershipStartDateField.val(),
            end_date: that.newMembershipEndDateField.val(),
            auto_renew: that.newMembershipAutoRenewField.prop('checked') === true ? 1 : 0,
            amount: that.newMembershipAmountField.val()
          }
        });
      }

      return false;
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
    return financialTypes.filter(function(financialType) {
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
      'end_date': {'>=': startDate}
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
          'alert'
        );

        return;
      }

      var formURL = CRM.url(path, parameters);
      CRM.loadForm(formURL, {
        dialog: {width: 480, height: 0}
      }).on('crmFormSuccess', function () {
        createActivity('Update Payment Plan Current Period', 'Update Payment Plan Current Period')
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
    var errors = '';

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
      CRM.alert('<p>Required fields are missing:</p> <ul>' + errors + '</ul>', 'Missing Fields', 'error');

      return false;
    }

    return true;
  }

  /**
   * Validates new membership line item.
   *
   * @return (boolean)
   */
  CurrentPeriodLineItemHandler.prototype.validateNewMembership = function () {
    var errors = '';

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
      CRM.alert('<p>Required fields are missing:</p> <ul>' + errors + '</ul>', 'Missing Fields', 'error');

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
        CRM.alert('Cannot remove the last item in an order!', null, 'alert');

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
        createActivity('Update Payment Plan Current Period', 'Update Payment Plan Current Period');
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
        'account_relationship': {
          'IN': ['Sales Tax Account is']
        },
        'api.FinancialAccount.getsingle': {
          'id': '$value.financial_account_id'
        }
      },
      'api.FinancialType.getsingle': {
        'id': '$value.financial_type_id'
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
    var financialType = membershipTypeData['api.FinancialType.getsingle'];
    var taxAccount = membershipTypeData['api.EntityFinancialAccount.getsingle']['api.FinancialAccount.getsingle'];
    var numberOfInstallments = this.recurringContribution.installments;
    var minAmount = Math.round((membershipTypeData.minimum_fee / numberOfInstallments) * 100) / 100;

    this.newMembershipAmountField.val(minAmount);
    CRM.$('#newline_financial_type', this.newMembershipRow).html(financialType.name);

    if (typeof taxAccount !== 'undefined') {
      var taxRate = Math.round(taxAccount.tax_rate * 100) / 100;
      CRM.$('#newline_tax_rate', this.newMembershipRow).html(taxRate + ' %');
    }
  };

  return CurrentPeriodLineItemHandler;
})(CRM.$);
