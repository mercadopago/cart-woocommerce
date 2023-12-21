/* globals jQuery, ajaxurl, mercadopago_settings_admin_js_params */

function clearMessage() {
  document.querySelector('.mp-alert').remove();
}

function clearElement(element) {
  document.getElementById(element).remove();
}

function mpMsgElement(element, title, subTitle, link, msgLink, type) {
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
    setTimeout(clearElement, 10000, classCardInfo.id);
  }
}

function selectTestMode(test) {
  const badge = document.getElementById('mp-mode-badge');
  const colorBadge = document.getElementById('mp-orange-badge');
  const iconBadge = document.getElementById('mp-icon-badge');
  const helperTest = document.getElementById('mp-helper-test');
  const helperProd = document.getElementById('mp-helper-prod');
  const titleHelperProd = document.getElementById('mp-title-helper-prod');
  const titleHelperTest = document.getElementById('mp-title-helper-test');
  const badgeTest = document.getElementById('mp-mode-badge-test');
  const badgeProd = document.getElementById('mp-mode-badge-prod');

  if (test) {
    badge.classList.remove('mp-settings-prod-mode-alert');
    badge.classList.add('mp-settings-test-mode-alert');

    colorBadge.classList.remove('mp-settings-alert-payment-methods-green');
    colorBadge.classList.add('mp-settings-alert-payment-methods-orange');

    iconBadge.classList.remove('mp-settings-icon-success');
    iconBadge.classList.add('mp-settings-icon-warning');

    mpVerifyAlertTestMode();

    helperTest.style.display = 'block';
    helperProd.style.display = 'none';

    titleHelperTest.style.display = 'block';
    titleHelperProd.style.display = 'none';

    badgeTest.style.display = 'block';
    badgeProd.style.display = 'none';
  } else {
    const red_badge = document.getElementById('mp-red-badge').parentElement;
    badge.classList.remove('mp-settings-test-mode-alert');
    badge.classList.add('mp-settings-prod-mode-alert');

    red_badge.style.display = 'none';

    colorBadge.classList.remove('mp-settings-alert-payment-methods-orange');
    colorBadge.classList.add('mp-settings-alert-payment-methods-green');

    iconBadge.classList.remove('mp-settings-icon-warning');
    iconBadge.classList.add('mp-settings-icon-success');

    helperTest.style.display = 'none';
    helperProd.style.display = 'block';

    titleHelperTest.style.display = 'none';
    titleHelperProd.style.display = 'block';

    badgeTest.style.display = 'none';
    badgeProd.style.display = 'block';
  }
}

function mpVerifyAlertTestMode() {
  if ((document.querySelector('input[name="mp-test-prod"]').checked) && (
    document.getElementById('mp-public-key-test').value === '' ||
    document.getElementById('mp-access-token-test').value === ''
  )) {
    document.getElementById('mp-red-badge').parentElement.style.display = 'flex';
    return true;
  } else {
    document.getElementById('mp-red-badge').parentElement.style.display = 'none';
    return false;
  }
}

function mpShowMessage(message, type, block) {
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

  setTimeout(clearMessage, 3000);
}

function mpValidateCredentialsTips() {
  const iconCredentials = document.getElementById('mp-settings-icon-credentials');
  jQuery
    .post(
      ajaxurl,
      {
        action: 'mp_validate_credentials_tips',
        nonce: mercadopago_settings_admin_js_params.nonce,
      },
      function () {
      }
    )
    .done(function (response) {
      if (response.success) {
        iconCredentials.classList.remove('mp-settings-icon-credentials');
        iconCredentials.classList.add('mp-settings-icon-success');
      } else {
        iconCredentials.classList.remove('mp-settings-icon-success');
      }
    })
    .fail(function () {
      iconCredentials.classList.remove('mp-settings-icon-success');
    });
}

function mpValidateStoreTips() {
  const iconStore = document.getElementById('mp-settings-icon-store');
  jQuery
    .post(
      ajaxurl,
      {
        action: 'mp_validate_store_tips',
        nonce: mercadopago_settings_admin_js_params.nonce,
      },
      function () {
      }
    )
    .done(function (response) {
      if (response.success) {
        iconStore.classList.remove('mp-settings-icon-store');
        iconStore.classList.add('mp-settings-icon-success');
      } else {
        iconStore.classList.remove('mp-settings-icon-success');
      }
    })
    .fail(function () {
      iconStore.classList.remove('mp-settings-icon-success');
    });
}

function mpValidatePaymentTips() {
  const iconPayment = document.getElementById('mp-settings-icon-payment');
  jQuery
    .post(
      ajaxurl,
      {
        action: 'mp_validate_payment_tips',
        nonce: mercadopago_settings_admin_js_params.nonce,
      },
      function () {
      }
    )
    .done(function (response) {
      if (response.success) {
          iconPayment.classList.remove('mp-settings-icon-payment');
          iconPayment.classList.add('mp-settings-icon-success');
      } else {
          iconPayment.classList.remove('mp-settings-icon-success');
      }
    })
    .fail(function () {
      iconPayment.classList.remove('mp-settings-icon-success');
    });
}

