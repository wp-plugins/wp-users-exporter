<?php
/*
Plugin Name: WP Users Exporter
Plugin URI: 
Description: Users Exporter
Author: hacklab
Version: 0.1 
Text Domain: 
*/
define('WPUE_PREFIX', 'wpue-');

add_action('init', 'wpue_init');
register_activation_hook(__FILE__, 'wpue_activate');
register_deactivation_hook(__FILE__, 'wpue_deactivate');

function wpue_init(){
    
    require_once dirname(__FILE__).'/A_UserExporter.class.php';
    
    
    $dir = opendir(dirname(__FILE__).'/exporters/');
    while (false !== ($d = readdir($dir))){
        if(strpos($d,'class.php')){
    	    require_once dirname(__FILE__).'/exporters/'.$d;
    	}
    }
    add_action('admin_menu', 'wpue_admin_menu');
    
    if(isset($_POST[WPUE_PREFIX.'action'])){
        switch($_POST[WPUE_PREFIX.'action']){
            case 'export-users':
                if(isset($_POST['roles']) && isset($_POST['exporter'])){
                    $result = wpue_getUsers();
                    eval('$exporter = new '.$_POST['exporter'].'($result);');
                    $exporter->export();
                    die;
                }
            break;
            
            case 'save-config':
                $wpue_options = wpue_getDefaultConfig();
                $wpue_config = new stdClass();
                global $wp_roles;
                
                foreach ($wp_roles->roles as $r) {
            		if ($r['name'] != 'Administrator') {
	            		$role = get_role(strtolower($r['name']));
	            		if ($_POST[$r['name']]) {
	            			$role->add_cap('use-wp-users-exporter');
	            		} else {
	            			$role->remove_cap('use-wp-users-exporter');
	            		}
            		}
            	}
                
                
                /* BUDDYPRESS EDITION - begin */
                if(wpue_isBP()){
	                foreach ($wpue_options->bp_fields as $id){
	                    if(isset($_POST['bp_fields'][$id])) 
	                        $wpue_config->bp_fields[$id] = $id;
	                }
                }
                /* BUDDYPRESS EDITION - end */
                
                foreach ($wpue_options->metadata as $k=>$n){
                    if(isset($_POST['metadata'][$k])) 
                        $wpue_config->metadata[$k] = $_POST['metadata_desc'][$k];
                }
                
                foreach ($wpue_options->userdata as $k=>$n){
                    if(isset($_POST['userdata'][$k])) 
                        $wpue_config->userdata[$k] = $_POST['userdata_desc'][$k];
                }
                
                foreach ($wpue_options->roles as $k=>$n){
                    if(isset($_POST['roles'][$k]))
                        $wpue_config->roles[$k] = $_POST['roles_desc'][$k];
                }
                
                foreach ($wpue_options->exporters as $e){
                    if(isset($_POST['exporters'][$e])){ 
                        $wpue_config->exporters[] = $e;
                        eval("{$e}::saveOptions();");
                    }
                }
                
                $wpue_config->date_format = $_POST['date_format'];
                
                wpue_setConfig($wpue_config);
            break;
        }
    }
}

function wpue_admin_menu(){

    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-sortable');
    add_submenu_page("tools.php", __('Export Users','wp-users-exporter'), __('Export Users','wp-users-exporter'), 'use-wp-users-exporter', WPUE_PREFIX.'wpue-main', 'wpue_page');
    add_options_page(__('WP Users Exporter','wp-users-exporter'), __('Exportador de Usuários', 'wp-users-exporter'), 'manage-wp-users-exporter', 'wpue-config', 'wpue_config_page');    

}

function wpue_config_page(){
    include dirname(__FILE__).'/config.php';
}
    
function wpue_page(){
    include dirname(__FILE__).'/page.php';
}

function wpue_activate(){
    $wpue_config = wpue_getDefaultConfig();
    $wpue_config->metadata = array();
    if(get_option('wpue-config'))
        delete_option('wpue-config');
    
    add_option('wpue-config', $wpue_config);
    
    $admin = get_role('administrator');   
    $admin->add_cap('manage-wp-users-exporter');
    $admin->add_cap('use-wp-users-exporter');

    
    $exporters = wpue_getExporters();
    foreach ($exporters as $exporter)
        eval("{$exporter}::activate();");
}

function wpue_deactivate(){
    $exporters = wpue_getExporters();
    foreach ($exporters as $exporter)
        eval("{$exporter}::deactivate();"); 
    delete_option('wpue-config');
}

