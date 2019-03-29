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

}
