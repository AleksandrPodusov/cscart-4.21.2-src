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

namespace Tygh\Shippings\PickupPoints\Factories;

use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;

/**
 * Builds CS-Cart compatible shipping_info array from pickup points lookup context.
 *
 * This factory is used when pickup points logic requires interaction with
 * standard CS-Cart shipping calculation and validation pipeline.
 *
 * @package Tygh\Shippings\PickupPoints\Factory
 */
class ShippingInfoFactory
{
    /**
     * Builds shipping_info array from lookup context.
     *
     * @param PickupPointsLookupContext $context Lookup context
     *
     * @return array Shipping info in CS-Cart format
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public static function buildFromContext(PickupPointsLookupContext $context): array
    {
        $shipping_info = $context->getShippingInfo();

        if ($shipping_info === null) {
            $shipping_info = fn_get_shipping_info($context->getShippingId());
        }

        $package_info = self::buildPackageInfo($context);

        $shipping_info['package_info'] = $package_info;

        return $shipping_info;
    }

    /**
     * Builds package_info array from lookup context.
     *
     * @param PickupPointsLookupContext $context Lookup context
     *
     * @return array Package info in CS-Cart format
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected static function buildPackageInfo(PickupPointsLookupContext $context): array
    {
        $summary_package = $context->getSummaryPackage();
        $package_info = $summary_package->toPackageInfoArray();

        $package_info['location'] = $context->getLocation()->toArray();

        $origin = $context->getOrigin();
        if ($origin !== null) {
            $package_info['origination'] = $origin->toArray();
        }

        return $package_info;
    }
}
