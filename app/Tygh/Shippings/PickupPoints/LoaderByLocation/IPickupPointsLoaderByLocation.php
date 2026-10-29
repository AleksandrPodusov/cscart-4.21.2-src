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

namespace Tygh\Shippings\PickupPoints\LoaderByLocation;

use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;

interface IPickupPointsLoaderByLocation
{
    /**
     * @param PickupPointsLookupContext $lookup_context Context data for pickup points lookup
     *
     * @return \Tygh\Shippings\PickupPoints\Dto\PickupPoint[]|array
     */
    public function getPickupPointsByLocation(PickupPointsLookupContext $lookup_context);
}
