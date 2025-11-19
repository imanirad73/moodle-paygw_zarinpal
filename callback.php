<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package    paygw_zarinpal
 * @copyright  2025 Ali Imani rad <airid73@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;
use core_payment\helper as payment_helper;
use paygw_zarinpal\zarinpal_helper;

require("../../../config.php");

global $CFG, $USER, $DB;

require_once($CFG->libdir . '/filelib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
require_sesskey();

$authority   = required_param('Authority', PARAM_RAW);
$status = required_param('Status', PARAM_TEXT);

$error = false;
$message = '';

if($status === 'NOK') {
    $error = true;
    $message = get_string('payment_error_detail', 'paygw_zarinpal');
} elseif($status === 'OK') {
    // Find transaction
    if($trx = $DB->get_record('paygw_zarinpal', array('zp_authority' => $authority))) {
        // Get trx details
        $component   = $trx->component;
        $paymentarea = $trx->paymentarea;
        $itemid      = $trx->itemid;
        $amount      = $trx->amount;

        // Get config.
        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'zarinpal');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        //Verify the payment
        $zarinpalhelper = new zarinpal_helper($config->merchantid, $config->environment);
        $result = $zarinpalhelper->verify_trx($amount, $authority);

        if($result['error']) {
            $error = true;
            $message = $result['message'];
        } else {
            try {
                // Save payment
                $paymentid = payment_helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                    $itemid, (int) $USER->id, $amount, $currency, 'zarinpal');
                // Update database
                $trx->paymentid = $paymentid;
                $trx->ref_id = $result['ref_id'];
                $DB->update_record('paygw_zarinpal', $trx);
                // Deliver order
                payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $USER->id);
                // Get success url
                $successurl = payment_helper::get_success_url($component, $paymentarea, $itemid);
            } catch (\Exception $e) {
                debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $error = true;
                $message = get_string('internalerror', 'paygw_zarinpal');
            }

        }
    } else {
        $error = true;
        $message = get_string('payment_error_detail', 'paygw_zarinpal');
    }
}

// Set the context of the page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/payment/gateway/zarinpal/callback.php');

// Set the appropriate headers for the page.
$PAGE->set_cacheable(false);
$PAGE->set_pagelayout('standard');

if($error) {
    $string = get_string('payment_error', 'paygw_zarinpal');
    $PAGE->set_title(format_string($string));
    $PAGE->set_heading(format_string($string));

    echo $OUTPUT->header();

    global $SESSION;
    $returnurl = $SESSION->wantsurl ?? (new \moodle_url('/'))->out(false);
    $templatedata = new stdClass();
    $templatedata->message = $message;
    $templatedata->backurl = $returnurl;

    echo $OUTPUT->render_from_template('paygw_zarinpal/error', $templatedata);

    echo $OUTPUT->footer();
} else {
    $string = get_string('payment_success', 'paygw_zarinpal');
    $PAGE->set_title(format_string($string));
    $PAGE->set_heading(format_string($string));

    echo $OUTPUT->header();

    $templatedata = new stdClass();
    $templatedata->ref_id = $result['ref_id'];
    $templatedata->successurl = $successurl;

    echo $OUTPUT->render_from_template('paygw_zarinpal/success', $templatedata);

    echo $OUTPUT->footer();
}