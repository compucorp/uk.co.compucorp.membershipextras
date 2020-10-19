<div class="action-link">
    {crmButton p='civicrm/admin/member/automated-upgrade-rules/add?rest=1' icon="plus-circle"}{ts}Add Rule{/ts}{/crmButton}
</div>

{if $rows}
  <div class="crm-content-block crm-block">
      {strip}
        {include file="CRM/common/enableDisableApi.tpl"}
        <table id="options" class="row-highlight">
          <thead>
          <tr>
            <th>{ts}Label{/ts}</th>
            <th>{ts}From Membership{/ts}</th>
            <th>{ts}Held For{/ts}</th>
            <th>{ts}Basis{/ts}</th>
            <th>{ts}To Membership{/ts}</th>
            <th>{ts}Filter Group{/ts}</th>
            <th>{ts}Order{/ts}</th>
            <th>{ts}Enabled?{/ts}</th>
            <th></th>
          </tr>
          </thead>
            {foreach from=$rows item=row}
              <tr id="auto_membership_upgrade_rule-{$row.id}" class="crm-entity {cycle values='odd-row,even-row'} {$row.class} crm-auto-membership-upgrade-rule {if NOT $row.is_active} disabled{/if}">
                <td>{$row.label}</td>
                <td>{$row.from_membership_label}</td>
                <td>{$row.held_for}</td>
                <td>{$row.basis}</td>
                <td>{$row.to_membership_label}</td>
                <td>{$row.filter_group_label}</td>
                <td class="nowrap crmf-weight">{$row.weight}</td>
                <td class="crmf-is_active">{if $row.is_active eq 1} {ts}Yes{/ts} {else} {ts}No{/ts} {/if}</td>
                <td>{$row.action|replace:'xx':$row.id}</td>
              </tr>
            {/foreach}
        </table>
      {/strip}
  </div>
{else}
  <div class="messages status no-popup">
    <img src="{$config->resourceBase}i/Inform.gif" alt="{ts}status{/ts}"/>
      {capture assign=crmURL}{crmURL p='civicrm/admin/member/automated-upgrade-rules/add?rest=1'}{/capture}{ts 1=$crmURL}There are no any membership automated upgrade rule configured. You can add one <a href='%1'>from here</a>.{/ts}
  </div>
{/if}
