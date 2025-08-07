<div class="crm-block crm-form-block crm-payment-plan-cancel-form-block">
  <div class="messages status no-popup">
   {if $isOfflinePaymentProcessor}
    <div class="crm-i fa-info-circle"></div>
    WARNING - This action sets the CiviCRM recurring contribution status to cancelled, but does NOT send a cancellation request to the payment processor. You will need to ensure that this recurring payment (subscription) is cancelled by the payment processor.
  </div>
   {else}
  <div class="crm-i fa-info-circle"></div>
  WARNING: Cancelling the payment plan will also cancel any future payments that have not yet been submitted from being taken by the payment processor. Note, that any payments which have already been submitted will continue to process.
  </div>
   {/if}
  <p>
    <strong> Are you sure you want to mark this recurring contribution as cancelled? </strong>
  </p>
  <input type="hidden" name="force_cancellation" value="1">
  {if $isMembershipextrasPaymentPlan}
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
  {/if}
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
