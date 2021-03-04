<script type="text/javascript">
  {literal}
  CRM.$(function ($) {
    $(".schedule-row").on('click', function(e) {
      e.preventDefault();
      $className = 'expanded';
      if ($(this).hasClass($className)) {
        $(this).removeClass($className);
        $(this).closest('tr').next('tr').hide();
      } else {
        $(this).addClass($className);
        $(this).closest('tr').next('tr').show();
      }
    });
  });
  {/literal}
</script>

<table id="instalment_row_table" class="selector row-highlight" style="position: relative;">
  <thead class="sticky">
  <tr>
    <th scope="col"></th>
    <th scope="col">{ts}Instalment no{/ts}</th>
    <th scope="col">{ts}Date{/ts}</th>
    <th scope="col">{ts}Tax Amount{/ts}</th>
    <th scope="col">{ts}Total{/ts}</th>
    <th scope="col">{ts}Status{/ts}</th>
  </tr>
  </thead>
  <tbody class="sticky">
  {foreach from=$instalments item=instalment}
    <tr>
      <td><a class="schedule-row nowrap bold crm-expand-row" title="view subitem" href="#">&nbsp</a></td>
      <td>{$instalment.instalment_no}</td>
      <td>{$instalment.instalment_date|crmDate}</td>
      <td>{$currency_symbol}{$instalment.instalment_tax_amount|crmNumberFormat:2}</td>
      <td>{$currency_symbol}{$instalment.instalment_total_amount|crmNumberFormat:2}</td>
      <td>
        {crmAPI var='contribution_status'
                entity='OptionValue'
                action='getsingle'
                sequential=0
                option_group_id="contribution_status"
                value=$instalment.instalment_status
        }
        {$contribution_status.label}
      </td>
    </tr>
    <tr style="display: none;">
    <td colspan="6">
      <table id="instalment_row_sub_table" style="position: relative;">
        <thead>
        <th>{ts}Item{/ts}</th>
        <th>{ts}Financial type{/ts}</th>
        <th>{ts}Quantity{/ts}</th>
        <th>{ts}Unit Price{/ts}</th>
        <th>{ts}Sub total{/ts}</th>
        <th>{ts}Tax Rate{/ts}</th>
        <th>{ts}Tax Amount{/ts}</th>
        <th>{ts}Total Amount{/ts}</th>
        </thead>
        <tbody>
       {foreach from=$instalment.instalment_lineitems item=lineitem}
         <tr>
           <td>{$lineitem.item_no}</td>
           <td>
             {crmAPI var='result' entity='FinancialType' action='getsingle' sequential=0 return="name" id=$lineitem.financial_type_id}
             {$result.name}
           </td>
           <td>{$lineitem.quantity}</td>
           <td>{$currency_symbol}{$lineitem.unit_price|crmNumberFormat:2}</td>
           <td>{$currency_symbol}{$lineitem.sub_total|crmNumberFormat:2}</td>
           <td>{$lineitem.tax_rate|crmNumberFormat:2}%</td>
           <td>{$currency_symbol}{$lineitem.tax_amount|crmNumberFormat:2}</td>
           <td>{$currency_symbol}{$lineitem.total_amount|crmNumberFormat:2}</td>
         </tr>
       {/foreach}
        </tbody>
      </table>
    </td>
    </tr>
  {/foreach}
  </tbody>
</table>
<div id="instalment-total-amount" style="display: none;">{$total_amount}</div>
