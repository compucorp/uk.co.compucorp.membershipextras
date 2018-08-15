<script>
var recurringContributionID = {$recurringContributionID};

{literal}
  CRM.$(function () {
    CRM.$('.remove-next-period-line-button').each(function () {
      CRM.$(this).click(function (e) {
        e.preventDefault();

        var itemData = CRM.$(this).closest('tr').data('item-data');
        showNextPeriodLineItemRemovalConfirmation(itemData);

        CRM.$('#periodsContainer').on('crmLoad', function(event, data) {
          CRM.$('#tab_next a').click();
        });
      });
    });
  });

  function showNextPeriodLineItemRemovalConfirmation(lineItemData) {
    CRM.confirm({
      title: 'Remove ' + lineItemData.label + '?',
      message: 'Please note the changes should take effect immediately after "Apply"',
      options: {
        no: 'Cancel',
        yes: 'Apply'
      }
    }).on('crmConfirm:yes', function() {
      CRM.api3('ContributionRecurLineItem', 'create', {
        'id': lineItemData.id,
        'auto_renew': 0,
      }).done(function (lineRemovalRes) {
        
        if (lineRemovalRes.is_error) {
          CRM.alert('Cannot remove the last item in an order!', null, 'error');
          return;
        }

        if (lineItemData.entity_table === 'civicrm_membership') {
          CRM.api3('Membership', 'create', {
            'id': lineItemData.entity_id,
            'contribution_recur_id': '',
          }).done(function (membershipUnlinkRes) {
            
            if (membershipUnlinkRes.is_error) {
              CRM.alert('Cannot unlink the associated membership', null, 'alert');
              return;
            }
            
            CRM.refreshParent('#periodsContainer');
            CRM.alert(
              lineItemData.label + ' should no longer be continued in the next period.',
              null,
              'success'
            );
            return;
          });
        } else {
          CRM.refreshParent('#periodsContainer');
          CRM.alert(
            lineItemData.label + ' should no longer be continued in the next period.',
            null,
            'success'
          );
          return;
        }

      });
    }).on('crmConfirm:no', function() {
      return;
    });
  }
{/literal}
</script>
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

    <tr id="lineitem-{$currentItem.id}" data-action="cancel"
        data-item-data='{$currentItem|@json_encode}'
        class="crm-entity rc-line-item {cycle values="odd-row,even-row"}">
      <td>{$currentItem.label}</td>
      <td>{$currentItem.financial_type}</td>
      <td>{if !empty($currentItem.tax_rate)}{$currentItem.tax_rate}{else}N/A{/if}</td>
      <td>{$currentItem.line_total|crmMoney}</td>
      <td>
        <a class="remove-next-period-line-button" href="#">
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
