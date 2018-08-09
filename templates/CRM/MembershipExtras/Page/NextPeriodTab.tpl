<div class="right">
  Period Start Date: {$nextPeriodStartDate|date_format}
</div>
<table class="selector row-highlight">
  <tbody>
  <tr class="columnheader">
    <th scope="col">{ts}Item{/ts}</th>
    <th scope="col">{ts}Financial Type{/ts}</th>
    <th scope="col">{ts}Tax{/ts}</th>
    <th scope="col">{ts}Amount{/ts}</th>
    <th scope="col">&nbsp;</th>
  </tr>
  {assign var='subTotal' value=0}
  {assign var='taxTotal' value=0}
  {assign var='installmentTotal' value=0}

  {foreach from=$nextPeriodLineItems item='currentItem'}
    {assign var='subTotal' value=$subTotal+$currentItem.line_total}
    {assign var='taxTotal' value=$taxTotal+$currentItem.tax_amount}

    <tr id="lineitem-{$currentItem.id}" data-action="cancel" class="crm-entity {cycle values="odd-row,even-row"}">
      <td>{$currentItem.label}</td>
      <td>{$currentItem.financial_type}</td>
      <td>{$currentItem.tax_rate}</td>
      <td>{$currentItem.line_total|crmMoney}</td>
      <td>
        <a class="delete" href="">
          <span><i class="crm-i fa-trash"></i></span>
        </a>
      </td>
    </tr>
  {/foreach}
  {assign var='installmentTotal' value=$subTotal+$taxTotal}
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
<div class="clear"></div>
<div class="right">
  <table class="form-layout-compressed" align="right">
    <tr>
      <td colspan="2">
        <hr/>
      </td>
    </tr>
    <tr>
      <td class="contriTotalLeft right">{ts}Untaxed Amount:{/ts}</td>
      <td>{$subTotal|crmMoney}</td>
    </tr>
    <tr>
      <td class="contriTotalLeft right">{ts}Tax:{/ts}</td>
      <td>{$taxTotal|crmMoney}</td>
    </tr>
    <tr>
      <td colspan="2">
        <hr/>
      </td>
    </tr>
    <tr>
      <td class="contriTotalLeft right">{ts}Total per Installment:{/ts}</td>
      <td>{$installmentTotal|crmMoney}</td>
    </tr>
  </table>
</div>
