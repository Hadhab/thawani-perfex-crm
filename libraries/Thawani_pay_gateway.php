<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Thawani_pay_gateway extends App_gateway
{
    protected $thawani;

    public function __construct()
    {
        parent::__construct();

        $this->setId('thawanipay');
        $this->setName('Thawani Pay');

        $this->setSettings([
            [
                'name'  => 'public_key',
                'label' => 'Public Key',
                'type'  => 'input',
            ],
            [
                'name'      => 'secret_key',
                'label'     => 'Secret Key',
                'type'      => 'input',
                'encrypted' => true,
            ],
            [
                'name'          => 'test_mode_enabled',
                'type'          => 'yes_no',
                'default_value' => 0,
                'label'         => 'Enable Test Mode',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'Payment Description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'Supported Currencies',
                'default_value' => 'OMR',
            ],
        ]);
    }

    public function process_payment($data)
    {
        $invoice = $data['invoice'];
        $amount = number_format($data['amount'], 3, '.', '');

        $public_key  = $this->getSetting('public_key');
        $private_key = $this->decryptSetting('secret_key');
        $test_mode   = (bool)$this->getSetting('test_mode_enabled');

        $url = $this->getUrl('checkout/session');

        $payload = [
            'client_reference_id' => 'invoice_' . $invoice->id,
            'mode' => 'payment',
            'products' => [
                [
                    'name' => str_replace('{invoice_number}', format_invoice_number($invoice->id), $this->getSetting('description_dashboard')),
                    'quantity' => 1,
                    'unit_amount' => intval($amount * 1000),
                ]
            ],
            'success_url' => site_url('thawanipay/success/' . $invoice->id . '/' . $invoice->hash),
            'cancel_url'  => site_url('thawanipay/cancel/' . $invoice->id . '/' . $invoice->hash),
            'metadata' => [
                'invoice_id'   => $invoice->id,
                'invoice_hash' => $invoice->hash,
                'amount'       => $amount,
            ],
        ];

        $headers = [
            'Content-Type: application/json',
            'Thawani-Api-Key: ' . $private_key,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $httpcode !== 200) {
            log_message('error', '[ThawaniPay] Error response: ' . $err . ' | HTTP Code: ' . $httpcode . ' | Response: ' . $response);
            set_alert('danger', 'ThawaniPay: Payment initiation failed.');
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        $data_response = json_decode($response, true);
        $session_id = $data_response['data']['session_id'] ?? null;

        if (!$session_id) {
            log_message('error', '[ThawaniPay] Session ID missing in response. Full response: ' . $response);
            set_alert('danger', 'ThawaniPay: Session ID missing.');
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        $ci = &get_instance();
        $checkout_url = ($test_mode ? 'https://uatcheckout.thawani.om/pay/' : 'https://checkout.thawani.om/pay/') . $session_id . '?key=' . $public_key;

        $ci->db->where('id', $invoice->id);
        $ci->db->update(db_prefix() . 'invoices', [
            'thawani_session_id'   => $session_id,
            'thawani_checkout_url' => $checkout_url,
        ]);

        redirect($checkout_url);
    }

    public function checkPaymentStatus($session_id)
    {
        $session_url = $this->getUrl('checkout/session') . '/' . $session_id;
        $private_key = $this->decryptSetting('secret_key');

        $headers = [
            'Thawani-Api-Key: ' . $private_key,
            'Content-Type: application/json',
        ];

        $ch = curl_init($session_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $session_response = curl_exec($ch);
        $session_httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error || $session_httpcode !== 200 || !$session_response) {
            log_message('error', '[ThawaniPay] Session error: ' . $curl_error . ' | HTTP: ' . $session_httpcode);
            return null;
        }

        $session_data = json_decode($session_response, true);

        if (!isset($session_data['data']['payment_status'])) {
            log_message('error', '[ThawaniPay] payment_status not found. Session Data: ' . print_r($session_data, true));
            return null;
        }

        return [
            'success' => 1,
            'data' => [
                'status' => $session_data['data']['payment_status'],
                'invoice_id' => $session_data['data']['metadata']['invoice_id'] ?? null,
                'invoice_hash' => $session_data['data']['metadata']['invoice_hash'] ?? null,
                'amount' => isset($session_data['data']['products'][0]['unit_amount']) ? $session_data['data']['products'][0]['unit_amount']  : null,
                'session_id' => $session_id,
            ]
        ];
    }

    protected function getUrl($endpoint = '')
    {
        $base = (bool)$this->getSetting('test_mode_enabled')
            ? 'https://uatcheckout.thawani.om/api/v1/'
            : 'https://checkout.thawani.om/api/v1/';
        return rtrim($base, '/') . '/' . ltrim($endpoint, '/');
    }
    
    public function refund($payment_id)
    {
    $url = $this->getUrl('refunds');
    $secret = $this->decryptSetting('secret_key');

    $payload = ['payment_id' => $payment_id];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Thawani-Api-Key: ' . $secret,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code !== 200) {
        log_message('error', "[ThawaniPay] Refund failed: $err | $response");
        return ['success' => false, 'error' => 'API error'];
    }

    $res = json_decode($response, true);
    if ($res['success']) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $res['message'] ?? 'Unknown error'];
    }
}

}
