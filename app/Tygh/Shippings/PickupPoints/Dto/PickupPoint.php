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

namespace Tygh\Shippings\PickupPoints\Dto;

class PickupPoint
{
    /** @var string */
    protected $pickup_point_code;

    /** @var string|null */
    protected $pickup_point_name = null;

    /** @var string */
    protected $provider;

    /** @var string */
    protected $provider_city_code;

    /** @var string|null */
    protected $city_name = null;

    /** @var string */
    protected $address;

    /** @var string|null */
    protected $metro = null;

    /** @var string|null */
    protected $work_time = null;

    /** @var string|null */
    protected $phone = null;

    /** @var float */
    protected $latitude = 0.0;

    /** @var float */
    protected $longitude = 0.0;

    /** @var string|null */
    protected $notes = null;

    /** @var bool|null */
    protected $is_postamat = null;

    /**
     * @var array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingTraversableTypeHintSpecification
     */
    protected $extra_data = [];

    /**
     * @return string|null
     */
    public function getCityName()
    {
        return $this->city_name;
    }

    /**
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @return string
     */
    public function getPickupPointCode()
    {
        return $this->pickup_point_code;
    }

    /**
     * @param string|int $pickup_point_code Pickup point code
     *
     * @return void
     */
    public function setPickupPointCode($pickup_point_code)
    {
        $this->pickup_point_code = (string) $pickup_point_code;
    }

    /**
     * @return string|null
     */
    public function getPickupPointName()
    {
        return $this->pickup_point_name;
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @param string $provider Shipping service code
     *
     * @return void
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;
    }

    /**
     * @return string
     */
    public function getProviderCityCode()
    {
        return $this->provider_city_code;
    }

