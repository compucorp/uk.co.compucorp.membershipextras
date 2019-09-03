<?php

/**
 * Implements pre hook on Contribution entity.
 */
class CRM_MembershipExtras_Hook_Pre_Contribution {

  /**
   * Operation being performed.
   *
   * @var string
   */
  private $operation;

  /**
   * Current data for the contribution, if it's being updated.
   *
   * @var int
   */
  private $contributionID;

  /**
   * List of parameters that are being used to create/update the recurring
   * contribution.
   *
   * @var array
   */
  private $params;

  /**
   * CRM_MembershipExtras_Hook_Pre_ContributionRecur constructor.
   *
   * @param string $op
   * @param int $id
   * @param array $params
   */
  public function __construct($op, $id, &$params) {
    $this->operation = $op;
    $this->contributionID = $id;
    $this->params = &$params;
  }

  /**
   * Pre-processes the parameters being used to create or update the recurring
   * contribution.
   */
  public function preProcess() {
    if ($this->operation == 'edit' && $this->contributionID) {
      $this->rectifyAmountsBasedOnLineItems();
    }
  }

  /**
   * Checks if total amount is ok vs sum of line items.
   */
  private function rectifyAmountsBasedOnLineItems() {
    $lineItems = $this->getContributionLineItems();
    $totalAmount = 0;
    $taxAmount = 0;

    foreach ($lineItems as $line) {
      $lineTax = CRM_Utils_Array::value('tax_amount', $line, 0);
      $totalAmount += $line['line_total'] + $lineTax;
      $taxAmount += $lineTax;
    }

    if ($totalAmount != $this->params['total_amount'] || $taxAmount != $this->params['tax_amount']) {
      $this->params['total_amount'] = $totalAmount;
      $this->params['tax_amount'] = $taxAmount;
    }
  }

  /**
   * Obtains list of line items for contribution.
   */
  private function getContributionLineItems() {
    $result = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $this->contributionID,
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

}
