<?php

class filters_preivew
{

    public $reports_id;
    public $include_paretn_filters;
    public $redirect_to;
    public $count_filters;
    public $path;
    public $has_listing_configuration;
    public $has_listing_configuration_fields;
    public $filters_panels_fields;

    function __construct($report_id, $include_paretn_filters = true)
    {
        $this->reports_id = $report_id;

        $this->include_paretn_filters = $include_paretn_filters;

        $this->redirect_to = 'report';

        $this->path = '';

        $this->count_filters = $this->count_filters();

        $this->has_listing_configuration = true;
        $this->has_listing_configuration_fields = true;
    }

    static function has_default_panel_access($entity_cfg)
    {
        global $app_user;

        $panel_access = (strlen($entity_cfg->get('default_filter_panel_access')) ? explode(',', $entity_cfg->get('default_filter_panel_access')) : []);

        if($entity_cfg->get('default_filter_panel_status', 1) == 0)
        {
            return false;
        }
        elseif(count($panel_access) and!in_array($app_user['group_id'], $panel_access))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    function count_filters()
    {
        global $app_module_path;
        $report_info = db_find('app_reports', $this->reports_id);

        $filters_panels_fields = [];

        if($app_module_path == 'items/items')
        {
            $filters_panels_fields = filters_panels::get_fields_list($report_info['entities_id']);
        }

        $count = 0;
        $filters_query = db_query("select count(*) as total from app_reports_filters rf, app_fields f  where rf.fields_id=f.id and rf.reports_id='" . db_input($this->reports_id) . "' " . (count($filters_panels_fields) ? " and f.id not in (" . implode(',', $filters_panels_fields) . ")" : '') . " order by rf.id", false);
        $filters = db_fetch_array($filters_query);

        $count += $filters['total'];

        if($this->include_paretn_filters)
        {
            foreach(reports::get_parent_reports($this->reports_id) as $parent_reports_id)
            {
                $filters_query = db_query("select count(*) as total from app_reports_filters rf, app_fields f  where rf.fields_id=f.id and rf.reports_id='" . db_input($parent_reports_id) . "' order by rf.id");
                $filters = db_fetch_array($filters_query);

                $count += $filters['total'];
            }
        }

        return $count;
    }

    function get_applied_filters_name()
    {
        global $app_current_users_filter;

        if(isset($app_current_users_filter[$this->reports_id]))
        {
            return (strlen($app_current_users_filter[$this->reports_id]) ? $app_current_users_filter[$this->reports_id] : TEXT_APPLIED_FILTERS);
        }
        else
        {
            return TEXT_APPLIED_FILTERS;
        }
    }

    function render()
    {
        $portlets = new portlets('filters_preview_' . $this->reports_id, ($this->count_filters == 0 ? true : false));

        $html = '
      <div class="portlet portlet-filters-preview noprint">
      	<div class="portlet-title">
      		<div class="caption">        
            ' . $this->render_users_filters() . ' ' . $this->render_add_button() . ' ' . $this->get_applied_filters_name() . '            
          </div>' .
                ($this->has_listing_configuration ?
                '<div class="actions">
            ' . $this->render_listing_configuration_button() . '
          </div>' : '') .
                '<div class="tools">            
      		<a href="javascript:;" class="' . $portlets->button_css() . '"></a>
            </div>
      	</div>
      	<div class="portlet-body" ' . $portlets->render_body() . '>
          ' . ($this->count_filters == 0 ? TEXT_NO_FILTERS_SETUP : $this->render_filters()) . '
        </div>
      </div>    
    ';

        return $html;
    }

    function render_listing_configuration_button()
    {
        $html = '    
      <div class="btn-group">
				<a class="btn dropdown-toggle" href="#" data-toggle="dropdown" data-hover="dropdown">
				<i class="fa fa-gear"></i></i>
				</a>
				<ul class="dropdown-menu pull-right">
					<li>						
            ' . link_to_modalbox('<i class="fa fa-sort-amount-asc"></i> ' . TEXT_HEADING_REPORTS_SORTING, url_for('reports/sorting', 'reports_id=' . $this->reports_id . '&redirect_to=' . $this->redirect_to . (strlen($this->path) > 0 ? '&path=' . $this->path : ''))) . '
            ' . ($this->has_listing_configuration_fields ? link_to_modalbox('<i class="fa fa-wrench"></i> ' . TEXT_NAV_LISTING_CONFIG, url_for('reports/configure', 'reports_id=' . $this->reports_id . '&redirect_to=' . $this->redirect_to . (strlen($this->path) > 0 ? '&path=' . $this->path : ''))) : '') . '
            
					</li>
				</ul>
			</div>    
    ';

        return $html;
    }

    function render_add_button()
    {
        $url_params = '&redirect_to=' . $this->redirect_to;

        if(strlen($this->path) > 0)
        {
            $url_params .= '&path=' . $this->path;
        }

        $report_info = db_find('app_reports', $this->reports_id);
        $entity_info = db_find('app_entities', $report_info['entities_id']);

        $dropdown_html = '      
        <li>				        
          ' . link_to_modalbox(TEXT_FILTERS_FOR_ENTITY_SHORT . ': <b>' . $entity_info['name'] . '</b>', url_for('reports/filters_form', 'reports_id=' . $this->reports_id . $url_params)) . '
  			</li>
        ';

        if($this->include_paretn_filters)
        {
            foreach(reports::get_parent_reports($this->reports_id) as $parent_reports_id)
            {
                $report_info = db_find('app_reports', $parent_reports_id);

                //skip entities without access
                if(!users::has_users_access_name_to_entity('view', $report_info['entities_id']))
                    continue;

                $entity_info = db_find('app_entities', $report_info['entities_id']);

                $dropdown_html .= '      
            <li>				        
              ' . link_to_modalbox(TEXT_FILTERS_FOR_ENTITY_SHORT . ': <b>' . $entity_info['name'] . '</b>', url_for('reports/filters_form', 'reports_id=' . $this->reports_id . '&parent_reports_id=' . $parent_reports_id . $url_params)) . '
      			</li>
            ';
            }
        }

        $html = '      
        <div class="btn-group">
  				<button title="' . htmlspecialchars(TEXT_BUTTON_ADD_NEW_REPORT_FILTER) . '" class="btn dropdown-toggle btn-users-filters" type="button" data-toggle="dropdown" data-hover="dropdown"><i class="fa fa-plus"></i></button>
  				<ul class="dropdown-menu" role="menu">
  					' . $dropdown_html . '
  				</ul>
  			</div>                  
      ';

        return $html;
    }

    function render_users_filters()
    {
        global $app_module_path;

        $url_params = '&redirect_to=' . $this->redirect_to . '&reports_id=' . $this->reports_id . (strlen($this->path) > 0 ? '&path=' . $this->path : '');

        $filters_html = '';

        $users_filters = new users_filters($this->reports_id);

        foreach($users_filters->get_choices() as $id => $name)
        {
            $filters_html .= '
        <li>
  				<a href="' . url_for('reports/users_filters', 'action=use&id=' . $id . $url_params) . '"><i class="fa fa-angle-right"></i>' . $name . '</a>
  			</li>';
        }

        //use defaulf filters for entities listing only
        $report_info = db_find('app_reports', $this->reports_id);
        if(in_array($report_info['reports_type'], array('entity_menu', 'entity')))
        {
            $filters_html .= '
	      <li>
					<a href="' . url_for('reports/users_filters', 'action=use&id=default' . $url_params) . '"><i class="fa fa-angle-right"></i>' . TEXT_DEFAULT_FILTERS . '</a>
				</li>';
        }

        $filters_html .= '
      <li class="divider"></li>
      <li>				        
        ' . link_to_modalbox('<i class="fa fa-floppy-o"></i> ' . TEXT_SAVE_FILTERS, url_for('reports/users_filters_form', $url_params)) . '
			</li>
    ';

        if($users_filters->count() > 0)
        {
            $filters_html .= '      
        <li>				        
          ' . link_to_modalbox('<i class="fa fa-trash-o"></i> ' . TEXT_DELETE_FILTERS, url_for('reports/users_filters_delete', $url_params)) . '
  			</li>
    ';
        }

        $html = '      
        <div class="btn-group">
  				<button class="btn dropdown-toggle btn-users-filters" type="button" data-toggle="dropdown" data-hover="dropdown"><i class="fa fa-angle-down"></i></button>
  				<ul class="dropdown-menu" role="menu">
  					' . $filters_html . '
  				</ul>
  			</div>                  
      ';

        return $html;
    }

    function render_filters()
    {
        $html = '';

        $html .= $this->render_filters_by_report($this->reports_id);

        if($this->include_paretn_filters)
        {
            foreach(reports::get_parent_reports($this->reports_id) as $parent_reports_id)
            {
                $html .= $this->render_filters_by_report($parent_reports_id, true);
            }
        }

        return $html;
    }

    function render_filters_by_report($reports_id, $is_parent = false)
    {
        global $app_module_path;

        $html = '';

        $url_params = '&redirect_to=' . $this->redirect_to;

        if(strlen($this->path) > 0)
        {
            $url_params .= '&path=' . $this->path;
        }

        if($is_parent)
        {
            $url_params .= '&reports_id=' . $this->reports_id . '&parent_reports_id=' . $reports_id;
        }
        else
        {
            $url_params .= '&reports_id=' . $reports_id;
        }

        $report_info = db_find('app_reports', $reports_id);
        $entity_info = db_find('app_entities', $report_info['entities_id']);

        $filters_panels_fields = [];

        if($app_module_path == 'items/items')
        {
            $filters_panels_fields = filters_panels::get_fields_list($report_info['entities_id']);
        }

        $filters_query = db_query("select rf.*, f.name, f.type, f.entities_id from app_reports_filters rf, app_fields f  where rf.fields_id=f.id and rf.reports_id='" . db_input($reports_id) . "' order by rf.id");
        while($v = db_fetch_array($filters_query))
        {
            //skip fields in quick filter panel
            if(in_array($v['fields_id'], $filters_panels_fields))
                continue;

            $edit_url = url_for('reports/filters_form', 'id=' . $v['id'] . $url_params);
            $delete_url = url_for('reports/filters', 'action=delete&id=' . $v['id'] . $url_params);

            if(in_array($v['filters_condition'], array('empty_value', 'not_empty_value', 'filter_by_overdue', 'filter_by_overdue_with_time')))
            {
                $fitlers_values = reports::get_condition_name_by_key($v['filters_condition']);
            }
            else
            {
                $fitlers_values = reports::render_filters_values($v['fields_id'], $v['filters_values'], ', ', $v['filters_condition']);
            }

            $html .= '
        <li>
          <div class="filters-preview-box is-active-' . $v['is_active'] . '">
          		
          	' . ($v['is_active'] ? $this->render_filters_templates($v['fields_id'], $v['id'], $url_params) : '') . '	
                ' . $this->render_toggler($v) . '    
                    <span class="filters-preview-box-heading" onClick="open_dialog(\'' . $edit_url . '\')"  title="' . htmlspecialchars(TEXT_BUTTON_EDIT) . '"><b>' . fields_types::get_option($v['type'], 'name', $v['name']) . '</b><i class="fa fa-angle-right"></i>' .
                    '<span class="filters-preview-condition-' . $v['filters_condition'] . '">' . $fitlers_values . '</span>' .
                    '</span>' .
                    link_to('<i class="fa fa-trash-o" title="' . htmlspecialchars(TEXT_BUTTON_REMOVE_FILTER) . '"></i>', $delete_url) .
                    '</div>
        </li>';
        }

        if(strlen($html) > 0)
        {

            $html = ($is_parent ? '<div class="divider"></div>' : '') .
                    '<ul class="list-inline">
            <li>' .
                    link_to('<i class="fa fa-trash-o"></i> ', url_for('reports/filters', 'action=delete&id=all' . $url_params), array('title' => TEXT_BUTTON_REMOVE_ALL_FILTERS, 'class' => 'btn btn-default')) . ' ' .
                    link_to_modalbox($entity_info['name'] . ':', url_for('reports/filters_form', $url_params), array('title' => TEXT_BUTTON_ADD_NEW_REPORT_FILTER, 'class' => 'btn btn-default')) . '&nbsp;' .
                    '</li>
            ' . $html . '
          </ul>
          
          <script>
            $(function(){
                app_filters_preview_toggle()
            })
          </script>
        ';
        }



        return $html;
    }

    function render_toggler($filter)
    {
        global $app_module_path;
        
        if(!in_array($app_module_path,['items/items','reports/view'])) return '';
        
        $html = '<span title="' . ($filter['is_active'] ? TEXT_TOGGLE_OFF : TEXT_TOGGLE_ON). '" '
                . 'class="filters-preview-toggle ' . ($filter['is_active'] ? 'active':''). '" '
                . 'cfg_report_id="' .  $filter['reports_id'] . '" '
                . 'cfg_entity_id="' .  $filter['entities_id'] . '" '
                . 'cfg_fitler_id="' . $filter['id'] . '">' . ($filter['is_active'] ? '<i class="fa fa-toggle-on"></i>':'<i class="fa fa-toggle-off"></i>'). '</span>';
        
        return $html;
    }

    function render_filters_templates($fields_id, $filters_id, $url_params)
    {
        global $app_logged_users_id;

        $html = '';
        $html_templates = '';
        $dropdown_menu_width_auto = true;

        $templates_query = db_query("select * from app_reports_filters_templates where users_id='" . db_input($app_logged_users_id) . "' and fields_id='" . db_input($fields_id) . "'");
        while($templates = db_fetch_array($templates_query))
        {
            if(in_array($templates['filters_condition'], array('empty_value', 'not_empty_value', 'filter_by_overdue', 'filter_by_overdue_with_time')))
            {
                $fitlers_values = reports::get_condition_name_by_key($templates['filters_condition']);
            }
            else
            {
                $fitlers_values = reports::render_filters_values($templates['fields_id'], $templates['filters_values'], ', ', $templates['filters_condition']);
            }

            if(strlen($fitlers_values) > 50)
            {
                $dropdown_menu_width_auto = false;
            }

            $html_templates .= '
	  		<li class="li-templates-' . $templates['id'] . '">
	  			<a href="javascript: delete_filters_templates(' . $templates['id'] . ')" data-url="' . url_for('reports/filters', 'action=delete_filters_templates&templates_id=' . $templates['id'] . '&id=' . $filters_id . $url_params) . '" class="action a-templates-' . $templates['id'] . '"><i class="fa fa-trash-o" title="' . addslashes(TEXT_BUTTON_DELETE) . '"></i></a>
  				<a href="' . url_for('reports/filters', 'action=use_filters_template&templates_id=' . $templates['id'] . '&id=' . $filters_id . $url_params) . '">' . $fitlers_values . '</a>	  			
	  		</li>
  		';
        }

        if(strlen($html_templates) > 0)
        {
            $html = '
		  	<div class="btn-group">
	  			<button class="btn dropdown-toggle btn-filters-templates" type="button" data-toggle="dropdown" data-hover="dropdown"><i class="fa fa-angle-down"></i></button>
	  				<ul class="dropdown-menu dropdown-menu-with-action ' . ($dropdown_menu_width_auto ? 'dropdown-menu-width-auto' : '') . '" role="menu">
	  				' . $html_templates . '		     
	  				</ul>
	  		</div>';
        }

        return $html;
    }

}