    /**
     * @param string $provider_city_code Shipping service city code
     *
     * @return void
     */
    public function setProviderCityCode($provider_city_code)
    {
        $this->provider_city_code = $provider_city_code;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param string $address Address
     *
     * @return void
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @return string|null
     */
    public function getMetro()
    {
        return $this->metro;
    }

    /**
     * @param string|null $metro Nearest station
     *
     * @return void
     */
    public function setMetro($metro)
    {
        $this->metro = $metro;
    }

    /**
     * @return string|null
     */
    public function getWorkTime()
    {
        return $this->work_time;
    }

    /**
     * @param string|null $work_time Pickup point work time
     *
     * @return void
     */
    public function setWorkTime($work_time)
    {
        $this->work_time = $work_time;
    }

    /**
     * @return string|null
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param string|null $phone Pickup point phone number
     *
     * @return void
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    /**
     * @return string|null
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @param string|null $notes Pickup point notes
     *
     * @return void
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     * @return bool|null
     */
    public function getIsPostamat()
    {
        return $this->is_postamat;
    }

    /**
     * @param bool|null $is_postamat Whether pickup point is postamat
     *
     * @return void
     */
    public function setIsPostamat($is_postamat)
    {
        $this->is_postamat = $is_postamat;
    }

    /**
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function getExtraData()
    {
        return $this->extra_data;
    }

    /**
     * @param array $extra_data Whole pickup point data from shipping API
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function setExtraData(array $extra_data)
    {
        $this->extra_data = $extra_data;
    }

    /**
     * @param string|null $city_name City name
     *
     * @return void
     */
    public function setCityName($city_name)
    {
        $this->city_name = $city_name;
    }

    /**
     * @param string|null $pickup_point_name Pickup point name
     *
     * @return void
     */
    public function setPickupPointName($pickup_point_name)
    {
        $this->pickup_point_name = $pickup_point_name;
    }

    /**
     * @param float $latitude Pickup point latitude
     *
     * @return void
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
    }

    /**
     * @param float $longitude Pickup point longitude
     *
     * @return void
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
    }


    /**
     * Create new PickupPoint object
     *
     * @param string|int  $pickup_point_code  Pickup point code
     * @param string      $provider           Shipping service
     * @param string      $provider_city_code City code in shipping service format
     * @param string      $address            Pickup point address
     * @param float       $longitude          Pickup point longitude
     * @param float       $latitude           Pickup point latitude
     * @param array       $extra_data         Whole pickup point data from shipping API
     * @param string|null $pickup_point_name  Pickup point name
     * @param string|null $city_name          City name
     * @param string|null $metro              Pickup point nearest station
     * @param string|null $work_time          Pickup point work time
     * @param string|null $phone              Pickup point phone number
     * @param string|null $notes              Pickup point notes
     * @param bool|null   $is_postamat        Whether pickup point is postamat or not
     *
     * @return PickupPoint
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function create(
        $pickup_point_code,
        $provider,
        $provider_city_code,
        $address,
        $longitude,
        $latitude,
        array $extra_data,
        $pickup_point_name = null,
        $city_name = null,
        $metro = null,
        $work_time = null,
        $phone = null,
        $notes = null,
        $is_postamat = null
    ) {
        $self = new self();

        $self->setPickupPointCode($pickup_point_code);
        $self->setProvider($provider);
        $self->setProviderCityCode($provider_city_code);
        $self->setAddress($address);
        $self->setLongitude($longitude);
        $self->setLatitude($latitude);
        $self->setExtraData($extra_data);
        $self->setPickupPointName($pickup_point_name);
        $self->setCityName($city_name);
        $self->setMetro($metro);
        $self->setWorkTime($work_time);
        $self->setPhone($phone);
        $self->setNotes($notes);
        $self->setIsPostamat($is_postamat);

        return $self;
    }

    /**
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function toArray()
    {
        return [
            'pickup_point_code'  => $this->pickup_point_code,
            'pickup_point_name'  => $this->pickup_point_name,
            'provider'           => $this->provider,
            'provider_city_code' => $this->provider_city_code,
            'city_name'          => $this->city_name,
            'address'            => $this->address,
            'metro'              => $this->metro,
            'work_time'          => $this->work_time,
            'phone'              => $this->phone,
            'latitude'           => $this->latitude,
            'longitude'          => $this->longitude,
            'notes'              => $this->notes,
            'is_postamat'        => $this->is_postamat,
            'extra_data'         => $this->extra_data,
        ];
    }

    /**
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function toArrayWithDefaults()
    {
        $data = $this->toArray();

        $data['pickup_point_name'] = $data['pickup_point_name'] ?? '';
        $data['city_name'] = $data['city_name'] ?? '';
        $data['metro'] = $data['metro'] ?? '';
        $data['work_time'] = $data['work_time'] ?? '';
        $data['phone'] = $data['phone'] ?? '';
        $data['notes'] = $data['notes'] ?? '';
        $data['is_postamat'] = $data['is_postamat'] ?? false;

        return $data;
    }

    /**
     * @param array $pickup_point Pickup point data as array
     *
     * @return PickupPoint|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function createFromArray(array $pickup_point)
    {
        $required = [
            'pickup_point_code',
            'provider',
            'provider_city_code',
            'address',
            'extra_data'
        ];

        foreach ($required as $field) {
            if (!isset($pickup_point[$field])) {
                return null;
            }
        }

        if (!is_array($pickup_point['extra_data'])) {
            return null;
        }

        $self = new self();

        $self->setPickupPointCode((string) $pickup_point['pickup_point_code']);
        $self->setProvider((string) $pickup_point['provider']);
        $self->setProviderCityCode((string) $pickup_point['provider_city_code']);
        $self->setAddress((string) $pickup_point['address']);
        $self->setExtraData($pickup_point['extra_data']);

        if (isset($pickup_point['pickup_point_name']) && is_string($pickup_point['pickup_point_name'])) {
            $self->setPickupPointName($pickup_point['pickup_point_name']);
        }

        if (isset($pickup_point['city_name']) && is_string($pickup_point['city_name'])) {
            $self->setCityName($pickup_point['city_name']);
        }

        if (isset($pickup_point['metro']) && is_string($pickup_point['metro'])) {
            $self->setMetro($pickup_point['metro']);
        }

        if (isset($pickup_point['work_time']) && is_string($pickup_point['work_time'])) {
            $self->setWorkTime($pickup_point['work_time']);
        }

        if (isset($pickup_point['phone']) && (is_string($pickup_point['phone']) || is_numeric($pickup_point['phone']))) {
            $self->setPhone((string) $pickup_point['phone']);
        }

        if (isset($pickup_point['notes']) && is_string($pickup_point['notes'])) {
            $self->setNotes($pickup_point['notes']);
        }

        if (isset($pickup_point['latitude']) && is_numeric($pickup_point['latitude'])) {
            $self->setLatitude((float) $pickup_point['latitude']);
        }

        if (isset($pickup_point['longitude']) && is_numeric($pickup_point['longitude'])) {
            $self->setLongitude((float) $pickup_point['longitude']);
        }

        if (isset($pickup_point['is_postamat'])) {
            $value = filter_var(
                $pickup_point['is_postamat'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            $self->setIsPostamat($value);
        }

        return $self;
    }
}
