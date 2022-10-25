/* globals ajaxurl */

function mp_get_requirements() {
  jQuery.post(ajaxurl, { action: 'mp_get_requirements' }, function (response) {
    const requirements = {
      ssl: document.getElementById('mp-req-ssl'),
      gd_ext: document.getElementById('mp-req-gd'),
      curl_ext: document.getElementById('mp-req-curl'),
    };

    for (let i in requirements) {
      const requirement = requirements[i];
      requirement.style = '';
      if (!response.data[i]) {
        requirement.classList.remove('mp-settings-icon-success');
        requirement.classList.add('mp-settings-icon-warning');
      }
    }
  });
}

function mp_settings_screen_load() {
  mp_get_requirements();
}
