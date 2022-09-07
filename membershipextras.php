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
    'name' => 'payment_plan_settings',
    'label' => ts('Payment Plan Settings'),
    'url' => 'civicrm/admin/payment_plan_settings',
    'permission' => 'administer CiviCRM,administer MembershipExtras',
    'operator' => 'OR',
    'separator' => NULL,
  ];

  _membershipextras_civix_insert_navigation_menu($menu, 'Administer/CiviContribute', $paymentPlanSettingsMenuItem);

  $automatedMembershipUpgradeRulesMenuItem = [
    'name' => 'automated_membership_upgrade_rules',
    'label' => ts('Membership Automated Upgrade Rules'),
    'url' => 'civicrm/admin/member/automated-upgrade-rules?reset=1',
    'permission' => 'administer CiviCRM,administer MembershipExtras',
    'operator' => 'OR',
    'separator' => 2,
  ];
  _membershipextras_civix_insert_navigation_menu($menu, 'Administer/CiviMember', $automatedMembershipUpgradeRulesMenuItem);
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

  if ($objectName === 'Membership' && $op == 'edit') {
    $paymentType = Civi::$statics[E::LONG_NAME]['paymentType'] ?? '';
    $membershipPreHook = new CRM_MembershipExtras_Hook_Pre_MembershipEdit($id, $params, $contributionID, $paymentType);
    $membershipPreHook->preProcess();
  }

  static $isFirstPaymentPlanContribution = TRUE;
  $isPaymentPlanPayment = CRM_MembershipExtras_Helper_InstalmentSchedule::isPaymentPlanPayment();
  $isContributionCreation = ($objectName === 'Contribution' && $op === 'create');
  if ($isContributionCreation && $isPaymentPlanPayment && $isFirstPaymentPlanContribution) {
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_Contribution($params);
    $paymentPlanProcessor->createPaymentPlan();
    $paymentPlanProcessor->setContributionToPayLater();
    $isFirstPaymentPlanContribution = FALSE;
  }

  static $firstPaymentPlanContributionId;
  $lineItemContributionCreation = $objectName === 'LineItem' && $op === 'create' && !empty($params['contribution_id']);
  $firstPaymentPlanContributionLineItemCreation = ($lineItemContributionCreation && (empty($firstPaymentPlanContributionId) || $firstPaymentPlanContributionId == $params['contribution_id']));
  if ($firstPaymentPlanContributionLineItemCreation && $isPaymentPlanPayment) {
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItem($params);
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
 * Implements hook_civicrm_post().
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
 * Implements hook_civicrm_postProcess().
 */
function membershipextras_civicrm_postProcess($formName, &$form) {
  $isAddAction = $form->getAction() & CRM_Core_Action::ADD;
  $isRenewAction = $form->getAction() & CRM_Core_Action::RENEW;

  if (($formName === 'CRM_Member_Form_Membership' && $isAddAction) || ($formName === 'CRM_Member_Form_MembershipRenewal' && $isRenewAction)) {

    $contributionIsPaymentPlan =
      CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if (!$contributionIsPaymentPlan) {
      return;
    }

    $operation = $isAddAction ? 'creation' : 'renewal';
    $paymentPlanProcessor = new CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessor($formName, $form, $operation);
    $paymentPlanProcessor->postProcess();

    if ($formName == 'CRM_Member_Form_Membership') {
      $offlineAutoRenewProcessor = new CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor($form);
      $offlineAutoRenewProcessor->postProcess();
    }

  }

  if ($formName === 'CRM_Contribute_Form_UpdateSubscription') {
    $postProcessFormHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription($form);
    $postProcessFormHook->postProcess();
  }

  if ($formName === 'CRM_Member_Form_MembershipType') {
    $membershipTypeHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateMembershipTypeColour($form);
    $membershipTypeHook->process();

    $membershipTypeProRataCalculationHook = new CRM_MembershipExtras_Hook_PostProcess_MembershipTypeSetting($form);
    $membershipTypeProRataCalculationHook->process();
  }
}

/**
 * Implements hook_civicrm_buildForm().
 */
function membershipextras_civicrm_buildForm($formName, &$form) {
  if ($formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::UPDATE)) {
    $membershipEdit = new CRM_MembershipExtras_Hook_BuildForm_MembershipEdit($form);
    $membershipEdit->buildForm();
  }

  if (
    ($formName === 'CRM_Member_Form_Membership' && ($form->getAction() & CRM_Core_Action::ADD))
    || ($formName === 'CRM_Member_Form_MembershipRenewal' && ($form->getAction() & CRM_Core_Action::RENEW))
  ) {
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

  if ($formName === 'CRM_Member_Form_MembershipType') {
    $membershipTypeColourHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipType_Colour($form);
    $membershipTypeColourHook->buildForm();

    $membershipSettingsHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting($form);
    $membershipSettingsHook->buildForm();
  }

  if ($formName === 'CRM_Contribute_Form_Contribution') {
    $contributionEditHook = new CRM_MembershipExtras_Hook_BuildForm_ContributionEdit();
    $contributionEditHook->buildForm();
  }

  if ($formName == 'CRM_Contribute_Form_AdditionalPayment') {
    Civi::$statics[E::LONG_NAME]['paymentType'] = $form->getVar('_paymentType');
  }

  if ($formName === 'CRM_Price_Form_Option' || $formName === 'CRM_Price_Form_Field') {
    $priceFieldHook = new CRM_MembershipExtras_Hook_BuildForm_PriceOptionEdit($form);
    $priceFieldHook->buildForm();
  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * @link https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_pageRun/
 */
function membershipextras_civicrm_pageRun($page) {
  $hooks = [
    new CRM_MembershipExtras_Hook_PageRun_MembershipTypePageColourUpdate(),
    new CRM_MembershipExtras_Hook_PageRun_MemberPageTabColourUpdate(),
    new CRM_MembershipExtras_Hook_PageRun_MemberPageDashboardColourUpdate(),
    new CRM_MembershipExtras_Hook_PageRun_MemberPageTab(),
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

  _membershipextras_appendJSToModifyRecurringContributionPage($page);
}

/**
 * Implements hook_civicrm_validateForm().
 */
function membershipextras_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName === 'CRM_Member_Form_MembershipType') {
    $membershipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membershipType->validate();
  }

  $formAction = $form->getAction();
  $isNewMembershipForm = ($formName === 'CRM_Member_Form_Membership' && ($formAction & CRM_Core_Action::ADD));
  $isPaymentPlanPayment = CRM_MembershipExtras_Helper_InstalmentSchedule::isPaymentPlanPayment();
  if ($isNewMembershipForm && $isPaymentPlanPayment) {
    $paymentPlanValidateHook = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($form, $fields, $errors);
    $paymentPlanValidateHook->validate();
  }

  $isMembershipUpdateForm = $formName === 'CRM_Member_Form_Membership' && ($formAction & CRM_Core_Action::UPDATE);
  if ($isMembershipUpdateForm) {
    $membershipUpdateValidationHook = new CRM_MembershipExtras_Hook_ValidateForm_MembershipUpdate($form, $fields, $errors);
    $membershipUpdateValidationHook->validate();
  }

  $isUpdateSubcriptionForm = $formName === 'CRM_Contribute_Form_UpdateSubscription' && ($formAction & CRM_Core_Action::UPDATE);
  if ($isUpdateSubcriptionForm) {
    $subscriptionUpdateValidationHook = new CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscription($form, $fields, $errors);
    $subscriptionUpdateValidationHook->validate();
  }
}

/**
 * Implements hook_civicrm_alterCalculatedMembershipStatus().
 */
function membershipextras_civicrm_alterCalculatedMembershipStatus(&$calculatedStatus, $arguments, $membership) {
  $alterMembershipStatusHook = new CRM_MembershipExtras_Hook_Alter_CalculatedMembershipStatus();
  $alterMembershipStatusHook->alterMembershipStatus($calculatedStatus, $arguments, $membership);
}

/**
 * Implements hook_civicrm_links().
 */
function membershipextras_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  if ($op == 'contribution.selector.recurring' && $objectName == 'Contribution') {
    $recurContribuLinksHook = new CRM_MembershipExtras_Hook_Links_RecurringContribution($objectId, $links, $mask);
    $recurContribuLinksHook->alterLinks();
  }

  if ($op == 'contribution.selector.row' && $objectName == 'Contribution') {
    if (CRM_Core_Permission::check('edit contributions')) {
      $links[] = [
        'name' => 'Duplicate As New Pending Contribution',
        'url' => 'civicrm/contribution/duplicate-contribution',
        'qs' => 'reset=1&crid=%%id%%',
        'title' => 'Duplicate As New Pending Contribution',
      ];
    }
  }
}

/**
 * Implements hook_civicrm_alterContent().
 */
function membershipextras_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  if ($tplName == 'CRM/Member/Page/Tab.tpl') {
    $memberTabPage = new CRM_MembershipExtras_Hook_AlterContent_MemberTabPage($content);
    $memberTabPage->alterContent();
  }
}

