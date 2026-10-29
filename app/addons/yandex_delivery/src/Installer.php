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

namespace Tygh\Addons\YandexDelivery;

use Tygh\Addons\InstallerInterface;
use Tygh\Core\ApplicationInterface;
use Tygh\Languages\Languages;

/**
 * Installs and uninstalls the Yandex Delivery add-on.
 */
class Installer implements InstallerInterface
{
    /**
     * @inheritDoc
     */
    public static function factory(ApplicationInterface $app)
    {
        return new self();
    }

    /**
     * @inheritDoc
     */
    public function onBeforeInstall()
    {
    }

    /**
     * @inheritDoc
     */
    public function onInstall()
    {
        $this->installShippingService();
    }

    /**
     * @inheritDoc
     */
    public function onUninstall()
    {
        $this->uninstallShippingService();
    }

    /**
     * Adds the Yandex Delivery shipping service.
     *
     * @return void
     */
    protected function installShippingService()
    {
        $service = [
            'status' => 'A',
            'module' => 'yandex_delivery',
            'code' => 'yandex_delivery',
            'sp_file' => '',
            'description' => 'Яндекс Доставка',
        ];

        $service['service_id'] = db_replace_into('shipping_services', $service);

        foreach (array_keys(Languages::getAll()) as $service['lang_code']) {
            db_replace_into('shipping_service_descriptions', $service);
        }
    }

    /**
     * Removes the Yandex Delivery shipping service.
     *
     * @return void
     */
    protected function uninstallShippingService()
    {
        $service_ids = db_get_fields('SELECT service_id FROM ?:shipping_services WHERE module = ?s', 'yandex_delivery');

        if (empty($service_ids)) {
            return;
        }

        db_query('DELETE FROM ?:shipping_services WHERE service_id IN (?a)', $service_ids);
        db_query('DELETE FROM ?:shipping_service_descriptions WHERE service_id IN (?a)', $service_ids);
    }
}
