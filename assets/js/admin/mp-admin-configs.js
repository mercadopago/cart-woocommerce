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
    handleMultipleCheckboxes();
    makeCollapsibleAdvancedConfig();
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
      const pElement = document.querySelectorAll('#mainform > p:not(.submit)');
      pElement[0] ? (pElement[0].style.display = 'none') : null;
    }

    const mainFormH2 = document.querySelector('#mainform > h2');
    mainFormH2.classList.length === 0 ? (mainFormH2.style.display = 'none') : null;

    document.querySelectorAll('.mp-hidden-field-description').forEach((element) => {
      element.closest('tr').style.display = 'none';
    });
  }

  function handleMultipleCheckboxes() {
    (function ($) {
      $('.mp-child').change(function () {
        // create var for parent .checkall and group
        const group = $(this).data('group');
        const checkall = $('.mp-selectall[data-group="' + group + '"]');

        // mark selectall as checked if some children are checked
        const someChecked = $('.mp-child[data-group="' + group + '"]:checked').length > 0;
        checkall.prop("checked", someChecked);
      }).change();
      // removing onbeforeunload so wordpress do not prompt confirmation on reload/exit
      window.onbeforeunload = '';
      // now adding browser's default prompt if some config was changed
      $('input, textarea, select, checkbox').on('change', function () {
        window.onbeforeunload = function (e) {
          e.preventDefault();
          return true;
        }
      });

      // clicking .checkall will check or uncheck all children in the same group
      $('.mp-selectall').click(function () {
        const group = $(this).data('group');
        $('.mp-child[data-group="' + group + '"]').prop('checked', this.checked).change();
      });
    }(window.jQuery));
  }

  function makeCollapsibleAdvancedConfig() {
    const collapseTitle = document.querySelector(
      '[id^="woocommerce_woo-mercado-pago"][id$="advanced_configuration_title"]'
    );

    const collapseDescription = document.querySelector(
      '[id^="woocommerce_woo-mercado-pago"][id$="advanced_configuration_description"]'
    );

    const collapseTable = document.querySelector(
      '[id^="woocommerce_woo-mercado-pago"][id$="advanced_configuration_description"]'
    ).nextElementSibling;

    collapseTitle.style.cursor = "pointer";
    collapseDescription.style.display = "none";
    collapseTable.style.display = "none";

    collapseTitle.innerHTML += makeCollapsibleOptions(
      "header_plus",
      "header_less"
    );

    const headerPlus = document.querySelector("#header_plus");
    const headerLess = document.querySelector("#header_less");

    collapseTitle.onclick = function () {
      if (collapseTable.style.display === "none") {
        collapseTable.style.display = "block";
        collapseDescription.style.display = "block";
        headerLess.style.display = "block";
        headerPlus.style.display = "none";
      } else {
        collapseTable.style.display = "none";
        collapseDescription.style.display = "none";
        headerLess.style.display = "none";
        headerPlus.style.display = "block";
      }
    };
  }

  function makeCollapsibleOptions(idPlus, idLess) {
    return (
      '<span class="mp-btn-collapsible" id="' +
      idPlus +
      '" style="display:block">+</span>\
      <span class="mp-btn-collapsible" id="' +
      idLess +
      '" style="display:none">-</span>'
    );
  }

})();
