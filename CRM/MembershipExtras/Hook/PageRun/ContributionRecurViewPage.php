<?php
class CRM_MembershipExtras_Hook_PageRun_ContributionRecurViewPage implements CRM_MembershipExtras_Hook_PageRun_PageRunInterface {

  private $page;

  const PAYMENT_PLAN_EXTRA_ATTRIBUTES_CUSTOM_GROUP_NAME = 'payment_plan_extra_attributes';

  /**
   * @param CRM_Core_Page $page
   */
  public function handle($page) {
    $this->page = $page;
    $this->modifyPageElements();
  }

  private function modifyPageElements() {
    $contributionData = $this->page->getTemplateVars('recur');
    $isActiveRecurringContribution = $this->isActivePaymentPlan($contributionData['id']);
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
        'is_active_recurring_contribution' => $isActiveRecurringContribution,
        'payment_scheme_schedule' => $paymentSchemeSchedule,
      ]
    );

    $this->setPaymentSchemeTitle();
  }

  private function isActivePaymentPlan($recurId) {
    return \Civi\Api4\ContributionRecur::get(FALSE)
      ->addSelect('payment_plan_extra_attributes.is_active')
      ->addWhere('id', '=', $recurId)
      ->execute()
      ->column('payment_plan_extra_attributes.is_active')[0];
  }

  private function getFuturePaymentSchemeScheduleIfExist($recurId) {
    try {
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

  private function setPaymentSchemeTitle() {
    $customData = $this->page->getTemplateVars('viewCustomData');
    $paymentSchemeGroup = civicrm_api3('CustomGroup', 'get', [
      'sequential' => 1,
      'name' => self::PAYMENT_PLAN_EXTRA_ATTRIBUTES_CUSTOM_GROUP_NAME,
    ]);
    $paymentSchemeField = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => self::PAYMENT_PLAN_EXTRA_ATTRIBUTES_CUSTOM_GROUP_NAME,
      'name' => 'payment_scheme_id',
    ]);

    if (empty($paymentSchemeGroup['id']) || empty($paymentSchemeField['id']) || empty($customData[$paymentSchemeGroup['id']])) {
      return;
    }
    $paymentSchemeGroupId = $paymentSchemeGroup['id'];
    $paymentSchemeFieldId = $paymentSchemeField['id'];

    foreach ($customData[$paymentSchemeGroupId] as $k => $group) {
      if (!empty($group['fields'][$paymentSchemeFieldId])) {
        $paymentScheme = \Civi\Api4\PaymentScheme::get(FALSE)
          ->addSelect('admin_title')
          ->addWhere('id', '=', $customData[$paymentSchemeGroupId][$k]['fields'][$paymentSchemeFieldId]['field_value'])
          ->setLimit(1)
          ->execute();

        if (!empty($paymentScheme[0])) {
          $paymentSchemeLink = CRM_Utils_System::url('civicrm/member/admin/payment-schemes');
          $paymentSchemeTitle = $paymentScheme[0]['admin_title'];

          $customData[$paymentSchemeGroupId][$k]['fields'][$paymentSchemeFieldId]['field_value'] =
            "<a href='$paymentSchemeLink'>$paymentSchemeTitle</a>";
        }
        break;
      }
    }

    $this->page->assign('viewCustomData', $customData);
  }

}