function mpGoToNextStep(actualStep, nextStep, actualArrowId, nextArrowId) {
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

function mpContinueToNextStep() {
  document
    .getElementById('mp-payment-method-continue')
    .addEventListener('click', function () {
      mpGoToNextStep('mp-step-3', 'mp-step-4', 'mp-payments-arrow-up', 'mp-modes-arrow-up');
    });
}

function mpGetRequirements() {
  jQuery.post(
    ajaxurl,
    {
      action: 'mp_get_requirements',
      nonce: mercadopago_settings_admin_js_params.nonce,
    },
    function (response) {
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

function mpGetPaymentMethods() {
  jQuery.post(
    ajaxurl,
    {
      action: 'mp_get_payment_methods',
      nonce: mercadopago_settings_admin_js_params.nonce,
    },
    function (response) {
      const payment = document.getElementById('mp-payment');

      // removes current payment methods
      document.querySelectorAll('.mp-settings-payment-block').forEach(element => {element.remove()})

      response.data.reverse().forEach((gateway) => {
        payment.insertAdjacentElement('afterend', createMpPaymentMethodComponent(gateway));
      });

      // added melidata events on store configuration step three
      if (window.melidata && window.melidata.client && window.melidata.client.stepPaymentMethodsCallback) {
        window.melidata.client.stepPaymentMethodsCallback();
      }
    });
}

function createMpPaymentMethodComponent(gateway) {
  const payment_active = gateway.enabled === 'yes' ? 'mp-settings-badge-active' : 'mp-settings-badge-inactive';
  const text_payment_active = gateway.enabled === 'yes' ? gateway.badge_translator.yes : gateway.badge_translator.no;

  const container = document.createElement('div');
  container.appendChild(getPaymentMethodComponent(gateway, payment_active, text_payment_active));

  return container;
}

function getPaymentMethodComponent(gateway, payment_active, text_payment_active) {
  const component = `
    <a href="${gateway.link}" class="mp-settings-link mp-settings-font-color">
      <div class="mp-block mp-block-flex mp-settings-payment-block mp-settings-align-div">
        <div class="mp-settings-align-div">
          <div class="mp-settings-icon">
            <img src="${gateway.icon}" alt="mp gateway icon" />
          </div>

          <span class="mp-settings-subtitle-font-size mp-settings-margin-title-payment">
            <b>${gateway.title_gateway}</b> - ${gateway.description}
          </span>

          <span class="${payment_active}">${text_payment_active}</span>
        </div>

        <div class="mp-settings-title-align">
        <span class="mp-settings-text-payment">Configurar</span>
          <div class="mp-settings-icon-config"></div>
        </div>
      </div>
    </a>
  `;

  return new DOMParser().parseFromString(component, 'text/html').firstChild;
}

function mpSettingsAccordionStart() {
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

function mpSettingsAccordionOptions() {
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
      element.textContent = mercadopago_settings_admin_js_params.show_advanced_text;
    } else {
      element.textContent = mercadopago_settings_admin_js_params.hide_advanced_text;
      elementBlock.classList.remove('mp-settings-flex-start');
    }
  });
}

function mpValidateCredentials() {
  document
    .getElementById('mp-access-token-prod')
    .addEventListener('change', function () {
      const self = this;
      jQuery
        .post(
          ajaxurl,
          {
            is_test: false,
            access_token: this.value,
            action: 'mp_update_access_token',
            nonce: mercadopago_settings_admin_js_params.nonce,
          },
          function () {
          }
        )
        .done(function (response) {
          if (response.success) {
            self.classList.add('mp-credential-feedback-positive');
            self.classList.remove('mp-credential-feedback-negative');
          } else {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          }
        })
        .fail(function () {
          self.classList.remove('mp-credential-feedback-positive');
          self.classList.add('mp-credential-feedback-negative');
        });
    });

  document
    .getElementById('mp-access-token-test')
    .addEventListener('change', function () {
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
              action: 'mp_update_access_token',
              nonce: mercadopago_settings_admin_js_params.nonce,
            },
            function () {
            }
          )
          .done(function (response) {
            if (response.success) {
              self.classList.add('mp-credential-feedback-positive');
              self.classList.remove('mp-credential-feedback-negative');
            } else {
              self.classList.remove('mp-credential-feedback-positive');
              self.classList.add('mp-credential-feedback-negative');
            }
          })
          .fail(function () {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          });
      }
    });

  document
    .getElementById('mp-public-key-prod')
    .addEventListener('change', function () {
      const self = this;
      jQuery
        .post(
          ajaxurl,
          {
            is_test: false,
            public_key: this.value,
            action: 'mp_update_public_key',
            nonce: mercadopago_settings_admin_js_params.nonce,
          },
          function () {
          }
        )
        .done(function (response) {
          if (response.success) {
            self.classList.add('mp-credential-feedback-positive');
            self.classList.remove('mp-credential-feedback-negative');
          } else {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          }
        })
        .fail(function () {
          self.classList.remove('mp-credential-feedback-positive');
          self.classList.add('mp-credential-feedback-negative');
        });
    });

  document
    .getElementById('mp-public-key-test')
    .addEventListener('change', function () {
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
              action: 'mp_update_public_key',
              nonce: mercadopago_settings_admin_js_params.nonce,
            },
            function () {
            }
          )
          .done(function (response) {
            if (response.success) {
              self.classList.add('mp-credential-feedback-positive');
              self.classList.remove('mp-credential-feedback-negative');
            } else {
              self.classList.remove('mp-credential-feedback-positive');
              self.classList.add('mp-credential-feedback-negative');
            }
          })
          .fail(function () {
            self.classList.remove('mp-credential-feedback-positive');
            self.classList.add('mp-credential-feedback-negative');
          });
      }
    });
}

