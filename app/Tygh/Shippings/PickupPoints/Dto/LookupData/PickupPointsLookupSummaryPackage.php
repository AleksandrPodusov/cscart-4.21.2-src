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

use Tygh\Registry;

/**
 * Class provides package for pickup points lookup.
 *
 * @package Tygh\Shippings\PickupPoints\Location
 */
class PickupPointsLookupSummaryPackage
{
    /** @var float|null Total shipment cost */
    protected $total_cost;

    /** @var float|null Total weight in grams */
    protected $total_weight;

    /** @var int|null Total items count */
    protected $items_count;

    /** @var float|null Shipping freight */
    protected $shipping_freight;

    /** @var PickupPointsLookupSinglePackage[]|null Array of packages for pickup points lookup */
    protected $packages;

    /**
     * PickupPointsLookupSummaryPackage constructor.
     *
     * @param float|null                             $total_cost       Total shipment cost
     * @param float|null                             $total_weight     Weight in grams
     * @param int|null                               $items_count      Total items count
     * @param float|null                             $shipping_freight Shipping freight
     * @param PickupPointsLookupSinglePackage[]|null $packages         Array of packages for pickup points lookup
     */
    private function __construct(
        $total_cost = null,
        $total_weight = null,
        $items_count = null,
        $shipping_freight = null,
        $packages = null
    ) {
        $this->total_cost = $total_cost;
        $this->total_weight = $total_weight;
        $this->items_count = $items_count;
        $this->shipping_freight = $shipping_freight;
        $this->packages = $packages;
    }

    /**
     * Creates DTO from CS-Cart package_info array.
     *
     * @param array $package_info Package info
     *
     * @return self
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function fromPackageInfo(array $package_info)
    {
        $symbol_grams = (float) Registry::get('settings.General.weight_symbol_grams');

        $weight_data = fn_convert_weight_to_imperial_units($package_info['W'] ?? 0);
        $total_weight = !empty($weight_data['plain'])
            ? $weight_data['plain'] * $symbol_grams
            : null;

        $packages = [];

        foreach ($package_info['packages'] ?? [] as $package) {
            $package_weight_data = fn_convert_weight_to_imperial_units($package['weight'] ?? 0);
            $package_weight = !empty($package_weight_data['plain'])
                ? $package_weight_data['plain'] * $symbol_grams
                : null;

            $packages[] = new PickupPointsLookupSinglePackage(
                $package_weight,
                $package['cost'] ?? null,
                $package['amount'] ?? null,
                $package['shipping_params']['box_length'] ?? null,
                $package['shipping_params']['box_width'] ?? null,
                $package['shipping_params']['box_height'] ?? null
            );
        }

        return new self(
            $package_info['C'] ?? null,
            $total_weight,
            $package_info['I'] ?? null,
            $package_info['shipping_freight'] ?? null,
            $packages
        );
    }

    /**
     * Creates summary package from manual API data.
     *
     * @param array $data Manual data for summary package (for example from API)
     *
     * @return self
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function fromArray(array $data)
    {
        // TODO: Add dimensions
        return new self(
            $data['cost'] ?? null,
            $data['weight'] ?? null,
            $data['items'] ?? null,
            $data['shipping_freight'] ?? null,
            $data['packages'] ?? null
        );
    }

    /**
     * Converts DTO back to CS-Cart package_info format.
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function toPackageInfoArray()
    {
        $result = [
            'C' => $this->total_cost,
            'W' => $this->total_weight,
            'I' => $this->items_count,
            'shipping_freight' => $this->shipping_freight,
        ];

        if (!empty($this->packages)) {
            $result['packages'] = [];

            foreach ($this->packages as $package) {
                $result['packages'][] = $package->toArray();
            }
        }

        return $result;
    }

    /**
     * Gets total shipment cost.
     *
     * Corresponds to package_info['C'].
     *
     * @return float|null Total cost
     */
    public function getTotalCost()
    {
        return $this->total_cost;
    }

    /**
     * Gets total shipment weight in grams.
     *
     * Corresponds to package_info['W'] converted to grams.
     *
     * @return float|null Weight in grams
     */
    public function getTotalWeight()
    {
        return $this->total_weight;
    }

    /**
     * Gets total number of items in shipment.
     *
     * Corresponds to package_info['I'].
     *
     * @return int|null Items count
     */
    public function getItemsCount()
    {
        return $this->items_count;
    }

    /**
     * Gets shipping freight amount.
     *
     * Corresponds to package_info['shipping_freight'].
     *
     * @return float|null Shipping freight
     */
    public function getShippingFreight()
    {
        return $this->shipping_freight;
    }

    /**
     * Checks whether package contains split packages.
     *
     * Corresponds to presence of package_info['packages'].
     *
     * @return bool
     */
    public function hasPackages()
    {
        return !empty($this->packages);
    }

    /**
     * Gets original split packages.
     *
     * Each package corresponds to one box from package_info['packages'].
     *
     * @return PickupPointsLookupSinglePackage[]|null
     */
    public function getPackages()
    {
        return $this->packages;
    }
}
