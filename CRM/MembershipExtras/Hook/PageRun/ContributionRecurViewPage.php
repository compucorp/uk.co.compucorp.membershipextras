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
      if (!$this->isActivePaymentPlan($recurId)) {
        return NULL;
      }

      $paymentPlanScheduleGenerator = new CRM_MembershipExtras_Service_PaymentScheme_PaymentPlanScheduleGenerator($recurId);
      $paymentsSchedule = $paymentPlanScheduleGenerator->generateSchedule();
      array_walk($paymentsSchedule['instalments'], function (&$value) {
        $value['charge_date'] = CRM_Utils_Date::customFormat($value['charge_date']);
      });

      return $paymentsSchedule;
    }
    catch (CRM_Extension_Exception $e) {
      return NULL;
    }
  }

  private function isActivePaymentPlan($recurId) {
    return \Civi\Api4\ContributionRecur::get()
      ->addSelect('payment_plan_extra_attributes.is_active')
      ->addWhere('id', '=', $recurId)
      ->execute()
      ->column('payment_plan_extra_attributes.is_active')[0];
  }

}
