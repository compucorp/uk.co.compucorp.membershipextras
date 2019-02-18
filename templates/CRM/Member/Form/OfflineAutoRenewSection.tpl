<script type="text/javascript">
  {literal}
    CRM.$(function($) {
      CRM.$('#offline_autorenew_fields').insertBefore(CRM.$('#recordContribution'));
    });
  {/literal}
</script>

<table class="form-layout-compressed">
  <tr id="offline_autorenew_fields">
    <td class="label">
      {$form.offline_auto_renew.label}
    </td>
    <td class="html-adjust">
      {$form.offline_auto_renew.html}
      {if $form.offline_auto_renew.frozen}
        {$form.membership_is_already_autorenew.html}
        {ts}This membership is already set up to auto-renew. You can still choose to make an additional renewal payment below.{/ts}
      {/if}
    </td>
  </tr>
</table>
