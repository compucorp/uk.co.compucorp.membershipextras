<div class="crm-block crm-form-block crm-payment-plan-cancel-form-block">
  <p>
    <strong>
      {if $isPaymentStarted}
        Membership period {$period->start_date|date_format} to {$period->end_date|date_format}
        has a payment in progress or is already paid. Would you still like to
        deactivate it?
      {else}
        Would you like to deactivate membership period {$period->start_date|date_format}
        to {$period->end_date|date_format}?
      {/if}
    </strong>
  </p>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
