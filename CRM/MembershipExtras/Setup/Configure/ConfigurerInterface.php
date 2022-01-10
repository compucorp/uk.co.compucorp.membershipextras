<?php

/**
 * Describes the interface for things we want to configure
 * on existing entities or for configuring certain default settings
 * during the extension installations.
 *
 */
interface CRM_MembershipExtras_Setup_Configure_ConfigurerInterface {

  /**
   * Applies the configuration.
   */
  public function apply();

}
