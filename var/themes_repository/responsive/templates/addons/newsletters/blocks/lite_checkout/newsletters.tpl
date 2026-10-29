{capture name="mailing_lists"}
    {$show_newsletters_content=false}
    {hook name="newsletters:checkout_email_subscription"}
        <input type="text" name="mailing_lists" value="" class="cm-news-subscribe hidden"/>
        {foreach $page_mailing_lists as $list}
            {if $list.show_on_checkout}
                {$show_newsletters_content=true}
            {/if}
            <div class="ty-control-group ty-checkout__terms{if !$list.show_on_checkout} hidden{/if}">
                <div class="cm-field-container">
                    <input type="hidden" name="all_mailing_lists[]" value="{$list.list_id}"/>
                    <div class="ty-newsletters__item">
                        <label for="subscribe_list_{$list.list_id}">
                            <input type="checkbox"
                                   id="subscribe_list_{$list.list_id}"
                                   name="mailing_lists[]"
                                   value="{$list.list_id}"
                                   {if $user_mailing_lists[$list.list_id]}checked="checked"{/if}
                                   class="checkbox cm-news-subscribe"
                            />{$list.object}
                        </label>
                    </div>
                </div>
            </div>
        {/foreach}
    {/hook}
{/capture}

{if $show_newsletters_content}
    <div class="litecheckout__container">
        <div class="litecheckout__item">
            <div class="litecheckout__terms subscription-container" id="subsciption_{$tab_id}">
                <div class="ty-control-group ty-checkout__terms">
                    <div class="cm-field-container">
                        <h2 class="litecheckout__step-title">{$block.name|default:__("text_signup_for_subscriptions")}</h2>
                    </div>
                </div>
                {$smarty.capture.mailing_lists nofilter}
            <!--subsciption_{$tab_id}--></div>
        </div>
    </div>
{/if}
