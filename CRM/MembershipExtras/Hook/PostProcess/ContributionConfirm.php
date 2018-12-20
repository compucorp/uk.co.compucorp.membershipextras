<?php

/**
 * Post processes contribution confirmation form, the last step in a
 * contribution page workflow.
 */
class CRM_MembershipExtras_Hook_PostProcess_ContributionConfirm {

  /**
   * Form that is being post processed.
   *
   * @var \CRM_Contribute_Form_Contribution_Confirm
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_PostProcess_ContributionConfirm constructor.
   *
   * @param \CRM_Contribute_Form_Contribution_Confirm $form
   */
  public function __construct(CRM_Contribute_Form_Contribution_Confirm $form) {
    $this->form = $form;
  }

  /**
   * Postprocesses form to creaate membership periods.
   */
  public function postProcess() {
    $membershipID = CRM_Utils_Array::value(
      'membershipID',
      $this->form->_params,
      NULL
    );

    if (!empty($membershipID)) {
      CRM_MembershipExtras_BAO_MembershipPeriod::createPeriodForMembership($membershipID);
    }
  }

}
