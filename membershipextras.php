<?php

require_once 'membershipextras.civix.php';

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembersOnlyEvent_BAO_MembersOnlyEvent as MembersOnlyEvent;
use CRM_MembersOnlyEvent_BAO_EventMembershipType as EventMembershipType;
use CRM_MembersOnlyEvent_Configurations as Configurations;

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
  _membershipextras_membersonlyevent_add_configurations_menu($menu);
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

  if ($formName === 'CRM_Member_Form_MembershipType') {
    $membershipTypeHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipTypeColour($form);
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

  $f = '_' . __FUNCTION__ . '_' . get_class($page);
  if (function_exists($f)) {
    $f($page);
  }
}

/**
 * Implementation of hook_civicrm_preProcess
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess/
 *
 * Handler for preProcess hook.
 */
function membershipextras_civicrm_preProcess($formName, &$form) {
  $f = '_' . __FUNCTION__ . '_' . $formName;
  if (function_exists($f)) {
    $f($form);
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

/**
 * Implements hook_civicrm_permission().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_permission
 */
function membershipextras_civicrm_permission(&$permissions) {
  $prefix = ts('Members-Only Event') . ': ';
  $permissions['members only event registration'] = $prefix . ts('Can register for members-only events irrespective of membership status');
}

/**
 * Implements hook_civicrm_tabset().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_tabset/
 */
function membershipextras_civicrm_tabset($tabsetName, &$tabs, $context) {
  // check if the tabset is 'Manage Event' page
  if ($tabsetName == 'civicrm/event/manage') {
    if (empty($context['event_id'])) {
      return;
    }

    $eventID = $context['event_id'];
    $url = CRM_Utils_System::url(
      'civicrm/event/manage/membersonlyevent',
      'reset=1&id=' . $eventID . '&action=update&component=event');

    $tab['membersonlyevent'] = array(
      'title' => ts('Members only event settings'),
      'link' => $url,
      'valid' => _membershipextras_membersonlyevent_is_tab_valid($eventID),
      'active' => TRUE,
      'current' => FALSE,
      'class' => 'ajaxForm',
    );

    //Insert this tab into position 4 (after `Online Registration` tab)
    $tabs = array_merge(
      array_slice($tabs, 0, 4),
      $tab,
      array_slice($tabs, 4)
    );
  }
}

/* Helper functions for membersonlyevent */

/**
 * Checks if the members-only settings tab
 * should be valid or not. Currently it is valid
 * only if the event is members-only event and
 * online registration is enabled.
 *
 * @param int $eventID
 *
 * @return bool
 *
 */
function _membershipextras_membersonlyevent_is_tab_valid($eventID) {
  $isOnlineRegistrationEnabled = FALSE;
  $event = civicrm_api3('Event', 'get', array(
    'sequential' => 1,
    'return' => array('is_online_registration'),
    'id' => $eventID,
  ));
  if (!empty($event['values'][0]['is_online_registration'])) {
    $isOnlineRegistrationEnabled = TRUE;
  }

  $membersOnlyEvent = MembersOnlyEvent::getMembersOnlyEvent($eventID);

  if ($isOnlineRegistrationEnabled && $membersOnlyEvent) {
    return TRUE;
  }

  return FALSE;
}

/**
 * Callback for event registration page
 *
 * Hence that users are supposed to register for events
 * from the info page, so in case the user tired to access
 * the registration page directly we will just redirect him
 * to the main page instead of showing any error or buttons to
 * login or buy membership.
 *
 * @param $form
 */
function _membershipextras_civicrm_preProcess_CRM_Event_Form_Registration_Register(&$form) {
  $eventID = $form->_eventId;
  $userHasEventAccess = _membershipextras_membersonlyevent_user_has_event_access($eventID);
  if (!$userHasEventAccess) {
    // if the user has no access, redirect to the main page
    CRM_Utils_System::redirect('/');
  }
  if(_membershipextras_membersonly_is_event_for_members_only($eventID)) {
    $cid = CRM_Utils_Request::retrieve('cid', 'Positive');
    CRM_Core_Resources::singleton()->addStyle('.crm-not-you-message { display: none; }');
    if (isset($cid)) {
      CRM_Core_Session::setStatus('You have already registered for this event! You cannot register other users.');
      $id = CRM_Utils_Request::retrieve('id', 'Positive');
      $params = 'id='.$id;
      if($reset = CRM_Utils_Request::retrieve('reset', 'Positive')) {
        $params .= '&reset='.$reset;
      }
      $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), $params);
      CRM_Utils_System::redirect($url);
      $form->_skipDupeRegistrationCheck = TRUE;
    }
  }
}

/**
 * Checks if the logged-in user has
 * an access to the specified event or not.
 *
 * @param int $eventID
 *
 * @return bool
 *   True if has access or False otherwise
 */
