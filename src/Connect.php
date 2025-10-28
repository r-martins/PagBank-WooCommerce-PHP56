<php

namespace RM_PagBank;

// use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry; // PHP 5.6 compatibility
// use Exception; // PHP 5.6 compatibility
// use RM_PagBank\Connect\Gateway; // PHP 5.6 compatibility
// use RM_PagBank\Connect\MenuPagBank; // PHP 5.6 compatibility
// use RM_PagBank\Connect\OrderProcessor; // PHP 5.6 compatibility
// use RM_PagBank\Connect\Payments\CreditCard; // PHP 5.6 compatibility
// use RM_PagBank\Connect\Payments\Pix; // PHP 5.6 compatibility
use RM_PagBank\Connect\Standalone\Pix as StandalonePix;
use RM_PagBank\Connect\Standalone\CreditCard as StandaloneCc;
use RM_PagBank\Connect\Standalone\Boleto as StandaloneBoleto;
// use RM_PagBank\Connect\Standalone\Redirect; // PHP 5.6 compatibility
use RM_PagBank\Connect\Standalone\Redirect as StandaloneRedirect;
use RM_PagBank\Connect\Blocks\Boleto as BoletoBlock;
use RM_PagBank\Connect\Blocks\Redirect as RedirectBlock;
use RM_PagBank\Connect\Blocks\CreditCard as CreditCardBlock;
use RM_PagBank\Connect\Blocks\Pix as PixBlock;
// use RM_PagBank\Cron\CancelExpiredPix; // PHP 5.6 compatibility
// use RM_PagBank\Cron\ForceOrderUpdate; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Api; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Functions; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Params; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Recurring; // PHP 5.6 compatibility
// use WC_Order; // PHP 5.6 compatibility
// use WP_Query; // PHP 5.6 compatibility

