<?php

interface CRM_MembershipExtras_SelectValues_SelectInterface {

  /**
   * Gets an array of the select list
   * values in 'value' => 'label' format.
   *
   * @return mixed
   */
  static function getAll();
}
