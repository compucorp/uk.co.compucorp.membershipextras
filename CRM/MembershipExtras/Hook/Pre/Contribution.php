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
    $givenTotalAmount = CRM_Utils_Array::value('total_amount', $this->params, NULL);
    $givenTaxAmount = CRM_Utils_Array::value('tax_amount', $this->params, NULL);

    /*
     * If amount is not being changed WE SHOULD NOT UPDATE AMOUNT! Doing so will
     * cause line items to be updated, using total amount of contribution as
     * line total and adding taxes again!
     */
    if (!isset($givenTotalAmount)) {
      return;
    }

    $calculatedTotalAmount = 0;
    $calculatedTaxAmount = 0;
    $lineItems = $this->getContributionLineItems();

    foreach ($lineItems as $line) {
      $lineTax = CRM_Utils_Array::value('tax_amount', $line, 0);
      $calculatedTotalAmount += $line['line_total'] + $lineTax;
      $calculatedTaxAmount += $lineTax;
    }

    $this->params['total_amount'] = $calculatedTotalAmount;
    $this->params['tax_amount'] = $calculatedTaxAmount;
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
