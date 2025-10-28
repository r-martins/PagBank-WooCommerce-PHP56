<php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

// use JsonSerializable; // PHP 5.6 compatibility

/**
 * Class QrCode
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class QrCode implements JsonSerializable
{
    private Amount $amount;
    private $expiration_date;

    # array(\ReturnTypeWillChange)
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param $amount
     */
    public function setAmount(Amount $amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getExpirationDate()
    {
        return $this->expiration_date;
    }

    /**
     * @param $expiration_date ISO 8601 (2021-08-29T20:15:59-03:00)
     */
    public function setExpirationDate($expiration_date)
    {
        $this->expiration_date = $expiration_date;
    }

}
