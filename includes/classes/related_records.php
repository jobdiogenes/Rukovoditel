<?php

class related_records
{

    public $entities_id;
    public $items_id;
    public $field;
    public $cfg;
    public $entities_access_schema;
    public $current_entities_access_schema;

    function __construct($entities_id, $items_id)
    {
        global $app_user;

        $this->entities_id = $entities_id;
        $this->items_id = $items_id;
        $this->current_entities_access_schema = users::get_entities_access_schema($this->entities_id, $app_user['group_id']);
    }

    function set_related_field($fields_id)
    {
        $field = db_find('app_fields', $fields_id);
        $this->field = $field;
        $this->cfg = new fields_types_cfg($field['configuration']);
    }

    function render_as_single_list($as_single_list = true)
    {
        global $app_user, $current_path;

        $html = '';

        $fields_access_schema = users::get_fields_access_schema($this->entities_id, $app_user['group_id']);

        $fields_query = db_query("select f.*, t.name as tab_name from app_fields f, app_forms_tabs t where f.type in ('fieldtype_related_records') and f.entities_id='" . db_input($this->entities_id) . "' and f.forms_tabs_id=t.id  order by t.sort_order, t.name, f.sort_order, f.name");
        while ($field = db_fetch_array($fields_query))
        {

            $this->cfg = new fields_types_cfg($field['configuration']);

            //skip fields that will not dipslay as single list
            if ($as_single_list == true and $this->cfg->get('display_in_main_column') != 1)
            {
                continue;
            }

            if ($as_single_list == false and $this->cfg->get('display_in_main_column') == 1)
            {
                continue;
            }

            //check field access
            $current_field_access = '';
            if (isset($fields_access_schema[$field['id']]))
            {
                $current_field_access = $fields_access_schema[$field['id']];

                if ($fields_access_schema[$field['id']] == 'hide')
                    continue;
            }

            $this->entities_access_schema = users::get_entities_access_schema($this->cfg->get('entity_id'), $app_user['group_id']);

            //checking view access
            if (!users::has_access('view', $this->entities_access_schema) and!users::has_access('view_assigned', $this->entities_access_schema))
            {
                continue;
            }


            //render list
            $this->field = $field;

            $html .= $this->render_single_list($current_field_access);
        }

        return $html;
    }

    static function get_report_info($field_info)
    {
        global $app_heading_fields_id_cache;

        $cfg = new fields_types_cfg($field_info['configuration']);

        $entity_id = $cfg->get('entity_id');
        $reports_type = 'related_items_' . $field_info['id'];

        $reports_info_query = db_query("select * from app_reports where entities_id='" . db_input($entity_id) . "' and reports_type='" . $reports_type . "'");
        if (!$reports_info = db_fetch_array($reports_info_query))
        {
            $fields_in_listing = (isset($app_heading_fields_id_cache[$entity_id]) ? $app_heading_fields_id_cache[$entity_id] : '');

            if (strlen($cfg->get('fields_in_listing')) > 0)
            {
                $fields_in_listing .= (strlen($fields_in_listing) ? ',' : '') . $cfg->get('fields_in_listing');
            }

            $sql_data = array('name' => '',
                'entities_id' => $entity_id,
                'reports_type' => $reports_type,
                'in_menu' => 0,
                'in_dashboard' => 0,
                'fields_in_listing' => $fields_in_listing,
                'created_by' => 0,
                'parent_entity_id' => 0,
                'parent_item_id' => 0,
            );

            db_perform('app_reports', $sql_data);

            $reports_id = db_insert_id();

            reports::auto_create_parent_reports($reports_id);

            $reports_info = db_find('app_reports', $reports_id);
        }

        return $reports_info;
    }

