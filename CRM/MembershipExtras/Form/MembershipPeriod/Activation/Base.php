<?php

use CRM_MembershipExtras_Form_MembershipPeriod_Activation_PreChangeWarnings as PreChangeWarnings;

/**
 * Base for forms to update membership periods.
 */
abstract class CRM_MembershipExtras_Form_MembershipPeriod_Activation_Base extends CRM_Core_Form {

  /**
   * ID of the period to be activated.
   *
   * @var int
   */
  protected $id;

  /**
   * Determines if we are doing an activation or
   * deactivation operation.
   *
   * @var boolean
   */
  protected $activationStatus;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('id', 'String', $this, TRUE);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->setFormTitle();

    $this->addButtons($this->getFormButtons());

    $period = CRM_MembershipExtras_BAO_MembershipPeriod::getMembershipPeriodById($this->id);

    $startDate = new DateTime($period->start_date);
    $period->start_date = $startDate->format('Y-m-d');

    if (!empty($period->end_date)) {
      $endDate = new DateTime($period->end_date);
      $period->end_date = $endDate->format('Y-m-d');
    }

    $this->assign('period', $period);
    $this->assign('preChangeWarnings', PreChangeWarnings::checkForQuickAction($period, $this->activationStatus));
  }

  /**
   * Sets the title for the form.
   */
  protected abstract function setFormTitle();

	/**
	 * Returns array of buttons for the form.
	 *
	 * @return array
	 */
  protected abstract function getFormButtons();

}