function _membershipextras_membersonlyevent_user_has_event_access($eventID) {
  $membersOnlyEvent = MembersOnlyEvent::getMembersOnlyEvent($eventID);
  if (!$membersOnlyEvent) {
    // the member is not a members-only event so nothing to check
    return TRUE;
  }

  $contactID = CRM_Core_Session::getLoggedInContactID();
  if (!$contactID) {
    // the user is not logged-in so he cannot access the event
    return FALSE;
  }

  if(CRM_Core_Permission::check('members only event registration')){
    // any user with 'members only event registration' permission
    // can access any members-only event.
    return TRUE;
  }

  $contactActiveAllowedMemberships = _membershipextras_membersonlyevent_get_contact_active_allowed_memberships($membersOnlyEvent->id, $contactID);

  if (!$contactActiveAllowedMemberships) {
    // the users does not have any active membership
    // so he cannot access the event.
    return FALSE;
  }

  return _membershipextras_membersonlyevent_is_memberships_duration_valid_during_event($eventID, $contactActiveAllowedMemberships);
}

/**
 * Checks if any of a list of allowed an active memberships
 * is valid (active) during the  period of
 * the specified event in case 'membership duration check'
 * is enabled.
 * The membership is valid during an event if :
 * 1- The membership end date is empty.
 * 2- The event start date is empty.
 * 3- The membership end data is > the event start date.
 *
 * @param int $eventID
 * @param array $activeAllowedMemberships
 *   A list of allowed and active memberships to be checked
 *   if they are valid during the specified event period.
 *
 * @return bool
 *   True if there is any valid membership during the event period
 *   or false otherwise.
 */
function _membershipextras_membersonlyevent_is_memberships_duration_valid_during_event($eventID, $activeAllowedMemberships) {
  $configs = Configurations::get();
  if (empty($configs['membership_duration_check'])) {
    // the 'membership duration check' is not enabled
    // so the user should be able to access the event.
    return TRUE;
  }

  $eventStartDate = _membershipextras_membersonlyevent_get_event_start_date($eventID);
  foreach($activeAllowedMemberships as $membership) {
    $membershipEndDate = !(empty($membership['end_date'])) ? $membership['end_date'] : '';
    if (empty($membershipEndDate) || empty($eventStartDate) || ($membershipEndDate >= $eventStartDate)) {
      // the user has an active allowed membership for this event
      // so the user should be able to access the event.
      return TRUE;
    }
  }

  // since 'membership duration check' is enabled but
  // the user does not have any active allowed membership
  // for this event so he will not be able to access the event.
  return FALSE;
}

/**
 * Gets the event start date in Y-m-d format if exist.
 *
 * @param $eventID
 *
 * @return string
 *   Event start date in y-md format
 *   or empty string if no start date exist.
 */
function _membershipextras_membersonlyevent_get_event_start_date($eventID) {
  $eventStartDate = '';

  $eventInfo = civicrm_api3('event', 'get',
    array('id' => $eventID, 'return' => array('start_date'), 'sequential' => 1))['values'][0];

  if (!empty($eventInfo['start_date'])) {
    $date = new DateTime($eventInfo['start_date']);
    $eventStartDate = $date->format('Y-m-d');
  }

  return $eventStartDate;
}

/**
 * Gets the memberships for the specified
 * contact in case he has any active membership
 * with a membership type allowed to access the
 * provided members-only event.
 *
 * @param int $membersOnlyEventID
 * @param int $contactID
 *
 * @return array
 *   List of contact Memberships or empty array if nothing found
 */
function _membershipextras_membersonlyevent_get_contact_active_allowed_memberships($membersOnlyEventID, $contactID) {
  $params = array(
    'sequential' => 1,
    'contact_id' => $contactID,
    'active_only' => 1,
  );

  $allowedMembershipTypes = EventMembershipType::getAllowedMembershipTypesIDs($membersOnlyEventID);
  if (!empty($allowedMembershipTypes)) {
    $params['membership_type_id'] = array('IN' => $allowedMembershipTypes);
  }

  $contactActiveMemberships = civicrm_api3('Membership', 'get', $params);

  if ($contactActiveMemberships['count']) {
    return $contactActiveMemberships['values'];
  }

  return array();
}

/**
 * Checks if the logged-in user has
 * an access to the specified event or not.
 *
 * @param $eventID
 * @return CRM_MembersOnlyEvent_DAO_MembersOnlyEvent|FALSE
 */
function _membershipextras_membersonly_is_event_for_members_only($eventID) {
  return MembersOnlyEvent::getMembersOnlyEvent($eventID);
}

/**
 * Callback for event info page
 */
function _membershipextras_civicrm_pageRun_CRM_Event_Page_EventInfo(&$page) {
  $eventID = $page->_id;

  if(_membershipextras_membersonly_is_event_for_members_only($eventID)) {
    $session = CRM_Core_Session::singleton();
    $statusMessages = $session->get('status');
    foreach ($statusMessages as $k => $msg) {
      if (strpos($msg['text'], 'register another participant')) {
        $statusMessages[$k]['text'] = ts("It looks like you are already registered for this event. If you want to change your registration, or you feel that you've gotten this message in error, please contact the site administrator.");
      }
    }
    $session->set('status', $statusMessages);
  }

  $userHasEventAccess = _membershipextras_membersonlyevent_user_has_event_access($eventID);
  if ($userHasEventAccess) {
    // skip early and show the page if the user has access to the members-only event.
    return;
  }

  _membershipextras_membersonlyevent_hide_event_info_page_register_button();

  $userLoggedIn = CRM_Core_Session::getLoggedInContactID();
  if (!$userLoggedIn) {
    _membershipextras_membersonlyevent_handle_access_denied_for_guest_users();
  }
  else {
    _membershipextras_membersonlyevent_handle_access_denied_for_logged_users($eventID);
  }
  CRM_Core_Session::setStatus('You have already registered for this event!');
}

