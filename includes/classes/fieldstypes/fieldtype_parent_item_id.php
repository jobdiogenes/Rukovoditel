<?php

class fieldtype_parent_item_id
{

    public $options;

    function __construct()
    {
        $this->options = array('name' => TEXT_FIELDTYPE_PARENT_ITEM_ID_TITLE, 'title' => TEXT_FIELDTYPE_PARENT_ITEM_ID_TITLE);
    }

    function output($options)
    {
        global $app_entities_cache;

        if(isset($options['is_comments_listing']))
        {
            $entities_id = $options['field']['entities_id'];
            $parent_entities_id = $app_entities_cache[$entities_id]['parent_id'];
            $parent_item_id = $options['value'];

            if(users::has_users_access_to_entity($parent_entities_id) and!isset($options['is_export']))
            {
                return '<a href="' . url_for('items/info', 'path=' . $parent_entities_id . '-' . $parent_item_id) . '">' . items::get_heading_field($parent_entities_id, $parent_item_id) . '</a>';
            }
            else
            {
                return $options['path_info']['parent_name'];
            }
        }
        elseif(isset($options['is_export']))
        {
            return str_replace('<br>', ' - ', $options['path_info']['parent_name']);
        }
        else
        {
            $parent_path_array = explode('/', $options['path_info']['parent_path']);
            $parent_path = explode('-', $parent_path_array[count($parent_path_array) - 1]);
            $parent_entities_id = $parent_path[0];

            if(users::has_users_access_to_entity($parent_entities_id))
            {
                return '<a href="' . url_for('items/info', 'path=' . $options['path_info']['parent_path']) . '">' . $options['path_info']['parent_name'] . '</a>';
            }
            else
            {
                return $options['path_info']['parent_name'];
            }
        }
    }

    function reports_query($options)
    {
        $filters = $options['filters'];
        $sql_query = $options['sql_query'];

        if(strlen($filters['filters_values']) > 0)
        {
            $sql_query[] = " e.parent_item_id " . ($filters['filters_condition'] == 'include' ? 'in' : 'not in') . " (" . $filters['filters_values'] . ") ";
        }

        return $sql_query;
    }

}
