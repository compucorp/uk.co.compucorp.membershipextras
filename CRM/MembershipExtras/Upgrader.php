<?php

/**
 * Manages and configure entities during installation, uninstallation,
 * enabling and disabling the extension. Also includes the code
 * to run the upgrade steps defined in Upgrader/Steps/ directory.
 */
class CRM_MembershipExtras_Upgrader extends CRM_MembershipExtras_Upgrader_Base {

  public function postInstall() {
    // steps that create new entities.
    $creationSteps = [
      new CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob(),
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessorType(),
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessor(),
      new CRM_MembershipExtras_Setup_Manage_PaymentPlanActivityTypes(),
      new CRM_MembershipExtras_Setup_Manage_FutureMembershipStatusRules(),
    ];
    foreach ($creationSteps as $step) {
      $step->create();
    }

    // steps that configure existing entities or alter settings.
    $configurationSteps = [
      new CRM_MembershipExtras_Setup_Configure_SetManualPaymentProcessorAsDefaultProcessor(),
      new CRM_MembershipExtras_Setup_Configure_DisableContributionCancelActionsExtension(),
    ];
    foreach ($configurationSteps as $step) {
      $step->apply();
    }
  }

  public function enable() {
    $steps = [
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessorType(),
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessor(),
      new CRM_MembershipExtras_Setup_Manage_FutureMembershipStatusRules(),
      new CRM_MembershipExtras_Setup_Manage_PaymentPlanActivityTypes(),
      new CRM_MembershipExtras_Setup_Manage_CustomGroup_PaymentPlanExtraAttributes(),
      new CRM_MembershipExtras_Setup_Manage_CustomGroup_OfflineAutorenewOption(),
    ];
    foreach ($steps as $step) {
      $step->activate();
    }
  }

  public function disable() {
    $steps = [
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessor(),
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessorType(),
      new CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob(),
      new CRM_MembershipExtras_Setup_Manage_FutureMembershipStatusRules(),
      new CRM_MembershipExtras_Setup_Manage_PaymentPlanActivityTypes(),
      new CRM_MembershipExtras_Setup_Manage_CustomGroup_PaymentPlanExtraAttributes(),
      new CRM_MembershipExtras_Setup_Manage_CustomGroup_OfflineAutorenewOption(),
    ];
    foreach ($steps as $step) {
      $step->deactivate();
    }
  }

  public function uninstall() {
    $removalSteps = [
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessor(),
      new CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessorType(),
      new CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob(),
      new CRM_MembershipExtras_Setup_Manage_PaymentPlanActivityTypes(),
      new CRM_MembershipExtras_Setup_Manage_FutureMembershipStatusRules(),
      new CRM_MembershipExtras_Setup_Manage_CustomGroup_PaymentPlanExtraAttributes(),
      new CRM_MembershipExtras_Setup_Manage_CustomGroup_OfflineAutorenewOption(),
    ];
    foreach ($removalSteps as $step) {
      $step->remove();
    }
  }

  // To reduce the size of this Upgrader class we move upgraders to Upgrader/Steps folder.
  // The functions below override the ones defined in CRM_MembershipExtras_Upgrader_Base file.
  // These functions allow Civi to process the upgraders added in the Upgrader/Steps folder
  // because without these functions Civi will not process them by default.

  /**
   * @inheritdoc
   */
  public function hasPendingRevisions() {
    $revisions = $this->getRevisions();
    $currentRevisionNum = $this->getCurrentRevision();
    if (empty($revisions)) {
      return FALSE;
    }
    if (empty($currentRevisionNum)) {
      return TRUE;
    }
    return ($currentRevisionNum < max($revisions));
  }

  /**
   * @inheritdoc
   */
  public function enqueuePendingRevisions(CRM_Queue_Queue $queue) {
    $currentRevisionNum = (int) $this->getCurrentRevision();
    foreach ($this->getRevisions() as $revisionClass => $revisionNum) {
      if ($revisionNum <= $currentRevisionNum) {
        continue;
      }
      $tsParams = [1 => $this->extensionName, 2 => $revisionNum];
      $title = ts('Upgrade %1 to revision %2', $tsParams);
      $upgradeTask = new CRM_Queue_Task(
        [get_class($this), 'runStepUpgrade'],
        [(new $revisionClass())],
        $title
      );
      $queue->createItem($upgradeTask);
      $setRevisionTask = new CRM_Queue_Task(
        [get_class($this), '_queueAdapter'],
        ['setCurrentRevision', $revisionNum],
        $title
      );
      $queue->createItem($setRevisionTask);
    }
  }

  /**
   * This is a callback for running step upgraders from the queue
   *
   * @param CRM_Queue_TaskContext $context
   * @param \object $step
   *
   * @return true
   *   The queue requires that true is returned on successful upgrade, but we
   *   use exceptions to indicate an error instead.
   */
  public static function runStepUpgrade($context, $step) {
    $step->apply();
    return TRUE;
  }

  /**
   * Get a list of revisions.
   *
   * @return array
   *   An array of revisions sorted by the upgrader class as keys
   */
  public function getRevisions() {
    $extensionRoot = __DIR__;
    $stepClassFiles = glob($extensionRoot . '/Upgrader/Steps/Step*.php');
    $sortedKeyedClasses = [];
    foreach ($stepClassFiles as $file) {
      $class = $this->getUpgraderClassnameFromFile($file);
      $numberPrefix = 'Steps_Step';
      $startPos = strpos($class, $numberPrefix) + strlen($numberPrefix);
      $revisionNum = (int) substr($class, $startPos);
      $sortedKeyedClasses[$class] = $revisionNum;
    }
    asort($sortedKeyedClasses, SORT_NUMERIC);

    return $sortedKeyedClasses;
  }

  /**
   * Gets the PEAR style classname from an upgrader file
   *
   * @param $file
   *
   * @return string
   */
  private function getUpgraderClassnameFromFile($file) {
    $file = str_replace(realpath(__DIR__ . '/../../'), '', $file);
    $file = str_replace('.php', '', $file);
    $file = str_replace('/', '_', $file);
    return ltrim($file, '_');
  }

}
