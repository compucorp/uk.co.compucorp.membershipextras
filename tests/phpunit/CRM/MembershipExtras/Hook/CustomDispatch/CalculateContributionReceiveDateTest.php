<?php
use Civi\Test\HookInterface;

/**
 * Class CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDateTest.
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDateTest extends BaseHeadlessTest implements HookInterface {

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
   * Another date.
   *
   * @var string
   */
  private $newReceiveDate;

  /**
   * Another array of params.
   *
   * @var array
   */
  private $newParams;

  public function testHookDispatchInputPassThroughToImplementationsAndParameterModification() {
    $this->contributionNumber = 1;
    $this->receiveDate = '2020-01-01';
    $this->params = [1, 2, 3, 4];

    $this->newReceiveDate = '2021-12-31';
    $this->newParams = [5, 6, 7, 8];

    $dispatcher = new CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate(
      $this->contributionNumber,
      $this->receiveDate,
      $this->params
    );
    $dispatcher->dispatch();

    $this->assertEquals($this->newReceiveDate, $this->receiveDate);
    $this->assertEquals($this->newParams, $this->params);
  }

  /**
   * Implements calculateContributionReceiveDate hook for testing.
   *
   * @param $instalmentNumber
   * @param $receiveDate
   * @param $params
   */
  public function hook_membershipextras_calculateContributionReceiveDate($instalmentNumber, &$receiveDate, &$params) {
    $this->assertEquals($this->contributionNumber, $instalmentNumber);
    $this->assertEquals($this->receiveDate, $receiveDate);
    $this->assertEquals($this->params, $params);

    $receiveDate = $this->newReceiveDate;
    $params = $this->newParams;
  }

}
