<?php

use Civi\Core\Event\GenericHookEvent;

/**
 * @group headless
 */
class CRM_MembershipExtras_Service_CustomFieldsCopierTest extends BaseHeadlessTest {

  public function testExcludedFieldIsNotCopiedWhileOthersAre() {
    $contactId = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Copier',
      'last_name' => 'Test',
    ])['id'];

    $groupId = civicrm_api3('CustomGroup', 'create', [
      'title' => 'Copier Test Group',
      'extends' => 'Contribution',
    ])['id'];

    $excludedFieldId = civicrm_api3('CustomField', 'create', [
      'custom_group_id' => $groupId,
      'label' => 'Excluded Field',
      'html_type' => 'Text',
      'data_type' => 'String',
    ])['id'];

    $copiedFieldId = civicrm_api3('CustomField', 'create', [
      'custom_group_id' => $groupId,
      'label' => 'Copied Field',
      'html_type' => 'Text',
      'data_type' => 'String',
    ])['id'];

    $sourceId = civicrm_api3('Contribution', 'create', [
      'contact_id' => $contactId,
      'financial_type_id' => 1,
      'total_amount' => 10,
      'contribution_status_id' => 'Completed',
      'custom_' . $excludedFieldId => 'should-not-copy',
      'custom_' . $copiedFieldId => 'should-copy',
    ])['id'];

    $destinationId = civicrm_api3('Contribution', 'create', [
      'contact_id' => $contactId,
      'financial_type_id' => 1,
      'total_amount' => 10,
      'contribution_status_id' => 'Completed',
    ])['id'];

    // Exclude only the "excluded" field, via the hook.
    $listener = function (GenericHookEvent $event) use ($excludedFieldId) {
      $customFieldIds =& $event->customFieldIds;
      $customFieldIds[] = (int) $excludedFieldId;
    };
    Civi::dispatcher()->addListener(
      CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME,
      $listener
    );

    try {
      CRM_MembershipExtras_Service_CustomFieldsCopier::copy($sourceId, $destinationId, 'Contribution');
    }
    finally {
      Civi::dispatcher()->removeListener(
        CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME,
        $listener
      );
    }

    $destination = civicrm_api3('Contribution', 'getsingle', [
      'id' => $destinationId,
      'return' => ['custom_' . $excludedFieldId, 'custom_' . $copiedFieldId],
    ]);

    $this->assertEmpty(
      $destination['custom_' . $excludedFieldId] ?? '',
      'Excluded field must not be copied to the destination contribution'
    );
    $this->assertEquals(
      'should-copy',
      $destination['custom_' . $copiedFieldId] ?? '',
      'Non-excluded field should be copied to the destination contribution'
    );
  }

}