function wpue_getExporters(){
    require_once dirname(__FILE__).'/A_UserExporter.class.php';
    $exporters = array();
    $dir = opendir(dirname(__FILE__).'/exporters/');
    while (false !== ($d = readdir($dir))){
        if(strpos($d,'class.php')){
    	    require_once dirname(__FILE__).'/exporters/'.$d;
    		$exporters[] = substr($d, 0, -10);
    	}
    }	
    return $exporters;
}

function wpue_getConfig(){
    $wpue_config = get_option('wpue-config');
    $ok = true;
    foreach ($wpue_config->exporters as $exporter)
        if(!class_exists($exporter))
            $ok = false;

    if(!$ok){
        $wpue_config->exporters = wpue_getExporters();
        wpue_setConfig($wpue_config);
    }
    return $wpue_config;
}

function wpue_setConfig($config){
    if(!$config->metadata)
        $config->metadata = array();

    if(!$config->userdata)
        $config->userdata = array();
    
    if(!$config->exporters)
        $config->exporters = array();
    
    update_option('wpue-config', $config);
}

/**
 * Retorna um array com as configurações padrões
 * @return array config
 */
function wpue_getDefaultConfig(){
    global $wpdb, $wp_roles;
    $wpue_options = new stdClass();

    $metakeys = $wpdb->get_col("SELECT DISTINCT meta_key FROM $wpdb->usermeta");
    
    $wpue_options->userdata = array(
    	'ID' => __('ID','wp-users-exporter'),
        'user_login' => __('Login','wp-users-exporter'),
        'user_nicename' => __('Nice Name','wp-users-exporter'),
        'user_email' => __('E-Mail','wp-users-exporter'),
    	'user_url' => __('URL','wp-users-exporter'),
    	'user_registered' => __('Data de Registro','wp-users-exporter'),
    	'user_status' => __('Status do Usuário','wp-users-exporter'),
    	'display_name' => __('Nome','wp-users-exporter')
    );
    foreach ($metakeys as $metakey)
        $wpue_options->metadata[$metakey] = $metakey;
    
    foreach ($wp_roles->roles as $role => $r)
        $wpue_options->roles[$role] = $r['name'];
        
    /* BUDDYPRESS EDITION - begin */
    if(wpue_isBP()){
    	$fields = wpue_bp_getProfileFields();
    	foreach ($fields as $field)
    		$wpue_options->bp_fields[$field->id] = $field->id; 
    }
    /* BUDDYPRESS EDITION - end */
        
    $wpue_options->exporters = wpue_getExporters();
    
    $wpue_options->date_format = 'Y-m-d';
    
    return $wpue_options;
}


