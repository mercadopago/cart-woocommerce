(function () {

  window.addEventListener('load', function() {
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

})();
