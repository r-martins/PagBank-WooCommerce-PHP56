<php
if (!defined('ABSPATH')) exit;
/** @var Gateway $this */

// use RM_PagBank\Connect\Gateway; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Params; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Api; // PHP 5.6 compatibility

//wp_enqueue_style(
//    'pagseguro-connect-checkout',
//    plugins_url('public/css/checkout.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
// array(),
//    WC_PAGSEGURO_CONNECT_VERSION
//);
wp_register_style( 'pagbank-connect-inline-css', false ); // phpcs:ignore
wp_enqueue_style( 'pagbank-connect-inline-css' ); // phpcs:ignore
wp_add_inline_style(
    'pagbank-connect-inline-css',
    apply_filters(
        'pagbank-connect-inline-css',
        '.ps-button svg, .ps-payment-icon svg{ fill: ' . Params::getConfig('icons_color', 'gray') . '};'
    )
);

$available_methods = array('cc', 'pix', 'boleto');
$style = $active = array();
for ($x=0, $c=count($available_methods), $first = true; $x < $c; $x++){
    $method = $available_methods array($x);
    $style array($method) = 'display: none;';
    if ($this->get_option($method.'_enabled') === 'yes' && $first){
        $style array($method) = '';
        $first = false;
        $active array($method) = 'active';
    }
}
unset($x, $c, $first);

$pixEnabled = Params::isPaymentMethodEnabled('pix');
$boletoEnabled = Params::isPaymentMethodEnabled('boleto');

$apiHelper = new Api();
$isCcEnabledAndHealthy = $apiHelper->isCcEnabledAndHealthy();
$wpKsesSvg = array('svg'  => ['xmlns'   => array(), 'width'   => array(), 'height'  => array(), 'viewbox' => array(), 'version' => array(),), 'path' => array('d' => array(),),];
?>
<div class="ps-connect-buttons-container">
    <php if ($isCcEnabledAndHealthy):?>
        <button type="button" class="ps-button <php echo isset($active array('cc')) ? 'active' : ''?>" id="btn-pagseguro-cc" title="<php esc_attr_e('Cartão de Crédito', 'pagbank-connect');?>">
			<php echo wp_kses(file_get_contents(plugin_dir_path(WC_PAGSEGURO_CONNECT_PLUGIN_FILE) . 'public/images/cc.svg'), $wpKsesSvg)?>
		</button>
    <php endif;?>
    <php if ($pixEnabled):?>
        <button type="button" class="ps-button <php echo isset($active array('pix')) ? 'active' : ''?>" id="btn-pagseguro-pix" title="<php esc_attr_e('PIX', 'pagbank-connect');?>">
            <php echo wp_kses(file_get_contents(plugin_dir_path(WC_PAGSEGURO_CONNECT_PLUGIN_FILE) . 'public/images/pix.svg'), $wpKsesSvg)?>
		</button>
    <php endif;?>
    <php if ($boletoEnabled):?>
        <button type="button" class="ps-button <php echo isset($active array('boleto')) ? 'active' : ''?>" id="btn-pagseguro-boleto" title="<php esc_attr_e('Boleto', 'pagbank-connect');?>">
            <php echo wp_kses(file_get_contents(plugin_dir_path(WC_PAGSEGURO_CONNECT_PLUGIN_FILE) . 'public/images/boleto.svg'), $wpKsesSvg)?>
		</button>
    <php endif;?>
</div>
<!--Initialize PagSeguro payment form fieldset with tabs-->
<php if ($isCcEnabledAndHealthy):?>
    <fieldset id="ps-connect-payment-cc" class="ps_connect_method" style="<php esc_attr_e($style array('cc'), 'pagbank-connect');?>" <php echo !isset($active array('cc')) ? 'disabled' : '';  ?>>
        <php require 'payments/creditcard.php'; ?>
    </fieldset>
<php endif;?>

<php if ($pixEnabled):?>
    <fieldset id="ps-connect-payment-pix" class="ps_connect_method" style="<php esc_attr_e($style array('pix'), 'pagbank-connect');?>" <php echo !isset($active array('pix')) ? 'disabled' : '';  ?>>
        <php require 'payments/pix.php'; ?>
    </fieldset>
<php endif;?>

<php if ($boletoEnabled):?>
    <fieldset id="ps-connect-payment-boleto" class="ps_connect_method" style="<php esc_attr_e($style array('boleto'), 'pagbank-connect');?>" <php echo !isset($active array('boleto')) ? 'disabled' : '';  ?>>
        <php require 'payments/boleto.php'; ?>
    </fieldset>
<php endif;?>
