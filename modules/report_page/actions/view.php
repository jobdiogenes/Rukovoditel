<?php

$report_page_query = db_query("select * from app_ext_report_page where id='" . _GET('id'). "' and (find_in_set({$app_user['group_id']},users_groups) or find_in_set({$app_user['id']},assigned_to)) order by sort_order, name");
if(!$report_page = db_fetch_array($report_page_query))
{
    redirect_to_forbidden();
}

if (!app_session_is_registered('report_page_filters')) 
{
  $report_page_filters = array();
  app_session_register('report_page_filters');    
} 

if(strlen($app_path))
{
    $path = items::parse_path($app_path);
    $current_entity_id = $path['entity_id'];
    $current_item_id = $path['item_id'];
    
    $current_path_array = explode('/', $app_path);
    $app_breadcrumb = items::get_breadcrumb($current_path_array);
}
else
{
    $current_entity_id = false;
    $current_item_id = false;
}

switch($app_module_action)
{
    case 'load':
        
        //set filters
        report_page\report_filters::set_filters();
        
        $page = new report_page\report($report_page);
        
        //set item if exist
        if($current_item_id)
        {
            $page->set_item($current_entity_id,$current_item_id);
        }
        echo $page->get_html();
        
        exit();
        break;
}
  
