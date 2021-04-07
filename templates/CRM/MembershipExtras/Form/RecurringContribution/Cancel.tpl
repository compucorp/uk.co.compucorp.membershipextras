<div class="crm-block crm-form-block crm-payment-plan-cancel-form-block">
  <div class="messages status no-popup">
    <div class="crm-i fa-info-circle"></div>
    WARNING - This action sets the CiviCRM recurring contribution status to cancelled, but does NOT send a cancellation request to the payment processor. You will need to ensure that this recurring payment (subscription) is cancelled by the payment processor.
  </div>
  <p>
    <strong> Are you sure you want to mark this recurring contribution as cancelled? </strong>
  </p>
  <table class="form-layout-compressed">
    <tbody>
      <tr>
        <td class="label">
          <label>{$form.cancel_pending_installments.label}</label>
        </td>
        <td>
          {$form.cancel_pending_installments.html}
        </td>
      </tr>
      <tr>
        <td class="label">
          <label>{$form.cancel_memberships.label}</label>
        </td>
        <td>
          {$form.cancel_memberships.html}
        </td>
      </tr>      
    </tbody>
  </table>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
