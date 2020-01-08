<?php
use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

/**
 * Implements post-process hooks on ContributionRecur entity.
 */
class CRM_MembershipExtras_Hook_Post_ContributionRecur {

  /**
   * Reference to the recurring contribution's BAO that was stored.
   *
   * @var \CRM_Contribute_BAO_ContributionRecur
   */
  private $contributionRecurBAO;

  /**
   * List of subscription lines associated to the recurring contribution.
   *
   * @var
   */
  private $subscriptionLines;

  /**
   * CRM_MembershipExtras_Hook_Post_ContributionRecur constructor.
   *
   * @param \CRM_Contribute_BAO_ContributionRecur $contributionBAO
   *
   * @throws \Exception
   */
  public function __construct(CRM_Contribute_BAO_ContributionRecur $contributionBAO) {
    $this->contributionRecurBAO = CRM_Contribute_BAO_ContributionRecur::findById($contributionBAO->id);
    $this->subscriptionLines = $this->getSubscriptionLines();
  }

  /**
   * Post processes recurring contribution entity.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function postProcess() {
    $isManualPaymentPlan = ManualPaymentProcessors::isManualPaymentProcessor($this->contributionRecurBAO->payment_processor_id);
    if ($isManualPaymentPlan) {
      $this->updateStartDates();
      $this->updateLineItemEndDates();
    }
  }

  /**
   * Updates start dates of recurring contribution and lines.
   *
   * Sets start date of recurring contribution and related line items to the
   * earliest membership start date.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function updateStartDates() {
    $earliestDate = $this->getEarliestStartDate();
    if (!$earliestDate) {
      return;
    }

    foreach ($this->subscriptionLines as $line) {
      civicrm_api3('ContributionRecurLineItem', 'create', [
        'id' => $line['id'],
        'start_date' => $earliestDate,
      ]);
    }
  }

  /**
   * Obtains earliest membership start date.
   *
   * @return string
   *   Earliest membership start date.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getEarliestStartDate() {
    $earliestDate = NULL;

    foreach ($this->subscriptionLines as $line) {
      if ($line['entity_table'] !== 'civicrm_membership') {
        continue;
      }

      $membership = $this->getMembership($line['entity_id']);
      $startDate = new DateTime($membership['start_date']);

      if (!isset($earliestDate)) {
        $earliestDate = $startDate;
      } elseif ($earliestDate > $startDate) {
        $earliestDate = $startDate;
      }
    }

    if ($earliestDate) {
      return $earliestDate->format('Y-m-d');
    }

    return NULL;
  }

  /**
   * Obtains data for membership identitfied by given ID.
   *
   * @param int $membershipID
   *   ID of the membership.
   *
   * @return array
   *   Array with the membership's data.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getMembership($membershipID) {
    return civicrm_api3('Membership', 'getsingle', ['id' => $membershipID]);
  }

  /**
   * Updates end dates for line items associated to the recurring contribution.
   */
  private function updateLineItemEndDates() {
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus($this->contributionRecurBAO->contribution_status_id, 'name');

    if ($contributionStatus === 'Completed' && $this->contributionRecurBAO->installments > 1) {
      foreach($this->subscriptionLines as $line) {
        if (!empty($line['start_date']) && empty($line['end_date'])) {
          civicrm_api3('ContributionRecurLineItem', 'create', [
            'id' => $line['id'],
            'end_date' => $this->contributionRecurBAO->end_date,
          ]);
        }
      }
    }
  }

  /**
   * Returns LineItems associated to a recurring contribution.
   *
   * @return array
   *   List of current line items for the payment plan.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getSubscriptionLines() {
    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->contributionRecurBAO->id,
      'is_removed' => 0,
      'start_date' => ['IS NOT NULL' => 1],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1]
      ],
    ]);

    if ($result['count'] < 1) {
      return [];
    }

    $lineItems = [];
    foreach ($result['values'] as $lineItemData) {
      $lineDetails = $lineItemData['api.LineItem.getsingle'];
      unset($lineDetails['id']);
      unset($lineItemData['api.LineItem.getsingle']);
      $lineItems[] = array_merge($lineItemData, $lineDetails);
    }

    return $lineItems;
  }

}
