<php

namespace RM_PagBank\Connect\Payments;

// use RM_PagBank\Connect; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Functions; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Params; // PHP 5.6 compatibility
// use RM_PagBank\Object\Amount; // PHP 5.6 compatibility
// use RM_PagBank\Object\AuthenticationMethod; // PHP 5.6 compatibility
// use RM_PagBank\Object\Buyer; // PHP 5.6 compatibility
// use RM_PagBank\Object\Card; // PHP 5.6 compatibility
// use RM_PagBank\Object\Charge; // PHP 5.6 compatibility
// use RM_PagBank\Object\Fees; // PHP 5.6 compatibility
// use RM_PagBank\Object\Holder; // PHP 5.6 compatibility
// use RM_PagBank\Object\Interest; // PHP 5.6 compatibility
// use RM_PagBank\Object\PaymentMethod; // PHP 5.6 compatibility
// use RM_PagBank\Object\Recurring; // PHP 5.6 compatibility
// use WC_Order; // PHP 5.6 compatibility

/**
 * Class CreditCard
 *
 * @author    Ricardo Martins
 * @copyright 2024 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class CreditCardToken extends Common
{
    public $code = 'credit_card_token';

    /**
	 * @param WC_Order $order
	 */
    public function __construct(WC_Order $order)
    {
        parent::__construct($order);
    }

    /**
     * Create the array with the data to be sent to the API
     *
     * @return array
     */
    public function prepare()
    {
        return array('encrypted' => $this->order->get_meta('_pagbank_card_encrypted')
        );
    }

    /**
     * Process response from the API and add the metadata to the order
     * @param WC_Order $order
     * @param $response
     *
     * @return void
     */
    public function process_response(WC_Order $order, $response)
    {
        $order->add_meta_data('pagbank_order_recurring_card', $response ?null, true);
        $order->add_meta_data('pagbank_is_sandbox', Params::getConfig('is_sandbox', false) ? 1 : 0);
        $order->update_status('processing', 'PagBank: Pagamento Pendente');
        do_action('pagbank_connect_after_proccess_response', $order, $response);
    }
}
