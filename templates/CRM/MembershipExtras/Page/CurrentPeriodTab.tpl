<script>
  var recurringContributionID = {$recurringContributionID};
  var financialTypes = {$financialTypes|@json_encode};
  var recurringContribution = {$recurringContribution|@json_encode};

  {literal}
  CRM.$(function () {
    var formHandler = new CRM.RecurringContribution.CurrentPeriodLineItemHandler(CRM.$('#recurringContributionID').val());
    formHandler.initializeForm(CRM.$('#current-subtab'));
    formHandler.set('financialTypes', financialTypes);
    formHandler.addEventHandlers();
  });
  {/literal}
</script>
<div id="confirmLineItemDeletion" style="display: none;"></div>

<div class="right period-dates">
  Period Start Date: {$periodStartDate|date_format:"%Y-%m-%d"|crmDate}
  &nbsp;&nbsp;&nbsp;
  Period End Date: {$periodEndDate|date_format:"%Y-%m-%d"|crmDate}
</div>
<span id="current_period_end_date" style="display: none;">{$periodEndDate}</span>
<form>
  <input name="recurringContributionID" id="recurringContributionID" value="{$recurringContributionID}" type="hidden" />
  <table class="selector row-highlight" id="currentPeriodLineItems">
    <tbody>
    <tr class="columnheader">
      <th scope="col">{ts}Item{/ts}</th>
      <th scope="col">{ts}Start Date{/ts}</th>
      <th scope="col">{ts}End Date{/ts}</th>
      {if $recurringContribution.auto_renew}
        <th scope="col">{ts}Renew Automatically{/ts}</th>
      {/if}
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
        <td>
            {$currentItem.start_date|date_format:"%Y-%m-%d"|crmDate}
        </td>
        <td>
          {if $currentItem.related_membership.related_membership_type.duration_unit == 'lifetime'}
            -
          {elseif $currentItem.end_date}
            {$currentItem.end_date|date_format:"%Y-%m-%d"|crmDate}
          {elseif $currentItem.related_membership.end_date}
              {$currentItem.related_membership.end_date|date_format:"%Y-%m-%d"|crmDate}
          {else}
              {$periodEndDate|date_format:"%Y-%m-%d"|crmDate}
          {/if}
        </td>
        {if $recurringContribution.auto_renew}
          <td>
              <input type="checkbox" class="auto-renew-line-checkbox"{if $currentItem.auto_renew} checked{/if} />
          </td>
        {/if}
        <td>{$currentItem.financial_type}</td>
        <td>{if $currentItem.tax_rate == 0}N/A{else}{$currentItem.tax_rate}%{/if}</td>
        <td nowrap>{$currentItem.line_total|crmMoney}</td>
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
          {foreach from=$currentPeriodMembershipTypes item="membership"}
            <option value="{$membership.id}">{$membership.name}</option>
          {/foreach}
        </select>
      </td>
      <td nowrap>
        <input data-crm-datepicker="{ldelim}&quot;time&quot;:false, &quot;allowClear&quot;:false{rdelim}" aria-label="Start Date" name="newline_start_date" type="text" value="{$currentDate}" id="newline_start_date" class="crm-form-text crm-hidden-date">
      </td>
      <td nowrap>
        <input data-crm-datepicker="{ldelim}&quot;time&quot;:false, &quot;allowClear&quot;:false{rdelim}" aria-label="End Date" name="newline_end_date" type="text" value="{$largestMembershipEndDate}" id="newline_end_date" class="crm-form-text crm-hidden-date">
      </td>
      {if $recurringContribution.auto_renew}
        <td>
            <input name="newline_auto_renew" id="newline_auto_renew" type="checkbox" checked />&nbsp;
        </td>
      {/if}
      <td id="newline_financial_type"> - </td>
      <td id="newline_tax_rate" nowrap> - </td>
      <td><input name="newline_amount" id="newline_amount" class="crm-form-text"/></td>
      <td nowrap class="confirmation-icons">
        <a class="line-apply-btn" href="" id="apply_add_membership_btn">
          <span><i class="crm-i fa-check" title="Add line item..."></i>&nbsp;</span>
        </a>
        <a class="line-apply-btn" href="" id="cancel_add_membership_btn">
          <span><i class="crm-i fa-times" title="Cancel"></i></span>
        </a>
      </td>
    </tr>
    <tr id="new_donation_line_item" class="crm-entity rc-new-line-item {cycle values="odd-row,even-row"}">
      <td>
        <input name="newline_donation_item" id="newline_donation_item" class="crm-form-text"/>
      </td>
      <td nowrap>
        <input data-crm-datepicker="{ldelim}&quot;time&quot;:false, &quot;allowClear&quot;:false{rdelim}" aria-label="Start Date" name="newline_donation_start_date" type="text" value="{$currentDate}" id="newline_donation_start_date" class="crm-form-text crm-hidden-date">
      </td>
      <td nowrap>
        N/A
      </td>
      {if $recurringContribution.auto_renew}
        <td>
            <input name="newline_donation_auto_renew" id="newline_donation_auto_renew" type="checkbox" checked />&nbsp;
        </td>
      {/if}
      <td>
        <select class="crm-form-select" name="newline_donation_financial_type_id" id="newline_donation_financial_type_id">
          <option value="">- {ts}select{/ts} -</option>
          {foreach from=$financialTypes item='financialType'}
            <option value={$financialType.id}>{$financialType.name}</option>
          {/foreach}
        </select>
      </td>
      <td id="newline_donation_tax_rate" nowrap> N/A </td>
      <td><input name="newline_donation_amount" id="newline_donation_amount" class="crm-form-text"/></td>
      <td nowrap class="confirmation-icons">
        <a class="line-apply-btn" href="" id="apply_add_donation_btn">
          <span><i class="crm-i fa-check" title="Add line item..."></i>&nbsp;</span>
        </a>
        <a class="line-apply-btn" href="" id="cancel_add_donation_btn">
          <span><i class="crm-i fa-times" title="Cancel"></i></span>
        </a>
      </td>
    </tr>
    </tbody>
  </table>
</form>

<div id="current_buttons">
  <a class="button clickable" href="" id="add_membership_btn">
    <span><i class="crm-i fa-plus"></i>&nbsp; Add Membership</span>
  </a>
  <a class="button clickable" href="" id="add_other_btn">
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
      <td class="contriTotalLeft right">{ts}Total per Instalment:{/ts}</td>
      <td>{$installmentTotal|crmMoney}</td>
    </tr>
  </table>
</div>
