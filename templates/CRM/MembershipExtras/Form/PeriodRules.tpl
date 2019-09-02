<div class="region region-content">
  <div class="crm-block crm-form-block">
    <p>
      {$form.membershipextras_membership_period_rules_automatically_update_membership_period_with_overdue_payment.html} 
      {$form.membershipextras_membership_period_rules_automatically_update_membership_period_with_overdue_payment.label}
    </p>
    <div class="crm-block crm-content-block crm-hidden" id="advanceSettings">
      <table class="form-layout-compressed">
        <tbody>
          <tr>
            <td class="label">
              {$form.membershipextras_membership_period_rules_days_to_act_on_membership_period_with_overdue_payment.label}
            </td>
            <td>
              {$form.membershipextras_membership_period_rules_days_to_act_on_membership_period_with_overdue_payment.html}
            </td>
          </tr>
          <tr>
            <td class="label">
              {$form.membershipextras_membership_period_rules_action_on_period_with_overdue_payment.label}
            </td>
            <td>
              {$form.membershipextras_membership_period_rules_action_on_period_with_overdue_payment.html}
            </td>
          </tr>
        </tbody>
      </table>
      <table class="form-layout-compressed crm-hidden" id="endDateSettingsContainer">
        <tbody>
          <tr>
            <td class="label">
              {$form.membershipextras_membership_period_rules_update_the_period_end_date_to.label}
            </td>
            <td>
              {$form.membershipextras_membership_period_rules_update_the_period_end_date_to.html}
            </td>
          </tr>
          <tr>
            <td class="label">
              {$form.membershipextras_membership_period_rules_update_period_end_date_offset.label}
              {help id=membershipextras_membership_period_rules_update_period_end_date_offset file="CRM/MembershipExtras/Form/PeriodRules.hlp"}
            </td>
            <td>
              {$form.membershipextras_membership_period_rules_update_period_end_date_offset.html}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
  </div>
</div>
<script type="text/javascript">
{literal}
CRM.MembershipPeriodRules = CRM.MembershipPeriodRules || {};

CRM.MembershipPeriodRules.ModifyRulesHandler = (function($) {

  /**
   * Constructor
   */
  function ModifyRulesHandler() {
    this.autoUpdateOverdueMembershipPeriodCheckbox = $('#membershipextras_membership_period_rules_automatically_update_membership_period_with_overdue_payment');
    this.updateOverdueMembershipPeriodRadioGroup = $('input[name="membershipextras_membership_period_rules_action_on_period_with_overdue_payment"]');
    this.updateOverdueMembershipPeriodEndDateRadio = $('#membershipextras_membership_period_rules_action_on_period_with_overdue_payment_2');
    this.advanceSettingsContainer = $('#advanceSettings');
    this.endDateSettingsContainer = $('#endDateSettingsContainer');
    this.showAutoUpdateOverduePeriodSettingsToggler();
    this.showUpdateOverduePeriodEndDateSettingsToggler();
  }

  ModifyRulesHandler.prototype.addEventListeners = function() {
    this.autoUpdateOverdueMembershipPeriodCheckbox.change($.proxy(this.showAutoUpdateOverduePeriodSettingsToggler, this));
    this.updateOverdueMembershipPeriodRadioGroup.change($.proxy(this.showUpdateOverduePeriodEndDateSettingsToggler, this));
  }

  ModifyRulesHandler.prototype.showAutoUpdateOverduePeriodSettingsToggler = function(event) {
    var allowAutoUpdateCheckbox = event ? $(event.target) : this.autoUpdateOverdueMembershipPeriodCheckbox;
    this.advanceSettingsContainer.toggle(allowAutoUpdateCheckbox.is(':checked'));
  }

  ModifyRulesHandler.prototype.showUpdateOverduePeriodEndDateSettingsToggler = function(event) {
    event = this.updateOverdueMembershipPeriodRadioGroup;
    var isChecked = this.updateOverdueMembershipPeriodEndDateRadio.prop('checked');
    this.endDateSettingsContainer.toggle(isChecked);
  }

  return ModifyRulesHandler;
})(CRM.$);

var pageHandler = new CRM.MembershipPeriodRules.ModifyRulesHandler();
pageHandler.addEventListeners();
{/literal}
</script>