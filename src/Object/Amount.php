<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Amount
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Amount implements JsonSerializable
{
    private $value;
    private $currency = 'BRL';
    private $summary;
	private $fees;

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @param int $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getCurrency() {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency) {
        $this->currency = substr($currency, 0, 3);
    }

    /**
     * @return Summary
     */
    public function getSummary() {
        return $this->summary;
    }

    /**
     * @param Summary $summary
     */
    public function setSummary(Summary $summary) {
        $this->summary = $summary;
    }

	public function getFees() {
		return $this->fees;
	}

	public function setFees(Fees $fees) {
		$this->fees = $fees;
	}

}
