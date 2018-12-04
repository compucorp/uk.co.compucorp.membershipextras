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
                <a href='{crmURL p="civicrm/membership/period/view" q="id=`$membershipPeriod.id`"}' class='action-item crm-hover-button' title='View Membership Period'>View</a>
                <a href='{crmURL p="civicrm/membership/period/edit" q="id=`$membershipPeriod.id`"}' class='action-item crm-hover-button' title='Edit Membership Period'>Edit</a>
            </td>
        </tr>
    {/foreach}
</table>
