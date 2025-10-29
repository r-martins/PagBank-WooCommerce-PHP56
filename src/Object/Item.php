<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

class Item implements JsonSerializable
{
    private $reference_id;
    private $name;
    private $quantity;
    private $unit_amount;

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getReferenceId() {
        return $this->reference_id;
    }

    /**
     * @param string $reference_id
     */
    public function setReferenceId($reference_id) {
        $this->reference_id = substr($reference_id, 0, 255);
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = substr($name, 0, 100);
    }

    /**
     * @return int
     */
    public function getQuantity() {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity) {
        $this->quantity = $quantity;
    }

    /**
     * @return int
     */
    public function getUnitAmount() {
        return $this->unit_amount;
    }

    /**
     * @param int $unit_amount
     */
    public function setUnitAmount($unit_amount) {
        $this->unit_amount = $unit_amount;
    }
}
