<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class AuthenticationMethod
 * Objeto contendo os dados adicionais de autenticação vínculados à uma transação.
 * Obrigatório para o método de pagamento com cartão de débito. ⚠️
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class AuthenticationMethod implements JsonSerializable
{
    /*Indica o método de autenticação utilizado na cobrança. ⚠️ Condicional para Token de Bandeira ELO. ⚠️
    - THREEDS se o método de autenticação utilizado for 3DS.
    - INAPP se o método de autenticação utilizado for InApp. */
    protected $type;

    /*Identificador do método de autenticação utilizado.*/
    protected $id;

    /*Identificador único gerado em cenário de sucesso de autenticação do cliente.*/
    protected $cavv;

    /*Indicador E-Commerce retornado quando ocorre uma autenticação. Corresponde ao resultado da autenticação.
    * (required)
    */
    protected $eci;

    /*Identificador de uma transação de um MPI - Recomendado para a bandeira VISA. ⚠️ Condicional para 3DS. ⚠️*/
    protected $xid;

    /*Versão do protocolo 3DS utilizado na autenticação.*/
    protected $version;

    /*ID da transação gerada pelo servidor de diretório durante uma autenticação - Recomendado para a bandeira MASTERCARD. ⚠️ Condicional para 3DS. ⚠️*/
    protected $dstrans_id;

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
    public function getCavv() {
        return $this->cavv;
    }

    /**
     * @param string $cavv
     */
    public function setCavv($cavv) {
        $this->cavv = $cavv;
    }

    /**
     * @return string
     */
    public function getEci() {
        return $this->eci;
    }

    /**
     * @param string $eci
     */
    public function setEci($eci) {
        $this->eci = $eci;
    }

    /**
     * @return string
     */
    public function getXid() {
        return $this->xid;
    }

    /**
     * @param string $xid
     */
    public function setXid($xid) {
        $this->xid = $xid;
    }

    /**
     * @return string
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion($version) {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getDstransId() {
        return $this->dstrans_id;
    }

    /**
     * @param string $dstrans_id
     */
    public function setDstransId($dstrans_id) {
        $this->dstrans_id = $dstrans_id;
    }

}
