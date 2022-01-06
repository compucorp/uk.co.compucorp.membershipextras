<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigurerInterface;
use CRM_MembershipExtras_ExtensionUtil as ExtensionUtil;

/**
 * Adding unique constraint on all external ids custom group tables.
 */
class CRM_MembershipExtras_Setup_Configure_SetExternalIdFieldsToBeUnique implements ConfigurerInterface {

  public function apply() {
    CRM_Utils_File::sourceSQLFile(
      CIVICRM_DSN,
      ExtensionUtil::path() . DIRECTORY_SEPARATOR . 'sql/set_unique_external_ids.sql'
    );
  }

}
