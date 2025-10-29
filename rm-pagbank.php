<?php
/**
 * PagSeguro Connect - Ricardo Martins (com descontos)
 *
 * @package           PagSeguroConnect
 * @author            Ricardo Martins
 * @copyright         2024 Ricardo Martins
 * @license           GPL-3.0
 *
 * @wordpress-plugin
 * Plugin Name:       PagBank Connect PHP 5.6
 * Plugin URI:        https://pbintegracoes.com
 * Description:       Integra seu WooCommerce com as APIs PagSeguro v4 através da aplicação de Ricardo Martins (com descontos nas taxas oficiais), com suporte a PIX transparente e muito mais.
 * Version:           4.45.2
 * Requires at least: 4.9
 * Tested up to:      6.2
 * Requires PHP:      5.6
 * Author:            PagBank Integrações (Ricardo Martins)
 * Author URI:        https://pbintegracoes.com
 * License:           GPL-3.0
 * License URI:       https://opensource.org/license/gpl-3/
 * Text Domain:       pagbank-connect
 * Domain Path:       /languages
 */

/** @noinspection PhpDefineCanBeReplacedWithConstInspection */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\EnvioFacil;

// Prevent direct file access.
defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

// Plugin constants.
define( 'WC_PAGSEGURO_CONNECT_VERSION', '4.45.2-php56' );
define( 'WC_PAGSEGURO_CONNECT_PLUGIN_FILE', __FILE__ );
define( 'WC_PAGSEGURO_CONNECT_BASE_DIR', __DIR__ );
define( 'WC_PAGSEGURO_CONNECT_TEMPLATES_DIR', WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/' );
define( 'WC_PAGSEGURO_CONNECT_URL', plugins_url( __FILE__ ) );

spl_autoload_register(
    function ($class) {
        $prefix = 'RM_PagBank\\';
        $prefixLength = strlen($prefix);

        if (strncmp($prefix,$class,$prefixLength) !== 0) {
            return;
        }

        $relativeClass = substr($class,$prefixLength);
        $relativePath = str_replace('\\', '/',$relativeClass) . '.php';
        $file = __DIR__ . '/src/' . $relativePath;

        if (file_exists($file)) {
            require_once $file;
        }
    }
);

add_action('init', array(Connect::class, 'init'));
add_action('init', array(Connect\Recurring::class, 'addManageSubscriptionEndpoints'));
add_action('after_setup_theme', array(Connect::class, 'loadTextDomain'));

// Add Gateway
add_filter('woocommerce_payment_gateways', array(Connect::class, 'addGateway'));
//add_action('woocommerce_blocks_payment_method_type_registration', array(Connect::class, 'registerPaymentMethodOnCheckoutBlocks'));
add_action('woocommerce_blocks_loaded', array(Connect::class, 'gatewayBlockSupport'));

//Add Recurring Config
add_filter('woocommerce_get_settings_checkout' , [Connect\Recurring::class, 'recurringSettingsFields'] , 10, 2 );
add_filter('woocommerce_settings_checkout' , [Connect\Recurring::class, 'recurringHeaderSettingsSection'] , 10, 2 );

//envio facil
add_filter('woocommerce_shipping_methods', [EnvioFacil::class, 'addMethod']);

//recurring and styles
add_filter('woocommerce_enqueue_styles', [Gateway::class, 'addStyles'], 99999, 1);
add_filter('woocommerce_enqueue_styles', [Gateway::class, 'addStylesWoo'], 99999, 1);

//not needed so far...
register_activation_hook(__FILE__, [Connect::class, 'activate']);
register_deactivation_hook(__FILE__, [Connect::class, 'deactivate']);
register_uninstall_hook(__FILE__, [Connect::class, 'uninstall']);

// Upgrading scripts
add_action('plugins_loaded', [Connect::class, 'upgrade']);

add_action(
    'init',
    function () {
        if (function_exists('wp_robots_no_robots')) {
            if (has_action('wp_head', 'wp_no_robots')) {
                remove_action('wp_head', 'wp_no_robots');
            }

            if ( ! has_filter('wp_robots', 'wp_robots_no_robots') ) {
                add_filter('wp_robots', 'wp_robots_no_robots');
            }
        }
    }
);