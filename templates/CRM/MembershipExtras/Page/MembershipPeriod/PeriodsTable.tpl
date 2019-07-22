<table class="periods-nested-view-table">
    <tr>
        <th>Term</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Actions</th>
    </tr>
    {foreach from=$membershipPeriods item='membershipPeriod'}
        <tr class="{$membershipPeriod.css_class}">
            <td>Term {$membershipPeriod.term_number}</td>
            <td>{$membershipPeriod.start_date|crmDate}</td>
            <td>{$membershipPeriod.end_date|crmDate}</td>
            <td>
                <span>
                    <a href='{crmURL p="civicrm/membership/period/view" q="id=`$membershipPeriod.id`"}' class='period-action action-item crm-hover-button' title='View Membership Period'>View</a>
                    <a href='{crmURL p="civicrm/membership/period/edit" q="id=`$membershipPeriod.id`"}' class='period-action action-item crm-hover-button' title='Edit Membership Period'>Edit</a>
                </span>
                <span class="btn-slide crm-hover-button">
                    ...
                    <ul class="panel" style="display: none;">
                        {if $membershipPeriod.is_active}
                            <li>
                                <a href='{crmURL p="civicrm/membership/period/deactivate" q="id=`$membershipPeriod.id`"}' class='period-action action-item crm-hover-button' title='Deactivate Membership Period'>Deactivate</a>
                            </li>
                        {else}
                            <li>
                                <a href='{crmURL p="civicrm/membership/period/activate" q="id=`$membershipPeriod.id`"}' class='period-action action-item crm-hover-button' title='Activate Membership Period'>Activate</a>
                            </li>
                        {/if}

                        <li>
                            <a href='{crmURL p="civicrm/membership/period/delete" q="id=`$membershipPeriod.id`"}' class='period-action action-item crm-hover-button' title='Delete Membership Period'>Delete</a>
                        </li>
                    </ul>

                </span>
            </td>
        </tr>
    {/foreach}
</table>
