<?php

use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;

class CRM_MembershipExtras_Form_MembershipPeriod_EditPeriod extends CRM_Core_Form {

  private $id;

  private $membershipPeriod;

  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('id', 'String', $this, TRUE);
    $this->setMembershipPeriod();

    parent::preProcess();
  }

  private function setMembershipPeriod() {
    $this->membershipPeriod = MembershipPeriod::getMembershipPeriodById($this->id);

    if (!$this->membershipPeriod) {
      throw new CRM_Core_Exception('Membership period Id could not be found');
    }
  }

  public function buildQuickForm() {
    $this->assignContactAndMembershipTypeInfoToTemplate();

    $this->add('datepicker', 'start_date', ts('Start Date'), '', TRUE, ['time' => FALSE]);

    $this->add('datepicker', 'end_date', ts('End Date'), '', TRUE, ['time' => FALSE]);

    $this->add('checkbox', 'is_active', ts('Activated'));

    $this->add('checkbox', 'is_historic', ts('Estimated Legacy Period?'));

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'isDefault' => FALSE,
      ],
    ]);
  }

  private function assignContactAndMembershipTypeInfoToTemplate() {
    $contactName = '';
    $membershipType = '';

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['contact_id.display_name', 'membership_type_id.name'],
      'id' => $this->membershipPeriod->membership_id,
    ]);

    if (!empty($membership['values'][0]['contact_id.display_name'])) {
      $contactName = $membership['values'][0]['contact_id.display_name'];
    }

    if (!empty($membership['values'][0]['membership_type_id.name'])) {
      $membershipType = $membership['values'][0]['membership_type_id.name'];
    }

    $this->assign('contactName', $contactName);
    $this->assign('membershipType', $membershipType);
  }

  public function setDefaultValues() {
    $defaults['start_date'] = $this->membershipPeriod->start_date;
    $defaults['end_date'] = $this->membershipPeriod->end_date;
    $defaults['is_active'] = !empty($this->membershipPeriod->is_active) ? TRUE : FALSE ;
    $defaults['is_historic'] = !empty($this->membershipPeriod->is_historic) ? TRUE : FALSE ;

    return $defaults;
  }

  public function postProcess() {
    $submittedValues = $this->exportValues();
    $paramKeysToUpdate = [
      'start_date',
      'end_date',
      'is_active',
      'is_historic',
    ];

    $params['id'] = $this->id;
    foreach ($paramKeysToUpdate as $paramKey) {
      $params[$paramKey] = CRM_Utils_Array::value($paramKey, $submittedValues, NULL);
    }

    if (!isset($submittedValues['is_active'])) {
      $params['is_active'] = 0;
    }

    $params['start_date'] = (new DateTime($params['start_date']))->format('Y-m-d');
    $params['end_date'] = (new DateTime($params['end_date']))->format('Y-m-d');

    try {
      MembershipPeriod::updatePeriod($params);
    }
    catch (CRM_Core_Exception $exception) {
      CRM_Core_Session::setStatus($exception->getMessage(), '', 'error');
    }
  }

}
