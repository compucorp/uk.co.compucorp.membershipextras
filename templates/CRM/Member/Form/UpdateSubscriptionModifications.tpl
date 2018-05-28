{include file="CRM/common/paymentBlock.tpl"}
<script type="text/javascript">
  {literal}
  CRM.$(function($) {
    var formLayoutTable = $('#UpdateSubscription table.form-layout');

    $('#additional_fields tr').each(function () {
      formLayoutTable.append($(this));
    });

    $('#payment_instrument_id').trigger('change');
  });

  function processUpdate(buttonObj, formName, onClickLabel) {
    var showConfirmationDialog = false;

    if (CRM.$('#old_payment_instrument_id').val() != CRM.$('#payment_instrument_id').val()) {
      showConfirmationDialog = true;
    }

    if (CRM.$('#old_cycle_day').val() != CRM.$('#cycle_day').val()) {
      showConfirmationDialog = true;
    }

    if (showConfirmationDialog) {
      CRM.$('#confirmInstallmentsUpdate').dialog({
        modal: true, title: 'Update Recurring Contribution', zIndex: 10000, autoOpen: true,
        width: 'auto', resizable: false,
        buttons: {
          Yes: function () {
            CRM.$('#update_installments').val(1);
            submitOnce(buttonObj, formName, onClickLabel);
            CRM.$(this).dialog("close");
          },
          No: function () {
            submitOnce(buttonObj, formName, onClickLabel);
            CRM.$(this).dialog("close");
          }
        },
        close: function (event, ui) {
          CRM.$(this).dialog("close");
        }
      });
    } else {
      return submitOnce(buttonObj, formName, onClickLabel);
    }

    return false;
  }
  {/literal}
</script>

<table id="additional_fields">
  <tr id="payment_instrument_id_field">
    <td class="label">
      {$form.payment_instrument_id.label}
    </td>
    <td>
      {$form.payment_instrument_id.html}
      <input type="hidden" name="old_payment_instrument_id" id="old_payment_instrument_id" value="{$form.payment_instrument_id.value.0}" />
      <input type="hidden" name="update_installments" id="update_installments" value="0" />
    </td>
  </tr>
  <tr id="cycle_day_field">
    <td class="label">
      {$form.cycle_day.label}
    </td>
    <td>
      {$form.cycle_day.html}
      <input type="hidden" name="old_cycle_day" id="old_cycle_day" value="{$form.cycle_day.value}" />
    </td>
  </tr>
  <tr id="autorenew_field">
    <td class="label">
      {$form.auto_renew.label}
    </td>
    <td>
      {$form.auto_renew.html}
    </td>
  </tr>
  <tr id="billing_optional_fields" class="crm-membership-form-block-billing">
    <td colspan="2">
      <div id="billing-payment-block" class="crm-ajax-container"></div>
    </td>
  </tr>
</table>
<div id="confirmInstallmentsUpdate" style="display: none;">
  <table>
    <tr>
      <td>{ts}Do you want to update any outstanding instalment contribution with the new Payment Method/ Cycle Day?{/ts}</td>
    </tr>
  </table>
</div>
