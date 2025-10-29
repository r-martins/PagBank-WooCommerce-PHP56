<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Holder
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Holder implements JsonSerializable
{
    private $name;
    private $tax_id;
    private $email;
    private $address;

        public function jsonSerialize()
    {
        return get_object_vars($this);
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
        $this->name = substr($name, 0, 30);
    }

    /**
     * @return string
     */
    public function getTaxId() {
        return $this->tax_id;
    }

    /**
     * @param string $tax_id
     */
    public function setTaxId($tax_id) {
        $this->tax_id = substr($tax_id, 0, 14);
    }

    /**
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email) {
        $email = strtolower($email);
        $this->email = substr($email, 0, 255);
    }

    /**
     * @return Address
     */
    public function getAddress() {
        return $this->address;
    }

    /**
     * @param Address $address
     */
    public function setAddress(Address $address) {
        $this->address = $address;
    }

}
