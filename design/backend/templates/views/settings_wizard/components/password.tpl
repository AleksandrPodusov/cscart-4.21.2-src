<div class="control-group setting-wide" data-ca-settings-wizard="passwordControlGroup">
    <label for="password_field" class="control-label">{__("new_administrator_password")}:</label>
    <div class="controls">
        {include_ext file="components/input_password.tpl"
            id="password_field"
            name="new_password"
            class="input-text"
        }
        <a class="cm-generate-password a-pseudo">{__("generate")}</a>
    </div>
</div>

<script>
    (function($, _) {
        $('.cm-generate-password').on('click', function(e) {
            const $passwordControlGroup =  $(this).closest('[data-ca-settings-wizard="passwordControlGroup"]');

            // Password generator
            $('[data-ca-input-password="input"]', $passwordControlGroup).val(Math.random().toString(36).slice(-10));

            // Show password
            if (!$('[data-ca-input-password="eyeCloseIcon"]', $passwordControlGroup).hasClass('hidden')) {
                $('[data-ca-input-password="input"]', $passwordControlGroup).prop('type', 'text');
                $('[data-ca-input-password="toggleButton"]', $passwordControlGroup).addClass('active');
                $('[data-ca-input-password="eyeCloseIcon"]', $passwordControlGroup).addClass('hidden');
                $('[data-ca-input-password="eyeOpenIcon"]', $passwordControlGroup).removeClass('hidden');
            }
        });
    })(Tygh.$, Tygh);
</script>
