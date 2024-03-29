<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from uk.co.compucorp.membershipextras/xml/schema/CRM/MembershipExtras/PaymentScheme.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:4c9369ab33edde1eedc52b603af295af)
 */
use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Database access object for the PaymentScheme entity.
 */
class CRM_MembershipExtras_DAO_PaymentScheme extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'membershipextras_payment_scheme';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique PaymentScheme ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * @var string
   *   (SQL type: varchar(250))
   *   Note that values will be retrieved from the database as a string.
   */
  public $name;

  /**
   * @var string|null
   *   (SQL type: varchar(250))
   *   Note that values will be retrieved from the database as a string.
   */
  public $admin_title;

  /**
   * @var string|null
   *   (SQL type: varchar(500))
   *   Note that values will be retrieved from the database as a string.
   */
  public $description;

  /**
   * @var string
   *   (SQL type: varchar(250))
   *   Note that values will be retrieved from the database as a string.
   */
  public $public_title;

  /**
   * @var string
   *   (SQL type: varchar(500))
   *   Note that values will be retrieved from the database as a string.
   */
  public $public_description;

  /**
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $payment_processor;

  /**
   * @var string
   *   (SQL type: varchar(10))
   *   Note that values will be retrieved from the database as a string.
   */
  public $permission;

  /**
   * @var bool|string
   *   (SQL type: tinyint)
   *   Note that values will be retrieved from the database as a string.
   */
  public $enabled;

  /**
   * @var string
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $parameters;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'membershipextras_payment_scheme';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Payment Schemes') : E::ts('Payment Scheme');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'payment_processor', 'civicrm_payment_processor', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('Unique PaymentScheme ID'),
          'required' => TRUE,
          'where' => 'membershipextras_payment_scheme.id',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'name' => [
          'name' => 'name',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Name'),
          'required' => TRUE,
          'maxlength' => 250,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'membershipextras_payment_scheme.name',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'admin_title' => [
          'name' => 'admin_title',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Admin title'),
          'maxlength' => 250,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'membershipextras_payment_scheme.admin_title',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'description' => [
          'name' => 'description',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Description'),
          'maxlength' => 500,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'membershipextras_payment_scheme.description',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'public_title' => [
          'name' => 'public_title',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Public title'),
          'required' => TRUE,
          'maxlength' => 250,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'membershipextras_payment_scheme.public_title',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'public_description' => [
          'name' => 'public_description',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Public description'),
          'required' => TRUE,
          'maxlength' => 500,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'membershipextras_payment_scheme.public_description',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'payment_processor' => [
          'name' => 'payment_processor',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Payment Processor'),
          'required' => TRUE,
          'where' => 'membershipextras_payment_scheme.payment_processor',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'FKClassName' => 'CRM_Financial_DAO_PaymentProcessor',
          'add' => NULL,
        ],
        'permission' => [
          'name' => 'permission',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Permission'),
          'required' => TRUE,
          'maxlength' => 10,
          'size' => CRM_Utils_Type::TWELVE,
          'where' => 'membershipextras_payment_scheme.permission',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'enabled' => [
          'name' => 'enabled',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => E::ts('Enabled'),
          'required' => TRUE,
          'where' => 'membershipextras_payment_scheme.enabled',
          'default' => 'false',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
        'parameters' => [
          'name' => 'parameters',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Parameters'),
          'required' => TRUE,
          'where' => 'membershipextras_payment_scheme.parameters',
          'table_name' => 'membershipextras_payment_scheme',
          'entity' => 'PaymentScheme',
          'bao' => 'CRM_MembershipExtras_DAO_PaymentScheme',
          'localizable' => 0,
          'add' => NULL,
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'ipextras_payment_scheme', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'ipextras_payment_scheme', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
