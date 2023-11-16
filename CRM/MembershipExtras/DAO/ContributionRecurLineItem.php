<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from uk.co.compucorp.membershipextras/xml/schema/CRM/MembershipExtras/ContributionRecurLineItem.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:8a1cee1646df3534b1ae802f4d2b4c6b)
 */
use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Database access object for the ContributionRecurLineItem entity.
 */
class CRM_MembershipExtras_DAO_ContributionRecurLineItem extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '5.0';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'membershipextras_subscription_line';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Discount Item ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * ID of the recurring contribution.
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $contribution_recur_id;

  /**
   * ID of the line item related to the recurring contribution.
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $line_item_id;

  /**
   * Start date of the period for the membership/recurring contribution.
   *
   * @var string
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $start_date;

  /**
   * End date of the period for the membership/recurring contribution.
   *
   * @var string
   *   (SQL type: datetime)
   *   Note that values will be retrieved from the database as a string.
   */
  public $end_date;

  /**
   * If the line-item should be auto-renewed or not.
   *
   * @var bool|string
   *   (SQL type: tinyint)
   *   Note that values will be retrieved from the database as a string.
   */
  public $auto_renew;

  /**
   * If the line-item has been marked as removed or not.
   *
   * @var bool|string
   *   (SQL type: tinyint)
   *   Note that values will be retrieved from the database as a string.
   */
  public $is_removed;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'membershipextras_subscription_line';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Contribution Recur Line Items') : E::ts('Contribution Recur Line Item');
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
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'contribution_recur_id', 'civicrm_contribution_recur', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'line_item_id', 'civicrm_line_item', 'id');
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
          'description' => E::ts('Discount Item ID'),
          'required' => TRUE,
          'where' => 'membershipextras_subscription_line.id',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => '5.0',
        ],
        'contribution_recur_id' => [
          'name' => 'contribution_recur_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('ID of the recurring contribution.'),
          'required' => TRUE,
          'where' => 'membershipextras_subscription_line.contribution_recur_id',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contribute_DAO_ContributionRecur',
          'add' => '5.0',
        ],
        'line_item_id' => [
          'name' => 'line_item_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('ID of the line item related to the recurring contribution.'),
          'required' => TRUE,
          'where' => 'membershipextras_subscription_line.line_item_id',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'FKClassName' => 'CRM_Price_DAO_LineItem',
          'add' => '5.0',
        ],
        'start_date' => [
          'name' => 'start_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('Start Date'),
          'description' => E::ts('Start date of the period for the membership/recurring contribution.'),
          'required' => FALSE,
          'where' => 'membershipextras_subscription_line.start_date',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'add' => '5.0',
        ],
        'end_date' => [
          'name' => 'end_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => E::ts('End Date'),
          'description' => E::ts('End date of the period for the membership/recurring contribution.'),
          'required' => FALSE,
          'where' => 'membershipextras_subscription_line.end_date',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'add' => '5.0',
        ],
        'auto_renew' => [
          'name' => 'auto_renew',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => E::ts('Auto Renew'),
          'description' => E::ts('If the line-item should be auto-renewed or not.'),
          'required' => TRUE,
          'where' => 'membershipextras_subscription_line.auto_renew',
          'default' => 'false',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'add' => '5.0',
        ],
        'is_removed' => [
          'name' => 'is_removed',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'description' => E::ts('If the line-item has been marked as removed or not.'),
          'required' => TRUE,
          'where' => 'membershipextras_subscription_line.is_removed',
          'default' => 'false',
          'table_name' => 'membershipextras_subscription_line',
          'entity' => 'ContributionRecurLineItem',
          'bao' => 'CRM_MembershipExtras_DAO_ContributionRecurLineItem',
          'localizable' => 0,
          'add' => '5.0',
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
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'ipextras_subscription_line', $prefix, []);
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
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'ipextras_subscription_line', $prefix, []);
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
    $indices = [
      'index_contribrecurid_lineitemid' => [
        'name' => 'index_contribrecurid_lineitemid',
        'field' => [
          0 => 'contribution_recur_id',
          1 => 'line_item_id',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'membershipextras_subscription_line::1::contribution_recur_id::line_item_id',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
