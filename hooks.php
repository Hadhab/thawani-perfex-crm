hooks()->add_action('after_payment_modal_view', 'add_thawani_refund_button');

function add_thawani_refund_button($payment)
{
    $CI =& get_instance();
    $CI->load->model('payment_modes_model');

    $mode = $CI->payment_modes_model->get($payment->paymentmode);

    if (!$mode || strtolower($mode->name) !== 'thawani pay') {
        return;
    }

    $refund_url = admin_url('thawanipay/refund/' . $payment->id);

    echo '<a href="' . $refund_url . '" class="btn btn-danger btn-sm" style="margin-top:10px;" onclick="return confirm(\'Are you sure you want to refund this payment?\')">
            <i class="fa fa-undo"></i> Refund
          </a>';
}
