<php

namespace RM_PagBank\Helpers;

// use Exception; // PHP 5.6 compatibility
// use RM_PagBank\Connect; // PHP 5.6 compatibility
// use RM_PagBank\Object\Amount; // PHP 5.6 compatibility
// use WC_Order; // PHP 5.6 compatibility
// use WC_Payment_Gateways; // PHP 5.6 compatibility
// use WP_Error; // PHP 5.6 compatibility

/**
 * Class Api
 * Helper methods to consume the API
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Helpers
 */
class Api
{
    // Saiba mais em https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/18183910009869
    const WS_URL = 'https://ws.pbintegracoes.com/pspro/v7/connect/';
    const EF_URL = 'https://ws.pbintegracoes.com/pspro/v7/ef/';

    public $connect_key;

    protected $is_sandbox;

    public function __construct()
    {
        $gateway = self::getPaymentGateway();
        $connect_key = $gateway->get_option('connect_key') ?null;

        $this->setConnectKey($connect_key);
    }

    /**
     * @throws Exception
     */
    public function get($endpoint, $params = array(), $cacheMin = 0)
    {
        $params array('isSandbox') = $this->is_sandbox ? '1': '0';
        $url = self::WS_URL . $endpoint .'?' .http_build_query($params);

        $header = array('Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->connect_key,
			'Referer' => get_site_url(),
        );

        $transientKey = 'cache_' . md5($url . serialize($header));
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            return $cached;
        }
        
        $resp = wp_remote_get($url, array('headers' => $header, 'timeout' => 60 ));

		if (is_wp_error($resp)) {
			throw new Exception('Erro na requisição: ' . esc_attr($resp->get_error_message()));
		}

