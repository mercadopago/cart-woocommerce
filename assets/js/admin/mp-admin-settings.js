/* globals jQuery, ajaxurl, mercadopago_settings_admin_js_params */

function clearMessage() {
  document.querySelector('.mp-alert').remove();
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

function mpShowSnackbar(message, type) {
  const $snack = jQuery(`
    <div class="mp-snackbar">
      <div class="mp-snackbar-${type}">
        ${message}
      </div>
    </div>
  `);
  jQuery('.mp-settings').append($snack);
  setTimeout(() => $snack.remove(), 5000);
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
      document.querySelectorAll('.mp-settings-payment-block').forEach(element => { element.remove() })

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

      let accordionArrow = null;

      for (let i = 0; i < this.childNodes.length; i++) {
        if (this.childNodes[i]?.classList?.contains('mp-settings-margin-left')) {
          accordionArrow = this.childNodes[i];
          break;
        }
      }

      accordionArrow?.childNodes[1]?.classList?.toggle('mp-arrow-up');

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

function mpUpdateOptionCredentials() {
  document
    .getElementById('mp-btn-credentials')?.addEventListener('click', function () {
      setTimeout(() => {
        mpGoToNextStep('mp-step-1', 'mp-step-2', 'mp-credentials-arrow-up', 'mp-store-info-arrow-up');
      }, 2000);
    })
}

function mpUpdateTestCredentials() {
  document
    .getElementById('mp-btn-update-test-credentials')?.addEventListener('click', function () {
      const msgAlert = document.getElementById('msg-info-credentials');
      if (msgAlert.childNodes.length >= 1) {
        document.querySelector('.mp-card-info').remove();
      }

      hideCredentialsErrors(document.getElementById('mp-test-access-token'), document.getElementById("mp-modal-alert-access-token"));
      hideCredentialsErrors(document.getElementById('mp-test-public-key'), document.getElementById("mp-modal-alert-public-key"));

      jQuery
        .post(
          ajaxurl,
          {
            public_key_test: document.getElementById('mp-public-key-test').value,
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
            document.getElementById('mp-credentials-modal').style.display = 'none';
            mpVerifyAlertTestMode();
            mpShowSnackbar(response.data, 'success');

          } else {
            showCredentialsError('public-key', response.data['message']);
            showCredentialsError('access-token', response.data['message']);
          }
        })
        .fail(function (error) {
          mpShowSnackbar(error?.data, 'error');
        });
    });
}

function hideCredentialsErrors(element, alert) {
  if (alert) {
    alert.remove();
  }
  if (element) {
    const label = element.getElementsByTagName('label')[0];
    const input = element.getElementsByTagName('input')[0];

    if (label.classList.contains('mp-settings-modal-text-alert')) {
      label.classList.remove('mp-settings-modal-text-alert');
    }
    if (input.classList.contains('mp-settings-modal-input-alert')) {
      input.classList.remove('mp-settings-modal-input-alert');
    }
  }
}

function showCredentialsError(field, message) {
  document.getElementById('mp-test-' + field).getElementsByTagName('label')[0].classList.add('mp-settings-modal-text-alert');
  document.getElementById('mp-test-' + field).getElementsByTagName('input')[0].classList.add('mp-settings-modal-input-alert');

  const alertDivText = document.createElement('p');
  alertDivText.innerText = message;

  const alertDiv = document.createElement('div');
  alertDiv.className = 'mp-modal-alert';
  alertDiv.id = 'mp-modal-alert-' + field;
  alertDiv.appendChild(alertDivText);
  document.getElementById('mp-test-' + field).appendChild(alertDiv);
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
            store_cron_config: document.getElementById('mp-store-cron-config')?.value,
            action: 'mp_update_store_information',
            nonce: mercadopago_settings_admin_js_params.nonce,
          },
          function () {
          }
        )
        .done(function (response) {
          if (response.success) {
            mpValidateStoreTips();
            mpShowSnackbar(response.data, 'success');
            setTimeout(() => {
              mpGoToNextStep('mp-step-2', 'mp-step-3', 'mp-store-info-arrow-up', 'mp-payments-arrow-up');
            }, 3000);
          } else {
            mpShowSnackbar(response.data, 'error');
          }
        })
        .fail(function (error) {
          mpShowSnackbar(error?.data, 'error');
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
            mpShowSnackbar(response.data, 'success');
          } else {
            if (rad[0].checked) {
              document.getElementById('mp-red-badge').parentElement.style.display = 'flex';
            }
            mpShowSnackbar(response.data, 'error');
          }
        })
        .fail(function (error) {
          mpShowSnackbar(error.data, 'error');
        });
    });
}

