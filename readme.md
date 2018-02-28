## "Membership Extras" CiviCRM Extension

This extension introduce changes to how recurring contribution and 
paying in installments for CiviCRM work, it is mainly meant to be used along
with "CiviCRM webform" module, these changes can be summarized as following : 

1- When the user decide to pay later for a Membership in multiple installments through
a CiviCRM webform, multiple contributions equal to the number of the installments 
will be created, but each time one of these  contributions get paid (completed), the
membership will be extended for one period, this extension fix this issue and ensure
that the membership does not get extended.

2- The extension also define a new payment processor and a new payment processor type,
and force the CiviCRM webform to treat it similar to "Pay Later" through
"hook_webform_civicrm_paylater_payment_processors_alter", this hook is defined by
the CiviCRM webform module and therefore it can be only  be implemented inside a drupal 
module, that's why there is a drupal module defined inside this extension called 
"CiviCRM Membership Extras" and it should also be enabled along with the extension.
