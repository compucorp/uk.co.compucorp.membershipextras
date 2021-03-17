CRM.$(function ($) {
  var formLayoutTable = $('#UpdateSubscription table.form-layout');

  $('#additional_fields tr').each(function () {
    formLayoutTable.append($(this));
  });

  $('#payment_instrument_id').trigger('change');
});

function isPaymentMethodChanged() {
  return CRM.$('#old_payment_instrument_id').val() != CRM.$('#payment_instrument_id').val();
}

function isCycleDayChanged() {
  return CRM.$('#old_cycle_day').val() != CRM.$('#cycle_day').val();
}

function processUpdate(event) {
  if (!isPaymentMethodChanged() && !isCycleDayChanged()) {
    return true;
  }
  event.preventDefault();
  CRM.$('#confirmInstallmentsUpdate').dialog({
    modal: true, title: ts('Update Recurring Contribution'), zIndex: 10000, autoOpen: true,
    width: 'auto', resizable: false,
    buttons: [{
      text: ts('Yes'),
      click: function () {
        disableConfirmButton();
        CRM.$('#update_installments').val(1);
        CRM.$('#UpdateSubscription').unbind();
        CRM.$('#UpdateSubscription').submit();
        CRM.$(this).dialog("close");
      },
    }, {
      text: ts('No'),
      click: function () {
        CRM.$(this).dialog("close");
      }
    }],
    close: function (event, ui) {
      CRM.$(this).dialog("close");
    }
  });
}

function disableConfirmButton() {
  CRM.$('button[data-identifier="_qf_UpdateSubscription_upload"]').attr("disabled", true);
  CRM.$('button[data-identifier="_qf_UpdateSubscription_upload"]').html(ts('Processing') + '...');
}
