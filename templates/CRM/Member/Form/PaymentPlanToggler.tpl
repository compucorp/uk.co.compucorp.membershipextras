<script type="text/javascript">
  {literal}
  CRM.$(function ($) {
    {/literal}
    const togglerValue = '{$contribution_type_toggle}';
    const currencySymbol = '{$currency_symbol}';
    paymentPlanToggler(togglerValue, currencySymbol);
    {literal}
  });
  {/literal}
</script>
<div id="payment_plan_fields_tabs">
  <input name="contribution_type_toggle" type="hidden">
  <div class="ui-tabs">
    <ul class="ui-tabs-nav ui-helper-clearfix">
      <li class="crm-tab-button ui-corner-top ui-tabs-active" data-selector="contribution">
        <a href="#contribution-subtab">{ts}Contribution{/ts}</a>
      </li>
      <li class="crm-tab-button ui-corner-top" data-selector="payment_plan">
        <a href="#payment_plan-subtab">{ts}Payment Plan{/ts}</a>
      </li>
    </ul>
  </div>
</div>
<table id="payment_plan_fields">
  <tr id="payment_plan_schedule_row">
    <td class="label" nowrap>
      {$form.payment_plan_schedule.label}
    </td>
    <td nowrap>
      {$form.payment_plan_schedule.html}
    </td>
  </tr>
  <tr id="payment_plan_schedule_instalment_row">
    <td class="label" nowrap><label>{ts}Instalment Schedule{/ts}</label></td>
    <td>
      <div id="instalment_schedule_table"> </div>
    </td>
  </tr>
</table>
