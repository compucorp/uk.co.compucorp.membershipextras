<?php
class CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment extends Exception {
  const ONE_YEAR_DURATION = "All membership types must have a duration of one year!";
  const PERIOD_TYPE = "All membership types must have same period type.";
  const SAME_PERIOD_START_DAY = "All Membership types must have same period start day";
  const QUARTERLY_NOT_SUPPORT = "Quarterly instalments for fixed period membership types are not supported.";
  const DURATION_INTERVAL = "Membership types must be 1 month, 1 year or 1 life time only";
  const DAY_DURATION = "Day duration unit is not supported";

}
