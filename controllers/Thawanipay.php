<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ThawaniPay extends App_Controller
{
    protected $thawani;

    public const STATUS_UNPAID    = 1;
    public const STATUS_PAID      = 2;
    public const STATUS_PARTIALLY = 3;
    public const STATUS_OVERDUE   = 4;
    public const STATUS_CANCELLED = 5;
    public const STATUS_DRAFT     = 6;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Thawani_pay_gateway');
        $this->thawani = $this->thawani_pay_gateway;
        $this->load->model('Invoices_model');
    }

    public function createPayment($invoice_id)
    {
        $invoice = $this->Invoices_model->get($invoice_id);

        if (!$invoice) {
            set_alert('danger', 'Invoice not found.');
            redirect('invoices');
            return;
        }

        $this->thawani->process_payment([
            'invoice' => $invoice,
            'amount'  => $invoice->total,
        ]);
    }

    public function success($invoice_id, $invoice_hash)
    {
        $invoice = $this->Invoices_model->get($invoice_id);

        if (!$invoice || $invoice->hash !== $invoice_hash) {
            set_alert('danger', 'Invalid invoice.');
            redirect('invoices');
            return;
        }

        $session_id = $this->input->get('session_id') ?: $invoice->thawani_session_id;

        if (!$session_id) {
            set_alert('danger', 'Missing session ID.');
            redirect('invoice/' . $invoice_id . '/' . $invoice_hash);
            return;
        }

        $response = $this->thawani->checkPaymentStatus($session_id);
        log_message('debug', '[ThawaniPay] Status response: ' . print_r($response, true));

        if ($response['success'] && isset($response['data']['status'])) {
            $status = strtolower($response['data']['status']);
            $amount_paid = isset($response['data']['amount']) ? $response['data']['amount'] / 1000 : 0; // Thawani returns in Baisa

            if ($amount_paid <= 0) {
                log_message('error', '[ThawaniPay] Amount is zero or missing.');
                set_alert('danger', 'Failed to verify payment amount.');
                redirect('invoice/' . $invoice_id . '/' . $invoice_hash);
                return;
            }

            // Record the payment
            $result = $this->recordPayment($invoice, $session_id, $amount_paid);

            if ($result['success']) {
                switch ($status) {
                    case 'paid':
                        $this->updateInvoiceStatus($invoice->id, self::STATUS_PAID);
                        set_alert('success', 'Payment successful.');
                        break;
                    case 'partially_paid':
                        $this->updateInvoiceStatus($invoice->id, self::STATUS_PARTIALLY);
                        set_alert('info', 'Partial payment recorded.');
                        break;
                    case 'cancelled':
                        $this->updateInvoiceStatus($invoice->id, self::STATUS_CANCELLED);
                        set_alert('warning', 'Payment cancelled.');
                        break;
                    default:
                        set_alert('info', 'Payment status: ' . ucfirst($status));
                        break;
                }
            } else {
                set_alert('warning', $result['error']);
            }
        } else {
            set_alert('danger', 'Failed to verify payment status.');
            log_message('error', '[ThawaniPay] Invalid response or payment verification failed.');
        }

        redirect('invoice/' . $invoice_id . '/' . $invoice_hash);
    }

    public function cancel($invoice_id, $invoice_hash)
    {
        set_alert('warning', 'Payment was cancelled.');
        redirect('invoice/' . $invoice_id . '/' . $invoice_hash);
    }

    private function updateInvoiceStatus($invoice_id, $status)
    {
        // Validate invoice_id type
        if (!is_scalar($invoice_id)) {
            log_message('error', '[ThawaniPay] Invalid invoice ID (not scalar) in updateInvoiceStatus: ' . print_r($invoice_id, true));
            return;
        }
        if (!is_numeric($invoice_id) && !is_string($invoice_id)) {
            log_message('error', '[ThawaniPay] Invalid invoice ID type in updateInvoiceStatus: ' . gettype($invoice_id));
            return;
        }

       // log_message('debug', "[ThawaniPay] Updating invoice #$invoice_id status to $status");
        //$this->Invoices_model->update($invoice->id, ['status' => $status]);
        //log_message('info', "[ThawaniPay] Invoice #$invoice_id updated to status $status.");
    }

    private function recordPayment($invoice, $transaction_id, $amount)
    {
        $ci = &get_instance();
        $ci->load->model('payment_modes_model');

        // Check if already recorded
        $exists = $ci->db->get_where('tblinvoicepaymentrecords', ['transactionid' => $transaction_id])->row();

        if ($exists) {
            return ['success' => true, 'message' => 'Payment already recorded.'];
        }

        $payment_data = [
            'invoiceid'     => $invoice->id,
            'paymentmode'   => '2',
            'paymentmethod' => '2',
            'amount'        => $amount,
            'transactionid' => $transaction_id,
            'date'          => date('Y-m-d H:i:s'),
        ];

        $insert = $ci->db->insert('tblinvoicepaymentrecords', $payment_data);

        if ($insert) {
            log_activity("Thawani Payment Recorded [Invoice: $invoice->id, Amount: $amount]");
            return ['success' => true, 'message' => 'Payment recorded successfully.'];
        } else {
            log_message('error', '[ThawaniPay] Failed to insert payment record.');
            return ['success' => false, 'error' => 'Failed to record payment.'];
        }
    }
    
    public function refund($payment_id)
    {
    if (!is_numeric($payment_id)) {
        show_404();
    }

    $this->load->model('payments_model');
    $payment = $this->payments_model->get($payment_id);

    if (!$payment || $payment->paymentmode !== 'thawani') {
        set_alert('danger', 'Invalid or unsupported payment.');
        redirect($_SERVER['HTTP_REFERER']);
    }

    // Get payment_id stored earlier (usually in transactionid)
    $thawani_payment_id = $payment->transactionid;

    // Refund via gateway
    $result = $this->thawani->refund($thawani_payment_id);

    if ($result['success']) {
        // Optionally mark refunded or delete record
        set_alert('success', 'Refund processed successfully.');
    } else {
        set_alert('danger', 'Refund failed: ' . $result['error']);
    }

    redirect($_SERVER['HTTP_REFERER']);
}

}
