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

}
