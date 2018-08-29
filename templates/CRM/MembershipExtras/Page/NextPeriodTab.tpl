<script>
var recurringContributionID = {$recurringContributionID};
var financialTypes = JSON.parse('{$financialTypes|@json_encode}');

{literal}
  CRM.$(function () {

    CRM.$('.remove-next-period-line-button').each(function () {
      CRM.$(this).click(function (e) {
        e.preventDefault();
        var itemData = CRM.$(this).closest('tr').data('item-data');
        showNextPeriodLineItemRemovalConfirmation(itemData);

        window.CompucorpMembershipExtras_selectedTab = 'next';
      });
    });

    CRM.$('#next_buttons #addOtherAmount').on('click', function(e) {
      e.preventDefault();
      CRM.$('#addLineItemRow').show();
      CRM.$('#periodsContainer').find('tr').not(CRM.$('#addLineItemRow')).addClass('disabled-row');
      CRM.$('#periodsContainer').find('a').not(CRM.$('#addLineItemRow').find('a')).addClass('disabled-click');
    });

    CRM.$('.cancel-add-next-period-line-button').on('click', function(e) {
      e.preventDefault();
      CRM.$('#addLineItemRow').hide();
      CRM.$('#periodsContainer').find('tr').removeClass('disabled-row');
      CRM.$('#periodsContainer').find('a').removeClass('disabled-click');
    });

    CRM.$('#financialType').on('change', function() {
      var selectedId = CRM.$(this).val();
      var financialType = getFinancialType(selectedId);

      if (!financialType) {
        throw new Error('Invalid financial type id passed');
      }
      
      CRM.$('#financialTypeTaxRate').text(financialType.tax_rate || 'N/A');
    });

    CRM.$('.confirm-add-next-period-line-button').on('click', function(e) {
      e.preventDefault();
      
      var label = CRM.$('#item').val(),
          amount = CRM.$('#amount').val(),
          financial_type_id = CRM.$('#financialType').val();

      if (!label.length) {
        CRM.alert('Item label is required', null, 'error');
        
        return;
      }

      if (!amount.length) {
        CRM.alert('Item amount is required', null, 'error');

        return;
      } else {
        try {
          amount = parseInt(amount);
        } catch(error) {
          CRM.alert('Amount you entered is not valid', null, 'error');

          return;
        }
      }

      showAddOtherAmountConfirmation(label, amount, financial_type_id)
    });
  });

  function getFinancialType(id) {
    return financialTypes.filter(function(financialType) {
      return financialType.id === id;
    })[0];
  }

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

  function showAddOtherAmountConfirmation(label, amount, financial_type_id) {
    CRM.confirm({
        title: 'Add ' + label + '?',
        message: 'Please note the changes should take effect immediately after "Apply".',
        options: {
          no: 'Cancel',
          yes: 'Apply'
        }
      }).on('crmConfirm:yes', function() {
        var financialType = getFinancialType(financial_type_id),
            taxAmount = financialType.tax_rate * amount;

        CRM.api3('LineItem', 'create', {
          label: label,
          entity_id: recurringContributionID,
          qty: 1.0,
          unit_price: amount,
          line_total: amount,
          tax_amount: taxAmount,
          financial_type_id: financial_type_id,
          entity_table: 'civicrm_contribution_recur',
        }).done(function(lineItemResult) {
          if (lineItemResult.is_error) {
            CRM.alert(lineItemResult.error_message, null, 'error');

            return;
          }

          var createdLineItemId = lineItemResult.id;
          CRM.api3('ContributionRecurLineItem', 'create', {
            contribution_recur_id: recurringContributionID,
            line_item_id: createdLineItemId,
            auto_renew: true,
          }).done(function(result) {
            if (result.is_error) {
              CRM.alert(result.error_message, null, 'error');

              return;
            }

            CRM.alert(
              label + ' will now be continued in the next period.',
              null,
              'success'
            );
            CRM.refreshParent('#periodsContainer');
          });
        });
      }).on('crmConfirm:no', function() {
        return;
      });
  }
{/literal}
</script>
<style>
  {literal}
    .crm-container a:hover .crm-i.fa-check,
    .crm-container a:hover .crm-i.fa-times,
    .crm-container a:hover .crm-i.fa-trash {
      color: #8A1F11;
      cursor: pointer;
    }

    .crm-container a.disabled-click,
    .crm-container a.button.clickable.disabled-click {
      pointer-events: none;
      color: #ddd;
    }

    tr.disabled-row {
      color: #ddd;
    }

    input.required,
    #newline_membership_type.required {
      border: 2px solid #900 !important;
    }
  {/literal}
</style>
<div class="right">
  Period Start Date: {$nextPeriodStartDate|date_format}
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
        <a class="remove-next-period-line-button">
          <span><i class="crm-i fa-trash"></i></span>
        </a>
      </td>
    </tr>
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
    <td id="financialTypeTaxRate">{if !empty($financialTypes[0].tax_rate)}{$financialTypes[0].tax_rate}{else}N/A{/if}</td>
    <td>
      {$currencySymbol}&nbsp; <input type="text" class="four crm-form-text" size="4" id="amount" />
    </td>
    <td>
      <a href="#" class="cancel-add-next-period-line-button">
        <span><i class="crm-i fa-times crm-i-red"></i></span>
      </a>
      <a href="#" class="confirm-add-next-period-line-button">
        <span><i class="crm-i fa-check crm-i-green"></i></span>
      </a>
    </td>
  </tr>
  </tbody>
</table>
<div id="next_buttons">
  <button class="crm-button" id="addMembership">
    <span><i class="crm-i fa-plus"></i>&nbsp; Add Membership</span>
  </button>
  <button class="crm-button" id="addOtherAmount">
    <span><i class="crm-i fa-plus"></i>&nbsp; Add Other Amount</span>
  </button>
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
