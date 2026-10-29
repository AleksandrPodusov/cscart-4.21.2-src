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

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Tygh\Common\OperationResult;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\ProductTracking;
use Tygh\Enum\YesNo;
use Tygh\Helpdesk;
use Tygh\Http;
use Tygh\Mailer\Mailer;
use Tygh\Mailer\Transports\PhpMailerTransport;
use Tygh\Providers\HelpdeskProvider;
use Tygh\Registry;
use Tygh\Settings;

/**
 * Check if secure connection is available
 */
function fn_settings_actions_security_secure_storefront(&$new_value, $old_value)
{
    if ($new_value !== YesNo::NO) {
        $company_id = fn_get_runtime_company_id();

        if (!fn_allowed_for('ULTIMATE') || (fn_allowed_for('ULTIMATE') && $company_id)) {

            $suffix = '';
            if (fn_allowed_for('ULTIMATE')) {
                $suffix = '&company_id=' . $company_id;
            }

            $storefront_url = fn_url('index.index?check_https=Y' . $suffix, 'C', 'https');

            $content = Http::get($storefront_url);
            if (empty($content) || $content != 'OK') {
                // Disable https
                Settings::instance()->updateValue('secure_storefront', YesNo::NO, 'Security');
                $new_value = YesNo::NO;

                $error = Http::getErrorFields();
                $error_warning = __('warning_https_is_disabled', array(
                    '[href]' => Registry::get('config.resources.kb_https_failed_url'
                )));

                $error_warning .= fn_settings_actions_build_detailed_error_message($error);
                fn_set_notification('W', __('warning'), $error_warning);
            }
        }
    }
}

/**
 * Check if secure connection is available
 */
function fn_settings_actions_security_secure_admin(&$new_value, $old_value)
{
    if ($new_value !== YesNo::NO) {
        $suffix = '';
        if (fn_allowed_for('ULTIMATE')) {
            $suffix = '&company_id=' . Registry::get('runtime.company_id');
        }

        $admin_url = fn_url('index.index?check_https=Y' . $suffix, 'A', 'https');

        $content = Http::get($admin_url);

        if (empty($content) || $content != 'OK') {
            // Disable https
            Settings::instance()->updateValue('secure_admin', 'N', 'Security');
            $new_value = 'N';

            $error = Http::getErrorFields();
            $error_warning = __('warning_https_is_disabled', array(
                    '[href]' => Registry::get('config.resources.kb_https_failed_url'
                    )));

            $error_warning .= fn_settings_actions_build_detailed_error_message($error);
            fn_set_notification('W', __('warning'), $error_warning);
        }
    }
}

/**
 * Sets the correct value for the password validity period setting
 *
 * @param string|int $new_value New setting value
 *
 * @param-out int $new_value
 *
 * @return void
 */
function fn_settings_actions_security_account_password_expiration_period(&$new_value)
{
    if (!is_numeric($new_value)) {
        $new_value = 0;
    }
    $new_value = abs((int) $new_value);
}

/**
 * Alter order initial ID
 */
function fn_settings_actions_checkout_order_start_id(&$new_value, $old_value)
{
    $new_value = intval($new_value);
    if ($new_value > 0) {

        if ($new_value <= MAX_INITIAL_ORDER_ID) {
            db_query("ALTER TABLE ?:orders AUTO_INCREMENT = ?i", $new_value);

            return true;
        }
    }

    $new_value = $old_value;
    fn_set_notification('W', __('warning'), __('wrong_number_initial_order_id', array('[max_initial_order_id]' => MAX_INITIAL_ORDER_ID)));

    return false;
}

/**
 * Save empty value if has no checked check boxes
 */
function fn_settings_actions_general_search_objects(&$new_value, $old_value)
{
    if ($new_value == 'N') {
        $new_value = '';
    }
}

function fn_settings_actions_upgrade_center_license_number(&$new_value, &$old_value)
{
    if (empty($new_value)) {
        $new_value = $old_value;
        fn_set_notification(NotificationSeverity::ERROR, __('error'), __('license_number_cannot_be_empty'));

        return;
    }

    $old_mode = fn_get_storage_data('store_mode');

    list($license_status, , $new_mode) = Helpdesk::getStoreMode($new_value, Tygh::$app['session']['auth']);
    if ($license_status === 'ACTIVE' && $old_mode !== $new_mode) {
        fn_set_storage_data('store_mode', $new_mode, true);
        Tygh::$app['session']['mode_recheck'] = true;
    } elseif ($license_status !== 'ACTIVE') {
        $new_value = $old_value;
    }

    if ($license_status !== 'ACTIVE') {
        return;
    }

    call_user_func(HelpdeskProvider::getAccountConnectionReporter());
}