/**
 * Implements hook_civicrm_entityTypes().
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
 * Implements alterMailParams hook().
 *
 * @param array $params
 * @param $context
 */
function membershipextras_civicrm_alterMailParams(&$params, $context) {
  $alterMailParamsHook = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
  $alterMailParamsHook->handle();
}

/**
 * Implements hook_civicrm_permission().
 *
 */
function membershipextras_civicrm_permission(&$permissions) {
  $permissions += [
    'administer MembershipExtras' => [
      E::ts('MembershipExtras: administer Membership Extras'),
      E::ts('Perform all Membership Extras administration tasks in CiviCRM'),
    ],
  ];
}

/**
 * Implements hook_civicrm_alterAPIPermissions().
 *
 */
function membershipextras_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  // PaymentSchedule API end-point actions are not dangerous, so
  // no need to check permissions.
  if ($entity == 'payment_schedule') {
    $params['check_permissions'] = FALSE;
  }

  if ($entity == 'contribution_recur_line_item') {
    $permissions['contribution_recur_line_item'][$action] = ['edit contributions'];
  }

  // PriceFieldValue API automatically does a join on PrieField
  // API, in which Civi does a permission check again without the context
  // parameter being passed down,thus resulting in permission failure for any
  // PriceFieldValue API call,thus we here store it in a static variable
  // to make sure it is clear that this extra API join is actually coming from
  // Membershipextras context.
  static $isMembershipextrasContext = FALSE;
  if (!empty($params['context']) && $params['context'] == 'Membershipextras') {
    $isMembershipextrasContext = TRUE;
  }

  // These are the APIs that we call from the Manage installments page JS file, except
  // 'PriceField' which is called from an automatic join that Civi does when you call 'PriceFieldValue' API.
  // These entities have no default permissions, thus Civi forces 'administer CiviCRM' permission on them if called
  // using AJAX, we here reduce their permissions to 'access CiviContribute' if they are called from Membershipextras context.
  // 'access CiviContribute' is used instead of `edit contributions' because the actions we perform on them from the manage installment
  // page are just 'get' operations, and not 'create' or 'delete'.
  $isManageInstallmentAPIs = in_array($entity, ['entity_financial_account', 'financial_account', 'financial_type', 'price_field', 'price_field_value']);
  $isManageInstallmentAPIsAllowedActions = in_array($action, ['get', 'getsingle']);
  if ($isMembershipextrasContext && $isManageInstallmentAPIs && $isManageInstallmentAPIsAllowedActions) {
    $permissions[$entity][$action] = ['access CiviContribute'];
  }
}

function _membershipextras_appendJSToModifyRecurringContributionPage(&$page) {
  if (!($page instanceof CRM_Contribute_Page_ContributionRecur)) {
    return;
  }

  $contributionData = $page->get_template_vars('recur');
  $frequency = CRM_Utils_Array::value('frequency_unit', $contributionData, '');

  CRM_Core_Resources::singleton()->addScriptFile(
    CRM_MembershipExtras_ExtensionUtil::LONG_NAME,
    'js/modifyAnnualRecuringContributionPage.js',
    1,
    'page-header'
  )->addVars(
    CRM_MembershipExtras_ExtensionUtil::SHORT_NAME,
    ['contribution_frequency' => $frequency]
  );
}

/**
 * Implements fieldOptions hook().
 *
 * @param string $entity
 * @param string $field
 * @param array $options
 * @param array $params
 */
function membershipextras_civicrm_fieldOptions($entity, $field, &$options, $params) {
  if (in_array($entity, ['FinancialItem', 'LineItem']) && $field == 'entity_table') {
    $options['civicrm_contribution_recur'] = ts('Recurring Contribution');
  }
}
