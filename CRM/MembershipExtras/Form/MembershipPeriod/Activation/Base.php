<?php

use CRM_MembershipExtras_Service_ContributionUtilities as ContributionUtilities;

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
    $this->assign('isPaymentStarted', $this->isPaymentStarted($period));
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

  /**
   * Obtains payment entity status.
   *
   * @param \CRM_MembershipExtras_BAO_MembershipPeriod $period
   *
   * @return string
   */
  private function isPaymentStarted(CRM_MembershipExtras_BAO_MembershipPeriod $period) {
    $status = $this->getPaymentEntityStatus($period->payment_entity_table, $period->entity_id);
    $contributionStatusesValueMap = ContributionUtilities::getStatusesValueMap();
    $statusName = CRM_Utils_Array::value($status, $contributionStatusesValueMap, '');

    switch ($statusName) {
      case 'Completed':
      case 'In Progress':
      case 'Partially paid':
        $isPaymentStarted = TRUE;
        break;

      default:
        $isPaymentStarted = FALSE;
    }

    return $isPaymentStarted;
  }

  /**
   * Obtains the status of the payment entity associated to the given period.
   *
   * @param string $entityTable
   * @param int $entityID
   *
   * @return string
   */
  private function getPaymentEntityStatus($entityTable, $entityID) {
    $entity = $entityTable === 'civicrm_contribution_recur' ? 'ContributionRecur' : 'Contribution';

    try {
      $paymentEntity = civicrm_api3($entity, 'getsingle', [
        'id' => $entityID,
      ]);
    } catch (Exception $e) {
      return '';
    }

    return $paymentEntity['contribution_status_id'];
  }

}
