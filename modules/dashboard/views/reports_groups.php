<?php 
if($app_user['group_id']==0)
{ 
    $breadcrumb = [];
    $breadcrumb[] = '<li>' . link_to(TEXT_EXT_COMMON_REPORTS_GROUPS, url_for('ext/reports_groups/reports')) . '<i class="fa fa-angle-right"></i></li>';
    $breadcrumb[] = '<li>' . $reports_groups_info['name'] . '</li>';
?>
<ul class="page-breadcrumb breadcrumb">
    <?php echo implode('', $breadcrumb) ?>  
</ul>

    <div class="dashboard-reports-config hidden-xs hidden-sm" style="margin-right: 0;">
            <div class="toggler" title="<?php echo TEXT_NAV_VIEW_CONFIG ?>" onClick="open_dialog('<?php echo url_for('dashboard/reports_groups_configure','id=' . $app_reports_groups_id) ?>')">
                    <i class="fa fa-bars"></i>
            </div>
    </div>  
    <h3 class="page-title"><?php echo $reports_groups_info['name'] ?></h3>
    <p><?php echo TEXT_EXT_COMMON_REPORTS_GROUPS_INFO ?></p>
<?php
}

echo '<div id="dashboard-reports-group-container" data_id="' . $reports_groups_info['id'] . '"></div>';

app_reset_selected_items();

//tabs
echo reports_groups::render_dashboard_tabs();

$has_reports_on_dashboard = false;

$reports_groups_info = db_find('app_reports_groups',_get::int('id'));

if(strlen($reports_groups_info['counters_list']))
{	
	$reports_counter = new reports_counter;
	$reports_counter->title = $reports_groups_info['name'];
	$reports_counter->reports_query = "select r.*,e.name as entities_name,e.parent_id as entities_parent_id from app_reports r, app_entities e where e.id=r.entities_id  and r.reports_type in ('common') and find_in_set(" . $app_user['group_id'] . ",r.users_groups) and  r.id in (" . $reports_groups_info['counters_list'] . ") order by field(r.id," . $reports_groups_info['counters_list'] . ")";
	$html = $reports_counter->render();
	if(strlen($html))
	{
		echo $html;	
	}
}

//include sections
require(component_path('dashboard/sections'));

if(strlen($reports_groups_info['reports_list']))
{
	$reports_query = db_query("select * from app_reports where id in (" . $reports_groups_info['reports_list'] . ") and reports_type in ('common') and find_in_set(" . $app_user['group_id'] . ",users_groups) order by field(id," . $reports_groups_info['reports_list'] . ")");
	while($reports = db_fetch_array($reports_query))
	{
		
		$check_query = db_query("select id from app_reports_sections where (report_left='standard{$reports['id']}' or report_right='standard{$reports['id']}') and reports_groups_id={$reports_groups_info['id']}");
		if($check = db_fetch_array($check_query))
		{
			echo '
			<div class="row">
        <div class="col-md-12"><h3 class="page-title"><a href="' . url_for('reports/view','reports_id=' . $reports['id']) . '">' . $reports['name'] . '</a></h3></div>
      </div>
			<div class="alert alert-warning">' . TEXT_REPORT_ALREADY_ASSIGNED . '</div>';
		}
		else
		{
                        $use_redirect_to = 'reports_groups' . $reports_groups_info['id'];
			require(component_path('dashboard/render_standard_reports'));
		}
		
				
	}
}

require(component_path('items/load_items_listing.js'));