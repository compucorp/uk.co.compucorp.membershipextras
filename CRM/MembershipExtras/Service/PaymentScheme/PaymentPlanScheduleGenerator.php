<?php

class CRM_MembershipExtras_Service_PaymentScheme_PaymentPlanScheduleGenerator {

  private $contributionRecurId;

  private $scheduleGenerationRawData;

  public function __construct($contributionRecurId) {
    $this->contributionRecurId = $contributionRecurId;
  }

  /**
   * Generates a payment schedule for payment
   * scheme recurring contributions.
   *
   * @return array
   *   With the following values:
   *   'name' => In "'PP' + contact id + recurring contribution id" format.
   *   'currency' => the currency of the payment schedule, same as the recurring contribution currency.
   *   'total_amount' => the same of all instalment amounts.
   *   'instalments' => an array of array,where each sub-array contains 'charge_date'
   *     and 'amount', the 'amount' is coming from the recurring contribution amount, where
   *     the 'charge_date' is calculated based on "base_time, time_to_add" format. The
   *    "base_time" can be:
   *      A- a token in case it is surrounded by {},  for now we will only support 1 token which is {next_period_start_date}
   *         ,which is the max end date among all the memberships that are part of the payment plan + 1 day.
   *      B- Or anything that DateTime constructor accepts.
   *    And "time_to_add" is any string that DateTime::modify() accepts, which will be added
   *     to or subtracted from the "base_time"
   * @throws CRM_Extension_Exception
   */
  public function generateSchedule() {
    $this->scheduleGenerationRawData = $this->getScheduleGenerationRawData();
    if (empty($this->scheduleGenerationRawData)) {
      return [];
    }

    $instalmentsSchedule = $this->generateInstalmentsSchedule();
    $totalAmount = $this->calculateTotalAmount($instalmentsSchedule);

    $outputParams = [
      'name' => 'PP' . '-' . $this->scheduleGenerationRawData['contact_id'] . '-' . $this->scheduleGenerationRawData['contribution_recur_id'],
      'currency' => $this->scheduleGenerationRawData['currency'],
      'instalments' => $instalmentsSchedule,
      'total_amount' => $totalAmount,
    ];

    return $outputParams;
  }

  /**
   * Gets all the data needed to generate
   * the payment schedule.
   *
   * @return array
   */
  private function getScheduleGenerationRawData() {
    $query = '
      SELECT
      cr.id as contribution_recur_id, cr.contact_id, cr.currency, cr.amount as instalment_amount, ps.parameters as payment_scheme_parameters
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_value_payment_plan_extra_attributes ppea ON cr.id = ppea.entity_id
      INNER JOIN membershipextras_payment_scheme ps ON ppea.payment_scheme_id = ps.id
      WHERE cr.id = %1
      LIMIT 1
    ';
    $result = CRM_Core_DAO::executeQuery($query, [
      1 => [$this->contributionRecurId, 'Integer'],
    ]);

    if (!$result->fetch()) {
      throw new CRM_Extension_Exception('The recurring contribution used does not exists, or is not linked to a payment scheme.');
    }

    $result = $result->toArray();

    $result['max_membership_end_date'] = $this->getMaxMembershipEndDate();

    return $result;
  }

  /**
   * Gets the max end date of all memberships that
   * are part of the payment plan, and adds one day to it
   * which is the time of next membership renewal start
   * date.
   *
   * @return mixed|null
   */
  private function getMaxMembershipEndDate() {
    $query = '
      SELECT
      max(m.end_date) as max_membership_end_date
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_contribution c ON cr.id = c.contribution_recur_id
      INNER JOIN civicrm_membership_payment cm ON c.id = cm.contribution_id
      INNER JOIN civicrm_membership m ON cm.membership_id = m.id
      WHERE cr.id = %1
      LIMIT 1
    ';
    $result = CRM_Core_DAO::executeQuery($query, [
      1 => [$this->contributionRecurId, 'Integer'],
    ]);

    if (!$result->fetch()) {
      return NULL;
    }

    $result = $result->toArray();
    return $result['max_membership_end_date'];
  }

  /**
   * Generates the 'instalments' part of
   * instalments schedule.
   *
   * @return array|NULL
   */
  private function generateInstalmentsSchedule() {
    $paymentSchemeParameters = $this->scheduleGenerationRawData['payment_scheme_parameters'];
    $decodedPaymentSchemeParameters = json_decode($paymentSchemeParameters, TRUE);
    if (empty($decodedPaymentSchemeParameters)) {
      return NULL;
    }

    $result = [];
    $instalmentsConfigs = $decodedPaymentSchemeParameters['instalments'];
    foreach ($instalmentsConfigs as $instalmentConfigs) {
      $instalmentChargeDateConfig = explode(',', $instalmentConfigs['charge_date']);
      $baseTime = $instalmentChargeDateConfig[0];
      $timeToAdd = $instalmentChargeDateConfig[1] ?? '+0';
      $result[] = [
        'charge_date' => $this->calculateInstalmentChargeDate($baseTime, $timeToAdd),
        'amount' => $this->scheduleGenerationRawData['instalment_amount'],
      ];
    }

    return $result;
  }

  private function calculateInstalmentChargeDate($baseTime = 'now', $timeToAdd = '') {
    if (stripos($baseTime, '{next_period_start_date}') !== FALSE) {
      $membershipEndDate = $this->scheduleGenerationRawData['max_membership_end_date'];
      $baseChargeDate = new DateTime($membershipEndDate);
      $baseChargeDate->modify('+1 day');
      $baseChargeDate = $baseChargeDate->format('Y-m-d');
    }
    elseif (stripos($baseTime, '{next_period_year}') !== FALSE) {
      $membershipEndDate = new DateTime($this->scheduleGenerationRawData['max_membership_end_date']);
      $membershipEndDateYear = $membershipEndDate->format('Y');

      $baseChargeDate = str_replace('{next_period_year}', $membershipEndDateYear, $baseTime);
    }
    else {
      $baseChargeDate = $baseTime;
    }

    $chargeDate = new DateTime($baseChargeDate);
    $chargeDate->modify($timeToAdd);
    return $chargeDate->format('Y-m-d');
  }

  private function calculateTotalAmount($instalmentsSchedule) {
    $instalmentAmounts = array_column($instalmentsSchedule, 'amount');
    return array_sum($instalmentAmounts);
  }

}
