<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Summary
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Summary implements JsonSerializable
{
    private $total;
    private $paid;
    private $refunded;

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getTotal() {
        return $this->total;
    }

    /**
     * @param int $total
     */
    public function setTotal($total) {
        $this->total = $total;
    }

    /**
     * @return int
     */
    public function getPaid() {
        return $this->paid;
    }

    /**
     * @param int $paid
     */
    public function setPaid($paid) {
        $this->paid = $paid;
    }

    /**
     * @return int
     */
    public function getRefunded() {
        return $this->refunded;
    }

    /**
     * @param int $refunded
     */
    public function setRefunded($refunded) {
        $this->refunded = $refunded;
    }

}
