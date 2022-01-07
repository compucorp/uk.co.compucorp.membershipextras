<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0008 {

  public function apply() {
    $this->migratePaymentPlansToSupportNextContributionDateAutorenewal();
  }

  /**
   * As of Membershipextras v5 we are using next_sched_contribution_date
   * to calculate the installments receive dates after autorenewal
   * instead of using the payment plan related memberships end dates.
   * In this upgrader we update this field value for all offline payment plans
   * so it equals:
   * - For annual payment plans, take max contribution receive date and add 1 year to it.
   * - For monthly payment plans, take max contribution receive date and add 1 month to it.
   * - For quarterly payment plans, take max contribution receive date and add 3 months to it.
   * - For any payment plans that are not any of the above, take min membership end date + 1 day
   */
  private function migratePaymentPlansToSupportNextContributionDateAutorenewal() {
    $this->createTemporaryUpdateTable();

    $payLaterProcessorID = 0;
    $manualPaymentProcessorIDs = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
    $manualPaymentProcessorIDs = implode(',', $manualPaymentProcessorIDs);
    $cancelledStatusID = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'Cancelled');

    $this->updateAnnualPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID);
    $this->updateMonthlyPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID);
    $this->updateQuarterlyPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID);
    $this->updateOtherPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID);
    $this->commitUpdates();

    $this->removeTemporaryUpdateTable();
  }

  private function createTemporaryUpdateTable() {
    CRM_Core_DAO::executeQuery('CREATE TABLE recur_conts_to_update (`recur_id` int(11), `next_cont_date` varchar(255))');
  }

  private function updateAnnualPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID) {
    $query = "
              INSERT INTO recur_conts_to_update
              SELECT ccr.id as recur_id, DATE_ADD(max(cc.receive_date), INTERVAL 1 YEAR) as next_cont_date FROM civicrm_contribution_recur ccr
              INNER JOIN civicrm_contribution cc ON ccr.id = cc.contribution_recur_id
              WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorIDs}))
              AND ccr.auto_renew = 1
              AND ccr.contribution_status_id != {$cancelledStatusID}
              AND ccr.frequency_unit = 'year'
              GROUP BY ccr.id";
    CRM_Core_DAO::executeQuery($query);
  }

  private function updateMonthlyPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID) {
    $query = "
              INSERT INTO recur_conts_to_update
              SELECT ccr.id as recur_id, DATE_ADD(max(cc.receive_date), INTERVAL 1 MONTH) as next_cont_date FROM civicrm_contribution_recur ccr
              INNER JOIN civicrm_contribution cc ON ccr.id = cc.contribution_recur_id
              WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorIDs}))
              AND ccr.auto_renew = 1
              AND ccr.contribution_status_id != {$cancelledStatusID}
              AND ccr.frequency_unit = 'month' AND ccr.installments = 12
              GROUP BY ccr.id";
    CRM_Core_DAO::executeQuery($query);
  }

  private function updateQuarterlyPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID) {
    $query = "
              INSERT INTO recur_conts_to_update
              SELECT ccr.id as recur_id, DATE_ADD(max(cc.receive_date), INTERVAL 3 MONTH) as next_cont_date FROM civicrm_contribution_recur ccr
              INNER JOIN civicrm_contribution cc ON ccr.id = cc.contribution_recur_id
              WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorIDs}))
              AND ccr.auto_renew = 1
              AND ccr.contribution_status_id != {$cancelledStatusID}
              AND ccr.frequency_unit = 'month' AND ccr.installments = 4
              GROUP BY ccr.id";
    CRM_Core_DAO::executeQuery($query);
  }

  /**
   * updating payment plans that are not annual, monthly or quarterly.
   *
   * @param string $manualPaymentProcessorIDs
   * @param int $cancelledStatusID
   *
   */
  private function updateOtherPaymentPlans($manualPaymentProcessorIDs, $cancelledStatusID) {
    $query = "
              INSERT INTO recur_conts_to_update
              SELECT ccr.id as recur_id, DATE_ADD(min(cm.end_date), INTERVAL 1 DAY) as next_cont_date FROM civicrm_contribution_recur ccr
              INNER JOIN civicrm_contribution cc ON ccr.id = cc.contribution_recur_id
              INNER JOIN civicrm_membership_payment cmp ON cc.id = cmp.contribution_id
              INNER JOIN civicrm_membership cm ON cmp.membership_id = cm.id
              WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorIDs}))
              AND ccr.auto_renew = 1
              AND ccr.contribution_status_id != {$cancelledStatusID}
              AND cm.contribution_recur_id IS NOT NULL
              AND (
               (ccr.frequency_unit = 'month' AND ccr.installments NOT IN (4, 12))
               OR (ccr.frequency_unit = 'year' AND ccr.installments > 1)
              )
              GROUP BY ccr.id";
    CRM_Core_DAO::executeQuery($query);
  }

  private function commitUpdates() {
    $query = "
              UPDATE civicrm_contribution_recur ccr
              INNER JOIN recur_conts_to_update ctu ON ccr.id = ctu.recur_id
              SET ccr.next_sched_contribution_date = ctu.next_cont_date";
    CRM_Core_DAO::executeQuery($query);
  }

  private function removeTemporaryUpdateTable() {
    CRM_Core_DAO::executeQuery('DROP TABLE IF Exists recur_conts_to_update');
  }

}
