<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_PaymentProcessorType_ManualRecurringPayment as ManualRecurringPaymentProcessorType;
use CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringPaymentProcessor;

/**
 * Collection of upgrade steps.
 */
class CRM_MembershipExtras_Upgrader extends CRM_MembershipExtras_Upgrader_Base {

  public function Install() {
    $this->createOfflineAutoRenewalScheduledJob();
    $this->createPaymentProcessorType();
    $this->createPaymentProcessor();
    $this->createLineItemExternalIDCustomField();
    $this->executeSqlFile('sql/set_unique_external_ids.sql');
    $this->updatePaymentPlans();
  }

  /**
   * Creates 'Renew offline auto-renewal memberships'
   * Scheduled Job.
   */
  private function createOfflineAutoRenewalScheduledJob() {
    civicrm_api3('Job', 'create', [
      'run_frequency' => 'Daily',
      'name' => 'Renew offline auto-renewal memberships',
      'description' => ts('Automatically renew any offline/paylater membership that is configured to be auto-renewed'),
      'api_entity' => 'OfflineAutoRenewalJob',
      'api_action' => 'run',
      'is_active' => 0,
    ]);
  }

  private function createPaymentProcessorType() {
    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->create();
  }

  private function createPaymentProcessor() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->createIfNotExists();
    $paymentProcessor->setAsDefaultPaymentPlanProcessor();
  }

  /**
   * Creates 'External ID' Custom Field for LineItem
   */
  private function createLineItemExternalIDCustomField() {
    $optionValues = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'cg_extend_objects',
      'name' => 'civicrm_line_item'
    ]);

    if (!$optionValues['count']) {
      civicrm_api3('OptionValue', 'create', [
        'option_group_id' => 'cg_extend_objects',
        'name' => 'civicrm_line_item',
        'label' => ts('Line Item'),
        'value' => 'LineItem',
      ]);
    }

    $customGroups = civicrm_api3('CustomGroup', 'get', [
      'extends' => 'LineItem',
      'name' => 'recurring_contribution_external_id',
    ]);

    if (!$customGroups['count']) {
      $customGroups = civicrm_api3('CustomGroup', 'create', [
        'extends' => 'LineItem',
        'name' => 'line_item_external_id',
        'title' => E::ts('Line Item External ID'),
        'table_name' => 'civicrm_value_line_item_ext_id',
        'is_active' => 0,
        'style' => 'Inline',
        'is_multiple' => 0,
      ]);
    }

    $customFields = civicrm_api3('CustomField', 'get', [
      'custom_group_id' => $customGroups['id'],
      'name' => 'external_id',
    ]);
    if (!$customFields['count']) {
      $customField = civicrm_api3('CustomField', 'create', [
        'custom_group_id' => $customGroups['id'],
        'name' => 'external_id',
        'label' => E::ts('External ID'),
        'data_type' => 'String',
        'html_type' => 'Text',
        'required' => 0,
        'is_active' => 0,
        'is_searchable' => 1,
        'column_name' => 'external_id',
        'is_view' => 1
      ]);
    }
  }

  public function enable() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->toggle(TRUE);

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->toggle(TRUE);

    $this->toggleOfflineAutoRenewalScheduledJob(TRUE);
  }

  public function disable() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->toggle(FALSE);

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->toggle(FALSE);

    $this->toggleOfflineAutoRenewalScheduledJob(FALSE);
  }

  /**
   * Enables/Disables 'Renew offline auto-renewal memberships'
   * Scheduled Job based on the passed status.
   *
   * @param int $newStatus
   */
  private function toggleOfflineAutoRenewalScheduledJob($newStatus) {
    civicrm_api3('Job', 'get', [
      'name' => 'Renew offline auto-renewal memberships',
      'api.Job.create' => ['id' => '$value.id', 'is_active' => $newStatus],
    ]);
  }

  public function uninstall() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->remove();

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->remove();

    $this->removeOfflineAutoRenewalScheduledJob();
    $this->removeCustomExternalIDs();
  }

  /**
   * Removes 'Renew offline auto-renewal memberships'
   * Scheduled Job.
   */
  private function removeOfflineAutoRenewalScheduledJob() {
    civicrm_api3('Job', 'get', [
      'name' => 'Renew offline auto-renewal memberships',
      'api.Job.delete' => ['id' => '$value.id'],
    ]);
  }

  private function removeCustomExternalIDs() {
    $customGroupsToDelete = [
      'recurring_contribution_external_id',
      'contribution_external_id',
      'membership_external_id',
      'line_item_external_id',
    ];

    foreach ($customGroupsToDelete as $customGroup) {
      civicrm_api3('CustomGroup', 'get', [
        'name' => $customGroup,
        'api.CustomGroup.delete' => ['id' => '$value.id'],
      ]);
    }
  }

  /**
   * Add Related Payment Plan Periods' Custom Fields
   */
  private function createPeriodLinkCustomFields() {
    civicrm_api3('CustomGroup', 'create', [
      'name' => 'related_payment_plan_periods',
      'title' => E::ts('Related Payment Plan Periods'),
      'extends' => 'ContributionRecur',
      'style' => 'Inline',
      'collapse_display' => 1,
      'weight' => 10,
      'is_active' => 0,
      'table_name' => 'civicrm_value_payment_plan_periods',
      'is_multiple' => 0,
      'collapse_adv_display' => 0,
      'is_reserved' => 0,
      'is_public' => 1,
      'api.CustomField.create' => [
        [
          'custom_group_id' => '$value.id',
          'name' => 'previous_period',
          'label' => E::ts('Previous Payment Plan Period'),
          'data_type' => 'Int',
          'html_type' => 'Text',
          'is_required' => 0,
          'is_searchable' => 0,
          'weight' => 2,
          'is_active' => 0,
          'is_view' => 1,
          'is_selector' => 0,
          'custom_group_name' => 'related_payment_plan_periods',
          'column_name' => 'previous_period',
        ], [
          'custom_group_id' => '$value.id',
          'name' => 'next_period',
          'label' => E::ts('Next Payment Plan Period'),
          'data_type' => 'Int',
          'html_type' => 'Text',
          'is_required' => 0,
          'is_searchable' => 0,
          'weight' => 2,
          'is_active' => 0,
          'is_view' => 1,
          'is_selector' => 0,
          'custom_group_name' => 'related_payment_plan_periods',
          'column_name' => 'next_period',
        ]
      ],
    ]);
  }

  /**
   * Checks if a payment plan id is a manual payment
   * 
   * @param string $paymentProcessorID
   * @return bool
   */
  private function isManualPaymentPlan($paymentProcessorID) {
    $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();
    $isOfflineContribution = in_array($paymentProcessorID, $manualPaymentProcessors);

    if ($isOfflineContribution || empty($paymentProcessorID)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns all existing payment plans
   * 
   * @return array
   */
  private function getAllPaymentPlans() {
    $result = civicrm_api3('ContributionRecur', 'get', [
      'options' => ['limit' => 0],
    ]);

    $paymentPlans = [];
    if ($result['count'] > 0) {
      $paymentPlans = $result['values'];
    }

    return $paymentPlans;
  }

  /**
   * Gets the last instalment of a payment plan
   * 
   * @param string $paymentPlanId
   * 
   * @return array
   */
  private function getLastInstalmentForPaymentPlan($paymentPlanId) {
    if (empty($membershipID)) {
      return [];
    }

    $result = civicrm_api3('Contribution', 'getsingle', [
      'options' => ['sort' => 'id DESC'],
      'contribution_recur_id' => $paymentPlanId,
    ]);
  }

  /**
   * @param string $contributionId
   * 
   * @return array
   */
  private function getLineItemsForContribution($contributionId) {
    $result = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 0],
      'contribution_id' => $contributionId,
    ]);

    $lineItems = [];
    if ($result['count'] > 0) {
      $lineItems = $result['values'];
    }

    return $lineItems;
  }

  /**
   * 
   */
  private function copyLastInstalmentLineItemsToRecurContrib($paymentPlan, $lineItems) {
    foreach ($lineItems as $lineItem) {
      unset($lineItem['id']);
      unset($lineItem['contribution_id']);

      $params = array_merge($lineItem, [
        'api.ContributionRecurLineItem.create' => [
          'contribution_recur_id' => $paymentPlan['id'],
          'line_item_id' => '$value.id',
          'start_date' => $paymentPlan['start_date'],
          'end_date' => $paymentPlan['end_date'],
          'auto_renew' => $paymentPlan['auto_renew'],
          'is_removed' => 0,
        ],
      ]);
      civicrm_api3('LineItem', 'create', $params);
    }
  }

  private function getCustomFieldId($customGroupName, $customFieldName) {
    return civicrm_api3('CustomField', 'getvalue', [
      'return' => 'id',
      'custom_group_id' => $customGroupName,
      'name' => $customFieldName,
    ]);
  }

  private function createCustomValueForPaymentPlan($paymentPlanId) {
    $nextPeriodCustomFieldId = $this->getCustomFieldId('related_payment_plan_periods', 'next_period');
    $prevPeriodCustomFieldId = $this->getCustomFieldId('related_payment_plan_periods', 'previous_period');

    civicrm_api3('ContributionRecur', 'create', [
      [
        'id' => $paymentPlanId,
        'custom_' . $nextPeriodCustomFieldId => 0,
      ], [
        'id' => $paymentPlanId,
        'custom_' . $prevPeriodCustomFieldId => 0,
      ],
    ]);
  }

  /**
   * Finds all payment plans and populate the offline recurring contribution
   * line item for the payment plans using an offline payment processor
   */
  private function updatePaymentPlans() {
    $recurContributions = $this->getAllPaymentPlans();

    foreach ($recurringContributions as $paymentPlan) {
      if ($this->isManualPaymentPlan($paymentPlan['payment_processor_id'])) {
        $lastInstalment = $this->getLastInstalmentForPaymentPlan($paymentPlan['id']);
        $lineItems = $this->getLineItemsForContribution($lastInstalment['id']);

        $this->copyLastInstalmentLineItemsToRecurContrib($paymentPlan, $lineItems);
      }

      $isMatch = (
        $this->isManualPaymentPlan($paymentPlan['payment_processor_id']) &&
        !empty($this->getLastInstalmentForPaymentPlan($paymentPlan['id'])) &&
        !empty($paymentPlan['end_date']) && $this->isMoreThanOneMonthOld($paymentPlan['end_date']) &&
        $paymentPlan['auto_renew']
      );
      if ($isMatch) {
        $this->createCustomValueForPaymentPlan($paymentPlan['id']);
      }
    }
  }

  /**
   * Checks if a date is older than 1 month
   * 
   * @param string $date
   * 
   * @return bool
   */
  private function isMoreThanOneMonthOld($date) {
    return strtotime($date) < strtotime('-30 days');
  }

  /**
   * Adds membershipextras_contribution_recur_line_item table to DB.
   *
   * @return bool
   */
  public function upgrade_0001() {
    $this->executeSqlFile('sql/auto_install.sql');
    $this->createPeriodLinkCustomFields();
    $this->updatePaymentPlans();

    return TRUE;
  }

}