function fn_settings_actions_appearance_backend_default_language(&$new_value, &$old_value)
{
    if (fn_allowed_for('ULTIMATE')) {
        db_query("UPDATE ?:companies SET lang_code = ?s", $new_value);
    }
}

if (fn_allowed_for('ULTIMATE')) {
    function fn_settings_actions_stores_share_users(&$new_value, $old_value)
    {
        $emails = fn_get_double_user_emails();
        if (!empty($emails)) {
            fn_delete_notification('changes_saved');
            fn_set_notification('E', __('error'), __('ult_share_users_setting_disabled'));
            $new_value = $old_value;
        }
    }
}

function fn_settings_actions_appearance_notice_displaying_time(&$new_value, $old_value)
{
    $new_value = fn_convert_to_numeric($new_value);
}

function fn_settings_actions_build_detailed_error_message($error) {

    $detailed_message = "";

    if (!empty($error['error_number'])) {
        $transport_prefix = __('http_transport_error_prefix_' . $error['transport']);

        $detailed_message .= "<br/><strong>{$transport_prefix} {$error['error_number']}</strong>";

        if ($error['transport'] == 'curl') {
            $error_description_paragraph = __('curl_error_code_reference_link',
                array(
                    '[href]' => Registry::get('config.resources.curl_error_interpretation'
                    )));
            $detailed_message .= "<br/>" . $error_description_paragraph;

        }
    }
    return $detailed_message;
}

/**
 * Checks whether SMTP connection details are valid.
 *
 * @param string $host                 The "SMTP host" setting value
 * @param string $username             The "SMTP username" setting value
 * @param string $password             The "SMTP password" setting value
 * @param string $encrypted_connection The "Use Encrypted Connection" setting value
 * @param string $auth                 The "Use SMTP authentication" setting value
 *
 * @return \Tygh\Common\OperationResult
 */
function fn_validate_stmp_connection_details($host, $username, $password, $encrypted_connection, $auth)
{
    $transport_settings = [
        'mailer_send_method'              => 'smtp',
        'mailer_smtp_host'                => $host,
        'mailer_smtp_username'            => $username,
        'mailer_smtp_password'            => $password,
        'mailer_smtp_ecrypted_connection' => $encrypted_connection,
        'mailer_smtp_auth'                => $auth,
    ];

    /** @var \Tygh\Mailer\Mailer $mailer */
    $mailer = new Mailer(
        Tygh::$app['mailer.message_builder_factory'],
        Tygh::$app['mailer.transport_factory'],
        $transport_settings,
        false
    );

    $transport = $mailer->getTransport($transport_settings);

    $result = new OperationResult(false);
    if ($transport instanceof PhpMailerTransport) {
        try {
            $debug_output_handler = static function ($error_message) use ($result) {
                static $i = 0;
                if (strpos($error_message, 'SMTP ERROR: ') !== 0) {
                    return;
                }

                $error_message = substr_replace($error_message, '', 0, strlen('SMTP ERROR: '));
                $result->addError((string) $i++, $error_message);
            };
            $transport->SMTPDebug = 1;
            $transport->Debugoutput = $debug_output_handler;
            $result->setSuccess($transport->smtpConnect());
        } catch (PHPMailerException $e) {
            $result->addError('', $e->getMessage());
        }
    }

    return $result;
}

/**
 * Checks that new SMTP settings are valid and restores old setting values when the check fails.
 */
function fn_check_smtp_settings_and_restore_on_fail()
{
    $old_settings = Registry::ifGet('smtp_settings', []);
    /** @var array<string, string> $new_settings */
    $new_settings = Settings::instance()->getValues('Emails');

    $result = fn_validate_stmp_connection_details(
        $new_settings['mailer_smtp_host'],
        $new_settings['mailer_smtp_username'],
        $new_settings['mailer_smtp_password'],
        $new_settings['mailer_smtp_ecrypted_connection'],
        $new_settings['mailer_smtp_auth']
    );

    if ($result->isSuccess()) {
        return;
    }

    /**
     * The validation callback is executed in a shutdown function and session is shut down already.
     * We should start it to store notifications.
     */
    Tygh::$app['session']->start();

    fn_delete_notification('changes_saved');

    foreach ($result->getErrors() as $error_message) {
        fn_set_notification(NotificationSeverity::ERROR, __('settings.smtp_error'), $error_message);
    }
    foreach ($old_settings as $old_setting => $old_value) {
        Settings::instance()->updateValue($old_setting, $old_value, 'Emails', false, null, false);
    }

    Tygh::$app['session']->shutdown();
}