    function field_has_button($button)
    {
        $hide_controls = array();
        if (is_array($this->cfg->get('hide_controls')))
        {
            $hide_controls = $this->cfg->get('hide_controls');
        }

        if (!in_array($button, $hide_controls))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function render_single_list($current_field_access)
    {
        global $current_path, $app_path;

        $count_related_items = $this->count_related_items();

        //skip output if no records
        if ($this->cfg->get('hide_field_without_records') == 1 and $count_related_items == 0)
            return '';

        $reports_info = self::get_report_info($this->field);

        $listing_container = 'entity_items_listing' . $reports_info['id'] . '_' . $reports_info['entities_id'];

        $entity_cfg = new entities_cfg($reports_info['entities_id']);

        $with_selected_menu = '';

        if (users::has_access('export_selected', $this->entities_access_schema) and users::has_access('export', $this->entities_access_schema))
        {
            $with_selected_menu .= '<li>' . link_to_modalbox('<i class="fa fa-file-excel-o"></i> ' . TEXT_EXPORT, url_for('items/export', 'path=' . $reports_info["entities_id"] . '&reports_id=' . $reports_info['id'])) . '</li>';
        }

        $with_selected_menu .= plugins::include_dashboard_with_selected_menu_items($reports_info['id'], '&path=' . $app_path . '/' . $reports_info['entities_id'] . '&redirect_to=parent_item_info_page');


        $html_btn = '
            <div class="row">
                <div class="col-sm-' . ($this->cfg->get('display_search_bar')==1 ? '6':'12'). '">
            ';
        

        if (users::has_access('update', $this->current_entities_access_schema) and $current_field_access != 'view')
        {
            //add button
            if (users::has_access('create', $this->entities_access_schema) and $this->field_has_button('add'))
            {
                $html_btn .= button_tag((strlen($entity_cfg->get('insert_button')) > 0 ? $entity_cfg->get('insert_button') : TEXT_ADD), $this->get_add_url(), true, array('class' => 'btn btn-primary btn-sm')) . ' ';
            }

            if ($this->field_has_button('bind'))
            {
                //link button    	
                $html_btn .= button_tag('<i class="fa fa-link"></i>', url_for('items/link_related_item', 'path=' . $current_path . '&related_entities=' . $this->cfg->get('entity_id') . '&field_id=' . $this->field['id']), true, array('class' => 'btn btn-primary btn-sm', 'title' => TEXT_BUTTON_LINK)) . ' ';

                //unlink button
                if ($count_related_items > 0)
                {
                    $html_btn .= button_tag('<i class="fa fa-unlink"></i>', url_for('items/unlink_related_item', 'path=' . $current_path . '&fields_id=' . $this->field['id'] . '&related_entities_id=' . $this->cfg->get('entity_id')), true, array('class' => 'btn btn-primary btn-sm', 'title' => TEXT_UNLINK));
                    
                    $with_selected_menu = '<li>' . link_to_modalbox('<i class="fa fa-unlink"></i>' . TEXT_UNLINK ,url_for('items/unlink_selected_items','path=' . $current_path . '&fields_id=' . $this->field['id'] . '&related_entities_id=' . $this->cfg->get('entity_id') . '&reports_id='  . $reports_info['id'])) . '</li>' . $with_selected_menu;
                }
            }

            //reset with selected menu if button is hidden
            if (!$this->field_has_button('with_selected'))
            {
                $with_selected_menu = '';
            }

            //with selected
            if (strlen($with_selected_menu) and $count_related_items > 0)
            {
                $html_btn .= '
	            <div class="btn-group">
	      				<button class="btn btn-default dropdown-toggle btn-sm" type="button" data-toggle="dropdown" data-hover="dropdown">
	      				' . TEXT_WITH_SELECTED . '<i class="fa fa-angle-down"></i>
	      				</button>
	      				<ul class="dropdown-menu" role="menu">
	      					' . $with_selected_menu . '
	      				</ul>
	      			</div>';
            }
        }
        
        
        //add search bar
        if($this->cfg->get('display_search_bar')==1)
        {
            $html_btn .= '</div><div class="col-sm-6">' . render_listing_search_form($this->cfg->get('entity_id'), $listing_container, $reports_info['id']);
        }
        
        $html_btn .= '
                </div>
            </div>';

        $fields_in_popup = (is_array($this->cfg->get('fields_in_popup')) ? implode(',', $this->cfg->get('fields_in_popup')) : $this->cfg->get('fields_in_popup'));
        
        $portlets = new portlets($listing_container, $this->cfg->get('is_collapsed',false));

        $html = '
        <div class="portlet portlet-related-items form-group-' . $this->field['id'] . '">
            <div class="portlet-title">
                    <div class="caption">        
                    ' . fields_types::get_option($this->field['type'], 'name', $this->field['name']) . '&nbsp;<span class="portlet-count">(' . $count_related_items . ')</span>    
                  </div>
            <div class="tools">
                    <a href="javascript:;" class="' . $portlets->button_css() . '"></a>
            </div>

        </div>
        <div class="portlet-body" ' . $portlets->render_body() . '>
          		
        ' . $html_btn . '  		
          		
        <div id="' . $listing_container . '" class="entity_items_listing"></div>
        ' . input_hidden_tag($listing_container . '_order_fields', $reports_info['listing_order_fields']) .
                input_hidden_tag($listing_container . '_has_with_selected', (strlen($with_selected_menu) ? 1 : 0)) .
                input_hidden_tag($listing_container . '_force_display_id', implode(',', array(0) + $this->get_related_items())) .
                input_hidden_tag($listing_container . '_redirect_to', 'related_records_info_page_' . $app_path) .
                input_hidden_tag($listing_container . '_force_popoup_fields', $fields_in_popup) . '
                
      </div>
    </div>
              		
    <script>
		  $(function() {     
		    load_items_listing("' . $listing_container . '",' . (isset($_GET['gotopage'][$reports_info['id']]) ? (int) $_GET['gotopage'][$reports_info['id']] : 1) . ');                                                                         
		  });    
	  </script>
    ';

        return $html;
    }

    static function handle_app_redirect()
    {
        global $app_redirect_to;

        if (strstr($app_redirect_to, 'related_records_info_page_'))
        {
            $path = str_replace('related_records_info_page_', '', $app_redirect_to);
            redirect_to('items/info', 'path=' . $path);
        }
    }

    function get_add_url()
    {
        global $current_path_array;

        $entity_info = db_find('app_entities', $this->cfg->get('entity_id'));
        $current_entity_info = db_find('app_entities', $this->entities_id);

//if parent items are different    
        if ($entity_info['parent_id'] != $current_entity_info['parent_id'] and $entity_info['parent_id'] > 0)
        {
            $add_url = url_for('reports/prepare_add_item', 'reports_id=' . reports::get_default_entity_report_id($this->cfg->get('entity_id'), 'entity_menu') . '&related=' . $this->entities_id . '-' . $this->items_id);
        }
        //if parent items are the same
        elseif ($entity_info['parent_id'] == $current_entity_info['parent_id'] and $entity_info['parent_id'] > 0)
        {
            $path = app_get_path_to_parent_item($current_path_array) . '/' . $this->cfg->get('entity_id');

            $add_url = url_for('items/form', 'path=' . $path . '&related=' . $this->entities_id . '-' . $this->items_id);
        }
        else
        {
            $path = $this->cfg->get('entity_id');

            $add_url = url_for('items/form', 'path=' . $path . '&related=' . $this->entities_id . '-' . $this->items_id);
        }

        return $add_url;
    }

    public static function get_related_items_table_name($entities_id, $related_entities_id)
    {
        if ($entities_id > $related_entities_id)
        {
            $table_name = 'app_related_items_' . $related_entities_id . '_' . $entities_id;
            $key_name = $related_entities_id . '_' . $entities_id;
        }
        else
        {
            $table_name = 'app_related_items_' . $entities_id . '_' . $related_entities_id;
            $key_name = $entities_id . '_' . $related_entities_id;
        }

        $sufix = '';

        if ($entities_id == $related_entities_id)
        {
            $sufix = '_related';
        }

        return array('table_name' => $table_name, 'table_key' => $key_name, 'sufix' => $sufix);
    }

    function get_related_items()
    {
        $related_items_array = array();

        $table_info = self::get_related_items_table_name($this->entities_id, $this->cfg->get('entity_id'));

        $where_sql = '';

        $related_items_query = db_query("select * from " . $table_info['table_name'] . " where entity_" . $this->entities_id . "_items_id='" . db_input($this->items_id) . "'");

        while ($related_items = db_fetch_array($related_items_query))
        {
            $related_items_array[$related_items['id']] = $related_items['entity_' . $this->cfg->get('entity_id') . $table_info['sufix'] . '_items_id'];
        }

        if (strlen($table_info['sufix']) > 0)
        {
            $related_items_query = db_query("select * from " . $table_info['table_name'] . " where entity_" . $this->entities_id . $table_info['sufix'] . "_items_id='" . db_input($this->items_id) . "'");

            while ($related_items = db_fetch_array($related_items_query))
            {
                $related_items_array[$related_items['id']] = $related_items['entity_' . $this->cfg->get('entity_id') . '_items_id'];
            }
        }

        return $related_items_array;
    }

    function count_related_items()
    {
        $related_items = $this->get_related_items();

        if (count($related_items) > 0)
        {            
            $reports_info = self::get_report_info($this->field);
            
            $listing_sql_query = " and e.id in (" . implode(',', $related_items) . ")";
            
            $listing_sql_query = reports::add_filters_query($reports_info['id'], $listing_sql_query, 'e');

            //check view assigned only access
            $listing_sql_query = items::add_access_query($this->cfg->get('entity_id'), $listing_sql_query);

            //include access to parent records
            $listing_sql_query .= items::add_access_query_for_parent_entities($this->cfg->get('entity_id'));

            $listing_sql = "select count(e.id) as total from app_entity_" . $this->cfg->get('entity_id') . " e where e.id>0 " . $listing_sql_query;
            $check_query = db_query($listing_sql);
            $check = db_fetch_array($check_query);

            return $check['total'];
        }
        else
        {
            return 0;
        }
    }

    function render_list_in_listing($options)
    {
        global $sql_query_having;

        $related_items = $this->get_related_items();

        if (count($related_items) > 0)
        {
            $reports_info = self::get_report_info($this->field);

            $listing_sql_query_having = '';

            $listing_sql_query = " and e.id in (" . implode(',', $related_items) . ")";

            $listing_sql_query = reports::add_filters_query($reports_info['id'], $listing_sql_query, 'e');

            //prepare having query for formula fields
            if (isset($sql_query_having[$this->cfg->get('entity_id')]))
            {
                $listing_sql_query_having = reports::prepare_filters_having_query($sql_query_having[$this->cfg->get('entity_id')]);
            }

            //check view assigned only access
            $listing_sql_query = items::add_access_query($this->cfg->get('entity_id'), $listing_sql_query);

            //include access to parent records
            $listing_sql_query .= items::add_access_query_for_parent_entities($this->cfg->get('entity_id'));

            $listing_sql_query .= $listing_sql_query_having;

            $listing_sql = "select e.* " . fieldtype_formula::prepare_query_select($this->cfg->get('entity_id'), '') . " from app_entity_" . $this->cfg->get('entity_id') . " e where e.id>0 " . $listing_sql_query;

            $export_list = array();

            $html = '<ul class="related-items-list">';
            $items_query = db_query($listing_sql);
            while ($items = db_fetch_array($items_query))
            {
                if (strlen($this->cfg->get('heading_template')))
                {
                    $text_pattern = new fieldtype_text_pattern();
                    $item_name = $text_pattern->output_singe_text($this->cfg->get('heading_template'), $this->cfg->get('entity_id'), $items);
                }
                else
                {
                    $item_name = items::get_heading_field($this->cfg->get('entity_id'), $items['id'], $items);
                }

                $export_list[] = str_replace('&nbsp;', ' ', $item_name);

                $html .= '<li><a href="' . url_for('items/info', 'path=' . $this->cfg->get('entity_id') . '-' . $items['id']) . '">' . $item_name . '</a></li>';
            }
            $html .= '</ul>';

            if (isset($options['is_print']))
            {
                return implode('<br>', $export_list);
            }
            elseif (isset($options['is_export']))
            {
                return implode(', ', $export_list);
            }
            else
            {
                return $html;
            }
        }
        else
        {
            return '';
        }
    }

    public static function delete_related_by_item_id($entities_id, $items_id)
    {
        $fields_query = db_query("select f.* from app_fields f where f.type in ('fieldtype_related_records') and f.entities_id='" . db_input($entities_id) . "'");
        while ($field = db_fetch_array($fields_query))
        {
            $cfg = new fields_types_cfg($field['configuration']);

            if ($cfg->get('entity_id') > 0)
            {
                $table_info = self::get_related_items_table_name($entities_id, $cfg->get('entity_id'));

                db_query("delete from " . $table_info['table_name'] . " where entity_" . $entities_id . "_items_id='" . db_input($items_id) . "'");

                if (strlen($table_info['sufix']) > 0)
                {
                    db_query("delete from " . $table_info['table_name'] . " where entity_" . $entities_id . $table_info['sufix'] . "_items_id='" . db_input($items_id) . "'");
                }
            }
        }
    }

    public static function get_fields_choices_available_to_relate_to_entity($entities_id)
    {
        global $app_user;

        $choices = array();
        $fields_query = db_query("select f.*, e.name as entity_name from app_fields f, app_entities e where f.entities_id=e.id and f.type='fieldtype_related_records' order by e.name");
        while ($fields = db_fetch_array($fields_query))
        {
            $cfg = new fields_types_cfg($fields['configuration']);

            if ($cfg->get('entity_id') == $entities_id)
            {
                $access_schema = users::get_entities_access_schema($fields['entities_id'], $app_user['group_id']);
                if (users::has_access('view', $access_schema))
                {
                    $check = false;
                    $field_check_query = db_query("select id,configuration from app_fields where type='fieldtype_related_records' and entities_id = '" . $entities_id . "'");
                    while ($field_check = db_fetch_array($field_check_query))
                    {
                        $cfg = new fields_types_cfg($field_check['configuration']);

                        if ($cfg->get('entity_id') == $fields['entities_id'])
                        {
                            $check = true;
                        }
                    }

                    if ($check)
                    {
                        $choices[$fields['entities_id'] . '-' . $fields['id']] = $fields['entity_name'];
                    }
                }
            }
        }

        return $choices;
    }

    public static function prepare_entities_related_items_table($entities_id, $fields_id)
    {
        $field = db_find('app_fields', $fields_id);

        if ($field['type'] == 'fieldtype_related_records')
        {
            $cfg = new fields_types_cfg($field['configuration']);
            $related_entities_id = $cfg->get('entity_id');

            if ($related_entities_id > 0)
            {
                $tables_array = array();
                $tables_query = db_query("show tables");
                while ($tables = db_fetch_array($tables_query))
                {
                    $tables_array[] = current($tables);
                }

                $table_info = self::get_related_items_table_name($entities_id, $related_entities_id);

                if (!in_array($table_info['table_name'], $tables_array))
                {
                    $sql = '
		          CREATE TABLE IF NOT EXISTS `' . $table_info['table_name'] . '` (
		            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		            `entity_' . $entities_id . '_items_id` int(11) UNSIGNED NOT NULL,
		            `entity_' . $related_entities_id . $table_info['sufix'] . '_items_id` int(11) UNSIGNED NOT NULL,
		            PRIMARY KEY (`id`),
		            KEY `idx_' . $entities_id . '_items_id` (`entity_' . $entities_id . '_items_id`),
		            KEY `idx_' . $related_entities_id . $table_info['sufix'] . '_items_id` (`entity_' . $related_entities_id . $table_info['sufix'] . '_items_id`)
		          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
		      ';

                    db_query($sql);
                }
            }
        }
    }

    public static function delete_entities_related_items_table($entities_id)
    {
        $tables_array = array();
        $tables_query = db_query("show tables");
        while ($tables = db_fetch_array($tables_query))
        {
            $tables_array[] = current($tables);
        }

        foreach ($tables_array as $table)
        {
            if (preg_match('/app_related_items_(\d+)_' . $entities_id . '/', $table) or preg_match('/app_related_items_' . $entities_id . '_(\d+)/', $table))
            {
                $sql = 'DROP TABLE IF EXISTS ' . $table;
                db_query($sql);
            }
        }
    }

    public static function autocreate_comments($current_entity_id, $item_id, $related_entities_id, $related_items_id)
    {
        global $app_user;

        $field_info_query = db_query("select id, name, configuration from app_fields where entities_id='" . $related_entities_id . "' and type='fieldtype_related_records'");
        while ($field_info = db_fetch_array($field_info_query))
        {
            $cfg = new fields_types_cfg($field_info['configuration']);

            if ($cfg->get('entity_id') == $current_entity_id)
            {
                if (in_array($cfg->get('create_related_comment'), ['comment', 'comment_notification']))
                {
                    $item_info = db_find('app_entity_' . $current_entity_id, $item_id);
                    $fieldtype_text_pattern = new fieldtype_text_pattern();
                    $description = $fieldtype_text_pattern->output_singe_text($cfg->get('create_related_comment_text'), $current_entity_id, $item_info);

                    $sql_data = array(
                        'description' => db_prepare_html_input($description),
                        'entities_id' => $related_entities_id,
                        'items_id' => $related_items_id,
                        'attachments' => '',
                        'date_added' => time(),
                        'created_by' => $app_user['id']
                    );

                    db_perform('app_comments', $sql_data);

                    $comments_id = db_insert_id();

                    if ($cfg->get('create_related_comment') == 'comment_notification')
                    {
                        //send notificaton
                        app_send_new_comment_notification($comments_id, $related_items_id, $related_entities_id);

                        //track changes
                        if (class_exists('track_changes'))
                        {
                            $log = new track_changes($related_entities_id, $related_items_id);
                            $log->log_comment($comments_id, array());
                        }
                    }
                }

                if (in_array($cfg->get('create_related_comment_to'), ['comment', 'comment_notification']))
                {

                    $related_item_info = db_find('app_entity_' . $related_entities_id, $related_items_id);
                    $fieldtype_text_pattern = new fieldtype_text_pattern();
                    $description = $fieldtype_text_pattern->output_singe_text($cfg->get('create_related_comment_to_text'), $related_entities_id, $related_item_info);

                    $sql_data = array(
                        'description' => db_prepare_html_input($description),
                        'entities_id' => $current_entity_id,
                        'items_id' => $item_id,
                        'attachments' => '',
                        'date_added' => time(),
                        'created_by' => $app_user['id']
                    );

                    db_perform('app_comments', $sql_data);

                    $comments_id = db_insert_id();

                    if ($cfg->get('create_related_comment_to') == 'comment_notification')
                    {
                        //send notificaton
                        app_send_new_comment_notification($comments_id, $item_id, $current_entity_id);

                        //track changes
                        if (class_exists('track_changes'))
                        {
                            $log = new track_changes($current_entity_id, $item_id);
                            $log->log_comment($comments_id, array());
                        }
                    }
                }

                break;
            }
        }
    }

    public static function autocreate_comments_delete($current_entity_id, $item_id, $related_entities_id, $related_items_id)
    {
        global $app_user;

        $field_info_query = db_query("select id, name, configuration from app_fields where entities_id='" . $related_entities_id . "' and type='fieldtype_related_records'");
        while ($field_info = db_fetch_array($field_info_query))
        {
            $cfg = new fields_types_cfg($field_info['configuration']);

            if ($cfg->get('entity_id') == $current_entity_id)
            {

                if (in_array($cfg->get('delete_related_comment'), ['comment', 'comment_notification']))
                {

                    $item_info = db_find('app_entity_' . $current_entity_id, $item_id);
                    $fieldtype_text_pattern = new fieldtype_text_pattern();
                    $description = $fieldtype_text_pattern->output_singe_text($cfg->get('delete_related_comment_text'), $current_entity_id, $item_info);

                    $sql_data = array(
                        'description' => db_prepare_html_input($description),
                        'entities_id' => $related_entities_id,
                        'items_id' => $related_items_id,
                        'attachments' => '',
                        'date_added' => time(),
                        'created_by' => $app_user['id']
                    );

                    db_perform('app_comments', $sql_data);

                    $comments_id = db_insert_id();

                    if ($cfg->get('delete_related_comment') == 'comment_notification')
                    {
                        //send notificaton
                        app_send_new_comment_notification($comments_id, $related_items_id, $related_entities_id);

                        //track changes
                        if (class_exists('track_changes'))
                        {
                            $log = new track_changes($related_entities_id, $related_items_id);
                            $log->log_comment($comments_id, array());
                        }
                    }
                }

                if (in_array($cfg->get('delete_related_comment_to'), ['comment', 'comment_notification']))
                {

                    $related_item_info = db_find('app_entity_' . $related_entities_id, $related_items_id);
                    $fieldtype_text_pattern = new fieldtype_text_pattern();
                    $description = $fieldtype_text_pattern->output_singe_text($cfg->get('delete_related_comment_to_text'), $related_entities_id, $related_item_info);

                    $sql_data = array(
                        'description' => db_prepare_html_input($description),
                        'entities_id' => $current_entity_id,
                        'items_id' => $item_id,
                        'attachments' => '',
                        'date_added' => time(),
                        'created_by' => $app_user['id']
                    );

                    db_perform('app_comments', $sql_data);

                    $comments_id = db_insert_id();

                    if ($cfg->get('delete_related_comment_to') == 'comment_notification')
                    {
                        //send notificaton
                        app_send_new_comment_notification($comments_id, $item_id, $current_entity_id);

                        //track changes
                        if (class_exists('track_changes'))
                        {
                            $log = new track_changes($current_entity_id, $item_id);
                            $log->log_comment($comments_id, array());
                        }
                    }
                }

                break;
            }
        }
    }

    function add_related_record($entities_id, $items_id, $related_items_id)
    {
        $related_entities_id = $this->cfg->get('entity_id');
        $related_items_id = $related_items_id;

        $table_info = related_records::get_related_items_table_name($entities_id, $related_entities_id);

        $sql_data = array('entity_' . $entities_id . '_items_id' => $items_id,
            'entity_' . $related_entities_id . $table_info['sufix'] . '_items_id' => $related_items_id);

        db_perform($table_info['table_name'], $sql_data);
    }

}
