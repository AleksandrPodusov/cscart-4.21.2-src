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
 * Universal lookup context for pickup points search.
 *
 * @package Tygh\Shippings\PickupPoints\LookupData
 */
class PickupPointsLookupContext
{
    /** @var int $shipping_id Shipping identifier */
    protected $shipping_id;

    /** @var int $company_id Company identifier */
    protected $company_id;

    /** @var PickupPointsLookupLocation Location data */
    protected $location;

    /** @var PickupPointsLookupSummaryPackage $summary_package Summary package info */
    protected $summary_package;

    /** @var PickupPointsLookupOrigin|null Origin data */
    protected $origin = null;

    /**
     * @var array|null $shipping_info Static shipping data from fn_get_shipping_info()
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingTraversableTypeHintSpecification
     */
    protected $shipping_info = null;

    /**
     * @var bool Whether the caller reads the carrier payload of the points it asked for
     */
    protected $is_extra_data_required = true;

    /**
     * @param int                              $shipping_id     Shipping identifier
     * @param int                              $company_id      Company identifier
     * @param PickupPointsLookupLocation       $location        Location data
     * @param PickupPointsLookupSummaryPackage $summary_package Summary package
     * @param PickupPointsLookupOrigin|null    $origin          Origin data
     * @param array|null                       $shipping_info   Static shipping data from fn_get_shipping_info()
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function __construct(
        $shipping_id,
        $company_id,
        PickupPointsLookupLocation $location,
        PickupPointsLookupSummaryPackage $summary_package,
        $origin = null,
        $shipping_info = null
    ) {
        $this->shipping_id     = $shipping_id;
        $this->company_id      = $company_id;
        $this->location        = $location;
        $this->summary_package = $summary_package;
        $this->origin          = $origin;
        $this->shipping_info   = $shipping_info;
    }

    /**
     * Gets shipping identifier.
     *
     * @return int
     */
    public function getShippingId()
    {
        return $this->shipping_id;
    }

    /**
     * Gets company (vendor) identifier.
     *
     * @return int
     */
    public function getCompanyId()
    {
        return $this->company_id;
    }

    /**
     * Gets customer location.
     *
     * @return PickupPointsLookupLocation
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Tells whether the carrier payload of every point is going to be read.
     *
     * A list that is only shown to the customer needs the address, the phone and the opening hours, all of which
     * are columns of their own. The payload is what the carrier sent about the point, and in a city with
     * thousands of points reading it for all of them costs more memory than the rest of the checkout together.
     *
     * @return bool
     */
    public function isExtraDataRequired()
    {
        return $this->is_extra_data_required;
    }

    /**
     * Says whether the carrier payload of every point is going to be read.
     *
     * @param bool $is_extra_data_required Whether the payload is needed
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function setExtraDataRequired($is_extra_data_required)
    {
        $this->is_extra_data_required = $is_extra_data_required;
    }

    /**
     * Gets shipment origination point.
     *
     * @return PickupPointsLookupOrigin|null
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * Gets summary package info.
     *
     * @return PickupPointsLookupSummaryPackage
     */
    public function getSummaryPackage()
    {
        return $this->summary_package;
    }

    /**
     * Gets static shipping info (from fn_get_shipping_info).
     * May be null if not requested or not needed.
     *
     * @return array|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function getShippingInfo()
    {
        return $this->shipping_info;
    }

    /**
     * Sets static shipping info.
     *
     * @param array $shipping_info Static shipping data from fn_get_shipping_info()
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function setShippingInfo(array $shipping_info)
    {
        $this->shipping_info = $shipping_info;
    }

    /**
     * Creates lookup context from package_info array.
     *
     * @param int   $shipping_id  Shipping identifier
     * @param int   $company_id   Company identifier
     * @param array $package_info Data from package_info array (see \Tygh\Shippings\Shippings::_getPackageInfo())
     *
     * @return self
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function fromPackageInfo($shipping_id, $company_id, array $package_info)
    {
        return new self(
            $shipping_id,
            $company_id,
            PickupPointsLookupLocation::createFromArray($package_info['location'] ?? []),
            PickupPointsLookupSummaryPackage::fromPackageInfo($package_info),
            PickupPointsLookupOrigin::createFromArray($package_info['origination'] ?? [])
        );
    }

    /**
     * Creates lookup context from manual data (for example from API request).
     *
     * @param int        $shipping_id   Shipping identifier
     * @param int        $company_id    Company identifier
     * @param array      $location_data Location data (country, state, city, zipcode or coordinates expected)
     * @param array      $package_data  Package data (weight in grams, width, length, height expected)
     * @param array|null $origin_data   Origin data (country, state, city, zipcode expected)
     *
     * @return self
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function fromManualData(
        $shipping_id,
        $company_id,
        array $location_data,
        array $package_data = [],
        array $origin_data = null
    ) {
        $location = PickupPointsLookupLocation::createFromArray($location_data);

        $origin = null;

        if ($origin_data !== null) {
            $origin = PickupPointsLookupOrigin::createFromArray($origin_data);
        }

        $summary_package = PickupPointsLookupSummaryPackage::fromArray($package_data);

        return new self(
            $shipping_id,
            $company_id,
            $location,
            $summary_package,
            $origin ?? null
        );
    }

    /**
     * Converts lookup context back to CS-Cart package_info format.
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function toPackageInfoArray()
    {
        $result = [];

        if ($this->summary_package !== null) {
            $result = $this->summary_package->toPackageInfoArray();
        }

        if ($this->location !== null) {
            $result['location'] = $this->location->toArray();
        }

        if ($this->origin !== null) {
            $result['origination'] = $this->origin->toArray();
        }

        return $result;
    }
}
