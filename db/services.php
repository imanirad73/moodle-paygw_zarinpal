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
 * External functions and service definitions for the Zarinpal payment gateway plugin.
 *
 * @package    paygw_zarinpal
 * @copyright  2025 Ali Imani rad <airid73@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_zarinpal_payment_returnurl' => [
        'classname'   => 'paygw_zarinpal\external\payment_returnurl',
        'classpath'   => '',
        'description' => 'Sets return URL if payment is faced with issue',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