/**
 * Stores old setting value when the "SMTP host" setting value.
 *
 * @param string $new_value Old setting value
 * @param string $old_value New setting value
 */
function fn_settings_actions_emails_mailer_smtp_host($new_value, $old_value)
{
    Registry::isExist('smtp_settings') or register_shutdown_function('fn_check_smtp_settings_and_restore_on_fail');
    Registry::set('smtp_settings.mailer_smtp_host', $old_value);
}

/**
 * Stores old setting value when the "SMTP username" setting value.
 *
 * @param string $new_value Old setting value
 * @param string $old_value New setting value
 */
function fn_settings_actions_emails_mailer_smtp_username($new_value, $old_value)
{
    Registry::isExist('smtp_settings') or register_shutdown_function('fn_check_smtp_settings_and_restore_on_fail');
    Registry::set('smtp_settings.mailer_smtp_username', $old_value);
}

/**
 * Stores old setting value when the "SMTP password" setting value.
 *
 * @param string $new_value Old setting value
 * @param string $old_value New setting value
 */
function fn_settings_actions_emails_mailer_smtp_password($new_value, $old_value)
{
    Registry::isExist('smtp_settings') or register_shutdown_function('fn_check_smtp_settings_and_restore_on_fail');
    Registry::set('smtp_settings.mailer_smtp_password', $old_value);
}

/**
 * Stores old setting value when the "Use Encrypted Connection" setting value.
 *
 * @param string $new_value Old setting value
 * @param string $old_value New setting value
 */
function fn_settings_actions_emails_mailer_smtp_ecrypted_connection($new_value, $old_value)
{
    Registry::isExist('smtp_settings') or register_shutdown_function('fn_check_smtp_settings_and_restore_on_fail');
    Registry::set('smtp_settings.mailer_smtp_ecrypted_connection', $old_value);
}

/**
 * Stores old setting value when the "Use SMTP authentication" setting value.
 *
 * @param string $new_value Old setting value
 * @param string $old_value New setting value
 */
function fn_settings_actions_emails_mailer_smtp_auth($new_value, $old_value)
{
    Registry::isExist('smtp_settings') or register_shutdown_function('fn_check_smtp_settings_and_restore_on_fail');
    Registry::set('smtp_settings.mailer_smtp_auth', $old_value);
}

/**
 * For backward compatibility:
 *  - Saves inventory_tracking setting
 *
 * @param string              $new_value New setting value
 * @param string              $old_value Old setting value
 * @param \Tygh\Settings|null $instance  Instance of settings
 */
function fn_settings_actions_general_global_tracking($new_value, $old_value, $instance = null)
{
    if ($instance === null) {
        $instance = Settings::instance();
    }

    if (fn_allowed_for('ULTIMATE') && !$instance->getCompanyId()) {
        /** @var int|false $inventory_tracking_id */
        $inventory_tracking_id = $instance->getId('inventory_tracking', 'General');

        if ($inventory_tracking_id) {
            $instance->resetAllOverrides($inventory_tracking_id);
        }
    }

    if ($new_value === ProductTracking::TRACK || !isset($new_value)) {
        $instance->updateValue('inventory_tracking', YesNo::YES, 'General', false, null, false);
        return;
    }

    $instance->updateValue('inventory_tracking', YesNo::NO, 'General', false, null, false);
}

/**
 * For backward compatibility:
 *  - Saves default_product_details_view setting
 *
 * @param string              $new_value New setting value
 * @param string              $old_value Old setting value
 * @param \Tygh\Settings|null $instance  Instance of settings
 */
