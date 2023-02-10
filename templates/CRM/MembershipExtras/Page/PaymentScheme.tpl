<div class="crm-content-block crm-block">
    {if $rows}
      <div class="action-link">
          {crmButton p="civicrm/member/admin/payment-scheme" q='action=add&reset=1' class="new-option" icon="plus-circle"}{ts}Add Payment Scheme{/ts}{/crmButton}
          {crmButton p="civicrm/admin" q="reset=1" class="cancel" icon="times"}{ts}Done{/ts}{/crmButton}
      </div>
      {strip}
        <table cellpadding="0" cellspacing="0" border="0"  class="selector row-highlight">
          <tr class="columnheader">
            <th></th>
            <th>{ts}Name{/ts}</th>
            <th>{ts}Admin Title{/ts}</th>
            <th>{ts}Public Title{/ts}</th>
            <th>{ts}Admin Description{/ts}</th>
            <th>{ts}Public Description{/ts}</th>
            <th>{ts}Permission{/ts}</th>
            <th>{ts}Enabled?{/ts}</th>
            <th></th>
          </tr>
            {foreach from=$rows item=row}
              <tr id="scheme-{$row.id}" class="crm-entity {cycle values="odd-row,even-row"}{if !empty($row.class)} {$row.class}{/if}{if NOT $row.enabled} disabled{/if}">
                <td></td>
                <td class="crm-admin-member-payment-scheme-name">{$row.name}</td>
                <td class="crm-admin-member-payment-scheme-admin-title">{$row.admin_title}</td>
                <td class="crm-admin-member-payment-scheme-public-title">{$row.public_title}</td>
                <td class="crm-admin-member-payment-scheme-admin-description">{$row.admin_description}</td>
                <td class="crm-admin-member-payment-scheme-public-description">{$row.public_description}</td>
                <td class="crm-admin-member-payment-scheme-permission">{$row.permission}</td>
                <td class="crm-admin-member-payment-scheme-permission-enabled" id="row_{$row.id}_status">{if $row.enabled eq 1} {ts}Yes{/ts} {else} {ts}No{/ts} {/if}</td>
                <td>
                  <a href="{crmURL p="civicrm/member/admin/payment-scheme" q="id=`$row.id`&action=update&reset=1"}" class="action-item crm-hover-button" title="{ts}View and Edit Payment Scheme{/ts}">{ts}Edit{/ts}</a>
                  <a href="{crmURL p="civicrm/member/admin/payment-scheme" q="id=`$row.id`&action=delete&reset=1"}" class="action-item crm-hover-button small-popup" title="Delete Paymet Scheme">Delete</a>
                </td>
              </tr>
            {/foreach}
        </table>
      {/strip}

    {else}
      <div class="messages status no-popup">
        <img src="{$config->resourceBase}i/Inform.gif" alt="{ts}status{/ts}"/>{ts}No payment schemes found.{/ts}
      </div>
      <div class="action-link">
          {crmButton p="civicrm/member/admin/payment-scheme" q='action=add&reset=1' class="new-option" icon="plus-circle"}{ts}Add Payment Scheme{/ts}{/crmButton}
          {crmButton p="civicrm/admin" q="reset=1" class="cancel" icon="times"}{ts}Done{/ts}{/crmButton}
      </div>
   {/if}

</div>
