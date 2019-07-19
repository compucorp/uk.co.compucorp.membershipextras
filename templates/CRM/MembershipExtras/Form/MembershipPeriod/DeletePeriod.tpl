<div class="crm-block crm-form-block">
    <div class="messages status no-popup crm-membershipextras-delete-membership-period">
        {if $isTheOnlyPeriodOfMembership}
            <div>
                <span class="icon inform-icon"></span>
                <strong class="font-red"> WARNING - Please note deleting the last membership
                    period will also delete the membership. Would you like to proceed ? </strong>
            </div>
        {else}
            <div>
                <strong> Are you sure you want to delete this membership period ? </strong>
            </div>
        {/if}

        <div class="crm-submit-buttons">
            {include file="CRM/common/formButtons.tpl" location="bottom"}
        </div>
    </div>
</div>
