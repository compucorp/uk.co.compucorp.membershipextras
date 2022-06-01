function paymentPlanToggler(togglerValue, currencySymbol) {

  CRM.$(function ($) {

    /**
     * Perform changes on form to add payment plan as an option to pay for
     * membership.
     */
    $(function () {
      moveMembershipFormFields();
      setMembershipFormEvents();
      initializeMembershipForm();
      hideOfflineAutorenewField();
      toggleNumOfTermsField();
    });

    /**
     * Leaves form in a consistent state after the fields have been moved and the
     * new events added.
     */
    function initializeMembershipForm() {
      selectPaymentPlanTab(togglerValue);
    }

    /**
     * Reorders fields within the form to follow a coherent logic with the new
     * payment plan fields.
     */
    function moveMembershipFormFields() {
      $('#contributionTypeToggle').insertBefore($('#is_different_contribution_contact').parent().parent());
      $('#recordContribution legend:first').html('Contribution and Payment Plan');
      $('#payment_plan_schedule_row').insertAfter($('#financial_type_id').parent().parent());
      $('#payment_plan_schedule_instalment_row').insertAfter($('#payment_plan_schedule_row'));
      $('span.crm-error').css('display', 'none');
      $('label span.crm-error').css('display', 'inline');
      $('#payment_plan_fields_tabs').insertBefore($('#payment_plan_schedule_row').closest('table'));
    }

    /**
     * Selects Payment Plan tab
     *
     * @param {String} tabOptionId
     */
    function selectPaymentPlanTab(tabOptionId) {
      const allTabsSelector = '#payment_plan_fields_tabs li';
      const tabSelector = `${allTabsSelector}[data-selector=${tabOptionId}]`;

      $(allTabsSelector).removeClass('ui-tabs-active');
      $(tabSelector).addClass('ui-tabs-active');
      $('[name=contribution_type_toggle]').val(tabOptionId);
      updateContributionPaymentPlanView(tabOptionId);
    }

    /**
     * Hides the auto renew option field when the contribution tab is opened.
     */
    function hideOfflineAutorenewField() {
      const autoRenewSelector = '.custom-group-offline_autorenew_option';

      waitForElement($, '#customData', 
        element => isPaymentPlanTabActive()
          ? $(autoRenewSelector).show()
          : $(autoRenewSelector).hide()
      );
      $('a[href="#contribution-subtab"]').click( event => $(autoRenewSelector).hide());
    }

    /**
     * Gets Membership type details based on the selected Membership Type,
     * Price set, or Payment Plan Schedule
     */
    function setScheduleEvents() {
      $('#total_amount, #membership_type_id_1, #record_contribution').change(() => {
        if (!isPaymentPlanTabActive()) {
          return;
        }

        let isPriceSet = isPriceSetSelected();
        if (isPriceSet) {
          let selectedPriceFieldValues = getSelectedPriceFieldValues();
          if (jQuery.isEmptyObject(selectedPriceFieldValues)) {
            return;
          }
          let params = {};
          params.price_field_values = {'IN': selectedPriceFieldValues};
          CRM.api3('PaymentSchedule', 'getscheduleoptionsbypricefieldvalues', params).then(function (result) {
            if (result.is_error === 0) {
              setPaymentPlanScheduleOption(result.values);
              generateInstalmentSchedule(isPriceSet);
            } else {
              CRM.alert(result.error_message, 'Error', 'error');
            }
          });
        } else {
          CRM.api3('PaymentSchedule', 'getscheduleoptionsbymembershiptype', {
            'membership_type_id': parseInt($('#membership_type_id_1').val()),
          }).then(function (result) {
            if (result.is_error === 0) {
              setPaymentPlanScheduleOption(result.values);
              generateInstalmentSchedule(isPriceSet);
            } else {
              CRM.alert(result.error_message, 'Error', 'error');
            }
          });
        }
        assignFirstContributionReceiveDate();
      });

      $('#payment_plan_schedule, #payment_instrument_id, #start_date, #end_date').change(() => {
        if (!isPaymentPlanTabActive()) {
          return;
        }
        generateInstalmentSchedule(isPriceSetSelected(), $('#start_date').val());
        assignFirstContributionReceiveDate();
      });

      $('#renewal_date').change(() => {
        if (!isPaymentPlanTabActive()) {
          return;
        }
        generateInstalmentSchedule(isPriceSetSelected(), $('#renewal_date').val());
        assignFirstContributionReceiveDate();
      });
    }

    /**
     * Checks if payment plan tab is active
     *
     * @returns {boolean}
     */
    function isPaymentPlanTabActive() {
      if ($('#payment_plan_schedule_row').is(":hidden")) {
        return false;
      }

      return true;
    }

    /**
     * Assigns first contribution received date from either start date or join date
     * on new Membership Form and renewal date on renew membership form
     */
    function assignFirstContributionReceiveDate() {
      let receivedDate;
      if (isRenewMembershipForm()) {
        receivedDate = $('#renewal_date').val();
      } else {
        let startDate = $('#start_date').val();
        receivedDate = !startDate || 0 === startDate.length ? $('#join_date').val() : startDate;
      }
      $('#receive_date').val(receivedDate);
    }

    /**
     * Checks if price set is selected instead of Membership type
     *
     * @return {boolean} isPriceSet
     */
    function isPriceSetSelected() {
      const priceSetIdSelector = $('#price_set_id');
      return priceSetIdSelector.length > 0 && priceSetIdSelector.val();
    }

    /**
     * Generates Instalments schedule based on selected schedule
     * and selected price set or membership type
     *
     * @param {boolean} isPriceSet
     * @param startDate
     */
    function generateInstalmentSchedule(isPriceSet, startDate) {
      let schedule = $('#payment_plan_schedule').val();
      let params = {
        schedule: schedule,
        start_date: startDate,
        join_date: $('#join_date').val(),
        payment_method: $('#payment_instrument_id').val(),
      };
      if (isPriceSet) {
        let selectedPriceFieldValues = getSelectedPriceFieldValues();
        if (jQuery.isEmptyObject(selectedPriceFieldValues)) {
          return;
        }
        params.price_field_values = selectedPriceFieldValues;
      } else {
        params.membership_type_id = parseInt($('#membership_type_id_1').val());
      }
      let url = CRM.url('civicrm/member/instalment-schedule', params, 'back');
      CRM.loadPage(url, {
        target: '#instalment_schedule_table',
        dialog: false,
      }).on('crmLoad', function (event, data) {
        if (data.hasOwnProperty('is_error') && data.is_error == true) {
          CRM.alert(data.error_message, 'Error', 'error');
        } else {
          updateTotalAmount($('#instalment-total-amount').html(), isPriceSet);
          setMembershipDates($('#instalment-membership-start-date').html(), $('#instalment-membership-end-date').html());
        }
      });
    }

    /**
     * Returns selected price field values based the selected inputs
     *
     * @return object selectedPriceFieldValues
     */
    function getSelectedPriceFieldValues() {
      let selectedPriceFieldValues = {};
      $("#priceset [price]").each(function () {
        let elementType = $(this).prop('type');
        switch (elementType) {
          case 'checkbox':
            addOrRemoveSelectedPriceFieldValues(selectedPriceFieldValues, JSON.parse($(this).attr('price'))[0], $(this).is(':checked'));
            break;
          case 'text':
            let isInputHasValue = !isNaN(parseInt($(this).val()));
            addOrRemoveSelectedPriceFieldValues(selectedPriceFieldValues, JSON.parse($(this).attr('price'))[0], isInputHasValue, $(this).val());
            break;
          case 'radio':
            addOrRemoveSelectedPriceFieldValues(selectedPriceFieldValues, $(this).val(), $(this).is(':checked'));
            break;
          case 'select-one':
            let priceFieldId = $(this).val();
            if (!priceFieldId) {
              break;
            }
            let options = $(this).prop('options');
            for (let option of options) {
              addOrRemoveSelectedPriceFieldValues(selectedPriceFieldValues, priceFieldId, true);
            }
            break;
        }
      });

      return selectedPriceFieldValues;
    }

    /**
     * Adds or removes selected price field from selectedPriceFieldValue object
     *
     * @param selectedPriceFieldValues
     * @param priceFieldId
     * @param isSelected
     * @param qty
     */
    function addOrRemoveSelectedPriceFieldValues(selectedPriceFieldValues, priceFieldId, isSelected, qty = 1) {
      if (isSelected) {
        selectedPriceFieldValues[priceFieldId] = qty;
      } else {
        delete selectedPriceFieldValues[priceFieldId];
      }
    }

    /**
     * Sets membership dates when using new membership form
     *
     */
    function setMembershipDates(startDate, endDate) {
      if (isRenewMembershipForm()) {
        return;
      }
      $('#start_date').val(startDate);
      $('#start_date').next('.hasDatepicker').datepicker('setDate', new Date(startDate));
      $('#end_date').val(endDate);
      $('#end_date').next('.hasDatepicker').datepicker('setDate', new Date(endDate));
    }

    /**
     * Updates total amount based and also updated price value if is price set amount
     */
    function updateTotalAmount(totalAmount, isPriceSet) {
      $('#total_amount').val(totalAmount);
      if (isPriceSet) {
        $('#pricevalue').html(currencySymbol + ' ' + CRM.formatMoney(totalAmount, true));
      }
    }

    /**
     * Sets PaymentPlan Schedule Options based on the membership period type
     */
    function setPaymentPlanScheduleOption(options) {
      $('#payment_plan_schedule').empty();
      $.each(options, function (key, value) {
        $('#payment_plan_schedule')
          .append($("<option></option>")
            .attr("value", key)
            .text(value));
      });
    }

    /**
     * Creates events that modify the behaviour of the form:
     */
    function setMembershipFormEvents() {
      setupPayPlanTogglingEvents();
      setScheduleEvents();
    }

    /**
     * Adds events that enable toggling contribution/payment plan selection:
     * - Reloads original total amount if payment plan is selected.
     * - Changes total_amount field to readonly if payment plan is selected.
     * - Shows installments, frequency and frequency unit fields if payment plan
     *   is selected.
     * - Hides transaction id if payment plan is selected.
     * - Resets form to original state if contribution is selected.
     */
    function setupPayPlanTogglingEvents() {
      $('#payment_plan_fields_tabs li').click(function () {
        const tabOptionId = $(this).attr('data-selector');
        selectPaymentPlanTab(tabOptionId);
      });
    }

    /**
     * Updates the view of the Contribution / Payment Plan form
     * depending on the selected tab
     *
     * @param {String} tabOptionId
     */
    function updateContributionPaymentPlanView(tabOptionId) {
      if (isPaymentPlanTab(tabOptionId)) {
        $('#payment_plan_schedule_row').show();
        $('#payment_plan_schedule_instalment_row').show();
        if ($('#membership_type_id_1').val()) {
          $('#membership_type_id_1').change();
        }
      } else {
        $('#payment_plan_schedule_row').hide();
        $('#payment_plan_schedule_instalment_row').hide();
        $('#receive_date').val('');
      }
      if (isRenewMembershipForm()) {
        updateRenewMembershipForm(tabOptionId);
      } else {
        updateNewMembershipForm(tabOptionId);
      }
      assignFirstContributionReceiveDate();
    }

    /**
     * Updates view for New Membership Form
     *
     * @param tabOptionId
     */
    function updateNewMembershipForm(tabOptionId) {
      if (isPaymentPlanTab(tabOptionId)) {
        $('.crm-membership-form-block-trxn_id').hide();
        $('.crm-membership-form-block-receive_date').hide();
        $('.crm-membership-form-block-total_amount').hide();
        $('.crm-membership-form-block-financial_type_id').hide();
        $('.crm-membership-form-block-contribution_status_id').hide();
        $('.crm-membership-form-block-payment_instrument_id')
          .insertBefore('.crm-membership-form-block-contribution-contact');
        $('.crm-membership-form-block-billing').insertAfter('.crm-membership-form-block-payment_instrument_id');
      } else {
        $('.crm-membership-form-block-trxn_id').show();
        $('.crm-membership-form-block-receive_date').show();
        $('.crm-membership-form-block-total_amount').show();
        $('.crm-membership-form-block-financial_type_id').show();
        $('.crm-membership-form-block-contribution_status_id').show();
        $('.crm-membership-form-block-payment_instrument_id')
          .insertBefore('.crm-membership-form-block-contribution_status_id');
        $('.crm-membership-form-block-billing').insertAfter('.crm-membership-form-block-contribution_status_id');
      }
    }

    /**
     * Updates view for RenewMembership Form
     *
     * @param tabOptionId
     */
    function updateRenewMembershipForm(tabOptionId) {
      if (isPaymentPlanTab(tabOptionId)) {
        $('.crm-membershiprenew-form-block-trxn_id').hide();
        $('.crm-membershiprenew-form-block-receive_date').hide();
        $('.crm-membershiprenew-form-block-total_amount').hide();
        $('.crm-membershiprenew-form-block-financial_type_id').hide();
        $('.crm-membershiprenew-form-block-contribution_status_id').hide();
        $('.crm-membershiprenew-form-block-payment_instrument_id')
          .insertBefore('.crm-membershiprenew-form-block-contribution-contact');
        $('.crm-membershiprenew-form-block-billing').insertAfter('.crm-membershiprenew-form-block-payment_instrument_id');
      } else {
        $('.crm-membershiprenew-form-block-trxn_id').show();
        $('.crm-membershiprenew-form-block-receive_date').show();
        $('.crm-membershiprenew-form-block-total_amount').show();
        $('.crm-membershiprenew-form-block-financial_type_id').show();
        $('.crm-membershiprenew-form-block-contribution_status_id').show();
        $('.crm-membershiprenew-form-block-payment_instrument_id')
          .insertBefore('.crm-membershiprenew-form-block-contribution_status_id');
        $('.crm-membershiprenew-form-block-billing').insertAfter('.crm-membershiprenew-form-block-contribution_status_id');
      }
    }

    /**
     * Checks if payment plan tab is selected.
     *
     * @param tabOptionId
     * @returns {boolean}
     */
    function isPaymentPlanTab(tabOptionId) {
      return tabOptionId === 'payment_plan';
    }

    /**
     * Checks if the form is a Renew Membership Form.
     *
     * @returns {boolean}
     */
    function isRenewMembershipForm() {
      return !!$('#MembershipRenewal').length;
    }

    /**
     * Disable the “Number of Terms" if payment tab is selected,
     * otherwise enable.
     */
    function toggleNumOfTermsField() {
      $('#num_terms').val(1);
      $('#num_terms').prop("readonly", isPaymentPlanTabActive());

      waitForElement($, 'input[name=contribution_type_toggle]', 
        element => {
          $('#num_terms').val(1);
          $('#num_terms').prop("readonly", isPaymentPlanTabActive());
        }
      );
    }

    /**
     * Triggers callback when element attribute changes.
     * 
     * @param {object} $ 
     * @param {string} elementPath 
     * @param {object} callBack 
     */
    function waitForElement($, elementPath, callBack) {
      (new MutationObserver(function(mutations) {
        callBack($(elementPath));
      })).observe(document.querySelector(elementPath), {
        attributes: true
      });
    };
  });

}
