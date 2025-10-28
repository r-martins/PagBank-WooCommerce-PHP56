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
class Interest implements JsonSerializable
{
    private $total;
    private $installments;


    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getInstallments()
	{
		return $this->installments;
	}

	public function setInstallments($installments)
	{
		$this->installments = $installments;
	}

	public function getTotal()
	{
		return $this->total;
	}

	public function setTotal($total)
	{
		$this->total = $total;
	}


}
