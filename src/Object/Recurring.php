<php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

// use JsonSerializable; // PHP 5.6 compatibility

/**
 * Class Recurring
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Recurring implements JsonSerializable
{
    protected $type;

    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Set Recurring Type. Can be INITIAL or SUBSEQUENT.
	 * @param $type
	 *
	 * @return void
	 */
	public function setType($type)
	{
		$this->type = $type;
	}

}
