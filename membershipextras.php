<?php

require_once 'membershipextras.civix.php';

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function membershipextras_civicrm_config(&$config) {
  _membershipextras_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function membershipextras_civicrm_xmlMenu(&$files) {
  _membershipextras_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function membershipextras_civicrm_install() {
  _membershipextras_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function membershipextras_civicrm_postInstall() {
  _membershipextras_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function membershipextras_civicrm_uninstall() {
  _membershipextras_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function membershipextras_civicrm_enable() {
  _membershipextras_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function membershipextras_civicrm_disable() {
  _membershipextras_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function membershipextras_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _membershipextras_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function membershipextras_civicrm_managed(&$entities) {
  _membershipextras_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function membershipextras_civicrm_caseTypes(&$caseTypes) {
  _membershipextras_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function membershipextras_civicrm_angularModules(&$angularModules) {
  _membershipextras_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function membershipextras_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _membershipextras_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_pre().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu/
 */
function membershipextras_civicrm_navigationMenu(&$menu) {
  $paymentPlanSettingsMenuItem = [
    'name' => ts('payment_plan_settings'),
    'label' => ts('Payment Plan Settings'),
    'url' => 'civicrm/admin/payment_plan_settings',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => NULL,
  ];

  _membershipextras_civix_insert_navigation_menu($menu, 'Administer/', $paymentPlanSettingsMenuItem);
}

/**
 * Implements hook_civicrm_pre().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pre
 */
function membershipextras_civicrm_pre($op, $objectName, $id, &$params) {
  /**
   * We store the contribution parameters in a static variable because we
   * need it for CRM_MembershipExtras_Hook_PreEdit_Membership class
   * to be able to determine the correct recurring contribution ID in
   * case there was more than one recurring contribution.
   * it also used to identify if a membership edit is triggered
   * by completing a contribution or not.
   *
   * It is not that pretty solution but there is no other
   * options for now.
   */
  static $contributionPreviousParams = NULL;
  if ($op === 'edit' && $objectName === 'Contribution' && !$contributionPreviousParams) {
    $contributionParamsResponse = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $id,
    ]);

    if (!empty($contributionParamsResponse['values'][0])) {
      $contributionPreviousParams = $contributionParamsResponse['values'][0];
    }
  }

  static $recurContributionPreviousStatus = NULL;
  if ($objectName === 'ContributionRecur' && $op === 'edit') {
    if (!empty($params['contribution_status_id'])) {
      $recurContributionPreviousStatus = _membershipextras_getRecurContributionPreviousStatus($id);
    }
  }

  if ($objectName === 'Membership' && $op == 'create') {
    $membershipPreHook = new CRM_MembershipExtras_Hook_Pre_MembershipCreate($params);
    $membershipPreHook->preProcess();
  }

  if ($objectName === 'Membership' && $op == 'edit') {
    $membershipPreHook = new CRM_MembershipExtras_Hook_Pre_MembershipEdit($id, $params, $contributionPreviousParams, $recurContributionPreviousStatus);
    $membershipPreHook->preProcess();
  }

  static $isFirstPaymentPlanContribution = TRUE;
  $isPaymentPlanPayment = _membershipextras_isPaymentPlanWithAtLeastOneInstallment();
  $membershipContributionCreation = ($objectName === 'Contribution' && $op === 'create' && !empty($params['membership_id']));
  if ($membershipContributionCreation && $isPaymentPlanPayment && $isFirstPaymentPlanContribution) {
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor($params);
    $paymentPlanProcessor->createPaymentPlan();
    $isFirstPaymentPlanContribution = FALSE;
  }

  static $firstPaymentPlanContributionId;
  $firstPaymentPlanContributionLineItemCreation = ($objectName === 'LineItem' && $op === 'create' && !empty($params['contribution_id'])
                                                  && (empty($firstPaymentPlanContributionId) || $firstPaymentPlanContributionId == $params['contribution_id']));
  if ($firstPaymentPlanContributionLineItemCreation && $isPaymentPlanPayment) {
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor($params);
    $paymentPlanProcessor->alterLineItemParameters();
    $firstPaymentPlanContributionId = $params['contribution_id'];
  }

  if ($objectName == 'ContributionRecur') {
    $contributionRecurPreHook = new CRM_MembershipExtras_Hook_Pre_ContributionRecur($op, $id, $params);
    $contributionRecurPreHook->preProcess();
  }
}

function _membershipextras_getRecurContributionPreviousStatus($id) {
  try{
    return civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'contribution_status_id.name',
      'id' => $id,
    ]);
  }
  catch (CRM_Core_Exception $exception) {
    return NULL;
  }
}

/**
 * Determines if the membership is paid using payment plan option having at
 * least one instalment.
 *
 * @return bool
 */
function _membershipextras_isPaymentPlanWithAtLeastOneInstallment() {
  $installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
  $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
  $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

  if ($isSavingContribution && $contributionIsPaymentPlan && $installmentsCount > 0) {
    return TRUE;
  }

  return FALSE;
}

/**
 * Implements hook_civicrm_post()
 */
function membershipextras_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName === 'EntityFinancialTrxn') {
    $entityFinancialTrxnHook = new CRM_MembershipExtras_Hook_Post_EntityFinancialTrxn($objectRef);
    $entityFinancialTrxnHook->updatePaymentPlanStatus();
  }

  if ($objectName == 'LineItem') {
    $lineItemPostHook = new CRM_MembershipExtras_Hook_Post_LineItem($op, $objectId, $objectRef);
    $lineItemPostHook->postProcess();
  }

  if ($objectName == 'Membership' && $op == 'create') {
    $membershipPaymentPostHook = new CRM_MembershipExtras_Hook_Post_MembershipCreate($objectRef);
    $membershipPaymentPostHook->process();
  }

  static $periodId = NULL;
  if ($objectName == 'MembershipPeriod') {
    $periodId = $objectId;
  }

  if ($objectName == 'MembershipPayment') {
    $membershipPaymentPostHook = new CRM_MembershipExtras_Hook_Post_MembershipPayment($op, $objectId, $objectRef, $periodId);
    $membershipPaymentPostHook->postProcess();
  }

  if ($objectName == 'Contribution' && $op == 'edit') {
    $membershipPaymentPostHook = new CRM_MembershipExtras_Hook_Post_ContributionEdit($objectRef);
    $membershipPaymentPostHook->process();
  }
}

/**
 * Implements hook_civicrm_postProcess()
 */
function membershipextras_civicrm_postProcess($formName, &$form) {
  $isAddAction = $form->getAction() & CRM_Core_Action::ADD;
  $isRenewAction = $form->getAction() & CRM_Core_Action::RENEW;

  if (
    ($formName === 'CRM_Member_Form_Membership' && $isAddAction)
    ||
    ($formName === 'CRM_Member_Form_MembershipRenewal' && $isRenewAction)
  ) {
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessor($form);
    $paymentPlanProcessor->postProcess();

    $offlineAutoRenewProcessor = new CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor($form);
    $offlineAutoRenewProcessor->postProcess();
  }

  if ($formName === 'CRM_Contribute_Form_UpdateSubscription') {
    $postProcessFormHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription($form);
    $postProcessFormHook->postProcess();
  }
}

/**
 * Implements hook_civicrm_buildForm()
 */
function membershipextras_civicrm_buildForm($formName, &$form) {
  if (
    ($formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::ADD))
    || ($formName === 'CRM_Member_Form_MembershipRenewal' && ($form->getAction() & CRM_Core_Action::RENEW))
  ) {
    $offlineAutoRenew = new CRM_MembershipExtras_Hook_BuildForm_MembershipOfflineAutoRenew($form);
    $offlineAutoRenew->buildForm();

    $membershipHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan($form);
    $membershipHook->buildForm();
  }

  if ($formName === 'CRM_Member_Form_MembershipStatus') {
    $membershipStatusHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipStatus();
    $membershipStatusHook->buildForm($form);
  }

  if ($formName === 'CRM_Contribute_Form_UpdateSubscription') {
    $updateFormHook = new CRM_MembershipExtras_Hook_BuildForm_UpdateSubscription($form);
    $updateFormHook->buildForm();
  }
}

/**
 * Implements hook_civicrm_validateForm()
 */
function membershipextras_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  $isNewMembershipForm = $formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::ADD);
  $isRenewMembershipForm = $formName === 'CRM_Member_Form_MembershipRenewal' && ($form->getAction() & CRM_Core_Action::RENEW);

  if ($isNewMembershipForm || $isRenewMembershipForm) {
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($contributionIsPaymentPlan) {
      $paymentPlanValidateHook = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($form, $fields, $errors);
      $paymentPlanValidateHook->validate();
    }
  }

  if($formName === 'CRM_Member_Form_Task_Batch') {
    $paymentPlanValidateHook = new CRM_MembershipExtras_Hook_ValidateForm_MemberTaskBatch($form, $errors);
    $paymentPlanValidateHook->validate();
  }
}

/**
 * Implements hook_civicrm_alterCalculatedMembershipStatus()
 */
function membershipextras_civicrm_alterCalculatedMembershipStatus(&$calculatedStatus, $arguments, $membership) {
  $alterMembershipStatusHook = new CRM_MembershipExtras_Hook_Alter_CalculatedMembershipStatus();
  $alterMembershipStatusHook->alterMembershipStatus($calculatedStatus, $arguments, $membership);
}

/**
 * Implements hook_civicrm_links()
 */
function membershipextras_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  if ($op == 'contribution.selector.recurring' && $objectName == 'Contribution') {
    $recurContribuLinksHook = new CRM_MembershipExtras_Hook_Links_RecurringContribution($objectId, $links, $mask);
    $recurContribuLinksHook->alterLinks();
  }
}