function fn_settings_actions_appearance_global_product_details_view($new_value, $old_value, $instance = null)
{
    if ($new_value === null) {
        return;
    }
    if ($instance === null) {
        $instance = Settings::instance();
    }

    if (
        fn_allowed_for('ULTIMATE') && !$instance->getCompanyId()
        || fn_allowed_for('MULTIVENDOR') && !$instance->getStorefrontId(null, null)
    ) {
        /** @var int|false $default_product_details_view_id */
        $default_product_details_view_id = $instance->getId('default_product_details_view', 'Appearance');

        if ($default_product_details_view_id) {
            $instance->resetAllOverrides($default_product_details_view_id);
        }
    }

    $instance->updateValue('default_product_details_view', $new_value, 'Appearance');
}

/**
 * Validates max qty settings with qty step setting
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_global_max_qty($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates min qty settings with qty step setting
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_global_min_qty($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates qty step settings with min/max qty settings.
 * default_qty_step follows global_qty_step during shutdown when global step is non-null (see fn_checkout_qty_mirror_global_qty_step_to_default).
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_global_qty_step($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates list qty count setting
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_global_list_qty_count($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates max qty settings with qty step setting
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_default_max_qty($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates min qty settings with qty step setting
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_default_min_qty($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates qty step settings with min/max qty settings
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_default_qty_step($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Validates list qty count setting
 *
 * @param string $new_value New value
 * @param string $old_value Old value
 *
 * @return void
 */
function fn_settings_actions_checkout_default_list_qty_count($new_value, $old_value)
{
    fn_checkout_qty_schedule_validate_shutdown();
}

/**
 * Registers shutdown handler for checkout qty normalization.
 *
 * @return void
 */
function fn_checkout_qty_schedule_validate_shutdown()
{
    static $registered = false;

    if ($registered) {
        return;
    }

    $registered = true;
    register_shutdown_function('fn_validate_qty_settings');
}

/**
 * Normalizes checkout qty settings at shutdown. Open/close session for notifications.
 *
 * @return void
 */
function fn_validate_qty_settings()
{
    $session = Tygh::$app['session'] ?? null;
    if ($session) {
        $session->start();
    }

    $settings = Settings::instance();
    $checkout_qty_snapshot = [
        'global_qty_step'  => $settings->getValue('global_qty_step', 'Checkout'),
        'default_qty_step' => $settings->getValue('default_qty_step', 'Checkout'),
    ];

    fn_checkout_qty_validate_settings_for_prefix('global_', $checkout_qty_snapshot);
    fn_checkout_qty_mirror_global_qty_step_to_default();
    fn_checkout_qty_validate_settings_for_prefix('default_', $checkout_qty_snapshot);

    if ($session) {
        $session->shutdown();
    }
}

/**
 * Copies normalized global_qty_step into default_qty_step when global step is set; leaves default unchanged if global is null.
 *
 * @return void
 */
function fn_checkout_qty_mirror_global_qty_step_to_default()
{
    $settings = Settings::instance();
    $global = $settings->getValue('global_qty_step', 'Checkout');

    if ($global === null || $global === false) {
        return;
    }

    $settings->updateValue('default_qty_step', (string) $global, 'Checkout', false, null, false);
}

/**
 * Normalizes list count, step, and min/max for one checkout scope.
 *
 * @param string                                    $prefix                Scope prefix (`global_` or `default_`).
 * @param array<string, string|int|float|null>|null $checkout_qty_snapshot Pre-shutdown step values; skips duplicate default qty_step notice when equal.
 *
 * @return void
 */
