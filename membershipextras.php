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
   * We store the contribution ID in a static variable because we
   * need it for CRM_MembershipExtras_Hook_PreEdit_Membership class
   * to be able to determine the correct recurring contribution ID in
   * case there was more than one recurring contribution.
   * It is not that pretty solution but there is no much
   * options for now.
   */
  static $contributionID = NULL;
  if ($op === 'edit' && $objectName === 'Contribution') {
    $contributionID = $id;
  }

  if ($op === 'edit' && $objectName === 'Membership' && $contributionID) {
    $preEditMembershipHook = new CRM_MembershipExtras_Hook_PreEdit_Membership($id, $contributionID, $params);
    $preEditMembershipHook->preventExtendingOfflinePendingRecurringMembership();
  }

  $isPaymentPlanPayment = _membershipextras_isPaymentPlanPayment();

  $isMembershipRenewal = ($op === 'edit' && $objectName === 'Membership')
                         && (CRM_Utils_Request::retrieve('action', 'String') & CRM_Core_Action::RENEW);
  if ($isMembershipRenewal && $isPaymentPlanPayment) {
    $preEditMembershipHook = new CRM_MembershipExtras_Hook_PreEdit_Membership($id, $contributionID, $params);
    $preEditMembershipHook->extendPendingPaymentPlanMembershipOnRenewal();
  }
  
  static $isFirstPaymentPlanContribution = TRUE;
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
}

/**
 * Determines if the membership is paid
 * using payment plan option using more than
 * one installment or not.
 *
 * @return bool
 */
function _membershipextras_isPaymentPlanPayment() {
  $installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
  $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
  $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

  if ($isSavingContribution && $contributionIsPaymentPlan && $installmentsCount > 1) {
    return TRUE;
  }

  return FALSE;
}

/**
 * Implements hook_civicrm_postProcess()
 */
function membershipextras_civicrm_postProcess($formName, &$form) {
  if (
  ($formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::ADD))
    || ($formName === 'CRM_Member_Form_MembershipRenewal' && ($form->getAction() & CRM_Core_Action::RENEW))
  ) {
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessor($form);
    $paymentPlanProcessor->process();

    $offlineAutoRenewProcessor = new CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor($form);
    $offlineAutoRenewProcessor->process();
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
    $recurContribuLinksHook = new CRM_MembershipExtras_Hook_Links_RecurringContribution();
    //$recurContribuLinksHook->alterLinks($links);
  }
}
