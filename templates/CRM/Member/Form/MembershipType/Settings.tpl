<script type="text/javascript">
  {literal}
  (function($) {

    $(function() {
      moveFields();
      eventListener();
    });

    function moveFields() {
      $('#membership_type_annual_pro_rata_calculation').insertAfter($('#month_fixed_rollover_day_row'));
    }

    function eventListener() {
      let annualProRataCalculationElement = $('#membership_type_annual_pro_rata_calculation');
      $('#period_type option').each(function() {
        if (this.selected && this.value === 'fixed') {
          annualProRataCalculationElement.show();
        }
      });
      $('#period_type').change(() => {
        if ($('#period_type').val() === 'fixed') {
          annualProRataCalculationElement.show();
        } else {
          annualProRataCalculationElement.hide();
        }
      });
    }

  })(CRM.$);
  {/literal}
</script>

<table>
  <tr id="membership_type_annual_pro_rata_calculation" style="display: none;">
    <td class="label">{$form.membership_type_annual_pro_rata_calculation.label}</td>
    <td>{$form.membership_type_annual_pro_rata_calculation.html}</td>
  </tr>
</table>
