<script type="text/javascript">
  {literal}

  (function ($) {
    {/literal}
    const membershipextrasTaxRatesStr = '{$taxRates}';
    const membershipextrasTaxTerm = '{$taxTerm}';
    const membershipextrasCurrency = '{$currency}';
    var optionSep = '|';
    {literal}
    const membershipextrasTaxRates = membershipextrasTaxRatesStr !== ''
      ? JSON.parse(membershipextrasTaxRatesStr)
      : [];

    $(function () {
      setProratedAmount();

      $('#start_date').change(function () {
        var memTypeId = parseInt($('#membership_type_id_1').val());
        var isPriceSet = cj('#price_set_id').length > 0 && cj('#price_set_id').val();
        if (memTypeId && !isPriceSet) {
          setMembershipEndDate(this, memTypeId);
        }
      });

      $('#start_date, #end_date, #membership_type_id_1, #price_set_id').change(() => {
        if ($('#start_date').val() || $('#end_date').val()) {
          setProratedAmount();
        }
      });
    });

    /**
     * Sets the membership end date for a membership type
     */
    function setMembershipEndDate(startDate, memTypeId) {
      var startDateValue = cj(startDate).val();
      var memSinceDate = $('#join_date').val();
      CRM.api3('MembershipType', 'getdatesformembershiptype', {
        "membership_type_id" : memTypeId,
        "start_date" : startDateValue,
        "join_date" : memSinceDate,
      }).done(function(result) {
        if (result.is_error == 0) {
          cj('#end_date').val(result.values.end_date);
          cj('#end_date').next('.hasDatepicker').datepicker('setDate', new Date(result.values.end_date));
        }
      });
    }

    /**
     * Sets prorated amount for membership type and membership types in price sets.
     */
    function setProratedAmount() {
      cj("#prorated_label").hide();
      var isPriceSet = cj('#price_set_id').length > 0 && cj('#price_set_id').val();
      var memType = parseInt($('#membership_type_id_1').val());
      var memStartDate = $('#start_date').val();
      var memEndDate = $('#end_date').val();
      var memSinceDate = $('#join_date').val();

      if (isPriceSet) {
        setProratedAmountForPriceSet(memStartDate, memEndDate, memSinceDate);
      } else if (memType) {
        setProratedAmountForMembershipType(memStartDate, memEndDate, memSinceDate);
      }
    }

    /**
     * Sets the prorated amount for a membership type depending on the selected dates.
     *
     * @param {String} memStartDate
     * @param {String} memEndDate
     * @param {String} memSinceDate
     */
    function setProratedAmountForMembershipType(memStartDate, memEndDate, memSinceDate) {
      var memTypeId = parseInt(cj('#membership_type_id_1').val())
      CRM.api3('MembershipType', 'getproratedamount', {
        "membership_type_id" : memTypeId,
        "start_date" : memStartDate,
        "end_date" : memEndDate,
        "join_date" : memSinceDate,
        "is_fixed_membership" : true
      }).done(function(result) {
        if (result.is_error == 0) {
          var totalAmount = result.values.pro_rated_amount;
          cj("#total_amount").val(CRM.formatMoney(totalAmount, true));
          $('#total_amount').change();
          $('<span id="prorated_label" class="description"> Prorated for ' + result.duration_in_days + ' days</span>').insertAfter($('#total_amount'));
        }
      });
    }

    /**
     * Sets the prorated amount for membership types in a price set depending on the selected dates.
     *
     * @param {String} memStartDate
     * @param {String} memEndDate
     * @param {String} memSinceDate
     */
    function setProratedAmountForPriceSet(memStartDate, memEndDate, memSinceDate) {
      var priceSetId = cj('#price_set_id').val();

      CRM.api3('MembershipType', 'getproratedamountforpriceset', {
        "price_set_id" : priceSetId,
        "start_date" : memStartDate,
        "end_date" : memEndDate,
        "join_date" : memSinceDate,
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
     * Gets the Tax message for a prorated price set amount.
     */
    function getTaxLabelForPriceSetAmount(currentAmount, financialTypeID) {
      taxRate = membershipextrasTaxRates[financialTypeID];

      if (taxRate !== undefined) {
        taxAmount = (currentAmount * (taxRate / 100)) / (1 + (taxRate / 100));
        taxAmount = isNaN(taxAmount) ? 0 : taxAmount.toFixed(2);

        return ` (Includes ${membershipextrasTaxTerm} of ${membershipextrasCurrency} ${taxAmount})`;
      }

      return '';
    }
    
    /**
     * Sets updated prorated label for a select item in price set
     */
    function setProratedLabelForSelectLineItem(priceElement, proratedAmounts) {
      eval( 'var priceOption = ' + cj(priceElement).attr('price') );
      eval( 'var priceFieldValues = ' + cj(priceElement).attr('data-price-field-values') );
      cj(priceElement.options).each(function() {
        if (this.value && proratedAmounts[this.value]) {
          var newPrice = CRM.formatMoney(proratedAmounts[this.value].pro_rated_amount);
          var taxMessage = getTaxLabelForPriceSetAmount(proratedAmounts[this.value].pro_rated_amount, proratedAmounts[this.value].financial_type_id);
          var fullLabel = priceFieldValues[this.value].label + ' - ' + newPrice + taxMessage;

          cj(this).html(fullLabel);
        }
      });
    }

    /**
     * Sets updated prorated label for a checkbox item in price set
     */
    function setProratedLabelForCheckBoxLineItem(priceElement, proratedAmounts) {
      eval( 'var option = ' + cj(priceElement).attr('price') );
      if (proratedAmounts[option[0]]) {
        var proratedAmount = proratedAmounts[option[0]];
        var optionPart = option[1].split(optionSep);
        var oldPrice = CRM.formatMoney(optionPart[0], true);
        var elementId = cj(priceElement).attr('id');
        var checkBoxLabelElement = cj('label[for="' + elementId + '"]');
        var checkboxLabel = checkBoxLabelElement.html();
        var newPrice = CRM.formatMoney(proratedAmount.pro_rated_amount, true);
        var optionText = checkboxLabel.replace(oldPrice, newPrice);
        cj('label[for="' + elementId + '"]').html(optionText);

        checkBoxLabelElement.find('.crm-price-amount-tax').html(
          getTaxLabelForPriceSetAmount(proratedAmount.pro_rated_amount, proratedAmount.financial_type_id)
        )
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
        var radioPriceElement = cj('label[for="' + elementId + '"]');
        var radioLabel = radioPriceElement.html();
        var newPrice = CRM.formatMoney(proratedAmounts[priceValueId].pro_rated_amount, true);
        var optionText = radioLabel.replace(oldPrice, newPrice);
        cj('label[for="' + elementId + '"]').html(optionText);

        radioPriceElement.find('.crm-price-amount-tax').html(
          getTaxLabelForPriceSetAmount(proratedAmounts[priceValueId].pro_rated_amount, proratedAmounts[priceValueId].financial_type_id)
        )
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
