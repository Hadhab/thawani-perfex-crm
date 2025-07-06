<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Thawanipay Gateway
Description: The easiest way to accept payments for businesses in Oman
Version: 2.3.0
Requires at least: 2.3.4
*/

require_once(__DIR__ . '/vendor/autoload.php');

register_payment_gateway('thawani_pay_gateway', 'thawanipay');
