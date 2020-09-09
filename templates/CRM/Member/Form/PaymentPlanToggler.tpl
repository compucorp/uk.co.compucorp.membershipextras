<script type="text/javascript">
  {literal}
  let selectedPriceValueIds = [];

  (function ($) {
    {/literal}
    const togglerValue = '{$contribution_type_toggle}';
    {literal}

    /**
     * Perform changes on form to add payment plan as an option to pay for
     * membership.
     */
    $(function () {
      moveMembershipFormFields();
      setMembershipFormEvents();
      initializeMembershipForm();
    });

    /**
     * Leaves form in a consistent state after the fields have been moved and the
     * new events added.
     */
    function initializeMembershipForm () {
      selectPaymentPlanTab(togglerValue);
    }

    /**
     * Reorders fields within the form to follow a coherent logic with the new
     * payment plan fields.
     */
    function moveMembershipFormFields () {
      $('#contributionTypeToggle').insertBefore($('#is_different_contribution_contact').parent().parent());
      $('#recordContribution legend:first').html('Contribution and Payment Plan');
      $('#payment_plan_schedule_row').insertAfter($('#financial_type_id').parent().parent());
      $('span.crm-error').css('display', 'none');
      $('label span.crm-error').css('display', 'inline');
      $('#payment_plan_fields_tabs').insertBefore($('#payment_plan_schedule_row').closest('table'));
    }

    /**
     * Selects Payment Plan tab and syncs the selection with radiobuttons
     *
     * @param {String} tabOptionId
     */
    function selectPaymentPlanTab (tabOptionId) {
      const allTabsSelector = '#payment_plan_fields_tabs li';
      const tabSelector = `${allTabsSelector}[data-selector=${tabOptionId}]`;

      $(allTabsSelector).removeClass('ui-tabs-active');
      $(tabSelector).addClass('ui-tabs-active');
      $('[name=contribution_type_toggle]').val(tabOptionId);
      updateContributionPaymentPlanView(tabOptionId);
    }

    /**
     * Gets Membership type details based on the selected Membership Type and price set.
     */
    function setScheduleEvents () {
      $('#total_amount, #membership_type_id_1').change(() => {
        const isPriceSet = $('#price_set_id').length > 0 && $('#price_set_id').val();
        if (isPriceSet) {
          return;
        }
        const memType = parseInt($('#membership_type_id_1').val());
        CRM.api3('MembershipType', 'get', {
          "sequential": 1,
          "id": memType
        }).then(function (result) {
          if (result.is_error == 0) {
            setPaymentPlanScheduleOption(result.values);
          } else {
            CRM.alert(result.error_message, 'Error', 'error');
          }
        });
      });
    }

    /**
     * Sets PaymentPlan Schedule Options based on the values
     */
    function setPaymentPlanScheduleOption (values) {
      let durationUnit = values[0].duration_unit;
      let periodType = values[0].period_type;
      if (durationUnit == 'month') {
        setScheduleOptions(['monthly']);
        return;
      }
      if (periodType == 'fixed') {
        setScheduleOptions(['monthly', 'annual']);
        return;
      }
      setScheduleOptions();
    }

    /**
     * Displays select schedule options based on parameters.
     */
    function setScheduleOptions (optionsToDisplay = []) {
      let defaultOptions = {
        monthly: '{/literal}{ts}Monthly{/ts}{literal}',
        quarterly: '{/literal}{ts}}Quarterly{/ts}{literal}',
        annual: '{/literal}{ts}Annual{/ts}{literal}'
      };
      if (optionsToDisplay.length > 0) {
        Object.keys(defaultOptions).forEach(key => {
          if (!optionsToDisplay.includes(key)) {
            delete defaultOptions[key];
          }
        });
      }
      $('#payment_plan_schedule').empty();
      $.each(defaultOptions, function(key, value) {
        $('#payment_plan_schedule')
                .append($("<option></option>")
                        .attr("value",key)
                        .text(value));
      });
    }

    /**
     * Creates events that modify the behaviour of the form:
     */
    function setMembershipFormEvents () {
      setupPayPlanTogglingEvents();
      setScheduleEvents();
    }

    /**
     * Adds events that enable toggling contribution/payment plan selection:
     * - Reloads original total amount if payment plan is selected.
     * - Changes total_amount field to readonly if payment plan is selected.
     * - Shows installments, frequency and frequency unit fields if payment plan
     *   is selected.
     * - Hides transaction id if payment plan is selected.
     * - Resets form to original state if contribution is selected.
     */
    function setupPayPlanTogglingEvents () {
      $('#payment_plan_fields_tabs li').click(function () {
        const tabOptionId = $(this).attr('data-selector');

        selectPaymentPlanTab(tabOptionId);
      });
    }

    /**
     * Updates the view of the Contribution / Payment Plan form
     * depending on the selected tab
     *
     * @param {String} tabOptionId
     */
    function updateContributionPaymentPlanView (tabOptionId) {
      if (tabOptionId === 'contribution') {
        $('#payment_plan_schedule_row').hide();
        $('.crm-membership-form-block-trxn_id').show();
        $('.crm-membership-form-block-receive_date').show();
        $('.crm-membership-form-block-total_amount').show();
        $('.crm-membership-form-block-financial_type_id').show();
        $('.crm-membership-form-block-contribution_status_id').show();
        $('.crm-membership-form-block-payment_instrument_id')
                .insertBefore('.crm-membership-form-block-contribution_status_id');
      } else if (tabOptionId === 'payment_plan') {
        $('#payment_plan_schedule_row').show();
        $('.crm-membership-form-block-trxn_id').hide();
        $('.crm-membership-form-block-receive_date').hide();
        $('.crm-membership-form-block-total_amount').hide();
        $('.crm-membership-form-block-financial_type_id').hide();
        $('.crm-membership-form-block-contribution_status_id').hide();
        $('.crm-membership-form-block-payment_instrument_id')
                .insertBefore('.crm-membership-form-block-contribution-contact');
        if ($('#membership_type_id_1').val()) {
          $('#membership_type_id_1').change();
        }
      }
    }

  })(CRM.$);
  {/literal}
</script>

<div id="payment_plan_fields_tabs">
  <input name="contribution_type_toggle" type="hidden">
  <div class="ui-tabs">
    <ul class="ui-tabs-nav ui-helper-clearfix">
      <li class="crm-tab-button ui-corner-top ui-tabs-active" data-selector="contribution">
        <a href="#contribution-subtab">{ts}Contribution{/ts}</a>
      </li>
      <li class="crm-tab-button ui-corner-top" data-selector="payment_plan">
        <a href="#payment_plan-subtab">{ts}Payment Plan{/ts}</a>
      </li>
    </ul>
  </div>
</div>

<table id="payment_plan_fields">
  <tr id="payment_plan_schedule_row">
    <td class="label" nowrap>
      {$form.payment_plan_schedule.label}
    </td>
    <td nowrap>
      {$form.payment_plan_schedule.html}
    </td>
  </tr>
  </tr>
</table>
