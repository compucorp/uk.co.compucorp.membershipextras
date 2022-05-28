<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

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
   * Data associated to the recurring contribution.
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * CRM_MembershipExtras_Hook_Links_RecurringContribution constructor.
   *
   * @param int $contributionID
   * @param array $links
   * @param int $mask
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function __construct($contributionID, &$links, &$mask) {
    $this->recurringContributionID = $contributionID;
    $this->recurringContribution = $this->getRecurringContribution();
    $this->links = &$links;
    $this->mask = &$mask;
  }

  /**
   * Loads recurring contribution.
   *
   * @return array
   *   Array with recurring contribution's data.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getRecurringContribution() {
    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->recurringContributionID,
    ]);
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

    if ($this->isLastRenewalOfManualPaymentPlan() && $this->hasManageInstallmentPermissions()) {
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
    $paymentProcessorID = CRM_Utils_Array::value('payment_processor_id', $this->recurringContribution);

    return ManualPaymentProcessors::isManualPaymentProcessor($paymentProcessorID);
  }

  /**
   * Checks if current recurring contribution is
   * the last in a payment plan by checking if
   * it is active or not.
   *
   * @return bool
   *   TRUE if the recurring contribution is the last in a succession of
   *   renewals, FALSE otherwise.
   *
   * @throws \Exception
   */
  private function isLastRenewalOfManualPaymentPlan() {
    $isActivePaymentPlanFieldId = $this->getCustomFieldID('payment_plan_extra_attributes', 'is_active');

    if (!CRM_Utils_Array::value('custom_' . $isActivePaymentPlanFieldId, $this->recurringContribution, FALSE)) {
      return FALSE;
    }

    return $this->isManualPaymentPlan();
  }

  /**
   * Checks if the logged-in user has enough permissions
   * to manage the installments.
   *
   * @return bool
   */
  private function hasManageInstallmentPermissions() {
    if (CRM_Core_Permission::check(['edit contributions', 'edit memberships'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Obtains ID for custom field name in given group.
   *
   * @param $fieldGroup
   * @param $fieldName
   *
   * @return int
   * @throws \Exception
   */
  protected function getCustomFieldID($fieldGroup, $fieldName) {
    $result = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => $fieldGroup,
      'name' => $fieldName,
    ]);

    if ($result['count'] > 0) {
      return $result['values'][0]['id'];
    }

    throw new Exception("Cannot find customfield $fieldName in $fieldGroup group.");
  }

}
