/* globals jQuery, ajaxurl */

function clear_message() {
  document.querySelector('.mp-alert').remove();
}

function clear_element(element) {
  document.getElementById(element).remove();
}

function mp_msg_element(element, title, subTitle, link, msgLink, type) {
  const cardInfo = document.getElementById(element);

  const classCardInfo = document.createElement('div');
  classCardInfo.className = 'mp-card-info';
  classCardInfo.id = element.concat('-card-info');

  const cardInfoColor = document.createElement('div');
  cardInfoColor.className = 'mp-alert-color-'.concat(type);

  const cardBodyStyle = document.createElement('div');
  cardBodyStyle.className = 'mp-card-body-payments mp-card-body-size';

  const cardInfoIcon = document.createElement('div');
  cardInfoIcon.className = 'mp-icon-badge-warning';

  const titleElement = document.createElement('span');
  titleElement.className = 'mp-text-title';
  titleElement.appendChild(document.createTextNode(title));

  const subTitleElement = document.createElement('span');
  subTitleElement.className = 'mp-helper-test';
  subTitleElement.appendChild(document.createTextNode(subTitle));

  const cardInfoBody = document.createElement('div');
  cardInfoBody.appendChild(titleElement);

  if (link !== undefined) {
    const linkText = document.createElement('a');
    linkText.href = link;
    linkText.className = 'mp-settings-blue-text';
    linkText.appendChild(document.createTextNode(msgLink));
    linkText.setAttribute('target', '_blank');
    subTitleElement.appendChild(linkText);
  }

  cardInfo.appendChild(classCardInfo);
  cardInfoBody.appendChild(subTitleElement);
  cardBodyStyle.appendChild(cardInfoIcon);
  cardBodyStyle.appendChild(cardInfoBody);
  classCardInfo.appendChild(cardInfoColor);
  classCardInfo.appendChild(cardBodyStyle);

  if ('alert' === type) {
    setTimeout(clear_element, 10000, classCardInfo.id);
  }
}

function select_test_mode(test){
  const badge = document.getElementById('mp-mode-badge');
  const color_badge = document.getElementById('mp-orange-badge');
  const icon_badge = document.getElementById('mp-icon-badge');
  const helper_test = document.getElementById('mp-helper-test');
  const helper_prod = document.getElementById('mp-helper-prod');
  const title_helper_prod = document.getElementById('mp-title-helper-prod');
  const title_helper_test = document.getElementById('mp-title-helper-test');
  const badge_test = document.getElementById('mp-mode-badge-test');
  const badge_prod = document.getElementById('mp-mode-badge-prod');

  if (test) {
    badge.classList.remove('mp-settings-prod-mode-alert');
    badge.classList.add('mp-settings-test-mode-alert');

    color_badge.classList.remove('mp-settings-alert-payment-methods-green');
    color_badge.classList.add('mp-settings-alert-payment-methods-orange');

    icon_badge.classList.remove('mp-settings-icon-success');
    icon_badge.classList.add('mp-settings-icon-warning');

    mp_verify_alert_test_mode();

    helper_test.style.display = 'block';
    helper_prod.style.display = 'none';

    title_helper_test.style.display = 'block';
    title_helper_prod.style.display = 'none';

    badge_test.style.display = 'block';
    badge_prod.style.display = 'none';
  } else {
    const red_badge = document.getElementById('mp-red-badge');
    badge.classList.remove('mp-settings-test-mode-alert');
    badge.classList.add('mp-settings-prod-mode-alert');

    red_badge.style.display ='none';

    color_badge.classList.remove('mp-settings-alert-payment-methods-orange');
    color_badge.classList.add('mp-settings-alert-payment-methods-green');

    icon_badge.classList.remove('mp-settings-icon-warning');
    icon_badge.classList.add('mp-settings-icon-success');

    helper_test.style.display = 'none';
    helper_prod.style.display = 'block';

    title_helper_test.style.display = 'none';
    title_helper_prod.style.display = 'block';

    badge_test.style.display = 'none';
    badge_prod.style.display = 'block';
  }
}

function mp_verify_alert_test_mode() {
  if ((document.querySelector('input[name="mp-test-prod"]').checked) && (
      document.getElementById('mp-public-key-test').value === '' ||
      document.getElementById('mp-access-token-test').value === ''
  )) {
    document.getElementById('mp-red-badge').style.display ='block';
    return true;
  } else {
    document.getElementById('mp-red-badge').style.display ='none';
    return false;
  }
}

