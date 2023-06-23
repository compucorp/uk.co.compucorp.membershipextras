<p class="help">
    {ts}Here you can switch membership type for a member and arrange an additional fee if required.{/ts}<br>
    {ts}Note that if you would like the switch to take place at the end of the current period this should be done from the "Next period" tab rather than on this screen.{/ts}
</p>
<div class="crm-section">
  <table id="switch_membership_form_container" class="form-layout-compressed">
    <tbody>
    <tr>
      <td>{ts}Current Membership Type{/ts}</td>
      <td></td>
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

<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
