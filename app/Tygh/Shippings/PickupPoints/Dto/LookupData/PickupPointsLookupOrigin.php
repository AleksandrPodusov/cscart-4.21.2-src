<?php
/***************************************************************************
*                                                                          *
*   © 2012 ООО "Эком Системы"                                              *
*                                                                          *
* Это коммерческое программное обеспечение. Только пользователи, которые   *
* приобрели действующую лицензию и согласились с условиями лицензионного   *
* соглашения, могут устанавливать и использовать эту программу.            *
*                                                                          *
****************************************************************************
* ПОЖАЛУЙСТА, ВНИМАТЕЛЬНО ПРОЧТИТЕ ПОЛНЫЙ ТЕКСТ ЛИЦЕНЗИОННОГО СОГЛАШЕНИЯ   *
* В ФАЙЛЕ "copyright.txt", ПРЕДОСТАВЛЕННОМ ВМЕСТЕ С ЭТИМ ДИСТРИБУТИВОМ.    *
***************************************************************************/

namespace Tygh\Shippings\PickupPoints\Dto\LookupData;

/**
 * Class provides shipping origin data for pickup points search.
 *
 * @package Tygh\Shippings\PickupPoints\Location
 */
class PickupPointsLookupOrigin
{
    /** @var string|null $city City name */
    protected $city;

    /** @var string|null $country Country (code) */
    protected $country;

    /** @var string|null $state_code State (code) */
    protected $state;

    /** @var string|null $zipcode Zipcode */
    protected $zipcode;

    /** @var string|null $address Address line */
    protected $address;

    /** @var string|null $name Name of sender */
    protected $name;

    /** @var int|null $company_id Company identifier of sender */
    protected $company_id;

    /** @var string|null $phone Phone number of sender */
    protected $phone;

    /**
     * PickupPointsLookupOrigin constructor.
     *
     * @param string|null $city       City name
     * @param string|null $country    Country code (ISO 3166-1 alpha-2)
     * @param string|null $state      State / region code (ISO 3166-2)
     * @param string|null $zipcode    Zip / postal code
     * @param string|null $address    Address line
     * @param string|null $name       Name of sender
     * @param int|null    $company_id Company identifier of sender
     * @param string|null $phone      Phone number of sender
     */
    public function __construct(
        $city = null,
        $country = null,
        $state = null,
        $zipcode = null,
        $address = null,
        $name = null,
        $company_id = null,
        $phone = null
    ) {
        $this->city = $city;
        $this->country = $country;
        $this->state = $state;
        $this->zipcode = $zipcode;
        $this->address = $address;
        $this->name = $name;
        $this->company_id = $company_id;
        $this->phone = $phone;
    }

    /**
     * Gets country.
     *
     * @return string|null
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Gets state code.
     *
     * @return string|null
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Gets city name.
     *
     * @return string|null
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string|null
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * @return string|null
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int|null
     */
    public function getCompanyId()
    {
        return $this->company_id;
    }

    /**
     * @return string|null
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function toArray()
    {
        return [
            'city'        => $this->city,
            'country'     => $this->country,
            'state'       => $this->state,
            'zipcode'     => $this->zipcode,
            'address'     => $this->address,
            'name'        => $this->name,
            'company_id'  => $this->company_id,
            'phone'       => $this->phone,
        ];
    }

    /**
     * @param array $origin_data Origin data
     *
     * @return \Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupOrigin
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function createFromArray(array $origin_data)
    {
        return new self(
            $origin_data['city'] ?? null,
            $origin_data['country'] ?? null,
            $origin_data['state'] ?? null,
            $origin_data['zipcode'] ?? null,
            $origin_data['address'] ?? null,
            $origin_data['name'] ?? null,
            $origin_data['company_id'] ?? null,
            $origin_data['phone'] ?? null
        );
    }
}
