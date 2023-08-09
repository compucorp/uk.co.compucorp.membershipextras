<script type="text/javascript">
var activeRecurRowsPaymentSchemeField= {$activeRecurRowsPaymentSchemeField};
var inactiveRecurRowsPaymentSchemeField = {$inactiveRecurRowsPaymentSchemeField};

{literal}
(function($) {
  // we look for the columns with the word "Every" which is hardcoded inside the
  // recurring contribution tab frequency column.
  $('.crm-contact-contribute-recur-active tr td:contains("Every")').each(function(index, el) {
    if (activeRecurRowsPaymentSchemeField[index]) {
      $(el).text('~ Payment Scheme ~');
    }
  });

  $('.crm-contact-contribute-recur-inactive tr td:contains("Every")').each(function(index, el) {
    if (inactiveRecurRowsPaymentSchemeField[index]) {
      $(el).text('~ Payment Scheme ~');
    }
  });
})(CRM.$);
{/literal}
</script>
