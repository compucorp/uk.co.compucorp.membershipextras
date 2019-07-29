<?php

class CRM_MembershipExtras_BAO_MembershipPeriod extends CRM_MembershipExtras_DAO_MembershipPeriod {

  /**
   * Create a new MembershipPeriod based on array-data
   *
   * @param array $params key-value pairs
   *
   * @return \CRM_MembershipExtras_DAO_MembershipPeriod
   * @throws \CRM_Core_Exception
   */
  public static function create($params) {
    $hook = empty($params['id']) ? 'create' : 'edit';

    self::validateOverlapWithOtherActivePeriods($params);

    CRM_Utils_Hook::pre($hook, 'MembershipPeriod', CRM_Utils_Array::value('id', $params), $params);

    $membershipPeriod = new CRM_MembershipExtras_DAO_MembershipPeriod();
    $membershipPeriod->copyValues($params);
    $membershipPeriod->save();

    CRM_Utils_Hook::post($hook, 'MembershipPeriod', $membershipPeriod->id, $membershipPeriod);

    return $membershipPeriod;
  }

  /**
   * Creates a new period for the given membership, using the membership's
   * current state.
   *
   * @param int $membershipID
   *
   * @return \CRM_MembershipExtras_DAO_MembershipPeriod|NULL
   */
  public static function createPeriodForMembership($membershipID) {
    $membership = civicrm_api3('Membership', 'getsingle', [
      'id' => $membershipID,
    ]);
    $lastActivePeriod = self::getLastActivePeriod($membershipID);
    $lastPayment = self::getMembershipLastContribution($membershipID);

    $paymentEntityTable = NULL;
    $entityId = NULL;
    if (!empty($lastPayment)) {
      $paymentEntityTable = self::calculateEntityTableForContribution($lastPayment);
      $entityId = self::calculateEntityIDForContribution($lastPayment);
    }

    return self::create([
      'membership_id' => $membershipID,
      'start_date' => self::calculateStartDate($membership, $lastActivePeriod),
      'end_date' => CRM_Utils_Array::value('end_date', $membership),
      'payment_entity_table' => $paymentEntityTable,
      'entity_id' => $entityId,
      'is_active' => TRUE,
    ]);
  }

  public static function getMembershipPeriodById($periodId) {
    $membershipPeriod = new self();
    $membershipPeriod->id = $periodId;
    if ($membershipPeriod->find(TRUE) > 0) {
      return $membershipPeriod;
    }

    return NULL;
  }

  /**
   * Returns an array with the information for the first active period for the
   * given membership.
   *
   * @param int $membershipID
   *
   * @return array
   */
  public static function getFirstActivePeriod($membershipID) {
    $membershipPeriod = new self();
    $membershipPeriod->membership_id = $membershipID;
    $membershipPeriod->is_active = TRUE;
    $membershipPeriod->orderBy('end_date ASC');
    $membershipPeriod->limit(1);
    if ($membershipPeriod->find(TRUE) > 0) {
      return $membershipPeriod->toArray();
    }

    return [];
  }

  /**
   * Returns an array with the information for the latest active period for the
   * given membership.
   *
   * @param int $membershipID
   *
   * @return array
   */
  public static function getLastActivePeriod($membershipID) {
    $membershipPeriod = new self();
    $membershipPeriod->membership_id = $membershipID;
    $membershipPeriod->is_active = TRUE;
    $membershipPeriod->orderBy('end_date DESC');
    $membershipPeriod->limit(1);
    if ($membershipPeriod->find(TRUE) > 0) {
      return $membershipPeriod->toArray();
    }

    return [];
  }

  /**
   * Returns an array with the information for the latest period for the
   * given membership whether its active or not.
   *
   * @param int $membershipID
   *
   * @return array
   */
  public static function getLastPeriod($membershipID) {
    $membershipPeriod = new self();
    $membershipPeriod->membership_id = $membershipID;
    $membershipPeriod->orderBy('end_date DESC,id DESC');
    $membershipPeriod->limit(1);
    if ($membershipPeriod->find(TRUE) > 0) {
      return $membershipPeriod->toArray();
    }

    return [];
  }

