<script type="text/javascript">
  {literal}
  CRM.$(function () {
    CRM.$('form').submit(function() {
      CRM.$(".ui-dialog-buttonset button, .crm-submit-buttons button").prop('disabled',true);
    });

    CRM.$('#payment_details_form_container').css('display', 'none');

    var amountExcTax = CRM.$('input[name=amount_exc_tax]').val();
    var financialTypeId = CRM.$('select[name=noinstalmentline_financial_type_id]').val();
    updateAmountIncTaxField(amountExcTax, financialTypeId);

    CRM.$('input[type=radio][name=payment_type]').change(function() {
      if (this.value == 1) {
        CRM.$('#payment_details_form_container').css('display', 'none');
      }
      else if (this.value == 2) {
        CRM.$('#payment_details_form_container').css('display', 'inline');
      }
    });

    CRM.$('input[name=amount_exc_tax]').keyup(function() {
      amountExcTax = CRM.$(this).val();
      financialTypeId = CRM.$('select[name=noinstalmentline_financial_type_id]').val();

      updateAmountIncTaxField(amountExcTax, financialTypeId);
    });

    CRM.$('select[name=noinstalmentline_financial_type_id]').on('change', function() {
      financialTypeId = CRM.$(this).val();
      amountExcTax = CRM.$('input[name=amount_exc_tax]').val();

      updateAmountIncTaxField(amountExcTax, financialTypeId);
    });

    function updateAmountIncTaxField(amountExcTax, financialTypeId) {
      CRM.$('button[data-identifier="_qf_AddNoInstalmentsMembershipLineItem_submit"]').prop('disabled',true);

      CRM.api3('ContributionRecurLineItem', 'calculatetaxamount', {
        'amount_exc_tax': amountExcTax,
        'financial_type_id': financialTypeId
      }).done(function (response) {
        CRM.$('input[name=amount_inc_tax]').val(response.total_amount);
        CRM.$('button[data-identifier="_qf_AddNoInstalmentsMembershipLineItem_submit"]').prop('disabled',false);
      });
    }
  });
  {/literal}
</script>

<p class="help">
  {ts}As there are no future instalments in this period, you can decide whether to add this line item for no charge or to create a single one off future payment.{/ts}
</p>

<div class="crm-section">
  <div>
      {$form.payment_type.label}
      {$form.payment_type.html}
  </div>
  <br>

  <table id="payment_details_form_container" class="form-layout-compressed">
    <tbody>
    <tr>
      <td>{$form.scheduled_charge_date.label}</td>
      <td>{$form.scheduled_charge_date.html}</td>
    </tr>
    <tr>
      <td>{$form.amount_exc_tax.label}</td>
      <td>{$form.amount_exc_tax.html}</td>
    </tr>
    {if $prorataDaysCount}
      <tr>
        <td></td>
        <td><span class="description">Pro-rated for {$prorataDaysCount} day(s)</span></td>
      </tr>
    {/if}
    <tr>
      <td>{$form.noinstalmentline_financial_type_id.label}</td>
      <td>{$form.noinstalmentline_financial_type_id.html}</td>
    </tr>
    <tr>
      <td>{$form.amount_inc_tax.label}</td>
      <td>{$form.amount_inc_tax.html}</td>
    </tr>
    <tr>
      <td>{$form.noinstalmentline_send_confirmation_email.label}</td>
      <td>{$form.noinstalmentline_send_confirmation_email.html}</td>
    </tr>
    </tbody>
  </table>
</div>

<div id="AddNoInstalmentsMembershipLineItemFormButtons" class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
