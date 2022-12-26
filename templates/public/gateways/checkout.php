<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
    <input id="misha_ccNo" type="text" autocomplete="off">
</div>

<div class="form-row form-row-first">
    <label>Expiry Date <span class="required">*</span></label>
    <input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
</div>

<div class="form-row form-row-last">
    <label>Card Code (CVC) <span class="required">*</span></label>
    <input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
</div>

<div class="clear"></div>