function fn_checkout_qty_validate_settings_for_prefix($prefix, $checkout_qty_snapshot = null)
{
    $settings = Settings::instance();

    $skip_duplicate_default_qty_step_notification = $checkout_qty_snapshot !== null
        && $prefix === 'default_'
        && ($checkout_qty_snapshot['global_qty_step'] ?? null) === ($checkout_qty_snapshot['default_qty_step'] ?? null);

    $list_qty_count = $settings->getValue($prefix . 'list_qty_count', 'Checkout');

    if (is_numeric($list_qty_count)) {
        $list_before = $list_qty_count;
        $new_list = $list_qty_count < 0 ? 0 : (int) ceil(abs((float) $list_qty_count));
        $settings->updateValue($prefix . 'list_qty_count', (string) $new_list, 'Checkout', false, null, false);
        if (fn_checkout_qty_is_non_integer_input($list_before)) {
            fn_checkout_qty_notify_invalid_integer_correction('list_qty_count', $list_before, $new_list);
        }
    } elseif ($list_qty_count !== null) {
        fn_checkout_qty_notify_invalid_integer_correction('list_qty_count', $list_qty_count, 0);
        $settings->updateValue($prefix . 'list_qty_count', '0', 'Checkout', false, null, false);
    }

    $qty_step_raw = $settings->getValue($prefix . 'qty_step', 'Checkout');

    if ($qty_step_raw === null) {
        foreach (['min_qty', 'max_qty'] as $suffix) {
            $name = $prefix . $suffix;
            $value = $settings->getValue($name, 'Checkout');

            if ($value === null) {
                continue;
            }

            if (!is_numeric($value)) {
                fn_checkout_qty_notify_invalid_integer_correction($suffix, $value, 0);
                $settings->updateValue($name, '0', 'Checkout', false, null, false);

                continue;
            }

            $as_float = (float) $value;
            if ((float) (int) $as_float === $as_float) {
                continue;
            }

            $truncated = (int) $as_float;
            fn_checkout_qty_notify_invalid_integer_correction($suffix, $value, $truncated);
            $settings->updateValue($name, (string) $truncated, 'Checkout', false, null, false);
        }

        return;
    }

    if (!is_numeric($qty_step_raw)) {
        $raw_step = $qty_step_raw;
        $settings->updateValue($prefix . 'qty_step', '0', 'Checkout', false, null, false);
        $qty_step = 0;
        if ((string) $raw_step !== '0' && !$skip_duplicate_default_qty_step_notification) {
            fn_checkout_qty_notify_invalid_integer_correction('qty_step', $raw_step, 0);
        }
    } else {
        $before_step = $qty_step_raw;
        $qty_step = (int) abs((float) $qty_step_raw);
        $settings->updateValue($prefix . 'qty_step', (string) $qty_step, 'Checkout', false, null, false);
        if (fn_checkout_qty_is_non_integer_input($before_step) && !$skip_duplicate_default_qty_step_notification) {
            fn_checkout_qty_notify_invalid_integer_correction('qty_step', $before_step, $qty_step);
        }
    }

    $old_min_qty_value = $settings->getValue($prefix . 'min_qty', 'Checkout');
    $old_max_qty_value = $settings->getValue($prefix . 'max_qty', 'Checkout');

    $correct_min_qty_value = $old_min_qty_value !== null
        ? fn_ceil_to_step((int) abs((float) $old_min_qty_value), $qty_step)
        : null;
    $correct_max_qty_value = $old_max_qty_value !== null
        ? fn_ceil_to_step((int) abs((float) $old_max_qty_value), $qty_step)
        : null;

    if ($correct_min_qty_value !== false && $correct_min_qty_value !== null) {
        $correct_min_qty_value = (int) $correct_min_qty_value;
    }
    if ($correct_max_qty_value !== false && $correct_max_qty_value !== null) {
        $correct_max_qty_value = (int) $correct_max_qty_value;
    }

    if (
        !empty($correct_min_qty_value)
        && !empty($correct_max_qty_value)
        && $correct_min_qty_value > $correct_max_qty_value
    ) {
        $correct_min_qty_value = 0;
    }

    if ($old_min_qty_value !== $correct_min_qty_value) {
        $new_min = (int) $correct_min_qty_value;
        if ($old_min_qty_value !== null && fn_checkout_qty_is_non_integer_input($old_min_qty_value)) {
            fn_checkout_qty_notify_invalid_integer_correction('min_qty', $old_min_qty_value, $new_min);
        } elseif (
            $qty_step > 0
            && $correct_min_qty_value !== false
            && $correct_min_qty_value !== null
            && $old_min_qty_value !== null
            && is_numeric($old_min_qty_value)
            && !fn_checkout_qty_is_non_integer_input($old_min_qty_value)
            && (int) abs((float) $old_min_qty_value) !== $correct_min_qty_value
            && $new_min === $correct_min_qty_value
        ) {
            fn_checkout_qty_notify_step_alignment('min_qty', $old_min_qty_value, $new_min, $qty_step);
        }
        $settings->updateValue($prefix . 'min_qty', (string) $new_min, 'Checkout', false, null, false);
    }

    if ($old_max_qty_value !== $correct_max_qty_value) {
        $new_max = (int) $correct_max_qty_value;
        if ($old_max_qty_value !== null && fn_checkout_qty_is_non_integer_input($old_max_qty_value)) {
            fn_checkout_qty_notify_invalid_integer_correction('max_qty', $old_max_qty_value, $new_max);
        } elseif (
            $qty_step > 0
            && $correct_max_qty_value !== false
            && $correct_max_qty_value !== null
            && $old_max_qty_value !== null
            && is_numeric($old_max_qty_value)
            && !fn_checkout_qty_is_non_integer_input($old_max_qty_value)
            && (int) abs((float) $old_max_qty_value) !== $correct_max_qty_value
            && $new_max === $correct_max_qty_value
        ) {
            fn_checkout_qty_notify_step_alignment('max_qty', $old_max_qty_value, $new_max, $qty_step);
        }
        $settings->updateValue($prefix . 'max_qty', (string) $new_max, 'Checkout', false, null, false);
    }
}

