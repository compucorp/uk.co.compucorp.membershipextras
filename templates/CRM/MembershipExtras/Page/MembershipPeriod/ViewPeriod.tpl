<div class="crm-block crm-form-block">
    <table id="membership-period-data" class="form-layout-compressed">
        <tbody>
        <tr>
            <td class="label">
                <label>Contact</label>
            </td>
            <td>
                {$membershipPeriod.contact_name}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>Membership</label>
            </td>
            <td>
                {$membershipPeriod.membership_type_name}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>Start Date</label>
            </td>
            <td>
                {$membershipPeriod.start_date|crmDate}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>End Date</label>
            </td>
            <td>
                {$membershipPeriod.end_date|crmDate}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>Activated</label>
            </td>
            <td>
                {$membershipPeriod.is_active}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>Estimated Legacy Period?</label>
                {help id="membershipextras_membershipperiod_is_historic" file="CRM/MembershipExtras/Form/MembershipPeriod/EditPeriod.hlp"}
            </td>
            <td>
                {$membershipPeriod.is_historic}
            </td>
        </tr>
        </tbody>
    </table>

    <div id="custom-fieldset">
        <div class="crm-accordion-wrapper collapsed">
            <div class="crm-accordion-header">
                Custom Fieldset
            </div>
            <div class="crm-accordion-body" style="display: none;">
                {include file="CRM/Custom/Page/CustomDataView.tpl"}
            </div>
            <div class="clear"></div>
        </div>
    </div>
    
    <div id="contributions">
        <script type="text/javascript">
          var periodID = {$membershipPeriod.id};

          {literal}
            CRM.$(function($) {
              CRM.loadPage(
                CRM.url(
                  'civicrm/membership/period/related-contributions',
                  {
                    reset: 1,
                    id: periodID,
                  },
                  'back'
                ),
                {
                  target : '#contributions',
                  dialog : false
                }
              );
            });
          {/literal}
        </script>        
    </div>
    

    {if !empty($recurContribution)}
    <div id="recur-contribution">
        <div class="crm-accordion-wrapper">
            <div class="crm-accordion-header">
                Recurring Contributions
            </div>
            <div class="crm-accordion-body" style="display: block;">
                <table class="crm-info-panel">
                    <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Frequency</th>
                        <th>Start Date</th>
                        <th>Installments</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tr>
                        <td>{$recurContribution.amount|crmMoney:$recurContribution.currency}</td>
                        <td>Every {$recurContribution.frequency_interval} {$recurContribution.frequency_unit|ucfirst}(s)</td>
                        <td>{$recurContribution.start_date|crmDate}</td>
                        <td>{$recurContribution.installments}</td>
                        <td>
                            <a href='{crmURL p="civicrm/contact/view/contributionrecur" q="id=`$recurContribution.id`&cid=`$recurContribution.contact_id`"}' class='action-item crm-hover-button' title='View Recur Contribution'>View</a>
                            <a href='{crmURL p="civicrm/contribute/updaterecur" q="crid=`$recurContribution.id`&action=update&cid=`$recurContribution.contact_id`"}' class='action-item crm-hover-button' title='Edit Recur Contribution'>Edit</a>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="clear"></div>
        </div>
    </div>
    {/if}

</div>
