jQuery(document).ready(function(){
  var NO_SELECTED = '0';
  var YES_SELECTED = '1';

  var LINK_TYPE_CONTRIBUTION_PAGE = '0';
  var LINK_TYPE_URL = '1';

  var membersOnlyEventCheckbox= jQuery("#is_members_only_event");
  var membersOnlyEventFields = jQuery("#members-only-event-fields");

  var purchaseButtonDisabledSection = jQuery("#purchase-button-disabled-section");
  var purchaseButtonEnabledSection = jQuery("#purchase-button-enabled-section");

  var contributionPageField = jQuery("#field-contribution-page-id");
  var purchaseURLField = jQuery("#field-purchase-membership-url");

  setInitialFieldValues();
  setFieldListeners();

  /**
   * Sets the initial field values and show/hide
   * the needed fields.
   */
  function setInitialFieldValues() {
    toggleMembersOnlyEventFields(membersOnlyEventCheckbox.attr("checked"));

    var purchaseMembershipButtonEnabled = jQuery("input[name='purchase_membership_button']:checked").val();
    togglePurchaseButtonFields(purchaseMembershipButtonEnabled);

    var purchaseLinkType = jQuery("input[name='purchase_membership_link_type']:checked").val();
    toggleLinkTypeFields(purchaseLinkType);
  }

  /**
   * Sets the fields event listeners
   */
  function setFieldListeners() {
    membersOnlyEventCheckbox.click(function(){
      toggleMembersOnlyEventFields(jQuery(this).attr("checked"));
    });

    jQuery("input[name='purchase_membership_button']").click(function(){
      togglePurchaseButtonFields(jQuery(this).val());
    });

    jQuery("input[name='purchase_membership_link_type']").click(function(){
      toggleLinkTypeFields(jQuery(this).val());
    });
  }

  /**
   * Shows/Hides the members-only events fields
   * based on 'Is members-only event ?' checkbox
   * value.
   *
   * @param isMembersOnlyEvent
   */
  function toggleMembersOnlyEventFields(isMembersOnlyEvent) {
    if (isMembersOnlyEvent){
      membersOnlyEventFields.show();
    } else {
      membersOnlyEventFields.hide();
    }
  }

  /**
   * Shows/Hides the related purchase membership
   * button fields.
   * If Yes is selected then allow the user to set
   * button label and the link.
   * If No is selected then show only the
   * notice message textarea.
   *
   * @param selectedOption
   */
  function togglePurchaseButtonFields(selectedOption) {
    switch (selectedOption) {
      case NO_SELECTED:
        purchaseButtonDisabledSection.show();
        purchaseButtonEnabledSection.hide();
        break;
      case YES_SELECTED:
        purchaseButtonDisabledSection.hide();
        purchaseButtonEnabledSection.show();
        break;
    }
  }

  /**
   * Shows contribution selectttion field
   * and hide the url field or vice-versa
   * based on the selected link type.
   *
   * @param linkType
   */
  function toggleLinkTypeFields(linkType) {
    switch (linkType) {
      case LINK_TYPE_CONTRIBUTION_PAGE:
        contributionPageField.show();
        purchaseURLField.hide();
        break;
      case LINK_TYPE_URL:
        contributionPageField.hide();
        purchaseURLField.show();
        break;
    }
  }
});
