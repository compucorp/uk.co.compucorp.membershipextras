<?php

use Civi\Core\Event\GenericHookEvent;

/**
 * Class CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields
 *
 * Lets other extensions declare custom field IDs that must never be copied
 * from the previous contribution onto the renewed contribution during offline
 * auto-renewal. Listeners append their field IDs to $event->customFieldIds.
 */
class CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields {

  const NAME = 'me.autorenew.excluded_custom_fields';

  /**
   * Custom field IDs to exclude from auto-renew copying.
   *
   * @var array
   */
  private array $customFieldIds;

  /**
   * @param array $customFieldIds
   */
  public function __construct(array &$customFieldIds) {
    $this->customFieldIds =& $customFieldIds;
  }

  /**
   * Dispatches the event.
   */
  public function dispatch() {
    $event = GenericHookEvent::create(['customFieldIds' => &$this->customFieldIds]);
    Civi::dispatcher()->dispatch(self::NAME, $event);
  }

}
