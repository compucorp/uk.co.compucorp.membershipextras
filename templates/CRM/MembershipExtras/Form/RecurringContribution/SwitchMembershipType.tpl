<script type="text/javascript">
    {literal}
    CRM.$(function () {
      var currentMembershipTypeId = {/literal}{$current_membership_type_id}{literal};

      var checkedVal = CRM.$('input[type=radio][name=payment_type]:checked').val()
      if (checkedVal != 2) {
        CRM.$('#payment_details_form_container').css('display', 'none');
      }

      CRM.$('input[type=radio][name=payment_type]').change(function() {
        if (this.value == 1) {
          CRM.$('#payment_details_form_container').css('display', 'none');
        }
        else if (this.value == 2) {
          CRM.$('#payment_details_form_container').css('display', 'inline');
        }
      });

      CRM.$('#new_membership_type').change(function() {
        var newMembershipTypeId = CRM.$(this).val();
        var fromDate = CRM.$('input[name=switch_date]').val();
        updateAmountFields(newMembershipTypeId, fromDate);
      });

      CRM.$('#switch_date').change(function() {
        var fromDate = CRM.$(this).val();
        var newMembershipTypeId = CRM.$('input[name=new_membership_type]').val();
        updateAmountFields(newMembershipTypeId, fromDate);
      });

      function updateAmountFields(newMembershipTypeId, fromDate) {
        CRM.$('button[data-identifier="_qf_SwitchMembershipType_submit"]').prop('disabled',true);

        var recurContributionId = CRM.$('#recurringContributionID').val();
        CRM.api3('ContributionRecurLineItem', 'calcmembershipprorata', {
          'recur_contribution_id': recurContributionId,
          'membership_type_id' : currentMembershipTypeId,
          'from_date' : fromDate,
        }).done(function (currentTypeResult) {
          CRM.api3('ContributionRecurLineItem', 'calcmembershipprorata', {
            'recur_contribution_id': recurContributionId,
            'membership_type_id' : newMembershipTypeId,
            'from_date' : fromDate,
          }).done(function (newTypeResult) {
            var amountExcTax = 0;
            var amountIncTax = 0;
            if (newTypeResult.amount_inc_tax > currentTypeResult.amount_inc_tax) {
              amountExcTax = (newTypeResult.amount_exc_tax - currentTypeResult.amount_exc_tax).toFixed(2);
              amountIncTax = (newTypeResult.amount_inc_tax - currentTypeResult.amount_inc_tax).toFixed(2);
            }

            CRM.$('input[name=amount_exc_tax]').val(amountExcTax);
            CRM.$('input[name=amount_inc_tax]').val(amountIncTax);
            CRM.$('#switchmembership_financial_type_id ').val(newTypeResult.used_financial_type_id).change();
            CRM.$('button[data-identifier="_qf_SwitchMembershipType_submit"]').prop('disabled',false);
          });
        });
      }

      CRM.$('input[name=amount_exc_tax]').keyup(function() {
        amountExcTax = CRM.$(this).val();
        financialTypeId = CRM.$('select[name=switchmembership_financial_type_id]').val();

        updateAmountIncTaxField(amountExcTax, financialTypeId);
      });

      CRM.$('select[name=switchmembership_financial_type_id]').on('change', function() {
        financialTypeId = CRM.$(this).val();
        amountExcTax = CRM.$('input[name=amount_exc_tax]').val();

        updateAmountIncTaxField(amountExcTax, financialTypeId);
      });

      function updateAmountIncTaxField(amountExcTax, financialTypeId) {
        CRM.$('button[data-identifier="_qf_SwitchMembershipType_submit"]').prop('disabled',true);

        CRM.api3('ContributionRecurLineItem', 'calculatetaxamount', {
          'amount_exc_tax': amountExcTax,
          'financial_type_id': financialTypeId
        }).done(function (response) {
          CRM.$('input[name=amount_inc_tax]').val(response.total_amount);
          CRM.$('button[data-identifier="_qf_SwitchMembershipType_submit"]').prop('disabled',false);
        });
      }
    });
    {/literal}
</script>

<p class="help">
    {ts}Here you can switch membership type for a member and arrange an additional fee if required.{/ts}<br>
    {ts}Note that if you would like the switch to take place at the end of the current period this should be done from the "Next period" tab rather than on this screen.{/ts}
</p>
<div class="crm-section">
  <table id="switch_membership_form_container" class="form-layout-compressed">
    <tbody>
    <tr>
      <td style="width: 50%;">{ts}Current Membership Type{/ts}</td>
      <td style="width: 20%;"></td>
      <td style="font-weight: 600;color: #464354;">{$form.new_membership_type.label}</td>
    </tr>
    <tr>
      <td style="font-weight: 100 !important;">{$current_membership_type_name}</td>
      <td style=""><span class="crm-i fa-arrow-right fa-sm fa-xl" style=""></span></td>
      <td>{$form.new_membership_type.html}</td>
    </tr>
    <tr>
      <td>{$form.switch_date.label}:</td>
      <td></td>
      <td></td>
    </tr>
    <tr>
      <td>{$form.switch_date.html}</td>
      <td></td>
      <td></td>
    </tr>
    <tr>
      <td>{$form.payment_type.label}</td>
      <td></td>
      <td></td>
    </tr>
    <tr id="payment_type_select">
      <td>{$form.payment_type.html}</td>
      <td></td>
      <td></td>
    </tr>
    </tbody>
  </table>
</div>

<div class="crm-section">
  <table id="payment_details_form_container" class="form-layout-compressed">
    <tbody>
    <tr>
      <td>{$form.scheduled_charge_date.label}</td>
      <td>{$form.scheduled_charge_date.html}</td>
    </tr>
    <tr>
      <td>{$form.amount_exc_tax.label}</td>
      <td>{$form.amount_exc_tax.html}</td>
    </tr>
      <tr>
        <td></td>
        <td><span class="description">{ts}This amount defaults to the pro-rata increased cost of the new membership type for the rest of the period, but you maybe enter any amount.{/ts}</span></td>
      </tr>
    <tr>
      <td>{$form.switchmembership_financial_type_id.label}</td>
      <td>{$form.switchmembership_financial_type_id.html}</td>
    </tr>
    <tr>
      <td>{$form.amount_inc_tax.label}</td>
      <td>{$form.amount_inc_tax.html}</td>
    </tr>
    <tr>
      <td>{$form.switchmembership_send_confirmation_email.label}</td>
      <td>{$form.switchmembership_send_confirmation_email.html}</td>
    </tr>
    </tbody>
  </table>
</div>

<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
