<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class PaymentMethod
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class PaymentMethod implements JsonSerializable
{
    private $type;
    private $installments;
    private $capture;
    private $soft_descriptor;
    private $card;
    private $boleto;
    private $authentication_method;

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getInstallments() {
        return $this->installments;
    }

    /**
     * @param int $installments
     */
    public function setInstallments($installments) {
        $this->installments = $installments;
    }

    /**
     * @return bool
     */
    public function isCapture() {
        return $this->capture;
    }

    /**
     * @param bool $capture
     */
    public function setCapture($capture) {
        $this->capture = $capture;
    }

    /**
     * @return string
     */
    public function getSoftDescriptor() {
        return $this->soft_descriptor;
    }

    /**
     * @param string $soft_descriptor
     */
    public function setSoftDescriptor($soft_descriptor) {
        $this->soft_descriptor = $soft_descriptor;
    }

    /**
     * @return Card
     */
    public function getCard() {
        return $this->card;
    }

    /**
     * @param Card $card
     */
    public function setCard(Card $card) {
        $this->card = $card;
    }

    /**
     * @return Boleto
     */
    public function getBoleto() {
        return $this->boleto;
    }

    /**
     * @param Boleto $boleto
     */
    public function setBoleto(Boleto $boleto) {
        $this->boleto = $boleto;
    }

    public function getAuthenticationMethod() {
        return $this->authentication_method;
    }

    public function setAuthenticationMethod(AuthenticationMethod $authentication_method) {
        $this->authentication_method = $authentication_method;
    }

}
