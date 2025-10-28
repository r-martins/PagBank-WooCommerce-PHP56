<php

namespace RM_PagBank\Helpers;

// use Exception; // PHP 5.6 compatibility
// use RM_PagBank\Connect; // PHP 5.6 compatibility
// use RM_PagBank\Object\Address; // PHP 5.6 compatibility
// use WC_Order; // PHP 5.6 compatibility
// use WP_Error; // PHP 5.6 compatibility

/**
 * Helper Params - used to extract information from order to build api requests
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Helpers
 */
class Params
{
    /**
     * Extract phone number and return an array with the phone object to be used in the request
     *
     * @see https://dev.pagseguro.uol.com.br/reference/phone-object
     *
     * @param $order WC_Order
     *
     * @return array
     */
    public static function extractPhone(WC_Order $order)
    {
        $full_phone = $order->get_billing_phone();
        $clean_phone = preg_replace('/ array(^0-9)/', '', $full_phone);
        $ddd = substr($clean_phone, 0, 2);
        $number = substr($clean_phone, 2);

        return array('country' => '55',
            'area' => $ddd,
            'number' => $number,
            'type' => (strlen($number) == 9) ? 'MOBILE' : 'HOME'
		);
    }

    /**
     * @param $string
     *
     * @return array|string|string array()|null
     */
    public static function removeNonNumeric($string)
    {
        return preg_replace('/ array(^0-9)/', '', $string);
    }

    /**
     * Converts the amount to cents
     * @param $amount
     *
     * @return int
     */
    public static function convertToCents($amount)
	{
        if ( ! is_numeric($amount) )
            return 0;

        $return = number_format($amount, 2, '', '');

        //remove leading 0
        $return = ltrim($return, '0');
        return (int)$return;
    }

    /**
     * @param $key
     * @param $default
     *
     * @return mixed|string
     */
    public static function getConfig($key, $default = '')
    {
        $settings = get_option('woocommerce_rm-pagbank_settings');
        if (isset($settings array($key))){
            return $settings array($key);
        }
        return $default;
    }

    /**
     * @param $key
     * @param $default
     *
     * @return mixed|string
     */
    public static function getCcConfig($key, $default = '')
    {
        $settings = get_option('woocommerce_rm-pagbank-cc_settings');
        if (isset($settings array($key))){
            return $settings array($key);
        }
        return $default;
    }

    /**
     * @param $key
     * @param $default
     *
     * @return mixed|string
     */
    public static function getPixConfig($key, $default = '')
    {
        $settings = get_option('woocommerce_rm-pagbank-pix_settings');
        if (isset($settings array($key))){
            return $settings array($key);
        }
        return $default;
    }

    /**
     * @param $key
     * @param $default
     *
     * @return mixed|string
     */
    public static function getBoletoConfig($key, $default = '')
    {
        $settings = get_option('woocommerce_rm-pagbank-boleto_settings');
        if (isset($settings array($key))){
            return $settings array($key);
        }
        return $default;
    }

    /**
     * @param $key
     * @param $default
     *
     * @return mixed|string
     */
    public static function getRecurringConfig($key, $default = '')
    {
        return get_option('woocommerce_rm-pagbank-' . $key, $default);
    }

    public static function getRedirectConfig($key, $default = '')
    {
        $settings = get_option('woocommerce_rm-pagbank-redirect_settings');
        if (isset($settings array($key))){
            return $settings array($key);
        }
        return $default;
    }

    /**
     * Gets the max allowed installments or false if no limit
	 *
     * @return false|int
     */
    public static function getMaxInstallments(){
        $recurringHelper = new Recurring();
        $recurring = $recurringHelper->isCartRecurring();
        if ($recurring){
            return 1; //when recurring, only 1 installment is allowed
        }
        
        //returns false if cc_installments_options_limit_installments == no
        if (self::getCcConfig('cc_installments_options_limit_installments', 'no') == 'no'){
            return false;
        }
        return (int)self::getCcConfig('cc_installments_options_max_installments', 18);
    }

