<?php

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
    $this->id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, 0);
    $this->setFormTitle();
    parent::preProcess();
  }

  public function setFormTitle() {
    if ($this->_action == CRM_Core_Action::UPDATE) {
      $this->setTitle(ts('Edit Payment Scheme'));

      return;
    }

    if ($this->isDeleteContext()) {
      $this->setTitle(ts('Delete Payment Scheme'));

      return;
    }

    $this->setTitle(ts('Add Payment Scheme'));
  }

  public function buildQuickForm() {
    if ($this->isDeleteContext()) {

      $this->addButtons([
        [
          'type' => 'submit',
          'name' => ts('Delete'),
          'isDefault' => TRUE,
        ],
        [
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ],
      ]);

      return;
    }

    $this->add('text', 'name', ts('Name'), ['maxlength' => 250, 'size' => 50], TRUE);
    $this->add('text', 'admin_title', ts('Admin Title'), ['maxlength' => 250, 'size' => 50]);
    $this->add('textarea', 'description', ts('Description'), ['maxlength' => 500, 'cols' => 49]);
    $this->add('text', 'public_title', ts('Public Title'), ['maxlength' => 250, 'size' => 50], TRUE);
    $this->add('textarea', 'public_description', ts('Public Description'), ['maxlength' => 500, 'cols' => 49], TRUE);
    $this->addPaymentProcessorField();
    $this->add(
      'select',
      'permission',
      ts('Permission'),
      [
        'public' => ts('Public'),
        'admin' => ts('Admin'),
      ],
      TRUE
    );
    $this->add('checkbox', 'enabled', ts('Enabled'), NULL, FALSE);
    $this->add('textarea', 'parameters', ts('Parameters'), ['cols' => 49], TRUE);

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
    if ($this->isDeleteContext()) {
      $this->postProcessDeletion();

      return;
    }

    $this->postProcessFormSubmission();

    parent::postProcess();
  }

  public function setDefaultValues() {
    if (!$this->id) {
      return [];
    }

    $schemeObj = CRM_MembershipExtras_BAO_PaymentScheme::findById($this->id);
    $scheme = (array) $schemeObj;

    return [
      'id' => $scheme['id'],
      'name' => $scheme['name'],
      'admin_title' => $scheme['admin_title'],
      'description' => $scheme['description'],
      'public_title' => $scheme['public_title'],
      'public_description' => $scheme['public_description'],
      'payment_processor' => $scheme['payment_processor'],
      'permission' => $scheme['permission'],
      'enabled' => $scheme['enabled'],
      'parameters' => $scheme['parameters'],
    ];
  }

  /**
   * Gets the fields/elements defined in this form.
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

  private function addPaymentProcessorField() {
    $gocardlessProcessorType = civicrm_api3('PaymentProcessorType', 'get', [
      'return' => ["id"],
      'name' => "GoCardless",
    ]);

    if ($gocardlessProcessorType['count'] != 0) {
      $gocardlessPaymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
        'sequential' => 1,
        'is_active' => 1,
        'is_test' => 0,
        'payment_processor_type_id' => $gocardlessProcessorType['id'],
      ]);
    }

    $paymentProcessors = [];
    if (!empty($gocardlessPaymentProcessors['values'])) {
      foreach ($gocardlessPaymentProcessors['values'] as $gocardlessPaymentProcessors) {
        $paymentProcessors[$gocardlessPaymentProcessors['id']] = $gocardlessPaymentProcessors['name'];
      }
    }
    $select = ['' => ts('- select -')] + $paymentProcessors;

    $this->add('select', 'payment_processor', ts('Payment Processor'), $select, TRUE);
  }

  private function isDeleteContext() {
    return $this->_action == CRM_Core_Action::DELETE;
  }

  private function postProcessDeletion() {
    try {
      CRM_MembershipExtras_BAO_PaymentScheme::deleteByID($this->id);
      CRM_Core_Session::setStatus(ts('Details removed successfully.'), ts('Delete'), "success");
    }
    catch (CRM_Core_Exception $exception) {
      CRM_Core_Session::setStatus(ts($exception->getMessage()), ts("Payment Scheme cannot be deleted"), "error");
    }

  }

  private function postProcessFormSubmission() {
    $values = $this->exportValues();

    $params = [
      'name' => $values['name'],
      'admin_title' => $values['admin_title'],
      'description' => $values['description'],
      'public_title' => $values['public_title'],
      'public_description' => $values['public_description'],
      'payment_processor' => $values['payment_processor'],
      'permission' => $values['permission'],
      'enabled' => CRM_Utils_Array::value('enabled', $values, FALSE),
      'parameters' => $values['parameters'],
    ];

    if (!empty($this->id)) {
      $params['id'] = $this->id;
    }

    CRM_MembershipExtras_BAO_PaymentScheme::create($params);
    CRM_Core_Session::setStatus(ts('The payment scheme has been saved.'), ts('Saved'), 'success');
  }

}
