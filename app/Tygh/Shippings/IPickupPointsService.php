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

namespace Tygh\Shippings;

/**
 * Shipping pickup services advanced interface
 */
interface IPickupPointsService
{
    /**
     * Checks if pickup points are supported by the shipping method settings.
     *
     * - Return true by default when all shipping methods provided by the service
     * support pickup points.
     * - When pickup points are not supported for specific method settings,
     * for example by tariff or delivery mode, return false.
     *
     * @param array $shipping_info Shipping method data
     *
     * @return bool
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     */
    public function isPickupPointsSupported(array $shipping_info);

    /**
     * Gets minimal cost among available pickup points
     *
     * @return float|false
     */
    public function getPickupMinCost();

    /**
     * Gets pickup points quantity
     *
     * @return int|false
     */
    public function getPickupPointsQuantity();

    /**
     * @return \Tygh\Shippings\PickupPoints\LoaderByLocation\IPickupPointsLoaderByLocation
     */
    public function getPickupPointsLoader();
}
