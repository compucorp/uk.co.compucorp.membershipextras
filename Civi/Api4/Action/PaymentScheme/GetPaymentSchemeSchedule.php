<?php

namespace Civi\Api4\Action\PaymentScheme;

use Civi\Api4\Generic\Result;
use CRM_MembershipExtras_Service_PaymentScheme_PaymentPlanScheduleGenerator as PaymentPlanScheduleGenerator;

/**
 *
 * @see \Civi\Api4\Generic\AbstractAction
 *
 * @package Civi\Api4\Action\PaymentScheme
 */
class GetPaymentSchemeSchedule extends \Civi\Api4\Generic\AbstractAction {

  /**
   * @var int
   * @required
   */
  protected $contributionRecurId;

  /**
   * @inheritDoc
   *
   * @param \Civi\Api4\Generic\Result $result
   */
  public function _run(Result $result) {
    $paymentPlanScheduleGenerator = new PaymentPlanScheduleGenerator($this->contributionRecurId);
    $result[] = $paymentPlanScheduleGenerator->generateSchedule();
  }

  /**
   * @inheritDoc
   *
   * @return array
   */
  public static function fields() {
    return [
      ['name' => 'contributionRecurId', 'data_type' => 'Integer'],
    ];
  }

}
