<script type="text/javascript">
  {literal}
  CRM.$(function($) {
    // Move fields
    CRM.$('#contributionTypeToggle').insertBefore(CRM.$('#is_different_contribution_contact').parent().parent());
    CRM.$('#recordContribution legend').html('Contribution and Payment Plan');
    CRM.$('#installments_row').insertAfter(CRM.$('#financial_type_id').parent().parent());
    CRM.$('#first_installment').insertAfter(CRM.$('#contribution_status_id').parent().parent());

    // Set events
    CRM.$('#contribution_toggle').click(function() {
      CRM.$('#total_amount').prop('readonly', false);
      CRM.$('#installments_row').hide();
      CRM.$("label[for='receive_date']").html('Received');
      CRM.$('#trxn_id').parent().parent().show();
      CRM.$('#first_installment').hide();
    });
    CRM.$('#plan_toggle').click(function() {
      CRM.$('#total_amount').prop('readonly', true);
      CRM.$('#installments_row').show();
      CRM.$("label[for='receive_date']").html('Payment Plan Start Date');
      CRM.$('#trxn_id').parent().parent().hide();
      CRM.$('#first_installment').show();

      if (CRM.$('#membership_type_id_1').val()) {
        CRM.$('#membership_type_id_1').change();
      }
    });
    CRM.$('tr.crm-membership-form-block-receive_date td input').change(function () {
      if (CRM.$(this).attr('name').indexOf('receive_date_display_') >= 0) {
        CRM.$('#invoice_date_summary').html(CRM.$(this).val());
      }
    });

    CRM.$('#installments, #installments_frequency, #installments_frequency_unit').change(function () {
      var currentAmount = parseFloat(CRM.$('#total_amount').val().replace(/[^0-9\.]+/g, ""));
      var amountPerPeriod = currentAmount / parseFloat(CRM.$('#installments').val());

      CRM.$('#amount_summary').html(amountPerPeriod.toFixed(2));
    });

    // Initialize
    CRM.$('#contribution_toggle').click();
    CRM.$('#invoice_date_summary').html(CRM.$('#receive_date').val());
  });

  {/literal}
</script>
<table id="payment_plan_fields">
  <tr id="contributionTypeToggle">
    <td colspan="2" align="center">
      <input name="contribution_type_toggle" id="contribution_toggle" value="contribution" type="radio">
      <label for="contribution_toggle">Contribution</label>
      &nbsp;
      <input name="contribution_type_toggle" id="plan_toggle" value="payment_plan" type="radio">
      <label for="plan_toggle">Payment Plan</label>
    </td>
  </tr>
  <tr id="installments_row">
    <td nowrap>{$form.installments.label}</td>
    <td>
      {$form.installments.html}
      {$form.installments_frequency.label}
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
