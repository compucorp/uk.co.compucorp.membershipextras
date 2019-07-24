<?php

/**
 * Alters action links for recurring contributions.
 */
class CRM_MembershipExtras_Hook_Links_RecurringContribution {

  /**
   * List of links for the current recurring contribution.
   *
   * @var array
   */
  private $links;

  /**
   * ID for the current recurring contribution.
   *
   * @var int
   */
  private $recurringContributionID;

  /**
   * Bitmask being used to filter actions for the current recurring
   * contribution.
   *
   * @var int
   */
  private $mask;

  /**
   * CRM_MembershipExtras_Hook_Links_RecurringContribution constructor.
   *
   * @param int $contributionID
   * @param array $links
   * @param int $mask
   */
  public function __construct($contributionID, &$links, &$mask) {
    $this->recurringContributionID = $contributionID;
    $this->links = &$links;
    $this->mask = &$mask;
  }

  /**
   * Alters 'Cancel' action link to use a custom form, instead of CiviCRM's
   * default enable/disable weird control.
   */
  public function alterLinks() {
    foreach ($this->links as &$actionLink) {
      if ($actionLink['name'] == 'Cancel') {
        unset($actionLink['ref']);
        $actionLink['url'] = 'civicrm/recurring-contribution/cancel';
        $actionLink['qs'] = 'reset=1&crid=%%crid%%&cid=%%cid%%&context=contribution';
      }

      if ($actionLink['name'] == 'Edit' && $this->isManualPaymentPlan()) {
        $this->mask |= CRM_Core_Action::UPDATE;
      }
    }

    if ($this->isManualPaymentPlan()) {
      $this->links[] = [
        'name' => 'View/Modify Future Instalments',
        'url' => 'civicrm/recurring-contribution/edit-lineitems',
        'qs' => 'reset=1&crid=%%crid%%&cid=%%cid%%&context=contribution',
        'title' => 'View/Modify Future Instalments',
      ];
    }
  }

  /**
   * Checks if current recurring contribution is a manual payment plan.
   *
   * @return bool
   */
  private function isManualPaymentPlan() {
    $recurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->recurringContributionID
    ]);

    $paymentProcessorID = CRM_Utils_Array::value('payment_processor_id', $recurringContribution);
    $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();
    $isOfflineContribution = in_array($paymentProcessorID, $manualPaymentProcessors);

    if ($isOfflineContribution || empty($paymentProcessorID)) {
      return TRUE;
    }

    return FALSE;
  }

}
