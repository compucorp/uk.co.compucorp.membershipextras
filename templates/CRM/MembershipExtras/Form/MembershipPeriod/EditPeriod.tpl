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

  var activeNewStatus;
  var isActiveChanged = false;
  var activeOriginalState = (CRM.$('#is_active:checked').length > 0) ? 1 : 0;
  CRM.$('#is_active').change(function () {
    activeNewStatus = this.checked ? 1 : 0;
    if (activeOriginalState != activeNewStatus) {
      isActiveChanged = true;
    } else {
      isActiveChanged = false;
    }
  });

  CRM.$('input[value="Submit"]').on('click', function(e) {
    if (isActiveChanged) {
      e.preventDefault();
      var periodId = CRM.$("input[name='period_id']").val();
      var isActive = CRM.$("#is_active").val();

      var confirmationTitle = 'Deactivate Membership Period?';
      var confirmationButtonLabel = 'Deactivate';
      if (activeNewStatus) {
        confirmationTitle = 'Activate Membership Period?';
        confirmationButtonLabel = 'Activate';
      }

      CRM.confirm({
        title : confirmationTitle,
        url: CRM.url("civicrm/membership/period/preactive-validation", {'id' : periodId, 'is_active' : activeNewStatus}),
        options: {no: 'Cancel', yes: confirmationButtonLabel}
      }).on('crmConfirm:yes', function() {
        var updateParams = getUpdateApiParams();
        CRM.api3('MembershipPeriod', 'updateperiod', updateParams, true).done(function() {
          var redirectUrl = window.location.href;
          if (redirectUrl.indexOf("selectedChild=member") < 0) {
            redirectUrl += '&selectedChild=member';
          }
          window.location.href = redirectUrl;
        });

      });
    }
  });

  function getUpdateApiParams() {
    var formValues = {};
    CRM.$.each(CRM.$('#EditPeriod').serializeArray(), function(i, field) {
      formValues[field.name] = field.value;
    });

    var fieldsToUpdate = {
      'start_date' : '',
      'end_date' : '',
      'is_active' : 0,
      'is_historic' : 0
    };

    var apiParams = {};
    CRM.$.each(fieldsToUpdate, function(key, value) {
      if (!formValues[key]) {
        apiParams[key] = fieldsToUpdate[key];
      } else {
        apiParams[key] = formValues[key];
      }
    });

    apiParams['id'] = CRM.$("input[name='period_id']").val();

    return apiParams;
  }
  {/literal}
</script>
