<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_Base.
 */
abstract class CRM_MembershipExtras_Test_Fabricator_Base {

  /**
   * Name of the entity to be fabricated.
   *
   * @var string
   */
  protected static $entityName;

  /**
   * List of default parameters to use on fabrication of entities.
   *
   * @var array
   */
  protected static $defaultParams = [];

  /**
   * Fabricates an instance of the entity with the given parameters.
   *
   * @param array $params
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function fabricate(array $params = []) {
    if (empty(static::$entityName)) {
      throw new \Exception('Entity name cannot be empty!');
    }

    $params = array_merge(static::$defaultParams, $params);
    $result = civicrm_api3(static::$entityName, 'create', $params);

    return array_shift($result['values']);
  }

}
