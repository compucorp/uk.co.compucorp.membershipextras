CRM.$(function ($) {

  $(function () {
    refreshRelatedTabsOnRelationshipUpdate();
  });

  function refreshRelatedTabsOnRelationshipUpdate() {
    waitForElement($, '#contact-rel',
      function(element) {
        if (isRelationshipTabActive() && $('#tab_contribute').length && $('#tab_member').length) {
          $('#contact-rel').off('crmPopupFormSuccess').on('crmPopupFormSuccess', function() {
            CRM.tabHeader.resetTab('#tab_contribute');
            CRM.tabHeader.resetTab('#tab_member', true);
          });
        }
      }
    );
  }

  function waitForElement($, elementPath, callBack) {
    (new MutationObserver(function(mutations) {
      callBack($(elementPath));
    })).observe(document.querySelector(elementPath), {
      attributes: true
    });
  }

  function isRelationshipTabActive() {
    return $('#contact-rel').length && $('#contact-rel').is(":visible");
  }
});
