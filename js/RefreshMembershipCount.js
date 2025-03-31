CRM.$(function ($) {

  $(function () {
    refreshRelatedTabsOnRelationshipUpdate();
  });

  function refreshRelatedTabsOnRelationshipUpdate() {
    waitForElement($, '#contact-rel',
      function(element) {
        if (isRelationshipTabActive()) {
          $('#contact-rel').off('crmPopupFormSuccess').on('crmPopupFormSuccess', function() {
            if ($('#tab_contribute').length) {
              CRM.tabHeader.resetTab('#tab_contribute');
            }
            if ($('#tab_member').length) {
              CRM.tabHeader.resetTab('#tab_member', true);
            }
          });
        }
      }
    );
  }

  function waitForElement($, elementPath, callBack) {
    var target = document.querySelector(elementPath);
    if (!target) {
      return;
    }

    (new MutationObserver(function(mutations) {
      callBack($(elementPath));
    })).observe(target, {
      attributes: true
    });
  }

  function isRelationshipTabActive() {
    return $('#contact-rel').length && $('#contact-rel').is(":visible");
  }
});
