<?php
class CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment extends Exception {
  const ONE_YEAR_DURATION = "All membership types must have a duration of one year!";
  const SAME_PERIOD_START_DAY = "All Membership types must have same period start day";
  const QUARTERLY_NOT_SUPPORT = "Quarterly instalments for fixed period membership types are not supported.";
  const DURATION_INTERVAL = "Membership types must be 1 month, 1 year or 1 life time only";
  const DAY_DURATION = "Day duration unit is not supported";
  const SAME_PERIOD_AND_DURATION = "You have selected membership types with different period types (i.e. rolling and fixed) and/or different duration units. Please only select memberships with the same period types and duration units.";

}
