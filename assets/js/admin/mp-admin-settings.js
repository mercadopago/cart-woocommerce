/* globals ajaxurl */

function mp_get_requirements() {
  jQuery.post(ajaxurl, {action: 'mp_get_requirements'}, function (response) {
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

function mp_settings_accordion_start() {
  let i;
  const acc = document.getElementsByClassName('mp-settings-title-align');

  for (i = 0; i < acc.length; i++) {
    acc[i].addEventListener('click', function () {
      this.classList.toggle('active');

      if ('mp-settings-margin-left' && 'mp-arrow-up') {
        let accordionArrow = null;

        for (let i = 0; i < this.childNodes.length; i++) {
          if (this.childNodes[i]?.classList?.contains('mp-settings-margin-left')) {
            accordionArrow = this.childNodes[i];
            break;
          }
        }

        accordionArrow?.childNodes[1]?.classList?.toggle('mp-arrow-up');
      }

      const panel = this.nextElementSibling;
      if (panel.style.display === 'block') {
        panel.style.display = 'none';
      } else {
        panel.style.display = 'block';
      }
    });
  }
}

function mp_settings_screen_load() {
  mp_get_requirements();
  mp_settings_accordion_start();
}
