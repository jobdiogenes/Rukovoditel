<?php

$users_groups_info_query = db_query("select * from app_access_groups where id='" . _get::int('id') . "'");
if(!$users_groups_info = db_fetch_array($users_groups_info_query))
{
    redirect_to('users_groups/users_groups');
}

switch($app_module_action)
{
    case 'set_access':
        if(isset($_POST['access'][$_GET['entities_id']]))
        {
            $access_schema = $_POST['access'][$_GET['entities_id']];

            $access_schema = access_groups::prepare_entities_access_schema($access_schema);

            $sql_data = array('access_schema' => implode(',', $access_schema));

            $acess_info_query = db_query("select access_schema from app_entities_access where entities_id='" . db_input($_GET['entities_id']) . "' and access_groups_id='" . $users_groups_info['id'] . "'");
            if($acess_info = db_fetch_array($acess_info_query))
            {
                db_perform('app_entities_access', $sql_data, 'update', "entities_id='" . db_input($_GET['entities_id']) . "' and access_groups_id='" . $users_groups_info['id'] . "'");
                
                //reset duplicates
		db_query("delete from app_entities_access where entities_id='" . db_input($_GET['entities_id']) . "' and access_groups_id='" . $users_groups_info['id']. "' and id!='" . $acess_info['id'] . "'");
            }
            else
            {
                $sql_data['entities_id'] = $_GET['entities_id'];
                $sql_data['access_groups_id'] = $users_groups_info['id'];
                db_perform('app_entities_access', $sql_data);
            }
        }

        if(isset($_POST['comments_access']))
        {
            $access = $_POST['comments_access'][$_GET['entities_id']];

            $sql_data = array('access_schema' => str_replace('_', ',', $access));

            $acess_info_query = db_query("select access_schema from app_comments_access where entities_id='" . db_input($_GET['entities_id']) . "' and access_groups_id='" . $users_groups_info['id'] . "'");
            if($acess_info = db_fetch_array($acess_info_query))
            {
                db_perform('app_comments_access', $sql_data, 'update', "entities_id='" . db_input($_GET['entities_id']) . "' and access_groups_id='" . $users_groups_info['id'] . "'");
            }
            else
            {
                $sql_data['entities_id'] = $_GET['entities_id'];
                $sql_data['access_groups_id'] = $users_groups_info['id'];
                db_perform('app_comments_access', $sql_data);
            }
        }

        exit();
        break;
}