<?php

class CRM_MembershipExtras_BAO_MembershipPeriod extends CRM_MembershipExtras_DAO_MembershipPeriod {

  /**
   * Create a new MembershipPeriod based on array-data
   *
   * @param array $params key-value pairs
   *
   * @return CRM_MembershipExtras_DAO_MembershipPeriod|NULL
   */
  public static function create($params) {
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, 'MembershipPeriod', CRM_Utils_Array::value('id', $params), $params);
    $instance = new CRM_MembershipExtras_DAO_MembershipPeriod();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, 'MembershipPeriod', $instance->id, $instance);

    return $instance;
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

    return self::create([
      'membership_id' => $membershipID,
      'start_date' => self::calculateStartDate($membership, $lastActivePeriod),
      'end_date' => $membership['end_date'],
      'payment_entity_table' => self::calculateEntityTableForContribution($lastPayment),
      'entity_id' => self::calculateEntityIDForContribution($lastPayment),
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
    $membershipPeriod->orderBy('end_date DESC');
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
      'api.Contribution.getsingle' => ['id' => '$value.contribution_id'],
      'options' => [
        'sort' => 'contribution_id DESC',
        'limit' => 1
      ],
    ]);

    if ($result['count'] > 0) {
      return $result['values'][0]['api.Contribution.getsingle'];
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
   * Updates the membership period as wel as its
   * related membership dates in case the period
   * does not overlap with other active periods for the
   * same membership.
   *
   * @param $params
   *
   * @throws CRM_Core_Exception
   */
  public static function updatePeriod($params) {
    if (self::doesOverlapWithOtherActivePeriods($params)) {
      throw new CRM_Core_Exception('The new end date of this membership period overlaps with
       another activated membership period. please review your changes');
    }

    $transaction = new CRM_Core_Transaction();
    try {
      $membershipPeriod = self::create($params);
      $membershipPeriod->find(TRUE);
      self::updateMembershipDates($membershipPeriod);
    }
    catch (CRM_Core_Exception $exception) {
      $transaction->rollback();
      throw $exception;
    }
    $transaction->commit();
  }

  /**
   * Checks if the given period overlaps
   * with other active periods within the same membership.
   *
   * @param $periodParams
   * @return bool
   */
  private static function doesOverlapWithOtherActivePeriods($periodParams) {
    $membershipPeriod = self::getMembershipPeriodById($periodParams['id']);

    $periodNewStartDate = $periodParams['start_date'];
    $periodNewEndDate = $periodParams['end_date'];

    $overlappedMembershipPeriods = new self();
    $overlappedMembershipPeriods->membership_id = $membershipPeriod->membership_id;
    $overlappedMembershipPeriods->is_active = TRUE;
    $overlappedMembershipPeriods->whereAdd('"' . $periodNewStartDate . '" <= end_date');
    $overlappedMembershipPeriods->whereAdd('"' . $periodNewEndDate . '" >= start_date');
    $overlappedMembershipPeriods->whereAdd('id != ' . $periodParams['id']);
    if ($overlappedMembershipPeriods->find()) {
      return TRUE;
    }

    return FALSE;
  }

  private static function updateMembershipDates($membershipPeriod) {
    $membershipId = $membershipPeriod->membership_id;
    $joinDate = $startDate = self::getFirstActivePeriod($membershipId)['start_date'];
    $endDate = self::getLastActivePeriod($membershipId)['end_date'];

    civicrm_api3('Membership', 'create', [
      'id' => $membershipId,
      'join_date' => $joinDate,
      'start_date' => $startDate,
      'end_date' => $endDate,
      'skipStatusCal' => 0,
    ]);
  }

}
