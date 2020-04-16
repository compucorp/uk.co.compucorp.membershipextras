<?php

class CRM_MembershipExtras_Hook_BuildForm_ContributionEdit {

  public function buildForm() {
    $this->addPreventCompletingContributionJSFile();
  }

  private function addPreventCompletingContributionJSFile() {
    CRM_Core_Resources::singleton()->addScriptFile('uk.co.compucorp.membershipextras', 'js/preventCompletingContribution.js');
  }

}
