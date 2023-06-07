<?php
class CRM_MembershipExtras_Hook_PageRun_ContributionRecurViewPage implements CRM_MembershipExtras_Hook_PageRun_PageRunInterface {

  private $page;

  /**
   * @param CRM_Core_Page $page
   */
  public function handle($page) {
    $this->page = $page;
    $this->modifyPageElements();
  }

  private function modifyPageElements() {
    $contributionData = $this->page->get_template_vars('recur');
    $paymentSchemeSchedule = $this->getFuturePaymentSchemeScheduleIfExist($contributionData['id']);

    CRM_Core_Resources::singleton()->addScriptFile(
      CRM_MembershipExtras_ExtensionUtil::LONG_NAME,
      'js/modifyRecurringContributionPage.js',
      1,
      'page-header'
    )->addVars(
      CRM_MembershipExtras_ExtensionUtil::SHORT_NAME,
      [
        'recur_contribution' => $contributionData,
        'payment_scheme_schedule' => $paymentSchemeSchedule,
      ]
    );
  }

  private function getFuturePaymentSchemeScheduleIfExist($recurId) {
    try {
      $paymentPlanScheduleGenerator = new CRM_MembershipExtras_Service_PaymentScheme_PaymentPlanScheduleGenerator($recurId);
      return $paymentPlanScheduleGenerator->generateSchedule();
    }
    catch (CRM_Extension_Exception $e) {
      return NULL;
    }
  }

}
