<div id="periodsContainer" class="ui-tabs ui-widget ui-widget-content ui-corner-all">
  {* Tab management *}
  <script type="text/javascript">
    var selectedTab  = 'contributions';
    {literal}
    CRM.$(function($) {
      var tabIndex = $('#tab_' + selectedTab).prevAll().length;
      $("#periodsContainer").tabs({active: tabIndex});
      $(".crm-tab-button").addClass("ui-corner-bottom");
    });
    {/literal}
  </script>
  <ul class="ui-tabs-nav ui-corner-all ui-helper-reset ui-helper-clearfix ui-widget-header">
    <li id="tab_current" class="crm-tab-button ui-corner-all ui-tabs-tab ui-corner-top ui-state-default ui-tab ui-tabs-active ui-state-active">
      <a href="#current-subtab" title="{ts}Contributions{/ts}">
        {ts}Current Period{/ts}
      </a>
    </li>
    <li id="tab_next" class="crm-tab-button ui-corner-all ui-tabs-tab ui-corner-top ui-state-default ui-tab">
      <a href="#next-subtab" title="{ts}Recurring Contributions{/ts}">
        {ts}Next Period{/ts}
      </a>
    </li>
  </ul>

  <div id="current-subtab" class="ui-tabs-panel ui-widget-content ui-corner-bottom">
    <div class="right">
      Period Start Date: {$periodStartDate|date_format}
      &nbsp;&nbsp;&nbsp;
      Period End Date: {$periodEndDate|date_format}
    </div>
    <table class="selector row-highlight">
      <tbody>
      <tr class="columnheader">
        <th scope="col">{ts}Item{/ts}</th>
        <th scope="col">{ts}Start Date{/ts}</th>
        <th scope="col">{ts}End Date{/ts}</th>
        <th scope="col">{ts}Renew Automatically{/ts}</th>
        <th scope="col">{ts}Financial Type{/ts}</th>
        <th scope="col">{ts}Tax{/ts}</th>
        <th scope="col">{ts}Amount{/ts}</th>
        <th scope="col">&nbsp;</th>
      </tr>
      {foreach from=$lineItems item='currentItem'}
        <tr id="lineitem-{$currentItem.id}" data-action="cancel" class="crm-entity {cycle values="odd-row,even-row"}">
          <td>{$currentItem.label}</td>
          <td>{$currentItem.start_date|date_format}</td>
          <td>{$currentItem.end_date|date_format}</td>
          <td>{$currentItem.auto_renew}</td>
          <td>{$currentItem.financial_type}</td>
          <td>{$currentItem.tax_amount|crmMoney}</td>
          <td>{$currentItem.line_total|crmMoney}</td>
          <td>
            <a class="delete" href="">
              <span><i class="crm-i fa-trash"></i></span>
            </a>
          </td>
        </tr>
      {/foreach}
      </tbody>
    </table>
    <div id="current_buttons">
      <a class="button" href="">
        <span><i class="crm-i fa-plus"></i>&nbsp; Add Membership</span>
      </a>
      <a class="button" href="">
        <span><i class="crm-i fa-plus"></i>&nbsp; Add Other Amount</span>
      </a>
    </div>
  </div>
  <div id="next-subtab" class="ui-tabs-panel ui-widget-content ui-corner-bottom">
    [ NEXT PERIOD ]
  </div>
  <div class="clear"></div>
</div>