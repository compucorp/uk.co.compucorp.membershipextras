<?php

/**
 * Class CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate.
 *
 * Dispatches the hook to calculate instalment receive dates.
 */
class CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate {

  /**
   * Number of instalment in the payment plan.
   *
   * @var int
   */
  private $contributionNumber;

  /**
   * Receive date being used for the instalmen.
   *
   * @var string
   */
  private $receiveDate;

  /**
   * List of parameters being used to create the contribution.
   *
   * @var array
   */
  private $params;

  /**
   * CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate constructor.
   *
   * @param int $contributionNumber
   * @param string $receiveDate
   * @param array $params
   */
  public function __construct($contributionNumber, &$receiveDate, array &$params) {
    $this->contributionNumber = $contributionNumber;
    $this->receiveDate =& $receiveDate;
    $this->params =& $params;
  }

  /**
   * Dispatches the hook.
   */
  public function dispatch() {
    $nullObject = CRM_Utils_Hook::$_nullObject;
    CRM_Utils_Hook::singleton()->invoke(
      ['contributionNumber', 'receiveDate', 'contributionCreationParams'],
      $this->contributionNumber,
      $this->receiveDate,
      $this->params,
      $nullObject, $nullObject, $nullObject,
      'membershipextras_calculateContributionReceiveDate'
    );
  }

}
