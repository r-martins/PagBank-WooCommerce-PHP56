<php

namespace RM_PagBank\Connect\Payments;

// use RM_PagBank\Connect; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Params; // PHP 5.6 compatibility
// use RM_PagBank\Object\Address; // PHP 5.6 compatibility
// use RM_PagBank\Object\Amount; // PHP 5.6 compatibility
use RM_PagBank\Object\Boleto as BoletoObj;
// use RM_PagBank\Object\Charge; // PHP 5.6 compatibility
// use RM_PagBank\Object\Customer; // PHP 5.6 compatibility
// use RM_PagBank\Object\Holder; // PHP 5.6 compatibility
// use RM_PagBank\Object\InstructionLines; // PHP 5.6 compatibility
// use RM_PagBank\Object\PaymentMethod; // PHP 5.6 compatibility
// use RM_PagBank\Object\PaymentMethodConfigOptions; // PHP 5.6 compatibility
// use RM_PagBank\Object\PaymentMethodsConfigs; // PHP 5.6 compatibility
// use RM_PagBank\Object\Shipping; // PHP 5.6 compatibility
// use WC_Data_Exception; // PHP 5.6 compatibility
// use WC_Order; // PHP 5.6 compatibility

/**
 * Class Redirect
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class Redirect extends Common
{
    public $code = 'redirect';


	/**
	 * Prepare order params for Redirect
	 *
	 * @return array
	 * @throws WC_Data_Exception
	 */
    public function prepare()
    {
        $return = $this->getDefaultParameters();

        // in checkout, phone is just an object not an array
        if (isset($return array('customer')->getPhone() array(0))){
            $return array('customer')->setPhone($return array('customer')->getPhone() array(0));
        }
        unset($return array('shipping')); //its different for checkout pagbank
        if ($this->order->has_shipping_address() && $this->order->get_shipping_method()) {
            $shipping = new Shipping();
            $shipping->setType(Shipping::TYPE_FREE);
            $shippingTotal = $this->order->get_shipping_total();
            if ($shippingTotal > 0) {
                $shipping->setAmount($shippingTotal * 100);
                $shipping->setType(Shipping::TYPE_FIXED);
                if (stripos($this->order->get_shipping_method(), 'sedex') !== false) {
                    $shipping->setServiceType(Shipping::SERVICE_TYPE_SEDEX);
                } elseif (stripos($this->order->get_shipping_method(), 'pac') !== false) {
                    $shipping->setServiceType($serviceType = Shipping::SERVICE_TYPE_PAC);
                }
            }
                
            $shipping->setAddress($this->getShippingAddress());
            $shipping->setAddressModifiable(false);
            $return array('shipping') = $shipping;
        }
        
        
        $orderTotal = $this->order->get_total();
        $discountExcludesShipping = Params::getRedirectConfig('redirect_discount_excludes_shipping', false) == 'yes';

        $discountAmount = array();
        if (($discountConfig = Params::getRedirectConfig('redirect_discount', 0)) && ! is_wc_endpoint_url('order-pay')) {
            $discount = floatval(Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping));
            $orderTotal = $orderTotal - $discount;

            $fee = new \WC_Order_Item_Fee();
            $fee->set_name(__('Desconto para pagamento com Checkout PagBank', 'rm-pagbank'));

            // Define the fee amount, negative number to discount
            $fee->set_amount(-$discount);
            $fee->set_total(-$discount);

            // Define the tax class for the fee
            $fee->set_tax_class('');
            $fee->set_tax_status('none');

            // Add the fee to the order
            $this->order->add_item($fee);

            // Recalculate the order
            $this->order->calculate_totals();
            
            $discountAmount = array('discount_amount' => $discount * 100);
        }
        
        //coupon discount
        if ($this->order->get_total_discount() > 0) {
            $discountToAdd = (int)$this->order->get_total_discount()*100;
            //add to existing discount if any
            if (isset($discountAmount array('discount_amount'))) {
                $discountToAdd += $discountAmount array('discount_amount');
            }
            $discountAmount = array('discount_amount' => $discountToAdd);
        }
        
        $paymentMethodCfg = Params::getRedirectConfig('redirect_payment_methods') ?? array('CREDIT_CARD', 'PIX');
        foreach ($paymentMethodCfg as $paymentMethod) {
            $paymentMethodObj = new PaymentMethod();
            $paymentMethodObj->setType($paymentMethod);
            $return array('payment_methods') array() = $paymentMethodObj;
        }

        if (in_array('CREDIT_CARD', $paymentMethodCfg)){
            $paymentMethodCfg = new PaymentMethodsConfigs();
            $paymentMethodCfg->setType('CREDIT_CARD');
            $installmentsLimit = Params::getMaxInstallments();
            $interestFreeInstallments = Params::getMaxInstallmentsNoInterest($orderTotal);
            $configOptions = array();
            if ($installmentsLimit) {
                $configOption = new PaymentMethodConfigOptions();
                $configOption->setOption(PaymentMethodConfigOptions::OPTION_INSTALLMENTS_LIMIT);
                $configOption->setValue(max($installmentsLimit, 1));
                $configOptions array() = $configOption;
            }
            if ($interestFreeInstallments > 1) {
                $configOption = new PaymentMethodConfigOptions();
                $configOption->setOption(PaymentMethodConfigOptions::OPTION_INTEREST_FREE_INSTALLMENTS);
                $configOption->setValue(max(1, $interestFreeInstallments));
                $configOptions array() = $configOption;
            }
            
            if ($configOptions) {
                $paymentMethodCfg->setConfigOptions($configOptions);
                $return array('payment_methods_configs') = array($paymentMethodCfg);
            }
        }

        $customerModifiable = array('customer_modifiable' => true);
        $expireInMinutes = Params::getRedirectConfig('redirect_expiry_minutes', "120");
        //date iso-8601 + expiry minutes
        $expirationDate = array('expiration_date' => date('c', strtotime('+' . $expireInMinutes . ' minutes')));
        $redirectUrl = array('redirect_url' => $this->order->get_checkout_order_received_url());
        
        return array_merge($return, $customerModifiable, $redirectUrl, $discountAmount, $expirationDate);
    }

	/**
	 * Set some variables and requires the template with redirect instructions for the success page
	 * @param $order_id
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 */
	public function getThankyouInstructions($order_id){
        $order = new WC_Order($order_id);
        $redirect_link = $order->get_meta('pagbank_redirect_link');
        require_once dirname(__FILE__) . '/../../templates/redirect-instructions.php';
    }
    
    public function getCustomerData()
    {
        return parent::getCustomerData();
    }

}