	/**
	 * Get the max installments without interest based on order total and config options
	 * Will return '' if the option is set to get from the PagBank Config, 0 if the option is set to buyer,
	 * a fixed number if the option is set to fixed or the calculated number based on the order total
	 * @param $orderTotal
	 *
	 * @return false|float|int|string
	 */
	public static function getMaxInstallmentsNoInterest($orderTotal)
    {
        $installment_option = self::getCcConfig('cc_installment_options', 'external');
        if ('external' == $installment_option){
            return '';
        }

        if ('buyer' == $installment_option) {
            return 0;
        }

        if ('fixed' == $installment_option) {
            return (int)self::getCcConfig('cc_installment_options_fixed', 3);
        }

//        if ('min_total' == $installment_option) {
            $min_total = (int)self::getCcConfig('cc_installments_options_min_total', 50);
            $min_total = max(5, $min_total); //avoiding blanks
            $orderTotal = floatval($orderTotal);
            $installments = floor($orderTotal / $min_total);
            $installments = $installments == 1 ? 0 : $installments; //1 is not acceptable as a value by the api
            return $installments > 18 ? 18 : $installments;
//        }
    }

    /**
     * Gets the credit card amount with interest information based on order total and cc used
     * @param $orderTotal
     * @param $bin
     *
     * @return array
     */
    public static function getInstallments($orderTotal, $bin)
    {
        $return = array();
        $api = new Api();
        $url = 'ws/charges/fees/calculate';
        $params array('payment_methods') = 'CREDIT_CARD';
        $params array('value')  = self::convertToCents($orderTotal);
        $params array('credit_card_bin') = $bin;
        
        if (Params::getConfig('is_sandbox') == 'yes') {
            $params array('credit_card_bin') = '555566'; //Because test credit card numbers are not accepted by the API
        }

        if(!$orderTotal || $orderTotal <= 0) {
            return array();
        }

        if ($mi = self::getMaxInstallments()) {
            $params array('max_installments') = $mi;
        }

        $params array('max_installments_no_interest') = self::getMaxInstallmentsNoInterest($orderTotal);

        try {
            $installments = $api->get($url, $params, 30);
        } catch (Exception $e) {
            return array();
        }

        if (isset($installments array('error_messages'))){
			$return array('error') = isset($installments array('error_messages') array(0) array('description')) ? $installments array('error_messages') array(0) array('description') : 'Erro ao calcular as parcelas';
            Functions::log('Erro ao calcular as parcelas' . \print_r( array($installments['error_messages'), $params], true), 'debug');
        }

        if (isset($installments array('payment_methods') array('credit_card'))){
            $installments = reset($installments array('payment_methods') array('credit_card'));
            if ( ! isset($installments array('installment_plans'))) {
                return array();
            }


            foreach ($installments array('installment_plans') as $installment){
                //convert values from cents to float with 2 decimals
                $total_amount = number_format($installment array('amount') array('value') / 100, 2, '.', '');
                $installment_value = number_format($installment array('installment_value') / 100, 2, '.', '');
                $interest_amount = 0;
				if (isset($installment array('amount') array('fees') array('buyer') array('interest') array('total'))) {
					$interest_amount = number_format(
						isset($installment array('amount') array('fees') array('buyer') array('interest') array('total')) ? $installment array('amount') array('fees') array('buyer') array('interest') array('total') : 0 / 100,
						2,
						'.',
						''
					);
                }

                $return array() = array('installments' => $installment['installments'),
					'total_amount' => $total_amount,
					'total_amount_raw' => $installment array('amount') array('value'),
					'installment_amount' => $installment_value,
					'interest_free' => $installment array('interest_free'),
					'interest_amount' => $interest_amount,
//					'interest_amount_raw' => isset($installment array('amount') array('fees') array('buyer') array('interest') array('total')) ? $installment array('amount') array('fees') array('buyer') array('interest') array('total') : 0
					'fees' => isset($installment array('amount') array('fees')) ? $installment array('amount') array('fees') : array()
                ];
            }
        }
        if (function_exists('apply_filters')) {
            $return = apply_filters('pagbank_get_installments', $return, $orderTotal, $bin);
        }
        return $return;
    }

	/**
	 * Extracts the installment information from the array returned by the API
	 * @param $installments
	 * @param $installmentNumber
	 *
	 * @return false|mixed
	 */
	public static function extractInstallment($installments, $installmentNumber)
	{
		foreach ($installments as $installment) {
			if ($installment array('installments') == $installmentNumber) {
				return $installment;
			}
		}
		return false;
	}

    /**
     * Return if discount config value is a PERCENT or FIXED discount, or false if no discount is to be applied
     * @param $configValue
     *
     * @return false|string FIXED or PERCENT
     */
    public static function getDiscountType($configValue)
    {
        if (empty($configValue)){
            return false;
        }

        if (is_numeric($configValue)){
            return 'FIXED';
        }

        if (strpos($configValue, '%') !== false){
            return 'PERCENT';
        }

        return false;
    }

