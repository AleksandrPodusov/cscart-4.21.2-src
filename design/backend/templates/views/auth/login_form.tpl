{hook name="auth:login_form"}
<div class="modal signin-modal">
    <form action="{""|fn_url}" method="post" name="main_login_form" class="signin-modal__form cm-skip-check-items cm-check-changes">
        <input type="hidden" name="return_url" value="{$smarty.request.return_url|fn_url:"A":"rel"|fn_query_remove:"return_url"}">
        <div class="modal-header">
            <h4 class="signin-modal__form-header">{__("administration_panel")}</h4>
        </div>
        <div class="modal-body">
            <div class="control-group">
                <label for="username" class="signin-modal__form-label cm-trim cm-required cm-email">{__("email")}:</label>
                <input class="signin-modal__form-field cm-focus" id="username" type="text" name="user_login" size="20" value="{if $stored_user_login}{$stored_user_login}{else}{$config.demo_username}{/if}" tabindex="1">
            </div>
            <div class="control-group">
                <div>
                    <label for="password" class="signin-modal__form-label signin-modal__form-label--password cm-required">{__("password")}:</label>
                    <a href="{"auth.recover_password"|fn_url}" class="pull-right">{__("forgot_password_question")}</a>
                </div>
                {include_ext file="components/input_password.tpl"
                    id="password"
                    name="password"
                    value=$config.demo_password
                    class="signin-modal__form-field signin-modal__form-field--password"
                    input_attrs=[
                        maxlength => "32",
                        tabindex => "2"
                    ]
                }
            </div>
        </div>
        <div class="modal-footer signin-modal__footer">
            {include file="buttons/sign_in.tpl" but_name="dispatch[auth.login]" but_role="button_main" tabindex="3"}
        </div>
    </form>
</div>
{/hook}