function wpue_getUsers(){
    global $wpdb;
    $roles = $_POST['roles'];
    
    foreach ($roles as $k=>$role)
        $roles[$k] = "meta_value LIKE '%\"$role\"%'";
        
    $metakeys = implode(' OR ', $roles);
    $udata = $_POST['userdata'];
    if(!in_array('ID', $udata))
        $udata[] = 'ID';
        
        
    
    $cols = implode(',' , $udata);
    $orderby = $_POST['order'];
    $oby = $_POST['oby'] == 'ASC' ? 'ASC' : 'DESC';
    
    // filtro
    if(isset($_POST['filter']) && trim($_POST['filter']) != ''){
        $field = $_POST['filter'];
        $value = $_POST['filter_value'];
        if($field[0] == '_'){
            $field = substr($field, 1);
            switch($_POST['operator']){
                case 'eq':
                    $filter = "AND ID IN (SELECT user_id FROM $wpdb->usermeta WHERE meta_key='$field' AND meta_value = '$value')";
                break;
                case 'dif':
                    $filter = "AND ID NOT IN (SELECT user_id FROM $wpdb->usermeta WHERE meta_key='$field' AND meta_value = '$value')";
                break;
                case 'like':
                    $filter = "AND ID IN (SELECT user_id FROM $wpdb->usermeta WHERE meta_key='$field' AND meta_value LIKE '%$value%')";
                break;
                case 'not-like':
                    $filter = "AND ID NOT IN (SELECT user_id FROM $wpdb->usermeta WHERE meta_key='$field' AND meta_value LIKE '%$value%')";
                break;
            }
        }else {
            switch($_POST['operator']){
                case 'eq':
                    $filter = "AND $field = '$value')";
                break;
                case 'dif':
                    $filter = "AND $field <> '$value')";
                break;
                case 'like':
                    $filter = "AND $field LIKE '%$value%')";
                break;
                case 'not-like':
                    $filter = "AND $field NOT LIKE '%$value%')";
                break;
            }
        }
        
        
    }else{
        $filter = '';
    }
    
    
    // seleciona os usuários
    $q = "
    	SELECT 
    		$cols
    	FROM 
            $wpdb->users 
        WHERE 
        	ID IN (	SELECT 
        				user_id 
        			FROM 
                        $wpdb->usermeta 
                    WHERE 
                    	meta_key = '{$wpdb->prefix}capabilities' AND 
                    	($metakeys)
                   )
            $filter
        ORDER BY $orderby $oby";
                        
    $users = $wpdb->get_results($q);
    
    $wpue_config = wpue_getConfig();
    $user_ids = array();
    // limpa o usuário, removendo as propriedades que não foram selecionadas no formulario
    // de exportação
    $result = array();
    foreach($users as $user){
        $user_ids[] = $user->ID;
        $result[$user->ID] = $user;
    }
    
    unset($users);
    
    // seleciona os metadados to usuário
    $user_ids = implode(',', $user_ids);
    
    $metakeys = array();
    $metakeys = array_keys($wpue_config->metadata);
    $metakeys = "'".implode("','", $metakeys)."'";
    
    $qm = "
    	SELECT
    		user_id,
    		meta_key,
    		meta_value
    	FROM
    		$wpdb->usermeta
    	WHERE
    		meta_key IN ($metakeys) AND
    		user_id IN ($user_ids)";
    
    $rs = mysql_query($qm);
    while ($metadata = mysql_fetch_object($rs)){
    
        $meta_key = $metadata->meta_key;
        $meta_value = $metadata->meta_value;
        $user_id = $metadata->user_id;
        
        $result[$user_id]->$meta_key = isset($result[$user_id]->$meta_key) ? ($result[$user_id]->$meta_key).", ".$meta_value : $meta_value;
        
        if(is_object($result[$user_id]->$meta_key))
    		$result[$user_id]->$meta_key = (array) $result[$user_id]->$meta_key;
    		
    	if(is_array($result[$user_id]->$meta_key))
    		$result[$user_id]->$meta_key = implode(', ', $result[$user_id]->$meta_key);
    		
    }
    
    
    /* BUDDYPRESS EDITION */
    
    if(wpue_isBP()){
    	$field_ids = implode(',', $wpue_config->bp_fields);
    	
    	$bp_fields = wpue_bp_getProfileFields();
    	
    	$bp_data_query = "
    	SELECT 
    		* 
    	FROM 
    		{$wpdb->prefix}bp_xprofile_data 
    	WHERE 
    		user_id IN ($user_ids) AND 
    		field_id IN ($field_ids)
    	ORDER BY 
    		user_id ASC";
		
		
    	$bp_data = $wpdb->get_results($bp_data_query);
    	
    	
    	foreach ($bp_data as $data){
    		$field = 'bp_'.$data->field_id;
    		if($bp_fields[$data->field_id]->type == 'datebox')
    			$data->value = date($wpue_config->date_format, $data->value);
    			
    		if(is_serialized($data->value))
    			$data->value = unserialize($data->value);
    			
    		if(is_object($data->value))
    			$data->value = (array) $data->value;
    		
    		if(is_array($data->value))
    			$data->value = implode(', ', $data->value);
    			
    		$result[$data->user_id]->$field = $data->value;
    	}
    }
    
    return $result;
}



/* BUDDYPRESS EDITION - begin */

/**
 * is buddypress
 */
function wpue_isBP(){
	return defined('BP_VERSION');
}

function wpue_bp_getProfileFields(){
	global $wpdb, $wpue_bp_fields;
	
	// runtime cache
	if(isset($wpue_bp_fields) && is_array($wpue_bp_fields))
		return $wpue_bp_fields;
		
	$q = "
	SELECT 
		* 
	FROM 
		{$wpdb->prefix}bp_xprofile_fields 
	WHERE 
		parent_id = 0 
	ORDER BY 
		group_id ASC, 
		name ASC";
		
	$result = $wpdb->get_results($q);
	
	foreach ($result as $field)
		$wpue_bp_fields[$field->id] = $field;
		
	return $wpue_bp_fields;
}

/* BUDDYPRESS EDITION - end */
