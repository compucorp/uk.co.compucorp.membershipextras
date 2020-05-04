# Membership Extras for CiviCRM
## Do I need Membership Extras?
If your organisation have memberships then the answer is almost certainly: **Yes!**

Membership Extras is designed to overcome many challenges that prevent CiviCRM from being a great membership management system.

#### Membership fee in Instalments
Whilst CiviCRM allows you to have memberships that can have different durations (eg. monthly, annual, lifetime) CiviCRM doesn’t support actual payment by instalments. This is where an organisation will want to offer a membership that has a different duration to the payment duration. For example an annual membership that is paid in monthly or quarterly instalments. With Membership Extra’s you can!

#### Memberships in arrears
CiviCRM’s default handling of members being in “arrears” only supports a few use cases (specifically where members either do not renew at all) or where they do not pay before a grace period elapses. In order to really support payment by instalments we have extended the membership status rules, to also be able to change the status of a membership when a payment relating to the membership is overdue, so you can track members in arrears (and email them automatically!) before finally ending their membership benefits.

#### Membership periods (coming soon)
CiviCRM currently only records a single start date and end date for each membership throughout full history of the membership. Each period of membership, when it starts and ends are not accounted for.

With Membership Extras, any membership sign-up or renewal will create a new membership period recording the effective duration of the sign-up or renewal. You can expand any membership record to see all the period records including their start date, end date and status.

There will also be some enhancement shipped with this feature to allow better automated control over the benefit delivery for membership.

#### Subscription management (coming soon)
This will allow staff to modify a members benefits during the current membership period - upgrading, downgrading or adding add-ons as needed. These will all flow through as part of the same billing order as the previous membership - i.e. as part of the same recurring contribution and hence will all be kept on the same invoice. This will lower transaction costs for organisations and tidy up billing processes.

#### Offline auto-renewal
CiviCRM has support for many payment processors, including several Direct Debit payment processors. With these “online” payment processors, when the membership comes to renew, the logic is actually managed by the payment processor in order to renew the membership and take next years payment. CiviCRM doesn’t however have any functionality for memberships where the payment is “offline” i.e. some Direct Debit processes or where you invoice clients in advance of receiving the payment. With Membership extras CiviCRM now fully supports offline automated renewal including sending email notifications with invoices for payment. We also have created a new offline batch direct debit export module which allows for full management of high volume direct debits through export processes.

## How do I get Membership Extras?
Membership Extras is designed to work with a patched version CiviCRM 5.24.6 plus. If you are on an earlier version of CiviCRM, you will need to upgrade your site first or contact info@compucorp.co.uk if you need assistance to do so.

