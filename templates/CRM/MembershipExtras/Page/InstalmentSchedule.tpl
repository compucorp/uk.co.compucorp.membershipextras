{crmStyle ext=uk.co.compucorp.membershipextras file=css/instalmentSchedule.css}
{crmScript ext=uk.co.compucorp.membershipextras file=js/instalmentSchedule.js}
<table id="instalment_row_table" class="selector row-highlight" style="position: relative;">
  <thead class="sticky">
  <tr>
    <th scope="col"></th>
    <th scope="col">{ts}Instalment no{/ts}</th>
    <th scope="col">{ts}Date{/ts}</th>
    <th scope="col">{$tax_term} {ts}Amount{/ts}</th>
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
      <td>{$currency_symbol}&nbsp;{$instalment.instalment_tax_amount|crmNumberFormat:2}</td>
      <td>{$currency_symbol}&nbsp;{$instalment.instalment_total_amount|crmNumberFormat:2}</td>
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
          <th>{$tax_term} {ts}Rate{/ts}</th>
          <th>{$tax_term} {ts}Amount{/ts}</th>
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
              <td>{$currency_symbol}&nbsp;{$lineitem.unit_price|crmNumberFormat:2}</td>
              <td>{$currency_symbol}&nbsp;{$lineitem.sub_total|crmNumberFormat:2}</td>
              <td>{$lineitem.tax_rate|crmNumberFormat:2}%</td>
              <td>{$currency_symbol}&nbsp;{$lineitem.tax_amount|crmNumberFormat:2}</td>
              <td>{$currency_symbol}&nbsp;{$lineitem.total_amount|crmNumberFormat:2}</td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </td>
    </tr>
  {/foreach}
  <tr>
    <td colspan="3"></td>
    <td class="instalment-amount-text"><span>{ts}Sub Total Amount{/ts}</span></td>
    <td>
      {$currency_symbol}&nbsp;{$sub_total|crmNumberFormat:2}
    </td>
    <td></td>
  </tr>
  <tr>
    <td colspan="3"></td>
    <td class="instalment-amount-text">{ts}Total{/ts} {$tax_term} {ts}Amount{/ts}</td>
    <td>
      {$currency_symbol}&nbsp;{$tax_amount|crmNumberFormat:2}
    </td>
    <td></td>
  </tr>
  <tr>
    <td colspan="3"></td>
    <td class="instalment-amount-text">{ts}Total Amount{/ts}</td>
    <td colspan="2">
      <span>{$currency_symbol}&nbsp;<span id="instalment-total-amount">{$total_amount|crmNumberFormat:2}</span>
      {if isset($prorated_number) && isset($prorated_unit)}
        <span class="instalment-prorated-text">({ts}Prorated for {/ts} {$prorated_number} {$prorated_unit})</span>
      {/if}
    </td>
  </tr>
  </tbody>
</table>

<div id="instalment-membership-start-date" class="instalment-hidden">{$membership_start_date}</div>
<div id="instalment-membership-end-date" class="instalment-hidden">{$membership_end_date}</div>
