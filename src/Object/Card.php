<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Card
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Card implements JsonSerializable
{
    protected $id;
    protected $encrypted;
    protected $network_token;
    protected $exp_month;
    protected $exp_year;
    protected $security_code;
    protected $store;
    protected $holder;
    protected $token_data;
    protected $authentication_method;

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
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getEncrypted() {
        return $this->encrypted;
    }

    /**
     * @param string $encrypted
     */
    public function setEncrypted($encrypted) {
        $this->encrypted = $encrypted;
    }

    /**
     * @return string
     */
    public function getNetworkToken() {
        return $this->network_token;
    }

    /**
     * @param string $network_token
     */
    public function setNetworkToken($network_token) {
        $this->network_token = $network_token;
    }

    /**
     * @return int
     */
    public function getExpMonth() {
        return $this->exp_month;
    }

    /**
     * @param int $exp_month
     */
    public function setExpMonth($exp_month) {
        $this->exp_month = $exp_month;
    }

    /**
     * @return int
     */
    public function getExpYear() {
        return $this->exp_year;
    }

    /**
     * @param int $exp_year
     */
    public function setExpYear($exp_year) {
        $this->exp_year = $exp_year;
    }

    /**
     * @return string
     */
    public function getSecurityCode() {
        return $this->security_code;
    }

    /**
     * @param string $security_code
     */
    public function setSecurityCode($security_code) {
        $this->security_code = $security_code;
    }

    /**
     * @return bool
     */
    public function isStore() {
        return $this->store;
    }

    /**
     * @param bool $store
     */
    public function setStore($store) {
        $this->store = $store;
    }

    /**
     * @return Holder
     */
    public function getHolder() {
        return $this->holder;
    }

    /**
     * @param Holder $holder
     */
    public function setHolder(Holder $holder) {
        $this->holder = $holder;
    }

    /**
     * @return TokenData
     */
    public function getTokenData() {
        return $this->token_data;
    }

    /**
     * @param TokenData $token_data
     */
    public function setTokenData(TokenData $token_data) {
        $this->token_data = $token_data;
    }

    /**
     * @return AuthenticationMethod
     */
    public function getAuthenticationMethod() {
        return $this->authentication_method;
    }

    /**
     * @param AuthenticationMethod $authentication_method
     */
    public function setAuthenticationMethod(AuthenticationMethod $authentication_method) {
        $this->authentication_method = $authentication_method;
    }
}
