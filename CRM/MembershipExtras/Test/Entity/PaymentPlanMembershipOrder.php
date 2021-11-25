<?php

/**
 * Class CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder.
 *
 * This is a fake test entity that mimic what
 * a payment plan membership order might look like
 * and which fields it contain.
 * Usually creating a membership order paid with a payment
 * plan will require you do that from UI using membership create
 * form, which results in creating many different entities
 * and updating many fields, some by CiviCRM core and some by
 * this extension, but for testing it is cumbersome to try to mimic
 * that for every test class, so the goal of this class
 * is to simplify that by abstracting the concept of a membership
 * payment plan order into this fake entity.
 */
class CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder {

  public $contactId;

  public $lineItems;

  public $membershipJoinDate;

  public $membershipStartDate;

  public $membershipEndDate;

  public $paymentPlanStartDate;

  public $nextContributionDate;

  public $financialType;

  public $paymentPlanFrequency;

  public $paymentMethod;

  public $paymentPlanStatus;

  public $paymentProcessor;

  public $autoRenew;

}
