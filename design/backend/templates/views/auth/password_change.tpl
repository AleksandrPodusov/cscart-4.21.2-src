<div class="modal signin-modal">
    <form action="{""|fn_url}" method="post" name="main_login_form" class="signin-modal__form cm-skip-check-items">
        <input type="hidden" name="return_url" value="{$smarty.request.return_url|fn_url}">

        <div class="modal-header">
            <h4 class="signin-modal__form-header">{__("administration_panel")}</h4>
        </div>
        <div class="modal-body">
            <p>{__("error_password_expired")}</p>
            <label>{__("email")}:</label>
            <div id="email" class="input-text">{$user_data.email}</div>
            <label for="password1" class="signin-modal__form-label cm-required">{__("new_password")}:</label>
            {include_ext file="components/input_password.tpl"
                id="password1"
                name="user_data[password1]"
                class="signin-modal__form-field signin-modal__form-field--password cm-autocomplete-off"
                input_attrs=[
                    maxlength => "32"
                ]
            }

            <label for="password2" class="signin-modal__form-label cm-required">{__("confirm_new_password")}:</label>
            {include_ext file="components/input_password.tpl"
                id="password2"
                name="user_data[password2]"
                class="signin-modal__form-field signin-modal__form-field--password cm-autocomplete-off"
                input_attrs=[
                    maxlength => "32"
                ]
            }
        </div>
        <div class="modal-footer signin-modal__footer">
            {include file="buttons/button.tpl" but_text=__("save") but_name="dispatch[auth.password_change]" but_role="button_main"}
            <a href="{"auth.logout"|fn_url}" class="pull-right">{__("sign_out")}</a>
        </div>
    </form>
</div>