The patched versions of CiviCRM includes bug fixes and functionalities required by membership extras, that are not yet part of CiviCRM core. You can find this compatible CiviCRM version here: [CiviCRM v5.24.6](https://bitbucket.org/compucorp/civicrm-core/downloads/civicrm-5.24.6-patch.7464023.tar.gz)

If your CiviCRM is already on the patched version of 5.24.6 plus and this is the first time you use an extension,  please see [Here](http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions "CiviCRM Extensions Installation") for full instructions and information on how to set and configure extensions.

### What Exactly is Being Patched?
Currently, we are maintaining two patches, required for Membership Extras extension to work. The first one will prevent a membership to be cancelled if one of the installments of a payment plan gets cancelled. The second one fixes a known bug in CiviCRM where a failed validation on the form to create a membership (eg. if you failed to fill in a required field), will cause taxes to be recalculated and added again to the membership fee.

#### Patch #1: Prevent Cancelled Installments From Cancelling Membership
TL;DR: We've added a way to intercept a membership update before it is stored in the database, to prevent the membership from being cancelled when a single installment in the payment plan is cancelled.

The technical overview of this is we've proposed to CiviCRM core team a new hook that runs before saving information to a table that is associated to an entity DAO. Currently, there is already a `hook_civicrm_postSave_table_name` hook that is run *after* a write operation is done on a table associated to a DAO. But on many cases it is useful to have a hook that runs before the write operation. This hook takes the form `hook_civicrm_preSave_table_name`, and we use it precisely to prevent cancelling the membership when a single installment in the payment plan is cancelled by checking the status of the plan and other installments *before* actually updating the membership.

The change is being discussed on [this lab ticket](https://lab.civicrm.org/dev/core/issues/161), if you'd like to participate, and help push for the change to be accepted into core.

The PR with the implementation for the feature can be checked [here](https://github.com/civicrm/civicrm-core/pull/12253).

#### Patch #2: Prevent Taxes to be Recalculated and Added to Membership Fee Each Time a Validation Fails
There is a known bug in CiviCRM, tracked [here](https://lab.civicrm.org/dev/core/issues/778), where when creating a membership and clicking save, if a validation fails (say for example if the user skipped a required field), taxes would be recalculated and added again to the membership's fee. So, for example, if a membership's price was $100 with a 10% tax, the full price would be calculated to $110 before saving the membership. After the first validation error, the price would be recalculated to $121, then to $133.10 on the second failed validation, and so on every time the validation failed.

Our patch fixes this by making sure every time the validation fails, the membership's fee without tax is used to calculate taxes. This is a temporary fix, though, as CiviCRM core team has laid down new groundwork on which we can base a more permanent solution that can be part of CiviCRM core in the near future.

The PR with the bug fix can be seen [here](https://github.com/civicrm/civicrm-core/pull/15895).

### If I am a user
You can get the latest release of Membership Extras from [CiviCRM extension directory page](https://civicrm.org/extensions/membership-extras) or our [Github repository release page](https://github.com/compucorp/uk.co.compucorp.membershipextras/releases).

If you are using Drupal and you would like to use Membership Extras with Webform CiviCRM, you can simply download and install [the companion Drupal module](https://github.com/compucorp/webform_civicrm_membership_extras/releases) and there you have it!

### If I am a developer
You can get the bleeding edge version of the extension by downloading [the repository](https://github.com/compucorp/uk.co.compucorp.membershipextras) and checking out the master branch.

Also, the repository of our webform companion Drupal module is [here](https://github.com/compucorp/webform_civicrm_membership_extras).

## Do I need to configure Membership Extras?
Membership Extras is a plug and play extension. Most of the generic functionality works out of the box.

There are a few exceptions where we chose to not presumptively configure for you during the extension installation mainly due to those configurations vary largely from organisation to organisation.

#### 1. Offline auto-renew
If you would like to allow those offline memberships which opted into auto-renew to be renewed by the system automatically, you will need to enable the “Renew offline auto-renewal memberships” scheduled job.

With admin permissions,  go to **Administer -> System Settings -> Scheduled Jobs**. Simply enabled the “Renew offline auto-renewal memberships” scheduled job. You also have a chance to configure the job to have a custom frequency.

You can also tell the system, to renew memberships a number of days in advance of membership end dates if you want to have the payment schedule for the next period ready in advance.

This can be configured by going to **Administer -> Payment Plan Settings** and specify the number of days you want the renewal to happen in advance in the “Days to renew in advance” setting.

A slightly more advanced setting for auto-renew handling is “Use latest price when auto renew membership?”. You can also find it in **Administer -> Payment Plan Settings**. By enabling this setting, the system will reflect the latest membership price for all memberships in the renewed contributions unless a membership is opted out.

#### 2. Membership status rules for payment in arrears
The system can help you deal with memberships that have missed payments. You can configure such a status rule in **Administer -> CiviMember -> Membership Status Rules**.

Two new membership status triggers are provided by the extension in order to handle the status of memberships depending on the fulfilment of their instalments:

* Membership is in arrears (Payment Plan) - any instalment contribution is not completed and the contribution received date is in the past
* Membership is no longer in arrears (Payment Plan) - all instalment contributions whose contribution received date are in the past are completed

In a typical example where a membership should be set to inactive after a payment is late for 30 days, the new status rule should have the following configurations:

1. Label: In Arrears
2. Start Event: Membership is in arrears (Payment Plan)
3. Start Event Adjustment: 30 days
4. End Event: Membership is no longer in arrears (Payment Plan)
5. Current Membership?: No

Please note that once you saved the new status rule, it will need to be moved to the top of the list in order to take effect (because payment check should have priority over date check).

We also recommend to enable the **Update Membership Statuses** scheduled job, so these rules are maintained and applied consistently throught all memberships.

#### 3. (Advanced) Offline payment processor for back office
If you happen to have another offline payment processor (payment processor that uses Payment_Manual class) and you would like all payment plan created in the back office to use that payment processor instead, you will be able to make that change in the “Offline payment processor for back office” setting by going to **Administer -> Payment Plan Settings**.

This is particularly useful if you are going to use our [Manual Direct Debit extension](https://github.com/compucorp/uk.co.compucorp.manualdirectdebit).

#### 4. (Advanced) Custom groups to be excluded when auto-renew
When new contributions are being generated by the auto-renewal scheduled job, the custom fields information from the previous contributions will also be copied over by default. This is to allow any additional information that forms a part of your organisation’s payment process being preserved during automated process.

A good example is, if one contribution has a link to a Direct Debit Mandate, the newly generated contributions under the same recurring contribution will likely need the same link. This is all taken care of by default.

However, if there are custom information on the contributions that should not be carried over to any new generated contributions. You are able to exclude them by going to **Administer -> Payment Plan Settings** and selecting them in “Custom groups to be excluded when auto-renew” setting.

## Support
CiviCRM extension directory page: [https://civicrm.org/extensions/membership-extras](https://civicrm.org/extensions/membership-extras)

Please contact the follow email if you have any question: <hello@compucorp.co.uk>

Paid support for this extension is available, please contact us either via Github or at <hello@compucorp.co.uk>
