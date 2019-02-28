<script type="text/javascript">
  {literal}
  CRM.$(function () {
    CRM.$('#adjust_end_date').click(function() {
      if(this.checked) {
        CRM.$('#end_date_container').css('display', 'inline');
      } else {
        CRM.$('#end_date_container').css('display', 'none');
      }
    });

    CRM.$('#end_date_container').css('display', 'none');
  });
  {/literal}
</script>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

<p>
  {ts}
    If you want the <i>{$lineItem.label}</i> to end earlier than the current
    end date, you can adjust the end date below. <i>{$lineItem.label}</i> amount
    will be deducted from all remaining instalments after the new end date.
    Please note the changes should take effect immediately after clicking
    "Apply".
  {/ts}
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
