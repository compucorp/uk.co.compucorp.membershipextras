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
    $this->createManageInstallmentActivityTypes();
    $this->createFutureMembershipStatusRules();
  }

  /**
   * Creates 'Renew offline auto-renewal memberships'
   * Scheduled Job.
   */
  private function createOfflineAutoRenewalScheduledJob() {
    $result = civicrm_api3('Job', 'get', [
      'name' => 'Renew offline auto-renewal memberships',
    ]);

    if ($result['count'] > 0) {
      return;
    }

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
      'name' => 'line_item_external_id',
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
    $this->toggleFutureMembershipStatusRules(TRUE);
  }

  public function disable() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->toggle(FALSE);

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->toggle(FALSE);

    $this->toggleOfflineAutoRenewalScheduledJob(FALSE);
    $this->toggleFutureMembershipStatusRules(FALSE);
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

  private function toggleFutureMembershipStatusRules($newStatus) {
    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'current_renewed',
      'api.MembershipStatus.create' => ['id' => '$value.id', 'is_active' => $newStatus],
    ]);

    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'future_start',
      'api.MembershipStatus.create' => ['id' => '$value.id', 'is_active' => $newStatus],
    ]);
  }

  public function uninstall() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->remove();

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->remove();

    $this->removeOfflineAutoRenewalScheduledJob();
    $this->removeCustomExternalIDs();
    $this->removePeriodLinkCustomGroupAndFields();
    $this->removeManageInstallmentActivityTypes();
    $this->removeFutureMembershipStatusRules();
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
   * Add Related Payment Plan Periods' Custom Group and Fields
   */
  private function createPeriodLinkCustomGroupAndFields() {
    $customGroup = civicrm_api3('CustomGroup', 'get', [
      'sequential' => 1,
      'name' => 'related_payment_plan_periods',
    ]);

    if ($customGroup['count'] === 0) {
      civicrm_api3('CustomGroup', 'create', [
        'name' => 'related_payment_plan_periods',
        'title' => E::ts('Related Payment Plan Periods'),
        'extends' => 'ContributionRecur',
        'style' => 'Inline',
        'collapse_display' => 1,
        'weight' => 10,
        'is_active' => 1,
        'table_name' => 'civicrm_value_payment_plan_periods',
        'is_multiple' => 0,
        'collapse_adv_display' => 0,
        'is_reserved' => 0,
        'is_public' => 1,
      ]);
    }

    $customFields = [
      ['name' => 'previous_period', 'label' => E::ts('Previous Payment Plan Period')],
      ['name' => 'next_period', 'label' => E::ts('Next Payment Plan Period')],
    ];
    foreach ($customFields as $customField) {
      $result = civicrm_api3('CustomField', 'get', [
        'sequential' => 1,
        'name' => $customField['name'],
        'custom_group_id' => 'related_payment_plan_periods',
      ]);

      if ($result['count'] > 0) {
        continue;
      }

      civicrm_api3('CustomField', 'create', [
        'name' => $customField['name'],
        'label' => $customField['label'],
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_required' => 0,
        'is_searchable' => 0,
        'weight' => 2,
        'is_active' => 1,
        'is_view' => 1,
        'is_selector' => 0,
        'custom_group_id' => 'related_payment_plan_periods',
        'column_name' => $customField['name'],
      ]);
    }
  }

  /**
   * Remove 'Related Payment Plan Periods' Custom and Fields and Group
   */
  private function removePeriodLinkCustomGroupAndFields() {
    $customFields = [
      'previous_period',
      'next_period',
    ];
    civicrm_api3('CustomField', 'get', [
      'name' => ['IN' => $customFields],
      'custom_group_id' => 'related_payment_plan_periods',
      'api.CustomField.delete' => ['id' => '$value.id'],
    ]);

    civicrm_api3('CustomGroup', 'get', [
      'name' => 'related_payment_plan_periods',
      'api.CustomGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * Finds all payment plans and populate the offline recurring contribution
   * line item for the payment plans using an offline payment processor
   */
  private function updatePaymentPlans() {
    $updater = new CRM_MembershipExtras_Upgrader_Setup_PaymentPlanUpdater();
    $updater->run();
  }

  private function createManageInstallmentActivityTypes() {
    $optionValues = [
      ['name' => 'update_payment_plan_next_period', 'label' => 'Update Payment Plan Next Period'],
      ['name' => 'update_payment_plan_current_period', 'label' => 'Update Payment Plan Current Period'],
    ];
    foreach ($optionValues as $optionValue) {
      $result = civicrm_api3('OptionValue', 'get', [
        'sequential' => 1,
        'option_group_id' => 'activity_type',
        'name' => $optionValue['name'],
      ]);

      if ($result['count'] > 0) {
        $updateParams = [
          'id' => $result['id'],
          'filter' => 1,
          'is_reserved' => 1,
        ];
        civicrm_api3('OptionValue', 'create', $updateParams);
      } else {
        $optionValue['option_group_id'] = 'activity_type';
        $optionValue['filter'] = 1;
        $optionValue['is_reserved'] = 1;
        civicrm_api3('OptionValue', 'create', $optionValue);
      }
    }
  }

  /**
   * Creates membership status rule
   * that handles cases when the start
   * date of the membership is in the future.
   */
  private function createFutureMembershipStatusRules() {
    $this->createCurrentRenewMembershipStatusRule();
    $this->createFutureStartMembershipStatusRule();
  }

  /**
   * Creates a membership status rule that
   * applies when the membership join date
   * is in the past, but the membership
   * start date is in the future
   */
  private function createCurrentRenewMembershipStatusRule() {
    $membershipStatusRule = civicrm_api3('MembershipStatus', 'get', [
      'sequential' => 1,
      'name' => 'current_renewed',
    ]);

    if ($membershipStatusRule['count'] > 0) {
      return;
    }

    $minStatusWeight = civicrm_api3('MembershipStatus', 'getvalue', [
      'return' => 'weight',
      'options' => ['sort' => 'weight ASC', 'limit' => 1],
    ]);

    civicrm_api3('MembershipStatus', 'create', [
      'name' => 'current_renewed',
      'label' => 'Current Renewed',
      'start_event' => 'join_date',
      'start_event_adjust_unit' => 'day',
      'start_event_adjust_interval' => 1,
      'end_event' => 'start_date',
      'end_event_adjust_unit' => 'day',
      'end_event_adjust_interval' => -1,
      'is_current_member' => 1,
      'is_active' => 1,
      'weight' => $minStatusWeight - 1
    ]);
  }

  /**
   * Creates a membership status rule that
   * applies when the membership join date
   * and the  membership start date
   * are in the future
   */
  private function createFutureStartMembershipStatusRule() {
    $membershipStatusRule = civicrm_api3('MembershipStatus', 'get', [
      'sequential' => 1,
      'name' => 'future_start',
    ]);

    if ($membershipStatusRule['count'] > 0) {
      return;
    }

    $maxStatusWeight = civicrm_api3('MembershipStatus', 'getvalue', [
      'return' => 'weight',
      'options' => ['sort' => 'weight DESC', 'limit' => 1],
    ]);

    civicrm_api3('MembershipStatus', 'create', [
      'name' => 'future_start',
      'label' => 'Future Start',
      'start_event' => 'join_date',
      'start_event_adjust_unit' => 'year',
      'start_event_adjust_interval' => -1000,
      'end_event' => 'start_date',
      'is_current_member' => 0,
      'is_active' => 1,
      'weight' => $maxStatusWeight + 1
    ]);
  }

  private function removeManageInstallmentActivityTypes() {
    civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => [
        'IN' => ['update_payment_plan_next_period', 'update_payment_plan_current_period'],
      ],
      'api.OptionValue.delete' => ['id' => '$value.id'],
    ]);
  }

  private function removeFutureMembershipStatusRules() {
    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'current_renewed',
      'api.MembershipStatus.delete' => ['id' => '$value.id'],
    ]);

    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'future_start',
      'api.MembershipStatus.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * Adds membershipextras_contribution_recur_line_item table to DB.
   *
   * @return bool
   */
  public function upgrade_0001() {
    $this->executeSqlFile('sql/auto_install.sql');
    $this->createPeriodLinkCustomGroupAndFields();
    $this->updatePaymentPlans();
    $this->createManageInstallmentActivityTypes();

    return TRUE;
  }

  public function upgrade_0002() {
    $this->createFutureMembershipStatusRules();

    return TRUE;
  }

  /**
   * Compuclient Database is already
   * upgrader to upgrade_0002 which means
   * that the old current_renew status
   * is still on it. This upgrader is to provide
   * support for Compuclient sites by removing
   * the old status and force creating the new
   * ones.
   */
  public function upgrade_0003() {
    $this->removeOldCurrentRenewMembershipStatusRule();
    $this->createFutureMembershipStatusRules();

    return TRUE;
  }

  private function removeOldCurrentRenewMembershipStatusRule() {
    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'current_renew',
      'api.MembershipStatus.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * We here recreate manage installment
   * activity types if they do not exist,
   * if they do then we update them to be
   * reserved and hidden.
   */
  public function upgrade_0004() {
    $this->createManageInstallmentActivityTypes();

    return TRUE;
  }

  /**
   * Adds membershipextras_auto_membership_upgrade_rule table to DB.
   */
  public function upgrade_0005() {
    $this->executeSqlFile('sql/autoupgraderuletable_install.sql');

    return TRUE;
  }

}
