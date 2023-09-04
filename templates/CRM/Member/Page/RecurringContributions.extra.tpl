<script type="text/javascript">
var recurRowsPaymentSchemeField= {$recurRowsPaymentSchemeField};

{literal}
(function($) {
  // we look for the columns with the word "Every" which is hardcoded inside the
  // frequency column under the recurring contribution list page.
  $('#membership-recurring-contributions table tr td:contains("Every")').each(function(index, el) {
    if (recurRowsPaymentSchemeField[index]) {
      $(el).text('~ Payment Scheme ~');
    }
  });
})(CRM.$);
{/literal}
</script>
