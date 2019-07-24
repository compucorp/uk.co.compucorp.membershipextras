<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Implements hook to be run before a membership is created/edited.
 */
class CRM_MembershipExtras_Hook_Pre_MembershipCreate {

  /**
   * Parameters that will be used to create the membership.
   *
   * @var array
   */
  private $params;

  public function __construct(&$params) {
    $this->params = &$params;
  }

  /**
   * Preprocesses parameters used for Membership operations.
   */
  public function preProcess() {
    $this->recalculateTaxAmount();
    $this->recalculateLineItemsAmounts();
    $this->updateOrCreateMembership();
  }

  /**
   * Recalculates tax amount tax rate according to selected financial type, only
   * if a price set is not used, as this can be broken if paying using payment
   * plan and/or user edits default total value for the contribution.
   */
  private function recalculateTaxAmount() {
    if ($this->isUsingPriceSet()) {
      return;
    }

    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = CRM_Utils_Array::value($this->params['financial_type_id'], $taxRates, 0);

    $this->params['tax_amount'] = MoneyUtilities::roundToCurrencyPrecision(
      ($this->params['total_amount'] * ($rate / 100)) / (1 + ($rate / 100))
    );
  }

  /**
   * Checks if priceset was selected on the form to create the membership.
   */
  private function isUsingPriceSet() {
    $priceSetID = CRM_Utils_Request::retrieve('price_set_id', 'Int');

    if (!empty($priceSetID)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Recalculates line total, unit price and tax amount for each line item, as
   * these can get broken when paying with a payment plan or using a custom
   * total value.
   */
  private function recalculateLineItemsAmounts() {
    foreach (CRM_Utils_Array::value('lineItems', $this->params, []) as $types) {
      foreach ($types as &$line) {
        $total = $line['line_total'] + $line['tax_amount'];
        $line['tax_amount'] = MoneyUtilities::roundToCurrencyPrecision(
          ($total * ($line['tax_rate'] / 100)) / (1 + ($line['tax_rate'] / 100))
        );
        $line['line_total'] = $total - $line['tax_amount'];
        $line['unit_price'] = MoneyUtilities::roundToCurrencyPrecision(
          $line['line_total'] / $line['qty']
        );
      }
    }
  }

  /**
   * Checks if a membership of the same type already exists for the contact and
   * if it does, retrieves the ID so that the membership gets updated instead.
   */
  private function updateOrCreateMembership() {
    $contactID = $this->params['contact_id'];
    $membershipTypeID = $this->params['membership_type_id'];

    $membershipID = $this->getLastMembershipID([
      'contact_id' => $contactID,
      'membership_type_id' => $membershipTypeID,
    ]);

    if ($membershipID) {
      $this->params['id'] = $membershipID;
      $this->createPeriodForTheUpdatedMembershipDates();
    }
  }

  /**
   * Returns the last membership based on the conditions passed
   * 
   * @param mixed[] $conditions
   */
  private function getLastMembershipID($conditions) {
    $result = civicrm_api3('Membership', 'get', array_merge($conditions, [
      'sequential' => 1,
    ]));

    if (!empty($result['values'])) {
      $largestIndex = count($result['values']) - 1;
      return $result['values'][$largestIndex]['id'];
    }

    return FALSE;
  }

  private function createPeriodForTheUpdatedMembershipDates() {
    $newPeriodParams = [
      'is_active' => FALSE,
      'membership_id' => $this->params['id'],
      'start_date' => CRM_Utils_Array::value('start_date', $this->params),
      'end_date' => CRM_Utils_Array::value('end_date', $this->params),
    ];

    $completedStatus = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $relatedContributionStatus = CRM_Utils_Array::value('contribution_status_id', $this->params);
    if ($completedStatus == $relatedContributionStatus) {
      $newPeriodParams['is_active'] = TRUE;
    } else {
      unset($this->params['start_date']);
      unset($this->params['end_date']);
    }

    CRM_MembershipExtras_BAO_MembershipPeriod::create($newPeriodParams);
  }

}
