<script>
  {literal}
  CRM.$(function () {
    var formHandler = new CRM.RecurringContribution.CurrentPeriodLineItemHandler(CRM.$('#recurringContributionID').val());
    formHandler.initializeForm(CRM.$('#current-subtab'));
    formHandler.addEventHandlers();
  });
  {/literal}
</script>
<div id="confirmLineItemDeletion" style="display: none;"></div>

<div class="right">
  Period Start Date: {$periodStartDate|date_format}
  &nbsp;&nbsp;&nbsp;
  Period End Date: {$periodEndDate|date_format}
</div>
<form>
  <input name="recurringContributionID" id="recurringContributionID" value="{$recurringContributionID}" type="hidden" />
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
    {assign var='subTotal' value=0}
    {assign var='taxTotal' value=0}
    {assign var='installmentTotal' value=0}

    {foreach from=$lineItems item='currentItem'}
      {assign var='subTotal' value=$subTotal+$currentItem.line_total}
      {assign var='taxTotal' value=$taxTotal+$currentItem.tax_amount}

      <tr id="lineitem-{$currentItem.id}" data-item-data='{$currentItem|@json_encode}' class="crm-entity rc-line-item {cycle values="odd-row,even-row"}">
        <td>{$currentItem.label}</td>
        <td>{$currentItem.start_date|date_format}</td>
        <td>{$currentItem.end_date|date_format}</td>
        <td><input type="checkbox" class="auto-renew-line-checkbox"{if $currentItem.auto_renew} checked{/if} /></td>
        <td>{$currentItem.financial_type}</td>
        <td>{if $currentItem.tax_rate == 0}N/A{else}{$currentItem.tax_rate}%{/if}</td>
        <td>{$currentItem.line_total|crmMoney}</td>
        <td>
          <a class="remove-line-button clickable" href="" data-itemid="{$currentItem.line_item_id}">
            <span><i class="crm-i fa-trash" title="Remove line item..."></i></span>
          </a>
        </td>
      </tr>
    {/foreach}
    {assign var='installmentTotal' value=$subTotal+$taxTotal}

    <tr id="new_membership_line_item" class="crm-entity rc-new-line-item {cycle values="odd-row,even-row"}">
      <td>
        <select name="newline_membership_type" class="crm-form-select" id="newline_membership_type">
          <option value="">- {ts}select{/ts} -</option>
          {foreach from=$membershipTypes item="membership"}
            <option value="{$membership.id}">{$membership.name}</option>
          {/foreach}
        </select>
      </td>
      <td nowrap>
        <input data-crm-datepicker="{ldelim}&quot;time&quot;:false{rdelim}" aria-label="Start Date" name="newline_start_date" type="text" value="{$currentDate}" id="newline_start_date" class="crm-form-text crm-hidden-date">
      </td>
      <td nowrap>
        <input data-crm-datepicker="{ldelim}&quot;time&quot;:false{rdelim}" aria-label="End Date" name="newline_end_date" type="text" value="{$largestMembershipEndDate}" id="newline_end_date" class="crm-form-text crm-hidden-date">
      </td>
      <td>
        {if $recurringContribution.auto_renew}
          <input name="newline_auto_renew" id="newline_auto_renew" type="checkbox" checked />
        {/if}&nbsp;
      </td>
      <td id="newline_financial_type"> - </td>
      <td id="newline_tax_rate" nowrap> - </td>
      <td><input name="newline_amount" id="newline_amount"/></td>
      <td nowrap>
        <a class="line-apply-btn" href="" id="apply_add_membership_btn">
          <span><i class="crm-i fa-check" title="Add line item..."></i>&nbsp;</span>
        </a>
        <a class="line-apply-btn" href="" id="cancel_add_membership_btn">
          <span><i class="crm-i fa-times" title="Cancel"></i></span>
        </a>
      </td>
    </tr>
    </tbody>
  </table>
</form>

<div>
  <a class="button clickable" href="" id="add_membership_btn">
    <span><i class="crm-i fa-plus"></i>&nbsp; Add Membership</span>
  </a>
  <a class="button clickable" href="">
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
