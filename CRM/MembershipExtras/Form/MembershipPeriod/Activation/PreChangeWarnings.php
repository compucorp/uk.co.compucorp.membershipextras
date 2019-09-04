<?php

use CRM_MembershipExtras_Service_ContributionUtilities as ContributionUtilities;

class CRM_MembershipExtras_Form_MembershipPeriod_Activation_PreChangeWarnings {

  public static function checkForQuickAction($period, $newActiveStatus) {
    return self::getValidationMessage($period, $newActiveStatus);
  }

  public static function checkForEditForm() {
    $periodId = CRM_Utils_Request::retrieve('id', 'Integer');
    $newActiveStatus = CRM_Utils_Request::retrieve('is_active', 'Integer');

    $period = CRM_MembershipExtras_BAO_MembershipPeriod::getMembershipPeriodById($periodId);

    $message['content'] = '<p><strong>';
    $message['content'] .= self::getValidationMessage($period, $newActiveStatus);
    $message['content'] .= '</strong></p>';
    CRM_Utils_JSON::output($message);
  }

  private static function getValidationMessage($period, $newActiveStatus) {
    $message = '';
    $currentPeriodStatus = $period->is_active;

    if ($newActiveStatus && !$currentPeriodStatus) {
      $message = self::getActivationMessage($period);
    }

    if (!$newActiveStatus && $currentPeriodStatus) {
      $message = self::getDeactivationMessage($period);
    }

    return $message;
  }

  public static function getActivationMessage($period) {
    $config = CRM_Core_Config::singleton();
    $startDate = CRM_Utils_Date::customFormat($period->start_date, $config->dateformatFull);
    $endDate = CRM_Utils_Date::customFormat($period->end_date, $config->dateformatFull);

    if (self::isPaymentStarted($period)) {
      return "Would you like to activate membership period {$startDate} to
        {$endDate} ?";
    }

    return "Membership period {$startDate} to {$endDate} does not
        have any fulfilled payment. Would you still like to activate it?";
  }

  public static function getDeactivationMessage($period) {
    $config = CRM_Core_Config::singleton();
    $startDate = CRM_Utils_Date::customFormat($period->start_date, $config->dateformatFull);
    $endDate = CRM_Utils_Date::customFormat($period->end_date, $config->dateformatFull);

    if (self::isPaymentStarted($period)) {
      return "Membership period {$startDate} to {$endDate}
        has a payment in progress or is already paid. Would you still like to
        deactivate it?";
    }

    return "Would you like to deactivate membership period {$startDate}
        to {$endDate}? ";
  }

  /**
   * Obtains payment entity status.
   *
   * @param \CRM_MembershipExtras_BAO_MembershipPeriod $period
   *
   * @return string
   */
  private static function isPaymentStarted(CRM_MembershipExtras_BAO_MembershipPeriod $period) {
    $status = self::getPaymentEntityStatus($period->payment_entity_table, $period->entity_id);
    $contributionStatusesValueMap = ContributionUtilities::getStatusesValueMap();
    $statusName = CRM_Utils_Array::value($status, $contributionStatusesValueMap, '');

    switch ($statusName) {
      case 'Completed':
      case 'In Progress':
      case 'Partially paid':
        $isPaymentStarted = TRUE;
        break;

      default:
        $isPaymentStarted = FALSE;
    }

    return $isPaymentStarted;
  }

  /**
   * Obtains the status of the payment entity associated to the given period.
   *
   * @param string $entityTable
   * @param int $entityID
   *
   * @return string
   */
  private static function getPaymentEntityStatus($entityTable, $entityID) {
    $entity = $entityTable === 'civicrm_contribution_recur' ? 'ContributionRecur' : 'Contribution';

    try {
      $paymentEntity = civicrm_api3($entity, 'getsingle', [
        'id' => $entityID,
      ]);
    } catch (Exception $e) {
      return '';
    }

    return $paymentEntity['contribution_status_id'];
  }

}
