<?php

/**
 * Alters MembershipStatus Form by adding in_arrears and not_arrears events as
 * options.
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipStatus {

  /**
   * @var array
   *   Events that can be used to evaluate the start of a membership status
   */
  private $startEvents = [];

  /**
   * @var array
   *   Events that can be used to evaluate the end of a membership status
   */
  private $endEvents = [];

  /**
   * Initializes available start and end events.
   */
  public function __construct() {
    $this->startEvents = array_merge(
      CRM_Core_SelectValues::eventDate(),
      [
        'in_arrears' => ts('Membership is in arrears (Payment Plan)'),
        'not_arrears' => ts('Membership is no longer in arrears (Payment Plan)'),
      ]
    );

    $this->endEvents = array_merge(
      ['' => ts('- select -')],
      $this->startEvents
    );
  }

  /**
   * Alters given form by adding in_arrears and not_arrears events as options
   * for start_event and end_event fields.
   *
   * @param \CRM_Member_Form_MembershipStatus $form
   */
  public function buildForm(CRM_Member_Form_MembershipStatus &$form) {
    $form->removeAttribute('start_event');
    $form->removeAttribute('end_event');
    $form->add('select', 'start_event', ts('Start Event'), $this->startEvents, TRUE);
    $form->add('select', 'end_event', ts('End Event'), $this->endEvents);
  }

}