function mpSwitchAccount() {
  jQuery('#mp-switch-account-btn').click(() => {
    jQuery('#mp-settings-credentials-linked').addClass('mp-hidden');
    jQuery('#mp-settings-credentials-country').removeClass('mp-hidden');
  })
}

async function waitForMPDeviceSessionID(timeout) {
  const startTime = Date.now();

  return new Promise((resolve, reject) => {
    const interval = setInterval(() => {
      if (typeof MP_DEVICE_SESSION_ID !== 'undefined' && MP_DEVICE_SESSION_ID) {
        clearInterval(interval);
        resolve(MP_DEVICE_SESSION_ID);
      } else if (Date.now() - startTime > timeout) {
        clearInterval(interval);
        reject(new Error('Timeout waiting for MP_DEVICE_SESSION_ID'));
      }
    }, 100); 
  });
}

async function mpRedirectSellerToIntegrationAuthLogin() {
    try {
      const deviceFingerprint = await waitForMPDeviceSessionID(5000);
      jQuery
        .post(
          ajaxurl,
          {
            action: 'mp_integration_login',
            site_id: jQuery("#mp-credentials-country").val(),
            device_fingerprint: deviceFingerprint,
            nonce: mercadopago_settings_admin_js_params.nonce,
          }
        )
        .done(function (response) {
          if (response.success) {
            window.location.href = response.data.onboarding_url;
          } else {
            redirectToOnBoardingError();
          }
          return;
        })
        .fail(function () {
          redirectToOnBoardingError();
          return;
        });
    } catch (error) {
      redirectToOnBoardingError();
      console.error('Failed to get MP_DEVICE_SESSION_ID:', error.message);
    }
}

function mpIntegrationAuthLogin() {
  jQuery('#mp-integration-auth-login, #mp-integration-auth-login-update')?.click(async function (event) {
    const button = event.target;
    button.disabled = true;
    button.classList.add('mp-disabled-button');

    try {
      await mpRedirectSellerToIntegrationAuthLogin();
    } catch (error) {
      redirectToOnBoardingError();
      console.error('Error when executing mpRedirectSellerToIntegrationAuthLogin:', error.message);
    }
  });
}

function redirectTo(url) {
  window.location.href = url;
}

function redirectToOnBoardingError() {
  redirectTo(window.location.href + "&onboarding_error=true");
}

function mpIntegrationAuthFailed() {
  jQuery('#mp-integration-auth-failed')?.click(function () {
    const url = new URL(window.location.href);
    url.searchParams.delete("onboarding_error");
    window.history.replaceState({}, '', url.toString());
    window.location.reload();
  });
}

function mpCredentialsInputListeners() {
  if (document.getElementById('mp-test-access-token')) {
    document
      .getElementById('mp-test-access-token')
      .addEventListener('change', function () {
        const alert = document.getElementById("mp-modal-alert-access-token");
        hideCredentialsErrors(this, alert);
      });
  }

  if (document.getElementById('mp-test-public-key')) {
    document
      .getElementById('mp-test-public-key')
      .addEventListener('change', function () {
        const alert = document.getElementById("mp-modal-alert-public-key");
        hideCredentialsErrors(this, alert);
      });
  }
}

function mpCredentialsModalButtonsListeners() {
  if (document.getElementById('mp-settings-auto-data-consult'))
    document
      .getElementById('mp-settings-auto-data-consult')
      .addEventListener('click', function () {
        document.getElementById('mp-credentials-modal').style.display = 'flex';
      });

  if (document.getElementById('mp-credentials-close-modal'))
    document
      .getElementById('mp-credentials-close-modal')
      .addEventListener('click', function () {
        document.getElementById('mp-credentials-modal').style.display = 'none';
      });

  if (document.getElementById('mp-production-token-button'))
    document
      .getElementById('mp-production-token-button')
      .addEventListener('click', function () {
        switchVisibility('production');
      });

  if (document.getElementById('mp-test-token-button'))
    document
      .getElementById('mp-test-token-button')
      .addEventListener('click', function () {
        switchVisibility('test');
      });
}

