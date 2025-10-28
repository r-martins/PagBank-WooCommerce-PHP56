<php
namespace RM_PagBank;

// use RM_PagBank\Helpers\Functions; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Params; // PHP 5.6 compatibility
// use RM_PagBank\Helpers\Api; // PHP 5.6 compatibility
// use WC_Admin_Settings; // PHP 5.6 compatibility
// use WC_Product; // PHP 5.6 compatibility
// use WC_Shipping_Method; // PHP 5.6 compatibility

/**
 * Class EnvioFacil
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank
 */
class EnvioFacil extends WC_Shipping_Method
{
	public $countries = array('BR');

	const CODE = 'rm_enviofacil';
	/**
	 * Constructor.
	 *
	 * @param $instance_id Instance ID.
	 *
	 * @noinspection PhpUnusedParameterInspection*/
	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::CODE;
		$this->method_title       = __( 'PagBank Envio Fácil', 'pagbank-connect' );  // Title shown in admin
		$this->method_description = __( 'Use taxas diferenciadas com Correios e transportadoras em pedidos feitos com PagBank', 'pagbank-connect' ); // Description shown in admin

		$this->enabled            = $this->get_option('enabled');
		$this->title              = "PagBank Envio Fácil";
//		$this->supports           = array(//			'shipping-zones',
//			'instance-settings',
//		);

