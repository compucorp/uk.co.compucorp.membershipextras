<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_MembershipExtras_Form_PaymentScheme extends CRM_Core_Form {

  /**
   * @var int
   */
  private $id;

  /**
   * @throws CRM_Core_Exception
   */
  public function preProcess() {
    if ($this->_action == CRM_Core_Action::UPDATE) {
      CRM_Utils_System::setTitle(E::ts('Edit Payment Scheme'));
      $this->id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, 0);
    }
    else {
      CRM_Utils_System::setTitle(E::ts('Add Payment Scheme'));
    }

    parent::preProcess();
  }

  public function buildQuickForm() {
    $this->add('text', 'name', E::ts('Name'), NULL, TRUE);
    $this->add('text', 'admin_title', E::ts('Admin Title'), NULL, TRUE);
    $this->add('textarea', 'admin_description', E::ts('Admin Description'));
    $this->add('text', 'public_title', E::ts('Public Title'), NULL, TRUE);
    $this->add('textarea', 'public_description', E::ts('Public Description'));
    $this->add(
      'select',
      'permission',
      'permission',
      [
        'public' => E::ts('Public'),
        'admin' => E::ts('Admin'),
      ],
      TRUE
    );
    $this->add('checkbox', 'enabled', E::ts('Enabled'), NULL, FALSE);
    $this->add('textarea', 'parameters', E::ts('Parameters'), NULL, TRUE);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    $params = [
      'name' => $values['name'],
      'admin_title' => $values['admin_title'],
      'admin_description' => $values['admin_description'],
      'public_title' => $values['public_title'],
      'public_description' => $values['public_description'],
      'permission' => $values['permission'],
      'enabled' => CRM_Utils_Array::value('enabled', $values, FALSE),
      'parameters' => $values['parameters'],
    ];

    if (!empty($this->id)) {
      $params['id'] = $this->id;
    }

    CRM_MembershipExtras_BAO_PaymentScheme::create($params);
    CRM_Core_Session::setStatus(ts('The payment scheme has been saved.'), ts('Saved'), 'success');

    parent::postProcess();
  }

  function setDefaultValues() {
    if (!$this->id) {
      return [];
    }

    $schemeObj = CRM_MembershipExtras_BAO_PaymentScheme::findById($this->id);
    $scheme = (array) $schemeObj;

    return [
      'id' => $scheme['id'],
      'name' => $scheme['name'],
      'admin_title' => $scheme['admin_title'],
      'admin_description' => $scheme['admin_title'],
      'public_title' => $scheme['public_title'],
      'public_description' => $scheme['public_description'],
      'permission' => $scheme['permission'],
      'enabled' => $scheme['enabled'],
      'parameters' => $scheme['parameters'],
      ];
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