function switchVisibility(scope) {
  const field = document.getElementById('mp-access-token-' + scope);
  const visibilityImgElement = document.getElementById('mp-' + scope + '-token-img');
  const visibilityButtom = document.getElementById('mp-' + scope + '-token-button');
  const visibilityImgClass = visibilityImgElement.classList;
  if (field.type === 'password') {
    field.type = 'text';
    visibilityButtom.ariaLabel = mercadopago_settings_admin_js_params.hide_access_token;
    visibilityImgElement.title = mercadopago_settings_admin_js_params.hide_access_token;
    visibilityImgClass.remove('mp-access-token-button-closed');
    visibilityImgClass.add('mp-access-token-button-open');
  } else {
    field.type = 'password';
    visibilityButtom.ariaLabel = mercadopago_settings_admin_js_params.show_access_token;
    visibilityImgElement.title = mercadopago_settings_admin_js_params.show_access_token;
    visibilityImgClass.remove('mp-access-token-button-open');
    visibilityImgClass.add('mp-access-token-button-closed');
  }
}

function mpSelectCredentialsCountry() {
  const $panel       = jQuery("#mp-settings-credentials-country");
  const $btn         = jQuery('#mp-button-country');
  const $input       = $panel.find('#mp-credentials-country');
  const $select      = $input.parents('#mp-settings-credentials-country .mp-select');
  const $selectGroup = $input.parents('.mp-input-group');
  const $btnCountry  = $panel.find('#mp-button-country');

  const switchPanels = () => {
    $panel.toggleClass('mp-hidden');
    jQuery($btn.data('next')).toggleClass('mp-hidden');
  };

  $input.change(() => {
    $selectGroup.removeClass('mp-error')
    $btnCountry.removeClass('mp-button-error');
    $btnCountry.focus();
  });

  $btn.click(() => {
    if (!$input.val()) {
      $selectGroup.addClass('mp-error');
      $btnCountry.addClass('mp-button-error');
      const errorTextElement = jQuery(".mp-input-group.mp-error .mp-error-msg");
      errorTextElement.focus();
      return;
    }

    if ($btn.data('switch-account')) {
      $btn.prop('disabled', true);
      $btn.addClass('mp-disabled-button');

      mpRedirectSellerToIntegrationAuthLogin();
      return;
    }
    switchPanels();
    mpShowSnackbar(
      $btn.data('success').replace('{{country}}', $select.find('[aria-selected=true]').text()),
      'success'
    );
  });

  jQuery('#mp-credentials-change-country').click(switchPanels);
}

function mpSelect() {
  jQuery('.mp-select ul').on('open', (event) => {
    const $listbox = jQuery(event.target);
    const $select = $listbox.parents('.mp-select');
    jQuery('#mp-credentials-country-select button').addClass('mp-select-button-focus');

    $listbox.removeClass('mp-hidden');
    $select.attr('aria-expanded', 'true');
  });

  jQuery('.mp-select ul').on('close', (event) => {
    const $listbox = jQuery(event.target);
    const $select = $listbox.parents('.mp-select');

    $listbox.addClass('mp-hidden');
    $select.attr('aria-expanded', 'false');
    jQuery('#mp-credentials-country-select button').removeClass('mp-select-button-focus');
  });

  jQuery('.mp-select button').click((event) => {
    event.stopPropagation();

    const $btn = jQuery(event.target);
    const $select = $btn.parent('.mp-select');
    const $listbox = $select.find('ul');

    $listbox.trigger('open');
    jQuery('#mp-credentials-country-select ul').focus();
  })

  jQuery('body').click(() => jQuery('.mp-select ul').trigger('close'));

  jQuery('.mp-select li').click((event) => {
    const $option = jQuery(event.target);
    const $select = $option.parents('.mp-select');
    const $listbox = $select.find('ul');
    const $options = $listbox.find('li');
    const $input = $select.find('input');
    const $btn = $select.find('button');

    $listbox.trigger('close');
    $input.val($option.data('value')).trigger('change');
    $btn.text($option.text());

    $options.attr('aria-selected', false);
    $option.attr('aria-selected', true);
  });
}

