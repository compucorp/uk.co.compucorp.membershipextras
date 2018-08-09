<script type="text/javascript">
  {literal}
  CRM.$(function () {
    CRM.$('#adjust_end_date').click(function() {
      console.log(CRM.$('#end_date_container').css('display'));
      console.log(CRM.$('#end_date_container'));
      if(this.checked) {
        CRM.$('#end_date_container').css('display', 'inline');
      } else {
        CRM.$('#end_date_container').css('display', 'none');
      }
      console.log(CRM.$('#end_date_container').css('display'));
      console.log(CRM.$('#end_date_container'));
    });

    CRM.$('#end_date_container').css('display', 'none');
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
  <div class="content">
    {$form.adjust_end_date.html}
    <div id="end_date_container">{$form.end_date.html}</div>
  </div>
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
