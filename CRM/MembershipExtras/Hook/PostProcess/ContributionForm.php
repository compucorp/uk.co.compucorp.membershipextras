<?php

/**
 * Class CRM_MembershipExtras_Hook_PostProcess_ContributionForm.
 *
 * Post-processes contribution form used to create, update and delete
 * contributions.
 */
class CRM_MembershipExtras_Hook_PostProcess_ContributionForm {

  /**
   * Form object that is being processed.
   *
   * @var \CRM_Contribute_Form_Contribution
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_PostProcess_ContributionForm constructor.
   *
   * @param \CRM_Contribute_Form_Contribution $form
   *   Form object that is being processed.
   */
  public function __construct(CRM_Contribute_Form_Contribution &$form) {
    $this->form = $form;
  }

  /**
   * Post-processes the form.
   */
  public function postProcess() {
    $isEditAction = $this->form->getAction() & CRM_Core_Action::UPDATE;
    if (!$isEditAction) {
      return;
    }

    $session = CRM_Core_Session::singleton();
    $statusMessages = $session->getStatus(TRUE);

    foreach ($statusMessages as $message) {
      $message['text'] = preg_replace('/The membership End Date is [^.]+./', '', $message['text']);
      CRM_Core_Session::setStatus(
        $message['text'],
        $message['title'],
        $message['type'],
        $message['options'] ?: []
      );
    }
  }
}
