<?php
use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;

class CRM_MembershipExtras_Test_Fabricator_Group extends BaseFabricator {

  protected static $entityName = 'Group';

  protected static $defaultParams = [
    'title' => 'Test Group',
  ];

}
