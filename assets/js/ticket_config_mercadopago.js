window.onload = function () {
  //remove link breadcrumb, header and save button
  document.querySelector(".wc-admin-breadcrumb").style.display = 'none';
  document.querySelector(".mp-header-logo").style.display = 'none';
  document.querySelector("p.submit").style.display = 'none';
  document.querySelectorAll("h2")[4].style.display = 'none';

  //update form_fields label
  var label = document.querySelectorAll("th.titledesc");
  for (var i = 0; i < label.length; i++) {
    label[i].id = "mp_field_text";
    if (label[i].children[0].children[0] != null) {
      label[i].children[0].children[0].style.position = 'relative';
      label[i].children[0].children[0].style.fontSize = '22px';
    }
  }

  //collpase ajustes avanzados
  var table = document.querySelectorAll(".form-table");
  for (i = 0; i < table.length; i++) {
    table[i].id = "mp_table_" + i;
  }

  // Remove title and description label necessary for custom
  document.querySelector(".hidden-field-mp-title").setAttribute("type", "hidden");
  document.querySelector(".hidden-field-mp-desc").setAttribute("type", "hidden");
  var removeLabel = document.querySelectorAll("#mp_table_0");
  removeLabel[0].children[0].children[0].style.display = 'none';
  removeLabel[0].children[0].children[1].style.display = 'none';

  //clone save button
  var cloneSaveButton = document.getElementById('woocommerce_woo-mercado-pago-ticket_checkout_btn_save');
  if (document.getElementById("'woocommerce_woo-mercado-pago-ticket_checkout_homolog_title") != undefined || document.getElementById("'woocommerce_woo-mercado-pago-ticket_checkout_credential_description_prod") != undefined) {
    document.getElementById("mp_table_15").append(cloneSaveButton.cloneNode(true));
  }

  if (document.getElementById("woocommerce_woo-mercado-pago-ticket_checkout_homolog_title") != undefined || document.getElementById("woocommerce_woo-mercado-pago-ticket_checkout_ticket_options_title") != undefined ) {
   document.getElementById("woocommerce_woo-mercado-pago-ticket_checkout_ticket_options_subtitle").nextElementSibling.append(cloneSaveButton.cloneNode(true));
    document.getElementById("woocommerce_woo-mercado-pago-ticket_checkout_advanced_settings").nextElementSibling.append(cloneSaveButton.cloneNode(true));
    document.getElementById("woocommerce_woo-mercado-pago-ticket_checkout_payments_description").nextElementSibling.append(cloneSaveButton.cloneNode(true));
document.getElementById("woocommerce_woo-mercado-pago-ticket_checkout_payments_advanced_description").nextElementSibling.append(cloneSaveButton.cloneNode(true));

    var collapse_title = document.querySelector("#woocommerce_woo-mercado-pago-ticket_checkout_advanced_settings");
    var collapse_table = collapse_title.nextElementSibling;
    collapse_table.style.display = "none";
    collapse_title.style.cursor = "pointer";

    collapse_title.innerHTML += "<span class='btn-collapsible' id='header_plus' style='display:block'>+</span>\
            <span class='btn-collapsible' id='header_less' style='display:none'>-</span>";

    var header_plus = document.querySelector("#header_plus");
    var header_less = document.querySelector("#header_less");

    collapse_title.onclick = function () {
      if (collapse_table.style.display == "none") {
        collapse_table.style.display = "block";
        header_less.style.display = "block";
        header_plus.style.display = "none";
      }
      else {
        collapse_table.style.display = "none";
        header_less.style.display = "none";
        header_plus.style.display = "block";
      }
    }

    //collpase Configuración Avanzada
    var collapse_title_2 = document.querySelector("#woocommerce_woo-mercado-pago-ticket_checkout_ticket_payments_advanced_title");
    var collapse_table_2 =  document.querySelector("#woocommerce_woo-mercado-pago-ticket_checkout_payments_advanced_description").nextElementSibling;
    var collapse_description_2 = document.querySelector("#woocommerce_woo-mercado-pago-ticket_checkout_payments_advanced_description");
    collapse_table_2.style.display = "none";
    collapse_description_2.style.display = "none";
    collapse_title_2.style.cursor = "pointer";

    collapse_title_2.innerHTML += "<span class='btn-collapsible' id='header_plus_2' style='display:block'>+</span>\
            <span class='btn-collapsible' id='header_less_2' style='display:none'>-</span>";

    var header_plus_2 = document.querySelector("#header_plus_2");
    var header_less_2 = document.querySelector("#header_less_2");

    collapse_title_2.onclick = function () {
      if (collapse_table_2.style.display == "none") {
        collapse_table_2.style.display = "block";
        collapse_description_2.style.display = "block";
        header_less_2.style.display = "block";
        header_plus_2.style.display = "none";
      }
      else {
        collapse_table_2.style.display = "none";
        collapse_description_2.style.display = "none";
        header_less_2.style.display = "none";
        header_plus_2.style.display = "block";
      }
    }

    //payment methods
    var tablePayments = document.querySelector("#woocommerce_woo-mercado-pago-ticket_checkout_payments_description").nextElementSibling.getAttribute('class');
    var mp_input_payments = document.querySelectorAll( '.'+ tablePayments + ' td.forminp label');
    for (i = 0; i < mp_input_payments.length; i++) {
      mp_input_payments[i].id = "mp_input_payments_mt";
    }

    //offline payments configuration form
    var offlineChecked = "";
    var countOfflineChecked = 0;
    var offlineInputs = document.querySelectorAll(".ticket_payment_method_select");
    for (var ioff = 0; ioff < offlineInputs.length; ioff++) {
      var offline_payment_translate = offlineInputs[ioff].getAttribute("data-translate");
      if (offlineInputs[ioff].checked == true) {
        countOfflineChecked += 1;
      }
    }

    if (countOfflineChecked == offlineInputs.length) {
      offlineChecked = "checked";
    }

    for (ioff = 0; ioff < offlineInputs.length; ioff++) {
      if (ioff == 0) {
        var checkbox_offline_prepend = "<div class='all_checkbox'>\
        <label for='checkmeoff' id='mp_input_payments' style='margin-bottom: 37px !important;'>\
          <input type='checkbox' name='checkmeoff' id='checkmeoff' "+ offlineChecked + " onclick='completeOfflineCheckbox()'>\
          "+ offline_payment_translate + "\
        </label>\
      </div>";
        offlineInputs[ioff].parentElement.insertAdjacentHTML('beforebegin', checkbox_offline_prepend);
        break;
      }
    }

  }

  if(document.querySelector('.homologScroll') != null) {
  document.querySelector('.homologScroll').addEventListener('click', function () {
    document.querySelector('#woocommerce_woo-mercado-pago-ticket__mp_access_token_prod').scrollIntoView({
          block: "start",
          behavior: "smooth"
        });
    });
  }
}

//Offline payments
function completeOfflineCheckbox() {
  var offlineCheck = document.getElementById("checkmeoff").checked;
  var offlineInputs = document.querySelectorAll(".ticket_payment_method_select");
  for (var i = 0; i < offlineInputs.length; i++) {
    if (offlineCheck == true) {
      offlineInputs[i].checked = true;
    }
    else {
      offlineInputs[i].checked = false;
    }
  }
}