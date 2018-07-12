<?php

/**
 * Alters Memberships Tab of Contact's detail view.
 */
class CRM_MembershipExtras_Hook_Alter_MemberTabPageContent {

  public function __construct(&$content) {
    $this->content = &$content;
  }

  /**
   * Executes alterations for the content of the page.
   */
  public function alterContent() {
    $this->appendJSCodeToWatchTotalAmountValueChanges();
  }

  /**
   * Appends a '.change()' to throw the event when the total amount is altered.
   */
  private function appendJSCodeToWatchTotalAmountValueChanges() {
    $snippet = CRM_Utils_Request::retrieve('snippet', 'Int');
    $priceSetID = CRM_Utils_Request::retrieve('priceSetId', 'Int');

    if ($snippet == CRM_Core_Smarty::PRINT_NOFORM && !empty($priceSetID)) {
      // Throw change event when total_amount is altered
      $this->content = preg_replace(
        '/cj\(\'#total_amount\'\)\.val\((.+)\);/',
        'cj(\'#total_amount\').val(${1}).change();',
        $this->content
      );
    }
  }

}