/**
 * True when the value is a non-numeric string or has a fractional part.
 *
 * @param string|int|float|null $value Raw value from settings storage.
 *
 * @return bool
 */
function fn_checkout_qty_is_non_integer_input($value)
{
    if ($value === null) {
        return false;
    }

    if (!is_numeric($value)) {
        return true;
    }

    $as_float = (float) $value;

    return (float) (int) $as_float !== $as_float;
}

/**
 * Fetches human-readable checkout option title for admin notifications.
 *
 * @param string $field Option suffix: `min_qty`, `max_qty`, `qty_step`, `list_qty_count`.
 *
 * @return string
 */
function fn_checkout_qty_default_checkout_setting_title($field)
{
    $settings = Settings::instance();
    $object_id = $settings->getId('default_' . $field, 'Checkout');
    $title = is_int($object_id)
        ? $settings->getDescription($object_id, Settings::SETTING_DESCRIPTION)
        : '';

    if ($title === false || $title === null || $title === '') {
        return 'default_' . $field;
    }

    return $title;
}

/**
 * Next unique notification area id for checkout qty admin notices.
 *
 * @return string
 */
function fn_checkout_qty_notification_uid()
{
    static $index = 0;

    return 'checkout_qty_notify_' . $index++;
}

/**
 * Admin warning after coercing non-integer checkout qty input.
 *
 * @param string                $field Option suffix (`min_qty`, `max_qty`, `qty_step`, `list_qty_count`).
 * @param string|int|float|null $from  Value before correction.
 * @param string|int|float|null $to    Value after correction.
 *
 * @return void
 */
function fn_checkout_qty_notify_invalid_integer_correction($field, $from, $to)
{
    if (empty(Tygh::$app['session']) || !Tygh::$app['session']->isStarted()) {
        return;
    }

    fn_set_notification(
        NotificationSeverity::WARNING,
        __('warning'),
        __('checkout.qty_adjusted_must_be_integer', [
            '[setting]' => fn_checkout_qty_default_checkout_setting_title($field),
            '[from]'    => (string) $from,
            '[to]'      => (string) $to,
        ]),
        'K',
        fn_checkout_qty_notification_uid()
    );
}

/**
 * Admin warning when min/max were aligned to the quantity step.
 *
 * @param string                $field    Field `min_qty` or `max_qty`.
 * @param string|int|float|null $from     Value before correction.
 * @param int                   $to       Value after correction.
 * @param int                   $qty_step Current quantity step.
 *
 * @return void
 */
function fn_checkout_qty_notify_step_alignment($field, $from, $to, $qty_step)
{
    if (empty(Tygh::$app['session']) || !Tygh::$app['session']->isStarted()) {
        return;
    }

    fn_set_notification(
        NotificationSeverity::WARNING,
        __('warning'),
        __('checkout.qty_adjusted_for_quantity_step', [
            '[setting]' => fn_checkout_qty_default_checkout_setting_title($field),
            '[from]'    => (string) $from,
            '[to]'      => (string) $to,
            '[step]'    => (string) $qty_step,
        ]),
        'K',
        fn_checkout_qty_notification_uid()
    );
}

/**
 * Checks whether image file size setting value is greater than server 'upload_max_filesize' value.
 *
 * @param int|float $new_value New image file size value
 *
 * @return void
 */
function fn_settings_actions_thumbnails_image_file_size(&$new_value)
{
    $server_max_filesize = fn_get_allowed_server_image_file_size(true);

    if ($new_value <= $server_max_filesize) {
        return;
    }

    $new_value = $server_max_filesize;
    fn_set_notification(
        NotificationSeverity::WARNING,
        __('warning'),
        __('image_file_size_set_to_server_setting', ['[file_size]' => $new_value])
    );
}
