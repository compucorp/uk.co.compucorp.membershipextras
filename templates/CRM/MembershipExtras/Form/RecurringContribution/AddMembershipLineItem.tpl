<script type="text/javascript">
  {literal}
  CRM.$(function () {
    CRM.$('#adjust_first_amount').click(function() {
      if(this.checked) {
        CRM.$('#amount_container').css('display', 'inline');
      } else {
        CRM.$('#amount_container').css('display', 'none');
      }
    });

    CRM.$('#amount_container').css('display', 'none');
  });
  {/literal}
</script>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

<p>
  {ts}Please note the new line will be added to all pending instalments starting from {$newLineItem.start_date|crmDate} immediately after clicking "Apply".{/ts}
</p>
<div class="crm-section">
  <div>
    {$form.adjust_first_amount.label}
    {$form.adjust_first_amount.html}
  </div>
  <div id="amount_container">{$form.first_installment_amount.html}
    {if $daysRemainingUntilNextCycleDate}
      <div class="description">Pro-rated for the {$daysRemainingUntilNextCycleDate} days until the next billing cycle </div>
    {/if}
  </div>

</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
