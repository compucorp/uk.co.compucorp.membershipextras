<?php
class CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator extends Exception {
  const ONE_YEAR_DURATION = "All membership types must have a duration of one year!";
  const PERIOD_TYPE = "All membership types must have same period type.";
  const SAME_PERIOD_START_DAY = "All Membership types must have same period start day";

}