function mp_settings_screen_load() {
  mpGetRequirements();
  mpGetPaymentMethods();
  mpSettingsAccordionStart();
  mpSettingsAccordionOptions();
  mpCredentialsInputListeners();
  mpValidateStoreTips();
  mpValidatePaymentTips();
  mpVerifyAlertTestMode();
  mpUpdateOptionCredentials();
  mpUpdateTestCredentials();
  mpUpdateStoreInformation();
  mpUpdateTestMode();
  mpContinueToNextStep();
  mpIntegrationAuthLogin();
  mpIntegrationAuthFailed();
  mpCredentialsModalButtonsListeners();
  mpSelectCredentialsCountry();
  mpSelect();
  mpSwitchAccount();
  mpKeepStepOneOpenOnLoad();
  mpDeactivateProdModeRadio()
}

function openSupportModal() {
  var modal = document.getElementById('supportModal');
  modal.style.display = 'block';
}

function closeSupportModal() {
  var modal = document.getElementById('supportModal');
  modal.style.display = 'none';
}

// handle with pagination in support modal
document.addEventListener('DOMContentLoaded', function () {
  const checkboxes = document.querySelectorAll('input[name="selected_files[]"]');
  const downloadButton = document.getElementById('downloadSelected');
  const itemsPerPage = 8;
  let currentPage = 1;

  function updateTableDisplay() {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;

    checkboxes.forEach((checkbox, index) => {
      checkbox.closest('tr').style.display = index >= startIndex && index < endIndex ? 'table-row' : 'none';
    });
  }

  function updatePaginationButtons() {
    const totalPages = Math.ceil(checkboxes.length / itemsPerPage);
    const paginationElement = document.getElementById('mp-pagination');
    paginationElement.innerHTML = '';

    for (let i = 1; i <= totalPages; i++) {
      const pageSpan = document.createElement('span');
      pageSpan.textContent = i;
      pageSpan.classList.add('mp-page-span');
      if (i === currentPage) {
        pageSpan.classList.add('active');
      }
      pageSpan.addEventListener('click', function () {
        currentPage = i;
        updateTableDisplay();
        updatePaginationButtons();
      });
      paginationElement.appendChild(pageSpan);
    }
  }

  updateTableDisplay();
  updatePaginationButtons();

  checkboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      let atLeastOneChecked = false;
      checkboxes.forEach((cb) => {
        if (cb.checked) {
          atLeastOneChecked = true;
        }
      });
      downloadButton.disabled = !atLeastOneChecked;
    });
  });

  const selectAllCheckbox = document.getElementById('selectAllCheckbox');
  selectAllCheckbox.addEventListener('change', function () {
    checkboxes.forEach((checkbox) => {
      checkbox.checked = selectAllCheckbox.checked;
    });
    downloadButton.disabled = !selectAllCheckbox.checked;
  });

});

function mpKeepStepOneOpenOnLoad(){
  document.getElementById('mp-step-1').style.display = 'block';
  document.querySelector('#mp-settings-step-one img#mp-credentials-arrow-up').classList.add('mp-arrow-up');
}

function mpDeactivateProdModeRadio() {
  const radioProdMode = document.getElementById('mp-settings-testmode-prod');
  const radioTestMode = document.getElementById('mp-settings-testmode-test');
  const pluginHasNotProdKeys = mpHasNotProdKeys();

  if (pluginHasNotProdKeys) {
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
        ).done(function (response) {
          if (response.success) {
            radioTestMode.checked = true;
            radioProdMode.checked = false;
            radioProdMode.disabled = true;

            selectTestMode(true);
          } else if(!response.success && pluginHasNotProdKeys) {
            radioProdMode.disabled = true;
          }
        });
  } else {
    radioProdMode.disabled = false;
  }
}

function mpHasNotProdKeys() {
  const isNotProdKeys = document.getElementById('mp-public-key-prod')?.value === '' || document.getElementById('mp-access-token-production')?.value === '';
  const isProdKeyElementsNotRendered = !document.getElementById('mp-public-key-prod') || !document.getElementById('mp-access-token-production');

  return isNotProdKeys || isProdKeyElementsNotRendered;
}