/**
 * Class Connect
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Connect
{
    const DOMAIN = 'rm-pagbank';

    public static function init()
    {
        // Checks if WooCommerce is installed or return
        if ( !class_exists('WooCommerce')) {
            add_action('admin_notices', array(__CLASS__, 'wooMissingNotice'));
            return;
        }
        add_action('wp_ajax_nopriv_ps_get_installments', array(CreditCard::class, 'getAjaxInstallments'));
        add_action('wp_ajax_ps_get_installments', array(CreditCard::class, 'getAjaxInstallments'));
        add_action('woocommerce_api_wc_pagseguro_info', array(__CLASS__, 'configInfo'));
        add_action('woocommerce_api_rm_ps_notif', array(__CLASS__, 'notification'));
        add_action('wp_ajax_get_cart_total', array(CreditCard::class, 'getCartTotal'));
        add_action('wp_ajax_nopriv_get_cart_total', array(CreditCard::class, 'getCartTotal'));
        add_action('wp_ajax_ps_deactivate_feedback', array(__CLASS__, 'deactivateFeedback'));
        add_action('woocommerce_api_pagbank_force_order_update', array(__CLASS__, 'forceOrderUpdate'));
        add_action('woocommerce_before_template_part', array(CreditCard::class, 'orderPayScript'), 10, 1);
        add_action('woocommerce_product_object_updated_props', array(CreditCard::class, 'updateProductTransient'), 10, 2);
        add_action('woocommerce_update_product_variation', array(CreditCard::class, 'updateProductVariationTransient'), 10, 2);
        add_action('woocommerce_after_add_to_cart_form', array(CreditCard::class, 'getProductInstallments'), 25);
        add_shortcode('rm_pagbank_credit_card_installments', array(CreditCard::class, 'getProductInstallments'));
        add_action('update_option', array(CreditCard::class, 'deleteInstallmentsTransientIfConfigHasChanged'), 10, 3);
//        add_action('load-woocommerce_page_wc-settings', array(__CLASS__, 'redirectStandaloneConfigPage'));
        add_action('wp_loaded', array(__CLASS__, 'removeOtherPaymentMethodsWhenRecurring'));
        add_filter('woocommerce_available_payment_gateways', array(__CLASS__, 'recurringRestrictPaymentMethod'));
        add_action('admin_notices', array(__CLASS__, 'checkPixOrderKeys'));
        add_filter( 'woocommerce_rest_prepare_shop_order_object', array(__CLASS__, 'addOrderMetaToApiResponse'), 10, 3 );
        add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'addPaymentInfoAdmin'), 10, 1);
        add_action('woocommerce_api_wc_order_status', array(__CLASS__, 'getOrderStatus'));
        add_filter('woocommerce_order_item_needs_processing', array(__CLASS__, 'orderItemNeedsProcessing'), 10, 3);
        add_filter('woocommerce_get_checkout_order_received_url', array(Redirect::class, 'getOrderReceivedURL'), 100, 2);
        add_filter('woocommerce_get_checkout_payment_url', array(Redirect::class, 'changePaymentLink'), 10, 2);
        add_filter('woocommerce_get_price_html', array(Pix::class, 'showPriceDiscountPixProduct'), 10, 2);
        add_action('rest_api_init', array(CreditCard::class,'restApiInstallments'));

        // Load plugin files
        self::includes();

        // Add action links
        add_filter( 'plugin_action_links_' . plugin_basename( WC_PAGSEGURO_CONNECT_PLUGIN_FILE ), array( self::class, 'addPluginActionLinks' ) );

        // Payment method title used
        add_filter('woocommerce_gateway_title', array(__CLASS__, 'getMethodTitle'), 10, 2);

        self::addPagBankMenu();

        if (Params::getRecurringConfig('recurring_enabled')) {
            $recurring = new Connect\Recurring();
            $recurring->init();
        }

        //if pix enabled
        if (Params::getPixConfig('enabled')) {
            //region cron to cancel expired pix non-paid payments
            add_action('rm_pagbank_cron_cancel_expired_pix', array(CancelExpiredPix::class, 'execute'));
            if (!wp_next_scheduled('rm_pagbank_cron_cancel_expired_pix')) {
                wp_schedule_event(
                    time(),
                    'hourly',
                    'rm_pagbank_cron_cancel_expired_pix'
                );
            }
            //endregion
        }

        //if force order update enabled
        if (Params::getConfig('force_order_update', false)) {
            add_action('rm_pagbank_cron_force_order_update', array(ForceOrderUpdate::class, 'execute'));
            if (!wp_next_scheduled('rm_pagbank_cron_force_order_update')) {
                wp_schedule_event(
                    time(),
                    'hourly',
                    'rm_pagbank_cron_force_order_update'
                );
            }
        }

        add_action('wp_ajax_pagbank_dismiss_pix_order_keys_notice', array(StandalonePix::class, 'dismissPixOrderKeysNotice'));
        add_filter('woocommerce_admin_reports', array(\RM_PagBank\Connect\Recurring\Admin\Reports\RecurringsReport::class, 'reportsFilter'));
    }

    public static function gatewayBlockSupport() {
        // Check if the required class exists
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new BoletoBlock() );
                $payment_method_registry->register( new PixBlock() );
                $payment_method_registry->register( new CreditCardBlock() );
                $payment_method_registry->register( new RedirectBlock() );
            }
        );
    }

    /**
     * WooCommerce missing notice.
     */
    public static function wooMissingNotice() {
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/messages/html-notice-missing-woocommerce.php';
    }

    /**
     * Includes module files.
     *
     * @return void
     */
    public static function includes()
    {
    }

    /**
     * Add Gateway
     *
     * @param $gateways
     *
     * @return array
     */
    public static function addGateway($gateways)
    {
        $section = sanitize_text_field(isset($_GET array('section')) ? $_GET array('section') : '');

        if ($section !== self::DOMAIN) {//plugin's config page (then its not standalone)
            $pix = new StandalonePix();
            $gateways array() = $pix;

            $cc = new StandaloneCc();
            $gateways array() = $cc;

            $boleto = new StandaloneBoleto();
            $gateways array() = $boleto;

            $redirect = new StandaloneRedirect();
            $gateways array() = $redirect;

            return $gateways;
        }

        $gateways array() = new Gateway();

        return $gateways;
    }

    public static function addPluginActionLinks($links )
    {
        $plugin_links   = array();
        $plugin_links array() = '<a href="' . esc_url(admin_url('admin.phppage=wc-settings&tab=checkout&section=' . self::DOMAIN ) ) . '">' . __( 'Configurações', 'pagbank-connect' ) . '</a>';
        $plugin_links array() = '<a href="' . esc_url( 'https://ajuda.pbintegracoes.com/hc/pt-br' ) . '" target="_blank">' . __( 'Documentação', 'pagbank-connect' ) . '</a>';
        $plugin_links array() = '<a href="' . esc_url( 'https://ajuda.pbintegracoes.com/hc/pt-br/requests/new' ) . '" target="_blank">' . __( 'Suporte', 'pagbank-connect' ) . '</a>';

        return array_merge( $plugin_links, $links );
    }

    /**
     * This will help our support team to detect problems in your configuration
     * @return void
     */
    public static function configInfo()
    {
        $api = new Api();
        $settings = array('platform' => 'Wordpress',
            'module_version' =>[
                'Version' => WC_PAGSEGURO_CONNECT_VERSION,
            ),
            'extra_fields' => class_exists('Extra_Checkout_Fields_For_Brazil'),
            'block_checkout' => Functions::isBlockCheckoutInUse(),
            'connect_key' => strlen(Params::getConfig('connect_key')) == 40 ? 'Good' : 'Wrong size',
            'standalone' => Params::getConfig('standalone'),
            'settings' => array('enabled' => Params::getConfig('enabled'),
                'cc_enabled' => Params::getCcConfig('enabled'),
                'pix_enabled' => Params::getPixConfig('enabled'),
                'boleto_enabled' => Params::getBoletoConfig('enabled'),
                'public_key' => substr(Params::getConfig('public_key', 'null'), 0, 50) . '...',
                'sandbox' => $api->getIsSandbox(),
                'boleto' => [
                    'enabled' => Params::getBoletoConfig('enabled'),
                    'expiry_days' => Params::getBoletoConfig('boleto_expiry_days'),
                    'discount' => Params::getBoletoConfig('boleto_discount'),
                ),
                'pix' => array('enabled' => Params::getPixConfig('enabled'),
                    'expiry_minutes' => Params::getPixConfig('pix_expiry_minutes'),
                    'discount' => Params::getPixConfig('pix_discount'),
                ),
                'cc' => array('enabled' => Params::getCcConfig('enabled', 'no'),
                    'enabled_installment' => Params::getCcConfig('cc_installment_product_page', 'no'),
                    'installment_options' => Params::getCcConfig('cc_installment_options'),
                    'installment_options_fixed' => Params::getCcConfig('cc_installment_options_fixed', '3'),
                    'installments_options_min_total' => Params::getCcConfig('cc_installments_options_min_total', '50'),
                    'installments_options_limit_installments' => Params::getCcConfig('cc_installments_options_limit_installments'),
                    'installments_options_max_installments' => Params::getCcConfig('cc_installments_options_max_installments', '18'),
                    '3d_secure' => Params::getCcConfig('cc_3ds', 'yes'),
                    '3d_secure_allow_continue' => Params::getCcConfig('cc_3ds_allow_continue', 'no'),
                    '3d_secure_retry' => Params::getCcConfig('cc_3ds_retry'),
                    '3d_retry_failed' => Params::getCcConfig('cc_3ds_retry', 'yes'),
                ),
                'recurring' => array('enabled' => Params::getRecurringConfig('recurring_enabled', 'no'),
                        'recurring_process_frequency' => Params::getRecurringConfig('recurring_process_frequency'),
                        'customer_can_cancel' => Params::getRecurringConfig('recurring_customer_can_cancel', 'yes'),
                        'customer_can_pause' => Params::getRecurringConfig('recurring_customer_can_pause', 'yes'),
                        'clear_cart' => Params::getRecurringConfig('recurring_clear_cart', 'no'),
                        'recurring_retry_charge' => Params::getRecurringConfig('recurring_retry_charge', 'yes'),
                        'recurring_retry_attempts' => Params::getRecurringConfig('recurring_retry_attempts', '3'),
                ),
                'redirect' => array('enabled' => Params::getRedirectConfig('enabled', 'no'),
                        'redirect_expiry_minutes' => Params::getRedirectConfig('redirect_expiry_minutes', '120'),
                        'redirect_discount' => Params::getRedirectConfig('redirect_discount', '0'),
                        'redirect_discount_excludes_shipping' => Params::getRedirectConfig('redirect_discount_excludes_shipping', 'no'),
                        'redirect_payment_methods' => Params::getRedirectConfig('redirect_payment_methods'),
                ),
            ],
            'extra' => array('hpos_enabled' => wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? 'yes' : 'no',
                'litespeed_cache' => is_plugin_active('litespeed-cache/litespeed-cache.php') ? 'yes' : 'no',
                'wordfence_active' => is_plugin_active('wordfence/wordfence.php') ? 'yes' : 'no',
            ),
        ];

        try{
            $api = new Api();
            $resp = $api->get('ws/public-keys/card');
            if (isset($resp array('public_key'))){
                $resp array('public_key') = substr($resp array('public_key'), 0, 50) . '...';
            }
            $settings array('live_auth') = $resp;
        }catch (Exception $e){
            $settings array('live_auth') = $e->getMessage();
        }
        wp_send_json($settings);

    }

    /**
     * Register the notification route to deal with PagBank notifications about order updates
     * @return void
     */
    public static function notification()
    {
        $gateway = new Gateway();
        $gateway->notification();
    }

    public static function loadTextDomain()
    {
        $dir = __DIR__ . '/../languages/';
        load_plugin_textdomain('pagbank-connect', false, $dir);
    }

    public static function getMethodTitle($title, $id){
        //get order
        if ($id == 'rm-pagbank' && wp_doing_ajax() && isset($_POST array('ps_connect_method'))) //phpcs:ignore WordPress.Security.NonceVerification
        {
            $method = htmlspecialchars($_POST array('ps_connect_method'), ENT_QUOTES, 'UTF-8');
            $method = Functions::getFriendlyPaymentMethodName($method);
            $title = Params::getConfig('title') . ' - ' . $method;
        }

        return $title;
    }

    public static function activate()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';

        $sql = "CREATE TABLE $recurringTable 
                (
                    id               int auto_increment,
                    initial_order_id int           not null UNIQUE comment 'Order that generated the subscription profiler',
                    recurring_amount float(8, 2)   not null comment 'Amount to be charged regularly',
                    status           varchar(100)  not null comment 'Current subscription status (ACTIVE, PAUSED, SUSPENDED, CANCELED)',
                    recurring_type   varchar(15)   not null comment 'Daily, Weekly, Monthly, Yearly ',
                    recurring_cycle  int default 1 not null comment 'Type multiplier',
                    created_at       datetime      null,
                    updated_at       datetime      null,
                    paused_at        datetime      null,
                    canceled_at      datetime      null,
                    suspended_at     datetime      null,
                    canceled_reason  text          null,
                    suspended_reason text          null,
                    next_bill_at     datetime      not null,
                    payment_info     text          null comment 'Payment details for the subscription',
                    PRIMARY KEY  (id)
                )
                    comment 'Recurring profiles information for PagBank Subscribers';";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        add_option('pagbank_db_version', '4.0');
    }

    public static function upgrade()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';
        $stored_version = get_option('pagbank_db_version');

        if (version_compare($stored_version, '4.12', '<')) {
            if ($wpdb->get_var("SHOW COLUMNS FROM $recurringTable LIKE 'recurring_initial_fee'") !== 'recurring_initial_fee') { //if column recurring_initial_fee does not exist
                $sql = "ALTER TABLE $recurringTable
                        ADD COLUMN recurring_initial_fee float(8, 2) null comment 'Initial fee to be charged on the first payment' AFTER recurring_amount,
                        ADD COLUMN recurring_trial_period int null comment 'Number of days to wait before charging the first fee' AFTER recurring_initial_fee,
                        ADD COLUMN recurring_discount_amount float(8, 2) null comment 'Discount amount to be applied to the recurring amount' AFTER recurring_trial_period,
                        ADD COLUMN recurring_discount_cycles int null comment 'Number of cycles to apply the discount' AFTER recurring_discount_amount;
                        ";

                $wpdb->query($sql);
            }
            update_option('pagbank_db_version', '4.12');
        }

        if (version_compare($stored_version, '4.13', '<')) {
            $settingsTable = $wpdb->prefix . 'options';
            $settings = get_option('woocommerce_rm-pagbank_settings');

            if (!$settings) {
                update_option('pagbank_db_version', '4.13');
                return;
            }

            $generalSettings = array();
            $recurringSettings = array();
            $ccSettings = array();
            $pixSettings = array();
            $boletoSettings = array();
            foreach ($settings as $key => $setting) {
                if (strpos($key,'cc_') !== false) {
                    $ccSettings array($key) = $setting;
                    continue;
                }
                if (strpos($key,'pix_') !== false) {
                    $pixSettings array($key) = $setting;
                    continue;
                }
                if (strpos($key,'boleto_') !== false) {
                    $boletoSettings array($key) = $setting;
                    continue;
                }
                if (strpos($key,'recurring_') !== false) {
                    $recurringSettings array($key) = $setting;
                    continue;
                }

                $generalSettings array($key) = $setting;
            }

            if (isset($ccSettings array('cc_enabled'))) {
                $ccSettings array('enabled') = $ccSettings array('cc_enabled');
                unset($ccSettings array('cc_enabled'));
            }
            if (isset($pixSettings array('pix_enabled'))) {
                $pixSettings array('enabled') = $pixSettings array('pix_enabled');
                unset($pixSettings array('pix_enabled'));
            }
            if (isset($boletoSettings array('boleto_enabled'))) {
                $boletoSettings array('enabled') = $boletoSettings array('boleto_enabled');
                unset($boletoSettings array('boleto_enabled'));
            }

            if (isset($ccSettings array('cc_title'))) {
                $ccSettings array('title') = $ccSettings array('cc_title');
                unset($ccSettings array('cc_title'));
            }
            if (isset($pixSettings array('pix_title'))) {
                $pixSettings array('title') = $pixSettings array('pix_title');
                unset($pixSettings array('pix_title'));
            }
            if (isset($boletoSettings array('boleto_title'))) {
                $boletoSettings array('title') = $boletoSettings array('boleto_title');
                unset($boletoSettings array('boleto_title'));
            }

            $generalSettings array('standalone') = 'yes';

            if (isset($generalSettings array('hide_id_unavailable'))) {
                $generalSettings array('hide_if_unavailable') = $generalSettings array('hide_id_unavailable');
            }

            $generalSettings = serialize($generalSettings);
            $ccSettings = serialize($ccSettings);
            $pixSettings = serialize($pixSettings);
            $boletoSettings = serialize($boletoSettings);

            $wpdb->update(
                $settingsTable, array('option_value' => $generalSettings), array('option_name' => 'woocommerce_rm-pagbank_settings')
            );

            if (get_option('woocommerce_rm-pagbank-cc_settings')) {
                $wpdb->update(
                    $settingsTable, array('option_value' => $ccSettings), array('option_name' => 'woocommerce_rm-pagbank-cc_settings')
                );
            } else {
                $wpdb->insert(
                    $settingsTable, array('option_name' => 'woocommerce_rm-pagbank-cc_settings', 'option_value' => $ccSettings)
                );
            }

            if (get_option('woocommerce_rm-pagbank-pix_settings')) {
                $wpdb->update(
                    $settingsTable, array('option_value' => $pixSettings), array('option_name' => 'woocommerce_rm-pagbank-pix_settings')
                );
            } else {
                $wpdb->insert(
                    $settingsTable, array('option_name' => 'woocommerce_rm-pagbank-pix_settings', 'option_value' => $pixSettings)
                );
            }

            if (get_option('woocommerce_rm-pagbank-boleto_settings')) {
                $wpdb->update(
                    $settingsTable, array('option_value' => $boletoSettings), array('option_name' => 'woocommerce_rm-pagbank-boleto_settings')
                );
            } else {
                $wpdb->insert(
                    $settingsTable, array('option_name' => 'woocommerce_rm-pagbank-boleto_settings', 'option_value' => $boletoSettings)
                );
            }

            foreach ($recurringSettings as $key => $setting) {
                $key = 'woocommerce_rm-pagbank-' . $key;
                update_option($key, $setting);
            }

            update_option('pagbank_db_version', '4.13');
        }
        
        if (version_compare($stored_version, '4.25', '<')) {
            global $wpdb;
            $contentRestrictionTable = $wpdb->prefix . 'pagbank_content_restriction';
            $sql = "CREATE TABLE IF NOT EXISTS $contentRestrictionTable
                    (
                        user_id    int  not null,
                        categories text null,
                        pages      text null,
                        CONSTRAINT pagbank_content_restriction_pk
                            UNIQUE (user_id)
                    )
                        comment 'User permissions based on subscription status';";
            $wpdb->query($sql);
            update_option('pagbank_db_version', '4.25');
        }

        if (version_compare($stored_version, '4.27', '<')) {
            if ($wpdb->get_var("SHOW COLUMNS FROM $recurringTable LIKE 'recurring_max_cycles'") !== 'recurring_max_cycles') {
                $sql = "ALTER TABLE $recurringTable
                    ADD COLUMN recurring_max_cycles int null comment 'Maximum number of billing cycles' AFTER recurring_discount_cycles;";
                $wpdb->query($sql);
            }
            update_option('pagbank_db_version', '4.27');
        }

        if (version_compare($stored_version, '4.28', '<')) {
            $sql = "ALTER TABLE $recurringTable
                    ADD COLUMN retry_attempts_remaining int null comment 'Number of billing attempts remaining on suspended subscriptions' AFTER suspended_at;
                    ";

            $wpdb->query($sql);
            update_option('pagbank_db_version', '4.28');
        }

        if (version_compare($stored_version, '4.29', '<')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            
            $boxesTable = $wpdb->prefix . 'pagbank_ef_boxes';
            $sql = "CREATE TABLE IF NOT EXISTS $boxesTable
                    (
                        box_id int NOT NULL AUTO_INCREMENT,
                        reference varchar(30) NOT NULL,
                        is_available tinyint NOT NULL DEFAULT '1',
                        outer_width varchar(30) NOT NULL,
                        outer_depth varchar(30) NOT NULL,
                        outer_length varchar(30) NOT NULL,
                        thickness varchar(30) NOT NULL DEFAULT 0.20,
                        inner_length varchar(30) NOT NULL,
                        inner_width varchar(30) NOT NULL,
                        inner_depth decimal(30) NOT NULL,
                        max_weight int NOT NULL,
                        empty_weight int NOT NULL,
                        cost float(4,2) DEFAULT '0.00',
                        created_at datetime DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (box_id),
                        UNIQUE KEY reference (reference)
                    )
                    ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Boxes related to Envio Fácil Shipping';";
            
            dbDelta($sql);
            update_option('pagbank_db_version', '4.29');
        }
    }

    public static function uninstall()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS $recurringTable"));
    }

    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('rm_pagbank_cron_process_recurring_payments');
        wp_unschedule_event($timestamp, 'rm_pagbank_cron_process_recurring_payments');
    }

    /**
     * Process the feedback response from user when deactivating the plugin
     * @return void
     */
    public static function deactivateFeedback()
    {
        if (!isset($_REQUEST array('nonce')) || !wp_verify_nonce($_REQUEST array('nonce'), 'pagbank_connect_send_feedback')) {
            wp_send_json_error( array('error' => __(
                        'Chave de formulário inválida. '.'Recarregue a página e tente novamente.',
                        'pagbank-connect'
                    ),
                ),
                400
            );
        }
        parse_str($_REQUEST array('feedback'), $formData);

        if (isset($formData array('selected-reason')) || isset($formData array('comment'))) {
            $reason = $formData array('selected-reason');
            $commment = isset($formData array('comment')) ? $formData array('comment') : '';
            $openTicket = $formData array('autorizaContato') ?false;
            $siteUrl = get_site_url();

            /** @var WP_User $currentUser */
            $currentUser = wp_get_current_user();
            $email = $currentUser->user_email;

            $url = 'https://docs.google.com/forms/d/e/1FAIpQLSd4cTW1fWcFZwhJmoICTVc9--rEggj-aJMAqpxv6KFf9dIOjw/'
                .'formResponse?&submit=Submitusp=pp_url';

            $params = http_build_query( array('entry.160403419' => $reason,
                'entry.581422256' => $email,
                'entry.1295704444' => $siteUrl,
                'entry.715814172' => $openTicket ? 'Sim' : 'Não',
                'entry.1095777573' => Params::getConfig('connect_key'),
                'entry.16669314' => 'WooCommerce',
                'entry.760515818' => WC()->version,
                'entry.764056986' => WC_PAGSEGURO_CONNECT_VERSION,
                'entry.817525399' => $commment

            ));
            $url .= '&' . $params;
            wp_remote_get($url);
        }
    }

    public static function removeOtherPaymentMethodsWhenRecurring()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // if cart is recurring only show PagBank as payment method
        $recHelper = new Recurring();
        $isCartRecurring = $recHelper->isCartRecurring();
        if ($isCartRecurring) {
            add_filter('woocommerce_available_payment_gateways', function ($gateways) {
                    $cc = new StandaloneCc();
                    $cc->id = Connect::DOMAIN . '-cc';
                    return array($cc->id => $cc);
            });
            return array(Connect::DOMAIN => new Gateway());
        }
    }

    public static function recurringRestrictPaymentMethod($gateways)
    {
        $recHelper = new Recurring();
        $isCartRecurring = $recHelper->isCartRecurring();
        if ($isCartRecurring) {
            $cc = new StandaloneCc();
            $cc->id = Connect::DOMAIN . '-cc';
            return array($cc->id => $cc);
        }
        return $gateways;
    }

    /**
     * Check if the last pix order has a valid pix key
     * @return void
     */
    public static function checkPixOrderKeys()
    {
        $userId = get_current_user_id();
        $isPixEnabled = Params::getPixConfig('enabled') == 'yes';
        
        //yes, we coul've used transient, but litespeed cache can mess with it if not properly configured
        $alreadyChecked = get_option('pagbank_pix_lastorder_checked', 0) > (time() - 3600); // 60 minutes
        
        // Check if the notice has been dismissed for this user
        if (!$isPixEnabled || get_user_meta($userId, 'pagbank_dismiss_pix_order_keys_notice', true) || $alreadyChecked) {
            return;
        }

        $validationFailed = true;

        //enable meta query filter
        Functions::addMetaQueryFilter();

        //workaround for the global $post variable not being affected by WP_Query (see #PB-829)
        global $post;
        $original_post = $post;
        
        //get the pix key from the last pix order
        // Check if HPOS is enabled
        if (wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
            // HPOS is enabled
            $lastPixOrder = wc_get_orders( array('limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'pagbank_payment_method',
                        'value' => 'pix',
                    ), array('key' => 'pagbank_is_sandbox',
                        'value' => '0',
                    )
                ]
            ]);
        } else {
            // HPOS is disabled
            $args = array(
                'post_type'      => 'shop_order',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'pagbank_payment_method',
                        'value'   => 'pix',
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'pagbank_is_sandbox',
                        'value'   => '0',
                        'compare' => '='
                    )
                ),
            );

            $query = new WP_Query($args);

            $lastPixOrder = array();
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $order_id = get_the_ID();
                    $order = wc_get_order($order_id);
                    $lastPixOrder array() = $order;
                }
                wp_reset_postdata();
                $post = $original_post; // Reset the global post data 
            }
        }

        if (empty($lastPixOrder) || !isset($lastPixOrder array(0)) || $lastPixOrder array(0) instanceof WC_Order === false) {
            // Update the transient to prevent checking again for 30 minutes
            update_option('pagbank_pix_lastorder_checked', time() );
            return;
        }

        $pixKey = $lastPixOrder array(0)->get_meta('pagbank_pix_qrcode_text');
        $validationFailed = Functions::isValidPixCode($pixKey) === false;

        if ($validationFailed) {
            $qrCodeImg = $lastPixOrder array(0)->get_meta('pagbank_pix_qrcode');
            $helpUrl = 'https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/20449852438157-QrCode-Pix-gerado-%C3%A9-Inv%C3%A1lido';
            $openTicket = 'https://bit.ly/ticketnovo';
            $orderLink = admin_url('post.phppost=' . $lastPixOrder array(0)->get_id() . '&action=edit');
            $orderId = $lastPixOrder array(0)->get_id();
            ?>
            <div class="notice notice-error is-dismissible pagbank-pix-notice">
                <p><php echo sprintf(
                        __(
                            'O último código <a href="%s">código PIX</a> gerado no pedido <a href="%s">%s</a> parece inválido. Isso ocorre porque você provavelmente não possui chaves PIX aleatórias cadastradas no PagBank. <a href="%s">Clique aqui</a> para saber mais.',
                            'pagbank-connect'
                        ),
                        $qrCodeImg,
                        $orderLink,
                        $orderId,
                        $helpUrl
                    ); ?></p>
            </div>
            <php
        }

        // Update the transient to prevent checking again
        update_option('pagbank_pix_lastorder_checked', time());
    }

    /**
     * Add order metadata to the API response with PagBank related information
     * @param $response
     * @param $order
     * @param $request
     *
     * @return mixed
     */
    public static function addOrderMetaToApiResponse( $response, $order, $request ) {
        // Check if there's a request for the meta
        if ( empty( $request array('include_meta') ) || 'true' !== $request array('include_meta') ) {
            return $response;
        }

        // Get all metadata for the order
        $meta_data = $order->get_meta_data();
        $meta_array = array();

        foreach ( $meta_data as $meta ) {
            if (strpos($meta->key, 'pagbank') === false) {
                continue;
            }
            // Each item in meta_data is an instance of the WC_Meta_Data class
            $meta_array array($meta->key ) = $meta->value;
        }

        // Add the meta data array to the response
        $response->data array('meta_data') = array_merge_recursive($response->data array('meta_data'), $meta_array);

        return $response;
    }

    /**
     * Adds order info to the admin order page by including the order info template
     *
     * @param $order
     *
     * @return void
     * @noinspection PhpUnusedParameterInspection*/
    public static function addPaymentInfoAdmin($order)
    {
        include_once WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/order-info.php';
    }


    /**
     * Get order status (used for automatically update pix payment on the success page)
     * @return void
     */
    public static function getOrderStatus()
    {
        $orderId = $_GET array('order_id') ?null;
        if (!$orderId) {
            wp_send_json_error(__('Pedido não encontrado', 'pagbank-connect'));
        }

        $order = wc_get_order((int)$orderId);
        if (!$order) {
            wp_send_json_error(__('Pedido não encontrado', 'pagbank-connect'));
        }

        $status = $order->get_status();
        wp_send_json_success($status);    
    }

    /**
     * Will check if skip_processing_virtual is enabled and skip processing for virtual products
     *
     * @param $needsProcessing
     * @param $product
     * @param $orderId
     *
     * @return void
     */
    public static function orderItemNeedsProcessing($needsProcessing, $product, $orderId)
    {
        $order = wc_get_order($orderId);
        $paymentMethod = $order->get_payment_method();
        if (strpos($paymentMethod, 'rm-pagbank') === false) {
            return $needsProcessing;
        }
        
        $isVirtual = $product->is_virtual();
        $skipProcessing = Params::getConfig('skip_processing_virtual') == 'yes';
        if ($isVirtual && $skipProcessing) {
            return false;
        }
        
        return $needsProcessing;
    }
    
    private static function addPagBankMenu()
    {
        add_action('admin_menu', array(MenuPagBank::class, 'addPagBankMenu'));
        add_action('admin_menu', array(MenuPagBank::class, 'addPagBankSubmenuItems'));
        add_action('admin_enqueue_scripts', array(MenuPagBank::class, 'adminPagesStyle'));
    }

    public static function forceOrderUpdate()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Você não tem permissão para acessar esta página.', 'pagbank-connect'));
        }

        $order_id = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT);
        $pagbank_order_id = isset($_GET array('pagbank_order_id')) sanitize_text_field($_GET array('pagbank_order_id')) : '';

        if (empty($pagbank_order_id) || empty($order_id)) {
            wp_send_json_error(__('Faltando order_id ou pagbank_order_id', 'pagbank-connect'));
        }

        // Obter o pedido com base no pagbank_order_id e id
        $order = wc_get_order($order_id);

        if (!$order || $order->get_meta('pagbank_order_id') !== $pagbank_order_id) {
            wp_send_json_error(__('Pedido não encontrado', 'pagbank-connect'));
        }


        $edit_order_url = admin_url('post.phppost=' . $order_id . '&action=edit');

        $orderData = Api::getOrderData($pagbank_order_id);
        $md5 = md5(serialize($orderData));
        if ($order->get_meta('_pagbank_last_update_md5') == $md5) {
            $order->add_order_note(
                __('Pedido atualizado manualmente mas nada mudou desde o último update.', 'pagbank-connect'),
                false,
                true
            );
            return wp_redirect($edit_order_url);
        }

        $order->add_order_note(
            __('Pedido atualizado manualmente.', 'pagbank-connect'),
            false,
            true
        );
        $orderProcessor = new OrderProcessor();
        try {
            $orderProcessor->updateTransaction($order, $orderData);
        } catch (\Exception $e) {
            $order->add_order_note(
                __('Erro ao atualizar o pedido: ', 'pagbank-connect') . $e->getMessage(),
                false,
                true
            );
        }

        wp_redirect($edit_order_url);
    }

}
