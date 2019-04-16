<script type="text/javascript">
  {literal}
  if (typeof buildAutoRenew !== 'function') {
    function buildAutoRenew () {}
  }

  (function ($) {
    {/literal}
    const togglerValue = '{$contribution_type_toggle}';
    const membershipextrasAllMembershipData = {$allMembershipInfo};
    const membershipextrasTaxRatesStr = '{$taxRates}';
    const membershipextrasTaxTerm = '{$taxTerm}';
    const membershipextrasCurrency = '{$currency}';
    {literal}
    const membershipextrasTaxRates = membershipextrasTaxRatesStr !== ''
      ? JSON.parse(membershipextrasTaxRatesStr)
      : [];

    /**
     * Perform changes on form to add payment plan as an option to pay for
     * membership.
     */
    $(function () {
      moveMembershipFormFields();
      setMembershipFormEvents();
      initializeMembershipForm();
    });

    /**
     * Leaves form in a consistent state after the fields have been moved and the
     * new events added.
     */
    function initializeMembershipForm () {
      $('#invoice_date_summary').html($('#receive_date').val());
      selectPaymentPlanTab(togglerValue);
      toggleStatusOfPaymentAndAutoRenewCheckboxes($('#record_contribution'));
    }

    /**
     * Reorders fields within the form to follow a coherent logic with the new
     * payment plan fields.
     */
    function moveMembershipFormFields () {
      $('#contributionTypeToggle').insertBefore($('#is_different_contribution_contact').parent().parent());
      $('#recordContribution legend:first').html('Contribution and Payment Plan');
      $('#installments_row').insertAfter($('#financial_type_id').parent().parent());
      $('#first_installment').insertAfter($('#installments_row'));
      $('span.crm-error').css('display', 'none');
      $('label span.crm-error').css('display', 'inline');
      $('#payment_plan_fields_tabs').insertBefore($('#installments_row').closest('table'));
    }

    /**
     * Selects Payment Plan tab and syncs the selection with radiobuttons
     *
     * @param {String} tabOptionId
     */
    function selectPaymentPlanTab (tabOptionId) {
      const allTabsSelector = '#payment_plan_fields_tabs li';
      const tabSelector = `${allTabsSelector}[data-selector=${tabOptionId}]`;

      $(allTabsSelector).removeClass('ui-tabs-active');
      $(tabSelector).addClass('ui-tabs-active');
      $('[name=contribution_type_toggle]').val(tabOptionId);
      updateContributionPaymentPlanView(tabOptionId);
    }

    /**
     * Adds events so that any changes done to the form are reflected on first
     * invoice summary:
     *
     * - Changes date in invoice summary when recieve date is changed by user.
     * - Recalculates amount in invoice summary if installments, frequency or
     *   frequecy unit is changed.
     */
    function setInvoiceSummaryEvents () {
      $('tr.crm-membership-form-block-receive_date td input').change(function () {
        if ($(this).attr('name').indexOf('receive_date_display_') >= 0) {
          $('#invoice_date_summary').html($(this).val());
        }
      });

      $('#installments, #total_amount, #membership_type_id_1').change(() => {
        let currentMembershipData, taxRate, taxAmount, taxPerPeriod;
        let taxMessage = '';
        let taxPerPeriodMessage = '';
        const currentAmount = parseFloat($('#total_amount').val().replace(/[^0-9.]+/g, ''));
        const amountPerPeriod = currentAmount / parseFloat($('#installments').val());
        const memType = parseInt($('#membership_type_id_1').val());
        const isPriceSet = cj('#price_set_id').length > 0 && cj('#price_set_id').val();

        if (!isPriceSet) {
          currentMembershipData = membershipextrasAllMembershipData[memType];
          taxRate = membershipextrasTaxRates[currentMembershipData['financial_type_id']];

          if (taxRate !== undefined) {
            taxAmount = (currentAmount * (taxRate / 100)) / (1 + (taxRate / 100));
            taxAmount = isNaN(taxAmount) ? 0 : taxAmount.toFixed(2);
            taxPerPeriod = (taxAmount / parseFloat($('#installments').val())).toFixed(2);
            taxMessage = `Includes ${membershipextrasTaxTerm} amount of ${membershipextrasCurrency} ${taxAmount}`;
            taxPerPeriodMessage = `Includes ${membershipextrasTaxTerm} amount of ${membershipextrasCurrency} ${taxPerPeriod}`;
          }
        }

        $('.totaltaxAmount').html(taxMessage);
        $('#amount_summary').html(`${membershipextrasCurrency} ${amountPerPeriod.toFixed(2)} <br/> ${taxPerPeriodMessage}`);
        toggleStatusOfPaymentAndAutoRenewCheckboxes($('#record_contribution'));
      });
    }

    /**
     * Creates events that modify the behaviour of the form:
     */
    function setMembershipFormEvents () {
      setupPayPlanTogglingEvents();
      setInvoiceSummaryEvents();
    }

    /**
     * Adds events that enable toggling contribution/payment plan selection:
     * - Enables/disables auto-renew checkbox if payment is going to be recorded
     *   or not.
     * - Reloads original total amount if payment plan is selected.
     * - Changes total_amount field to readonly if payment plan is selected.
     * - Shows installments, frequency and frequency unit fields if payment plan
     *   is selected.
     * - Hides transaction id if payment plan is selected.
     * - Resets form to original state if contribution is selected.
     */
    function setupPayPlanTogglingEvents () {
      $('#record_contribution').change(function () {
        toggleStatusOfPaymentAndAutoRenewCheckboxes($(this));
      });

      $('#payment_plan_fields_tabs li').click(function () {
        const tabOptionId = $(this).attr('data-selector');

        selectPaymentPlanTab(tabOptionId);
      });
    }

    /**
     * Checks status of given checkbox and enables/disables auto-renew
     * checkbox if checked/unchecked, respectively.
     */
    function toggleStatusOfPaymentAndAutoRenewCheckboxes(recordContributionCheckbox) {
      if (recordContributionCheckbox.prop('checked')) {
        $('#offline_auto_renew').prop('disabled', false);
      } else {
        $('#offline_auto_renew').prop('disabled', true);
      }
    }

    /**
     * Updates the view of the Contribution / Payment Plan form
     * depending on the selected tab
     *
     * @param {String} tabOptionId
     */
    function updateContributionPaymentPlanView (tabOptionId) {
      if (tabOptionId === 'contribution') {
        $('#installments_row').hide();
        $("label[for='receive_date']").html('Received');
        $('#trxn_id').parent().parent().show();
        $('#first_installment').hide();
      } else if (tabOptionId === 'payment_plan') {
        $('#installments_row').show();
        $("label[for='receive_date']").html('Payment Plan Start Date');
        $('#trxn_id').parent().parent().hide();
        $('#first_installment').show();
        $('#installments').change();

        if ($('#membership_type_id_1').val()) {
          $('#membership_type_id_1').change();
        }
      }
    }
  })(CRM.$);
  {/literal}
