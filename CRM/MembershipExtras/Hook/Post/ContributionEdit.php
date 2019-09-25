<?php

class CRM_MembershipExtras_Hook_Post_ContributionEdit {

  private $contribution;

  public function __construct(CRM_Contribute_DAO_Contribution $contribution) {
    $this->contribution = $contribution;
  }

  /**
   * Post-processes a membership payment on creation and update.
   */
  public function process() {
    $this->correctContributionPeriodsPaymentEntity();
    $this->updateRelatedRecurringContribution();
  }

  /**
   * When paying for a membership using a payment plan,
   * the recur contribution will be attached to contribution separately
   * after the contribution creation, so we here correct
   * the periods related to the contribution by changing
   * its payment entity to use the recur contribution.
   */
  private function correctContributionPeriodsPaymentEntity() {
    if (empty($this->contribution->contribution_recur_id)) {
      return;
    }

    $contributionPeriods = new CRM_MembershipExtras_DAO_MembershipPeriod();
    $contributionPeriods->entity_id = $this->contribution->id;
    $contributionPeriods->payment_entity_table = 'civicrm_contribution';
    $contributionPeriods->find();
    while($contributionPeriods->fetch()) {
      $periodToUpdate = new CRM_MembershipExtras_DAO_MembershipPeriod();
      $periodToUpdate->id = $contributionPeriods->id;
      $periodToUpdate->entity_id = $this->contribution->contribution_recur_id;
      $periodToUpdate->payment_entity_table = 'civicrm_contribution_recur';
      $periodToUpdate->save();
    }
  }

  /**
   * If receive date of earliest contribution has changed, payment payment plan
   * should be updated accordingly.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function updateRelatedRecurringContribution() {
    if (empty($this->contribution->contribution_recur_id)) {
      return;
    }

    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $this->contribution->contribution_recur_id,
      'options' => ['sort' => 'receive_date ASC', 'limit' => 1],
    ]);

    civicrm_api3('ContributionRecur', 'create', [
      'id' => $this->contribution->contribution_recur_id,
      'start_date' => $result['values'][0]['receive_date'],
    ]);
  }

}
