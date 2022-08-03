<?php
use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

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
  private $financialTypes = array();

  /**
   * Contains the list of all membership types
   *
   * @var array
   */
  private $allMembershipTypes = [];

  /**
   * Contains the list of all current line items
   * membership types
   *
   * @var array
   */
  private $currentLineItemMembershipTypes = [];

  /**
   * Contains the list of all next line items
   * membership types
   *
   * @var array
   */
  private $nextLineItemMembershipTypes = [];

  /**
   * List of line items for current period.
   *
   * @var array
   */
  private $currentPeriodLineItems = [];

  /**
   * List of line items for next period.
   *
   * @var array
   */
  private $nextPeriodLineItems = [];

  /**
   * Cache of memberships that have been loaded into the page.
   *
   * @var array
   */
  private $membershipsCache = [];

  /**
   * Maps membership types to memberships that have been loaded.
   *
   * @var array
   */
  private $membershipTypesCache = [];

  /**
   * @inheritdoc
   */
  public function __construct($title = NULL, $mode = NULL) {
    parent::__construct($title, $mode);

    $this->contribRecur = $this->getRecurringContribution();
    $this->financialTypes = $this->getFinancialTypes();
    $this->setAllMembershipTypes();
    $this->currentPeriodLineItems = $this->getCurrentPeriodLineItems();
    $this->nextPeriodLineItems = $this->getNextPeriodLineItems();
    $this->setCurrentLineItemMembershipTypes();
    $this->setNextLineItemMembershipTypes();
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
    $financialTypes = array();

    $result = civicrm_api3('FinancialType', 'get', [
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $financialType) {
        $financialTypes[] = array_merge($financialType, array(
          'tax_rate' => $this->getTaxRateForFinancialType($financialType['id']),
        ));
      }
    }

    return $financialTypes;
  }

  private function setAllMembershipTypes() {
    $result = civicrm_api3('MembershipType', 'get', [
      'options' => ['limit' => 0],
      'is_active' => 1,
    ]);

    if ($result['count'] > 0) {
      $this->allMembershipTypes = $result['values'];
    }
  }

  private function getCurrentTabMembershipTypes() {
    $allowedTypes = $this->allMembershipTypes;
    foreach ($allowedTypes as $key => $type) {
      $lineItemIndex = array_search($type['member_of_contact_id'], array_column($this->currentLineItemMembershipTypes, 'org_id'));
      if ($lineItemIndex !== FALSE) {
        unset($allowedTypes[$key]);
      }
    }

    return $allowedTypes;
  }

  private function getNextTabMembershipTypes() {
    $allowedTypes = $this->allMembershipTypes;
    foreach ($allowedTypes as $key => $type) {
      $lineItemIndex = array_search($type['member_of_contact_id'], array_column($this->currentLineItemMembershipTypes, 'org_id'));
      if ($lineItemIndex !== FALSE) {
        $lineItemType = $this->currentLineItemMembershipTypes[$lineItemIndex];
        if ($lineItemType['is_autorenew']) {
          unset($allowedTypes[$key]);
          continue;
        }
      }

      $lineItemIndex = array_search($type['member_of_contact_id'], array_column($this->nextLineItemMembershipTypes, 'org_id'));
      if ($lineItemIndex !== FALSE) {
        unset($allowedTypes[$key]);
        continue;
      }
    }

    return $allowedTypes;
  }

  /**
   * Obtains membership type data for the given membership ID.
   *
   * @param $membershipID
   *
   * @return array
   */
  private function getMembershipTypeFromMembershipID($membershipID) {
    if (!isset($this->membershipTypesCache[$membershipID])) {
      try {
        $result = civicrm_api3('Membership', 'getsingle', [
          'id' => $membershipID,
          'api.MembershipType.getsingle' => [
            'id' => '$value.membership_type_id',
          ],
        ]);

        $this->membershipTypesCache[$membershipID] = $result['api.MembershipType.getsingle'];
      }
      catch (Exception $e) {
        return [];
      }
    }

    return $this->membershipTypesCache[$membershipID];
  }

  private function getMembershipTypeFromPriceFieldValue($priceFieldValueId) {
    $priceFieldMembershipType = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'id' => $priceFieldValueId,
      'options' => ['sort' => 'id desc'],
    ]);

    if (!empty($priceFieldMembershipType['values'][0]['membership_type_id'])) {
      $lineItemMembershipTypeId = $priceFieldMembershipType['values'][0]['membership_type_id'];
      return civicrm_api3('MembershipType', 'get', [
        'sequential' => 1,
        'id' => $lineItemMembershipTypeId,
      ])['values'][0];
    }

    return NULL;
  }

  /**
   * @inheritdoc
   */
  public function run() {
    CRM_Utils_System::setTitle(E::ts('Manage Installments'));

    $this->assign('currentDate', date('Y-m-d'));
    $this->assign('recurringContribution', $this->contribRecur);
    $this->assign('recurringContributionID', $this->contribRecur['id']);

    $this->assign('periodStartDate', $this->calculateCurrentPeriodStartDate());

    $currentPeriodEndDate = $this->calculateCurrentPeriodEndDate();
    $this->assign('periodEndDate', $currentPeriodEndDate);
    $this->assign('nextPeriodStartDate', $this->calculateNextPeriodStartDate($currentPeriodEndDate));
    $this->assign('largestMembershipEndDate', $this->getLargestMembershipEndDate($this->currentPeriodLineItems));

    $this->assign('currentPeriodMembershipTypes', $this->getCurrentTabMembershipTypes());
    $this->assign('nextPeriodMembershipTypes', $this->getNextTabMembershipTypes());

    $this->assign('lineItems', $this->currentPeriodLineItems);

    $this->assign('showNextPeriodTab', $this->showNextPeriodTab());
    $this->assign('financialTypes', $this->financialTypes);
    $this->assign('currencySymbol', $this->getCurrencySymbol());
    $this->assign('nextPeriodLineItems', $this->nextPeriodLineItems);

    parent::run();
  }

  /**
   * Obtains list of line items for the current period.
   *
   * @return array
   */
  private function getCurrentPeriodLineItems() {
    $conditions = [
      'is_removed' => 0,
      'start_date' => ['IS NOT NULL' => 1],
    ];

    $installments = CRM_Utils_Array::value('installments', $this->contribRecur, 0);
    if ($installments <= 1) {
      $conditions['end_date'] = ['IS NULL' => 1];
    }

    return $this->getLineItems($conditions);
  }

  private function setCurrentLineItemMembershipTypes() {
    foreach ($this->currentPeriodLineItems as $lineItem) {
      if ($lineItem['entity_table'] != 'civicrm_membership') {
        continue;
      }

      $typeDetails = [];

      $typeDetails['is_autorenew'] = $lineItem['auto_renew'];

      $lineItemMembershipType = $this->getMembershipTypeFromMembershipID($lineItem['entity_id']);
      $typeDetails['name'] = $lineItemMembershipType['name'];
      $typeDetails['org_id'] = $lineItemMembershipType['member_of_contact_id'];

      $this->currentLineItemMembershipTypes[] = $typeDetails;
    }
  }

  private function setNextLineItemMembershipTypes() {
    foreach ($this->nextPeriodLineItems as $lineItem) {
      $typeDetails = [];

      $lineItemMembershipType = $this->getMembershipTypeFromPriceFieldValue($lineItem['price_field_value_id']);
      if (!empty($lineItemMembershipType)) {
        $typeDetails['name'] = $lineItemMembershipType['name'];
        $typeDetails['org_id'] = $lineItemMembershipType['member_of_contact_id'];

        $this->nextLineItemMembershipTypes[] = $typeDetails;
      }
    }
  }

  /**
   * Calculates current period start date.
   *
   * @return string
   *   Start date for current period.
   *
   * @throws \Exception
   */
  private function calculateCurrentPeriodStartDate() {
    return $this->getEarliestLineStartDate($this->currentPeriodLineItems);
  }

  /**
   * Calculates current period end date.
   *
   * @return string
   *   End date of current period.
   *
   * @throws \Exception
   */
  private function calculateCurrentPeriodEndDate() {
    return $this->getLargestMembershipEndDate($this->currentPeriodLineItems);
  }

  /**
   * Obtains earliest Start Date from the given line items.
   *
   * @param array $lineItems
   *   List of line items for the period.
   *
   * @return string
   *   Earliest start date for the period.
   *
   * @throws \Exception
   */
  private function getEarliestLineStartDate($lineItems) {
    $earliestDate = NULL;

    foreach ($lineItems as $line) {
      $startDate = new DateTime($line['start_date']);

      if (!isset($earliestDate)) {
        $earliestDate = $startDate;
      }
      elseif ($earliestDate > $startDate) {
        $earliestDate = $startDate;
      }
    }

    return isset($earliestDate) ? $earliestDate->format('Y-m-d') : '';
  }

  /**
   * Obtains list of line items for the next period.
   *
   * @return array
   */
  private function getNextPeriodLineItems() {
    $conditions = [
      'auto_renew' => TRUE,
      'is_removed' => 0,
    ];

    $installments = CRM_Utils_Array::value('installments', $this->contribRecur, 0);
    if ($installments <= 1) {
      $conditions['end_date'] = ['IS NULL' => 1];
    }

    $nextLineItems = $this->getLineItems($conditions);

    foreach ($nextLineItems as &$nextLineItem) {
      if (!empty($nextLineItem['related_membership']['id'])) {
        $relatedMembershipId = $nextLineItem['related_membership']['id'];

        $autoUpgradableMembershipChecker = new CRM_MembershipExtras_Service_AutoUpgradableMembershipChecker();
        $upgradedMembershipTypeId = $autoUpgradableMembershipChecker->calculateMembershipTypeToUpgradeTo($relatedMembershipId);

        if (!empty($upgradedMembershipTypeId)) {
          $membershipType = civicrm_api3('MembershipType', 'get', [
            'sequential' => 1,
            'return' => ['name', 'minimum_fee', 'financial_type_id'],
            'id' => $upgradedMembershipTypeId,
          ]);

          if (!empty($membershipType['values'][0])) {
            $membershipType = $membershipType['values'][0];
            $nextLineItem['label'] = $membershipType['name'];
            $nextLineItem['line_total'] = MoneyUtilities::roundToCurrencyPrecision($membershipType['minimum_fee'] / $installments);
            $nextLineItem['financial_type'] = $this->getFinancialTypeName($membershipType['financial_type_id']);
            $nextLineItem['tax_rate'] = $this->getTaxRateForFinancialType($membershipType['financial_type_id']);
            $nextLineItem['tax_amount'] = MoneyUtilities::roundToCurrencyPrecision(
              $nextLineItem['line_total'] * $nextLineItem['tax_rate'] / 100
            );
          }
        }
      }
    }

    return $nextLineItems;
  }

  /**
   * @return string
   */
  private function getCurrencySymbol() {
    $config = CRM_Core_Config::singleton();
    return CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_Currency', $config->defaultCurrency, 'symbol', 'name');
  }

  /**
   * Returns largest end date for memberships associated to recurring
   * contribution.
   *
   * @param $lineItems
   *
   * @return string
   */
  private function getLargestMembershipEndDate($lineItems) {
    $latestDate = NULL;

    foreach ($lineItems as $line) {
      if ($line['entity_table'] != 'civicrm_membership') {
        continue;
      }

      $membership = $this->getMembership($line['entity_id']);
      $membershipDate = new DateTime($membership['end_date']);

      if (!isset($latestDate)) {
        $latestDate = $membershipDate;
      }
      elseif ($latestDate < $membershipDate) {
        $latestDate = $membershipDate;
      }
    }

    return isset($latestDate) ? $latestDate->format('Y-m-d') : '';
  }

  /**
   * Gets the memberships identified by givenn ID.
   *
   * @param $membershipID
   *
   * @return array
   */
  private function getMembership($membershipID) {
    if (empty($membershipID)) {
      return [];
    }

    if (!isset($this->membershipsCache[$membershipID])) {
      $membership = civicrm_api3('Membership', 'getsingle', [
        'sequential' => 1,
        'id' => $membershipID,
        'return' => ['start_date', 'end_date', 'id'],
      ]);
      $membership['related_membership_type'] = $this->getMembershipTypeFromMembershipID($membershipID);
      $this->membershipsCache[$membershipID] = $membership;
    }

    return $this->membershipsCache[$membershipID];
  }

  /**
   * Checks if auto-renew is enabled for recurring contribution.
   *
   * @return boolean
   */
  private function showNextPeriodTab() {
    return CRM_Utils_String::strtobool(CRM_Utils_Array::value('auto_renew', $this->contribRecur));
  }

  /**
   * Calculates next period's start date
   *
   * @return string
   *   Start date for next period.
   *
   * @throws \Exception
   */
  private function calculateNextPeriodStartDate($currentPeriodEndDate) {
    $membershipDate = new DateTime($currentPeriodEndDate);
    $membershipDate->add(new DateInterval('P1D'));

    return $membershipDate->format('Y-m-d');
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
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);

    $result = civicrm_api3('ContributionRecurLineItem', 'get', $options);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $lineDetails = $lineItemData['api.LineItem.getsingle'];
        $lineDetails['tax_rate'] = $this->getTaxRateForFinancialType($lineDetails['financial_type_id']);
        $lineDetails['financial_type'] = $this->getFinancialTypeName($lineDetails['financial_type_id']);

        if ($lineDetails['entity_table'] === 'civicrm_membership') {
          $lineDetails['related_membership'] = $this->getMembership($lineDetails['entity_id']);
        }

        unset($lineDetails['id']);
        unset($lineItemData['api.LineItem.getsingle']);
        $lineItems[] = array_merge($lineItemData, $lineDetails);
      }
    }

    return $lineItems;
  }

  /**
   * Returns tax rate used for given financial type ID.
   *
   * @param $financialTypeID
   *
   * @return double
   */
  private function getTaxRateForFinancialType($financialTypeID) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = MoneyUtilities::roundToPrecision(
      CRM_Utils_Array::value($financialTypeID, $taxRates, 0),
      2
    );

    return $rate;
  }

  /**
   * Returns the financial type's name identified by the given ID.
   *
   * @param int $id
   *
   * @return string
   */
  private function getFinancialTypeName($id) {
    foreach ($this->financialTypes as $financialType) {
      if ($financialType['id'] === $id) {
        return $financialType['name'];
      }
    }

    return '';
  }

}