function mpUpdateOptionCredentials() {
  document
    .getElementById('mp-btn-credentials')
    .addEventListener('click', function () {
      const msgAlert = document.getElementById('msg-info-credentials');
      if (msgAlert.childNodes.length >= 1) {
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
            nonce: mercadopago_settings_admin_js_params.nonce,
          },
          function () {
          }
        )
        .done(function (response) {
          mpGetPaymentMethods();
          if (response.success) {
            mpVerifyAlertTestMode();
            mpShowMessage(response.data, 'success', 'credentials');
            mpValidateCredentialsTips();

            setTimeout(() => {
              mpGoToNextStep('mp-step-1', 'mp-step-2', 'mp-credentials-arrow-up', 'mp-store-info-arrow-up');
            }, 3000);
          } else {
            const rad = document.querySelectorAll('input[name="mp-test-prod"]');
            const { message, subtitle, link, linkMsg, type, test_mode } = response?.data;

            mpMsgElement('msg-info-credentials', message, subtitle, link, linkMsg, type);

            if (test_mode === 'no') {
              rad[1].checked = true;
              selectTestMode(false);
            } else {
              rad[0].checked = true;
              selectTestMode(true);
            }
          }
        })
        .fail(function (error) {
          mpShowMessage(error?.data, 'error', 'credentials');
        });
    });
}

function mpUpdateStoreInformation() {
  document
    .getElementById('mp-store-info-save')
    .addEventListener('click', function () {
      jQuery
        .post(
          ajaxurl,
          {
            store_url_ipn: document.querySelector('#mp-store-url-ipn').value,
            store_url_ipn_options: document.querySelector('#mp-store-url-ipn-options').checked ? 'yes' : 'no',
            store_categories: document.getElementById('mp-store-categories').value,
            store_category_id: document.getElementById('mp-store-category-id').value,
            store_integrator_id: document.getElementById('mp-store-integrator-id').value,
            store_identificator: document.getElementById('mp-store-identification').value,
            store_debug_mode: document.querySelector('#mp-store-debug-mode:checked')?.value,
            action: 'mp_update_store_information',
            nonce: mercadopago_settings_admin_js_params.nonce,
          },
          function () {
          }
        )
        .done(function (response) {
          if (response.success) {
            mpValidateStoreTips();
            mpShowMessage(response.data, 'success', 'store');
            setTimeout(() => {
              mpGoToNextStep('mp-step-2', 'mp-step-3', 'mp-store-info-arrow-up', 'mp-payments-arrow-up');
            }, 3000);
          } else {
            mpShowMessage(response.data, 'error', 'store');
          }
        })
        .fail(function (error) {
          mpShowMessage(error?.data, 'error', 'store');
        });
    });
}

function mpUpdateTestMode() {
  const rad = document.querySelectorAll('input[name="mp-test-prod"]');

  rad[0].addEventListener('change', function () {
    if (rad[0].checked) {
      selectTestMode(true);
    }
  });

  rad[1].addEventListener('change', function () {
    if (rad[1].checked) {
      selectTestMode(false);
    }
  });

  document
    .getElementById('mp-store-mode-save')
    .addEventListener('click', function () {
      jQuery
        .post(
          ajaxurl,
          {
            input_mode_value: document.querySelector('input[name="mp-test-prod"]:checked').value,
            input_verify_alert_test_mode: mpVerifyAlertTestMode() ? 'yes' : 'no',
            action: 'mp_update_test_mode',
            nonce: mercadopago_settings_admin_js_params.nonce,
          },
          function () {
          }
        )
        .done(function (response) {
          if (response.success) {
            mpShowMessage(response.data, 'success', 'test_mode');
          } else {
            if (rad[0].checked) {
              document.getElementById('mp-red-badge').parentElement.style.display = 'flex';
            }
            mpShowMessage(response.data, 'error', 'test_mode');
          }
        })
        .fail(function (error) {
          mpShowMessage(error.data, 'error', 'test_mode');
        });
    });
}

function mp_settings_screen_load() {
  mpGetRequirements();
  mpGetPaymentMethods();
  mpSettingsAccordionStart();
  mpSettingsAccordionOptions();
  mpValidateCredentials();
  mpValidateCredentialsTips();
  mpValidateStoreTips();
  mpValidatePaymentTips();
  mpVerifyAlertTestMode();
  mpUpdateOptionCredentials();
  mpUpdateStoreInformation();
  mpUpdateTestMode();
  mpContinueToNextStep();
}
