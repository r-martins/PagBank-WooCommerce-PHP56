<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use DateTime;
use JsonSerializable;

/**
 * Class Charge
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Charge implements JsonSerializable
{
    protected $id;
    protected $status;
    protected $created_at;
    protected $paid_at;
    protected $reference_id;
    protected $description;
    protected $amount;
    protected $payment_response;
    protected $payment_method;
    protected $recurring;

    const ALLOWED_STATUS = [
        'AUTHORIZED',  // Indica que a cobrança está pré-autorizada.
        'PAID',        // Indica que a cobrança está paga (capturada).
        'IN_ANALYSIS', // Indica que o comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.
        'DECLINED',    // Indica que a cobrança foi negada pelo PagSeguro ou Emissor
        'CANCELED'     // Indica que a cobrança foi cancelada.
    ];

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id) {
        $this->id = substr($id, 0, 41);
    }

    /**
     * @return string
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status) {
        $this->status = substr($status, 0, 64);
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt() {
        return $this->created_at;
    }

    /**
     * @param DateTime $created_at
     */
    public function setCreatedAt(DateTime $created_at) {
        $this->created_at = $created_at;
    }

    /**
     * @return DateTime
     */
    public function getPaidAt() {
        return $this->paid_at;
    }

    /**
     * @param DateTime $paid_at
     */
    public function setPaidAt(DateTime $paid_at) {
        $this->paid_at = $paid_at;
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
        $this->reference_id = substr($reference_id, 0, 64);
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description) {
        $this->description = substr($description, 0, 64);
    }

    /**
     * @return Amount
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * @param Amount $amount
     */
    public function setAmount(Amount $amount) {
        $this->amount = $amount;
    }

    /**
     * @return PaymentResponse
     */
    public function getPaymentResponse() {
        return $this->payment_response;
    }

    /**
     * @param PaymentResponse $payment_response
     */
    public function setPaymentResponse(PaymentResponse $payment_response) {
        $this->payment_response = $payment_response;
    }

    /**
     * @return PaymentMethod
     */
    public function getPaymentMethod() {
        return $this->payment_method;
    }

    /**
     * @param PaymentMethod $payment_method
     */
    public function setPaymentMethod(PaymentMethod $payment_method) {
        $this->payment_method = $payment_method;
    }

    public function getRecurring() {
        return $this->recurring;
    }

    public function setRecurring(Recurring $recurring) {
        $this->recurring = $recurring;
    }

}
