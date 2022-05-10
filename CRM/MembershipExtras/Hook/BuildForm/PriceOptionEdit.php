<?php

class CRM_MembershipExtras_Hook_BuildForm_PriceOptionEdit {


  /**
   * @var CRM_Price_Form_Option
   */
  private $form;

  /**
   * @param \CRM_Price_Form_Option $form
   */
  public function __construct(CRM_Price_Form_Option $form) {
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
    if (!$this->form->elementExists('membership_num_terms')) {
      return;
    }

    $numOfTermElement = $this->form->getElement('membership_num_terms');
    $numOfTermElement->setValue(1);
    $numOfTermElement->setAttribute('readonly', TRUE);
  }

}
