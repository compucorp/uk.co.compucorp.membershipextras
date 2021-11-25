<?php

use CRM_MembershipExtras_DTO_ScheduleInstalmentAmount as InstalmentAmount;

class CRM_MembershipExtras_DTO_ScheduleInstalment {

  private $instalmentDate;
  private $instalmentAmount;

  /**
   * @return DateTime
   */
  public function getInstalmentDate() {
    return $this->instalmentDate;
  }

  /**
   * @param DateTime $instalmentDate
   */
  public function setInstalmentDate(DateTime $instalmentDate) {
    $this->instalmentDate = $instalmentDate;
  }

  /**
   * @return \CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
   */
  public function getInstalmentAmount() {
    return $this->instalmentAmount;
  }

  /**
   * @param \CRM_MembershipExtras_DTO_ScheduleInstalmentAmount $instalmentAmount
   */
  public function setInstalmentAmount(InstalmentAmount $instalmentAmount) {
    $this->instalmentAmount = $instalmentAmount;
  }

}
