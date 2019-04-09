<?php

use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;

class CRM_MembershipExtras_Hook_Pre_MembershipPeriodUpdater {

  /**
   * The period membership parameters as
   * passed from Membership pre edit hook
   */
  private $membershipHookParams;

  /**
   * The paid contribution details if the
   * membership edit was triggered by completing
   * a contribution.
   */
  private $contribution = NULL;

  /**
   * The period membership details
   */
  private $membership;

  /**
   * The membership new join date
   */
  private $calculatedMembershipJoinDate;

  /**
   * The membership new end date
   */
  private $calculatedMembershipEndDate;

  private $firstActivatedPeriod;

  private $lastActivatedPeriod;

  private $firstActivatedPeriodStartDate;

  private $firstActivatedPeriodEndDate;

  private $lastActivatedPeriodStartDate;

  private $lastActivatedPeriodEndDate;

  public function __construct($membershipId, &$membershipHookParams, $contributionID) {
    $this->membershipHookParams = &$membershipHookParams;
    $this->setMembership($membershipId);
    $this->setContribution($contributionID);
    $this->setCalculatedMembershipJoinDate();
    $this->setCalculatedMembershipEndDate();
  }

  private function setMembership($membershipId) {
    $this->membership =  civicrm_api3('Membership', 'get', [
      'id' => $membershipId,
      'return' => ['membership_type_id.name', 'join_date', 'end_date'],
      'sequential' => 1,
    ])['values'][0];
  }

