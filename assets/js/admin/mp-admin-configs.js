(function () {

  window.addEventListener('load', function () {
    initConfigScreen();
  });

  function initConfigScreen() {
    if (!hasConfigurations()) {
      removeElements();
      return;
    }

    setHide();
    setTitleInputMaxLength();
    setTitleDescriptionStyle();
    makeCollapsibleAdvancedConfig('pix');
  }

  function hasConfigurations() {
    const settingsTable = document.querySelector('table.form-table');
    return settingsTable && settingsTable.hasChildNodes();
  }

  function removeElements() {
    const settingsTable = document.querySelector('table.form-table');
    settingsTable?.previousElementSibling.remove();
    settingsTable?.previousElementSibling.remove();
    settingsTable?.nextElementSibling.remove();
  }

  function setTitleInputMaxLength() {
    const titleInput = document.querySelectorAll('.limit-title-max-length');
    if (titleInput) {
      titleInput.forEach((element) => {
        element.setAttribute('maxlength', '85');
      });
    }
  }

  function setTitleDescriptionStyle() {
    const label = document.querySelectorAll('th.titledesc');

    for (let j = 0; j < label.length; j++) {
      label[j].classList.add('mp-field-text-title');

      if (label[j] && label[j].children[0] && label[j].children[0].children[0]) {
        label[j].children[0].children[0].classList.add('mp-field-text-subtitle');
      }
    }
  }

  function setHide() {
    document.querySelector('.wc-admin-breadcrumb').style.display = 'none';

    if (document.querySelector('.mp-header-logo')) {
      document.querySelector('.mp-header-logo').style.display = 'none';
    } else {
      const pElement = document.querySelectorAll('#mainform > p');
      pElement[0] ? (pElement[0].style.display = 'none') : null;
    }

    const h2s = document.querySelectorAll('h2');
    h2s[4] ? (h2s[4].style.display = 'none') : null;

    document.querySelectorAll('.mp-hidden-field-description').forEach((element) => {
      element.closest('tr').style.display = 'none';
    });
  }

  function makeCollapsibleAdvancedConfig(gateway) {
    var collapseTitle = document.querySelector(
      `#woocommerce_woo-mercado-pago-${gateway}_advanced_configuration_title`
    );
    collapseTitle.style.cursor = "pointer";

    var collapseDescription = document.querySelector(
      `#woocommerce_woo-mercado-pago-${gateway}_advanced_configuration_description`
    );
    collapseDescription.style.display = "none";

    var collapseContent = document.querySelector(
      `#woocommerce_woo-mercado-pago-${gateway}_advanced_configuration_description`
    ).nextElementSibling;
    collapseContent.style.display = "none";

    collapseTitle.innerHTML +=
      '<span class="mp-btn-collapsible" id="header_plus_2" style="display:block">+</span>\
      <span class="mp-btn-collapsible" id="header_less_2" style="display:none">-</span>';

    var plusHeaderSelector = document.querySelector("#header_plus_2");
    var lessHeaderSelector = document.querySelector("#header_less_2");

    collapseTitle.onclick = function () {
      if (collapseContent.style.display === "none") {
        collapseContent.style.display = "block";
        collapseDescription.style.display = "block";
        lessHeaderSelector.style.display = "block";
        plusHeaderSelector.style.display = "none";
      } else {
        collapseContent.style.display = "none";
        collapseDescription.style.display = "none";
        lessHeaderSelector.style.display = "none";
        plusHeaderSelector.style.display = "block";
      }
    };
  }

})();
