<php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

// use JsonSerializable; // PHP 5.6 compatibility

/**
 * Class Shipping
 *
 * @author    Ricardo Martins
 * @copyright 2025 PagBank Integrações (Parceiro Oficial)
 * @package   RM_PagBank\Object
 * @link https://developer.pagbank.com.br/reference/criar-checkout
 */
class Shipping implements JsonSerializable
{
    const TYPE_FREE = 'FREE';
    const TYPE_FIXED = 'FIXED';
    const TYPE_CALCULATE = 'CALCULATE';
    
    const SERVICE_TYPE_PAC = 'PAC';
    const SERVICE_TYPE_SEDEX = 'SEDEX';
    
    private $type;
    private $service_type;
    private $address_modifiable;
    private $amount;
    private Address $address;
//    not implemented
//    private Box $box;

    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getServiceType()
	{
		return $this->service_type;
	}

	public function setServiceType($service_type)
	{
		$this->service_type = $service_type;
	}

	public function getType()
	{
		return $this->type;
	}

	public function setType($type)
	{
		$this->type = $type;
	}

    public function isAddressModifiable()
    {
        return $this->address_modifiable;
    }

    public function setAddressModifiable($address_modifiable)
    {
        $this->address_modifiable = $address_modifiable;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
    }
    
    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;
    }

}
