<script>
var nextPeriodMembershipTypes = {$nextPeriodMembershipTypes|@json_encode};
</script>
<div class="right period-dates">
  Period Start Date: {$nextPeriodStartDate|date_format:"%Y-%m-%d"|crmDate}
</div>
<table class="selector row-highlight" id="nextPeriodLineItems">
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
  {assign var='installments' value=$recurringContribution.installments|intval}

  {foreach from=$nextPeriodLineItems item='currentItem'}
    {if ($installments || (!$installments && !$currentItem.end_date))}
      {assign var='subTotal' value=$subTotal+$currentItem.line_total}
      {assign var='taxTotal' value=$taxTotal+$currentItem.tax_amount}

      <tr id="lineitem-{$currentItem.id}" data-action="cancel"
          data-item-data='{$currentItem|@json_encode}'
          class="crm-entity rc-line-item {cycle values="odd-row,even-row"}">
        <td>{$currentItem.label}</td>
        <td>{$currentItem.financial_type}</td>
        <td>{if !empty($currentItem.tax_rate)}{$currentItem.tax_rate} %{else}N/A{/if}</td>
        <td>{$currentItem.line_total|crmMoney}</td>
        <td>
          <a class="remove-next-period-line-button">
            <span><i class="crm-i fa-trash"></i></span>
          </a>
        </td>
      </tr>
    {/if}
  {/foreach}
  {assign var='installmentTotal' value=$subTotal+$taxTotal}
  <tr id="addLineItemRow" style="display: none">
    <td>
      <input type="text" class="crm-form-text" id="item" />
    </td>
    <td>
      <select class="crm-form-select" name="financial_type_id" id="financialType">
        {foreach from=$financialTypes item='financialType'}
          <option value={$financialType.id}>{$financialType.name}</option>
        {/foreach}
      </select>
    </td>
    <td id="financialTypeTaxRate" nowrap>{if !empty($financialTypes[0].tax_rate)}{$financialTypes[0].tax_rate} %{else}N/A{/if}</td>
    <td>
      {$currencySymbol}&nbsp; <input type="text" class="four crm-form-text" size="4" id="amount" />
    </td>
    <td nowrap class="confirmation-icons">
      <a href="#" class="confirm-add-next-period-line-button">
        <span><i class="crm-i fa-check crm-i-green"></i></span>
      </a>
      <a href="#" class="cancel-add-next-period-line-button">
        <span><i class="crm-i fa-times crm-i-red"></i></span>
      </a>
    </td>
  </tr>
  <tr id="addMembershipRow" style="display: none">
    <td>
      <select name="newline_membership_type" class="crm-form-select" id="newMembershipItem">
        <option value="">- {ts}select{/ts} -</option>
        {foreach from=$nextPeriodMembershipTypes item="membership"}
          <option value="{$membership.id}">{$membership.name}</option>
        {/foreach}
      </select>
    </td>
    <td id="newMembershipFinancialType">{ts}select a membership type{/ts}</td>
    <td id="newMembershipFinTypeTaxRate">-</td>
    <td>
      {$currencySymbol}&nbsp; <input type="text" class="four crm-form-text" size="4" id="newMembershipAmount" />
    </td>
    <td nowrap class="confirmation-icons">
      <a href="#" class="confirm-add-next-period-membership-button">
        <span><i class="crm-i fa-check"></i></span>
      </a>
      <a href="#" class="cancel-add-next-period-membership-button">
        <span><i class="crm-i fa-times"></i></span>
      </a>
    </td>
  </tr>
  </tbody>
</table>
<div id="next_buttons">
  <a href="" class="button clickable" id="addMembership">
    <span><i class="crm-i fa-plus"></i>&nbsp; Add Membership</span>
  </a>
  <a href="" class="button clickable" id="addOtherAmount">
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