</script>

<div id="payment_plan_fields_tabs">
  <input name="contribution_type_toggle" type="hidden">
  <div class="ui-tabs">
    <ul class="ui-tabs-nav ui-helper-clearfix">
      <li class="crm-tab-button ui-corner-top ui-tabs-active" data-selector="contribution">
        <a href="#contribution-subtab">Contribution</a>
      </li>
      <li class="crm-tab-button ui-corner-top" data-selector="payment_plan">
        <a href="#payment_plan-subtab">Payment Plan</a>
      </li>
    </ul>
  </div>
</div>

<table id="payment_plan_fields">
  <tr id="installments_row">
    <td class="label" nowrap>
      {$form.installments.label}<span class="crm-marker">*</span>
    </td>
    <td nowrap>
      {$form.installments.html}
      &nbsp;
      {$form.installments_frequency.label} <span class="crm-marker">*</span>
      {$form.installments_frequency.html}
      {$form.installments_frequency_unit.html}
    </td>
  </tr>
  <tr id="first_installment">
    <td colspan="2">
      <fieldset>
        <legend>{ts}First Installment Summary{/ts}</legend>
        <div class="crm-section billing_mode-section pay-later_info-section">
          <div class="crm-section check_number-section">
            <div class="label">Invoice Date</div>
            <div class="content" id="invoice_date_summary"></div>
            <div class="clear"></div>
          </div>
        </div>
        <div class="crm-section billing_mode-section pay-later_info-section">
          <div class="crm-section check_number-section">
            <div class="label">Amount</div>
            <div class="content" id="amount_summary"></div>
            <div class="clear"></div>
          </div>
        </div>
      </fieldset>
    </td>
  </tr>
</table>
