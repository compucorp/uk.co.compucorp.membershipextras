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

    $defaultParams = static::getDefaultParameters();
    $params = array_merge($defaultParams, $params);

    try {
      $result = civicrm_api3(static::$entityName, 'create', $params);
    }
    catch (CiviCRM_API3_Exception $e) {
      throw new Exception('Found exception fabricating ' . static::$entityName . ': ' . $e->getMessage() . "\n\n" . print_r($e->getExtraParams(), TRUE));
    }
    catch (Exception $e) {
      throw new Exception('Found exception fabricating ' . static::$entityName . ': ' . $e->getMessage());
    }

    return array_shift($result['values']);
  }

  /**
   * Returns default list of parameters to create an instance of the entity.
   *
   * @return array
   */
  public static function getDefaultParameters() {
    return static::$defaultParams;
  }

}
