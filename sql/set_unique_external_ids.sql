-- /*******************************************************
-- * Set External IDs as unique fields
-- *******************************************************/
ALTER TABLE civicrm_value_contribution_ext_id ADD CONSTRAINT unique_external_id UNIQUE(external_id);
ALTER TABLE civicrm_value_contribution_recur_ext_id ADD CONSTRAINT unique_external_id UNIQUE(external_id);
ALTER TABLE civicrm_value_membership_ext_id ADD CONSTRAINT unique_external_id UNIQUE(external_id);
ALTER TABLE civicrm_value_line_item_ext_id ADD CONSTRAINT unique_external_id UNIQUE(external_id);



