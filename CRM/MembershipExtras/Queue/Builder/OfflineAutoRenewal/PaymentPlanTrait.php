<?php

/**
 * Trait CRM_MembershipExtras_Queue_Builder_OfflineAutoRenewal_PaymentPlanTrait
 */
trait CRM_MembershipExtras_Queue_Builder_OfflineAutoRenewal_PaymentPlanTrait {

  /**
   * Maps contribution status names to their corresponding ID's.
   *
   * @var array
   */
  protected $contributionStatusesNameMap;

  /**
   * Number of days in advance a membership shuld be renewed.
   *
   * @var int
   */
  protected $daysToRenewInAdvance;

  /**
   * ID's for payment processors that are considered to be manual.
   *
   * @var array
   */
  protected $manualPaymentProcessorIDs;

  /**
   * CRM_MembershipExtras_Queue_Builder_MultipleInstalmentPlans constructor.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function __construct($queue) {
    parent::__construct($queue);

    $this->setContributionStatusesNameMap();
    $this->setManualPaymentProcessorIDs();
    $this->setDaysToRenewInAdvance();
  }

  /**
   * Gets contribution Statuses Name to value Mapping
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setContributionStatusesNameMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ['name', 'value'],
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ])['values'];

    $contributionStatusesNameMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesNameMap[$status['name']] = $status['value'];
    }

    $this->contributionStatusesNameMap = $contributionStatusesNameMap;
  }

  /**
   * Loads setting and assigns it to a class attribute.
   */
  private function setDaysToRenewInAdvance() {
    $this->daysToRenewInAdvance = CRM_MembershipExtras_SettingsManager::getDaysToRenewInAdvance();
  }

  /**
   * Loads list of manual payment processors into an array as a class attribute.
   */
  private function setManualPaymentProcessorIDs() {
    $payLaterProcessorID = 0;
    $this->manualPaymentProcessorIDs = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
  }

}
