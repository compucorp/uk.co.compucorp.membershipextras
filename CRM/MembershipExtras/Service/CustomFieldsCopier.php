<?php

class CRM_MembershipExtras_Service_CustomFieldsCopier {

  public static function copy($sourceEntityId, $destinationEntityId, $entityName) {
    $customValues = CRM_Core_BAO_CustomValueTable::getEntityValues($sourceEntityId, $entityName);
    if (empty($customValues)) {
      return;
    }

    $customFieldsIdsToExcludeForAutoRenew = CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew();

    $customParams = [];
    foreach ($customValues as $key => $value) {
      if (!empty($value) && !in_array($key, $customFieldsIdsToExcludeForAutoRenew)) {
        $customParams["custom_{$key}"] = $value;
      }
    }

    if (!empty($customParams)) {
      $customParams['id'] = $destinationEntityId;
      civicrm_api3($entityName, 'create', $customParams);
    }
  }
}
