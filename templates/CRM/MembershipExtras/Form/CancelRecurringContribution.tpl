Are you sure you want to mark this recurring contribution as cancelled?
<br><br>
<strong>
  WARNING - This action sets the CiviCRM recurring contribution status to
  Cancelled, but does NOT send a cancellation request to the payment
  processor. You will need to ensure that this recurring payment
  (subscription) is cancelled by the payment processor.
</strong>
<br><br>
<div class="crm-section">
  <div class="label">{$form.cancel_pending_installments.label}</div>
  <div class="content">{$form.cancel_pending_installments.html}</div>
  <div class="clear"></div>
</div>
<div class="crm-section">
  <div class="label">{$form.cancel_memberships.label}</div>
  <div class="content">{$form.cancel_memberships.html}</div>
  <div class="clear"></div>
</div>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
