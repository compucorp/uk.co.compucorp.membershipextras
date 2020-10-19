<div class="crm-block crm-form-block">
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

  <table class="form-layout-compressed">
    <tbody>
    <tr>
      <td class="label">{$form.label.label}</td>
      <td>{$form.label.html}</td>
    </tr>

    <tr>
      <td class="label">{$form.from_membership_type_id.label}</td>
      <td>{$form.from_membership_type_id.html}</td>
    </tr>

    <tr>
      <td class="label">{$form.to_membership_type_id.label}</td>
      <td>{$form.to_membership_type_id.html}</td>
    </tr>

    <tr>
      <td class="label"><label>{ts}Period to have held "from membership" for{/ts}</label></td>
      <td>{$form.upgrade_trigger_date_type.html}&nbsp;{$form.period_length.html}&nbsp;{$form.period_length_unit.html}</td>
    </tr>

    <tr>
      <td class="label">{$form.filter_group.label}</td>
      <td>{$form.filter_group.html}</td>
    </tr>

    <tr>
      <td class="label">{$form.is_active.label}</td>
      <td>{$form.is_active.html}</td>
    </tr>
    </tbody>
  </table>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>

