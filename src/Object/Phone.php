<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Phone
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Phone implements JsonSerializable
{
    private $country = 55;
    private $area;
    private $number;
    private $type = 'MOBILE';

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getCountry() {
        return $this->country;
    }

    /**
     * @param int $country
     */
    public function setCountry($country) {
        $this->country = $country;
    }

    /**
     * @return int
     */
    public function getArea() {
        return $this->area;
    }

    /**
     * @param int $area
     */
    public function setArea($area) {
        $this->area = $area;
    }

    /**
     * @return int
     */
    public function getNumber() {
        return $this->number;
    }

    /**
     * @param int $number
     */
    public function setNumber($number) {
        $this->number = $number;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
	 * Type can be MOBILE, BUSINESS or HOME
     * @param string $type
     */
    public function setType($type) {
        $this->type = $type;
    }

}
