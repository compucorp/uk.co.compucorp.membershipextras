<div class="crm-block crm-form-block crm-payment-plan-cancel-form-block">
  <p>
    <strong>
      {if $isPaymentStarted}
        Membership period {$period->start_date|crmDate} to {$period->end_date|crmDate}
        has a payment in progress or is already paid. Would you still like to
        deactivate it?
      {else}
        Would you like to deactivate membership period {$period->start_date|crmDate}
        to {$period->end_date|crmDate}?
      {/if}
    </strong>
  </p>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>