  /**
   * Returns an array with the data of the last contribution made as payment for
   * the given membership ID.
   *
   * @param int $membershipID
   *
   * @return array
   */
  private static function getMembershipLastContribution($membershipID) {
    $result = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'membership_id' => $membershipID,
      'api.Contribution.get' => ['id' => '$value.contribution_id'],
      'options' => [
        'sort' => 'contribution_id DESC',
        'limit' => 1,
      ],
    ]);

    if ($result['count'] > 0) {
      return $result['values'][0]['api.Contribution.get']['values'][0];
    }

    return [];
  }

  /**
   * Calculates the start date of a new period, given the last active period for
   * a membership.
   *
   * @param array $membership
   * @param array $lastActivePeriod
   *
   * @return string
   */
  private static function calculateStartDate($membership, $lastActivePeriod) {
    if (!isset($lastActivePeriod['end_date'])) {
      return $membership['start_date'];
    }

    $today = new DateTime();
    $endOfLastActivePeriod = new DateTime($lastActivePeriod['end_date']);
    $endOfLastActivePeriod->add(new DateInterval('P1D'));

    if ($endOfLastActivePeriod > $today) {
      return $endOfLastActivePeriod->format('Y-m-d');
    }

    return $today->format('Y-m-d');
  }

  /**
   * Given a contribution, determines if either contribution or recurring
   * contribution table shoud be used as payment entity.
   *
   * @param array $contribution
   *
   * @return string
   */
  private static function calculateEntityTableForContribution($contribution) {
    return $contribution['contribution_recur_id']
      ? 'civicrm_contribution_recur'
      : 'civicrm_contribution';
  }

  /**
   * Returns the ID of the payment entity to be used, from tha data of the given
   * contribution.
   *
   * @param array $contribution
   *
   * @return int
   */
  private static function calculateEntityIDForContribution($contribution) {
    return $contribution['contribution_recur_id'] ?: $contribution['id'];
  }

  public static function getOrderedMembershipPeriods($membershipId) {
    $membershipPeriod = new self();
    $membershipPeriod->membership_id = $membershipId;
    $membershipPeriod->orderBy('start_date ASC');
    if ($membershipPeriod->find() > 0) {
      return $membershipPeriod;
    }

    return NULL;
  }

  /**
   * Updates the membership period as well as its
   * related membership dates in case the period
   * does not overlap with other active periods for the
   * same membership.
   *
   * @param $params
   *
   * @return CRM_MembershipExtras_DAO_MembershipPeriod
   *
   * @throws CRM_Core_Exception
   */
  public static function updatePeriod($params) {
    $transaction = new CRM_Core_Transaction();
    try {
      $membershipPeriod = self::create($params);
      $membershipPeriod->find(TRUE);
      self::updateMembershipDatesAndStatus($membershipPeriod);

      $transaction->commit();

      return $membershipPeriod;
    } catch (CRM_Core_Exception $exception) {
      $transaction->rollback();
      throw $exception;
    }
  }

  /**
   * Checks if the given period overlaps
   * with other active periods within the same membership.
   *
   * @param $periodParams
   *
   * @throws \CRM_Core_Exception
   */
  private static function validateOverlapWithOtherActivePeriods($periodParams) {
    $periodID = CRM_Utils_Array::value('id', $periodParams, 0);
    $membershipID = CRM_Utils_Array::value('membership_id', $periodParams, 0);
    $periodNewStartDate = CRM_Utils_Array::value('start_date', $periodParams, '');
    $periodNewEndDate = CRM_Utils_Array::value('end_date', $periodParams, '');
    $periodIsActive = CRM_Utils_Array::value('is_active', $periodParams, '');

    if (!empty($periodID)) {
      $membershipPeriod = self::getMembershipPeriodById($periodID);
      $membershipID = $membershipID ?: $membershipPeriod->membership_id;
      $periodNewStartDate = $periodNewStartDate ?: $membershipPeriod->start_date;
      $periodNewEndDate = $periodNewEndDate ?: $membershipPeriod->end_date;
      $periodIsActive = $periodIsActive ?: $membershipPeriod->is_active;
    }

    if (!$periodIsActive) {
      return;
    }

    if (empty($periodNewStartDate)) {
      throw new CRM_Core_Exception("Can't calculate period overlapping without period start date!");
    }

    $errors = [];
    $membershipPeriods = self::getOrderedMembershipPeriods($membershipID);

    $term = 0;
    while ($membershipPeriods->N && $membershipPeriods->fetch()) {
      $term ++;

      if ($periodID && $membershipPeriods->id == $periodID) {
        continue;
      }

      if (self::isStartDateOverlap($periodNewStartDate, $membershipPeriods)) {
        $errors[] = "The new start date of this membership period overlaps with another activated membership period (term {$term}). Please review your changes.";
      }

      if (self::isEndDateOverlap($periodNewStartDate, $periodNewEndDate, $membershipPeriods)) {
        $errors[] = "The new end date of this membership period overlaps with another activated membership period (term {$term}). Please review your changes.";
      }
    }

    if (count($errors) > 0) {
      $errors = implode(' ', $errors);
      throw new CRM_Core_Exception('Overlapping errors were found when updating period: ' . $errors);
    }
  }

  /**
   * Checks if given start date overlaps with the given period's end date.
   *
   * @param string $periodStartDate
   * @param \CRM_MembershipExtras_BAO_MembershipPeriod $evaluatedPeriod
   *
   * @return bool
   * @throws \Exception
   */
  private static function isStartDateOverlap(
    $periodStartDate,
    CRM_MembershipExtras_BAO_MembershipPeriod $evaluatedPeriod
  ) {
    $startDate = new DateTime($periodStartDate);
    $evaluatedPeriodStartDate = new DateTime($evaluatedPeriod->start_date);

    if (empty($evaluatedPeriod->end_date)) {
      return $startDate >= $evaluatedPeriodStartDate;
    }

    $evaluatedPeriodEndDate = new DateTime($evaluatedPeriod->end_date);

    return $startDate <= $evaluatedPeriodEndDate && $startDate >= $evaluatedPeriodStartDate;
  }

  /**
   * Checks if the given end date overlaps with the given period's start date.
   *
   * @param string $periodStartDate
   * @param string $periodEndDate
   * @param \CRM_MembershipExtras_BAO_MembershipPeriod $evaluatedPeriod
   *
   * @return bool
   * @throws \Exception
   */
  private static function isEndDateOverlap(
    $periodStartDate,
    $periodEndDate,
    CRM_MembershipExtras_BAO_MembershipPeriod $evaluatedPeriod
  ) {
    $startDate = new DateTime($periodStartDate);
    $evaluatedPeriodStartDate = new DateTime($evaluatedPeriod->start_date);

    if (empty($periodEndDate)) {
      return $evaluatedPeriodStartDate >= $startDate;
    }

    $endDate = new DateTime($periodEndDate);
    if (empty($evaluatedPeriod->end_date)) {
      return $endDate >= $evaluatedPeriodStartDate;
    }

    $evaluatedPeriodEndDate = new DateTime($evaluatedPeriod->end_date);

    return $endDate >= $evaluatedPeriodStartDate && $endDate <= $evaluatedPeriodEndDate;
  }

  private static function updateMembershipDatesAndStatus($membershipPeriod) {
    $membershipId = $membershipPeriod->membership_id;
    $firstActivePeriod = self::getFirstActivePeriod($membershipId);
    $lastActivePeriod = self::getLastActivePeriod($membershipId);

    $joinDate = CRM_Utils_Array::value('start_date', $firstActivePeriod, '');
    $endDate = CRM_Utils_Array::value('end_date', $lastActivePeriod, '');

    $params = [
      'id' => $membershipId,
      'skipStatusCal' => 0,
    ];

    if (!empty($joinDate)) {
      $params['join_date'] = $joinDate;
      $params['start_date'] = $joinDate;
    }

    if (!empty($endDate)) {
      $params['end_date'] = $endDate;
    }

    $isTheOnlyActivePeriodDeactivated = empty($firstActivePeriod)
      && $membershipPeriod->is_active == 0 && !self::isMembershipStatusOverridden($membershipId);
    if ($isTheOnlyActivePeriodDeactivated) {
      $params['status_id'] = 'Pending';
      $params['skipStatusCal'] = 1;
    }

    if ($joinDate || $endDate || $isTheOnlyActivePeriodDeactivated) {
      civicrm_api3('Membership', 'create', $params);
    }
  }

  private static function isMembershipStatusOverridden($membershipId) {
    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['is_override'],
      'id' => $membershipId,
    ]);

    if (empty($membership['values'][0]['is_override'])) {
      return FALSE;
    }

    return TRUE;
  }

  public static function deleteById($id) {
    $membershipPeriod = self::getMembershipPeriodById($id);
    $membershipId = $membershipPeriod->membership_id;

    if (!$membershipPeriod) {
      throw new CRM_Core_Exception('Membership period Id could not be found');
    }

    if (self::periodLinkedToPayment($membershipPeriod)) {
      throw new CRM_Core_Exception('This membership period is linked to a payment, it cannot be deleted.');
    }

    $isTheOnlyPeriodOfMembership = self::isTheOnlyPeriodOfMembership($id);

    if ($isTheOnlyPeriodOfMembership && self::membershipLinkedToPayment($membershipId)) {
      throw new CRM_Core_Exception('The membership associated with this period is linked to a payment, it cannot be deleted.');
    }

    $transaction = new CRM_Core_Transaction();
    $membershipPeriod->delete();

    if ($isTheOnlyPeriodOfMembership) {
      self::deleteMembership($membershipId);
    } else {
      self::updateMembershipDatesAndStatus($membershipPeriod);
    }

    $transaction->commit();
  }

  private static function periodLinkedToPayment($membershipPeriod) {
    if (!empty($membershipPeriod->entity_id)) {
      return TRUE;
    }

    return FALSE;
  }

  private static function membershipLinkedToPayment($membershipId) {
    $membershipPaymentsCount = civicrm_api3('MembershipPayment', 'getcount', [
      'membership_id' => $membershipId,
    ]);

    return $membershipPaymentsCount;
  }

  /**
   * Determines if the period is the only
   * period of the membership it is associated with.
   *
   * @param $periodId
   *
   * @return bool
   */
  public static function isTheOnlyPeriodOfMembership($periodId) {
    $membershipPeriod = self::getMembershipPeriodById($periodId);

    $periodsOfMembership = new self();
    $periodsOfMembership->membership_id = $membershipPeriod->membership_id;
    return $periodsOfMembership->find() === 1;
  }

  private static function deleteMembership($membershipId) {
    civicrm_api3('Membership', 'delete', [
      'id' => $membershipId,
    ]);
  }

  /**
   * Calculates the term number for the current period.
   */
  public function calculateTermNumber() {
    $membershipPeriodsResult = self::getOrderedMembershipPeriods($this->membership_id);

    $term = 1;
    while ($membershipPeriodsResult->N && $membershipPeriodsResult->fetch()) {
      if ($membershipPeriodsResult->id === $this->id) {
        return $term;
      }

      $term++;
    }
  }

}
