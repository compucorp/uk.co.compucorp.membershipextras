<p class="help">
  {ts}As there are no future instalments in this period, you can create a single one off future payment.{/ts}
</p>

<div class="crm-section">
  <table id="payment_details_form_container" class="form-layout-compressed">
    <tbody>
    <tr>
      <td>{$form.noinstalmentline_send_confirmation_email.label}</td>
      <td>{$form.noinstalmentline_send_confirmation_email.html}</td>
    </tr>
    </tbody>
  </table>
</div>

<div id="AddNoInstalmentsDonationLineItemFormButtons" class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
