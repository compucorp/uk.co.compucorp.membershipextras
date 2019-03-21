{* Check if the online registration for this event is allowed, show notification message otherwise *}
<div class="crm-block crm-form-block crm-event-manage-membersonlyevent-form-block">
{if $isOnlineRegistration == 1}
  {* HEADER *}
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  <div class="crm-section">
    <div class="label">{$form.is_members_only_event.label}</div>
    <div class="content">{$form.is_members_only_event.html}</div>
    <div class="clear"></div>
  </div>

  <div id="members-only-event-fields">
    <div class="crm-section">
      <div class="label">{$form.allowed_membership_types.label} {help id="allowed-membership-types" file="CRM/MembersOnlyEvent/Form/MembersOnlyEventTab"}</div>
      <div class="content">{$form.allowed_membership_types.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.purchase_membership_button.label}</div>
      <div class="content">{$form.purchase_membership_button.html}</div>
      <div class="clear"></div>
    </div>

    <div id="purchase-button-disabled-section">
      <div class="crm-section">
        <div class="label">{$form.notice_for_access_denied.label}</div>
        <div class="content">{$form.notice_for_access_denied.html}</div>
        <div class="clear"></div>
      </div>
    </div>

    <div id="purchase-button-enabled-section">
      <div class="crm-section">
        <div class="label">{$form.purchase_membership_button_label.label}</div>
        <div class="content">{$form.purchase_membership_button_label.html}</div>
        <div class="clear"></div>
      </div>

      <div class="crm-section">
        <div class="label">{$form.purchase_membership_link_type.label}</div>
        <div class="content">
          {$form.purchase_membership_link_type.html}
          <span id="field-contribution-page-id">{$form.contribution_page_id.html}</span>
          <span id="field-purchase-membership-url">{$form.purchase_membership_url.html}</span>
        </div>
        <div class="clear"></div>
      </div>
    </div>
  </div>
</div>

  {* FOOTER *}
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

{else}
  <div id="help">{ts}Online registration tab needs to be enabled first.{/ts}</div>
{/if}

{crmScript ext="com.compucorp.membersonlyevent" file="js/CRM/Form/MembersOnlyEventTab.js"}
