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
 * Class provides customer location for pickup points.
 *
 * @package Tygh\Shippings\PickupPoints\Location
 */
class PickupPointsLookupLocation
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

    /** @var float|null $latitude Latitude */
    protected $latitude;

    /** @var float|null $longitude Longitude */
    protected $longitude;

    /**
     * PickupPointsLookupLocation constructor.
     *
     * @param string      $city      City name
     * @param string      $country   Country code (ISO 3166-1 alpha-2)
     * @param string|null $state     State / region code (ISO 3166-2)
     * @param string|null $zipcode   Zip / postal code
     * @param string|null $address   Address line
     * @param float|null  $latitude  Latitude
     * @param float|null  $longitude Longitude
     */
    public function __construct(
        $city = null,
        $country = null,
        $state = null,
        $zipcode = null,
        $address = null,
        $latitude = null,
        $longitude = null
    ) {
        $this->city = $city;
        $this->country = $country;
        $this->state = $state;
        $this->zipcode = $zipcode;
        $this->address = $address;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
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
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
        ];
    }

    /**
     * @param array $location Location
     *
     * @return PickupPointsLookupLocation
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function createFromArray(array $location)
    {
        return new self(
            $location['city'] ?? null,
            $location['country'] ?? null,
            $location['state'] ?? null,
            $location['zipcode'] ?? null,
            $location['address'] ?? null,
            $location['latitude'] ?? null,
            $location['longitude'] ?? null
        );
    }
}
