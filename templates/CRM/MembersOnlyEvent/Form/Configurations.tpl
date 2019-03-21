<h3>{ts}Members-Only Event Extension Configurations{/ts}</h3>
<div class="crm-block crm-form-block crm-membersonlyevent-system-configs-form-block">
    <fieldset>
        <legend>{ts}Membership validation settings{/ts}</legend>
        <table class="form-layout-compressed">
            <tr class="crm-membersonlyevent-system-configs-form-block-validation-settings">
                <td class="label">{$form.membership_duration_check.label}</td>
                <td>{$form.membership_duration_check.html}</td>
            </tr>
        </table>
    </fieldset>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
