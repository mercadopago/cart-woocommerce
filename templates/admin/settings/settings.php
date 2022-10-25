<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<script>
    window.addEventListener("load", function () {
        mp_settings_screen_load();
    });
</script>

<div class="mp-settings">
    <div class="mp-settings-header">
        <div class="mp-settings-header-img"></div>
        <div class="mp-settings-header-logo"></div>
        <hr class="mp-settings-header-hr"/>
        <p class="mp-settings-header-title">Accept <b>payments on the spot</b> with <br/> the security from Mercado Pago</p>
    </div>

    <div class="mp-settings-requirements">
        <div class="mp-container">
            <div class="mp-block mp-block-requirements mp-settings-margin-right">
                <p class="mp-settings-font-color mp-settings-title-font-size">Technical requirements</p>
                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">SSL</p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size"><b>SSL</b></p>
                                Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-ssl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
                <hr>

                <div class="mp-inner-container">
                    <div>GD Extensions</p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size"><b>GD Extensions</b></p>
                                These extensions are responsible for the implementation and operation of Pix in your store.
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-gd" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
                <hr>

                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">Curl</p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size"><b>Curl</b></p>
                                It is an extension responsible for making payments via requests from the plugin to Mercado Pago.
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-curl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
