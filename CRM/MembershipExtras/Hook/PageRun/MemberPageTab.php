<?php

/**
 * Class CRM_MembershipExtras_Hook_PageRun_MemberPageTab
 */
class CRM_MembershipExtras_Hook_PageRun_MemberPageTab implements CRM_MembershipExtras_Hook_PageRun_PageRunInterface {

  use CRM_MembershipExtras_Helper_PaymentPlanTogglerTrait;

  /**
   * @param CRM_Core_Page $page
   */
  public function handle($page) {
    if (!$this->shouldHandle($page)) {
      return;
    }

    $this->addResources('page-header');
  }

  /**
   * Checks if this is the right page
   *
   * @param CRM_Core_Page $page
   *
   * @return bool
   */
  private function shouldHandle($page) {
    return $page instanceof CRM_Member_Page_Tab;
  }

}
