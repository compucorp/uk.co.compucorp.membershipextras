<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing two membership status rules 'Current Renewed' and
 * 'Future Start'.
 */
class CRM_MembershipExtras_Setup_Manage_FutureMembershipStatusRules extends AbstractManager {

  /**
   * @inheritDoc
   */
  public function create() {
    $this->createCurrentRenewMembershipStatusRule();
    $this->createFutureStartMembershipStatusRule();
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'current_renewed',
      'api.MembershipStatus.delete' => ['id' => '$value.id'],
    ]);

    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'future_start',
      'api.MembershipStatus.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'current_renewed',
      'api.MembershipStatus.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);

    civicrm_api3('MembershipStatus', 'get', [
      'name' => 'future_start',
      'api.MembershipStatus.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

  /**
   * Creates a membership status rule that
   * applies when the membership join date
   * is in the past, but the membership
   * start date is in the future.
   */
  private function createCurrentRenewMembershipStatusRule() {
    $membershipStatusRule = civicrm_api3('MembershipStatus', 'get', [
      'sequential' => 1,
      'name' => 'current_renewed',
    ]);

    if ($membershipStatusRule['count'] > 0) {
      return;
    }

    // this ensures that the membership status rule will be on top and has
    // the highest priority during status calculation.
    $minStatusWeight = (int) civicrm_api3('MembershipStatus', 'getvalue', [
      'return' => 'weight',
      'options' => ['sort' => 'weight ASC', 'limit' => 1],
    ]);

    civicrm_api3('MembershipStatus', 'create', [
      'name' => 'current_renewed',
      'label' => 'Current Renewed',
      'start_event' => 'join_date',
      'start_event_adjust_unit' => 'day',
      'start_event_adjust_interval' => 1,
      'end_event' => 'start_date',
      'end_event_adjust_unit' => 'day',
      'end_event_adjust_interval' => -1,
      'is_current_member' => 1,
      'is_active' => 1,
      'weight' => ($minStatusWeight - 1),
    ]);
  }

  /**
   * Creates a membership status rule that
   * applies when the membership join date
   * and the  membership start date
   * are in the future.
   */
  private function createFutureStartMembershipStatusRule() {
    $membershipStatusRule = civicrm_api3('MembershipStatus', 'get', [
      'sequential' => 1,
      'name' => 'future_start',
    ]);

    if ($membershipStatusRule['count'] > 0) {
      return;
    }

    // this ensures that the membership status rule will be on bottom and has
    // the lowest priority during status calculation.
    $maxStatusWeight = (int) civicrm_api3('MembershipStatus', 'getvalue', [
      'return' => 'weight',
      'options' => ['sort' => 'weight DESC', 'limit' => 1],
    ]);

    // -1000 is an arbitrary number, the idea is
    // just to be something far in the past for
    // status rule to work.
    $startEventAdjustInterval = -1000;

    civicrm_api3('MembershipStatus', 'create', [
      'name' => 'future_start',
      'label' => 'Future Start',
      'start_event' => 'join_date',
      'start_event_adjust_unit' => 'year',
      'start_event_adjust_interval' => $startEventAdjustInterval,
      'end_event' => 'start_date',
      'is_current_member' => 0,
      'is_active' => 1,
      'weight' => ($maxStatusWeight + 1),
    ]);
  }

}
