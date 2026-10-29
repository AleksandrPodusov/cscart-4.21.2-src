<?php
    if (!defined('INSTALLER_INITED')) { die('Access denied'); }

use Installer\App;
use Tygh\Registry;

require $_tpl_vars['dir']['root'] . '/install/design/templates/common/header.php';
?>

<div id="content">
    <?php echo $this->getNotificationsHtmlCode();?>

    <h3><?php echo $this->t('installation'); ?></h3>
    <p class="muted"><?php echo $this->t('installation_instructions'); ?></p>

    <?php if (!empty($_tpl_vars['show_requirements_section'])): ?>
        <form name="setup_step_form" method="post" action="index.php" class="form-horizontal cm-ajax cm-comet">

        <h4><?php echo $this->t('checking_requirements'); ?></h4>
        <?php
            echo $this->getNotificationsHtmlCode('server_requirements');
        ?>

        <?php
            $status = false;
            foreach ($_tpl_vars['checking_result'] as $key => $value) {
                if ($key !== 'file_system_writable' && empty($value)) {
                    $status = true;
                    break;
                }
            }
        ?>

        <?php if ($status || $_tpl_vars['extensions']): ?>
            <div class="settings checking-result">
                <?php foreach ($_tpl_vars['extensions'] as $validator_id => $validator): ?>
                    <?php
                    /** @var \Installer\Requirements\ValidatorInteface $validator */
                    $errors = $validator->getErrors();
                    $warnings = $validator->getWarnings();
                    $extensions = $validator->getRequirements();
                    ?>
                    <div class="requirement">

                        <?php if ($errors): ?>
                            <span class="label label-important pull-right"><?php echo $this->t('extensions.required'); ?></span>
                        <?php else: ?>
                            <span class="label label-info pull-right"><?php echo $this->t('extensions.optional'); ?></span>
                        <?php endif; ?>

                        <strong><?php echo $this->t("extensions.{$validator_id}"); ?></strong>

                        <?php foreach ($errors as $error_code): ?>
                            <p class="summary summary--error">
                                <?php echo $this->t("extensions.{$validator_id}.error.{$error_code}"); ?>
                            </p>
                        <?php endforeach; ?>

                        <?php foreach ($warnings as $warning_code): ?>
                            <p class="summary summary--warning">
                                <?php echo $this->t("extensions.{$validator_id}.warning.{$warning_code}"); ?>
                            </p>
                        <?php endforeach; ?>

                        <?php if (in_array($validator::EXTENSION_MISSING, $errors) || in_array($validator::EXTENSION_MISSING, $warnings)): ?>
                            <p class="summary summary--extensions">
                                <?php
                                if (count($extensions) > 1) {
                                    if ($validator->getRequirementsMode() === $validator::REQUIRE_ALL) {
                                        echo $this->t("extensions.install_all");
                                    } else {
                                        echo $this->t("extensions.install_any");
                                    }
                                } else {
                                    echo $this->t("extensions.install_one");
                                }
                                ?>
                                <?php foreach ($extensions as $ext): ?>
                                    <code class="extension"><?php echo $ext; ?></code>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$_tpl_vars['checking_result']['session_started']): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('could_not_start_session'); ?></strong><br>
                        <p class="summary"><?php echo $this->t('text_could_not_start_session'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($_tpl_vars['checking_result']['file_upload'])): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('file_uploads'); ?></strong><br>
                        <p class="summary"><?php echo $this->t('file_uploads_must_be_enabled'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($_tpl_vars['checking_result']['safe_mode'])): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('safe_mode_enabled'); ?></strong><br>
                        <p class="summary"><?php echo $this->t('safe_mode_must_be_disabled'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($_tpl_vars['checking_result']['php_version_supported'])): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('php_version_not_support'); ?></strong><br>
                        <p class="summary"><?php echo $this->t('text_php_version_not_support'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($_tpl_vars['checking_result']['register_globals_disabled'])): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('text_register_globals_notice'); ?></strong><br>
                    </div>
                <?php endif; ?>

                <?php if (empty($_tpl_vars['checking_result']['session_auto_start'])): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('text_session_auto_start_notice'); ?></strong><br>
                    </div>
                <?php endif; ?>

                <?php if (empty($_tpl_vars['checking_result']['func_overload_acceptable'])): ?>
                    <div class="requirement">
                        <span class="label label-important pull-right"><?php echo $this->t('fail'); ?></span>
                        <strong><?php echo $this->t('text_func_overload_notice'); ?></strong><br>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
            echo $this->getNotificationsHtmlCode('file_permissions_section');
            $notifications = $this->getNotifications('file_permissions');
        ?>
        <?php if (!empty($notifications)): ?>
            <div class="settings">
                <?php foreach ($notifications as $notification): ?>
                    <?php echo $notification['message']; ?>
                    <br>
                <?php endforeach; ?>
            </div>

            <a class="btn cm-no-ajax cm-dialog-opener cm-dialog-auto-size"
               id="opener_correct_permissions"
               data-ca-target-id="correct_permissions"
            ><?php echo $this->t('correct_permissions'); ?></a>
        <?php endif; ?>

        <br><br>

        <?php if ($status): ?>
            <div class="modal-footer">
                <input type="submit" class="btn btn-primary pull-left cm-no-ajax" name="dispatch[setup.recheck]" value="<?php echo $this->t('recheck'); ?>">
            </div>
        <?php endif; ?>

        </form>
    <?php endif; ?>

