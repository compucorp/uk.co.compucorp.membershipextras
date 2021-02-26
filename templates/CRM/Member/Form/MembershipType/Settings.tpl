<script type="text/javascript">
  {literal}
  (function($) {

    $(function() {
      moveFields();
      initialFieldValues();
      eventListener();
    });

    /**
     * Re-organise fields
     */
    function moveFields() {
      $('#membership_type_annual_pro_rata_calculation').insertAfter($('#month_fixed_rollover_day_row'));
      $('.crm-membership-type-form-block-period_type').insertBefore($('.crm-membership-type-form-block-duration_unit_interval'));
    }

    /**
     * Sets default values
     */
    function initialFieldValues() {
      $('#period_type option').each(function() {
        if (this.selected && this.value === 'fixed') {
          handleFixedPeriod();
        }
      });
    }

    /**
     * Listens to behaviors
     */
    function eventListener() {
      $('#period_type').change(() => {
        if ($('#period_type').val() === 'fixed') {
          handleFixedPeriod();
        } else {
          handleRollingPeriod();
        }
      });
      $('#fixed_period_start_day_M').change(() => {
       let selectedFixedPeriodStartM= $('#fixed_period_start_day_M').val();
        setFixedPeriodRolloverDayM(selectedFixedPeriodStartM);
        setFixedPeriodRolloverReadOnly();
      });
    }

    /**
     * Handles fields when Membership Type fixed period is selected
    */
    function handleFixedPeriod() {
      $('#month_fixed_rollover_day_row').hide();
      $('#membership_type_annual_pro_rata_calculation').show();
      let durationInterval = $('#duration_interval');
      durationInterval.val(1);
      durationInterval.prop( 'readonly', true );
      $('#duration_unit option[value="year"]').prop('selected', true);
      $('#fixed_period_start_day_d').val(1);
      $('#fixed_period_start_day_d option:not(:selected)').prop('disabled', true);
      $('#fixed_start_day_row').show();
      $('#fixed_rollover_day_row').show();
    }

    /**
     * Handles fields when Membership Type rolling period is selected
     */
    function handleRollingPeriod() {
      $('#membership_type_annual_pro_rata_calculation').hide();
      $('#duration_interval').prop( 'readonly', false );
      $(`#duration_interval`).val('');
    }

    /**
     * Sets fixed period rollover month field
     * Based on selected fixed period start month
     *
     * @param selectedFixedPeriodStartM
     */
    function setFixedPeriodRolloverDayM(selectedFixedPeriodStartM) {
      let fixedPeriodRolloverDayM = 12;
      if (selectedFixedPeriodStartM != 1) {
        fixedPeriodRolloverDayM = selectedFixedPeriodStartM -1;
      }
      $('#fixed_period_rollover_day_M').val(fixedPeriodRolloverDayM);
      setFixedPeriodRolloverDayD(fixedPeriodRolloverDayM);
    }

    /**
     * Sets fixed period rollover day field
     * based on selected fixed period rollover Month
     *
     * @param fixedPeriodRolloverDayM
     */
    function setFixedPeriodRolloverDayD(fixedPeriodRolloverDayM) {
      let fixedPeriodRolloverDayD;
      switch (fixedPeriodRolloverDayM) {
        case 2:
          fixedPeriodRolloverDayD = 28;
          break;
        case 1:
        case 3:
        case 5:
        case 7:
        case 8:
        case 10:
        case 12:
          fixedPeriodRolloverDayD = 31;
          break;
        default:
          fixedPeriodRolloverDayD = 30;
      }
      $('#fixed_period_rollover_day_d').val(fixedPeriodRolloverDayD);
    }

    /**
     * Makes fixed period rollover month and day readonly
     */
    function setFixedPeriodRolloverReadOnly() {
      $('#fixed_period_rollover_day_M option:not(:selected)').prop('disabled', true);
      $('#fixed_period_rollover_day_d option:not(:selected)').prop('disabled', true);
    }

  })(CRM.$);
  {/literal}
</script>

<table>
  <tr id="membership_type_annual_pro_rata_calculation" style="display: none;">
    <td class="label">
      {$form.membership_type_annual_pro_rata_calculation.label}
    </td>
    <td>{$form.membership_type_annual_pro_rata_calculation.html}
      <br/>
      <span class="description">
        {ts}Define how the fee of the first year will be calculated for those paying annually.{/ts}
        {help id="membership_type_annual_pro_rata_calculation" file="CRM/Member/Form/MembershipType/Settings.hlp"}
      </span>
    </td>
  </tr>
</table>
