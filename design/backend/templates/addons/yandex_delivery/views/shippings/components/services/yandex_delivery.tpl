{$yd_yes = "YesNo::YES"|enum}
{$yd_no = "YesNo::NO"|enum}
{$shipping_id = $shipping.shipping_id|default:$smarty.request.shipping_id|default:0}
{$service_params = $shipping.service_params|default:[]}
{$delivery_type = $service_params.delivery_type|default:"time_interval"}
{$has_saved_token = $shipping_id && $service_params.auth_token}
{$use_dropoff = $service_params.use_dropoff|default:$yd_yes}

{include file="common/subheader.tpl" title=__("yandex_delivery.connection") target="#yandex_delivery_connection" meta="in collapse"}
<fieldset id="yandex_delivery_connection" class="in collapse">
    <div class="control-group">
        <label class="control-label cm-required" for="yandex_delivery_auth_token">{__("yandex_delivery.auth_token")}</label>
        <div class="controls">
            <input id="yandex_delivery_auth_token"
                   type="text"
                   name="shipping_data[service_params][auth_token]"
                   size="60"
                   value="{$service_params.auth_token}"
                   autocomplete="off"
            />
            <p class="muted description">{__("yandex_delivery.auth_token.tooltip") nofilter}</p>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label">{__("yandex_delivery.environment")}</label>
        <div class="controls">
            <label class="radio" for="yandex_delivery_environment_production">
                <input type="radio"
                       id="yandex_delivery_environment_production"
                       name="shipping_data[service_params][test_mode]"
                       value="{$yd_no}"
                       {if $service_params.test_mode !== $yd_yes}checked="checked"{/if}
                />
                {__("yandex_delivery.environment.production")}
            </label>
            <label class="radio" for="yandex_delivery_environment_sandbox">
                <input type="radio"
                       id="yandex_delivery_environment_sandbox"
                       name="shipping_data[service_params][test_mode]"
                       value="{$yd_yes}"
                       {if $service_params.test_mode === $yd_yes}checked="checked"{/if}
                />
                {__("yandex_delivery.environment.sandbox")}
            </label>
            <p class="muted description">{__("yandex_delivery.environment.tooltip")}</p>
        </div>
    </div>

    <div class="control-group">
        <div class="controls">
            {include file="buttons/button.tpl"
                but_id="yandex_delivery_check"
                but_role="button"
                but_meta="btn"
                but_text=__("yandex_delivery.check")
            }
        </div>
    </div>
</fieldset>

