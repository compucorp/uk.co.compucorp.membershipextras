<div class="crm-block crm-form-block crm-payment-plan-cancel-form-block">
  <p>
    <strong>
      {if $isPaymentStarted}
        Would you like to activate membership period {$period->start_date|date_format} to
        {$period->end_date|date_format}?
      {else}
        Membership period {$period->start_date|date_format} to {$period->end_date|date_format} does not
        have any fulfilled payment. Would you still like to activate it?
      {/if}
    </strong>
  </p>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
