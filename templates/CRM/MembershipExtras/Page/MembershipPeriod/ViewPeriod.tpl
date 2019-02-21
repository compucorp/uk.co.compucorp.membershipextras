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
                <label>Estimated Legacy Period {help id="membershipextras_membershipperiod_is_historic" file="CRM/MembershipExtras/Form/MembershipPeriod/EditPeriod.hlp"}</label>
            </td>
            <td>
                {$membershipPeriod.is_historic}
            </td>
        </tr>
        </tbody>
    </table>

    <div id="custom-fieldset">
        <table class="no-border">
            <tbody>
            <tr>
                <td  class="section-shown form-item">
                    <div class="crm-accordion-wrapper collapsed">
                        <div class="crm-accordion-header">
                            Custom Fieldset
                        </div>
                        <div class="crm-accordion-body" style="display: none;">
                            {include file="CRM/Custom/Page/CustomDataView.tpl"}
                        </div>
                        <div class="clear"></div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    {if !empty($contributions)}
    <div id="contributions">
        <table class="no-border">
            <tbody>
            <tr>
                <td  class="section-shown form-item">
                    <div class="crm-accordion-wrapper">
                        <div class="crm-accordion-header">
                            Contributions
                        </div>
                        <div class="crm-accordion-body" style="display: block;">
                            <table class="crm-info-panel">
                                <thead>
                                <tr>
                                    <th>Amount</th>
                                    <th>Source</th>
                                    <th>Received</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                                </thead>
                                {foreach from=$contributions item="contribution"}
                                    <tr>
                                        <td>{$contribution.total_amount|crmMoney:$contribution.currency}</td>
                                        <td>{$contribution.contribution_source}</td>
                                        <td>{$contribution.receive_date|crmDate}</td>
                                        <td>{$contribution.contribution_status}</td>
                                        <td>
                                            <a href='{crmURL p="civicrm/contact/view/contribution" q="id=`$contribution.id`&action=view"}' class='action-item crm-hover-button' title='View Contribution'>View</a>
                                            <a href='{crmURL p="civicrm/contact/view/contribution" q="id=`$contribution.id`&action=update"}' class='action-item crm-hover-button' title='Edit Contribution'>Edit</a>
                                        </td>
                                    </tr>
                                {/foreach}
                            </table>
                        </div>
                        <div class="clear"></div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    {/if}

    {if !empty($recurContribution)}
    <div id="recur-contribution">
        <table class="no-border">
            <tbody>
            <tr>
                <td  class="section-shown form-item">
                    <div class="crm-accordion-wrapper">
                        <div class="crm-accordion-header">
                            Recurring Contribution
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
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    {/if}

</div>
