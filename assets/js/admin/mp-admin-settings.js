/* globals jQuery, ajaxurl */

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

function mp_settings_accordion_options() {
  const element = document.getElementById('mp-advanced-options');
  const elementBlock = document.getElementById('block-two');

  element.addEventListener('click', function () {
    this.classList.toggle('active');
    const panel = this.nextElementSibling;

    if (panel.style.display === 'block') {
      panel.style.display = 'none';
    } else {
      panel.style.display = 'block';
    }

    if (!element.classList.contains('active') && !elementBlock.classList.contains('mp-settings-flex-start')) {
      elementBlock.classList.toggle('mp-settings-flex-start');
      element.textContent = 'Show advanced options';
    } else {
      element.textContent = 'Hide advanced options';
      elementBlock.classList.remove('mp-settings-flex-start');
    }
  });
}

function mp_validate_credentials() {
  document
    .getElementById('mp-access-token-prod')
    .addEventListener('change', function() {
      const self = this;
      jQuery
        .post(
          ajaxurl,
          {
            is_test: false,
            access_token: this.value,
            action: 'mp_validate_credentials',
          },
          function() {}
        )
        .done(function(response) {
          if (response.success) {
            self.classList.add('mp-credential-feedback-positive');
            self.classList.remove('mp-credential-feedback-negative');
          } else {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          }
        })
        .fail(function() {
          self.classList.remove('mp-credential-feedback-positive');
          self.classList.add('mp-credential-feedback-negative');
        });
    });

  document
    .getElementById('mp-access-token-test')
    .addEventListener('change', function() {
      const self = this;
      if (this.value === '') {
        self.classList.remove('mp-credential-feedback-positive');
        self.classList.remove('mp-credential-feedback-negative');
      } else {
        jQuery
          .post(
            ajaxurl,
            {
              is_test: true,
              access_token: this.value,
              action: 'mp_validate_credentials',
            },
            function() {}
          )
          .done(function(response) {
            if (response.success) {
              self.classList.add('mp-credential-feedback-positive');
              self.classList.remove('mp-credential-feedback-negative');
            } else {
              self.classList.remove('mp-credential-feedback-positive');
              self.classList.add('mp-credential-feedback-negative');
            }
          })
          .fail(function() {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          });
      }
    });

  document
    .getElementById('mp-public-key-prod')
    .addEventListener('change', function() {
      const self = this;
      jQuery
        .post(
          ajaxurl,
          {
            is_test: false,
            public_key: this.value,
            action: 'mp_validate_credentials',
          },
          function() {}
        )
        .done(function(response) {
          if (response.success) {
            self.classList.add('mp-credential-feedback-positive');
            self.classList.remove('mp-credential-feedback-negative');
          } else {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          }
        })
        .fail(function() {
          self.classList.remove('mp-credential-feedback-positive');
          self.classList.add('mp-credential-feedback-negative');
        });
    });

  document
    .getElementById('mp-public-key-test')
    .addEventListener('change', function() {
      const self = this;
      if (this.value === '') {
        self.classList.remove('mp-credential-feedback-positive');
        self.classList.remove('mp-credential-feedback-negative');
      } else {
        jQuery
          .post(
            ajaxurl,
            {
              is_test: true,
              public_key: this.value,
              action: 'mp_validate_credentials',
            },
            function() {}
          )
          .done(function(response) {
            if (response.success) {
              self.classList.add('mp-credential-feedback-positive');
              self.classList.remove('mp-credential-feedback-negative');
            } else {
              self.classList.remove('mp-credential-feedback-positive');
              self.classList.add('mp-credential-feedback-negative');
            }
          })
          .fail(function() {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          });
      }
    });
}

function mp_settings_screen_load() {
  mp_get_requirements();
  mp_settings_accordion_start();
  mp_settings_accordion_options();
  mp_validate_credentials();
}
