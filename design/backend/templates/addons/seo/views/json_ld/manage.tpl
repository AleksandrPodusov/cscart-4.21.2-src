{capture name="mainbox"}

<div class="items-container" id="manage_json_ld">

<form action="{""|fn_url}" method="post" name="json_ld_form" class="form-horizontal form-edit">
    <div id="manage_json_ld_content">

        {include file="common/subheader.tpl" title=__("seo.json_ld_organization_section")}

        <div class="control-group">
            <label for="jd_desc" class="control-label">{__("seo.json_ld_organization_description")}:</label>
            <div class="controls">
                <textarea id="jd_desc" name="json_ld[organization][description]" cols="55" rows="5" class="span12">{$json_ld.organization.description}</textarea>
                <p class="muted description">{__("seo.json_ld_organization_description_tooltip")}</p>
            </div>
        </div>

        <div class="control-group cm-mask-phone-group" data-ca-phone-mask-group-id="jd_phone">
            <label for="jd_phone" class="control-label cm-mask-phone-label">{__("seo.json_ld_organization_phone")}:</label>
            <div class="controls">
                {include file="components/phone.tpl"
                    id="jd_phone"
                    name="json_ld[organization][telephone]"
                    value=$json_ld.organization.telephone
                    label_text=""
                    show_control_group=false
                    show_controls=false
                    class="input-medium"
                }
                <p class="muted description">{__("seo.json_ld_organization_phone_tooltip")}</p>
            </div>
        </div>

        <div class="control-group">
            <label for="jd_email" class="control-label cm-email">{__("seo.json_ld_organization_email")}:</label>
            <div class="controls">
                <input id="jd_email" type="text" name="json_ld[organization][email]" value="{$json_ld.organization.email}" class="input-large" />
                <p class="muted description">{__("seo.json_ld_organization_email_tooltip")}</p>
            </div>
        </div>

        <div class="control-group">
            <label for="jd_same_as" class="control-label">{__("seo.json_ld_organization_same_as")}:</label>
            <div class="controls">
                <textarea id="jd_same_as" name="json_ld[organization][same_as]" cols="55" rows="6" class="span12">{$json_ld.organization.same_as}</textarea>
                <p class="muted description">{__("seo.json_ld_organization_same_as_tooltip")}</p>
            </div>
        </div>

    <!--manage_json_ld_content--></div>

</form>

<!--manage_json_ld--></div>

{capture name="buttons"}
    {include file="buttons/save.tpl" but_name="dispatch[json_ld.update]" but_role="submit-link" but_target_form="json_ld_form"}
{/capture}

{/capture}

{if "MULTIVENDOR"|fn_allowed_for}
    {include file="common/mainbox.tpl"
        title=__("seo.json_ld")
        content=$smarty.capture.mainbox
        buttons=$smarty.capture.buttons
        select_storefront=true
        show_all_storefront=false
    }
{else}
    {include file="common/mainbox.tpl"
        title=__("seo.json_ld")
        content=$smarty.capture.mainbox
        buttons=$smarty.capture.buttons
    }
{/if}