function mp_show_message(message, type, block) {
  const messageDiv = document.createElement('div');

  let card = '';
  let heading = '';

  switch (block) {
    case 'credentials':
      card = document.querySelector('.mp-message-credentials');
      heading = document.querySelector('.mp-heading-credentials');
      break;
    case 'store':
      card = document.querySelector('.mp-message-store');
      heading = document.querySelector('.mp-heading-store');
      break;
    case 'payment':
      card = document.querySelector('.mp-message-payment');
      heading = document.querySelector('.mp-heading-payment');
      break;
    case 'test_mode':
      card = document.querySelector('.mp-message-test-mode');
      heading = document.querySelector('.mp-heading-test-mode');
      break;
    default:
      card = '';
      heading = '';
  }

  type === 'error'
    ? (messageDiv.className = 'mp-alert mp-alert-danger mp-text-center mp-card-body')
    : (messageDiv.className = 'mp-alert mp-alert-success mp-text-center mp-card-body');

  messageDiv.appendChild(document.createTextNode(message));
  card.insertBefore(messageDiv, heading);

  setTimeout(clear_message, 3000);
}

function mp_validate_credentials_tips() {
  const icon_credentials = document.getElementById('mp-settings-icon-credentials');
  jQuery
    .post(
      ajaxurl,
      {
        action: 'mp_validate_credentials_tips'
      },
      function() {}
    )
    .done(function(response) {
      if (response.success) {
        icon_credentials.classList.remove('mp-settings-icon-credentials');
        icon_credentials.classList.add('mp-settings-icon-success');
      } else {
        icon_credentials.classList.remove('mp-settings-icon-success');
      }
    })
    .fail(function() {
      icon_credentials.classList.remove('mp-settings-icon-success');
    });
}

function mp_go_to_next_step(actualStep, nextStep, actualArrowId, nextArrowId) {
  const actual = document.getElementById(actualStep);
  const actualArrow = document.getElementById(actualArrowId);
  const next = document.getElementById(nextStep);
  const nextArrow = document.getElementById(nextArrowId);

  actual.style.display = 'none';
  next.style.display = 'block';
  actualArrow.classList.remove('mp-arrow-up');
  nextArrow.classList.add('mp-arrow-up');

  if (window.melidata && window.melidata.client && window.melidata.client.addStoreConfigurationsStepTimer) {
    switch (nextStep) {
      case 'mp-step-2':
        window.melidata.client.addStoreConfigurationsStepTimer({ step: 'business' });
        break;

      case 'mp-step-3':
        window.melidata.client.addStoreConfigurationsStepTimer({ step: 'payment_methods', sendOnClose: true });
        break;

      case 'mp-step-4':
        window.melidata.client.addStoreConfigurationsStepTimer({ step: 'mode' });
        break;

      default:
        break;
    }
  }
}

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

function mp_update_option_credentials() {
  document
    .getElementById('mp-btn-credentials')
    .addEventListener('click', function() {
      const msgAlert = document.getElementById('msg-info-credentials');
      if(msgAlert.childNodes.length>1){
        document.querySelector('.mp-card-info').remove();
      }

      jQuery
        .post(
          ajaxurl,
          {
            public_key_prod: document.getElementById('mp-public-key-prod').value,
            public_key_test: document.getElementById('mp-public-key-test').value,
            access_token_prod: document.getElementById('mp-access-token-prod').value,
            access_token_test: document.getElementById('mp-access-token-test').value,
            action: 'mp_update_option_credentials',
          },
          function() {}
        )
        .done(function(response) {
          if (response.success) {
            mp_verify_alert_test_mode();
            mp_show_message(response.data, 'success', 'credentials');
            mp_validate_credentials_tips();

            setTimeout(() => {
              mp_go_to_next_step('mp-step-1', 'mp-step-2', 'mp-credentials-arrow-up', 'mp-store-info-arrow-up');
            }, 3000);
          } else {
            const rad = document.querySelectorAll('input[name="mp-test-prod"]');
            const { message, subtitle, link, linkMsg, type, test_mode } = response?.data;

            mp_msg_element('msg-info-credentials', message, subtitle, link, linkMsg, type);

            if (test_mode === 'no') {
              rad[1].checked = true;
              select_test_mode(false);
            } else {
              rad[0].checked = true;
              select_test_mode(true);
            }
          }
        })
        .fail(function(error) {
          mp_show_message(error?.data, 'error', 'credentials');
        });
    });
}

function mp_settings_screen_load() {
  mp_get_requirements();
  mp_settings_accordion_start();
  mp_settings_accordion_options();
  mp_validate_credentials();
  mp_update_option_credentials();
  mp_validate_credentials_tips();
  mp_verify_alert_test_mode();
}
