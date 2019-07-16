<div class="crm-block crm-form-block">
    <table class="form-layout-compressed">
        <tbody>
        <tr>
            <td class="label">
                <label id="contact_label">Contact</label>
            </td>
            <td>
                {$contactName}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>Membership</label>
            </td>
            <td>
                {$membershipType}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>{$form.start_date.label}</label>
            </td>
            <td>
                {$form.start_date.html}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>{$form.end_date.label}</label>
            </td>
            <td>
                {$form.end_date.html}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>{$form.is_active.label}</label>
            </td>
            <td>
                {$form.is_active.html}
            </td>
        </tr>
        <tr>
            <td class="label">
                <label>{$form.is_historic.label}</label>
                {help id="membershipextras_membershipperiod_is_historic" file="CRM/MembershipExtras/Form/MembershipPeriod/EditPeriod.hlp"}
            </td>
            <td>
                {$form.is_historic.html}
            </td>
        </tr>
        </tbody>
    </table>
    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
<script type="text/javascript">
  {literal}
  CRM.$(function($) {
    $('#start_date').addClass('dateplugin');
    $('#end_date').addClass('dateplugin');
  });
  {/literal}
</script>