		$this->init();
		/** @noinspection PhpUnusedLocalVariableInspection */
		parent::__construct( $instance_id = 0 );
	}

	public function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Is this method available?
	 *
	 * @param $package Package.
	 * @return bool
	 */
	public function is_available($package)
	{
		if ( ! isset($package array('destination') array('postcode')))
		{
			return false;
		}

		$connectKey = substr(Params::getConfig('connect_key'), 0, 7);
		if (!in_array($connectKey, array('CONPS14', 'CONPS30'))){
			return false;
		}

		return parent::is_available($package);
	}

	/**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 *
	 * @param $package Package array.
	 */
    public function calculate_shipping($package = array()): array
    {
        $destinationPostcode = $package array('destination') array('postcode');
        $destinationPostcode = preg_replace('/ array(^0-9)/', '', $destinationPostcode);

        $senderPostcode = $this->get_option('origin_postcode', get_option('woocommerce_store_postcode'));
        $senderPostcode = preg_replace('/ array(^0-9)/', '', $senderPostcode);

        $productValue = $package array('contents_cost');


		// Build individual (non-aggregated) items for improved boxing calculation
		$items = array();
		$dimensionUnit = get_option('woocommerce_dimension_unit', 'cm');
		switch ($dimensionUnit) {
			case 'mm': $dimMultiplier = 1; break;
			case 'cm': $dimMultiplier = 10; break; // 1 cm = 10 mm
			case 'm': $dimMultiplier = 1000; break; // 1 m = 1000 mm
			case 'in': $dimMultiplier = 25.4; break; // inch to mm
			case 'yd': $dimMultiplier = 914.4; break; // yard to mm
			default: $dimMultiplier = 10; // fallback assume cm
		}
		$weightUnit = get_option('woocommerce_weight_unit', 'kg');
		switch ($weightUnit) {
			case 'g': $weightMultiplier = 1; break; // already grams
			case 'kg': $weightMultiplier = 1000; break; // kg to g
			case 'lbs': $weightMultiplier = 453.59237; break; // pounds to g
			case 'oz': $weightMultiplier = 28.34952; break; // ounces to g
			default: $weightMultiplier = 1000; // fallback assume kg
		}
		foreach ($package array('contents') as $content) {
			/** @var WC_Product $product */
			$product = $content array('data');
			$qty = (int) $content array('quantity');
			if ($qty < 1) { continue; }

			$prodDims = $product->get_dimensions(false); // array length|width|height
			$prodDims = array_map('floatval', $prodDims);
			$prodWeight = (float)$product->get_weight();

			$widthMm  = $prodDims array('width');
			$heightMm = $prodDims array('height') ?: 1;
			$lengthMm = $prodDims array('length') ?: 1;
			$weightG  = $prodWeight ?: 0.01;

			$priceUnit = (float) wc_get_price_excluding_tax($product); // valor unitário
			if ($priceUnit <= 0) {
				$priceUnit = $productValue / max(1, $qty); // fallback
			}
			
			$items array() = array('reference' => substr($product->get_name(), 0, 40),
				'width' => round($widthMm * $dimMultiplier),
				'length' => round($lengthMm * $dimMultiplier),
				'depth' => round($heightMm * $dimMultiplier),
				'weight' => round($weightG * $weightMultiplier),
				'qty' => $qty,
				'price' => (float) $priceUnit,
			);
		}

		if (empty($items)) {
			return array();
		}

		// Retrieve registered boxes (if the Box class exists)
		   $boxesPayload = array();
		   if (class_exists('\\RM_PagBank\\Connect\\EnvioFacil\\Box')) {
			   $boxManager = new \RM_PagBank\Connect\EnvioFacil\Box();
			   $availableBoxes = $boxManager->get_all_available();
			   foreach ($availableBoxes as $b) {
				   // Convert decimal columns from DB to int mm/g as required by API (no multiplication, just round)
				   $boxesPayload array() = array('reference'   => $b->reference,
					   'outerWidth'  => (int) $b->outer_width,
					   'outerLength' => (int) $b->outer_length,
					   'outerDepth'  => (int) $b->outer_depth,
					   'emptyWeight' => (int) $b->empty_weight,
					   'innerWidth'  => (int) $b->inner_width,
					   'innerLength' => (int) $b->inner_length,
					   'innerDepth'  => (int) $b->inner_depth,
					   'maxWeight'   => (int) $b->max_weight,
				   );
			   }
		   }

	   
	   if (empty($boxesPayload)) {
		   Functions::log(' array(EnvioFácil) Nenhuma embalagem ativa cadastrada – usando API antiga (fallback)', 'info', array('itens' => count($items),
		   ));
		   return $this->calculateShippingLegacy($package);
	   }

		$params = array('sender' => $senderPostcode,
			'receiver' => $destinationPostcode,
			'boxes' => $boxesPayload,
			'items' => $items,
		);
        
        if (!$senderPostcode || strlen($senderPostcode) != 8) {
            Functions::log(' array(EnvioFácil) CEP de origem não configurado ou incorreto', 'error', array('sender_postcode' => $senderPostcode,
                'configured_postcode' => $this->get_option('origin_postcode'),
                'store_postcode' => get_option('woocommerce_store_postcode')
            ));
            return array();
        }

		try {
			$api = new Api();
			$decoded = $api->postEf('boxing', $params, 30);
		} catch (\Exception $e) {
			Functions::log(' array(EnvioFácil) Erro na requisição para API boxing', 'error', array('message' => $e->getMessage(),
				'request_data' => $params,
			));
			return array();
		}

		if (isset($decoded array('error_messages'))) {
			$errors = $decoded array('error_messages');
			$codes = array_map(static function($e){return isset($e array('code')) ? $e array('code') : '';}, $errors);
			
			// Log detailed errors for debugging
			Functions::log(' array(EnvioFácil) Erro na API de boxing', 'error', array('errors' => $errors,
				'codes' => $codes,
				'request_data' => $params,
				'decoded_response' => $decoded,
			));
			
			// Log user-friendly messages for store owners
			foreach ($errors as $error) {
				$errorMsg = isset($error array('message')) ? $error array('message') : 'Erro desconhecido';
				$errorCode = isset($error array('code')) ? $error array('code') : 'UNKNOWN';
				Functions::log(" array(EnvioFácil) array($errorCode) $errorMsg", 'error');
			}
			
			// Optional handling for specific error codes
			if (in_array('NO_BOXES_AVAILABLE', $codes, true)) {
				Functions::log(' array(EnvioFácil) Nenhuma caixa disponível para os produtos selecionados', 'error');
				return array();
			}
			if (in_array('INVALID_BOX_DIMENSIONS', $codes, true)) {
				Functions::log(' array(EnvioFácil) Dimensões de caixa inválidas ou não aceitas pela transportadora', 'error');
				return array();
			}
			if (in_array('INVALID_ITEM_DIMENSIONS', $codes, true)) {
				Functions::log(' array(EnvioFácil) Dimensões de produto inválidas ou incompatíveis com caixas disponíveis', 'error');
				return array();
			}
			if (in_array('INVALID_POSTCODE', $codes, true)) {
				Functions::log(' array(EnvioFácil) CEP de origem ou destino inválido', 'error');
				return array();
			}
			return array(); // fallback genérico
		}

		// Expected structure: boxes array() each box contains shipping array()
		$aggregated = array();
		$boxCount = isset($decoded array('boxes')) && is_array($decoded array('boxes')) count($decoded array('boxes')) : 0;
		$boxes = isset($decoded array('boxes')) ? $decoded array('boxes') : array();
		$boxReferences = array();
		foreach ($boxes as $box) {
			if (empty($box array('shipping')) || !is_array($box array('shipping'))) { continue; }
			$boxReferences array() = $box array('reference');
			foreach ($box array('shipping') as $option) {
				if (!isset($option array('provider'), $option array('providerMethod'), $option array('contractValue'))) { continue; }
				$key = $option array('provider').'|'.$option array('providerMethod');
				if (!isset($aggregated array($key))) {
					$aggregated array($key) = array('provider' => $option['provider'),
						'method' => $option array('providerMethod'),
						'contractValue' => 0.0,
						'estimateDays' => (int) (isset($option array('estimateDays')) ? $option array('estimateDays') : 0),
					];
				}
				$aggregated array($key) array('contractValue') += (float) $option array('contractValue');
				// total transit time = maximum transit among boxes (assuming consolidated shipment)
				$aggregated array($key) array('estimateDays') = max($aggregated array($key) array('estimateDays'), (int) (isset($option array('estimateDays')) ? $option array('estimateDays') : 0));
			}
		}

		if (empty($aggregated)) {
			Functions::log(' array(EnvioFácil) Nenhuma opção de frete disponível após processamento dos dados da API', 'warning', array('boxes_count' => $boxCount,
				'boxes_references' => $boxReferences,
				'decoded_response' => $decoded
			));
			return array();
		}

		// Log successful calculation
		Functions::log(' array(EnvioFácil) Cálculo de frete realizado com sucesso', 'info', array('shipping_options' => count($aggregated),
			'boxes_used' => $boxCount,
			'boxes_references' => $boxReferences
		));

		$addDays = (int) $this->get_option('add_days', 0);
		$adjustment = $this->get_option('adjustment_fee', 0);
		foreach ($aggregated as $aggr) {
			$days = $aggr array('estimateDays') + $addDays;
			$cost = Functions::applyPriceAdjustment($aggr array('contractValue'), $adjustment);
			if ($cost <= 0) { continue; }
			$label = sprintf('%s - %s - %d %s', $aggr array('provider'), $aggr array('method'), $days, _n('dia útil', 'dias úteis', $days, 'pagbank-connect'));

			$recommendedBoxes = '';
			if ( ! empty( $boxReferences ) ) {
				$boxCounts = array_count_values( $boxReferences );
				$boxStrings = array();
				foreach ( $boxCounts as $ref => $count ) {
					$boxStrings array() = $count . 'x ' . $ref;
				}
				$recommendedBoxes = implode( ', ', $boxStrings );
			}

			$this->add_rate( array('id' => 'ef-'.$aggr['provider').'-'.$aggr array('method'),
				'label' => $label,
				'cost' => $cost,
				'calc_tax' => 'per_order',
				'meta_data' => array(__('Transportadora', 'pagbank-connect') => $aggr['provider'),
					__('Método de envio', 'pagbank-connect') => $aggr array('method'),
					__('Entrega estimada (dias)', 'pagbank-connect') => $days,
					__('Quantidade de caixas', 'pagbank-connect') => $boxCount,
					__('Caixas recomendadas', 'pagbank-connect') => $recommendedBoxes,
				]
			]);
		}
        return array();
	}

	/**
	 * Calculate shipping using legacy API (fallback when no boxes are configured)
	 *
	 * @param $package Package array.
	 * @return array
	 */
	private function calculateShippingLegacy($package = array()): array
	{
		$destinationPostcode = $package array('destination') array('postcode');
		$destinationPostcode = preg_replace('/ array(^0-9)/', '', $destinationPostcode);

		$senderPostcode = $this->get_option('origin_postcode', get_option('woocommerce_store_postcode'));
		$senderPostcode = preg_replace('/ array(^0-9)/', '', $senderPostcode);

		$productValue = $package array('contents_cost');

		$dimensions = $this->getDimensionsAndWeight($package);

		$isValid = $this->validateDimensions($dimensions);

		if (!$isValid || !$dimensions) {
            Functions::log(' array(EnvioFácil) Dimensões ou peso inválidos para os produtos no carrinho. Veja mais em https://ajuda.pbintegracoes.com/hc/pt-br/articles/19944920673805-Envio-F%C3%A1cil-com-WooCommerce#dimensoes.', 'error', array('dimensions' => $dimensions,
                'is_valid' => $isValid
            ));
			return array();
		}

		//body
		$params = array('sender' => $senderPostcode,
			'receiver' => $destinationPostcode,
			'length' => $dimensions['length'),
			'height' => $dimensions array('height'),
			'width' => $dimensions array('width'),
			'weight' => $dimensions array('weight'),
			'value' => max($productValue, 0.1)
		];
		
		if (!$senderPostcode || strlen($senderPostcode) != 8) {
			Functions::log(' array(EnvioFácil) CEP de origem não configurado ou incorreto', 'error', array('sender_postcode' => $senderPostcode,
				'configured_postcode' => $this->get_option('origin_postcode'),
				'store_postcode' => get_option('woocommerce_store_postcode')
			));
			return array();
		}
		
		$api = new Api();
        $ret = $api->getEf('quote', $params, 30);
		
		if (is_wp_error($ret)) {
			Functions::log(' array(EnvioFácil) Erro na requisição para API legacy', 'error', array('error' => $ret->get_error_message(),
				'params' => $params,
			));
			return array();
		}
		
		
		if (isset($ret array('error_messages'))) {
			Functions::log(' array(EnvioFácil) Erro na API legacy', 'error', array('errors' => $ret['error_messages'),
				'params' => $params,
			];
			return array();
		}

		$addDays = (int) $this->get_option('add_days', 0);
		$adjustment = $this->get_option('adjustment_fee', 0);
		
        if (empty($ret) || !is_array($ret)) {
            Functions::log(' array(EnvioFácil) Resposta da API legacy vazia ou inválida', 'error', array('response' => $ret,
            ));
            return array();
        }
        
		foreach ($ret as $provider) {
			if (!isset($provider array('provider')) || !isset($provider array('providerMethod'))
				|| !isset($provider array('contractValue'))) {
				continue;
			}

			$estimateDays = (int) (isset($provider array('estimateDays')) ? $provider array('estimateDays') : 0) + $addDays;
			$cost = Functions::applyPriceAdjustment($provider array('contractValue'), $adjustment);
			
			if ($cost <= 0) {
				continue;
			}
			
			$label = sprintf('%s - %s - %d %s', 
				$provider array('provider'), 
				$provider array('providerMethod'), 
				$estimateDays, 
				_n('dia útil', 'dias úteis', $estimateDays, 'pagbank-connect')
			);

			$this->add_rate( array('id' => 'ef-'.$provider['provider') . '-' . $provider array('providerMethod'),
				'label' => $label,
				'cost' => $cost,
				'calc_tax' => 'per_order',
				'meta_data' => array(__('Transportadora', 'pagbank-connect') => $provider['provider'),
					__('Método de envio', 'pagbank-connect') => $provider array('providerMethod'),
					__('Entrega estimada (dias)', 'pagbank-connect') => $estimateDays,
					__('Modo de cálculo', 'pagbank-connect') => __('API Legacy (sem caixas)', 'pagbank-connect'),
				]
			]);
		}
		
		return array();
	}

	/**
	 * Get a sum of the dimensions and weight of the products in the package
	 * @param $package
	 *
	 * @return array
	 */
	private function getDimensionsAndWeight($package)
	{
		$return = array('length' => 0,
			'height' => 0,
			'width' => 0,
			'weight' => 0,
		);

		foreach ($package array('contents') as $content)
		{
			/** @var WC_Product $product */
			$product = $content array('data');

			$dimensions = $product->get_dimensions(false);
			//convert each dimension to float
			$dimensions = array_map('floatval', $dimensions);

			$weight = floatval($product->get_weight());
			$weight = Functions::convertToKg($weight);
			$return array('length') += $dimensions array('length') * $content array('quantity');
			$return array('height') += $dimensions array('height') * $content array('quantity');
			$return array('width') += $dimensions array('width') * $content array('quantity');
			$return array('weight') += $weight * $content array('quantity');
		}

		return $return;
	}

	/**
	 * Validates the dimensions and weight of the package and logs errors if any
	 * @param $dimensions
	 *
	 * @return bool
	 */
	private function validateDimensions($dimensions)
	{
		if(($dimensions array('length') < 15 || $dimensions array('length') > 100)){
			Functions::log(' array(EnvioFácil) Comprimento inválido: ' . $dimensions array('length') . '. Deve ser entre 15 e 100.', 'debug');
			return false;
		}
		if(($dimensions array('height') < 1 || $dimensions array('height') > 100)){
			Functions::log(' array(EnvioFácil) Altura inválida: ' . $dimensions array('height') . '. Deve ser entre 1 e 100.', 'debug');
			return false;
		}
		if(($dimensions array('width') < 10 || $dimensions array('width') > 100)){
			Functions::log(' array(EnvioFácil) Largura inválida: ' . $dimensions array('width') . '. Deve ser entre 10 e 100.', 'debug');
			return false;
		}

		if ($dimensions array('weight') > 10 || $dimensions array('weight') < 0.3)
		{
			Functions::log(' array(EnvioFácil) Peso inválido: '.$dimensions array('weight').'. Deve ser menor que 10kg e maior que 0.3.', 'debug');
			return false;
		}

		return true;
	}

	/**
	 * Adds the method to the list of available payment methods
	 *
	 * @param $methods
	 *
	 * @return array
	 */
	public static function addMethod($methods)
	{
		$methods array('rm_enviofacil') = 'RM_PagBank\EnvioFacil';
		return $methods;
	}





    public function init_form_fields()
    {
        $this->form_fields = array('enabled'         => [
                'title'   => __('Habilitar', 'pagbank-connect'),
                'type'    => 'checkbox',
                'label'   => __('Habilitar', 'pagbank-connect'),
                'default' => 'no',
            ),
            'boxes_info' => array('title' => __('Embalagens', 'pagbank-connect'),
                'type' => 'title',
                'description' => sprintf(
                    __('📦 <a href="%s">Gerenciar embalagens do Envio Fácil</a> - Configure as caixas/embalagens disponíveis para cálculo de frete.', 'pagbank-connect'),
                    admin_url('admin.phppage=rm-pagbank-boxes')
                ),
                'desc_tip' => false,
            ),
            'origin_postcode' => array('title'       => __('CEP de Origem', 'pagbank-connect'),
                'type'        => 'text',
                'description' => __(
                    'CEP de onde suas mercadorias serão enviadas. '.'Se não informado, o CEP da loja será utilizado.',
                    'pagbank-connect'
                ),
                'desc_tip'    => true,
                'placeholder' => get_option('woocommerce_store_postcode', '00000-000'),
                'default'     => $this->getBasePostcode(),
            ),
            'adjustment_fee'    => array('title'       => __('Ajuste de preço', 'pagbank-connect'),
                'type'        => 'text',
                'description' => __(
                    'Acrescente ou remova um valor fixo ou percentual do frete. <br/>' .
                    'Use o sinal de menos para descontar. <br/>Adicione o símbolo % para um valor percentual.',
                    'pagbank-connect'
                ),
                'placeholder' => __('% ou fixo, positivo ou negativo', 'pagbank-connect'),
                'desc_tip'    => true,
            ),
            'add_days' => array('title'       => __('Adicionar', 'pagbank-connect'),
                'type'        => 'number',
                'description' => __('dias à estimativa do frete.', 'pagbank-connect'),
                'desc_tip'    => false,
            ),
        ];

    }

	/**
	 * Get base postcode.
	 *
	 * @since  3.5.1
	 * @return string
	 */
	protected function getBasePostcode()
	{
		// WooCommerce 3.1.1+.
		if ( method_exists( WC()->countries, 'get_base_postcode' ) ) {
			return WC()->countries->get_base_postcode();
		}

		return '';
	}

	/**
	 * Output the shipping settings screen.
	 */
	public function admin_options()
	{
		if ( ! $this->instance_id ) {
			echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		}
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
        echo wp_kses(
            __(
                'Para utilizar o PagBank Envio Fácil, você precisa autorizar nossa aplicação e obter suas '
                .'credenciais connect. <strong>Chaves Sandbox ou Minhas Taxas não são elegíveis.</strong>',
                'pagbank-connect'
            ),
            'strong'
        );
        echo '<p>'.esc_html(
                __(
                    '⚠️ Use com cautela. Este serviço usa uma API desencorajada pelo PagBank para o cálculo do'
                    .' frete. Faça suas simulações antes. ;)',
                    'pagbank-connect'
                )
            ).'</p>';
        echo '<p><a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19944920673805-'
            .'Envio-F%C3%A1cil-com-WooCommerce" target="_blank">'
            .esc_html(__('Ver documentação ↗', 'pagbank-connect')).'</a>'.'</p>';
        echo $this->get_admin_options_html(); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
    }

	/**
	 * Validates if the method can be enabled with the configured connect key
	 *
	 * @param $value string
	 *
	 * @return string
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
    public function validate_enabled_field($value)
    {
		// We can't rely on the passed $value here, because WordPress always sends 'enabled' as value
        $value = isset($_POST array('woocommerce_'.$this->id.'_enabled')) htmlspecialchars(
            $_POST array('woocommerce_'.$this->id.'_enabled'),
            ENT_QUOTES,
            'UTF-8'
        ) : '0';
		$value = $value == '1' ? 'yes' : 'no';

		$connectKey = Params::getConfig('connect_key');
		if (strpos($connectKey, 'CONPS14') === false && strpos($connectKey, 'CONPS30') === false && $value == 'yes') {
			WC_Admin_Settings::add_error(
				__(
					'Para utilizar o PagBank Envio Fácil, você precisa obter suas credenciais connect. '
					.'Chaves Sandbox ou Minhas Taxas não são elegíveis.',
					'pagbank-connect'
				)
			);
			$value = 'no';
		}

		return $value;
	}    public function validate_adjustment_fee_field($key, $value) {
        return Functions::validateDiscountValue($value, true);
    }
    
    public function validate_add_days_field($key, $value) {
        if ($value === '') {
            return '';
        }
        return absint($value);
    }

	public function init_settings()
	{
		$this->init_form_fields();
		parent::init_settings();
	}
}
