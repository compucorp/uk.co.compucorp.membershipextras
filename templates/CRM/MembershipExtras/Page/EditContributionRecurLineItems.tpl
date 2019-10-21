<div id="periodsContainer" class="ui-tabs ui-widget ui-widget-content ui-corner-all">
  <script type="text/javascript">
    {literal}
    var selectedTab = CRM.$('#periodsContainer').closest('.ui-dialog-content').data('selectedTab') || 'current';
    CRM.$(function($) {
      $('#tab_current').click(function () {
        window.CompucorpMembershipExtras_selectedTab = 'current';
      });

      $('#tab_next').click(function () {
        window.CompucorpMembershipExtras_selectedTab = 'next';
      });

      var tabIndex = $('#tab_' + selectedTab).prevAll().length;
      $("#periodsContainer").tabs({active: tabIndex});
      $(".crm-tab-button").addClass("ui-corner-bottom");
    });
    {/literal}
  </script>

  <ul class="ui-tabs-nav ui-corner-all ui-helper-reset ui-helper-clearfix ui-widget-header">
    <li id="tab_current" class="crm-tab-button ui-corner-all ui-tabs-tab ui-corner-top ui-state-default ui-tab">
      <a href="#current-subtab" title="{ts}Contributions{/ts}" class="clickable">
        {ts}Current Period{/ts}
      </a>
    </li>
    {if $showNextPeriodTab}
    <li id="tab_next" class="crm-tab-button ui-corner-all ui-tabs-tab ui-corner-top ui-state-default ui-tab">
      <a href="#next-subtab" title="{ts}Recurring Contributions{/ts}" class="clickable">
        {ts}Next Period (Forecast){/ts}
      </a>
    </li>
    {/if}
  </ul>

  <div id="current-subtab" class="ui-tabs-panel ui-widget-content ui-corner-bottom">
    {include file="CRM/MembershipExtras/Page/CurrentPeriodTab.tpl"}
  </div>
  {if $showNextPeriodTab}
  <div id="next-subtab" class="ui-tabs-panel ui-widget-content ui-corner-bottom">
    {include file="CRM/MembershipExtras/Page/NextPeriodTab.tpl"}
  </div>
  {/if}
  <div class="clear"></div>
</div>
