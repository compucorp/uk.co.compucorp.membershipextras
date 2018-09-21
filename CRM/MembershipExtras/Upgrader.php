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
      'table_name' => 'civicrm_value_related_payme_21',
      'is_multiple' => 0,
      'collapse_adv_display' => 0,
      'is_reserved' => 0,
      'is_public' => 1,
      'api.CustomField.create' => [
        [
          'custom_group_id' => '$value.id',
          'name' => 'previous_payment_plan_period',
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
          'column_name' => 'previous_payment_plan_period',
        ], [
          'custom_group_id' => '$value.id',
          'name' => 'next_payment_plan_period',
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
          'column_name' => 'next_payment_plan_period',
        ]
      ],
    ]);
  }

  /**
   * Adds membershipextras_contribution_recur_line_item table to DB.
   *
   * @return bool
   */
  public function upgrade_0001() {
    $this->executeSqlFile('sql/auto_install.sql');
    $this->createPeriodLinkCustomFields();

    return TRUE;
  }

}
