<?php

use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_PriceField.
 */
class CRM_MembershipExtras_Test_Fabricator_PriceSet extends BaseFabricator {

  /**
   * Name of the entity.
   *
   * @var string
   */
  protected static $entityName = 'PriceSet';

  /**
   * Array if default parameters to be used to create a contact.
   *
   * @var array
   */
  protected static $defaultParams = [
    'name' => 'test_price_set',
    'title' => 'Test Priceset',
    'is_active' => '1',
    'extends' => 'CiviContribute',
  ];

}
