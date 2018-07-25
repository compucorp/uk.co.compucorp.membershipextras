<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Page_EditContributionRecurLineItems extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('View/Update Recurring Line Items'));

    $this->assign('lineItems', $this->getLineItems());

    parent::run();
  }

  /**
   * Obtains list of line items for the current recurring contribution.
   *
   * @return array
   */
  private function getLineItems() {

  }

}
