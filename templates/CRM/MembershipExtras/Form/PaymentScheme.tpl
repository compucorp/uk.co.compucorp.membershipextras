<div class="crm-block crm-form-block">
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
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
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
