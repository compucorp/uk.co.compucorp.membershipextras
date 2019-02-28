<script type="text/javascript">
  var togglerValue = '{$contribution_type_toggle}';
  var membershipextras_allMembershipData = {$allMembershipInfo};
  var membershipextras_taxRatesStr = '{$taxRates}';
  var membershipextras_taxTerm = '{$taxTerm}';
  var membershipextras_currency = '{$currency}';
  var membershipextras_taxRates = [];

  {literal}

  if (membershipextras_taxRatesStr != '') {
    membershipextras_taxRates = JSON.parse(membershipextras_taxRatesStr);
  }

  /**
   * Perform changes on form to add payment plan as an option to pay for
   * membership.
   */
  CRM.$(function($) {
    moveMembershipFormFields();
    setMembershipFormEvents();
    initializeMembershipForm();
  });

  /**
   * Reorders fields within the form to follow a coherent logic with the new
   * payment plan fields.
   */
  function moveMembershipFormFields() {
    // Move fields
    CRM.$('#contributionTypeToggle').insertBefore(CRM.$('#is_different_contribution_contact').parent().parent());
    CRM.$('#recordContribution legend:first').html('Contribution and Payment Plan');
    CRM.$('#installments_row').insertAfter(CRM.$('#financial_type_id').parent().parent());
    CRM.$('#first_installment').insertAfter(CRM.$('#installments_row'));
    CRM.$('span.crm-error').css('display', 'none');
    CRM.$('label span.crm-error').css('display', 'inline');
  }

  /**
   * Creates events that modify the behaviour of the form:
   */
  function setMembershipFormEvents() {
    setupPayPlanTogglingEvents();
    setInvoiceSummaryEvents();
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
    CRM.$('#contribution_toggle').click(function() {
      CRM.$('#installments_row').hide();
      CRM.$("label[for='receive_date']").html('Received');
      CRM.$('#trxn_id').parent().parent().show();
      CRM.$('#first_installment').hide();
    });

    CRM.$('#payment_plan_toggle').click(function() {
      CRM.$('#installments_row').show();
      CRM.$("label[for='receive_date']").html('Payment Plan Start Date');
      CRM.$('#trxn_id').parent().parent().hide();
      CRM.$('#first_installment').show();
      CRM.$('#installments').change();

      if (CRM.$('#membership_type_id_1').val()) {
        CRM.$('#membership_type_id_1').change();
      }
    });
  }

  /**
   * Adds events so that any changes done to the form are reflected on first
   * invoice summary:
   *
   * - Changes date in invoice summary when recieve date is changed by user.
   * - Recalculates amount in invoice summary if installments, frequency or
   *   frequecy unit is changed.
   */
  function setInvoiceSummaryEvents() {
    CRM.$('tr.crm-membership-form-block-receive_date td input').change(function () {
      if (CRM.$(this).attr('name').indexOf('receive_date_display_') >= 0) {
        CRM.$('#invoice_date_summary').html(CRM.$(this).val());
      }
    });

    CRM.$('#installments, #total_amount, #membership_type_id_1').change(function () {
      var currentAmount = parseFloat(CRM.$('#total_amount').val().replace(/[^0-9\.]+/g, ""));
      var amountPerPeriod = currentAmount / parseFloat(CRM.$('#installments').val());
      var memType = parseInt(CRM.$('#membership_type_id_1').val());
      var taxMessage = '';
      var taxPerPeriodMessage = '';

      // Check if a price set is being used
      var isPriceSet = cj('#price_set_id').length > 0 && cj('#price_set_id').val();
      if (!isPriceSet) {
        var currentMembershipData = membershipextras_allMembershipData[memType];
        var taxRate = membershipextras_taxRates[currentMembershipData['financial_type_id']];

        if (taxRate != undefined) {
          var taxAmount = (currentAmount * (taxRate / 100)) / (1 + (taxRate / 100));
          taxAmount = isNaN (taxAmount) ? 0 : taxAmount.toFixed(2);
          var taxPerPeriod = (taxAmount / parseFloat(CRM.$('#installments').val())).toFixed(2);
          taxMessage = 'Includes ' + membershipextras_taxTerm + ' amount of ' + membershipextras_currency + ' ' + taxAmount;
          taxPerPeriodMessage = 'Includes ' + membershipextras_taxTerm + ' amount of ' + membershipextras_currency + ' ' + taxPerPeriod;
        }
      }

      CRM.$('.totaltaxAmount').html(taxMessage);
      CRM.$('#amount_summary').html(membershipextras_currency + ' ' + amountPerPeriod.toFixed(2) + '<br/>' + taxPerPeriodMessage);
    });
  }

  /**
   * Leaves form in a consistent state after the fields have been moved and the
   * new events added.
   */
  function initializeMembershipForm() {
    var idToggleOption = '#' + togglerValue + '_toggle';
    CRM.$(idToggleOption).click();
    CRM.$('#invoice_date_summary').html(CRM.$('#receive_date').val());
  }

  if (typeof buildAutoRenew !== 'function') {
    function buildAutoRenew() {};
  }

  {/literal}
</script>
<table id="payment_plan_fields">
  <tr id="contributionTypeToggle">
    <td colspan="2">
      <p>
        <input name="contribution_type_toggle" id="contribution_toggle" value="contribution" type="radio">
        <label for="contribution_toggle">Contribution</label>
        &nbsp;
        <input name="contribution_type_toggle" id="payment_plan_toggle" value="payment_plan" type="radio">
        <label for="payment_plan_toggle">Payment Plan</label>
      </p>
    </td>
  </tr>
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
