<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains helper class to work with zarinpal REST API.
 *
 * @package    paygw_zarinpal
 * @copyright  2025 Ali Imani rad <airid73@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_zarinpal;

use curl;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class zarinpal_helper {

    /**
     * @var string The payment was authorized or the authorized payment was captured for the order.
     */
    public const CAPTURE_STATUS_COMPLETED = 'COMPLETED';

    /**
     * @var string The merchant intends to capture payment immediately after the customer makes a payment.
     */
    public const ORDER_INTENT_CAPTURE = 'CAPTURE';

    /**
     * @var string The customer approved the payment.
     */
    public const ORDER_STATUS_APPROVED = 'APPROVED';

    /**
     * @var string Merchant ID
     */
    private $merchantid;

    /**
     * @var string Environment mode
     */
    private $environment;

    /**
     * helper constructor.
     *
     * @param string $merchantid The client id.
     * @param string $environment Sandbox or Live.
     */
    public function __construct(string $merchantid, string $environment = 'sandbox') {
        $this->merchantid = $merchantid;
        $this->environment = $environment == 'sandbox' ? 'sandbox' : 'payment';
    }

    /**
     * Request for zarinpal REST payment request API.
     *
     * @param float $cost The amount should be paid
     * @param string $description Payment description
     * @return array
     */
    public function get_authority(float $cost, string $description): array
    {
        $location = 'https://' . $this->environment . '.zarinpal.com/pg/v4/payment/request.json';

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_ENCODING' => '',
            'CURLOPT_MAXREDIRS' => 10,
            'CURLOPT_TIMEOUT' => 0,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        $params = [
            'merchant_id' => $this->merchantid,
            'amount' => $cost,
            'description' => $description,
            'callback_url' => (new \moodle_url(
                '/payment/gateway/zarinpal/callback.php',
                ['sesskey' => sesskey()]
            ))->out(false),
        ];

        $curl = new curl();
        $result = $curl->post($location, json_encode($params), $options);

        $result = json_decode($result, true);

        if(isset($result['data']['code'])) {
            if($result['data']['code'] != 100) {
                return [
                    'error' => true,
                    'message' => get_string("error_x", 'paygw_zarinpal', format_string($result['errors']['code']))
                ];
            } else {
                if(isset($result['data']['authority'])) {
                    return [
                        'error' => false,
                        'authority' => $result['data']['authority']
                    ];
                } else {
                    return [
                        'error' => true,
                        'message' => get_string("error_unknown", 'paygw_zarinpal')
                    ];
                }
            }
        } else {
            if(isset($result['errors']['code'])) {
                return [
                    'error' => true,
                    'message' => get_string("error_x", 'paygw_zarinpal', format_string($result['errors']['code']))
                ];
            } else {
                return [
                    'error' => true,
                    'message' => get_string("error_connection", 'paygw_zarinpal')
                ];
            }
        }
    }

    /**
     * Creates the payment URL
     * @param string $authority The authority code returned from Zarinpal
     * @return string Formatted payment URL
     */
    public function get_payment_url(string $authority): string
    {
        return 'https://' . $this->environment . '.zarinpal.com/pg/StartPay/' .  $authority;
    }

    /**
     * Verifies Zarinpal transaction.
     *
     * @param float $amount The amount should be paid
     * @param string $authority The authority code returned from Zarinpal
     * @return array
     */
    public function verify_trx(float $amount, string $authority): array
    {
        $location = 'https://' . $this->environment . '.zarinpal.com/pg/v4/payment/verify.json';

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_ENCODING' => '',
            'CURLOPT_MAXREDIRS' => 10,
            'CURLOPT_TIMEOUT' => 0,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        $params = [
            'merchant_id' => $this->merchantid,
            'amount' => $amount,
            'authority' => $authority
        ];

        $curl = new curl();
        $result = $curl->post($location, json_encode($params), $options);

        $result = json_decode($result, true);

        if(isset($result['data']['code'])) {
            if($result['data']['code'] != 100) {
                return [
                    'error' => true,
                    'message' => get_string("error_x", 'paygw_zarinpal', format_string($result['errors']['code']))
                ];
            } else {
                if(isset($result['data']['ref_id'])) {
                    return [
                        'error' => false,
                        'ref_id' => $result['data']['ref_id']
                    ];
                } else {
                    return [
                        'error' => true,
                        'message' => get_string("error_unknown", 'paygw_zarinpal')
                    ];
                }
            }
        } else {
            if(isset($result['errors']['code'])) {
                return [
                    'error' => true,
                    'message' => get_string("error_x", 'paygw_zarinpal', format_string($result['errors']['code']))
                ];
            } else {
                return [
                    'error' => true,
                    'message' => get_string("error_connection", 'paygw_zarinpal')
                ];
            }
        }
    }

}