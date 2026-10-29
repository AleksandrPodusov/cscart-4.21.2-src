{strip}{capture name="image_panel_process"}
{*
    Import
    ---
    $image_panel_items
    $image_panel_positions

    Export
    ---
    $image_panel_items
*}
{foreach $image_panel_positions as $position}
    {$image_panel_with_position_items = $image_panel_items[$position]|default:[]}
    {$image_panel_with_position_items_sorted = []}

    {* Add items without insert_before_id and insert_after_id *}
    {foreach $image_panel_with_position_items as $key => $item}
        {if isset($item.insert_before_id) || isset($item.insert_after_id)}
            {continue}
        {/if}
        {$image_panel_with_position_items_sorted[$key] = $item}
    {/foreach}

    {* Insert before ID *}
    {foreach $image_panel_with_position_items as $key => $item}
        {if isset($item.insert_before_id)}
            {$before_id = $item.insert_before_id}
            {$inserted = false}

            {foreach $image_panel_with_position_items_sorted as $sorted_key => $sorted_item}
                {if $sorted_key === $before_id}
                    {$image_panel_with_position_items_sorted = array_merge(
                        array_slice($image_panel_with_position_items_sorted, 0, array_search($sorted_key, array_keys($image_panel_with_position_items_sorted)), true),
                        [$key => $item],
                        array_slice($image_panel_with_position_items_sorted, array_search($sorted_key, array_keys($image_panel_with_position_items_sorted)), null, true)
                    )}
                    {$inserted = true}
                    {break}
                {/if}
            {/foreach}

            {if !$inserted}
                {$image_panel_with_position_items_sorted[$key] = $item}
            {/if}
        {/if}
    {/foreach}

    {* Insert after ID *}
    {foreach $image_panel_with_position_items as $key => $item}
        {if isset($item.insert_after_id)}
            {$after_id = $item.insert_after_id}
            {$inserted = false}

            {foreach $image_panel_with_position_items_sorted as $sorted_key => $sorted_item}
                {if $sorted_key === $after_id}
                    {$index = array_search($sorted_key, array_keys($image_panel_with_position_items_sorted)) + 1}
                    {$image_panel_with_position_items_sorted = array_merge(
                        array_slice($image_panel_with_position_items_sorted, 0, $index, true),
                        [$key => $item],
                        array_slice($image_panel_with_position_items_sorted, $index, null, true)
                    )}
                    {$inserted = true}
                    {break}
                {/if}
            {/foreach}

            {if !$inserted}
                {$image_panel_with_position_items_sorted[$key] = $item}
            {/if}
        {/if}
    {/foreach}

    {$image_panel_items[$position] = $image_panel_with_position_items_sorted}
{/foreach}

{$image_panel_items = $image_panel_items scope=parent}
{/capture}{/strip}