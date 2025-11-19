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
use paygw_zarinpal\zarinpal_helper;

require_once(__DIR__ . '/../../../config.php');

global $CFG, $USER, $DB;

defined('MOODLE_INTERNAL') || die();

require_login();
require_sesskey();

$component   = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid      = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);

$description = json_decode('"' . $description . '"');

$params = [
    'component'   => $component,
    'paymentarea' => $paymentarea,
    'itemid'      => $itemid,
    'description' => $description,
];

$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'zarinpal');
$payable = helper::get_payable($component, $paymentarea, $itemid);// Get currency and payment amount.
$currency = $payable->get_currency();
$surcharge = helper::get_gateway_surcharge('zarinpal');// In case user uses surcharge.
$cost = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

// Set the context of the page.
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/payment/gateway/zarinpal/method.php', $params);
$string = get_string('payment', 'paygw_zarinpal');
$PAGE->set_title(format_string($string));
$PAGE->set_heading(format_string($string));

// Set the appropriate headers for the page.
$PAGE->set_cacheable(false);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

$zarinpalhelper = new zarinpal_helper($config->merchantid, $config->environment);
$result = $zarinpalhelper->get_authority($cost, $description);

if($result['error']) {
    global $SESSION;
    $returnurl = $SESSION->wantsurl ?? (new \moodle_url('/'))->out(false);
    $templatedata = new stdClass();
    $templatedata->message = $result['message'];
    $templatedata->backurl = $returnurl;

    echo $OUTPUT->render_from_template('paygw_zarinpal/error', $templatedata);
} else {

    $record = new \stdClass();
    $record->amount = $cost;
    $record->zp_authority = $result['authority'];
    $record->component = $component;
    $record->paymentarea = $paymentarea;
    $record->itemid = $itemid;
    $DB->insert_record('paygw_zarinpal', $record);

    $templatedata = new stdClass();
    $templatedata->payable = get_string('cost_desc', 'paygw_zarinpal', number_format($cost, 0));
    $templatedata->description = $description;
    $templatedata->paymenturl = $zarinpalhelper->get_payment_url($result['authority']);
    $templatedata->image = $OUTPUT->image_url('img', 'paygw_zarinpal');

    echo $OUTPUT->render_from_template('paygw_zarinpal/method', $templatedata);
}

echo $OUTPUT->footer();
