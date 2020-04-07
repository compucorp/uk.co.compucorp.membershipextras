<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_Contact.
 */
class CRM_MembershipExtras_Test_Fabricator_Contact {

  /**
   * Array if default parameters to be used to create a contact.
   *
   * @var array
   */
  private static $defaultParams = [
    'contact_type' => 'Individual',
    'first_name'   => 'Bruce',
    'last_name'    => 'Wayne',
    'sequential'   => 1
  ];

  /**
   * Fabricates a contact with the given parameters.
   *
   * @param array $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricate($params = []) {
    $params = array_merge(self::$defaultParams, $params);
    $params['display_name'] = "{$params['first_name']} {$params['last_name']}";

    $result = civicrm_api3('Contact', 'create', $params);

    return array_shift($result['values']);
  }

  /**
   * Fabricates a contact with an e-mail address.
   *
   * @param $params
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricateWithEmail($params = [], $email = 'johndoe@test.com') {
    $contact = self::fabricate($params);

    civicrm_api3('Email', 'create', [
      'email' => $email,
      'contact_id' => $contact['id'],
      'is_primary' => 1
    ]);

    return $contact;
  }

  /**
   * Fabricates an organization with given parameters.
   *
   * @param $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricateOrganization($params = []) {
    $params['contact_type'] = 'Organization';
    $params['organization_name'] = empty($params['organization_name'])
      ? 'Organization ' . rand(1000, 9999)
      : $params['organization_name'];

    $result = civicrm_api3(
      'Contact',
      'create',
      $params
    );

    return array_shift($result['values']);
  }

}
