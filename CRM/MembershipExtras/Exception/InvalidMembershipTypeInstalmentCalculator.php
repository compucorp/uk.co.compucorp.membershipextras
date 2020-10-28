<?php
class CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator extends Exception {
  const ONE_YEAR_DURATION = "All membership types must have a duration of one year!";
  const FIXED_PERIOD_TYPE = "All membership types must have a fixed duration.";
  const LAST_DAY_OF_MONTH = "Selected end date must be the last day of the month!";
  const SAME_PERIOD_START_DAY = "All Membership types must have same period start day";

}
