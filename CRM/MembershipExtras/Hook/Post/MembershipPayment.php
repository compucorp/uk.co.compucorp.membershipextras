<?php

use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;
use CRM_MembershipExtras_SettingsManager as SettingsManager;

/**
 * Post processes membership payments after creation or update.
 */
class CRM_MembershipExtras_Hook_Post_MembershipPayment {

  /**
   * Operation being done on the line item
   *
   * @var string
   */
  private $operation;

  /**
   * ID of the record.
   *
   * @var int
   */
  private $id;

  /**
   * Reference to BAO.
   *
   * @var \CRM_Member_DAO_MembershipPayment
   */
  private $membershipPayment;

  /**
   * Holds the membership period id in case there is any
   * period get created before the membership payment creation.
   *
   * @var int
   */
  private $periodId;

  /**
   * Array with the membership's data.
   *
   * @var array
   */
  private $membership;

  /**
   * Array with the contribution's data.
   *
   * @var array
   */
  private $contribution;

  /**
   * Array with the recurring contribution's data.
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * Holds the list of membership payments created
   * in case there is more one payment created (e.g buying
   * membership with payment plan)
   *
   * @var array
   */
  private static $paymentIds = [];

  /**
   * CRM_MembershipExtras_Hook_Post_MembershipPayment constructor.
   *
   * @param $operation
   * @param $objectId
   * @param \CRM_Member_DAO_MembershipPayment $objectRef
   */
  public function __construct($operation, $objectId, CRM_Member_DAO_MembershipPayment $objectRef, $periodId) {
    $this->operation = $operation;
    $this->id = $objectId;
    self::$paymentIds[] = $objectId;
    $this->membershipPayment = $objectRef;
    $this->periodId = $periodId;

    $this->membership = civicrm_api3('Membership', 'getsingle', [
      'id' => $this->membershipPayment->membership_id,
      'return' => ['join_date', 'membership_type_id', 'membership_type_id.name',
        'membership_type_id.duration_unit', 'membership_type_id.duration_interval'],
    ]);

    $this->contribution = civicrm_api3('Contribution', 'getsingle', [
      'id' => $this->membershipPayment->contribution_id,
    ]);

    if (!empty($this->contribution['contribution_recur_id'])) {
      $this->recurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
        'id' => $this->contribution['contribution_recur_id'],
      ]);
    }
  }

  /**
   * Post-processes a membership payment on creation and update.
   */
  public function postProcess() {
    if ($this->operation == 'create') {
      $this->fixRecurringLineItemMembershipReferences();
      $this->createMissingMembershipPeriod();
      $this->linkPaymentToMembershipPeriod();
      $this->updateMembershipStatusBasedOnPaymentMethod();
    }
  }

  /**
   * Ugh... There is a bug/feature of CiviCRM where line items for memberships
   * are created with the first membership in a price set where several
   * memberships are used, and then the real membership is set via direct SQL
   * query... So we need to calculate real membership ID for recurring
   * line items, otherwise they will all reference one membership.
   *
   * Bug seen as late as v5.4 of CiviCRM.
   *
   * See: https://github.com/civicrm/civicrm-core/blob/5.4.0/CRM/Member/BAO/MembershipPayment.php#L72-L95
   */
  private function fixRecurringLineItemMembershipReferences() {
    $lineItem = $this->getRelatedRecurringLineItem();
    $entityTable = CRM_Utils_Array::value('entity_table', $lineItem, '');
    $entityID = CRM_Utils_Array::value('entity_id', $lineItem, 0);

    if ($entityID && $entityTable == 'civicrm_membership' && $entityID != $this->membershipPayment->membership_id) {
      $sql = "
        UPDATE civicrm_line_item 
        SET entity_table = 'civicrm_membership', entity_id = %1
        WHERE id = %2
      ";
      CRM_Core_DAO::executeQuery($sql, [
        1 => [$this->membershipPayment->membership_id, 'Integer'],
        2 => [$lineItem['id'], 'Integer'],
      ]);
    }
  }

  /**
   * Obtains recurring line item that matches the membership type of the current
   * payment, by looking at the membership type in the line item's price field
   * value.
   *
   * @return array
   */
  private function getRelatedRecurringLineItem() {
    $membershipTypeID = $this->membership['membership_type_id'];
    $recurringContributionID = $this->contribution['contribution_recur_id'];

    $recurringLineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1]
      ],
    ]);

    if ($recurringLineItems['count'] > 0) {
      foreach ($recurringLineItems['values'] as $lineItem) {
        $priceFieldValueID = CRM_Utils_Array::value('price_field_value_id', $lineItem['api.LineItem.getsingle'], 0);
        if (!$priceFieldValueID) {
          continue;
        }

        $priceFieldValueData = civicrm_api3('PriceFieldValue', 'getsingle', [
          'id' => $lineItem['api.LineItem.getsingle']['price_field_value_id'],
        ]);

        if (CRM_Utils_Array::value('membership_type_id', $priceFieldValueData, 0) == $membershipTypeID) {
          return $lineItem['api.LineItem.getsingle'];
        }
      }
    }

    return [];
  }

  /**
   * While Membership pre edit hook is responsible
   * for creating periods upon membership renewal,
   * the hook depends on the change on end date
   * which get extended on renewal. But CiviCRM
   * will not extend the end date of the membership
   * if the membership  is renewed with pending contribution
   * or payment plan and it will only be extended at the time
   * of completing the contribution (or the first contribution
   * in case of payment plan).
   * But we need  an inactive period in this case and
   * this method handles that.
   */
  private function createMissingMembershipPeriod() {
    // membership payment post hook usually get called more than once for
    // each single payment so we do this to prevent it from executing this
    // method more than once for the same payment.
    $counts = array_count_values(self::$paymentIds);
    $hookCallCountsForId = $counts[$this->id];
    if ($hookCallCountsForId > 1) {
      return;
    }

    $contributionStatus = $this->contribution['contribution_status'];
    if ($contributionStatus != 'Pending') {
      return;
    }

    $isPaymentPlanPayment  = !empty($this->recurringContribution) && !empty($this->recurringContribution['installments']);
    if($isPaymentPlanPayment) {
      if ($this->isFirstPaymentPlanContribution()) {
        $this->deactivateOrCreatePeriod();
      }
    } else {
      $this->deactivateOrCreatePeriod();
    }
  }

  private function isFirstPaymentPlanContribution() {
    $contributionsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->contribution['contribution_recur_id'],
    ]);

    return ($contributionsCount == 1);
  }

  private function deactivateOrCreatePeriod() {
    if ($this->periodId) {
      $this->deactivateExistingPeriod();
    } else {
      $this->createPendingMissingPeriod();
    }
  }

  private function deactivateExistingPeriod() {
    $newPeriodParams['id'] = $this->periodId;
    $newPeriodParams['is_active'] = FALSE;
    MembershipPeriod::create($newPeriodParams);
  }

  private function createPendingMissingPeriod() {
    $membershipType = $this->membership['membership_type_id.name'];
    if ($membershipType == 'Lifetime') {
      return;
    }

    $newPeriodParams = [];
    $newPeriodParams['membership_id'] = $this->membershipPayment->membership_id;

    $newPeriodParams['start_date'] = $this->calculateMissingPeriodStartDate();
    $newPeriodParams['end_date'] = $this->calculateMissingPeriodEndDate($newPeriodParams['start_date']);

    $newPeriodParams['is_active'] = FALSE;
    MembershipPeriod::create($newPeriodParams);
  }

  private function calculateMissingPeriodStartDate() {
    $membershipId = $this->membershipPayment->membership_id;
    $lastActivePeriod = MembershipPeriod::getLastActivePeriod($membershipId);
    if (!empty($lastActivePeriod) && !empty($lastActivePeriod['end_date'])) {
      $renewalDate = CRM_Utils_Request::retrieve('renewal_date', 'String');
      if ($renewalDate) {
        $renewalDate = (new DateTime($renewalDate))->format('Y-m-d');
      } else {
        $renewalDate = (new DateTime())->format('Y-m-d');
      }

      $endOfLastActivePeriod = new DateTime($lastActivePeriod['end_date']);
      $endOfLastActivePeriod->add(new DateInterval('P1D'));
      if ($endOfLastActivePeriod->format('Y-m-d') > $renewalDate) {
        $calculatedStartDate =  $endOfLastActivePeriod->format('Y-m-d');
      } else {
        $calculatedStartDate = $renewalDate;
      }
    } else {
      $calculatedStartDate = new DateTime($this->membership['join_date']);
      $calculatedStartDate = $calculatedStartDate->format('Y-m-d');
    }

    return $calculatedStartDate;
  }

  private function calculateMissingPeriodEndDate($baseStartDate) {
    $currentStartDate = new DateTime($baseStartDate);

    switch ($this->membership['membership_type_id.duration_unit']) {
      case 'month':
        $interval = 'P' . $this->membership['membership_type_id.duration_interval'] . 'M';
        break;
      case 'day':
        $interval = 'P' . $this->membership['membership_type_id.duration_interval'] .'D';
        break;
      case 'year':
        $interval = 'P' . $this->membership['membership_type_id.duration_interval'] .'Y';
        break;
    }

    $currentStartDate->add(new DateInterval($interval));
    $currentStartDate->sub(new DateInterval('P1D'));

    return $currentStartDate->format('Ymd');
  }

  /**
   * Since periods are sometimes created before
   * the payment record, we here
   * ensure that the payment entity get linked back
   * to the created period.
   */
  private function linkPaymentToMembershipPeriod() {
    $membershipId = $this->membershipPayment->membership_id;
    $lastMembershipPeriod = MembershipPeriod::getLastPeriod($membershipId);
    if (!empty($lastMembershipPeriod['entity_id'])) {
      return;
    }

    if(!empty($this->recurringContribution)) {
      $periodNewParams = [
        'id' => $lastMembershipPeriod['id'],
        'payment_entity_table' => 'civicrm_contribution_recur',
        'entity_id' => $this->recurringContribution['id'],
      ];
    } else {
      $periodNewParams = [
        'id' => $lastMembershipPeriod['id'],
        'payment_entity_table' => 'civicrm_contribution',
        'entity_id' => $this->contribution['id'],
      ];
    }

    $membershipPeriod = new MembershipPeriod();
    $membershipPeriod::create($periodNewParams);
  }

  /**
   * Updates the membership dates, status as well
   * as the related period status If the membership is paid for by
   * a payment method that that should activate the membership automatically
   * activate the membership.
   */
  private function updateMembershipStatusBasedOnPaymentMethod() {
    $paymentMethodId = $this->contribution['payment_instrument_id'];
    $paymentMethodsThatAlwaysActivateMemberships = SettingsManager::getPaymentMethodsThatAlwaysActivateMemberships();
    if (in_array($paymentMethodId, $paymentMethodsThatAlwaysActivateMemberships)) {
      $this->activateAllRelatedMemberships();
    }
  }

  /**
   * Activates the payment  membership
   * as well as any joint membership related to it.
   */
  private function activateAllRelatedMemberships() {
    $jointMembershipIds = $this->getJointMembershipIds();
    $membershipsToUpdateIds = array_merge([$this->membershipPayment->membership_id], $jointMembershipIds);

    foreach ($membershipsToUpdateIds as $membershipsId) {
      $paymentPendingPeriod = $this->getPendingPaymentPeriod($membershipsId);
      if ($paymentPendingPeriod) {
        $periodNewParams = ['id' => $paymentPendingPeriod->id, 'is_active' => true];
        MembershipPeriod::updatePeriod($periodNewParams);
      }
    }
  }

  private function getJointMembershipIds() {
    $jointMemberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['id'],
      'owner_membership_id' => $this->membershipPayment->membership_id,
      'options' => ['limit' => 0],
    ]);

    if ($jointMemberships['count'] < 1) {
      return [];
    }

    $jointMembershipIds = [];
    foreach ($jointMemberships['values'] as $jointMembership) {
      $jointMembershipIds[] = $jointMembership['id'];
    }

    return $jointMembershipIds;
  }

  /**
   * Gets the pending period that is linked to this
   * payment and the specified membership
   * in case there is any
   *
   * @param int $membershipsId
   *
   * @return CRM_MembershipExtras_DAO_MembershipPeriod|null
   */
  private function getPendingPaymentPeriod($membershipsId) {
    $period = new CRM_MembershipExtras_DAO_MembershipPeriod();
    $period->is_active = FALSE;
    $period->membership_id = $membershipsId;

    if(!empty($this->recurringContribution)) {
      $period->payment_entity_table = 'civicrm_contribution_recur';
      $period->entity_id = $this->recurringContribution['id'];
    } else {
      $period->payment_entity_table = 'civicrm_contribution';
      $period->entity_id = $this->contribution['id'];
    }

    $period->orderBy('end_date DESC,id DESC');
    $period->limit(1);
    if($period->find(TRUE)) {
      return $period;
    }

    return NULL;
  }

}
