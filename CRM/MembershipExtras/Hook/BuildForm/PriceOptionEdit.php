<?php

class CRM_MembershipExtras_Hook_BuildForm_PriceOptionEdit {


  /**
   * @var CRM_Core_Form
   */
  private $form;

  /**
   * @param \CRM_Core_Form $form
   */
  public function __construct(CRM_Core_Form $form) {
    $this->form = $form;
  }

  public function buildForm() {
    $this->disableNumberOfTerms();
  }

  /**
   * Only allow users to create terms
   * using the membership types themselves and
   * not also sell multiple terms via price sets.
   */
  private function disableNumberOfTerms() {
    if ($this->form->elementExists('membership_num_terms')) {
      $this->setToReadOnly('membership_num_terms');
    }

    foreach ($this->form->_elementIndex as $key => $index) {
      if (preg_match('/membership_num_terms\[[\d]+\]/', $key)) {
        $this->setToReadOnly($key);
      }
    }
  }

  /**
   * Prevents ediitng a form field
   *
   * @param string $field
   *  The form field to set to readonly.
   * @param mixed $value
   *  Thoe form feild default value.
   */
  private function setToReadOnly($field, $value = 1) {
    $numOfTermElement = $this->form->getElement($field);
    $numOfTermElement->setValue($value);
    $numOfTermElement->setAttribute('readonly', TRUE);
  }

}
