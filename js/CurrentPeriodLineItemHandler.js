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
    this.newMembershipRow = null;
    this.newMembershipTypeField = null;
    this.newMembershipStartDateField = null;
    this.newMembershipEndDateField = null;
    this.newMembershipAutoRenewField = null;
    this.newMembershipAmountField = null;
    this.clickedRow = false;

    this.membershipTypes = {};
    this.recurringContribution = {};
  }

  CurrentPeriodLineItemHandler.prototype.set = function (varName, value) {
    this[varName] = value;
  }

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
  }

  /**
   * Adds event handlers to form's elements.
   */
  CurrentPeriodLineItemHandler.prototype.addEventHandlers = function () {
    var that = this;

    CRM.$('.auto-renew-line-checkbox', this.currentTab).change(function() {
      if (!this.checked) {
        var itemData = CRM.$(this).closest('tr').data('item-data');
        console.log(itemData);
        showNextPeriodLineItemRemovalConfirmation(itemData);
      }
    })

    CRM.$('.remove-line-button', this.currentTab).each(function () {
      CRM.$(this).click(function () {
        var itemID = CRM.$(this).data('itemid');
        that.showLineItemRemovalConfirmation(itemID);

        return false;
      });
    });

    CRM.$('#add_membership_btn', this.currentTab).click(function () {
      that.newMembershipRow.css('display', 'table-row');
      CRM.$('.clickable').addClass('disabled-click');
      CRM.$('.rc-line-item').addClass('disabled-row');
      CRM.$('.auto-renew-line-checkbox').attr('disabled', true);

      return false;
    });

    CRM.$('#cancel_add_membership_btn', this.currentTab).click(function () {
      that.newMembershipRow.css('display', 'none');
      CRM.$('.clickable').removeClass('disabled-click');
      CRM.$('.rc-line-item').removeClass('disabled-row');
      CRM.$('.auto-renew-line-checkbox').attr('disabled', false);

      return false;
    });

    CRM.$(window).click(function () {
      if (!that.clickedRow) {
        var originalBg = that.newMembershipRowBGColor;
        that.newMembershipRow
        .stop()
        .css('background-color', '#AAA')
        .animate({backgroundColor: originalBg}, 1000);
      }

      that.clickedRow = false;
    });

    this.newMembershipRow.click(function (event) {
      that.clickedRow = true;
    });

    this.newMembershipTypeField.change(function () {
      var membershipTypeID = CRM.$(this).val();
      that.loadMembershipTypeData(membershipTypeID);
    });

    CRM.$('#apply_add_membership_btn', this.currentTab).click(function () {
      if (that.validateNewMembership()) {
        that.showMembershipLineItemAddConfirmation();
      }

      return false;
    });
  }

  /**
   * Validates new membership line item.
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
  }

  /**
   * Shows confirmation form when adding a new membership line item.
   */
  CurrentPeriodLineItemHandler.prototype.showMembershipLineItemAddConfirmation = function () {
    var that = this;
    var startDate = this.newMembershipStartDateField.val();

    this.currentTab.block({message: null});

    CRM.api3('Contribution', 'getcount', {
      'contribution_recur_id': that.recurringContributionID,
      'contribution_status_id': 'Pending',
      'end_date': {'>=': startDate}
    }).done(function (result) {
      that.currentTab.unblock();

      if (result.result < 1) {
        CRM.alert(
          'No outstanding instalment contribution from the selected start date. Suggest to follow the steps below:' +
          '<ul>' +
          '<li>Add the the item to next period instead.</li>' +
          '<li>(optional) Create the membership or contribution outside the recurring order.</li>' +
          '</ul>',
          null,
          'alert'
        );

        return;
      }

      var formUrl = CRM.url('civicrm/recurring-contribution/add-membership-lineitem', {
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

      CRM.loadForm(formUrl, {
        dialog: {width: 480, height: 0}
      }).on('crmFormSuccess', function () {
        CRM.refreshParent('#periodsContainer');
      });
    });
  }

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
        CRM.refreshParent('#periodsContainer');
      });
    });
  }

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

    if (typeof this.membershipTypes[typeID] != 'undefined') {
      this.showMembershipTypeInfo(this.membershipTypes[typeID]);
    } else {
      this.currentTab.block({message: null});
      var callParameters = this.buildMembershipTypesCallParameters(typeID);

      CRM.api3(callParameters).done(function(results) {
        that.currentTab.unblock();
        that.membershipTypes[typeID] = results['membershipType'];

        if (typeof results['recurringContribution'] != 'undefined') {
          that.recurringContribution = results['recurringContribution'];
        }

        that.showMembershipTypeInfo(that.membershipTypes[typeID]);
      });
    }
  }

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
      'membershipType': ['MembershipType', 'getsingle', membershipTypeParams],
    };

    if (typeof this.recurringContribution.id == 'undefined') {
      apiCalls.recurringContribution = [
        'ContributionRecur',
        'getsingle',
        {'id': this.recurringContributionID}
      ];
    }

    return apiCalls;
  }

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

    if (typeof taxAccount != 'undefined') {
      var taxRate = Math.round(taxAccount.tax_rate * 100) / 100;
      CRM.$('#newline_tax_rate', this.newMembershipRow).html(taxRate + ' %');
    }
  }

  return CurrentPeriodLineItemHandler;
})(CRM.$);
