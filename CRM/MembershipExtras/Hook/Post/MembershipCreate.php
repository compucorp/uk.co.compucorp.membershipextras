<?php

use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;

class CRM_MembershipExtras_Hook_Post_MembershipCreate {

  private $membership;

  public function __construct($membership) {
    $this->membership = $membership;
  }

  public function process() {
    $this->createInitialMembershipPeriod();
  }

  private function createInitialMembershipPeriod() {
    MembershipPeriod::createPeriodForMembership($this->membership->id);
  }

}
