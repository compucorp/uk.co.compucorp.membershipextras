CRM.$(function () {
    const frequency = CRM.vars.membershipextras.contribution_frequency;

    CRM.$('.crm-recurcontrib-view-block > table > tbody > tr').each(function() {
        if(CRM.$('td.label', this).text() === 'Cycle Day' && frequency === 'year') {
            CRM.$(this).hide();
        }
        if(CRM.$('td.label', this).text() === 'Next Contribution') {
            CRM.$('td.label', this).text('Next Scheduled Contribution Date');
        }
    })
});
