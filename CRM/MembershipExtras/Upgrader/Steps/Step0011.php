<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0011 {

  /**
   * Adds payment_scheme_id field to
   * payment_plan_extra_attributes custom group.
   *
   * @return void
   */
  public function apply() {
    $ppExtraAttributesCustomGroup = civicrm_api3('CustomGroup', 'get', [
      'extends' => 'ContributionRecur',
      'name' => 'payment_plan_extra_attributes',
    ]);

    if (!$ppExtraAttributesCustomGroup['count']) {
      return;
    }

    civicrm_api3('CustomField', 'create', [
      'custom_group_id' => $ppExtraAttributesCustomGroup['id'],
      'name' => 'payment_scheme_id',
      'label' => 'Payment Scheme',
      'data_type' => 'Int',
      'html_type' => 'Text',
      'required' => 0,
      'is_active' => 1,
      'is_searchable' => 1,
      'column_name' => 'payment_scheme_id',
      'is_view' => 1,
    ]);
  }

}
