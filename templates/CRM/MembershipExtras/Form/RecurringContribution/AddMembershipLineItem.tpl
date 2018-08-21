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
  {ts}Please note the changes should take effect immediately after "Apply".{/ts}
</p>
<div class="crm-section">
  <div>
    {$form.adjust_first_amount.label}
    {$form.adjust_first_amount.html}
  </div>
  <div id="amount_container">{$form.first_installment_amount.html}</div>
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
