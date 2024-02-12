CRM.$(function () {
  const isActiveRecurringContribution = CRM.vars.membershipextras.is_active_recurring_contribution;
  const paymentSchemeSchedule = CRM.vars.membershipextras.payment_scheme_schedule;
  hideUnnecessaryPaymentPlanFields();
  addFuturePaymentSchemeSchedule();

  function hideUnnecessaryPaymentPlanFields() {
    const frequency = CRM.vars.membershipextras.recur_contribution['frequency_unit'];
    const paymentSchemeFieldsToHide = ['Cycle Day', 'Frequency', 'Installments', 'Next Contribution'];

    CRM.$('.crm-recurcontrib-view-block > table > tbody > tr').each(function() {
      // hide 'Cycle Day' for all annual payment plans.
      if(CRM.$('td.label', this).text() === 'Cycle Day' && frequency === 'year') {
        CRM.$(this).hide();
      }

      if(paymentSchemeFieldsToHide.includes(CRM.$('td.label', this).text()) && paymentSchemeSchedule !== null) {
        CRM.$(this).hide();
      }

      if(CRM.$('td.label', this).text() === 'Next Contribution') {
        CRM.$('td.label', this).text('Next Scheduled Contribution Date');
      }
    });
  }

  function addFuturePaymentSchemeSchedule() {
    if (paymentSchemeSchedule === null) {
      return;
    }

    // no point in showing future instalments for inactive payment plans
    if (!isActiveRecurringContribution) {
      return;
    }

    var paymentSchemeBlock = '<tr><td class="label">Future payment scheme Instalments</td><td>';
    paymentSchemeBlock += '<table>';
    paymentSchemeBlock += '<thead>';
    paymentSchemeBlock += '<tr><th>Instalment Number</th><th>Expected charge date</th></tr>';
    paymentSchemeBlock += '</thead>';
    paymentSchemeBlock += '<tbody>'

    CRM.$.each(paymentSchemeSchedule['instalments'], function(index, instalment_data) {
      var chargeDate = instalment_data.charge_date;
      var rowBlock  = '<tr><td>' + (index + 1) + '</td><td>' + chargeDate + '</td></tr>';
      paymentSchemeBlock += rowBlock;
    });

    paymentSchemeBlock += '</tbody>';
    paymentSchemeBlock += '</table>';
    paymentSchemeBlock += '</td></tr>';

    CRM.$('.crm-recurcontrib-view-block table:first tbody:first').append(paymentSchemeBlock);
  }
});