{include file="common/subheader.tpl" title=__("yandex_delivery.handover") target="#yandex_delivery_handover" meta="in collapse"}
<fieldset id="yandex_delivery_handover" class="in collapse">
{if !$has_saved_token}
    <div class="control-group">
        <div class="controls">
            <div class="alert alert-block yandex-delivery-note">{__("yandex_delivery.handover_needs_token")}</div>
        </div>
    </div>
    <input type="hidden" name="shipping_data[service_params][use_dropoff]" value="{$use_dropoff}" />
    <input type="hidden" name="shipping_data[service_params][source_platform_station_id]" value="{$service_params.source_platform_station_id}" />
    <input type="hidden" name="shipping_data[service_params][dropoff_platform_station_id]" value="{$service_params.dropoff_platform_station_id}" />
{else}
    <div class="control-group hidden" id="yandex_delivery_stale_warehouses">
        <div class="controls">
            <div class="alert alert-block yandex-delivery-note">{__("yandex_delivery.warehouses_stale")}</div>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label">{__("yandex_delivery.handover_scheme")}</label>
        <div class="controls">
            <label class="radio" for="yandex_delivery_handover_dropoff">
                <input type="radio"
                       id="yandex_delivery_handover_dropoff"
                       class="cm-yandex-delivery-handover"
                       name="shipping_data[service_params][use_dropoff]"
                       value="{$yd_yes}"
                       {if $use_dropoff === $yd_yes}checked="checked"{/if}
                />
                {__("yandex_delivery.handover_scheme.dropoff")}
            </label>
            <label class="radio" for="yandex_delivery_handover_courier">
                <input type="radio"
                       id="yandex_delivery_handover_courier"
                       class="cm-yandex-delivery-handover"
                       name="shipping_data[service_params][use_dropoff]"
                       value="{$yd_no}"
                       {if $use_dropoff !== $yd_yes}checked="checked"{/if}
                />
                {__("yandex_delivery.handover_scheme.courier")}
            </label>
        </div>
    </div>

    <input type="hidden"
           id="yandex_delivery_dropoff_city"
           name="shipping_data[service_params][dropoff_city]"
           value="{$service_params.dropoff_city|default:$yandex_delivery_default_city.name}"
    />

    <div class="control-group" data-ca-yandex-delivery-scheme="dropoff">
        <label class="control-label" for="yandex_delivery_dropoff_geo_id">{__("yandex_delivery.dropoff_city")}</label>
        <div class="controls">
            <div class="input-xxlarge">
                <select id="yandex_delivery_dropoff_geo_id"
                        name="shipping_data[service_params][dropoff_geo_id]"
                        class="cm-object-picker object-picker__select"
                        data-ca-object-picker-object-type="yandex-delivery-city"
                        data-ca-object-picker-ajax-url="{"yandex_delivery.get_cities_list?shipping_id=`$shipping_id`"|fn_url}"
                        data-ca-object-picker-ajax-delay="400"
                        data-ca-object-picker-minimum-input-length="3"
                        data-ca-object-picker-allow-clear="true"
                        data-ca-object-picker-placeholder="{__("type_to_search")|escape:"javascript"}"
                        data-ca-object-picker-placeholder-value=""
                        data-ca-object-picker-width="100%"
                >
                    {if $service_params.dropoff_geo_id}
                        <option value="{$service_params.dropoff_geo_id}" selected="selected">{$service_params.dropoff_city}</option>
                    {elseif $yandex_delivery_default_city.id}
                        {* Nothing chosen yet, so the shop's own city is offered: parcels are brought there by hand. *}
                        <option value="{$yandex_delivery_default_city.id}" selected="selected">{$yandex_delivery_default_city.name}</option>
                    {/if}
                </select>
            </div>
            <p class="muted description">{__("yandex_delivery.dropoff_city.tooltip")}</p>
        </div>
    </div>

    <div class="control-group" data-ca-yandex-delivery-scheme="dropoff">
        <label class="control-label" for="yandex_delivery_dropoff_platform_station_id">{__("yandex_delivery.dropoff_platform_station_id")}</label>
        <div class="controls">
            <div class="input-xxlarge">
                <select id="yandex_delivery_dropoff_platform_station_id"
                        name="shipping_data[service_params][dropoff_platform_station_id]"
                        class="cm-object-picker object-picker__select"
                        data-ca-object-picker-object-type="yandex-delivery-dropoff-point"
                        data-ca-object-picker-ajax-url="{"yandex_delivery.get_dropoff_points_list?shipping_id=`$shipping_id`"|fn_url}"
                        data-ca-object-picker-ajax-delay="250"
                        data-ca-object-picker-allow-clear="true"
                        data-ca-object-picker-escape-html="false"
                        data-ca-object-picker-template-result-selector="#yandex_delivery_dropoff_result_template"
                        data-ca-object-picker-template-selection-selector="#yandex_delivery_dropoff_selection_template"
                        data-ca-object-picker-placeholder="{__("type_to_search")|escape:"javascript"}"
                        data-ca-object-picker-placeholder-value=""
                        data-ca-object-picker-width="100%"
                >
                    {if $service_params.dropoff_platform_station_id}
                        <option value="{$service_params.dropoff_platform_station_id}" selected="selected"></option>
                    {/if}
                </select>
            </div>
            <dl class="yandex-delivery-station-types">
                <dt><span class="yandex-delivery-station-type yandex-delivery-station-type--warehouse">{__("yandex_delivery.station_type.warehouse")}</span></dt>
                <dd>{__("yandex_delivery.station_type.warehouse.description")}</dd>
                <dt><span class="yandex-delivery-station-type yandex-delivery-station-type--pickup-point">{__("yandex_delivery.station_type.pickup_point")}</span></dt>
                <dd>{__("yandex_delivery.station_type.pickup_point.description")}</dd>
            </dl>
            <p class="muted description">{__("yandex_delivery.station_type.limits_note") nofilter}</p>
        </div>
    </div>

    <div class="control-group" data-ca-yandex-delivery-scheme="courier">
        <label class="control-label" for="yandex_delivery_source_platform_station_id">{__("yandex_delivery.source_platform_station_id")}</label>
        <div class="controls">
            <div class="input-xxlarge">
                <select id="yandex_delivery_source_platform_station_id"
                        name="shipping_data[service_params][source_platform_station_id]"
                        class="cm-object-picker object-picker__select"
                        data-ca-object-picker-object-type="yandex-delivery-warehouse"
                        data-ca-object-picker-ajax-url="{"yandex_delivery.get_warehouses_list?shipping_id=`$shipping_id`"|fn_url}"
                        data-ca-object-picker-ajax-delay="250"
                        data-ca-object-picker-allow-clear="true"
                        data-ca-object-picker-placeholder="{__("type_to_search")|escape:"javascript"}"
                        data-ca-object-picker-placeholder-value=""
                        data-ca-object-picker-width="100%"
                >
                    {if $service_params.source_platform_station_id}
                        <option value="{$service_params.source_platform_station_id}" selected="selected"></option>
                    {/if}
                </select>
            </div>
            <p class="muted description">{__("yandex_delivery.source_platform_station_id.tooltip") nofilter}</p>
        </div>
    </div>

    {* Filled by the pickup diagnosis, so a working setup stays quiet and a broken one is told what exactly is wrong. *}
    <div class="control-group" data-ca-yandex-delivery-scheme="courier">
        <div class="controls">
            <div class="alert alert-block yandex-delivery-note hidden" id="yandex_delivery_pickup_note"></div>
        </div>
    </div>

    {* The picker renders its placeholder through the same template, and the placeholder carries no station data. *}
    <script type="text/template" id="yandex_delivery_dropoff_result_template" data-no-defer="true" data-no-execute="§">
        {literal}
        <span class="yandex-delivery-station">
            ${data.type_label ? `<span class="yandex-delivery-station-type yandex-delivery-station-type--${data.type_class}">${data.type_label}</span>` : ''}
            <span class="yandex-delivery-station__body">
                <span class="yandex-delivery-station__name">${data.name}</span>
                <span class="yandex-delivery-station__address">${data.address}</span>
            </span>
        </span>
        {/literal}
    </script>

    <script type="text/template" id="yandex_delivery_dropoff_selection_template" data-no-defer="true" data-no-execute="§">
        {literal}
        <span class="yandex-delivery-station">
            ${data.type_label ? `<span class="yandex-delivery-station-type yandex-delivery-station-type--${data.type_class}">${data.type_label}</span>` : ''}
            <span class="yandex-delivery-station__body">${data.text}</span>
        </span>
        {/literal}
    </script>
{/if}
</fieldset>

{include file="common/subheader.tpl" title=__("yandex_delivery.delivery") target="#yandex_delivery_delivery" meta="in collapse"}
<fieldset id="yandex_delivery_delivery" class="in collapse">
    <div class="control-group">
        <label class="control-label">{__("yandex_delivery.delivery_type")}</label>
        <div class="controls">
            <label class="radio" for="yandex_delivery_type_time_interval">
                <input type="radio"
                       id="yandex_delivery_type_time_interval"
                       class="cm-yandex-delivery-type"
                       name="shipping_data[service_params][delivery_type]"
                       value="time_interval"
                       {if $delivery_type !== "self_pickup"}checked="checked"{/if}
                />
                {__("yandex_delivery.delivery_type.time_interval")}
            </label>
            <label class="radio" for="yandex_delivery_type_self_pickup">
                <input type="radio"
                       id="yandex_delivery_type_self_pickup"
                       class="cm-yandex-delivery-type"
                       name="shipping_data[service_params][delivery_type]"
                       value="self_pickup"
                       {if $delivery_type === "self_pickup"}checked="checked"{/if}
                />
                {__("yandex_delivery.delivery_type.self_pickup")}
            </label>
        </div>
    </div>

</fieldset>

{include file="common/subheader.tpl" title=__("yandex_delivery.package") target="#yandex_delivery_package" meta="in collapse"}
<fieldset id="yandex_delivery_package" class="in collapse">
    <div class="control-group">
        <label class="control-label cm-required cm-yandex-delivery-dimensions"
               for="yandex_delivery_length"
        >{__("yandex_delivery.dimensions")}</label>
        <div class="controls form-inline">
            {* cm-field-container makes the validator put its message after the whole row
               instead of after the first input, where it would push the other two aside. *}
            <div class="cm-field-container yandex-delivery-dimensions">
                <input id="yandex_delivery_length"
                       type="text"
                       class="input-mini"
                       name="shipping_data[service_params][length]"
                       value="{$service_params.length}"
                       title="{__("ship_length")}"
                       placeholder="{__("ship_length")}"
                />
                <span class="muted">&times;</span>
                <input id="yandex_delivery_width"
                       type="text"
                       class="input-mini"
                       name="shipping_data[service_params][width]"
                       value="{$service_params.width}"
                       title="{__("ship_width")}"
                       placeholder="{__("ship_width")}"
                />
                <span class="muted">&times;</span>
                <div class="input-group input-append">
                    <input id="yandex_delivery_height"
                           type="text"
                           class="input-mini"
                           name="shipping_data[service_params][height]"
                           value="{$service_params.height}"
                           title="{__("ship_height")}"
                           placeholder="{__("ship_height")}"
                    />
                    <span class="add-on">{__("yandex_delivery.cm")}</span>
                </div>
            </div>
            <p class="muted description">{__("yandex_delivery.dimensions.tooltip")}</p>
        </div>
    </div>
</fieldset>

<script>
(function (_, $) {
    var DROPOFF = '{$yd_yes}';

    // One label covers all three dimension inputs, so cm-required alone would only check the first.
    $(document).ready(function() {
        $.ceFormValidator('registerValidator', {
            class_name: 'cm-yandex-delivery-dimensions',
            message: '{__("yandex_delivery.dimensions_all_required")|escape:"javascript"}',
            func: function () {
                return $('#yandex_delivery_length').val() !== ''
                    && $('#yandex_delivery_width').val() !== ''
                    && $('#yandex_delivery_height').val() !== '';
            }
        });
    });

    // Shows only the fields that belong to the value picked in the matching radio group.
    // Hidden groups get cm-skip-validation so a required field nobody can see never blocks saving.
    function reveal(attribute, value) {
        $('[' + attribute + ']').each(function () {
            var $group = $(this);
            var visible = $group.attr(attribute) === value;

            $group.toggle(visible).toggleClass('cm-skip-validation', !visible);
        });
    }

    function refresh() {
        reveal(
            'data-ca-yandex-delivery-scheme',
            $('.cm-yandex-delivery-handover:checked').val() === DROPOFF ? 'dropoff' : 'courier'
        );
        var delivery_type = $('.cm-yandex-delivery-type:checked').val();
        reveal('data-ca-yandex-delivery-type', delivery_type);
    }

    $(_.doc).on('change', '.cm-yandex-delivery-handover, .cm-yandex-delivery-type', refresh);

    // Checks whatever is typed in right now, so it must not go through the form and its validation.
    // POST keeps the token out of the query string and the access log.
    $(_.doc).on('click', '#yandex_delivery_check', function () {
        $.ceAjax('request', '{"yandex_delivery.check_connection"|fn_url}', {
            method: 'post',
            data: {
                auth_token: $('#yandex_delivery_auth_token').val(),
                test_mode: $('[name="shipping_data[service_params][test_mode]"]:checked').val()
            }
        });
    });

    // Points are asked for by city, so a new city invalidates the point chosen for the previous one.
    // The picker fires change while restoring the saved value too, hence the comparison.
    var current_geo_id = $('#yandex_delivery_dropoff_geo_id').val();

    $(_.doc).on('change', '#yandex_delivery_dropoff_geo_id', function () {
        var $city = $(this),
            geo_id = $city.val(),
            $station = $('#yandex_delivery_dropoff_platform_station_id'),
            search_data = {};

        search_data.geo_id = geo_id;

        $('#yandex_delivery_dropoff_city').val($city.find('option:selected').text());
        $station.ceObjectPicker('extendSearchRequestData', search_data);

        if (geo_id === current_geo_id) {
            return;
        }

        current_geo_id = geo_id;
        $station.val('').trigger('change');
    });

    // Warehouse lists are loaded with the saved token, so an unsaved change makes them stale.
    $(_.doc).on(
        'change',
        '#yandex_delivery_auth_token, [name="shipping_data[service_params][test_mode]"]',
        function () {
            $('#yandex_delivery_stale_warehouses').removeClass('hidden');
        }
    );

    // Yandex is asked why it would not collect from this warehouse, and only an actual problem gets a note.
    // The form is sent as it stands, so the answer matches the warehouse just picked rather than the saved one.
    function refreshPickupNote() {
        var $note = $('#yandex_delivery_pickup_note'),
            station_id = $('#yandex_delivery_source_platform_station_id').val(),
            request_data = {};

        $note.addClass('hidden').empty();

        if ($('.cm-yandex-delivery-handover:checked').val() === DROPOFF || !station_id) {
            return;
        }

        request_data.auth_token = $('#yandex_delivery_auth_token').val();
        request_data.test_mode = $('[name="shipping_data[service_params][test_mode]"]:checked').val();
        request_data.use_dropoff = NO;
        request_data.source_platform_station_id = station_id;

        $.ceAjax('request', '{"yandex_delivery.check_warehouse_pickups"|fn_url}', {
            method: 'post',
            hidden: true,
            data: request_data,
            callback: function (data) {
                if (data && data.message) {
                    $note.html(data.message).removeClass('hidden');
                }
            }
        });
    }

    $(_.doc).on(
        'change',
        '#yandex_delivery_source_platform_station_id, .cm-yandex-delivery-handover',
        refreshPickupNote
    );

    refresh();
    refreshPickupNote();
}(Tygh, Tygh.$));
</script>
