{strip}
{$image_panel_positions = ["top_left", "top_right", "bottom_left", "bottom_right"]}
{$image_height = $image_height|default:200}

{* Process $image_panel_items data *}
{include file="views/products/components/image_panel_process.tpl"
    image_panel_items=$image_panel_items
    image_panel_positions=$image_panel_positions
}

{$image_panel_has_items = false}
{foreach $image_panel_positions as $position}
    {if $image_panel_items[$position]|count > 0}
        {$image_panel_has_items = true}
        {break}
    {/if}
{/foreach}

{if $image_panel_has_items}
<div class="ty-image-panel" style="--ty-image-panel-image-height: {$image_height}px;">
    {foreach $image_panel_positions as $position}
        {if $image_panel_items[$position]|count > 0}
            <div class="ty-image-panel__inner ty-image-panel__inner--{$position|replace:"_":"-"}">
                {foreach $image_panel_items[$position] as $item_id => $item}
                    {$item_suffix = $item_id|replace:"_":"-"}
                    {if $item.file}
                        {include file=$item.file item=$item item_id=$item_id}
                    {else}
                        {if $item.type}
                            {$element_type = $item.type}
                        {elseif $item.href}
                            {$element_type = "a"}
                        {else}
                            {$element_type = "div"}
                        {/if}

                        {$element_attrs = $item.element_attrs|default:[]}

                        {if $item.id}
                            {$element_attrs["id"] = $item.id}
                        {/if}
                        {if $item.name}
                            {$element_attrs["name"] = $item.name}
                        {/if}
                        {if $item.title}
                            {$element_attrs["title"] = $item.title}
                        {/if}
                        {if $item.href}
                            {$element_attrs["href"] = $item.href}
                        {/if}
                        {if $element_attrs["href"]}
                            {$element_attrs["href"] = $element_attrs["href"]|fn_url}
                        {/if}

                        {$element_class = "ty-image-panel__item ty-image-panel__item--`$item_suffix`"}
                        {if $item.class}
                            {$element_class = "`$element_class` `$item.class`"}
                        {elseif $element_attrs["class"]}
                            {$element_class = "`$element_class` `$element_attrs["class"]`"}
                        {/if}
                        {if $item.ajax}
                            {$element_class = "`$element_class` cm-ajax"}
                        {/if}
                        {if $item.full_render}
                            {$element_class = "`$element_class` cm-ajax-full-render"}
                        {/if}
                        {if $element_class}
                            {$element_attrs["class"] = $element_class}
                        {/if}

                        {$wrapper_attrs = $item.wrapper_attrs|default:[]}
                        {$wrapper_attrs.class = "ty-image-panel__item-wrapper ty-image-panel__item-wrapper--`$item_suffix`"}

                        <div {$wrapper_attrs|render_tag_attrs nofilter}>
                            {if $element_type === "button"}
                                <button {$element_attrs|render_tag_attrs nofilter}>
                            {elseif $element_type === "a"}
                                <a {$element_attrs|render_tag_attrs nofilter}>
                            {else}
                                <div {$element_attrs|render_tag_attrs nofilter}>
                            {/if}
                                {if $item.icon || $item.icon_class}
                                    {include_ext file="common/icon.tpl"
                                        class=(($item.icon) ? "`$item.icon` `$item.icon_class`" : $item.icon_class)
                                        id=$item.icon_id
                                        title=$item.icon_title
                                        icon_text=$item.icon_text
                                        data=$item.icon_data
                                    }
                                {/if}
                                {$item.text}
                            {if $element_type === "button"}
                                </button>
                            {elseif $element_type === "a"}
                                </a>
                            {else}
                                </div>
                            {/if}
                            {if $item.content}
                                {$item.content nofilter}
                            {/if}
                        </div>
                    {/if}
                {/foreach}
            </div>
        {/if}
    {/foreach}
</div>
{/if}
{/strip}