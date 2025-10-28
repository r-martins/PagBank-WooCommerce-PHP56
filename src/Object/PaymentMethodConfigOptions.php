<php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

// use JsonSerializable; // PHP 5.6 compatibility

/**
 * Class PaymentMethodConfigOptions
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 * @link https://developer.pagbank.com.br/reference/criar-checkout
 */
class PaymentMethodConfigOptions implements JsonSerializable
{
    private $option;
    private $value;
    
    const OPTION_INSTALLMENTS_LIMIT = 'INSTALLMENTS_LIMIT';
    const OPTION_INTEREST_FREE_INSTALLMENTS = 'INTEREST_FREE_INSTALLMENTS';


    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    public function getOption()
    {
        return $this->option;
    }

    public function setOption($option)
    {
        $this->option = $option;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }


}
