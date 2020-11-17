<?php
use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_RecurringLineItems.
 */
class CRM_MembershipExtras_Test_Fabricator_RecurringLineItem extends BaseFabricator {

  /**
   * Entity name.
   *
   * @var string
   */
  protected static $entityName = 'ContributionRecurLineItem';

  /**
   * List of default parameters used to create a line item.
   *
   * @var array
   */
  protected static $defaultParams = [
    'entity_table' => '',
    'label' => 'Test Line',
    'qty' => 1,
    'unit_price' => 120,
    'line_total' => 120,
    'non_deductible_amount' => 0,
  ];

}
