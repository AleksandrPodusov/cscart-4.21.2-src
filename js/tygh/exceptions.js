function fn_change_options(objId, id, optionId) {
  var $ = Tygh.$;
  // Change cart status
  var cart_changed = true;
  var self = $(':input[id*=_' + objId + '_' + optionId + ']').last()[0];
  var formData = [];
  var varNames = [];
  var updateIds = [];
  var cacheQuery = true;
  var defaultValues = {};
  var parents = $('.cm-reload-' + objId);
  $.each(parents, function (id, parentElm) {
    var reloadId = $(parentElm).prop('id');
    updateIds.push(reloadId);
    defaultValues[reloadId] = {};
    var elms = $(':input:not([type=radio]):not([type=checkbox])', parentElm);
    $.each(elms, function (id, elm) {
      if ($(elm).prop('disabled')) {
        return true;
      }
      if (elm.type !== 'submit' && elm.type !== 'file' && !($(this).hasClass('cm-hint') && elm.value === elm.defaultValue) && elm.name.length !== 0) {
        if (elm.name === 'no_cache' && elm.value) {
          cacheQuery = false;
        }
        formData.push({
          name: elm.name,
          value: elm.value
        });
        varNames.push(elm.name);
      }
    });
    elms = $(':input', parentElm);
    $.each(elms, function (id, elm) {
      var elmId = $(elm).prop('id');
      if (!elmId) {
        return true;
      }
      if ($(elm).is('select')) {
        $('option', elm).each(function () {
          if (this.defaultSelected) {
            defaultValues[reloadId][elmId] = this.value;
          }
        });
      } else if ($(elm).is('input[type=radio], input[type=checkbox]')) {
        defaultValues[reloadId][elmId] = elm.defaultChecked;
      } else if (!$(elm).is('input[type=file]')) {
        defaultValues[reloadId][elmId] = elm.defaultValue;
      }
    });
  });
  var radio = $('input[type=radio]:checked, input[type=checkbox]', parents);
  $.each(radio, function (id, elm) {
    if ($(elm).prop('disabled')) {
      return true;
    }
    var value = elm.value;
    if ($(elm).is('input[type=checkbox]:checked')) {
      if (!$(elm).hasClass('cm-no-change')) {
        value = $(elm).val();
      }
    } else if ($(elm).is('input[type=checkbox]')) {
      if ($.inArray(elm.name, varNames) !== -1) {
        return true;
      }
      if (!$(elm).hasClass('cm-no-change')) {
        value = 'unchecked';
      } else {
        value = '';
      }
    }
    formData.push({
      name: elm.name,
      value: value
    });
  });
  $.ceEvent('trigger', 'ce.product_option_changed', [objId, id, optionId, updateIds, formData]);
  var url = Tygh.area === 'A' ? url = fn_url('order_management.options?changed_option[' + id + ']=' + optionId) : url = fn_url('products.options?changed_option[' + id + ']=' + optionId);
  if (Tygh.area === 'A') {
    cacheQuery = false;
  }
  for (var i = 0; i < formData.length; i++) {
    url += '&' + formData[i]['name'] + '=' + encodeURIComponent(formData[i]['value']);
  }
  $.ceAjax('request', url, {
    result_ids: updateIds.join(',').toString(),
    caching: cacheQuery,
    force_exec: true,
    pre_processing: fn_pre_process_form_files,
    callback: function (data, params) {
      fn_post_process_form_files(data, params);
      var parents = $('.cm-reload-' + objId);
      $.each(parents, function (id, parentElm) {
        if (data.html && data.html[$(parentElm).prop('id')]) {
          var reloadId = $(parentElm).prop('id'),
            elms = $(':input', parentElm),
            checkedElms = [];
          if (defaultValues[reloadId] != null) {
            $.each(elms, function (id, elm) {
              var elm_id = $(elm).prop('id');
              if (elm_id && defaultValues[reloadId][elm_id] != null) {
                if ($(elm).is('select')) {
                  var selected = {};
                  var isSelected = false;
                  $('option', elm).each(function () {
                    selected[this.value] = this.defaultSelected;
                    this.defaultSelected = defaultValues[reloadId][elm_id] == this.value ? true : false;
                  });
                  $('option', elm).each(function () {
                    this.selected = selected[this.value];
                    if (this.selected == true) {
                      isSelected = true;
                    }
                  });
                  if (!isSelected) {
                    $('option', elm).get(0).selected = true;
                  }
                } else if ($(elm).is('input[type=radio], input[type=checkbox]')) {
                  var checked = elm.defaultChecked;
                  if (checked) {
                    checkedElms.push(elm);
                  }
                  elm.defaultChecked = defaultValues[reloadId][elm_id];
                  elm.checked = checked;
                } else {
                  var value = elm.defaultValue;
                  elm.defaultValue = defaultValues[reloadId][elm_id];
                  elm.value = value;
                }
              }
            });
            $(checkedElms).prop('checked', true);
          }
        }
      });

      // if notification with zero_inventory error happen, we shouldnt trigger option changes event
      for (var notificationKey in data.notifications) {
        if (data.notifications.hasOwnProperty(notificationKey)) {
          var notify = data.notifications[notificationKey];
          if (notify.extra == 'zero_inventory') {
            return;
          }
        }
      }
      $.ceEvent('trigger', 'ce.product_option_changed_post', [objId, id, optionId, updateIds, formData, data, params, self]);
    },
    method: 'post'
  });
}
function fn_set_option_value(id, optionId, value) {
  var $ = Tygh.$;
  var elm = $('#option_' + id + '_' + optionId);
  if (elm.prop('disabled')) {
    return false;
  }
  if (elm.prop('type') == 'select-one') {
    elm.val(value).change();
  } else {
    elms = $('#option_' + id + '_' + optionId + '_group');
    if ($.browser.msie) {
      $('input[type=radio][value=' + value + ']', elms).prop('checked', true);
    }
    $('input[type=radio][value=' + value + ']', elms).click();
  }
  return true;
}
function fn_pre_process_form_files(data, params) {
  var $ = Tygh.$;
  if (data.html) {
    // Create temporarily div element
    $(Tygh.body).append('<div id="file_container" class="hidden"></div>');
    var container = {};
    container = $('#file_container');

    // Move files blocks to the temporarily created container
    for (var k in data.html) {
      $('#' + k + ' .fileuploader ,' + '#' + k + ' .ty-fileuploader').each(function (idx, elm) {
        var jelm = $(elm);
        var jparent = jelm.parents('.control-group, .ty-control-group');
        jparent.appendTo(container);
        jparent.prop('id', 'moved_' + jparent.prop('id'));
      });
    }
  }
}
function fn_post_process_form_files(data, params) {
  var $ = Tygh.$;
  var container = {};
  container = $('#file_container');
  $('div.control-group, div.ty-control-group', container).each(function (idx, elm) {
    var jelm = $(elm);
    var elmId = jelm.prop('id').replace('moved_', '');
    var target = $('#' + elmId);
    target.html('');
    jelm.children().appendTo(target);
  });
  container.remove();
}
function fn_change_variant_image(prefix, optId, varId) {
  var $ = Tygh.$;
  var self = this;
  $('[id*=variant_image_' + prefix + '_' + optId + ']').removeClass('product-variant-image-selected').addClass('product-variant-image-unselected');

  // get select box variant
  if (typeof varId === 'undefined') {
    self = $('select[id*=_' + prefix + '_' + optId + ']')[0];
    varId = $(self).val();
  }

  // get checkbox variant
  if (typeof varId === 'undefined') {
    var $uncheckedVariant = $('#unchecked_option_' + prefix + '_' + optId);
    var $checkedVariant = $('#option_' + prefix + '_' + optId);
    if ($checkedVariant.length && $checkedVariant.is(':checked')) {
      self = $checkedVariant[0];
      varId = $checkedVariant.val();
    } else if ($uncheckedVariant.length) {
      self = $uncheckedVariant[0];
      varId = $uncheckedVariant.val();
    }
  }
  $('[id*=variant_image_' + prefix + '_' + optId + '_' + varId + ']').removeClass('product-variant-image-unselected').addClass('product-variant-image-selected');
  var formData = [],
    varNames = [],
    parents = $('.cm-reload-' + prefix);
  $.each(parents, function (id, parent_elm) {
    var elms = $(':input:not([type=radio]):not([type=checkbox])', parent_elm);
    $.each(elms, function (id, elm) {
      if (elm.type !== 'submit' && elm.type !== 'file' && !($(this).hasClass('cm-hint') && elm.value === elm.defaultValue) && elm.name.length !== 0) {
        formData.push({
          name: elm.name,
          value: elm.value
        });
        varNames.push(elm.name);
      }
    });
  });
  var selectables = $('input[type=radio]:checked, input[type=checkbox]', parents);
  $.each(selectables, function (id, elm) {
    if ($(elm).prop('disabled')) {
      return true;
    }
    var value = elm.value;
    if ($(elm).is('input[type=checkbox]:checked')) {
      if (!$(elm).hasClass('cm-no-change')) {
        value = $(elm).val();
      }
    } else if ($(elm).is('input[type=checkbox]')) {
      if ($.inArray(elm.name, varNames) !== -1) {
        return true;
      }
      if (!$(elm).hasClass('cm-no-change')) {
        value = 'unchecked';
      } else {
        value = '';
      }
    }
    formData.push({
      name: elm.name,
      value: value
    });
  });
  $.ceEvent('trigger', 'ce.product_option_changed_post', [prefix, varId, optId, [], formData, {}, {}, self]);
}

