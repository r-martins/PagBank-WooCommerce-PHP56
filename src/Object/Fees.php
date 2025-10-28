<php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

// use JsonSerializable; // PHP 5.6 compatibility

/**
 * Class Amount
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Fees implements JsonSerializable
{
    private Buyer $buyer;


    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getBuyer()
	{
		return $this->buyer;
	}

	public function setBuyer(Buyer $buyer)
	{
		$this->buyer = $buyer;
	}

}
