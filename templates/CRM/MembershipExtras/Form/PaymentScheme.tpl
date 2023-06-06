<div class="crm-block crm-form-block">
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
    {if $action eq 8}
      <div class="messages status no-popup">
          {icon icon="fa-info-circle"}{/icon}
          {ts}WARNING: This will permanently delete the payment scheme, this action cannot be undone. Do you want to continue?{/ts}
      </div>
    {else}
      <table class="form-layout-compressed">
        <tbody>
        {foreach from=$elementNames item=elementName}
        <tr>
          <td class="label">
            <label>{$form.$elementName.label}</label>
          </td>
          <td>
              {$form.$elementName.html}
          </td>
        </tr>
        {/foreach}
        </tbody>
      </table>
    {/if}
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
