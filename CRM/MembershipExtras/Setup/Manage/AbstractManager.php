<?php

/**
 * Describes the interface for entities that are
 * to be managed (created, removed ..etc) during the
 * extension installations, disabling ..etc.
 *
 */
abstract class CRM_MembershipExtras_Setup_Manage_AbstractManager {

  /**
   * Creates the entity.
   */
  abstract public function create();

  /**
   * Removes the entity.
   */
  abstract public function remove();

  /**
   * Deactivates the entity.
   */
  public function deactivate() {
    $this->toggle(FALSE);
  }

  /**
   * Activates the entity.
   */
  public function activate() {
    $this->toggle(TRUE);
  }

  /**
   * Activates/Deactivates the entity based on the passed status.
   * You just need to implement this method for active() and deactivate()
   * methods to work.
   *
   * @params boolean $status
   *  True to activate the entity, False to deactivate the entity.
   */
  abstract protected function toggle($status);

}
