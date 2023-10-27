<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_BAO_PaymentScheme extends CRM_MembershipExtras_DAO_PaymentScheme {

  /**
   * Creates a new PaymentScheme based on array-data.
   *
   * @param array $params key-value pairs
   *
   * @return CRM_MembershipExtras_BAO_PaymentScheme|NULL
   */
  public static function create($params) {
    $className = 'CRM_MembershipExtras_BAO_PaymentScheme';
    $entityName = 'PaymentScheme';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

  /**
   * Gets all Payment schemes order by ID in descending order.
   *
   * @return array
   */
  public static function getAll() {
    $bao = new self();
    $bao->orderBy('id DESC');
    $bao->find();
    $schemes = [];
    while ($bao->fetch()) {
      $schemes[] = $bao->toArray();
    }

    return $schemes;
  }

  /**
   * Deletes payment scheme by ID.
   *
   * @throws CRM_Core_Exception
   *   Function throws error if payment scheme ID
   *   is linked to any recurring contribution.
   */
  public static function deleteByID($id) {
    $contributionRecursCount = \Civi\Api4\ContributionRecur::get(FALSE)
      ->selectRowCount()
      ->addWhere('payment_plan_extra_attributes.payment_scheme_id', '=', $id)
      ->execute()->count();

    if ($contributionRecursCount > 0) {
      throw new CRM_Core_Exception('You are not able to delete this payment scheme as it’s linked with one or more recurring contributions.');
    }

    $param = [
      'id' => $id,
    ];
    CRM_MembershipExtras_BAO_PaymentScheme::deleteRecord($param);
  }

}
