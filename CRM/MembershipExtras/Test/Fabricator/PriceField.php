<?php
use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_PriceField.
 */
class CRM_MembershipExtras_Test_Fabricator_PriceField extends BaseFabricator {

  /**
   * Name of the entity.
   *
   * @var string
   */
  protected static $entityName = 'PriceField';

  /**
   * Array if default parameters to be used to create a contact.
   *
   * @var array
   */
  protected static $defaultParams = [
    'name' => 'test_price_field',
    'label' => 'Test Price Field',
    'html_type' => 'Radio',
    'is_enter_qty' => '0',
    'weight' => '1',
    'is_display_amounts' => '1',
    'options_per_line' => '1',
    'is_active' => '1',
    'is_required' => '1',
    'visibility_id' => '1'
  ];

}
