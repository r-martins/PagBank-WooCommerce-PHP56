<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Customer
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Customer implements JsonSerializable
{
    private $name;
    private $email;
    private $tax_id;
    private $phone; //type not declared because it can be an array or a Phone object and mixed types are not allowed in PHP 7.4

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
        $this->name = $name;
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
        $this->email = $email;
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
        $this->tax_id = $tax_id;
    }

    /**
     * @return array
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param array|Phone $phone When in Redirect mode, it receives the phone object directly
     */
    public function setPhone($phone) {
        $this->phone = $phone;
    }

}