  private function setContribution($contributionID) {
    if (empty($contributionID)) {
      return;
    }

    $paymentContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $contributionID,
      'return' => ['id', 'contribution_recur_id', 'contribution_status_id'],
    ]);

    $contribution =  NULL;
    if (!empty($paymentContribution['values'][0])) {
      $contribution = $paymentContribution['values'][0];
    }

    $this->contribution = $contribution;
  }

  private function setCalculatedMembershipJoinDate() {
    if (empty($this->membershipHookParams['join_date'])) {
      $membershipJoinDate = date('Ymd', strtotime($this->membership['join_date']));
    } else {
      $membershipJoinDate = date('Ymd', strtotime($this->membershipHookParams['join_date']));
    }

    $this->calculatedMembershipJoinDate = $membershipJoinDate;
  }

  private function setCalculatedMembershipEndDate() {
    $membershipEndDate = NULL;
    if (!empty($this->membershipHookParams['end_date'])) {
      $membershipEndDate = date('Ymd', strtotime($this->membershipHookParams['end_date']));
    }
    // on membership 'bulk update' the field name is membership_end_date and not end_date
    elseif(!empty($this->membershipHookParams['membership_end_date'])) {
      $membershipEndDate = date('Ymd', strtotime($this->membershipHookParams['membership_end_date']));
    }
    elseif (!empty($this->membership['end_date'])){
      $membershipEndDate = date('Ymd', strtotime($this->membership['end_date']));
    }

    $this->calculatedMembershipEndDate = $membershipEndDate;
  }

  public function process() {
    $this->rectifyPendingPeriodUponPaymentCompletion();

    $this->firstActivatedPeriod = MembershipPeriod::getFirstActivePeriod($this->membership['id']);
    $this->lastActivatedPeriod = MembershipPeriod::getLastActivePeriod($this->membership['id']);
    if (empty($this->firstActivatedPeriod) || empty($this->lastActivatedPeriod)) {
      return;
    }

    if ($this->membership['membership_type_id.name'] == 'Lifetime') {
      $this->updateLifetimeMembershipPeriod();
      return;
    }

    $this->firstActivatedPeriodStartDate = date('Ymd', strtotime($this->firstActivatedPeriod['start_date']));
    $this->firstActivatedPeriodEndDate =  date('Ymd', strtotime($this->firstActivatedPeriod['end_date']));
    $this->lastActivatedPeriodStartDate =  date('Ymd', strtotime($this->lastActivatedPeriod['start_date']));
    $this->lastActivatedPeriodEndDate =  date('Ymd', strtotime($this->lastActivatedPeriod['end_date']));

    $this->validatePeriodNewDates();

    $this->updateLastActivePeriodUponEndDateDecrement();
    $this->updateFirstActivePeriodUponStartDateIncrement();
    $this->createNewPeriodUponEndDateIncrement();
    $this->createNewPeriodUponStartDateDecrement();
  }

  /**
   * If there is a contribution set, then it means
   * that a contribution was completed and triggered
   * the membership edit event, and in this case
   * we need to activate the period associated with this
   * contribution as well as updating its dates if necessary.
   *
   */
  private function rectifyPendingPeriodUponPaymentCompletion() {
    if (empty($this->contribution['id'])) {
      return;
    }

    $periodToUpdate = $this->getCompletedContributionPeriod();
    if(!$periodToUpdate) {
      return;
    }

    $this->setCompletedContributionPeriodActivateStatus($periodToUpdate);
    $this->setCompletedContributionPeriodNewDates($periodToUpdate);
    $periodToUpdate->save();
  }

  /**
   * Lifetime membership does not have an end date
   * so we just need to update its period start date
   * in case it was edited.
   */
  private function updateLifetimeMembershipPeriod() {
    $updateParams = [];
    $updateParams['id'] = $this->firstActivatedPeriod['id'];
    $updateParams['start_date'] = $this->calculatedMembershipJoinDate;
    MembershipPeriod::create($updateParams);
  }

  /**
   * Gets the period related to the completed contribution.
   *
   * @return CRM_MembershipExtras_DAO_MembershipPeriod|null
   */
  private function getCompletedContributionPeriod() {
    $periodToUpdate = new CRM_MembershipExtras_DAO_MembershipPeriod();
    $periodToUpdate->is_active = FALSE;
    $periodToUpdate->membership_id = $this->membership['id'];
    $periodToUpdate->entity_id = $this->contribution['id'];
    $periodToUpdate->payment_entity_table = 'civicrm_contribution';

    if (!empty($this->contribution['contribution_recur_id'])) {
      $periodToUpdate->entity_id = $this->contribution['contribution_recur_id'];
      $periodToUpdate->payment_entity_table = 'civicrm_contribution_recur';
    }

    $periodToUpdate->orderBy('end_date DESC,id DESC');
    $periodToUpdate->limit(1);
    if($periodToUpdate->find(TRUE)) {
      return $periodToUpdate;
    }

    return NULL;
  }

  private function setCompletedContributionPeriodActivateStatus($periodToUpdate) {
    if (isset($this->contribution['contribution_status']) && $this->contribution['contribution_status'] == 'Completed') {
      $periodToUpdate->is_active = TRUE;
    }
  }

  /**
   * If a membership is expired but it has a pending contribution
   * and that contribution is completed then CiviCRM will update
   * the start date of the membership to today date and the end
   * date to after 1 term of the start date, this method ensure
   * that the period linked to the completed contribution dates
   * is also get updated to reflect the new membership dates.
   */
  private function setCompletedContributionPeriodNewDates($periodToUpdate) {
    if (!empty($this->membershipHookParams['start_date']) && !empty($this->membershipHookParams['end_date'])) {
      $newStartDate = date('Ymd', strtotime($this->membershipHookParams['start_date']));
      $oldStartDate = date('Ymd', strtotime($periodToUpdate->start_date));
      $newEndDate = date('Ymd', strtotime($this->membershipHookParams['end_date']));
      $oldEndDate = date('Ymd', strtotime($periodToUpdate->end_date));
      if ($newStartDate > $oldStartDate && $newEndDate > $oldEndDate) {
        $periodToUpdate->start_date = $newStartDate;
        $periodToUpdate->end_date = $newEndDate;
        // we also update the membership join date to match the new start date
        $this->membershipHookParams['join_date'] = date('Y-m-d', strtotime($this->membershipHookParams['start_date']));
        $this->calculatedMembershipJoinDate = $newStartDate;
      }
    }
  }

  private function validatePeriodNewDates() {
    if ($this->calculatedMembershipEndDate <= $this->lastActivatedPeriodStartDate) {
      throw new CRM_Core_Exception('The membership end date should be larger than the last active period start date');
    }

    if ($this->calculatedMembershipJoinDate >= $this->firstActivatedPeriodEndDate) {
      throw new CRM_Core_Exception('The membership join date should be less than the first active period end date');
    }
  }

  private function updateLastActivePeriodUponEndDateDecrement() {
    if ($this->calculatedMembershipEndDate < $this->lastActivatedPeriodEndDate) {
      $updateParams = [];
      $updateParams['id'] = $this->lastActivatedPeriod['id'];
      $updateParams['end_date'] = $this->calculatedMembershipEndDate;
      MembershipPeriod::create($updateParams);
    }
  }

  private function updateFirstActivePeriodUponStartDateIncrement() {
    if ($this->calculatedMembershipJoinDate > $this->firstActivatedPeriodStartDate) {
      $updateParams = [];
      $updateParams['id'] = $this->firstActivatedPeriod['id'];
      $updateParams['start_date'] = $this->calculatedMembershipJoinDate;
      MembershipPeriod::create($updateParams);
    }
  }

  private function createNewPeriodUponEndDateIncrement() {
    if ($this->calculatedMembershipEndDate > $this->lastActivatedPeriodEndDate) {
      $newPeriodParams = [];
      $newPeriodParams['membership_id'] = $this->membership['id'];
      $newPeriodParams['end_date'] = $this->calculatedMembershipEndDate;
      $newPeriodParams['is_active'] = TRUE;

      $newPeriodParams['start_date'] = $this->calculateNewLastPeriodStartDate();

      $this->setNewPeriodPaymentEntityParams($newPeriodParams);

      MembershipPeriod::create($newPeriodParams);
    }
  }

  private function calculateNewLastPeriodStartDate() {
    $endOfLastActivePeriod = new DateTime($this->lastActivatedPeriod['end_date']);
    $endOfLastActivePeriod->add(new DateInterval('P1D'));
    $endOfLastActivePeriodDate = $endOfLastActivePeriod->format('Y-m-d');
    $newPeriodStartDate = $endOfLastActivePeriodDate;

    $todayDate = (new DateTime())->format('Y-m-d');
    $renewalDate = CRM_Utils_Request::retrieve('renewal_date', 'String');
    if ($renewalDate) {
      $renewalDate = (new DateTime($renewalDate))->format('Y-m-d');
    } else {
      $renewalDate = $todayDate;
    }

    if ($renewalDate > $endOfLastActivePeriodDate && $renewalDate < $this->calculatedMembershipEndDate) {
      $newPeriodStartDate = $renewalDate;
    }

    return $newPeriodStartDate;
  }

  private function createNewPeriodUponStartDateDecrement() {
    if ($this->calculatedMembershipJoinDate < $this->firstActivatedPeriodStartDate) {
      $newPeriodParams = [];
      $newPeriodParams['membership_id'] = $this->membership['id'];
      $newPeriodParams['start_date'] = $this->calculatedMembershipJoinDate;
      $newPeriodParams['is_active'] = TRUE;

      $newPeriodParams['end_date'] = $this->calculateNewLastPeriodEndDate();

      $this->setNewPeriodPaymentEntityParams($newPeriodParams);

      MembershipPeriod::create($newPeriodParams);
    }
  }

  private function calculateNewLastPeriodEndDate() {
    $startOfFirstActivePeriod = new DateTime($this->firstActivatedPeriod['start_date']);
    $startOfFirstActivePeriod->sub(new DateInterval('P1D'));
    return $startOfFirstActivePeriod->format('Y-m-d');
  }

  private function setNewPeriodPaymentEntityParams(&$newPeriodParams) {
    if (!empty($this->contribution['id'])) {
      $newPeriodParams['payment_entity_table'] = 'civicrm_contribution';
      $newPeriodParams['entity_id'] = $this->contribution['id'];
      if (!empty($this->contribution['contribution_recur_id'])) {
        $newPeriodParams['payment_entity_table'] = 'civicrm_contribution_recur';
        $newPeriodParams['entity_id'] = $this->contribution['contribution_recur_id'];
      }
    }
  }

}
