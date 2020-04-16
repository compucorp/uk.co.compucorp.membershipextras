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
 * Implements hook_civicrm_navigationMenu().
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

  if ($objectName === 'Membership' && $op == 'create') {
    $membershipPreHook = new CRM_MembershipExtras_Hook_Pre_MembershipCreate($params);
    $membershipPreHook->preProcess();
  }

  if ($objectName === 'Membership' && $op == 'edit') {
    $membershipPreHook = new CRM_MembershipExtras_Hook_Pre_MembershipEdit($id, $params, $contributionID);
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

  if ($objectName === 'Contribution') {
    $contributionPreHook = new CRM_MembershipExtras_Hook_Pre_Contribution($op, $id, $params);
    $contributionPreHook->preProcess();
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

function membershipextras_civicrm_preSave_civicrm_contribution($dao) {
  if (!empty($dao->id)) {
    $membershipPreSaveHook = new CRM_MembershipExtras_Hook_PreSave_Membership();
    $membershipPreSaveHook->setContributionId($dao->id);
  }
}

function membershipextras_civicrm_preSave_civicrm_membership($dao) {
  if (!empty($dao->id) && !empty($dao->status_id)) {
    $membershipPreSaveHook = new CRM_MembershipExtras_Hook_PreSave_Membership($dao);
    $membershipPreSaveHook->preventCancellationOnInstallmentCancellation();
  }
}

/**
 * Implements hook_civicrm_post()
 */
function membershipextras_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName === 'EntityFinancialTrxn') {
    $entityFinancialTrxnHook = new CRM_MembershipExtras_Hook_Post_EntityFinancialTrxn($objectRef);
    $entityFinancialTrxnHook->updatePaymentPlanStatus();
  }

  if ($objectName === 'ContributionRecur') {
    $contributionRecurPostHook = new CRM_MembershipExtras_Hook_Post_ContributionRecur($objectRef);
    $contributionRecurPostHook->postProcess();
  }

  if ($objectName == 'MembershipPayment') {
    $membershipPaymentPostHook = new CRM_MembershipExtras_Hook_Post_MembershipPayment($op, $objectId, $objectRef);
    $membershipPaymentPostHook->postProcess();
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

  if ($formName === 'CRM_Member_Form_MembershipType') {
    $membershipTypeHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateMembershipTypeColour($form);
    $membershipTypeHook->process();
  }
}

/**
 * Implements hook_civicrm_buildForm()
 */
function membershipextras_civicrm_buildForm($formName, &$form) {
  if ($formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::UPDATE)) {
    $offlineAutoRenew = new CRM_MembershipExtras_Hook_BuildForm_MembershipEdit($form);
    $offlineAutoRenew->buildForm();
  }

  if (
    ($formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::ADD))
    || ($formName === 'CRM_Member_Form_MembershipRenewal' && ($form->getAction() & CRM_Core_Action::RENEW))
  ) {
    $offlineAutoRenew = new CRM_MembershipExtras_Hook_BuildForm_MembershipOfflineAutoRenew($form);
    $offlineAutoRenew->buildForm();

    $membershipHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan($form);
    $membershipHook->buildForm();

    $membershipAmountHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipAmountProRata($form);
    $membershipAmountHook->buildForm();
  }

  if ($formName === 'CRM_Member_Form_MembershipStatus') {
    $membershipStatusHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipStatus();
    $membershipStatusHook->buildForm($form);
  }

  if ($formName === 'CRM_Contribute_Form_UpdateSubscription') {
    $updateFormHook = new CRM_MembershipExtras_Hook_BuildForm_UpdateSubscription($form);
    $updateFormHook->buildForm();
  }

  if ($formName === 'CRM_Member_Form_MembershipType') {
    $membershipTypeHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipTypeColour($form);
    $membershipTypeHook->buildForm();
  }

  if ($formName === 'CRM_Contribute_Form_Contribution') {
    $membershipTypeHook = new CRM_MembershipExtras_Hook_BuildForm_ContributionEdit();
    $membershipTypeHook->buildForm();
  }
}

/**
 * Implements hrcore_civicrm_pageRun.
 *
 * @link https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_pageRun/
 */
function membershipextras_civicrm_pageRun($page) {
  $hooks = [
    new CRM_MembershipExtras_Hook_PageRun_MembershipTypePageColourUpdate(),
    new CRM_MembershipExtras_Hook_PageRun_MemberPageTabColourUpdate(),
    new CRM_MembershipExtras_Hook_PageRun_MemberPageDashboardColourUpdate()
  ];
  foreach ($hooks as $hook) {
    $hook->handle($page);
  }

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
}

/**
 * Implements hook_civicrm_validateForm()
 */
function membershipextras_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  $formAction = $form->getAction();
  $isNewMembershipForm = ($formName === 'CRM_Member_Form_Membership' && ($formAction & CRM_Core_Action::ADD));
  $isRenewMembershipForm = ($formName === 'CRM_Member_Form_MembershipRenewal' && ($formAction & CRM_Core_Action::RENEW));
  if ($isNewMembershipForm || $isRenewMembershipForm) {
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($contributionIsPaymentPlan) {
      $paymentPlanValidateHook = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($form, $fields, $errors);
      $paymentPlanValidateHook->validate();
    } else {
      $contributionValidateHook = new CRM_MembershipExtras_Hook_ValidateForm_MembershipContribution($form, $fields, $errors);
      $contributionValidateHook->validate();
    }
  }

  $isMembershipUpdateForm = $formName === 'CRM_Member_Form_Membership' && ($formAction & CRM_Core_Action::UPDATE);
  if ($isMembershipUpdateForm) {
    $membershipUpdateValidationHook = new CRM_MembershipExtras_Hook_ValidateForm_MembershipUpdate($form, $fields, $errors);
    $membershipUpdateValidationHook->validate();
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
    $memberTabPage  = new CRM_MembershipExtras_Hook_AlterContent_MemberTabPage($content);
    $memberTabPage->alterContent();
  }
}

/**
 * Implements hook_civicrm_entityTypes()
 */
function membershipextras_civicrm_entityTypes(&$entityTypes) {
  return _membershipextras_civix_civicrm_entityTypes($entityTypes);
}

function membershipextras_civicrm_preProcess($formName, $form) {
  if ($formName === 'CRM_Contribute_Form_ContributionView') {
    $preProcessor = new CRM_MembershipExtras_Hook_PreProcess_ContributionView($form);
    $preProcessor->preProcess();
  }
}

/**
 * Implements alterMailParams hook.
 *
 * @param array $params
 * @param $context
 */
function membershipextras_civicrm_alterMailParams(&$params, $context) {
  $alterMailParamsHook = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
  $alterMailParamsHook->handle();
}
