(function (_, $) {
  $(_.doc).ready(function () {
    $(_.doc).on('click', '.cm-yandex-delivery-select-store', function () {
      $.ceEvent('trigger', 'ce.shipping.select-store', []);
      fn_calculate_total_shipping_cost();
    });
    $(_.doc).on('click', '.cm-yandex-delivery-select-location', function () {
      var $elm = $(this),
        location = $elm.data('caLocationId'),
        group_key = $elm.data('caGroupKey'),
        shipping_id = $elm.data('caShippingId'),
        target_map_id = $elm.data('caTargetMapId'),
        $container = $('#' + target_map_id),
        delete_dummy_elm_after_calculate = false,
        $shipping_item_elm;
      if (!$('[data-ca-pickup-select-store]' + '[data-ca-shipping-id="' + shipping_id + '"]' + '[data-ca-group-key="' + group_key + '"]' + '[data-ca-location-id="' + location + '"]').length) {
        delete_dummy_elm_after_calculate = true;
        $shipping_item_elm = $('<input>').addClass('hidden').attr('type', 'radio').attr('name', 'select_pickup_point[' + group_key + '][' + shipping_id + ']').val(location).attr('data-ca-pickup-select-store', true).attr('data-ca-shipping-id', shipping_id).attr('data-ca-group-key', group_key).attr('data-ca-location-id', location);
        $('#shipping_rates_list').append($shipping_item_elm);
      }
      $('[data-ca-pickup-select-store]' + '[data-ca-shipping-id="' + shipping_id + '"]' + '[data-ca-group-key="' + group_key + '"]' + '[data-ca-location-id="' + location + '"]').each(function () {
        $(this).prop('checked', true);
      });
      fn_calculate_total_shipping_cost();
      $container.ceGeoMap('exitFullscreen');
      if (delete_dummy_elm_after_calculate) {
        $shipping_item_elm.remove();
      }
    });
  });
})(Tygh, Tygh.$);