/**
 * Implements hook_civicrm_alterContent()
 */
function membershipextras_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  if ($tplName == 'CRM/Member/Page/Tab.tpl') {
    $memberTabPage  = new CRM_MembershipExtras_Hook_AlterContent_MemberTabPage($content, $object);
    $memberTabPage->alterContent();
  }
}

/**
 * Implements hook_civicrm_entityTypes()
 */
function membershipextras_civicrm_entityTypes(&$entityTypes) {
  return _membershipextras_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_pageRun
 */
function membershipextras_civicrm_pageRun($page) {
  if (get_class($page) === 'CRM_MembershipExtras_Page_EditContributionRecurLineItems') {
    CRM_Core_Resources::singleton()->addStyleFile(
      CRM_MembershipExtras_ExtensionUtil::LONG_NAME,
      'css/style.css',
      1
    );

    CRM_Core_Resources::singleton()->addScriptFile(
      CRM_MembershipExtras_ExtensionUtil::LONG_NAME,
      'js/CurrentPeriodLineItemHandler.js',
      1,
      'page-header'
    )->addScriptFile(
      CRM_MembershipExtras_ExtensionUtil::LONG_NAME,
      'js/NextPeriodLineItemHandler.js',
      1,
      'page-header'
    );
  }

  if (get_class($page) === 'CRM_Member_Page_Tab') {
    Civi::resources()->addStyleFile('uk.co.compucorp.membershipextras', 'css/membershipPeriodsNestedView.css');
  }
}

function membershipextras_civicrm_permission(&$permissions) {
  $permissions['delete membership period'] = ts('Delete Membership Period');
}
