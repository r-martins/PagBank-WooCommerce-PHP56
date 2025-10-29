<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;
use RM_PagBank\Helpers\Params;

/**
 * Class Address
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Address implements JsonSerializable
{
    private $street;
    private $number;
    private $complement;
    private $locality;
    private $city;
    private $region;
    private $region_code;
    private $country = 'BRA';
    private $postal_code;

        public function jsonSerialize()
    {
        $vars = get_object_vars($this);
        if (empty($vars['complement'])) {
            unset($vars['complement']);
        }
        
        return $vars;
    }

    public function setStreet($street)
    {
        $this->street = substr($street, 0, 160);
    }

    public function getStreet() {
        return $this->street;
    }

    /**
     * @return mixed
     */
    public function getNumber() {
        return $this->number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number) {
        $this->number = substr($number, 0, 20);
    }

    /**
     * @return mixed
     */
    public function getComplement() {
        return $this->complement;
    }

    /**
     * @param mixed $complement
     */
    public function setComplement($complement) {
        $this->complement = substr($complement, 0, 40);
    }

    /**
     * @return mixed
     */
    public function getLocality() {
        return $this->locality;
    }

    /**
     * @param mixed $locality Bairro do endereço
     */
    public function setLocality($locality) {
        $this->locality = substr($locality, 0, 60);
    }

    /**
     * @return mixed
     */
    public function getRegionCode() {
        return $this->region_code;
    }

    /**
     * @param mixed $region_code Código do estado (ex: SP)
     */
    public function setRegionCode($region_code) {
        $this->region_code = strtoupper(substr($region_code, 0, 2));
        if ($this->country == 'BRA'){
            $this->region = $this->getRegion();
        }
    }

    /**
     * @return mixed
     */
    public function getCity() {
        return $this->city;
    }

    /**
     * @param mixed $city
     */
    public function setCity($city) {
        $this->city = substr($city, 0, 90);
    }

    /**
     * @return mixed
     */
    public function getRegion() {
        if ($this->country == 'BRA'){
            $regionCode = strtoupper($this->region_code);
            $brStatesNames = [
                'AC' => 'Acre',
                'AL' => 'Alagoas',
                'AP' => 'Amapá',
                'AM' => 'Amazonas',
                'BA' => 'Bahia',
                'CE' => 'Ceará',
                'DF' => 'Distrito Federal',
                'ES' => 'Espírito Santo',
                'GO' => 'Goiás',
                'MA' => 'Maranhão',
                'MT' => 'Mato Grosso',
                'MS' => 'Mato Grosso do Sul',
                'MG' => 'Minas Gerais',
                'PA' => 'Pará',
                'PB' => 'Paraíba',
                'PR' => 'Paraná',
                'PE' => 'Pernambuco',
                'PI' => 'Piauí',
                'RJ' => 'Rio de Janeiro',
                'RN' => 'Rio Grande do Norte',
                'RS' => 'Rio Grande do Sul',
                'RO' => 'Rondônia',
                'RR' => 'Roraima',
                'SC' => 'Santa Catarina',
                'SP' => 'São Paulo',
                'SE' => 'Sergipe',
                'TO' => 'Tocantins'
            ];

            if (isset($brStatesNames[$regionCode])){
                return $brStatesNames[$regionCode];
            }
        }

        return $this->region !== null ? $this->region : '';
    }

    /**
     * Optional if you have informed the region code
     * @param mixed $region Estado do endereço (ex: São Paulo)
     */
    public function setRegion($region) {
        $this->region = substr($region, 0, 50);
    }

    /**
     * @return string
     */
    public function getCountry() {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry($country) {
        $this->country = substr($country, 0, 3);
    }

    /**
     * @return mixed
     */
    public function getPostalCode() {
        return $this->postal_code;
    }

    /**
     * @param mixed $postal_code
     */
    public function setPostalCode($postal_code) {
        $postal_code = Params::removeNonNumeric($postal_code);
        $this->postal_code = substr($postal_code, 0, 8);
    }
}