/**
 * Handles the case when the logged-in
 * user does not have permission to access
 * the event info page.
 *
 * @param int $eventID
 */
function _membershipextras_membersonlyevent_handle_access_denied_for_logged_users($eventID) {
  $membersOnlyEvent = MembersOnlyEvent::getMembersOnlyEvent($eventID);
  if ($membersOnlyEvent->purchase_membership_button) {
    _membershipextras_membersonlyevent_add_membership_purchase_button_to_event_info_page($membersOnlyEvent);
  }
  else {
    // Purchase membership button is disabled, so we will just show the configured notice message
    CRM_Core_Session::setStatus($membersOnlyEvent->notice_for_access_denied);
  }
}

/**
 * Adds membership purchase button based
 * on the members-only event configurations to
 * the header and the footer of the event info page.
 *
 * @param MembersOnlyEvent $membersOnlyEvent
 */
function _membershipextras_membersonlyevent_add_membership_purchase_button_to_event_info_page($membersOnlyEvent) {
  switch ($membersOnlyEvent->purchase_membership_link_type) {
    case MembersOnlyEvent::LINK_TYPE_CONTRIBUTION_PAGE:
      $contributionPageID = $membersOnlyEvent->contribution_page_id;
      $path = 'civicrm/contribute/transact';
      $params = 'reset=1&id=' . $contributionPageID;
      $membershipPurchaseURL = CRM_Utils_System::url($path, $params);
      break;
    case MembersOnlyEvent::LINK_TYPE_URL:
    Default:
      $membershipPurchaseURL = $membersOnlyEvent->purchase_membership_url;
      break;
  }

  $buttonText = $membersOnlyEvent->purchase_membership_button_label;

  _membershipextras_membersonlyevent_add_action_button_to_event_info_page($membershipPurchaseURL, $buttonText);
}

/**
 * Handles the case when the guest
 * user does not have permission to access
 * the event info page.
 *
 * @param int $eventID
 */
function _membershipextras_membersonlyevent_handle_access_denied_for_guest_users() {
  $loginURL = CRM_Core_Config::singleton()->userSystem->getLoginURL();
  _membershipextras_membersonlyevent_add_action_button_to_event_info_page($loginURL, 'Login to register');
}

/**
 * Adds a button with the specified
 * url and text to the header and the footer
 * of the event info page.
 */
function _membershipextras_membersonlyevent_add_action_button_to_event_info_page($url, $buttonText) {
  $buttonToAdd = array(
    'template' => 'CRM/Event/Page/members-event-button.tpl',
    'button_text' => ts($buttonText),
    'position' => 'top',
    'url' => $url,
    'weight' => -10,
  );

  CRM_Core_Region::instance('event-page-eventinfo-actionlinks-top')->add($buttonToAdd);

  $buttonToAdd['position'] = 'bottom';
  CRM_Core_Region::instance('event-page-eventinfo-actionlinks-bottom')->add($buttonToAdd);
}

/**
 * Hides the event info page action links which contain
 * the event register link.
 */
function _membershipextras_membersonlyevent_hide_event_info_page_register_button() {
  CRM_Core_Region::instance('event-page-eventinfo-actionlinks-top')->update('default', array(
    'disabled' => TRUE,
  ));
  CRM_Core_Region::instance('event-page-eventinfo-actionlinks-bottom')->update('default', array(
    'disabled' => TRUE,
  ));
}

/**
 * Adds `Members-Only Event Extension Configurations` menu
 * item under `Administer` top-level menu item.
 *
 * @param $params
 */
function _membershipextras_membersonlyevent_add_configurations_menu(&$params) {
  $administerMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Administer', 'id', 'name');
  if ($administerMenuId) {
    // get the maximum key under administer menu
    $maxAdminMenuKey = max(array_keys($params[$administerMenuId]['child']));
    $nextAdminMenuKey = $maxAdminMenuKey+1;
    $params[$administerMenuId]['child'][$nextAdminMenuKey] =  array(
      'attributes' => array(
        'label' => ts('Members-Only Event Extension Configurations'),
        'name' => 'membersonlyevent_configurations',
        'url' => 'civicrm/admin/membersonlyevent',
        'permission' => 'administer CiviCRM,access CiviEvent',
        'operator' => null,
        'separator' => 1,
        'parentID' => $administerMenuId,
        'navID' => $nextAdminMenuKey,
        'active' => 1
      ),
      'child' => null
    );
  }
}