// buttons/add_to_cart_stepper.tpl
// based on js/tygh/cart_content.js
(function (_, $) {
  const delay = {
    input: 3000,
    change: 500
  };
  let cursorState = {};
  let recalculateTimer = null;
  let enterSubmitted = false;

  // Slight delay (before ajax recalculate) 
  // for the case when the quantity of product changes

  // Input quantity (input — while typing; change — on blur after Enter or +/-)
  $(_.doc).on('input change', '[data-ca-add-to-cart-stepper="inputQuantity"]', function (e) {
    if (e.type === 'change' && enterSubmitted) {
      enterSubmitted = false;
      return;
    }
    const element = this;
    clearTimeout(recalculateTimer);
    recalculateTimer = setTimeout(function () {
      recalculateTimer = null;
      recalculate.apply(element);
    }, e.type === 'input' ? delay.input : delay.change);
  });
  $(_.doc).on('keydown', '[data-ca-add-to-cart-stepper="inputQuantity"]', function (e) {
    if (e.keyCode === 13) {
      e.preventDefault();
      clearTimeout(recalculateTimer);
      recalculateTimer = null;
      enterSubmitted = true;
      recalculate.apply(this);
      $(this).blur();
    }
  });
  $.ceEvent('on', 'ce.form.afterAjax', function ($form, $clickedElm) {
    const $addToCartStepperQuantity = $('[data-ca-add-to-cart-stepper="inputQuantity"]', $form);
    if (!$clickedElm.hasClass('cm-btn-add-to-cart-stepper') || $addToCartStepperQuantity.length === 0) {
      return;
    }
    updateQuickView($addToCartStepperQuantity);
  });
  function recalculate(objId, id, optionId, updateIds, formData, data, params, self) {
    let $this;
    // self is passed from the options picker
    if (self && self.id) {
      $this = $('#' + self.id);
    } else if (this.isConnected) {
      $this = $(this);
    } else {
      return;
    }
    let $addToCartStepperQuantity = $();
    if ($this.data('caAddToCartStepper') === 'inputQuantity') {
      $addToCartStepperQuantity = $this;
    } else if ($('[data-ca-add-to-cart-stepper="inputQuantity"]', $this.closest('[data-ca-add-to-cart-stepper="main"]')).length > 0) {
      $addToCartStepperQuantity = $('[data-ca-add-to-cart-stepper="inputQuantity"]', $this.closest('[data-ca-add-to-cart-stepper="main"]')).first();
    }
    if ($addToCartStepperQuantity.length === 0) {
      return;
    }
    const productId = $addToCartStepperQuantity.data('caAddToCartStepperProductId');
    const formProductId = $addToCartStepperQuantity.data('caAddToCartStepperObjId') || productId;
    const cartProductId = $addToCartStepperQuantity.data('caAddToCartStepperCartProductId');
    const redirectUrl = $addToCartStepperQuantity.data('caAddToCartStepperRedirectUrl') || _.current_url;
    const productForm = $addToCartStepperQuantity[0].form || $addToCartStepperQuantity.closest('form')[0];
    if (!productForm || !(productForm instanceof HTMLFormElement)) {
      return;
    }
    saveCursorState();
    const $stepper = $addToCartStepperQuantity.closest('[data-ca-add-to-cart-stepper="main"]');
    toggleAddToCartStepperLoader($stepper, true);
    const ajaxPromise = $.ceAjax('request', fn_url(''), {
      method: 'post',
      full_render: true,
      get_promise: true,
      hidden: true,
      result_ids: $addToCartStepperQuantity.data('caAddToCartStepperTargetId'),
      data: {
        'dispatch[checkout.update]': '',
        redirect_mode: 'cart',
        redirect_url: redirectUrl,
        recalculate_cart: true,
        [$addToCartStepperQuantity.data('caAddToCartStepperCheckoutUpdateProductIdName')]: productId,
        [$addToCartStepperQuantity.data('caAddToCartStepperCheckoutUpdateAmountName')]: $addToCartStepperQuantity.val(),
        ...[...new FormData(productForm)].reduce((acc, _ref) => {
          let [key, value] = _ref;
          if (key.includes('product_data') && key.includes('[product_options]')) {
            const newKey = key.replace("product_data[".concat(formProductId, "]"), "cart_products[".concat(cartProductId, "]"));
            acc[newKey] = value;
          }
          return acc;
        }, {})
      }
    });
    const finishStepperAjax = function () {
      toggleAddToCartStepperLoader($stepper, false);
      restoreCursorState();
    };
    if (ajaxPromise && typeof ajaxPromise.done === 'function') {
      ajaxPromise.done(function () {
        updateQuickView($addToCartStepperQuantity).always(finishStepperAjax);
      }).fail(finishStepperAjax);
    } else {
      finishStepperAjax();
    }
  }
  function saveCursorState() {
    cursorState = {};
    const $focused = $(':focus');
    if (!$focused.length || $focused.data('caAddToCartStepper') !== 'inputQuantity') {
      return;
    }
    cursorState.id = $focused.attr('id');
    cursorState.type = $focused.attr('type');
    if (cursorState.type === 'text') {
      cursorState.val = $focused.val();
      cursorState.selectionStart = $focused[0].selectionStart;
      cursorState.selectionEnd = $focused[0].selectionEnd;
    }
  }
  function restoreCursorState() {
    if (!cursorState.id) {
      return;
    }
    const $activeElem = $('#' + cursorState.id);
    if (!$activeElem.length) {
      return;
    }
    $activeElem.focus();
    if (cursorState.type !== 'text') {
      return;
    }
    const activeElem = $activeElem[0];
    if ($activeElem.val() === cursorState.val) {
      activeElem.setSelectionRange(cursorState.selectionStart, cursorState.selectionEnd);
    } else {
      activeElem.selectionStart = activeElem.selectionEnd = activeElem.value.length;
    }
  }
  function updateQuickView($addToCartStepperQuantity) {
    const deferred = $.Deferred();
    if (typeof $addToCartStepperQuantity === 'undefined' || $addToCartStepperQuantity.length === 0 || $addToCartStepperQuantity.closest('.ui-dialog-content').length === 0 || $addToCartStepperQuantity.closest('[data-ca-quick-view="productMainInfo"]').length === 0 || typeof $addToCartStepperQuantity.closest('[data-ca-quick-view="productMainInfo"]').prop('id') === 'undefined' || typeof $addToCartStepperQuantity.data('caAddToCartStepperObjPrefix') === 'undefined' || typeof $addToCartStepperQuantity.data('caAddToCartStepperObjId') === 'undefined' || typeof $addToCartStepperQuantity.data('caAddToCartStepperCurrentUrl') === 'undefined') {
      return deferred.resolve().promise();
    }
    const $quickViewReloads = $addToCartStepperQuantity.closest('[data-ca-quick-view="productMainInfo"]').find(".cm-reload-".concat($addToCartStepperQuantity.data('caAddToCartStepperObjPrefix')).concat($addToCartStepperQuantity.data('caAddToCartStepperObjId')));
    const quickViewIds = $quickViewReloads.map((_, el) => el.id).get();
    const listingIds = $(".cm-reload-".concat($addToCartStepperQuantity.data('caAddToCartStepperReloadObjPrefix')).concat($addToCartStepperQuantity.data('caAddToCartStepperReloadObjId'))).map((_, el) => el.id).get();
    const promises = [];
    if (quickViewIds.length > 0) {
      promises.push($.ceAjax('request', $addToCartStepperQuantity.data('caAddToCartStepperCurrentUrl'), {
        skip_result_ids_check: true,
        get_promise: true,
        hidden: true,
        result_ids: quickViewIds.join(',')
      }));
    }
    if (listingIds.length > 0) {
      promises.push($.ceAjax('request', $addToCartStepperQuantity.data('caAddToCartStepperRedirectUrl'), {
        skip_result_ids_check: true,
        get_promise: true,
        hidden: true,
        result_ids: listingIds.join(',')
      }));
    }
    if (promises.length === 0) {
      deferred.resolve();
    } else {
      $.when.apply($, promises).then(deferred.resolve, deferred.reject);
    }
    return deferred.promise();
  }
  function toggleAddToCartStepperLoader($stepper, show) {
    if (!$stepper || !$stepper.length) {
      return;
    }
    $('[data-ca-add-to-cart-stepper="cartIconInCart"]', $stepper).toggleClass('hidden', show);
    $('[data-ca-add-to-cart-stepper="cartIconLoading"]', $stepper).toggleClass('hidden', !show);
  }
})(Tygh, Tygh.$);