<div class="hidden" id="correct_permissions" title="<?php echo $this->t('title_ftp_options'); ?>">
    <form name="correct_permissions_2" action="index.php" class="cm-ajax form-horizontal" method="post">
        <div class="settings">
            <div class="requirement">
                <div class="control-group">
                    <label class="control-label cm-required" for="correct_permissions_hostname"><?php echo $this->t('correct_permissions_hostname'); ?>:</label>
                    <div class="controls">
                        <input type="text" name="ftp_settings[ftp_hostname]" id="correct_permissions_hostname" placeholder="<?php echo $this->t('correct_permissions_placeholder'); ?>" value="<?php echo $this->prepareVar($_tpl_vars, 'ftp_settings.ftp_hostname'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label cm-required" for="correct_permissions_username"><?php echo $this->t('correct_permissions_username'); ?>:</label>
                    <div class="controls">
                        <input type="text" name="ftp_settings[ftp_username]" id="correct_permissions_username" value="<?php echo $this->prepareVar($_tpl_vars, 'ftp_settings.ftp_username'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label for="correct_permissions_pass" class="control-label cm-required"><?php echo $this->t('correct_permissions_pass'); ?>:</label>
                    <div class="controls">
                        <div class="input-append" data-ca-input-password="inputGroup">
                            <input type="password" id="correct_permissions_pass" name="ftp_settings[ftp_password]" value="<?php echo $this->prepareVar($_tpl_vars, 'ftp_settings.ftp_password'); ?>" class="cs-input-password__input" data-ca-input-password="input" />
                            <button type="button" class="btn cs-input-password__toggle-button " data-ca-input-password="toggleButton">
                                <span class="cs-icon cs-icon--type-eye-close cs-input-password__eye-close-icon " data-ca-input-password="eyeCloseIcon">
                                    <span class="cs-icon__hidden-accessible"></span>
                                    <svg fill="currentColor" class="cs-icon__svg" focusable="false" aria-hidden="true" viewBox="0 0 20 20">
                                        <path d="m1.36431.302983c-.29289-.2928933-.767763-.2928933-1.060656 0-.2928929.292893-.2928929.767767 0 1.060657l3.626676 3.62668c-1.52988 1.30212-2.80494 2.88041-3.757212 4.65506-.1152257.21473-.1189377.47202-.009954.69002l.67082-.3354c-.67082.3354-.670207.3366-.670207.3366l.000923.0019.002771.0055.009241.018c.007801.0152.018884.0366.033223.0637.028674.0541.070402.1314.12499.2285.10912.1939.269963.4676.481009.7944.421166.6521 1.047136 1.5235 1.866726 2.3977 1.62552 1.7339 4.10169 3.5705 7.31804 3.5705l.0122-.0002c1.7306-.0283 3.413-.5472 4.8541-1.4896l3.77 3.77c.2929.2929.7678.2929 1.0606 0 .2929-.2929.2929-.7678 0-1.0607l-4.2099-4.2099c-.0048-.005-.0098-.01-.0148-.0148l-9.8852-9.88523c-.00486-.005-.00978-.00991-.01475-.01475zm3.63046 5.751767c-1.32587 1.10976-2.44501 2.44633-3.30479 3.94795.09397.1638.22299.3797.38601.6321.38613.5979.95808 1.3931 1.70098 2.1856 1.49832 1.5982 3.60336 3.0939 6.21756 3.0963 1.33877-.023 2.64277-.3971 3.78407-1.0781l-2.0583-2.0583c-.1458.0955-.2993.1794-.4593.2507-.3986.1776-.829.2732-1.26538.2809s-.86984-.0726-1.27452-.2361c-.40468-.1634-.77229-.4067-1.08091-.7153-.30861-.3087-.5519-.6763-.71536-1.081-.16346-.4046-.24373-.8381-.23603-1.2745.0077-.43635.10321-.86671.28084-1.26537.07128-.15997.15519-.31351.25074-.45926zm3.33002 3.33003c-.08603.20485-.1323.42433-.13623.64672-.00414.235.03908.4684.1271.6863.08801.2179.21902.4158.38519.582.16618.1662.36413.2972.58203.3852.21791.088.45131.1312.68628.1271.22234-.0039.44184-.0502.64674-.1362z" fill-rule="evenodd"></path>
                                        <path d="m8.42159 4.26359c.517-.12101 1.04632-.18151 1.5773-.18026h.00181c2.617 0 4.7241 1.49684 6.2236 3.09629.7429.79243 1.3149 1.58773 1.701 2.18561.1633.25277.2924.46889.3864.63277-.4391.77-.9472 1.4988-1.5182 2.1774-.2667.317-.226.7901.0909 1.0568.317.2667.7901.226 1.0568-.091.7245-.861 1.3571-1.7953 1.8876-2.7876.1146-.2146.1181-.47142.0093-.68901-.039.01951-.0793.03572-.1207.04917l.1201-.05039c-.015-.02987-.0305-.05948-.0461-.08903-.0287-.05419-.0704-.13145-.125-.22849-.1091-.19399-.27-.46763-.481-.79441-.4212-.65212-1.0472-1.52349-1.8668-2.39773-1.6253-1.73372-4.1012-3.57004-7.3171-3.57038-.64693-.00145-1.29186.07229-1.92178.21974-.40332.09441-.65374.49789-.55933.9012.0944.40331.49788.65373.9012.55932z"></path>
                                    </svg>
                                </span>
                                <span class="cs-icon cs-icon--type-eye-open cs-input-password__eye-open-icon force-hidden" data-ca-input-password="eyeOpenIcon">
                                    <span class="cs-icon__hidden-accessible"></span>
                                    <svg fill="currentColor" class="cs-icon__svg" focusable="false" aria-hidden="true" viewBox="0 0 20 20">
                                        <path d="m10 6.75c-1.79493 0-3.25 1.45507-3.25 3.25 0 1.7949 1.45507 3.25 3.25 3.25 1.7949 0 3.25-1.4551 3.25-3.25 0-1.79493-1.4551-3.25-3.25-3.25zm-1.75 3.25c0-.9665.7835-1.75 1.75-1.75s1.75.7835 1.75 1.75-.7835 1.75-1.75 1.75-1.75-.7835-1.75-1.75z"></path>
                                        <path d="m19.8379 9.66403c.1055.21115.1058.46017.0002.67137l-.6708-.33542c.6708.33542.6702.33662.6702.33662l-.0009.0018-.0028.0055-.0092.0181c-.0234.0455-.0763.1466-.1582.2921-.1091.194-.27.4677-.481.7944-.4212.6522-1.0472 1.5235-1.8668 2.3978-1.6255 1.7339-4.1017 3.5703-7.3179 3.5703-3.21635 0-5.69252-1.8364-7.31804-3.5703-.81959-.8743-1.44556-1.7456-1.866726-2.3978-.211046-.3267-.371889-.6004-.481009-.7944-.057607-.1024-.113997-.2055-.167454-.3102l-.002771-.0055-.001536-.003c-.1055728-.2112-.1055728-.45968 0-.67083l.836836.33543-.832529-.34397c.053457-.10465.109847-.20779.167454-.3102.10912-.19399.269963-.46763.481009-.79441.421166-.65212 1.047136-1.52349 1.866726-2.39773 1.62552-1.73388 4.10169-3.57038 7.31804-3.57038 3.2162 0 5.6924 1.8365 7.3179 3.57038.8196.87424 1.4456 1.74561 1.8668 2.39773.211.32678.3719.60042.481.79441.0591.10502.1168.21081.1715.3182zm-17.76191.97077c-.16391-.2538-.29345-.4707-.38756-.63482.09411-.16412.22365-.38097.38756-.63477.38613-.59788.95808-1.39318 1.70098-2.18561 1.49949-1.59945 3.60665-3.09629 6.22373-3.09629 2.617 0 4.7241 1.49684 6.2236 3.09629.7429.79243 1.3149 1.58773 1.701 2.18561.1639.2538.2935.47065.3876.63477-.0941.16412-.2237.38102-.3876.63482-.3861.5978-.9581 1.3931-1.701 2.1856-1.4995 1.5994-3.6066 3.0962-6.2236 3.0962-2.61708 0-4.72424-1.4968-6.22373-3.0962-.7429-.7925-1.31485-1.5878-1.70098-2.1856zm17.09131-.63482c.6708-.33541.6706-.33595.6706-.33595z" fill-rule="evenodd"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label cm-required" for="correct_permissions_path"><?php echo $this->t('correct_permissions_path'); ?>:</label>
                    <div class="controls">
                        <input type="text" name="ftp_settings[ftp_directory]" id="correct_permissions_path" value="<?php echo $this->prepareVar($_tpl_vars, 'ftp_settings.ftp_path', 'string', Registry::get('config.http_path')); ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="buttons-container">
            <input type="submit" class="btn btn-primary pull-left cm-no-ajax" name="dispatch[setup.correct_permissions]" value="<?php echo $this->t('correct_permissions'); ?>">
        </div>
    </form>