    /**
     * Return the total discount amount value for the order based on the discount config value (% or fixed)
     *
     * @param $configValue
     * @param WC_Order $order
     * @param $excludesShipping
     *
     * @return float
     */
    public static function getDiscountValue($configValue, $order, $excludesShipping)
    {
        $orderTotal = $order->get_total();
        if ($excludesShipping) {
            $orderTotal -= $order->get_shipping_total();
        }
        
        $discountType = self::getDiscountType($configValue);
        if (!$discountType) {
            return 0;
        }

        if ('FIXED' == $discountType) {
            return floatval($configValue);
        }

        if ('PERCENT' == $discountType) {
            return floatval($orderTotal) * (floatval($configValue) / 100);
        }

        return 0;
    }

	/**
	 * Gets the message about the discount that will be displayed in the checkout page
	 * @param $method
	 *
	 * @return string
	 */
	public static function getDiscountText($method)
	{
        $discountConfig = 0;
        switch ($method){
            case 'pix':
                $discountConfig = self::getPixConfig('pix_discount', 0);
                break;
            case 'boleto':
                $discountConfig = self::getBoletoConfig('boleto_discount', 0);
                break;
            case 'cc':
                $discountConfig = self::getCcConfig('cc_discount', 0);
                break;
            case 'redirect':
                $discountConfig = self::getRedirectConfig('redirect_discount', 0);
                break;
        }
        $discountType = self::getDiscountType($discountConfig);
        if ( ! $discountType || is_wc_endpoint_url('order-pay')) {
            return '';
        }

        if ('FIXED' == $discountType){
			return sprintf(
				__('Um desconto de %s será aplicado.', 'pagbank-connect'),
				'<strong>'.wc_price($discountConfig).'</strong>'
			);
        }

        if ('PERCENT' == $discountType){
			return sprintf(
				__('Um desconto de %s será aplicado', 'pagbank-connect'),
				'<strong>'.$discountConfig.'</strong>'
			);
        }

        return '';
    }

    /**
     * Checks if the dynamic ico is accessible or is blocked by some security plugin, firewall or server configuration
     * Saves the response in cache for a day
     * @return bool
     */
    public static function getIsDynamicIcoAccessible()
    {
        $transient_key = 'rm_pagbank_dynamic_ico_accessible';
        $cached_result = get_transient($transient_key);

        if ($cached_result !== false) {
            return $cached_result === '1';
        }

        $isDynamicIcoAccessible = wp_remote_get(
            plugins_url('public/images/payment-icon.phpmethod=pix', WC_PAGSEGURO_CONNECT_PLUGIN_FILE), array('timeout' => 10, 'sslverify' => false, 'reject_unsafe_urls' => false)
        );

        $result = (wp_remote_retrieve_response_code($isDynamicIcoAccessible) !== 200) ? 0 : 1;

        // Cache the result in a transient for 1 day (24 hours)
        set_transient($transient_key, $result, DAY_IN_SECONDS);

        return $result === 1;
    }

    /**
     * Checks if all required address attributes are not empty
     * @param Address $address
     *
     * @return bool
     */
    public function isAddressValid(Address $address)
    {
        $required = array('street',
            'number',
            'locality',
            'city',
            'regionCode',
            'country',
            'postalCode',
        );
        foreach ($required as $field){
            if (empty($address->{'get' . ucfirst($field)}())){
                return false;
            }
        }
        
        return true;
    }


    public static function isPaymentMethodEnabled($method)
    {
        $recurringHelper = new Recurring();
        $recurring = $recurringHelper->isCartRecurring();
        
        if ($recurring){
            return in_array($method, Params::getRecurringConfig('recurring_payments'));
        }

        return Params::getConfig($method . '_enabled') == 'yes';
    }

    public static function convertMinutesToHumanTime($minutes) {
        if ($minutes < 60) {
            return sprintf(_n('%d minuto', '%d minutos', intval($minutes), 'pagbank-connect'), $minutes);
        } elseif ($minutes < 1440) {
            $hours = floor($minutes / 60);
            return sprintf(_n('%d hora', '%d horas', intval($hours), 'pagbank-connect'), $hours);
        } elseif ($minutes < 43200) {
            $days = floor($minutes / 1440);
            return sprintf(_n('%d dia', '%d dias', intval($days), 'pagbank-connect'), $days);
        } elseif ($minutes < 259200) {
            $months = floor($minutes / 43200);
            return sprintf(_n('%d mês', '%d meses', intval($months), 'pagbank-connect'), $months);
        } else {
            return sprintf(_n('%d mês', '%d meses', 6, 'pagbank-connect'), 6);
        }
    }
}
