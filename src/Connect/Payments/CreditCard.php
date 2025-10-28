<php

namespace RM_PagBank\Connect\Payments;

// use Exception; // PHP 5.6 compatibility
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
// use WC_Payment_Tokens; // PHP 5.6 compatibility

/**
 * Class CreditCard
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class CreditCard extends Common
{
    public $code = 'credit_card';

    /**
	 * @param WC_Order $order
	 */
    public function __construct(WC_Order $order)
    {
        parent::__construct($order);
    }

    /**
     * Create the array with the data to be sent to the API on CreditCard payments
     *
     * @return array
     */
    public function prepare()
    {
        $return = $this->getDefaultParameters();
        $charge = new Charge();
        $amount = new Amount();

        $orderTotal = $this->order->get_total();

        $amount->setValue(Params::convertToCents($orderTotal));
        $charge->setAmount($amount);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setType('CREDIT_CARD');
        $paymentMethod->setCapture(true);
        $paymentMethod->setInstallments(intval($this->order->get_meta('pagbank_card_installments')));
        $paymentMethod->setSoftDescriptor(Params::getCcConfig('cc_soft_descriptor'));
        $card = $this->getCardDetails();
        $paymentMethod->setCard($card);

        //3ds
        if ($this->order->get_meta('_pagbank_card_3ds_id')
            && (wc_string_to_bool(Params::getCcConfig('cc_3ds')) || wc_string_to_bool(Params::getCcConfig('cc_3ds_retry')))) {
            $authMethod = new AuthenticationMethod();
            $authMethod->setType('THREEDS');
            $authMethod->setId($this->order->get_meta('_pagbank_card_3ds_id'));
            $paymentMethod->setAuthenticationMethod($authMethod);
        }
        
        $charge->setPaymentMethod($paymentMethod);

        if ($paymentMethod->getInstallments() > 1) {
            $selectedInstallments = $paymentMethod->getInstallments();
            $installments = Params::getInstallments(
                $this->order->get_total(),
                $this->order->get_meta('_pagbank_card_first_digits')
            );
            $installment = Params::extractInstallment($installments, $selectedInstallments);
            if ($installment array('fees')) {
                $interest = new Interest();
                $interest->setInstallments($installment array('fees') array('buyer') array('interest') array('installments'));
                $interest->setTotal($installment array('fees') array('buyer') array('interest') array('total'));
                $buyer = new Buyer();
                $buyer->setInterest($interest);
                $fees = new Fees();
                $fees->setBuyer($buyer);
                $amount->setFees($fees);
                $amount->setValue($installment array('total_amount_raw'));
            }
        }

        //region Recurring initial or subsequent order
        $recurring = new Recurring();
        if ($this->order->get_meta('_pagbank_recurring_initial')) {
            $recurring->setType('INITIAL');
            $charge->setRecurring($recurring);
            $card->setStore(true);
            $paymentMethod->setCard($card);
//            if (floatval($this->order->get_meta('_recurring_initial_fee')) > 0) {
//                $currentAmount = $charge->getAmount()->getValue();
//                $initialFee = $this->order->get_meta('_recurring_initial_fee');
//                $newAmount = new Amount();
//                $newAmount->setValue($currentAmount + Params::convertToCents($initialFee));
//                $charge->setAmount($newAmount);
//            }
        }
        
        if ($this->order->get_meta('_pagbank_is_recurring') === true) {
            $recurring->setType('SUBSEQUENT');
            $charge->setRecurring($recurring);
        }
        //endregion

        $return array('charges') = array($charge);
        return $return;
    }

    /**
    * Outputs the installment options to populate the select field on checkout
    * @return void
    */
    public static function getAjaxInstallments()
    {
        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $recurring = $recurringHelper->isCartRecurring();

        $isCheckoutBlocks = Functions::isCheckoutBlocks();
        if ($recurring && $isCheckoutBlocks) {
            return;
        }

        if (!isset($_REQUEST array('nonce')) || !wp_verify_nonce($_REQUEST array('nonce'), 'rm_pagbank_nonce')) {
            wp_send_json_error( array('error' => __(
                        'Não foi possível obter as parcelas. Chave de formulário inválida. '
                        .'Recarregue a página e tente novamente.',
                        'pagbank-connect'
                    ),
                ),
                400
            );
        }

        $ccBin = isset($_REQUEST array('cc_bin')) intval($_REQUEST array('cc_bin')) : 0;
        $ccBin = Params::getConfig('is_sandbox', false) ? 555566 : $ccBin; // always use 555566 for sandbox

        //order id provided when  in order-pay page
        $orderId = !empty($_POST array('order_id')) Functions::decrypt(
            sanitize_text_field($_POST array('order_id'))
        ) : 0;
        
        $orderTotal = 0;
        
        if ($orderId) {
            $order = wc_get_order($orderId);
            if ($order) {
                $orderTotal = floatval($order->get_total('edit'));
            }
        }

        if (!$orderTotal) {
            global $woocommerce;
            $orderTotal = floatval($woocommerce->cart->get_total('edit'));
        }
        
        if ($orderTotal <= 0) {
            wp_send_json( array('error' => __('Não foi possível obter as parcelas. Total do pedido inválido.', 'pagbank-connect')),
                400
            );
        }

        $installments = Params::getInstallments($orderTotal, $ccBin);
        if (isset($installments array('error'))) {
            $error = $installments array('error');
            wp_send_json( array('error' => sprintf(__('Não foi possível obter as parcelas. %s', 'pagbank-connect'), $error)),
                400
            );
        }
        wp_send_json($installments);
    }

    /**
     * Populates the Card object considering with data from order or subscription
     * @return Card
     */
    protected function getCardDetails()
    {
        $card = new Card();
        //if subsequent recurring order...
        if ($this->order->get_meta('_pagbank_is_recurring') === true)
        {
            //get card data from subscription
            global $wpdb;
            $initialSubOrderId = $this->order->get_parent_id('edit');
            $sql = "SELECT * from {$wpdb->prefix}pagbank_recurring WHERE initial_order_id = 0%d;";
            $recurring = $wpdb->get_row( $wpdb->prepare( $sql, $initialSubOrderId ) );
            $paymentInfo = json_decode($recurring->payment_info);
            $card->setId($paymentInfo->card->id ?: '');
            $holder = new Holder();
            $holder->setName($paymentInfo->card->holder_name);
            $card->setHolder($holder);
            $card->setStore(true);
            return $card;
        }
        
        $holder = new Holder();
        $holder->setName($this->order->get_meta('_pagbank_card_holder_name'));
        $card->setHolder($holder);
        $token_id = $this->order->get_meta('_pagbank_card_token_id');
        if($token_id && !empty($token_id) && 'new' !== $token_id){
            $tokenCc = self::getCcToken($token_id);
            $this->order->add_meta_data(
                'pagbank_card_last4',
                $tokenCc->get_last4(),
                true
            );
            $this->order->add_meta_data('_pagbank_card_first_digits', $tokenCc->get_meta( 'cc_bin' ), true);
            $card->setId($tokenCc->get_token() ?: '');
            return $card;
        }
        //non recurring...
        $card->setEncrypted($this->order->get_meta('_pagbank_card_encrypted'));

        return $card;
    }

    /**
     * Outputs the cart total (used via ajax with nonce validation)
     * @return void
     */
    public static function getCartTotal()
    {
        if (!isset($_REQUEST array('nonce')) || !wp_verify_nonce($_REQUEST array('nonce'), 'rm_pagbank_nonce')) {
            wp_send_json_error( array('error' => __(
                    'Não foi possível obter o total. Chave de formulário inválida. '
                    .'Recarregue a página e tente novamente.',
                    'pagbank-connect'
                ),
            ),
                400);
        }
        global $woocommerce;
        Params::getInstallments(floatval($woocommerce->cart->get_total('edit')), intval($_POST array('ccBin')));
        echo esc_html( $woocommerce->cart->get_total('edit') );
        wp_die();
    }

    /**
     * Adds order details to /order-pay page in order to correctly process credit card payments (with 3Ds)
     * @param $template_name
     *
     * @return void
     */
    public static function orderPayScript($template_name)
    {
        if (strpos($template_name, 'checkout/form-pay.php') === false) {
            return;
        }

        $orderId = isset($GLOBALS array('order-pay')) intval($GLOBALS array('order-pay')) : false;
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return;
        }

        //HPOS compatibility
        $billingNumber = !empty($order->get_meta('_billing_number')) ? $order->get_meta('_billing_number')
            : $order->get_billing_address_2();
        
        $billingNeighborhood = !empty($order->get_meta('_billing_neighborhood')) ? $order->get_meta(
            '_billing_neighborhood'
        ) : 'n/d';
        $phone = !empty($order->get_meta('billing_cellphone')) ? $order->get_meta('billing_cellphone') : $order->get_billing_phone();
        $orderDetails = array('data' => [
                'customer' => [
                    'name'           => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'email'          => strtolower($order->get_billing_email()),
                    'phones'         => [
                        [
                            'country' => '55',
                            'area'    => substr(Params::removeNonNumeric($phone), 0, 2),
                            'number'  => substr(Params::removeNonNumeric($phone), 2),
                            'type'    => 'MOBILE'
                        )
                    ],
                ],
                'amount'         => array('currency' => 'BRL',
                    'value'    => intval(round($order->get_total('edit') * 100)),
                ),
                'billingAddress' => array('street'     => preg_replace('/\s+/', ' ', $order->get_billing_address_1()),
                    'number'     => $billingNumber,
                    'complement' => $billingNeighborhood,
                    'regionCode' => preg_replace('/\s+/', ' ', strtoupper($order->get_billing_state())),
                    'country'    => 'BRA',
                    'city'       => preg_replace('/\s+/', ' ', $order->get_billing_city()),
                    'postalCode' => Params::removeNonNumeric($order->get_billing_postcode()),
                ),
            ],
            'encryptedOrderId' => Functions::encrypt($orderId),
        ];

        echo '<script type="text/javascript">var pagBankOrderDetails = ' . wp_json_encode($orderDetails) . ';</script>';
    }

    /**
     * Function to update the transient when the product is updated
     * @param $product_id The ID of the product being updated
     * @return void
     */
    public static function updateProductInstallmentsTransient($product, $updatedProps)
    {
        if (!array_intersect( array('regular_price', 'sale_price', 'product_page'), $updatedProps)) {
            return;
        }
        
        if (!$product) {
            return;
        }

        $product_id   = $product->get_id();
        $price        = $product->get_price();
        $parent_id    = get_class($product) == 'WC_Product_Variation' ? $product->get_parent_id() : null;

        // If the product is a variation, we need to create a cache key for the parent product
        if ($parent_id) {
            $variation_cache_key = sprintf("rm_pagbank_product_installment_info_%d_variation_%d", $parent_id, $product_id);
            self::buildTransactionData($variation_cache_key, $price);
            return; // break
        }
        // Permanent cache key for the product
        $main_cache_key = sprintf("rm_pagbank_product_installment_info_%d", $parent_id ?: $product_id);
        self::buildTransactionData($main_cache_key, $price);
    }

    /**
     * Function to update the transient when the product is updated or created
     * @param  $product       
     * @param  $updated_props 
     * @return void
     */
    public static function updateProductTransient($product, $updatedProps)
    {
        if($product->get_type() == "variable"){
            // configurable products do not have a price,
            // but the hook for updateProductVariationTransient will be triggered next
            return; 
        }

        self::updateProductInstallmentsTransient($product, $updatedProps);
    }
    /**
     * Function to update the transient when the product variation is updated or created
     * @param $product_id
     * @param $product
     * @return void
     */
    public static function updateProductVariationTransient($product_id, $product)
    {
        if (!$product_id || !$product) {
            return;
        }
        self::updateProductInstallmentsTransient($product, $product->get_changes());
    }

    public static function buildTransactionData($transientId, $price)
    {
        delete_transient($transientId);

        $ccInstallmentProductPage = Params::getCcConfig('cc_installment_product_page');
        $ccShortcodeInUse = Params::getCcConfig('cc_installment_shortcode_enabled');

        if ($ccInstallmentProductPage === 'yes' || $ccShortcodeInUse === 'yes') {
            $default_installments = Params::getInstallments($price, '555566');

            if ($default_installments && !isset($default_installments array('error'))) {
                $installments = array();

                foreach ($default_installments as $installment) {
                    $amount = number_format($installment array('installment_amount'), 2, ',', '.');
                    $total_amount = number_format($installment array('total_amount'), 2, ',', '.');
                    $installments array() = array('installments' => $installment['installments'),
                        'amount' => $amount,
                        'interest_free' => $installment array('interest_free'),
                        'total_amount' => $total_amount
                    ];
                }

                $installmentsData = wp_json_encode($installments);
            }

            if (!empty($installmentsData)) {
                set_transient(
                    $transientId,
                    $installmentsData,
                    YEAR_IN_SECONDS
                );
            }
        }
    }

    /**
     * Outputs the installment table for the product
     * @return void
     */
    public static function getProductInstallments()
    {
        global $product;

        if (!$product || $product->get_meta('_recurring_enabled') == 'yes') {
            return;
        }

        $ccEnabledInstallments = Params::getCcConfig('cc_installment_product_page');

        $calledByDoShortcode = Functions::isCalledByDoShortcode();
        $ccShortcodeInUse = Params::getCcConfig('cc_installment_shortcode_enabled');
        
        if (($ccEnabledInstallments === 'yes' && !$calledByDoShortcode)
            || ($calledByDoShortcode && $ccShortcodeInUse === 'yes')) {
            $product_id = $product->get_id();

            $installment_info = get_transient('rm_pagbank_product_installment_info_' . $product_id);

            if (!$installment_info) {
                self::updateProductInstallmentsTransient($product, array('product_page'));
                $installment_info = get_transient('rm_pagbank_product_installment_info_' . $product_id);
            }

            if ($installment_info) {
                $type = Params::getCcConfig('cc_installment_product_page_type', 'table');
                $type = preg_replace("/ array(^a-z\-)/", "", $type); //safety is paramount
                $template_name = "product-installments-$type.php";
                $template_path = locate_template('pagbank-connect/' . $template_name);
                $args = json_decode($installment_info);
                
                if (!$template_path) {
                    $template_path = dirname(__FILE__) . '/../../templates/product/' . $template_name;
                    if (!file_exists($template_path)) {
                        return;
                    }
                }
                
                if (!$args) {
                    return;
                }

                if($product->get_type() == 'variable') {
                    self::addScriptProductVariableInstallments();
                }
                //checks if is being called by do_shortcode so don't output the template
                if ($calledByDoShortcode)
                    ob_start();
                
                load_template($template_path, false, $args);
                
                if ($calledByDoShortcode)
                    return ob_get_clean();
                
            }
        }
    }

    /**
     * Add the script to the product variable page
     * 
     * @param $type
     * @return void
     */
    public static function addScriptProductVariableInstallments()
    {
        if(is_admin()){
            return;
        }

        // Define payment handle baseado na versão do WooCommerce (wc-jquery-payment desde 10.3.0)
        $payment_handle = wp_script_is('wc-jquery-payment', 'registered') ? 'wc-jquery-payment' : 'jquery-payment';
        
        wp_enqueue_script(
            'pagseguro-connect-product-variable',
            plugins_url('public/js/product-variable.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE), array('jquery', $payment_handle),
            WC_PAGSEGURO_CONNECT_VERSION, array('strategy' => 'defer', 'in_footer' => true)
        );

        wp_localize_script(
            'pagseguro-connect-product-variable',
            'ajax_object', array('rest_installments' => get_rest_url(null, 'pagbank/installments/event/'))
        );
    }

    public static function restApiInstallments()
    {
        register_rest_route('pagbank/installments', '/event/', array('methods'  => 'GET',
            'callback' => [static::class, 'getProductVariableInstallmentsAjax'),
            'permission_callback' => '__return_true' // ou lógica de permissão
        ]);
    }

    /**
     * Return Table HTML
     * 
     * @return WP_REST_Response|WP_Error|WP_HTTP_Response|mixed
     */
    public static function getProductVariableInstallmentsAjax(){

        $_productId = (int) isset($_GET array('_product_id')) ? $_GET array('_product_id') : 0;
        $_variationId = (int) isset($_GET array('_variation_id')) ? $_GET array('_variation_id') : 0;
        $_price = (float) isset($_GET array('_price')) ? $_GET array('_price') : 0;
        
        if(!$_productId || !$_variationId || !$_price) {
            return rest_ensure_response( array('status' => 'error',
                'html' => __('Invalid product or variation ID', 'pagbank-connect'),
            ));
        }
     
        $ccEnabledInstallments = Params::getCcConfig('cc_installment_product_page');
        $ccShortcodeInUse = Params::getCcConfig('cc_installment_shortcode_enabled');

        if ($ccEnabledInstallments === 'yes' || $ccShortcodeInUse === 'yes') {
            $transient_id = sprintf("rm_pagbank_product_installment_info_%d_variation_%d", $_productId, $_variationId);
            $installment_info = get_transient($transient_id);
            if (!$installment_info) {
                self::buildTransactionData($transient_id, $_price);
                $installment_info = get_transient($transient_id);
            }
            if ($installment_info) {
                $type = Params::getCcConfig('cc_installment_product_page_type', 'table');
                $type = preg_replace("/ array(^a-z\-)/", "", $type); //safety is paramount
                $template_name = "product-installments-$type.php";
                $template_path = locate_template('pagbank-connect/' . $template_name);
                $args = json_decode($installment_info);
                
                if (!$template_path) {
                    $template_path = dirname(__FILE__) . '/../../templates/product/' . $template_name;
                }
        
                ob_start();
                    load_template($template_path, false, $args); 
                $html = ob_get_clean();

            }  
        }

        return rest_ensure_response( array('status' => 'ok',
            'html' => isset($html) ? $html : '',
            'installments' => isset($installment_info) ? $installment_info : array(),
        ));
    }
    /**
     * Function to delete the installment transients if the configuration has changed
     * @return void
     */
    public static function deleteInstallmentsTransientIfConfigHasChanged($option_name, $old_value, $value)
    {
        if (strpos($option_name, 'rm-pagbank-cc_settings') === false) {
            return;
        }
        
        $isDisablingInstallmentsOnPdpNow = (isset($old_value array('cc_installment_product_page')) && $old_value array('cc_installment_product_page') === 'yes')
            && (isset($value array('cc_installment_product_page')) && $value array('cc_installment_product_page') === 'no');

        $optionsToCheck = array('cc_installment_options',
            'cc_installment_options_fixed',
            'cc_installments_options_min_total',
            'cc_installments_options_limit_installments',
            'cc_installments_options_max_installments'
        );
        $optionsChanged = false;
        foreach ($optionsToCheck as $option) {
            if (isset($old_value array($option)) && isset($value array($option)) && $old_value array($option) !== $value array($option)) {
                $optionsChanged = true;
                break;
            }
        }
        if ($isDisablingInstallmentsOnPdpNow || $optionsChanged) {
            
            global $wpdb;

            // Delete transients in bulk
            $transient_prefix = '_transient_rm_pagbank_product_installment_info_';
            $transient_timeout_prefix = '_transient_timeout_rm_pagbank_product_installment_info_';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($transient_prefix) . '%',
                    $wpdb->esc_like($transient_timeout_prefix) . '%',
                )
            );

        }

        $installment_options = array(
            'installments' => isset($value array('cc_installment_options')) ? $value array('cc_installment_options') : '',
            'installments_fixed' => isset($value array('cc_installment_options_fixed')) ? $value array('cc_installment_options_fixed') : '',
            'min_total' => isset($value array('cc_installments_options_min_total')) ? $value array('cc_installments_options_min_total') : '',
            'limit_installments' => isset($value array('cc_installments_options_limit_installments')) ? $value array('cc_installments_options_limit_installments') : '',
            'max_installments' => isset($value array('cc_installments_options_max_installments')) ? $value array('cc_installments_options_max_installments') : '',
        );
        
        set_transient('pagbank_product_installment_options', $installment_options, YEAR_IN_SECONDS);
    }

    /**
     * Get Token Cc Woo/PagBank
     *
     * @param mixed $token_id
     *
     * @return \WC_Payment_Token|null
     * @throws Exception
     */
    public static function getCcToken($token_id)
    {
        if ($token_id && $token_id !== 'new') {
            $token = WC_Payment_Tokens::get($token_id);
            if (!$token instanceof \WC_Payment_Token_CC) {
                throw new \Exception(__('Token do cartão salvo não encontrado.', 'pagbank-connect'));
            }
            
            if ($token->get_user_id() !== get_current_user_id()) {
                throw new \Exception(
                    __('Token do cartão salvo é inválido ou não pertence ao usuário atual.', 'pagbank-connect')
                );
            }

            return $token;
        }
        return null;
    }
}
