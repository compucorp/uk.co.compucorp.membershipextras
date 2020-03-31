<?php

/**
 * Class CRM_MembershipExtras_Hook_Alter_MailParamsHandler.
 *
 * Implements alterMailParams hook.
 */
class CRM_MembershipExtras_Hook_Alter_MailParamsHandler {

  /**
   * Parameters being passed to template.
   *
   * @var array
   */
  private $params = [];

  public function __construct(&$params) {
    $this->params =& $params;
  }

  /**
   * Alters the parameters for the e-mail.
   */
  public function handle() {
    $this->useReceiveDateAsInvoiceDate();
  }

  /**
   * Changes the invoice date to be the contribution's receive date.
   */
  public function useReceiveDateAsInvoiceDate() {
    if (empty($this->params['valueName']) || $this->params['valueName'] != 'contribution_invoice_receipt') {
      return;
    }

    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'id' => $this->params['tplParams']['id'],
    ]);

    $this->params['tplParams']['invoice_date'] = date('F j, Y', strtotime($contribution['receive_date']));
  }

}
