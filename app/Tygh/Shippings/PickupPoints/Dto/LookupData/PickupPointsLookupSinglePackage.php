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
 * Class provides package for pickup points lookup.
 *
 * @package Tygh\Shippings\PickupPoints\Location
 */
class PickupPointsLookupSinglePackage
{
    /** @var float|null Weight in grams */
    protected $weight;

    /** @var float|null Package cost */
    protected $cost;

    /** @var int|null Items count inside the package */
    protected $items_count;

    /** @var int|null Box length (cm) */
    protected $box_length;

    /** @var int|null Box width (cm) */
    protected $box_width;

    /** @var int|null Box height (cm) */
    protected $box_height;

    /** @var array<int, int>|null Products list: cart_id => quantity */
    protected $products;

    /**
     * PickupPointsLookupSinglePackage constructor.
     *
     * @param float|null           $weight      Weight in grams
     * @param float|null           $cost        Package cost
     * @param int|null             $items_count Items count
     * @param int|null             $box_length  Box length (cm)
     * @param int|null             $box_width   Box width (cm)
     * @param int|null             $box_height  Box height (cm)
     * @param array<int, int>|null $products    Products list (cart_id => quantity)
     */
    public function __construct(
        $weight = null,
        $cost = null,
        $items_count = null,
        $box_length = null,
        $box_width = null,
        $box_height = null,
        array $products = null
    ) {
        $this->weight = $weight;
        $this->cost = $cost;
        $this->items_count = $items_count;
        $this->box_length = $box_length;
        $this->box_width = $box_width;
        $this->box_height = $box_height;
        $this->products = $products;
    }

    /**
     * Gets package weight.
     *
     * @return float|null Weight in grams
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Gets package cost.
     *
     * @return float|null
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     * Gets items count inside the package.
     *
     * @return int|null
     */
    public function getItemsCount()
    {
        return $this->items_count;
    }

    /**
     * Gets box length.
     *
     * @return int|null Length in cm
     */
    public function getBoxLength()
    {
        return $this->box_length;
    }

    /**
     * Gets box width.
     *
     * @return int|null Width in cm
     */
    public function getBoxWidth()
    {
        return $this->box_width;
    }

    /**
     * Gets box height.
     *
     * @return int|null Height in cm
     */
    public function getBoxHeight()
    {
        return $this->box_height;
    }

    /**
     * Gets products list.
     *
     * @return array<int, int>|null cart_id => quantity
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * Converts package to array compatible with package_info['packages'][].
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function toArray()
    {
        return [
            'products' => $this->products,
            'weight' => $this->weight,
            'cost'   => $this->cost,
            'amount' => $this->items_count,
            'shipping_params' => [
                'box_length' => $this->box_length,
                'box_width'  => $this->box_width,
                'box_height' => $this->box_height,
            ],
        ];
    }
}
