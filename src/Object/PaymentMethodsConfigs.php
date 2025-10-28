<php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

// use JsonSerializable; // PHP 5.6 compatibility

/**
 * Class PaymentMethodsConfigs
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 * @link https://developer.pagbank.com.br/reference/criar-checkout
 */
class PaymentMethodsConfigs implements JsonSerializable
{
    /**
     * @var string CREDIT_CARD, PIX, BOLETO, DEBIT_CARD
     */
    private $type;
    /**
     * @var array of PaymentMethodConfigOptions
     */
    private $config_options;


    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getType()
	{
		return $this->type;
	}

	public function setType($type)
	{
		$this->type = $type;
	}

    public function getConfigOptions()
    {
        return $this->config_options;
    }

    public function setConfigOptions($configOptions)
    {
        $this->config_options = $configOptions;
    }

}
