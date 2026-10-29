// For backend and frontend templates
(function (_, $) {
  $(_.doc).on('click', '[data-ca-input-password="toggleButton"]', function () {
    const $inputGroup = $(this).closest('[data-ca-input-password="inputGroup"]');
    const $eyeCloseIcon = $('[data-ca-input-password="eyeCloseIcon"]', $inputGroup);
    const $eyeOpenIcon = $('[data-ca-input-password="eyeOpenIcon"]', $inputGroup);
    const isPasswordShow = $eyeCloseIcon.hasClass('hidden');
    $('[data-ca-input-password="input"]', $inputGroup).prop('type', isPasswordShow ? 'password' : 'text');
    $(this).toggleClass('active', !isPasswordShow);
    $eyeCloseIcon.toggleClass('hidden', !isPasswordShow);
    $eyeOpenIcon.toggleClass('hidden', isPasswordShow);
  });
})(Tygh, Tygh.$);