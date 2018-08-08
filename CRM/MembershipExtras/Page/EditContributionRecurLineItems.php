<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Page_EditContributionRecurLineItems extends CRM_Core_Page {

  /**
   * ID of the recurring contribution being viewed.
   *
   * @var int
   */
  private $contribRecur;

  /**
   * List of available financial types in the system.
   *
   * @var array
   */
  private $financialTypes;

  /**
   * @inheritdoc
   */
  public function __construct($title = NULL, $mode = NULL) {
    parent::__construct($title, $mode);

    $this->contribRecur = $this->getRecurringContribution();
    $this->financialTypes = $this->getFinancialTypes();
  }

  /**
   * Loads recurring contribution identified by ID set in request.
   *
   * @return array
   */
  private function getRecurringContribution() {
    $recurringContributionID = CRM_Utils_Request::retrieveValue('crid', 'Positive', 0);

    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $recurringContributionID,
    ]);
  }

  /**
   * Loads available financial types.
   *
   * @return array
   */
  private function getFinancialTypes() {
    $financialTypes = civicrm_api3('FinancialType', 'get', [
      'options' => ['limit' => 0],
    ]);

    return $financialTypes['values'];
  }

  /**
   * @inheritdoc
   */
  public function run() {
    CRM_Utils_System::setTitle(E::ts('View/Update Recurring Line Items'));

    // Calculate next period start date: start_date + (installments * frequency_interval) frequency_unit
    $installments = CRM_Utils_Array::value('frequency_interval', $this->contribRecur) * CRM_Utils_Array::value('installments', $this->contribRecur);
    $nextPeriodStartsIn = $installments . ' ' . CRM_Utils_Array::value('frequency_unit', $this->contribRecur);
    $nextPeriodStartDate = date('Y-m-d H:i:s', strtotime(
      CRM_Utils_Array::value('start_date', $this->contribRecur) . ' + ' .  $nextPeriodStartsIn
    ));

    // Has line should be true if auto_renew is enabled and recurring
    // contribution has at least one membership
    $hasAutoRenewEnabled = CRM_Utils_String::strtobool(CRM_Utils_Array::value('auto_renew', $this->contribRecur)) && count($this->getMemberships());

    $this->assign('periodStartDate', CRM_Utils_Array::value('start_date', $this->contribRecur));
    $this->assign('periodEndDate', CRM_Utils_Array::value('end_date', $this->contribRecur));
    $this->assign('lineItems', $this->getLineItems());
    $this->assign('autoRenewEnabled', $hasAutoRenewEnabled);
    $this->assign('nextPeriodStartDate', $nextPeriodStartDate);
    $this->assign('nextPeriodLineItems', $this->getLineItems(['auto_renew' => false]));

    parent::run();
  }

  /**
   * Obtains list of line items for the current recurring contribution.
   * 
   * @param array $conditions
   *
   * @return array
   */
  private function getLineItems($conditions = []) {
    $lineItems = array();

    $options = array_merge($conditions, [
      'sequential' => 1,
      'contribution_recur_id' => $this->contribRecur['id'],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1]
      ],
    ]);

    $result = civicrm_api3('ContributionRecurLineItem', 'get', $options);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $lineDetails = $lineItemData['api.LineItem.getsingle'];
        unset($lineItemData['api.LineItem.getsingle']);

        $lineDetails['financial_type'] = $this->getFinancialTypeName($lineDetails['financial_type_id']);
        $lineItems[] = array_merge($lineItemData, $lineDetails);
      }
    }

    return $lineItems;
  }

  /**
   * Gets the memberships associated with the current recurring contribution
   * 
   * @return array
   */
  private function getMemberships() {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->contribRecur['id'],
    ])['values'];
  }

  /**
   * Returns the financial type's name identified by the given ID.
   *
   * @param int $id
   *
   * @return string
   */
  private function getFinancialTypeName($id) {
    if (in_array($id, array_keys($this->financialTypes))) {
      return $this->financialTypes[$id]['name'];
    }

    return '';
  }

}
