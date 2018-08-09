<script>
  var recurringContributionID = {$recurringContributionID};

  {literal}
  CRM.$(function () {
    CRM.$('.remove-line-button').each(function () {
      CRM.$(this).click(function () {
        var itemID = CRM.$(this).data('itemid');
        showLineItemRemovalConfirmation(itemID);

        return false;
      });
    });
  });

  function showLineItemRemovalConfirmation(lineItemID) {
    CRM.api3('ContributionRecurLineItem', 'getcount', {
      'contribution_recur_id': recurringContributionID,
      'end_date': {'IS NULL': 1},
    }).done(function (result) {
      if (result.result < 2) {
        CRM.alert("Cannot remove the last item in an order!", null, 'alert');

        return;
      }

      var formUrl = CRM.url('civicrm/recurring-contribution/remove-lineitems', {
        reset: 1,
        contribution_recur_id: recurringContributionID,
        line_item_id: lineItemID
      });

      CRM.loadForm(formUrl, {
        dialog: {width: 480}
      }).on('crmFormSuccess', function(event, data) {
        CRM.refreshParent('#periodsContainer');
      });
    });
  }
  {/literal}
</script>
<div id="confirmLineItemDeletion" style="display: none;"></div>
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
  {assign var='subTotal' value=0}
  {assign var='taxTotal' value=0}
  {assign var='installmentTotal' value=0}

  {foreach from=$lineItems item='currentItem'}
    {assign var='subTotal' value=$subTotal+$currentItem.line_total}
    {assign var='taxTotal' value=$taxTotal+$currentItem.tax_amount}

    <tr id="lineitem-{$currentItem.id}" data-action="cancel"
        class="crm-entity rc-line-item {cycle values="odd-row,even-row"}">
      <td>{$currentItem.label}</td>
      <td>{$currentItem.start_date|date_format}</td>
      <td>{$currentItem.end_date|date_format}</td>
      <td><input type="checkbox"
                 disabled{if $currentItem.auto_renew} checked{/if} /></td>
      <td>{$currentItem.financial_type}</td>
      <td>{if $currentItem.tax_rate == 0}N/A{else}{$currentItem.tax_rate}%{/if}</td>
      <td>{$currentItem.line_total|crmMoney}</td>
      <td>
        <a class="remove-line-button" href="#" data-itemid="{$currentItem.id}">
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
