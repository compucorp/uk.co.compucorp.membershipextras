<script type="text/javascript">
  {literal}
  CRM.$(function () {
    CRM.$('#adjust_end_date').click(function() {
      if(this.checked) {
        CRM.$('#end_date').prop('disabled', false);
      } else {
        CRM.$('#end_date').prop('disabled', true);
      }
    });
    CRM.$('#end_date').prop('disabled', true);
  });
  {/literal}
</script>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

<p>
  <i>{$lineItem.label}</i> amount should be deducted from all remaining instalments after
  the end date. Please note the changes should take effect immediately after
  "Apply".
</p>
<div class="crm-section">
  <div class="label">{$form.adjust_end_date.label}</div>
  <div class="content">{$form.adjust_end_date.html}</div>
  <div class="clear"></div>
  <div class="label">{$form.end_date.label}</div>
  <div class="content">{$form.end_date.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