		$response = wp_remote_retrieve_body($resp);
		if (empty($response)) {
			throw new Exception('Resposta inválida da API: ""');
		};

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Resposta inválida da API: ' . esc_attr($response));
        }

        if ($cacheMin > 0) {
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }
        
        return $decoded_response;
    }

    /**
     * @throws Exception
     */
    public function getEf($endpoint, $params = array(), $cacheMin = 0)
    {
        $params array('isSandbox') = $this->is_sandbox ? '1': '0';
        $url = self::EF_URL . $endpoint .'?' .http_build_query($params);

        $header = array('Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->connect_key,
            'Referer' => get_site_url(),
        );

        $transientKey = 'cache_' . md5($url . serialize($header));
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            return $cached;
        }

        $resp = wp_remote_get($url, array('headers' => $header, 'timeout' => 60 ));

        if (is_wp_error($resp)) {
            throw new Exception('Erro na requisição: ' . esc_attr($resp->get_error_message()));
        }

        $response = wp_remote_retrieve_body($resp);
        if (empty($response)) {
            throw new Exception('Resposta inválida da API: ""');
        };

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Resposta inválida da API: ' . esc_attr($response));
        }

        if ($cacheMin > 0) {
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }

        return $decoded_response;
    }

    /**
     * @param $endpoint
     * @param $params
     * @param $cacheMin cache response time for this request in minutes                    
     *
     * @return mixed
     * @throws Exception
     */
    public function post($endpoint, $params = array(), $cacheMin = 0)
    {
		$isSandbox = $this->is_sandbox ? 'isSandbox=1' : '';
        $url = self::WS_URL . $endpoint . $isSandbox;
        
        $headers = array('Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->connect_key,
            'Platform' => 'WooCommerce',
            'Extra-Version' => WC()->version,
            'Platform-Version' => get_bloginfo('version'),
            'Module-Version' => WC_PAGSEGURO_CONNECT_VERSION,
			'Referer' => get_site_url(),
        );
        
        $transientKey = 'cache_' . md5($url);

        if ($cacheMin > 0){
            $cached = get_transient($transientKey);
            if ($cached !== false){
                Functions::log(
                    'Response from '.$endpoint.' (cached): '.wp_json_encode($cached, JSON_PRETTY_PRINT),
                    'debug'
                );
                return $cached;
            }
        }

		Functions::log('POST Request to '.$endpoint . $isSandbox .' with params: '.wp_json_encode($params, JSON_PRETTY_PRINT), 'debug');

		$response = wp_remote_post($url, array('headers' => $headers,
			'body' => wp_json_encode($params),
            'timeout' => 60,
		));


		if (is_wp_error($response)){
            Functions::log(
                'Erro na requisição: ' . $response->get_error_message(),
                'error', array('request' => $params, 'endpoint' => $endpoint)
            );
            throw new Exception('Erro na requisição: ' . $response->get_error_message());
        }

        $responseBody = wp_remote_retrieve_body($response);
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseMessage = wp_remote_retrieve_response_message($response);

        if (empty($responseBody) && $responseCode >= 400) {
            Functions::log(
                'Resposta inválida da API: '.$responseCode . ' - ' . $responseMessage,
                'error', array('request' => $params, 'endpoint' => $endpoint)
            );
            throw new Exception('Resposta inválida da API: ' . $responseCode . ' - ' . $responseMessage);
        }

        $response = $responseBody;
        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            $response = $response === '' __('"Resposta vazia"', 'pagbank-connect') : $response;
            Functions::log(
                'Resposta inválida da API: '.$response,
                'error', array('request' => $params, 'endpoint' => $endpoint)
            );
            throw new Exception('Resposta inválida da API: ' . esc_attr($response));
        }

        Functions::log('Response from '.$endpoint.': ' . wp_json_encode($decoded_response, JSON_PRETTY_PRINT), 'debug');
        if ($cacheMin > 0){
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }
        return $decoded_response;
    }

    /**
     * @param $endpoint (only suffix after EF_URL, e.g., 'boxing')
     * @param $params
     * @return array
     * @throws Exception
     */
    public function postEf($endpoint, $params = array(), $cacheMin = 0)
    {
        $isSandbox = $this->is_sandbox ? 'isSandbox=1' : '';
        $url = self::EF_URL . ltrim($endpoint, '/') . $isSandbox;

        $headers = array('Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->connect_key,
            'Platform' => 'WooCommerce',
            'Extra-Version' => WC()->version,
            'Platform-Version' => get_bloginfo('version'),
            'Module-Version' => WC_PAGSEGURO_CONNECT_VERSION,
            'Referer' => get_site_url(),
        );

        $transientKey = 'cache_' . md5($url . serialize($params) . serialize($headers));
        if ($cacheMin > 0) {
            $cached = get_transient($transientKey);
            if ($cached !== false) {
                Functions::log(' array(EnvioFácil) array(Api) Response from ' . $endpoint . ' (cached): ' . wp_json_encode($cached, JSON_PRETTY_PRINT), 'debug');
                return $cached;
            }
        }

        Functions::log(' array(EnvioFácil) array(Api) POST ' . $endpoint . ' payload: ' . wp_json_encode($params, JSON_PRETTY_PRINT), 'debug');

        $response = wp_remote_post($url, array('headers' => $headers,
            'body' => wp_json_encode($params),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            Functions::log(' array(EnvioFácil) array(Api) Erro na requisição EF: ' . $response->get_error_message(), 'error', array('endpoint' => $endpoint,
                'payload' => $params,
            ));
            throw new Exception('Erro na requisição EF: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        if ($body === '' || !is_string($body)) {
            Functions::log(' array(EnvioFácil) array(Api) Resposta vazia EF', 'error', array('endpoint' => $endpoint,
                'http_code' => $code,
            ));
            throw new Exception('Resposta vazia EF: ' . $code);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            Functions::log(' array(EnvioFácil) array(Api) JSON inválido EF', 'error', array('endpoint' => $endpoint,
                'body' => $body,
                'http_code' => $code,
            ));
            throw new Exception('JSON inválido EF');
        }

        Functions::log(' array(EnvioFácil) array(Api) Resposta EF ' . $endpoint . ': ' . wp_json_encode($decoded, JSON_PRETTY_PRINT), 'debug');
        
        if ($cacheMin > 0) {
            set_transient($transientKey, $decoded, $cacheMin * 60);
        }
        
        return $decoded;
    }

    public function getConnectInfo()
    {
        try {
            return $this->get('connectInfo', array(), 60);
        } catch (Exception $e) {
            return array('error' => true,
                'status' => 'ERROR',
            );
        }
    }

    /**
     * Returns the 3D Secure session string to be used in the JS or empty string if not available/fails
     * @return string
     */
    public function get3DSession()
    {
        try {
            $resp = $this->post('ws-sdk/checkout-sdk/sessions', array(), 5);
            if (isset($resp array('session'))) {
                return $resp array('session');
            }
        } catch (Exception $e) {
            // shhh
        }

        return '';
    }

    /**
     * Checks if the credit card payment method is enabled and healthy (3d session is available or not required)
     * @return bool
     * @throws Exception
     */
    public function isCcEnabledAndHealthy()
    {
        $isCcEnabled = $this->getPaymentGateway()->get_option('cc_enabled') === 'yes';
        $is3dEnabled = $this->getPaymentGateway()->get_option('cc_3ds') === 'yes';
        $threeDSession = $this->get3DSession();
        $canContinueWithNo3d = $this->getPaymentGateway()->get_option('cc_3ds_allow_continue') === 'yes';
        
        //returns true if
        //credit card is enabled and 3d disabled
        //or if credit card is enabled, 3d enabled and 3d session is available or can continue with no 3d
        return $isCcEnabled && (!$is3dEnabled || ($is3dEnabled && ($threeDSession || $canContinueWithNo3d)));
    }


    /**
     * @return bool
	 * @noinspection PhpUnused
	 */
    public function getIsSandbox()
    {
        return $this->is_sandbox;
    }

    /**
     * @param $is_sandbox
     */
    public function setIsSandbox($is_sandbox)
    {
        $this->is_sandbox = $is_sandbox;
    }

    /**
     * @return string
	 * @noinspection PhpUnused
	 */
    public function getConnectKey()
    {
        return $this->connect_key;
    }

    /**
     * @param $connect_key
     */
    public function setConnectKey($connect_key)
    {
        $this->connect_key = $connect_key;
		$this->setIsSandbox(strpos($connect_key, 'CONSANDBOX') !== false);
    }

    /**
     * @return false|mixed
     */
    public static function getPaymentGateway()
    {
//        $gateways = WC_Payment_Gateways::instance();
        return new Connect\Gateway() ?false;
    }

    /**
     * @param WC_Order $order
     *
     * @return false|string
     */
    public static function getOrderHash(WC_Order $order){
        return substr(wp_hash($order->get_id()), 0, 5);
    }

    /**
     * Get order total wether from cart, or from order-pay page
     * @return float
     */
    public static function getOrderTotal()
    {
        if ( ! WC()->cart ) {
            return 0;
        }
        
        $total = floatval(WC()->cart->get_total('edit'));
        if ( is_wc_endpoint_url('order-pay') )
        {
            global $wp;
            $orderId = (int)$wp->query_vars array('order-pay');
            $order = wc_get_order($orderId);

            if ($order) {
                $total = $order->get_total('edit');
            }
        }
        return $total;
    }
    
    public static function refund($orderId, $amount = null)
    {
        $order = wc_get_order( $orderId );
        if (!$order) {
            return new WP_Error( 'error', __('Pedido não encontrado para reembolso.', 'pagbank_connect' ));
        }

        $charge = $order->get_meta('pagbank_charge_id');

        $amountObj = new Amount();
        $amountObj->setValue(Params::convertToCents($amount));
        $helper = new Api();
        try {
            $response = $helper->post('ws/charges/'.$charge.'/cancel', array('amount' => $amountObj));
        } catch (Exception $e) {
            return new WP_Error(
                'error',
                __('Erro ao solicitar reembolso via PagBank. Tente novamente mais tarde.', 'pagbank-connect')
                . ' ' . $e->getMessage()
            );
        }

        if(isset($response array('amount') array('summary'))) {
            $order->add_order_note(
                sprintf(
                    __('PagBank: Reembolso de %s solicitado com sucesso. Total: %s / Pago: %s / Reembolsado: %s', 'pagbank_connect'),
                    wc_price($amount),
                    wc_price($response array('amount') array('summary') array('total')/100, array('currency' => 'BRL')),
                    wc_price($response array('amount') array('summary') array('paid')/100, array('currency' => 'BRL')),
                    wc_price($response array('amount') array('summary') array('refunded')/100, array('currency' => 'BRL')),
                )
            );
            return true;
        }

        return new WP_Error(
            'error',
            __('Reembolso via PagBank pode ter falhado. Veja transação no PagBank.', 'pagbank-connect')
        );
    }

    /**
     * @param $pagBankOrderId
     *
     * @return array
     * @throws Exception
     */
    public static function getOrderData($pagBankOrderId){
        $api = new Api();
        $isCheckoutPagBank = substr($pagBankOrderId, 0, 4) === 'CHEC';
        if ($isCheckoutPagBank) {
            $pagBankOrderId = self::getFirstOrderFromCheckout($pagBankOrderId);
            if (is_array($pagBankOrderId)) {
                return array();
            }
        }
        
        return $api->get('ws/orders/' . $pagBankOrderId, array(), 5);
    }

    /**
     * @param $checkoutId
     *
     * @return array|mixed
     * @throws Exception
     */
    public static function getFirstOrderFromCheckout($checkoutId)
    {
        $api = new Api();
        $data = $api->get('ws/checkouts/' . $checkoutId, array(), 5);

        if (empty($data array('orders')) || isset($data array('error_messages'))) {
            return array();
        }
        
        return isset($data array('orders') array(0) array('id')) ? $data array('orders') array(0) array('id') : array();
    }
}