</div>

<form name="setup_step_form" id="setup_step_form" method="post" action="index.php" class="form-horizontal cm-ajax cm-comet">
<input type="hidden" name="dispatch" value="setup.next_step">
<input type="hidden" name="database_settings[notify]" value="1">
<input type="hidden" name="database_settings[allow_override]" value="N">
<input type="hidden" name="server_settings[correct_permissions]" value="0">

    <h4><?php echo $this->t('server_configuration'); ?></h4>
    <?php echo $this->getNotificationsHtmlCode('server_configuration'); ?>

    <div class="setting-wrap">
        <div class="settings">
            <div class="requirement">
                <div class="control-group control-group-last">
                    <label class="control-label"><?php echo $this->t('store_url'); ?></label>
                    <div class="controls">
                        <div class="label-value">
                            <?php echo 'http://' . Tygh\Tools\Url::decode(Registry::get('config.http_location')); ?>
                            <input type="hidden" name="server_settings[http_host]" value="<?php echo Tygh\Tools\Url::decode($this->prepareVar($_tpl_vars, 'server_settings.http_host', 'string', Registry::get('config.http_host'))); ?>">
                            <input type="hidden" name="server_settings[http_path]" value="<?php echo $this->prepareVar($_tpl_vars, 'server_settings.http_path', 'string', Registry::get('config.http_path')); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="requirement">
                <a href="javascript:void(0);" data-toggle="collapse" data-target="#server_config_advanced_options" class="advanced-options-title collapsed"><span><?php echo $this->t('advanced'); ?></span><b class="caret"></b></a>
                <div id="server_config_advanced_options" class="collapse">
                    <div class="control-group">
                        <label class="control-label"><?php echo $this->t('secure_server_host_name'); ?></label>
                        <div class="controls">
                            <input type="text" name="server_settings[https_host]" value="<?php echo Tygh\Tools\Url::decode($this->prepareVar($_tpl_vars, 'server_settings.https_host', 'string', Registry::get('config.http_host'))); ?>">
                        </div>
                    </div>
                    <div class="control-group control-group-last">
                        <label class="control-label"><?php echo $this->t('secure_server_host_directory'); ?></label>
                        <div class="controls">
                            <input type="text" name="server_settings[https_path]" value="<?php echo $this->prepareVar($_tpl_vars, 'server_settings.https_path', 'string', Registry::get('config.http_path')); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings">
            <div class="requirement">
                <div class="control-group">
                    <label class="control-label cm-required" for="database_settings_host"><?php echo $this->t('mysql_server_host'); ?></label>
                    <div class="controls">
                        <input type="text" name="database_settings[host]" id="database_settings_host" value="<?php echo $this->prepareVar($_tpl_vars, 'database_settings.host', 'string', 'localhost'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label cm-required" for="database_settings_name"><?php echo $this->t('mysql_database_name'); ?></label>
                    <div class="controls">
                        <input type="text" name="database_settings[name]" id="database_settings_name" value="<?php echo $this->prepareVar($_tpl_vars, 'database_settings.name'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label cm-required" for="database_settings_user"><?php echo $this->t('mysql_user'); ?></label>
                    <div class="controls">
                        <input type="text" name="database_settings[user]" id="database_settings_user" value="<?php echo $this->prepareVar($_tpl_vars, 'database_settings.user'); ?>">
                    </div>
                </div>
                <div class="control-group control-group-last">
                    <label for="database_settings_password" class="control-label cm-required"><?php echo $this->t('mysql_password'); ?></label>
                    <div class="controls">
                        <div class="input-append" data-ca-input-password="inputGroup">
                            <input type="password" id="database_settings_password" name="database_settings[password]" value="<?php echo $this->prepareVar($_tpl_vars, 'database_settings.password'); ?>" class="cs-input-password__input" data-ca-input-password="input" />
                            <button type="button" class="btn cs-input-password__toggle-button " data-ca-input-password="toggleButton">
                                <span class="cs-icon cs-icon--type-eye-close cs-input-password__eye-close-icon " data-ca-input-password="eyeCloseIcon">
                                    <span class="cs-icon__hidden-accessible"></span>
                                    <svg fill="currentColor" class="cs-icon__svg" focusable="false" aria-hidden="true" viewBox="0 0 20 20">
                                        <path d="m1.36431.302983c-.29289-.2928933-.767763-.2928933-1.060656 0-.2928929.292893-.2928929.767767 0 1.060657l3.626676 3.62668c-1.52988 1.30212-2.80494 2.88041-3.757212 4.65506-.1152257.21473-.1189377.47202-.009954.69002l.67082-.3354c-.67082.3354-.670207.3366-.670207.3366l.000923.0019.002771.0055.009241.018c.007801.0152.018884.0366.033223.0637.028674.0541.070402.1314.12499.2285.10912.1939.269963.4676.481009.7944.421166.6521 1.047136 1.5235 1.866726 2.3977 1.62552 1.7339 4.10169 3.5705 7.31804 3.5705l.0122-.0002c1.7306-.0283 3.413-.5472 4.8541-1.4896l3.77 3.77c.2929.2929.7678.2929 1.0606 0 .2929-.2929.2929-.7678 0-1.0607l-4.2099-4.2099c-.0048-.005-.0098-.01-.0148-.0148l-9.8852-9.88523c-.00486-.005-.00978-.00991-.01475-.01475zm3.63046 5.751767c-1.32587 1.10976-2.44501 2.44633-3.30479 3.94795.09397.1638.22299.3797.38601.6321.38613.5979.95808 1.3931 1.70098 2.1856 1.49832 1.5982 3.60336 3.0939 6.21756 3.0963 1.33877-.023 2.64277-.3971 3.78407-1.0781l-2.0583-2.0583c-.1458.0955-.2993.1794-.4593.2507-.3986.1776-.829.2732-1.26538.2809s-.86984-.0726-1.27452-.2361c-.40468-.1634-.77229-.4067-1.08091-.7153-.30861-.3087-.5519-.6763-.71536-1.081-.16346-.4046-.24373-.8381-.23603-1.2745.0077-.43635.10321-.86671.28084-1.26537.07128-.15997.15519-.31351.25074-.45926zm3.33002 3.33003c-.08603.20485-.1323.42433-.13623.64672-.00414.235.03908.4684.1271.6863.08801.2179.21902.4158.38519.582.16618.1662.36413.2972.58203.3852.21791.088.45131.1312.68628.1271.22234-.0039.44184-.0502.64674-.1362z" fill-rule="evenodd"></path>
                                        <path d="m8.42159 4.26359c.517-.12101 1.04632-.18151 1.5773-.18026h.00181c2.617 0 4.7241 1.49684 6.2236 3.09629.7429.79243 1.3149 1.58773 1.701 2.18561.1633.25277.2924.46889.3864.63277-.4391.77-.9472 1.4988-1.5182 2.1774-.2667.317-.226.7901.0909 1.0568.317.2667.7901.226 1.0568-.091.7245-.861 1.3571-1.7953 1.8876-2.7876.1146-.2146.1181-.47142.0093-.68901-.039.01951-.0793.03572-.1207.04917l.1201-.05039c-.015-.02987-.0305-.05948-.0461-.08903-.0287-.05419-.0704-.13145-.125-.22849-.1091-.19399-.27-.46763-.481-.79441-.4212-.65212-1.0472-1.52349-1.8668-2.39773-1.6253-1.73372-4.1012-3.57004-7.3171-3.57038-.64693-.00145-1.29186.07229-1.92178.21974-.40332.09441-.65374.49789-.55933.9012.0944.40331.49788.65373.9012.55932z"></path>
                                    </svg>
                                </span>
                                <span class="cs-icon cs-icon--type-eye-open cs-input-password__eye-open-icon force-hidden" data-ca-input-password="eyeOpenIcon">
                                    <span class="cs-icon__hidden-accessible"></span>
                                    <svg fill="currentColor" class="cs-icon__svg" focusable="false" aria-hidden="true" viewBox="0 0 20 20">
                                        <path d="m10 6.75c-1.79493 0-3.25 1.45507-3.25 3.25 0 1.7949 1.45507 3.25 3.25 3.25 1.7949 0 3.25-1.4551 3.25-3.25 0-1.79493-1.4551-3.25-3.25-3.25zm-1.75 3.25c0-.9665.7835-1.75 1.75-1.75s1.75.7835 1.75 1.75-.7835 1.75-1.75 1.75-1.75-.7835-1.75-1.75z"></path>
                                        <path d="m19.8379 9.66403c.1055.21115.1058.46017.0002.67137l-.6708-.33542c.6708.33542.6702.33662.6702.33662l-.0009.0018-.0028.0055-.0092.0181c-.0234.0455-.0763.1466-.1582.2921-.1091.194-.27.4677-.481.7944-.4212.6522-1.0472 1.5235-1.8668 2.3978-1.6255 1.7339-4.1017 3.5703-7.3179 3.5703-3.21635 0-5.69252-1.8364-7.31804-3.5703-.81959-.8743-1.44556-1.7456-1.866726-2.3978-.211046-.3267-.371889-.6004-.481009-.7944-.057607-.1024-.113997-.2055-.167454-.3102l-.002771-.0055-.001536-.003c-.1055728-.2112-.1055728-.45968 0-.67083l.836836.33543-.832529-.34397c.053457-.10465.109847-.20779.167454-.3102.10912-.19399.269963-.46763.481009-.79441.421166-.65212 1.047136-1.52349 1.866726-2.39773 1.62552-1.73388 4.10169-3.57038 7.31804-3.57038 3.2162 0 5.6924 1.8365 7.3179 3.57038.8196.87424 1.4456 1.74561 1.8668 2.39773.211.32678.3719.60042.481.79441.0591.10502.1168.21081.1715.3182zm-17.76191.97077c-.16391-.2538-.29345-.4707-.38756-.63482.09411-.16412.22365-.38097.38756-.63477.38613-.59788.95808-1.39318 1.70098-2.18561 1.49949-1.59945 3.60665-3.09629 6.22373-3.09629 2.617 0 4.7241 1.49684 6.2236 3.09629.7429.79243 1.3149 1.58773 1.701 2.18561.1639.2538.2935.47065.3876.63477-.0941.16412-.2237.38102-.3876.63482-.3861.5978-.9581 1.3931-1.701 2.1856-1.4995 1.5994-3.6066 3.0962-6.2236 3.0962-2.61708 0-4.72424-1.4968-6.22373-3.0962-.7429-.7925-1.31485-1.5878-1.70098-2.1856zm17.09131-.63482c.6708-.33541.6706-.33595.6706-.33595z" fill-rule="evenodd"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="requirement">
                <a href="javascript:void(0);"
                       data-toggle="collapse"
                       data-target="#server_config_prefix_advanced_options"
                       class="advanced-options-title collapsed"><span><?php echo $this->t('advanced'); ?></span><b class="caret"></b></a>
                <div id="server_config_prefix_advanced_options" class="collapse">
                    <div class="control-group control-group-last">
                        <label class="control-label cm-required" for="database_settings_table_prefix"><?php echo $this->t('table_prefix'); ?></label>
                        <div class="controls">
                            <input type="text"
                                   name="database_settings[table_prefix]"
                                   value="<?php echo $this->prepareVar($_tpl_vars, 'database_settings.table_prefix', 'string', App::DEFAULT_PREFIX); ?>"
                                   id="database_settings_table_prefix">
                        </div>
                    </div>

                    <?php
                        if (!empty($_tpl_vars['db_types'])):
                    ?>
                    <div class="control-group control-group-last">
                        <label class="control-label"><?php echo $this->t('database_backend'); ?></label>
                        <div class="controls">
                            <select name="database_settings[database_backend]">
                                <?php foreach ($_tpl_vars['db_types'] as $type => $type_descr): ?>
                                    <option value="<?php echo $type;?>" <?php if ($this->prepareVar($_tpl_vars, 'database_settings.database_backend', 'string') == $type): ?>selected="selected" <?php endif; ?>><?php echo $type_descr; ?></option>
                                <?php endforeach; ?>

                            </select>
                        </div>
                    </div>
                    <?php
                        endif;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <h4><?php echo $this->t('administration_settings'); ?></h4>
    <?php echo $this->getNotificationsHtmlCode('administration_settings'); ?>

    <div class="setting-wrap">
        <div class="settings">
            <div class="requirement">
                <div class="control-group">
                    <label class="control-label cm-required cm-email" for="cart_settings_email"><?php echo $this->t('administrator_email'); ?></label>
                    <div class="controls">
                        <input type="text" name="cart_settings[email]" id="cart_settings_email" value="<?php echo $this->prepareVar($_tpl_vars, 'cart_settings.email'); ?>">
                    </div>
                </div>

                <div class="control-group">
                    <label for="cart_settings_password" class="control-label cm-required"><?php echo $this->t('administrator_password'); ?></label>
                    <div class="controls">
                        <div class="input-append" data-ca-input-password="inputGroup">
                            <input type="password" id="cart_settings_password" name="cart_settings[password]" value="<?php echo $this->prepareVar($_tpl_vars, 'cart_settings.password'); ?>" class="cs-input-password__input" data-ca-input-password="input" />
                            <button type="button" class="btn cs-input-password__toggle-button " data-ca-input-password="toggleButton">
                                <span class="cs-icon cs-icon--type-eye-close cs-input-password__eye-close-icon " data-ca-input-password="eyeCloseIcon">
                                    <span class="cs-icon__hidden-accessible"></span>
                                    <svg fill="currentColor" class="cs-icon__svg" focusable="false" aria-hidden="true" viewBox="0 0 20 20">
                                        <path d="m1.36431.302983c-.29289-.2928933-.767763-.2928933-1.060656 0-.2928929.292893-.2928929.767767 0 1.060657l3.626676 3.62668c-1.52988 1.30212-2.80494 2.88041-3.757212 4.65506-.1152257.21473-.1189377.47202-.009954.69002l.67082-.3354c-.67082.3354-.670207.3366-.670207.3366l.000923.0019.002771.0055.009241.018c.007801.0152.018884.0366.033223.0637.028674.0541.070402.1314.12499.2285.10912.1939.269963.4676.481009.7944.421166.6521 1.047136 1.5235 1.866726 2.3977 1.62552 1.7339 4.10169 3.5705 7.31804 3.5705l.0122-.0002c1.7306-.0283 3.413-.5472 4.8541-1.4896l3.77 3.77c.2929.2929.7678.2929 1.0606 0 .2929-.2929.2929-.7678 0-1.0607l-4.2099-4.2099c-.0048-.005-.0098-.01-.0148-.0148l-9.8852-9.88523c-.00486-.005-.00978-.00991-.01475-.01475zm3.63046 5.751767c-1.32587 1.10976-2.44501 2.44633-3.30479 3.94795.09397.1638.22299.3797.38601.6321.38613.5979.95808 1.3931 1.70098 2.1856 1.49832 1.5982 3.60336 3.0939 6.21756 3.0963 1.33877-.023 2.64277-.3971 3.78407-1.0781l-2.0583-2.0583c-.1458.0955-.2993.1794-.4593.2507-.3986.1776-.829.2732-1.26538.2809s-.86984-.0726-1.27452-.2361c-.40468-.1634-.77229-.4067-1.08091-.7153-.30861-.3087-.5519-.6763-.71536-1.081-.16346-.4046-.24373-.8381-.23603-1.2745.0077-.43635.10321-.86671.28084-1.26537.07128-.15997.15519-.31351.25074-.45926zm3.33002 3.33003c-.08603.20485-.1323.42433-.13623.64672-.00414.235.03908.4684.1271.6863.08801.2179.21902.4158.38519.582.16618.1662.36413.2972.58203.3852.21791.088.45131.1312.68628.1271.22234-.0039.44184-.0502.64674-.1362z" fill-rule="evenodd"></path>
                                        <path d="m8.42159 4.26359c.517-.12101 1.04632-.18151 1.5773-.18026h.00181c2.617 0 4.7241 1.49684 6.2236 3.09629.7429.79243 1.3149 1.58773 1.701 2.18561.1633.25277.2924.46889.3864.63277-.4391.77-.9472 1.4988-1.5182 2.1774-.2667.317-.226.7901.0909 1.0568.317.2667.7901.226 1.0568-.091.7245-.861 1.3571-1.7953 1.8876-2.7876.1146-.2146.1181-.47142.0093-.68901-.039.01951-.0793.03572-.1207.04917l.1201-.05039c-.015-.02987-.0305-.05948-.0461-.08903-.0287-.05419-.0704-.13145-.125-.22849-.1091-.19399-.27-.46763-.481-.79441-.4212-.65212-1.0472-1.52349-1.8668-2.39773-1.6253-1.73372-4.1012-3.57004-7.3171-3.57038-.64693-.00145-1.29186.07229-1.92178.21974-.40332.09441-.65374.49789-.55933.9012.0944.40331.49788.65373.9012.55932z"></path>
                                    </svg>
                                </span>
                                <span class="cs-icon cs-icon--type-eye-open cs-input-password__eye-open-icon force-hidden" data-ca-input-password="eyeOpenIcon">
                                    <span class="cs-icon__hidden-accessible"></span>
                                    <svg fill="currentColor" class="cs-icon__svg" focusable="false" aria-hidden="true" viewBox="0 0 20 20">
                                        <path d="m10 6.75c-1.79493 0-3.25 1.45507-3.25 3.25 0 1.7949 1.45507 3.25 3.25 3.25 1.7949 0 3.25-1.4551 3.25-3.25 0-1.79493-1.4551-3.25-3.25-3.25zm-1.75 3.25c0-.9665.7835-1.75 1.75-1.75s1.75.7835 1.75 1.75-.7835 1.75-1.75 1.75-1.75-.7835-1.75-1.75z"></path>
                                        <path d="m19.8379 9.66403c.1055.21115.1058.46017.0002.67137l-.6708-.33542c.6708.33542.6702.33662.6702.33662l-.0009.0018-.0028.0055-.0092.0181c-.0234.0455-.0763.1466-.1582.2921-.1091.194-.27.4677-.481.7944-.4212.6522-1.0472 1.5235-1.8668 2.3978-1.6255 1.7339-4.1017 3.5703-7.3179 3.5703-3.21635 0-5.69252-1.8364-7.31804-3.5703-.81959-.8743-1.44556-1.7456-1.866726-2.3978-.211046-.3267-.371889-.6004-.481009-.7944-.057607-.1024-.113997-.2055-.167454-.3102l-.002771-.0055-.001536-.003c-.1055728-.2112-.1055728-.45968 0-.67083l.836836.33543-.832529-.34397c.053457-.10465.109847-.20779.167454-.3102.10912-.19399.269963-.46763.481009-.79441.421166-.65212 1.047136-1.52349 1.866726-2.39773 1.62552-1.73388 4.10169-3.57038 7.31804-3.57038 3.2162 0 5.6924 1.8365 7.3179 3.57038.8196.87424 1.4456 1.74561 1.8668 2.39773.211.32678.3719.60042.481.79441.0591.10502.1168.21081.1715.3182zm-17.76191.97077c-.16391-.2538-.29345-.4707-.38756-.63482.09411-.16412.22365-.38097.38756-.63477.38613-.59788.95808-1.39318 1.70098-2.18561 1.49949-1.59945 3.60665-3.09629 6.22373-3.09629 2.617 0 4.7241 1.49684 6.2236 3.09629.7429.79243 1.3149 1.58773 1.701 2.18561.1639.2538.2935.47065.3876.63477-.0941.16412-.2237.38102-.3876.63482-.3861.5978-.9581 1.3931-1.701 2.1856-1.4995 1.5994-3.6066 3.0962-6.2236 3.0962-2.61708 0-4.72424-1.4968-6.22373-3.0962-.7429-.7925-1.31485-1.5878-1.70098-2.1856zm17.09131-.63482c.6708-.33541.6706-.33595.6706-.33595z" fill-rule="evenodd"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="control-group control-group-last">
                    <label class="control-label"><?php echo $this->t('main_language'); ?></label>
                    <div class="controls">
                        <select name="cart_settings[main_language]" class="cm-main-language">
                            <?php foreach ($_tpl_vars['languages'] as $lang_code => $language): ?>
                                <option value="<?php echo $lang_code;?>" <?php if ($this->prepareVar($_tpl_vars, 'cart_settings.main_language', 'string') == $lang_code): ?>selected="selected" <?php endif; ?>><?php echo $language; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="cart_settings[secret_key]" value="<?php echo substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'), 0, 10); ?>">
            </div>
            <div class="requirement">
                <a href="javascript:void(0);" data-toggle="collapse" data-target="#administration_settings_advanced_options" class="advanced-options-title collapsed"><span><?php echo $this->t('advanced'); ?></span><b class="caret"></b></a>
                <div id="administration_settings_advanced_options" class="collapse">
                    <div class="control-group control-group-last language-choice">
                        <label class="control-label"><?php echo $this->t('additional_languages'); ?></label>

                        <input class="cm-additional-lang-main" type="hidden" name="cart_settings[languages][]" value="<?php
                            echo $this->prepareVar($_tpl_vars, 'cart_settings.main_language', 'string');
                        ?>">

                        <div class="controls">
                            <?php foreach ($_tpl_vars['languages'] as $lang_code => $language): ?>
                                <label class="checkbox"><input class="cm-additional-lang-item" id="additional_lang_<?php echo $lang_code;?>" type="checkbox" name="cart_settings[languages][]" value="<?php echo $lang_code;?>"<?php if ($this->prepareVar($_tpl_vars, 'cart_settings.main_language', 'string') == $lang_code): ?>checked="checked" data-ca-auto-ticked="true" disabled="disabled" <?php endif; ?>><?php echo $language; ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($_tpl_vars['survey'])): ?>
        <div id="survey">
            <h4><?php echo $this->t('survey'); ?></h4>

            <div class="settings">
                <div class="requirement">
                    <?php foreach ($_tpl_vars['survey'] as $question => $question_data): ?>
                        <?php if (empty($question_data['type'])) continue; ?>
                        <?php if (!empty($question_data['question']) && $question_data['type'] === 'text'): ?>
                            <div class="control-group">
                                <label class="control-label" for="<?php echo htmlspecialchars($question); ?>"><?php echo htmlspecialchars($question_data['question']); ?></label>
                                <div class="controls">
                                    <input type="text" name="survey[<?php echo htmlspecialchars($question); ?>]" id="<?php echo htmlspecialchars($question); ?>" value="">
                                </div>
                            </div>
                        <?php elseif (!empty($question_data['question']) && $question_data['type'] === 'select' && !empty($question_data['variants']) && is_array($question_data['variants'])): ?>
                            <div class="control-group">
                                <label class="control-label cm-required" for="<?php echo htmlspecialchars($question); ?>"><?php echo htmlspecialchars($question_data['question']); ?></label>
                                <div class="controls">
                                    <select name="survey[<?php echo htmlspecialchars($question); ?>]">
                                        <?php foreach ($question_data['variants'] as $value => $variant): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($variant); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php elseif (!empty($question_data['question']) && $question_data['type'] === 'datalist' && !empty($question_data['variants']) && is_array($question_data['variants'])): ?>
                            <div class="control-group">
                                <label class="control-label cm-required" for="<?php echo htmlspecialchars($question); ?>"><?php echo htmlspecialchars($question_data['question']); ?></label>
                                <div class="controls">
                                    <input name="survey[<?php echo htmlspecialchars($question); ?>]" list="<?php echo htmlspecialchars($question); ?>" autocomplete="none">
                                    <datalist id="<?php echo htmlspecialchars($question); ?>">
                                        <?php foreach ($question_data['variants'] as $variant): ?>
                                            <option value="<?php echo htmlspecialchars($variant);?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                        <?php elseif ($question_data['type'] === 'hidden' && !empty($question_data['value'])): ?>
                            <input type="hidden" name="survey[<?php echo htmlspecialchars($question); ?>]" value="<?php echo htmlspecialchars($question_data['value']); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label class="checkbox" onclick="event.preventDefault(); if (confirm('<?php echo $this->t('survey.skip_survey_confirm', ['product_name' => $this->getProductName()]); ?>')) { document.getElementById('survey').remove(); } ">
                        <input type="checkbox">
                        <?php echo $this->t('survey.skip_survey'); ?>
                    </label>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="settings">
            <div class="requirement">
                <label class="checkbox">
                    <input type="checkbox" name="cart_settings[demo_catalog]" value="Y">
                    <?php echo $this->t('install_demo_data'); ?>
                    <span class="muted annotate"><?php echo $this->t('install_demo_data_text'); ?></span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="cart_settings[feedback_auto]" value="Y" checked="checked">
                    <?php echo $this->t('help_us_improve_cart', array('product_name' => $this->getProductName())); ?>
                    <span class="muted annotate"><?php echo $this->t('help_us_improve_cart_text', array('product_name' => $this->getProductName())); ?></span>
                </label>
            </div>
        </div>
    </div>

    <?php
    // Disable theme selector, base theme should be installed always
    ?>
    <?php if (0 && count($_tpl_vars['available_themes']) > 1): ?>
        <h4><?php echo $this->t('select_storefront_theme'); ?></h4>
        <div class="theme-gallery">
            <?php $themes_iteration = 0; ?>
            <?php foreach ($_tpl_vars['available_themes'] as $theme_name => $theme_preview_image): ?>
                <?php $themes_iteration++; ?>
                <?php if ($themes_iteration % 3 == 1 || $themes_iteration == 1):?>
                    <div class="gallery-line cm-theme-item<?php if ($themes_iteration > 3) {echo " hidden";} ?>">
                <?php endif;?>
                <div class="item <?php if ($theme_name == App::THEME_NAME) {echo ' item-checked';} ?>">
                    <img src="../<?php echo $theme_preview_image; ?>" alt="<?php echo $theme_name; ?>" width="200" class="cm-bootstrap-item">
                    <span class="title"><?php echo $this->t('selected'); ?></span>
                    <input type="hidden" name="cart_settings[theme_name]" <?php if ($theme_name != App::THEME_NAME) {echo 'disabled="disabled"';} ?> value="<?php echo $theme_name; ?>">
                </div>
                <?php if ($themes_iteration % 3 == 0 || $themes_iteration == count($_tpl_vars['available_themes'])):?>
                    </div>
                <?php endif;?>
            <?php endforeach; ?>
        </div>
        <?php if (count($_tpl_vars['available_themes']) > 3): ?>
            <button class="btn btn-block cm-load-themes"><?php echo $this->t('load_more_themes'); ?></button>
        <?php endif; ?>
    <?php else: ?>
        <input type="hidden" name="cart_settings[theme_name]" value="<?php echo App::THEME_NAME ?>">
    <?php endif; ?>
</div>

<div class="modal-footer">
    <input type="submit" id="install_form_submit_button" class="btn btn-primary btn-large" name="dispatch[setup.next_step]" value="<?php echo $this->t('install'); ?>">
</div>

</form>
<?php
    require $_tpl_vars['dir']['root'] . '/install/design/templates/common/footer.php';
