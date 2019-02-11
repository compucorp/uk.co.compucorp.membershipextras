<script type="text/javascript">
  {literal}

  CRM.$(function($) {
    moveMembershipFormFields();
    initColorPicker();
    initColourFieldControls();
  });

  function moveMembershipFormFields() {
    // Move fields
    CRM.$('#membership_colour_field').insertAfter(CRM.$('#weight').parent().parent());
    CRM.$('#membership_colour_set').insertAfter(CRM.$('#weight').parent().parent());
  }

  function initColorPicker() {
    jQuery('#membership_colour').spectrum({
      showInput: true,
      preferredFormat: "hex3",
      // color: "#ECC",
    });
  }

  function hideColorField() {
    CRM.$('#membership_colour_field').hide();
  }

  function initColourFieldControls() {
    var set_membership_colour = CRM.$('#set_membership_colour');

    if (set_membership_colour.is(':checked')) {
      CRM.$('#membership_colour_field').show();
    }
    if (!set_membership_colour.is(':checked')) {
      hideColorField();
    }

    set_membership_colour.on('click', function() {
      if (this.checked) {
        CRM.$('#membership_colour_field').show();
      } else {
        hideColorField();
      }
    });
  }
  {/literal}
</script>

<table>
  <tr id="membership_colour_set">
    <td class="label">{$form.set_membership_colour.label}</td>
    <td>{$form.set_membership_colour.html}</td>
  </tr>
  <tr id="membership_colour_field">
    <td class="label">{$form.membership_colour.label}</td>
    <td>{$form.membership_colour.html}</td>
  </tr>
</table>
