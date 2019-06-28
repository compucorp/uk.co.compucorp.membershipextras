<script type="text/javascript">
  {literal}
  var optionSep = '|';

  (function ($) {

    $(function () {
      setProratedAmount();
      $('#start_date, #end_date, #membership_type_id_1, #price_set_id').change(() => {
        setProratedAmount();
      });
    });

    /**
     * Sets prorated amount for membership type and membership types in price sets.
     */
    function setProratedAmount() {
      cj("#prorated_label").hide();
      var isPriceSet = cj('#price_set_id').length > 0 && cj('#price_set_id').val();
      var memType = parseInt($('#membership_type_id_1').val());

      if (isPriceSet) {
        setProratedAmountForPriceSet();
      } else if (memType) {
        setProratedAmountForMembershipType();
      }
    }

    /**
     * Sets the prorated amount for a membership type depending on the selected dates.
     */
    function setProratedAmountForMembershipType() {
      var memTypeId = parseInt($('#membership_type_id_1').val());
      var memStartDate = $('#start_date').val();
      var memEndDate = $('#end_date').val();

      CRM.api3('MembershipType', 'getproratedamount', {
        "membership_type_id" : memTypeId,
        "start_date" : memStartDate,
        "end_date" : memEndDate,
        "is_fixed_membership" : true
      }).done(function(result) {
        if (result.is_error == 0) {
          var totalAmount = result.values.pro_rated_amount;
          cj("#total_amount").val(CRM.formatMoney(totalAmount, true));
          $('<span id="prorated_label" class="description"> Prorated for ' + result.duration_in_days + ' days</span>').insertAfter($('#total_amount'));
        }
      });
    }

    /**
     * Sets the prorated amount for membership types in a price set depending on the selected dates.
     */
    function setProratedAmountForPriceSet() {
      var priceSetId = cj('#price_set_id').length > 0 && cj('#price_set_id').val();
      var memStartDate = $('#start_date').val();
      var memEndDate = $('#end_date').val();

      CRM.api3('MembershipType', 'getproratedamountforpriceset', {
        "price_set_id" : priceSetId,
        "start_date" : memStartDate,
        "end_date" : memEndDate,
        "is_fixed_membership" : true
      }).done(function(result) {
        if (result.is_error == 0) {
          setProratedAmountsForPriceSetElements(result.values)
        }
      });
    }

    /**
     * Sets the label and price amount attribute for the individual membership
     * type price elements in a price set.
     */
    function setProratedAmountsForPriceSetElements(proratedAmounts) {
      cj("#priceset [price]").each(function () {
        var elementType =  cj(this).attr('type');
        if (this.tagName == 'SELECT' ) {
          elementType = 'select-one';
        }

        switch(elementType) {
          case 'checkbox':
            setProratedLabelForCheckBoxLineItem(this, proratedAmounts);
            setProratedAmountForCheckBoxLineItem(this, proratedAmounts);
            break;

          case 'radio':
            setProratedLabelForRadioLineItem(this, proratedAmounts);
            setProratedAmountForRadioLineItem(this, proratedAmounts);
            break;

          case 'select-one':
            setProratedLabelForSelectLineItem(this, proratedAmounts);
            setProratedAmountForSelectLineItem(this, proratedAmounts);
            break;
        }
      });
    }

    /**
     * Sets updated prorated label for a select item in price set
     */
    function setProratedLabelForSelectLineItem(priceElement, proratedAmounts) {
      eval( 'var priceOption = ' + cj(priceElement).attr('price') );
      cj(priceElement.options).each(function() {
        if (this.value && proratedAmounts[this.value]) {
          var priceOptionPart = priceOption[this.value].split(optionSep);
          var oldPrice = CRM.formatMoney(priceOptionPart[0], true);
          var optionText = cj(this).html();
          var newPrice = CRM.formatMoney(proratedAmounts[this.value].pro_rated_amount, true);
          optionText = optionText.replace(oldPrice, newPrice);
          cj(this).html(optionText);
        }
      });
    }

    /**
     * Sets updated prorated label for a checkbox item in price set
     */
    function setProratedLabelForCheckBoxLineItem(priceElement, proratedAmounts) {
      eval( 'var option = ' + cj(priceElement).attr('price') );
      if (proratedAmounts[option[0]]) {
        var optionPart = option[1].split(optionSep);
        var oldPrice = CRM.formatMoney(optionPart[0], true);
        var elementId = cj(priceElement).attr('id');
        var checkboxLabel = cj('label[for="' + elementId + '"]').html();
        var newPrice = CRM.formatMoney(proratedAmounts[option[0]].pro_rated_amount, true);
        var optionText = checkboxLabel.replace(oldPrice, newPrice);
        cj('label[for="' + elementId + '"]').html(optionText)
      }
    }

    /**
     * Sets updated prorated label for a radio item in price set
     */
    function setProratedLabelForRadioLineItem(priceElement, proratedAmounts){
      eval( 'var option = ' + cj(priceElement).attr('price') );
      if (proratedAmounts[cj(priceElement).val()]) {
        var priceValueId = cj(priceElement).val();
        var optionPart = option[1].split(optionSep);
        var oldPrice = CRM.formatMoney(optionPart[0], true);
        var elementId = cj(priceElement).attr('id');
        var radioLabel = cj('label[for="' + elementId + '"]').html();
        var newPrice = CRM.formatMoney(proratedAmounts[priceValueId].pro_rated_amount, true);
        var optionText = radioLabel.replace(oldPrice, newPrice);
        cj('label[for="' + elementId + '"]').html(optionText)
      }
    }

    /**
     * Updates the price attribute with the prorated price for a radio item in price set
     */
    function setProratedAmountForRadioLineItem(priceElement, proratedAmounts) {
      eval( 'var option = ' + cj(priceElement).attr('price') );
      if (proratedAmounts[cj(priceElement).val()]) {
        var priceValueId = cj(priceElement).val();
        var optionPart = option[1].split(optionSep);
        optionPart[0] = proratedAmounts[priceValueId].pro_rated_amount;
        option[1] = optionPart.join(optionSep);
        cj(priceElement).attr('price', JSON.stringify(option));
      }
      //trigger click so the total values are recalculated.
      if (cj(priceElement).is(':checked')) {
        cj(priceElement).click();
      }
    }

    /**
     * Updates the price attribute with the prorated price for a checkbox item in price set
     */
    function setProratedAmountForCheckBoxLineItem(priceElement, proratedAmounts) {
      eval( 'var option = ' + cj(priceElement).attr('price') );
      if (proratedAmounts[option[0]]) {
        var priceValueId = option[0];
        var optionPart = option[1].split(optionSep);
        optionPart[0] = proratedAmounts[priceValueId].pro_rated_amount;
        option[1] = optionPart.join(optionSep);
        cj(priceElement).attr('price', JSON.stringify( option ));
      }
      //trigger click so the total values are recalculated.
      if (cj(priceElement).is(':checked')) {
        cj(priceElement).click().click();
      }
    }

    /**
     * Updates the price attribute with the prorated price for a select item in price set
     */
    function setProratedAmountForSelectLineItem(priceElement, proratedAmounts) {
      eval( 'var priceOption = ' + cj(priceElement).attr('price') );
      Object.keys(priceOption).forEach(function(key) {
        if (proratedAmounts[key]) {
          var optionPart = priceOption[key].split(optionSep);
          optionPart[0] = proratedAmounts[key].pro_rated_amount;
          priceOption[key] = optionPart.join(optionSep);
          cj(priceElement).attr('price', JSON.stringify(priceOption));
        }
      });
      //trigger change so the total values are recalculated.
      cj(priceElement).change();
    }
  })(CRM.$);
  {/literal}
</script>
