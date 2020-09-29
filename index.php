<?php
/*

Plugin Name: Shul Admin by Victor Escobar, based on Church Admin by Andy Moyle
Plugin URI: <TBD>
Description: Manage shul life with address book, schedule, classes, small groups, and advanced communication tools - bulk email and sms. 
Version: 0.1
Author: Victor Escobar
Text Domain: shul-admin


Author URI: <TBD>
License:
----------------------------------------


Copyright (C) 2020 by Victor Escobar



    This program is free software: you can redistribute it and/or modify

    it under the terms of the GNU General Public License as published by

    the Free Software Foundation, either version 3 of the License, or

    (at your option) any later version.



    This program is distributed in the hope that it will be useful,

    but WITHOUT ANY WARRANTY; without even the implied warranty of

    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the

    GNU General Public License for more details.



	http://www.gnu.org/licenses/

----------------------------------------

*/
if ( ! defined( 'ABSPATH' ) ) exit('You need Jesus!'); // Exit if accessed directly
//define('CA_DEBUG',TRUE);
    //sandbox
    //define('CA_PAYPAL',"https://www.sandbox.paypal.com/cgi-bin/webscr");
    //live
    define('CA_PAYPAL',"https://www.paypal.com/cgi-bin/webscr");

	$church_admin_version = '2.6911';
    $church_admin_url='admin.php?page=church_admin/index.php';
	$people_type=get_option('church_admin_people_type');
	$level=get_option('church_admin_levels');
	
	

/******************************************
*
* Blur Cookies set/unset
*
*******************************************/
add_action('admin_init','church_admin_save_forms');
function church_admin_save_forms(){
    
    /*****************************************************************************
    *
    * New save functionality on init so redirect to list page can happen
    *
    ******************************************************************************/
    if(!empty($_POST['church-admin-save']))
    {
        require_once(plugin_dir_path(__FILE__).'includes/church_admin_save.php');
        church_admin_save();
        //church_admin_debug('ServiceSaved');
        exit();
    }
	if(!empty($_GET['page'])&&($_GET['page']=='church_admin/index.php')&&!empty($_GET['action'])&& $_GET['action']=='blur')
	{
		 church_admin_debug('Blur');
		setcookie('churchAdminBlur', 'Blurred', time()+24*60*60,'/' );
		
		 church_admin_debug(print_r($_COOKIE,TRUE));
		 //wp_redirect(admin_url('admin.php?page=church_admin/index.php&action=settings&section=settings'));
		//exit();
	}
	if(!empty($_GET['page'])&&($_GET['page']=='church_admin/index.php')&&!empty($_GET['action'])&& $_GET['action']=='unblur')
	{
		unset( $_COOKIE['churchAdminBlur'] );
    	setcookie( 'churchAdminBlur', FALSE, time() - ( 15 * 60 ),'/' );
		//wp_redirect(admin_url('admin.php?page=church_admin/index.php&action=settings&section=settings'));
		
	}
}
/* initialise plugin */

add_action( 'plugins_loaded', 'church_admin_initialise' );
function church_admin_initialise() {
	global $level,$church_admin_version,$wpdb,$current_user,$church_admin_prayer_request_success,$member_type;
	
	define('CA_PATH',plugin_dir_path( __FILE__));
    

    
	wp_get_current_user();
	church_admin_constants();//setup constants first
	//Version Number
	define('OLD_CHURCH_ADMIN_VERSION',get_option('church_admin_version'));
	if(OLD_CHURCH_ADMIN_VERSION!= $church_admin_version)
	{
		church_admin_backup();
		require_once(plugin_dir_path( __FILE__) .'/includes/install.php');
		church_admin_install();
	}

	$rota_order=ca_rota_order();
	$member_type=church_admin_member_type_array();
	if(!empty($_GET['ca_refresh']))
	{
		delete_option('church-admin-directory-output');
		
	}
	//handle move household to member type
	if(!empty($_POST['move_household_id']))
	{
		 if(ctype_digit($_POST['move_household_id']) &&ctype_digit($_POST['member_type_id']))
		 {
			 $wpdb->query('UPDATE '.CA_PEO_TBL.' SET member_type_id="'.intval($_POST['member_type_id']).'" WHERE household_id="'.intval(($_POST['move_household_id'])).'"');

		 }
	}
    //handle unit save
    if(!empty($_POST['save-unit']))
    {
        if(!empty($_GET['unit_id']))$unit_id=intval($_GET['unit_id']);
        $name=esc_sql(stripslashes($_POST['unit_name']));
        $description=esc_sql(stripslashes($_POST['unit_description']));
        if(empty($unit_id))$unit_id=$wpdb->get_var('SELECT unit_id FROM '.CA_UNI_TBL.' WHERE name="'.$name.'" AND description="'.$description.'"');
        if(empty($unit_id)){$wpdb->query('INSERT INTO '.CA_UNI_TBL.' (name,description)VALUES("'.$name.'","'.$description.'")');}
        else{$wpdb->query('UPDATE '.CA_UNI_TBL.' SET name="'.$name.'",description="'.$description.'" WHERE unit_id="'.intval($unit_id).'"');}
    }
    if(!empty($_POST['save-subunit']))
    {
        if(!empty($_GET['unit_id']))$unit_id=intval($_GET['unit_id']);
        if(!empty($_GET['subunit_id']))$subunit_id=intval($_GET['subunit_id']);
        $name=esc_sql(stripslashes($_POST['unit_name']));
        $description=esc_sql(stripslashes($_POST['unit_description']));
        if(empty($subunit_id))$subunit_id=$wpdb->get_var('SELECT subunit_id FROM '.CA_SUBU_TBL.' WHERE name="'.$name.'" AND description="'.$description.'"');
        if(empty($subunit_id))
        {
            $wpdb->query('INSERT INTO '.CA_SUBU_TBL.' (name,description,unit_id,active)VALUES("'.$name.'","'.$description.'","'.$unit_id.'",1)');
            $subunit_id=$wpdb->insert_id;
            //church_admin_debug($wpdb->last_query);
        }else{$wpdb->query('UPDATE '.CA_SUBU_TBL.' SET name="'.$name.'",description="'.$description.'" WHERE subunit_id="'.intval($subunit_id).'"');}
        //handle people
       $wpdb->query('DELETE FROM '.CA_MET_TBL.' WHERE meta_type="unit" AND ID="'.intval($subunit_id).'"'); $autocompleted=maybe_unserialize(church_admin_get_people_id(trim(stripslashes($_POST['people']))));
        foreach($autocompleted AS $x=>$name)
		{
		  $p_id=church_admin_get_one_id(trim($name));//get the people_id
		  if(!empty($p_id))
		  {
              church_admin_update_people_meta($subunit_id,$p_id,'unit');//update person 
          }
        }
        
    }

    
	//handle reset app menu
	if(!empty($_GET['action'])&&$_GET['action']=='reset-app-menu'&& wp_verify_nonce($_GET['_wpnonce'],'reset-app-menu'))
	{
		delete_option('church_admin_app_new_menu');
		$defaultMenu = array(
                        'home'=>array('edit'=>false,'item'=>__('Home','church-admin'),'order'=>1,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'account'=>array('edit'=>false,'item'=>__('Account','church-admin'),'order'=>2,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'courage'=>array('edit'=>true,'item'=>__('Acts of Courage',"church-admin"),'order'=>3,'show'=>FALSE,'type'=>'app','loggedinOnly'=>0),
						'address'=>array('edit'=>true,'item'=>__('Address','church-admin'),'order'=>4,'show'=>TRUE,'type'=>'app','loggedinOnly'=>1),
						'bible'=>array('edit'=>true,'item'=>__('Bible','church-admin'),'order'=>5,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'calendar'=>array('edit'=>true,'item'=>__('Calendar','church-admin'),'order'=>6,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'checkin'=>array('edit'=>true,'item'=>__('Checkin','church-admin'),'order'=>7,'show'=>TRUE,'type'=>'app','loggedinOnly'=>1),
						'classes'=>array('edit'=>true,'item'=>__('Classes','church-admin'),'order'=>8,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'giving'=>array('edit'=>true,'item'=>__('Giving','church-admin'),'order'=>9,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'smallgroup'=>array('edit'=>true,'item'=>__('Groups','church-admin'),'order'=>10,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'media'=>array('edit'=>true,'item'=>__('Media','church-admin'),'order'=>11,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
				        'messages'=>array('edit'=>TRUE,'item'=>__('Messages','church-admin'),'order'=>12,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'news'=>array('edit'=>true,'item'=>__('News','church-admin'),'order'=>13,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'prayer'=>array('edit'=>true,'item'=>__('Prayer','church-admin'),'order'=>14,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'myprayer'=>array('edit'=>true,'item'=>__('My prayer list','church-admin'),'order'=>15,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'rota'=>array('edit'=>true,'item'=>__('Schedule','church-admin'),'order'=>16,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
						'service-prebooking'=>array('edit'=>TRUE,'item'=>__('Service Prebooking','church-admin'),'order'=>17,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0),
                        'settings'=>array('edit'=>false,'item'=>__('Settings','church-admin'),'order'=>18,'show'=>TRUE,'type'=>'app','loggedinOnly'=>1),
						'logout'=>array('edit'=>false,'item'=>__('Reset church','church-admin'),'order'=>19,'show'=>TRUE,'type'=>'app','loggedinOnly'=>0)
						);
        //church_admin_debug(print_r($defaultMenu,TRUE));
        update_option('church_admin_app_new_menu',$defaultMenu);
		$url=admin_url().'admin.php?page=church_admin%2Findex.php&action=app&section=app&refresh=true';
		wp_redirect( $url );
		exit();
	}
	//handle unconfirm GDPR
	if(!empty($_GET['action'])&&$_GET['action']=='ca_unconfirm_GDPR')
	{
		
		if(current_user_can('manage_options'))
		{
			$wpdb->query('UPDATE '.CA_PEO_TBL.' SET gdpr_reason=NULL');
			
		}	
	}	
	//handle unsubscribe link from email
	if(!empty($_GET['ca_unsub']))
	{
			$details=$wpdb->get_row('SELECT * FROM '.CA_PEO_TBL.' WHERE md5(people_id)="'.esc_sql($_GET['ca_unsub']).'"');
			$wpdb->query('UPDATE '.CA_PEO_TBL.' SET email_send=0 WHERE md5(people_id)="'.esc_sql($_GET['ca_unsub']).'"');
			require_once(plugin_dir_path(__FILE__).'includes/unsubscribe.php');
			exit();
	}
	//handle re-subscribe
	if(!empty($_GET['ca_sub']))
	{
			$details=$wpdb->get_row('SELECT * FROM '.CA_PEO_TBL.' WHERE md5(people_id)="'.esc_sql($_GET['ca_sub']).'"');
			$wpdb->query('UPDATE '.CA_PEO_TBL.' SET email_send=1 WHERE md5(people_id)="'.esc_sql($_GET['ca_sub']).'"');
			require_once(plugin_dir_path(__FILE__).'includes/resubscribe.php');
			exit();
	}
	//handle gdpr link
	if(!empty($_GET['confirm']))
	{
		$details=explode("/",$_GET['confirm']);
		$household_id=$wpdb->get_var('SELECT household_id FROM '.CA_PEO_TBL.' WHERE last_name LIKE "'.esc_sql($details[0]).'" AND people_id="'.intval($details[1]).'"');
		if($household_id)
		{
			$wpdb->query('UPDATE '.CA_PEO_TBL.' SET email_send=1, sms_send=1, gdpr_reason="'.esc_sql(__('User confirmed on website','church-admin').' '.date(get_option('date_format'))).'" WHERE household_id="'.intval($household_id).'"' );
			$wpdb->query('UPDATE '.CA_HOU_TBL.' SET privacy=0 WHERE household_id="'.intval($household_id).'"' );
		}
		require_once(plugin_dir_path(__FILE__).'includes/confirmed.php');
			exit();
	}
	//temp fix fo bug in app
	if(isset($_GET['action'])&&$_GET['action']=='ca_classes'){require_once(plugin_dir_path(__FILE__).'app/app-admin.php');ca_classes();exit();}

	if(isset($_GET['action'])&&$_GET['action']=='auto_backup'){require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_backup_pdf();exit();}
	if(isset($_GET['action'])&&$_GET['action']=="delete_backup"){check_admin_referer('delete_backup');church_admin_delete_backup();}
	if(isset($_GET['action'])&&$_GET['action']=="refresh_backup")	{check_admin_referer('refresh_backup');church_admin_backup();}
	//remove cron auto email rotas
	if(isset($_GET['action'])&&$_GET['action']=="delete-cron")
	{
		check_admin_referer('delete-cron');
		require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
		church_admin_delete_cron($_GET['ts'],$_GET['key']);
		$url=admin_url().'admin.php?page=church_admin%2Findex.php&action=rota&section=rota';
		wp_redirect( $url );
	}
	if(!empty($_POST['ind_att_csv'])){require_once(plugin_dir_path(__FILE__).'includes/individual_attendance.php');church_admin_output_ind_att_csv();exit();}
	//load_plugin_textdomain( 'church-admin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    load_plugin_textdomain( 'church-admin');

    if(empty($level['Directory']))$level['Directory']='administrator';
	if(empty($level['Kidswork']))$level['Kidswork']='administrator';
    if(empty($level['Small Groups']))$level['Small Groups']='administrator';
    if(empty($level['Rota']))$level['Rota']='administrator';
    if(empty($level['Funnel'])) $level['Funnel']='administrator';
    if(empty($level['Bulk Email']))$level['Bulk Email']='administrator';
    if(empty($level['Sermons']))$level['Sermons']='administrator';
	if(empty($level['Bulk SMS']))$level['Bulk SMS']='administrator';
    if(empty($level['Calendar']))$level['Calendar']='administrator';
    if(empty($level['Attendance']))$level['Attendance']='administrator';
    if(empty($level['Member Type']))$level['Member Type']='administrator';
    if(empty($level['Service']))$level['Service']='administrator';
	if(empty($level['Prayer Chain']))$level['Prayer Chain']='administrator';
	if(empty($level['Sessions']))$level['Sessions']='administrator';
	if(empty($level['App']))$level['App']='administrator';
	if(empty($level['Prayer Requests']))$level['Prayer Requests']='administrator';
    if(empty($level['Events']))$level['Events']='administrator';
    if(empty($level['Ministries']))$level['Ministries']='administrator';
    update_option('church_admin_levels',$level);
    if(!empty($_POST['one_site']))$wpdb->query('UPDATE '.CA_PEO_TBL.' SET site_id="'.intval($_POST['site_id']).'"');
    //church admin app initialisation

	if(!empty($_GET['ca-app']))
	{
		require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
		switch($_GET['ca-app'])
		{
			case'latest_media': header("Content-Type: application/json");echo church_admin_json_latest_media();exit();break;

		}
	}

	//copy rota and then redirect
	 if(!empty($_GET['page'])&&($_GET['page']=='church_admin/index.php')&&!empty($_GET['action'])&& $_GET['action']=='copy_rota_data' &&church_admin_level_check('Rota'))
	{
		require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
		church_admin_copy_rota($_GET['rotaDate1'],$_GET['rotaDate2'], $_GET['service_id'],$_GET['mtg_type']);
		//MICK WALL – redirect back to the list for current service.
		 $service_id=!empty($_REQUEST['service_id'])?$_REQUEST['service_id']:NULL;
		 $url=admin_url().'admin.php?page=church_admin%2Findex.php&action=church_admin_rota_list&section=rota&service_id='.$service_id;
		wp_redirect( $url );

		 exit();
		 
	}
    //reset version
	if(!empty($_GET['page'])&&($_GET['page']=='church_admin/index.php')&&!empty($_GET['action'])&& $_GET['action']=='reset-version')
	{
		check_admin_referer('reset-version');

		delete_option("church_admin_version");
		$url=admin_url().'admin.php?page=church_admin%2Findex.php&message=Church+Admin+Version+Reset';
		wp_redirect( $url );
		exit;
	}
	//reset version
	//upgrade rota for 1.095
	 if(!empty($_GET['page'])&&($_GET['page']=='church_admin/index.php')&&!empty($_GET['action'])&& $_GET['action']=='upgrade_rota')
	{
		check_admin_referer('upgrade_rota');

		delete_option("church_admin_version");
		$wpdb->query('TRUNCATE TABLE '.CA_ROTA_TBL);
		$url=admin_url().'admin.php?page=church_admin%2Findex.php&message=Rota+Table+Reset';
		wp_redirect( $url );
		exit;
	}
		//upgrade rota for 1.095
	 if(!empty($_GET['page'])&&($_GET['page']=='church_admin/index.php')&&!empty($_GET['action'])&& $_GET['action']=='clear_debug')
	{
		check_admin_referer('clear_debug');

		$upload_dir = wp_upload_dir();
		$debug_path=$upload_dir['basedir'].'/church-admin-cache/debug_log.php';
		if(file_exists($debug_path))unlink($debug_path);
		$url=admin_url().'admin.php?page=church_admin%2Findex.php&action=settings&section=general-settings&message=Church+Admin+Debug+Log+has+been+deleted.';
		wp_redirect( $url );
		exit;
	}
    //save the church admin note before any display happens

	if(!empty($_POST['save-ca-comment']))
 	{
 		if(defined('CA_DEBUG'))church_admin_debug('******************************'."\r\n Save Comment ".date('Y-m-d H:i:s')."\r\n");
 		$sqlsafe=array();

 		if(!empty($_POST['parent_id']))$parent_id=intval($_POST['parent_id']);
 		if(empty($parent_id))$parent_id=null;
 		foreach($_POST AS $key=>$value)$sqlsafe[$key]=esc_sql(stripslashes($value));
 		if(!empty($_POST['comment_id']))
 		{
 			$sql='UPDATE '.CA_COM_TBL.' SET comment="'.$sqlsafe['comment'].'",comment_type="'.$sqlsafe['comment_type'].'",parent_id="'.$parent_id.'",author_id="'.intval($current_user->ID).'",timestamp="'.date('Y-m-d h:i:s').'" comment_id="'.intval($sqlsafe['comment_id']).'"';
 		}
 		else
 		{

 			$sql='INSERT INTO '.CA_COM_TBL.' (comment,comment_type,parent_id,author_id,timestamp,ID)VALUES("'.$sqlsafe['comment'].'","'.$sqlsafe['comment_type'].'","'.$parent_id.'","'.intval($current_user->ID).'","'.date('Y-m-d h:i:s').'","'.intval($sqlsafe['ID']).'")';
 		}
 		if(defined('CA_DEBUG'))church_admin_debug('******************************'."\r\n $sql \r\n");
 		$wpdb->query($sql);
 		if(empty($sqlsafe['comment_id']))$sqlsafe['comment_id']=$wpdb->insert_id;

 		$comment=$wpdb->get_row('SELECT * FROM '.CA_COM_TBL.' WHERE comment_id="'.intval($sqlsafe['comment_id']).'"');

 	}

}

require_once(plugin_dir_path(__FILE__) .'includes/functions.php');
//require_once(plugin_dir_path(__FILE__).'includes/admin.php');
require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
require_once(plugin_dir_path(__FILE__).'includes/custom_fields.php');
if(function_exists('register_block_type'))require_once(plugin_dir_path(__FILE__) .'gutenberg/php-blocks.php');
add_action( 'delete_user', 'church_admin_delete_user' );//make sure user account disconnected from directory


function church_admin_delete_user($user_id)
{
	global $wpdb;
	$wpdb->query('UPDATE '.CA_PEO_TBL.' SET user_id="NULL" WHERE user_id="'.intval($user_id).'"');
}
//add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));
add_action('activated_plugin','church_admin_save_error');
function church_admin_save_error(){
    update_option('church_admin_plugin_error',  ob_get_contents());
}
add_action('load-church-admin', 'church_admin_add_screen_meta_boxes');

//update_option('church_admin_roles',array(2=>'Elder',1=>'Small group Leader'));
$oldroles=get_option('church_admin_roles');
if(!empty($oldroles))
{
    update_option('church_admin_departments',$oldroles);
    delete_option('church_admin_roles');
}


function church_admin_constants()
{
/**
 *
 * Sets up constants for plugin
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
    global $wpdb;

    // DB
    define('CA_COV_TBL',$wpdb->prefix.'church_admin_covid_attendance');
    define('CA_VIS_TBL',$wpdb->prefix.'church_admin_plan_visit');
define('CA_ATT_TBL',$wpdb->prefix.'church_admin_attendance');
define('CA_BRP_TBL',$wpdb->prefix.'church_admin_brplan');
define('CA_APP_TBL',$wpdb->prefix.'church_admin_app');
define('CA_APV_TBL',$wpdb->prefix.'church_admin_app_visits');
define('CA_CP_TBL',$wpdb->prefix.'church_admin_safeguarding');
define ('CA_BIB_TBL',$wpdb->prefix.'church_admin_bible_books');
define ('CA_CAT_TBL',$wpdb->prefix.'church_admin_calendar_category');
define('CA_CEL_TBL',$wpdb->prefix.'church_admin_cell_structure');
define('CA_CLA_TBL',$wpdb->prefix.'church_admin_classes');
define('CA_COM_TBL',$wpdb->prefix.'church_admin_comments');
define('CA_CUST_TBL',$wpdb->prefix.'church_admin_custom_fields');
define('CA_DATE_TBL',$wpdb->prefix.'church_admin_calendar_date');
define('CA_EVE_TBL',$wpdb->prefix.'church_admin_events');
define('CA_BOO_TBL',$wpdb->prefix.'church_admin_bookings');
define('CA_TIK_TBL',$wpdb->prefix.'church_admin_tickets');
define ('CA_FIL_TBL',$wpdb->prefix.'church_admin_sermon_files');
define ('CA_KJV_TBL',$wpdb->prefix.'church_admin_kjv');
define('CA_EMA_TBL',$wpdb->prefix.'church_admin_email');
define('CA_EBU_TBL',$wpdb->prefix.'church_admin_email_build');

define ('CA_FAC_TBL',$wpdb->prefix.'church_admin_facilities');
define('CA_FUN_TBL',$wpdb->prefix.'church_admin_funnels');
define('CA_FP_TBL',$wpdb->prefix.'church_admin_follow_up');
define('CA_HOU_TBL',$wpdb->prefix.'church_admin_household');
define('CA_HOP_TBL',$wpdb->prefix.'church_admin_hope_team');
define('CA_IND_TBL',$wpdb->prefix.'church_admin_individual_attendance');
define('CA_KID_TBL',$wpdb->prefix.'church_admin_kidswork');

define('CA_MET_TBL',$wpdb->prefix.'church_admin_people_meta');
define('CA_METRICS_TBL',$wpdb->prefix.'church_admin_metrics');
define('CA_METRICS_META_TBL',$wpdb->prefix.'church_admin_metrics_meta');
define('CA_MTY_TBL',$wpdb->prefix.'church_admin_member_types');
define('CA_MIN_TBL',$wpdb->prefix.'church_admin_ministries');
define('CA_PAY_TBL',$wpdb->prefix.'church_admin_event_payments');
define('CA_PEO_TBL',$wpdb->prefix.'church_admin_people');
define('CA_ROTA_TBL',$wpdb->prefix.'church_admin_new_rota');
define('CA_ROT_TBL',$wpdb->prefix.'church_admin_rotas');
define('CA_RST_TBL',$wpdb->prefix.'church_admin_rota_settings');
define('CA_SMG_TBL',$wpdb->prefix.'church_admin_smallgroup');
define('CA_SER_TBL',$wpdb->prefix.'church_admin_services');
define('CA_SES_TBL',$wpdb->prefix.'church_admin_session');
define('CA_SMET_TBL',$wpdb->prefix.'church_admin_session_meta');
define('CA_SIT_TBL',$wpdb->prefix.'church_admin_sites');
define ('CA_SERM_TBL',$wpdb->prefix.'church_admin_sermon_series');
define ('CA_UNI_TBL',$wpdb->prefix.'church_admin_units');
define ('CA_SUBU_TBL',$wpdb->prefix.'church_admin_unit_meta');
//define DB
define('OLD_CHURCH_ADMIN_EMAIL_CACHE',WP_PLUGIN_DIR.'/church-admin-cache/');
define('OLD_CHURCH_ADMIN_EMAIL_CACHE_URL',WP_PLUGIN_URL.'/church-admin-cache/');


define('CA_POD_URL',content_url().'/uploads/sermons/');
$upload_dir = wp_upload_dir();

if(!is_dir( $upload_dir['basedir'].'/sermons/'))
    {
        $old = umask(0);
        mkdir( $upload_dir['basedir'].'/sermons/');
        chmod($upload_dir['basedir'].'/sermons/', 0755);
        umask($old);
        $index="<?php\r\n//nothing is good;\r\n?>";
        $fp = fopen($upload_dir['basedir'].'/sermons/'.'index.php', 'w');
        fwrite($fp, $index);
        fclose($fp);
    }
if(!is_dir($upload_dir['basedir'].'/church-admin-cache/'))
{
        $old = umask(0);
		 mkdir($upload_dir['basedir'].'/church-admin-cache/');
        chmod($upload_dir['basedir'].'/church-admin-cache/', 0755);
        umask($old);
        $index="<?php\r\n//nothing is good;\r\n?>";
        $fp = fopen($upload_dir['basedir'].'/church-admin-cache/'.'index.php', 'w');
        fwrite($fp, $index);
        fclose($fp);
}


//this needs to happen very early in page call!
 
}//end constants


 /**
 *
 * Add new household to admin toolbar
 *
 * @author  Andy Moyle
 * @param    null
 * @return   Array, key is order
 * @version  0.1
 *
 */
function church_admin_menu_item ($wp_admin_bar) {

    $args = array (
            'id'        => 'household',
            'title'     => __('Household','church-admin'),
            'href'      => wp_nonce_url('admin.php?page=church_admin/index.php&amp;section=people&action=add-household','add-household'),
            'parent'    => 'new-content'
    );

  if(church_admin_level_check('Directory'))  $wp_admin_bar->add_node( $args );
}

add_action('admin_bar_menu', 'church_admin_menu_item',71);



function ca_rota_order()
{
 /**
 *
 * Retrieves rota items in order
 *
 * @author  Andy Moyle
 * @param    null
 * @return   Array, key is order
 * @version  0.1
 *
 */
    global $wpdb;
    //rota_order
    $results=$wpdb->get_results('SELECT * FROM '.CA_RST_TBL.' ORDER BY rota_order ASC');
    if($results)
    {
        $rota_order=array();
        foreach($results AS $row)
        {
            $rota_order[]=$row->rota_id;
        }
    return $rota_order;
    }

}
/******************************************************************************************************************************
*
* For prayer request, if made private in settings we want to show the login form at the template_redirect hook 
*
******************************************************************************************************************************/

add_action( 'template_redirect', 'church_admin_prayer_requests_login_only' );
function church_admin_prayer_requests_login_only() {
    global $post;
    //church_admin_debug("POST:".print_r($post,true));

    //if ( $post->post_type == 'prayer-requests' || $post->post_type == 'acts-of-courage') {
   if(church_admin_is_post_type('prayer-requests')){
    
        $private=get_option('church-admin-private-prayer-requests');
        if($private)
        {
          
            if(!is_user_logged_in())
            {
                //auth_redirect();
                //church_admin_debug(get_post_type_archive_link('prayer-requests'));
                wp_redirect(wp_login_url(get_post_type_archive_link('prayer-requests')),307);
            }
        }
        
    }

}
/******************************************************************************************************************************
*
* Show a submit prayer requests form at the top of the archive
*
******************************************************************************************************************************/
$theme = wp_get_theme(); // gets the current theme
if ( 'The7' == $theme->name || 'The7' == $theme->parent_theme ) {
    add_action('presscore_before_loop', 'church_admin_draft_prayer_request');
}elseif( 'Omega' == $theme->name || 'Omega' == $theme->parent_theme ){
    
    add_action('omega_before_content', 'church_admin_draft_prayer_request');
}
else{
add_action('loop_start', 'church_admin_draft_prayer_request');
}

function church_admin_draft_prayer_request($content)
{
    global $wpdb,$church_admin_prayer_request_success;
    
		if(is_post_type_archive('prayer-requests')&& is_archive())
        {
			$private=get_option('church-admin-private-prayer-requests');
			//only show form if not private or logged in
			if (!$private ||(is_user_logged_in() && $private))
			{
				$out='';

                if(empty($_POST['save_prayer_request'])&&empty($_POST['non_spammer'])||!wp_verify_nonce($_POST['non_spammer'],'prayer-request'))
                {
                        $out.='<h3>'.__('Submit a prayer request','church-admin').'</h3>';
                        $message=get_option('church_admin_prayer_request_message');
                        if(!empty($message))$out.='<p>'. esc_html($message).'</p>';
                    $out.='<form action="" method="POST">';
                    $out.='<table class="form-table"><tbody>';
                    $out.='<tr><th scope="row">'.__('Title','church-admin').'</th><td><input type="text" name="request_title"></td></tr>';
                    $out.='<tr><th scope="row">'.__('Prayer request','church-admin').'</th><td><textarea name="request_content"></textarea></td></tr>';
                        $out.='<tr id="spam-proof">&nbsp;</td></tr>';
                        $out.='<tr><td cellspacing=2><input type="hidden" value="TRUE" name="save_prayer_request"/><input type="submit" value="'.__('Save','church-admin').'"/></td></tr></table>';

                        $out.='</form>';
                        $nonce=wp_create_nonce('prayer-request');
                        $out.='<script>jQuery(document).ready(function($) {var content="<th scope=\"row\">'.__('Check box if not a spammer','church-admin').'</th><td><input type=\"checkbox\" name=\"non_spammer\" value=\"'.$nonce.'\"/></td></tr>";$("#spam-proof").html(content);});</script>';
                }
                    else{$out=$church_admin_prayer_request_success;}
                echo $out;
            }
		}

}
add_action('loop_start', 'church_admin_draft_act_of_courage');

function church_admin_draft_act_of_courage($content)
{
    global $wpdb,$church_admin_acts_success;

		if(is_post_type_archive('acts-of-courage'))
    {
			$private=get_option('church-admin-private-acts-of-courage');
			//only show form if not private or logged in
			if (!$private ||(is_user_logged_in() && $private))
			{
				$out='';

      	if(empty($_POST['save_acts_request'])&&empty($_POST['non_spammer'])||!wp_verify_nonce($_POST['non_spammer'],'acts-of-courage'))
      	{
					$out.='<h3>'.__('Submit an act of courage','church-admin').'</h3>';
					$message=get_option('church-admin-acts-of-courage-message');
					if(!empty($message))$out.='<p>'. esc_html($message).'</p>';
        	$out.='<form action="" method="POST">';
        	$out.='<table class="form-table"><tbody>';
        	$out.='<tr><th scope="row">'.__('Title','church-admin').'</th><td><input type="text" name="request_title"></td></tr>';
        	$out.='<tr><th scope="row">'.__('Your act of courage','church-admin').'</th><td><textarea name="request_content"></textarea></td></tr>';
					$out.='<tr id="spam-proof">&nbsp;</td></tr>';
					$out.='<tr><td cellspacing=2><input type="hidden" value="TRUE" name="save_act_of_courage_request"/><input type="submit" value="'.__('Save','church-admin').'"/></td></tr></table>';

					$out.='</form>';
					$nonce=wp_create_nonce('acts-of-courage');
					$out.='<script>jQuery(document).ready(function($) {var content="<th scope=\"row\">'.__('Check box if not a spammer','church-admin').'</th><td><input type=\"checkbox\" name=\"non_spammer\" value=\"'.$nonce.'\"/></td></tr>";$("#spam-proof").html(content);});</script>';
				}
				else{$out=$church_admin_acts_success;}
      	echo $out;
			}
		}

}
/****************************************************************************
*
*	From 1.2800 register front end scripts early then enqueue on shortcode process
*
*****************************************************************************/
add_action( 'wp_enqueue_scripts', 'church_admin_register_frontend_scripts' );

function church_admin_register_frontend_scripts() {
    global $church_admin_version;
    wp_register_script('church-admin-event-booking',plugins_url( '/', __FILE__ ) . 'includes/event-booking.js',array( 'jquery' ),FALSE, TRUE);
	wp_register_script('church-admin-calendar-script',plugins_url( '/', __FILE__ ) . 'includes/calendar.js',array( 'jquery' ),FALSE, TRUE);
	wp_register_script('church-admin-calendar',plugins_url( '/', __FILE__ ) . 'includes/jQueryCalendar.js',array( 'jquery' ),FALSE, TRUE);
	wp_enqueue_script( 'jquery-ui-datepicker',plugins_url('church-admin/includes/jquery-ui.min.js',dirname(__FILE__)),array('jquery'),NULL );
	wp_enqueue_style( 'jquery.ui.theme', plugins_url('css/jquery-ui-1.8.21.custom.css',__FILE__ ) ,'',NULL);
	$ajax_nonce = wp_create_nonce("church_admin_mp3_play");

	//wp_register_script('ca_podcast_audio',plugins_url('church-admin/includes/audio.min.js',dirname(__FILE__) ) , array( 'jquery' ) ,FALSE, TRUE);
	wp_localize_script( 'ca_podcast_audio', 'ChurchAdminAjax1', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	wp_register_script('ca_podcast_audio_use',plugins_url('church-admin/includes/audio.use.js',dirname(__FILE__) ), array( 'jquery' ) ,$church_admin_version, TRUE);
	wp_localize_script( 'ca_podcast_audio_use', 'ChurchAdminAjax', array('security'=>$ajax_nonce, 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	wp_register_script( 'jquery-ui-datepicker','','',NULL );
	wp_enqueue_style( 'jquery.ui.theme', plugins_url('css/jquery-ui-1.8.21.custom.css',__FILE__ ) ,'',NULL);
	wp_register_script('church_admin_form_clone',plugins_url('church-admin/includes/jquery-formfields.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
	//fix issue caused by some "premium" themes, which call google maps w/o key on every admin page. D'uh!
 	wp_dequeue_script('avia-google-maps-api');
	//now enqueue google map api with the key
	$src = 'https://maps.googleapis.com/maps/api/js';
	$key='?key='.get_option('church_admin_google_api_key');
	wp_register_script( 'church_admin_google_maps_api',$src.$key, array() ,FALSE);
	wp_register_script('church_admin_map', plugins_url('church-admin/includes/google_maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE);
	wp_register_script('church_admin_map_script', plugins_url('church-admin/includes/maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE);
	wp_register_script('church_admin_frontend_sg_map_script', plugins_url('church-admin/includes/smallgroup_maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE);
	wp_register_script('church_admin_sg_map', plugins_url('church-admin/includes/admin_sg_maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
//google graph needs to be called early and in header, didn't like being registered and then enqueued later
	wp_enqueue_script('church_admin_google_graph_api','https://www.google.com/jsapi', array( 'jquery' ) ,FALSE, FALSE);
	
	
}

add_action('wp_head','church_admin_ajaxurl');
function church_admin_ajaxurl()
{
	$ajax_nonce = wp_create_nonce("church_admin_mp3_play");
	?>
	<script type="text/javascript">
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
		var security= '<?php echo $ajax_nonce; ?>';
	</script>
	<?php
}
add_action('wp_enqueue_scripts', 'church_admin_init');
add_action('admin_enqueue_scripts', 'church_admin_init',9999);//adding withlow priority to be last to call google maps api
/**
 *
 * Initialises js scripts and css
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
function church_admin_init()
{
	if(!empty($_COOKIE['churchAdminBlur']))wp_enqueue_style( 'church-admin-blur', plugins_url('includes/blur.css',__FILE__ ) ,'',NULL);
	wp_enqueue_style('font-awesome','https://use.fontawesome.com/releases/v5.7.1/css/all.css');
    //This function add scripts as needed
    	wp_enqueue_style( 'dashicons' );
		wp_enqueue_script('common','','',NULL);
		wp_enqueue_script('wp-lists','','',NULL);
		wp_enqueue_script('postbox','','',NULL);
		wp_enqueue_style('font-awesome','https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css');
    wp_enqueue_script('church_admin_google_graph_api','https://www.google.com/jsapi', array( 'jquery' ) ,FALSE, FALSE);
	
    ca_thumbnails();

	if(!empty($_POST['church_admin_search']))church_admin_editable_script();



	if(isset($_GET['action']))
	{
		switch($_GET['action'])
		{
            case 'edit-subunit':church_admin_autocomplete_script();break;
            case 'podcast-settings':church_admin_media_uploader_enqueue();break;
			case 'app':church_admin_sortable_script();break;
			case'edit-service':case'add-event':case'edit_safeguarding':case 'rota':case'church_admin_rota_list':church_admin_date_picker_script();break;
			case 'edit_event':
            case 'edit_ticket_type':
                church_admin_date_picker_script();break;
			case 'settings':case 'edit_ministry':church_admin_autocomplete_script();break;
			case 'bulk_geocode':
				check_admin_referer('bulk_geocode');
				church_admin_google_map_api();
				wp_enqueue_script('ca_batch_geocode', plugins_url('church-admin/includes/batch_geocode.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
			break;
			case 'services':case'attendance':church_admin_date_picker_script();church_admin_frontend_graph_script();break;
			case'church_admin_cron_email':
				if(defined('CA_DEBUG'))church_admin_debug('Cron fired:'.date('Y-m-d h:i:s')."/r/n");
				church_admin_bulk_email();exit();
			break;
			case 'remove-queue':check_admin_referer('remove-queue');church_admin_remove_queue();break;
			case'send-email':case'church_admin_send_email':church_admin_email_script();church_admin_autocomplete_script();church_admin_date_picker_script();break;
			case'edit_resend':church_admin_email_script();church_admin_autocomplete_script();church_admin_date_picker_script();break;
			case'resend_new':church_admin_email_script();church_admin_autocomplete_script();break;
			case'resend_email':church_admin_email_script();church_admin_autocomplete_script();break;
			case'church_admin_send_sms':church_admin_email_script();church_admin_autocomplete_script();break;
			case'delete_small_group':church_admin_sg_map_script();church_admin_autocomplete_script();break;
			case'church_admin_search';church_admin_editable_script();break;
			//calendar

			case'church_admin_add_category':
			case'church_admin_edit_category':
            case 'edit-category':
                church_admin_farbtastic_script();
            break;

           
            case 'small_groups':case'show-groups':case'smallgroups-cleanup':
                    church_admin_sortable_script();
					church_admin_form_script();
					church_admin_autocomplete_script();
					$key=get_option('church_admin_google_api_key');
					if(!empty($key))church_admin_sg_map_script();
			break;
            case 'edit-service':
			case 'edit_service':church_admin_form_script();church_admin_date_picker_script();break;
            case 'edit-site':
            case 'edit_site':church_admin_form_script();
						church_admin_media_uploader_enqueue();
				
						$key=get_option('church_admin_google_api_key');
						if(!empty($key))
						{
							church_admin_map_script();
							church_admin_sg_map_script();	
						}	
			break;
            
			case 'edit_small_group':
						
						//church_admin_form_script();
						church_admin_autocomplete_script();
						church_admin_media_uploader_enqueue();
						
						$key=get_option('church_admin_google_api_key');
						if(!empty($key))
						{
							church_admin_map_script();
							church_admin_sg_map_script();	
						}
			break;
			case 'small_groups': 			
						$key=get_option('church_admin_google_api_key');
						if(!empty($key))
						{
							church_admin_map_script();
							church_admin_sg_map_script();	
						}
			break;
			case'classes':church_admin_date_picker_script();church_admin_frontend_graph_script();break;
			case'view_class':church_admin_date_picker_script();church_admin_autocomplete_script();church_admin_frontend_graph_script();break;
			case'church_admin_add_calendar':
            case 'add-calendar':
            case 'view-rota':
            case'church_admin_series_event_edit':    
            case'church_admin_single_event_edit':
            case 'show-attendance':
            case'edit_attendance':
            case'church_admin_new_edit_calendar':
            case'edit_kidswork':
            case 'add-attendance':
            case 'individual-attendance':
            case 'individual-attendance-csv':
            case'individual_attendance':
            case 'csv-rota':
                church_admin_date_picker_script();
            break;
			case'edit_class':church_admin_date_picker_script();church_admin_autocomplete_script();break;

			case'edit_hope_team':church_admin_date_picker_script();church_admin_autocomplete_script();break;
			case'permissions':church_admin_date_picker_script();church_admin_autocomplete_script();break;
			case'edit_file':church_admin_date_picker_script();church_admin_autocomplete_script();break;
			case'file_add':church_admin_date_picker_script();church_admin_autocomplete_script();break;
			case'church_admin_member_type':church_admin_sortable_script();break;
			//rota
			case'rota';church_admin_editable_script();break;
          
            case'edit_rota';church_admin_editable_script();church_admin_autocomplete_script();church_admin_date_picker_script();break;
			case'list';church_admin_editable_script();break;
			case'church_admin_rota_settings_list':church_admin_sortable_script();break;
			case'church_admin_edit_rota_settings':church_admin_sortable_script();break;
			//directory
			case'new_household':
            case 'add-household':
            case'church_admin_new_household':
                church_admin_form_script();
                church_admin_map_script();
                
                church_admin_media_uploader_enqueue();
                church_admin_date_picker_script();
            break;
			case'edit_household':
			case'view_household':
				church_admin_map_script();church_admin_date_picker_script();church_admin_media_uploader_enqueue();
			break;
			case 'edit_people':
				church_admin_form_script();
				church_admin_date_picker_script();
				church_admin_media_uploader_enqueue();
			break;
			case'app':case'edit_sermon_series':church_admin_media_uploader_enqueue();
			break;
			case'church_admin_permissions':church_admin_date_picker_script();church_admin_autocomplete_script();break;
			case'view_ministry':church_admin_autocomplete_script();break;
			case'church_admin_update_order': church_admin_update_order($_GET['which']);exit();break;
			case'get_people':church_admin_ajax_people(TRUE);break;
			case'people':case'edit_funnel':case'delete_funnel':church_admin_sortable_script();break;
                case 'upload-mp3':church_admin_date_picker_script();church_admin_autocomplete_script();break;
            case 'send-sms':church_admin_autocomplete_script();break;
		}
	}


}



function church_admin_media_uploader_enqueue() {
   if(is_admin()) wp_enqueue_media();//enqueue media uploader if on admin page

  }

/**
 *
 * Enqueues jquery
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
 function church_admin_calendar_script()
 {
 	wp_enqueue_script(
			'church-admin-calendar-script',
			plugins_url( '/', __FILE__ ) . 'includes/calendar.js',
			array( 'jquery' ),
			FALSE, TRUE
		);
}




 /**
 *
 * Registers google map api with low priority, so it happens last on enqueuing!
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
 function church_admin_google_map_api()
 {

 	//fix issue caused by some "premium" themes, which call google maps w/o key on every admin page. D'uh!
 	wp_dequeue_script('avia-google-maps-api');

     //now enqueue google map api with the key
     $src = 'https://maps.googleapis.com/maps/api/js';
     $key='?key='.get_option('church_admin_google_api_key');
     wp_enqueue_script( 'Google Map Script',$src.$key, array() ,FALSE, TRUE);


 }

 /**
 *
 * Initialises js scripts for Google graph api
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
function church_admin_frontend_graph_script()
{

	wp_enqueue_script('google-graph-api','https://www.google.com/jsapi', array( 'jquery' ) ,FALSE, FALSE);

}
function church_admin_podcast_script()
{
	$ajax_nonce = wp_create_nonce("church_admin_mp3_play");
	wp_enqueue_script('jquery');
	//wp_enqueue_script('ca_podcast_audio',plugins_url('church-admin/includes/audio.min.js',dirname(__FILE__) ) , array( 'jquery' ) ,FALSE, TRUE);
	wp_localize_script( 'ca_podcast_audio', 'ChurchAdminAjax1', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	wp_enqueue_script('ca_podcast_audio_use');//,plugins_url('church-admin/includes/audio.use.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
	wp_localize_script( 'ca_podcast_audio_use', 'ChurchAdminAjax', array('security'=>$ajax_nonce, 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}
function church_admin_sortable_script()
{
	wp_enqueue_script( 'jquery-ui-sortable' ,'','',NULL);
	wp_enqueue_script('touch-punch',plugins_url('church-admin/includes/jQuery.touchpunch.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
}
function church_admin_form_script()
{
	wp_enqueue_script('form-clone',plugins_url('church-admin/includes/jquery-formfields.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
}
function church_admin_sg_map_script()
{

	church_admin_google_map_api();
	wp_enqueue_script('ca_google_map_script', plugins_url('church-admin/includes/admin_sg_maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
}
function church_admin_frontend_sg_map_script()
{

	church_admin_google_map_api();
	wp_enqueue_script('ca_google_map_script', plugins_url('church-admin/includes/smallgroup_maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
}
function church_admin_map_script()
{
    church_admin_google_map_api();
    wp_enqueue_script('js_map', plugins_url('church-admin/includes/maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
}
function church_frontend_map_script()
{
	church_admin_google_map_api();
	wp_enqueue_script('js_map', plugins_url('church-admin/includes/google_maps.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
}
function church_admin_autocomplete_script()
{
	wp_enqueue_script('jquery-ui-autocomplete','','',NULL);
}
function church_admin_date_picker_script()
{
	wp_enqueue_script( 'jquery-ui-datepicker','','',NULL );
	wp_enqueue_style( 'jquery.ui.theme', plugins_url('css/jquery-ui-1.8.21.custom.css',__FILE__ ) ,'',NULL);
}
function church_admin_farbtastic_script()
{
	wp_enqueue_script( 'farbtastic' ,'','',NULL);
    wp_enqueue_style('farbtastic','','',NULL);
}
function church_admin_email_script()
{
	wp_enqueue_script('jquery','','',NULL);
    wp_register_script('ca_email',  plugins_url('church-admin/includes/email.js',dirname(__FILE__) ), array( 'jquery' ) ,FALSE, TRUE);
	wp_enqueue_script('ca_email','','',NULL);
}
function church_admin_editable_script()
{
    wp_register_script('ca_editable',  plugins_url('church-admin/includes/jquery.jeditable.mini.js',dirname(__FILE__) ), array('jquery'), NULL,TRUE);
    wp_enqueue_script('ca_editable');
}




/* Thumbnails */
function ca_thumbnails()
{
        /**
 *
 * Add thumbnails for plugin use
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
    add_theme_support( 'post-thumbnails' );
    if ( function_exists( 'add_image_size' ) )
    {
        add_image_size('ca-people-thumb',75,75);
        add_image_size( 'ca-email-thumb', 300, 200 ); //300 pixels wide (and unlimited height)
        add_image_size('ca-120-thumb',120,90);
        add_image_size('ca-240-thumb',240,180);

    }

}
/* Thumbnails */
add_action( 'admin_enqueue_scripts','church_admin_public_css');
add_action('wp_enqueue_scripts','church_admin_public_css');
function church_admin_public_css(){
    global $church_admin_version;
    wp_enqueue_style('Church-Admin',plugins_url('church-admin/includes/style.new.css',dirname(__FILE__) ),NULL,$church_admin_version,'all');
}
add_action('wp_head', 'church_admin_public_header');
function church_admin_public_header()
{
    global $church_admin_version;
	echo"<!--
 
   ____ _                    _          _       _           _         ____  _             _       
  / ___| |__  _   _ _ __ ___| |__      / \   __| |_ __ ___ (_)_ __   |  _ \| |_   _  __ _(_)_ __  
 | |   | '_ \| | | | '__/ __| '_ \    / _ \ / _` | '_ ` _ \| | '_ \  | |_) | | | | |/ _` | | '_ \ 
 | |___| | | | |_| | | | (__| | | |  / ___ \ (_| | | | | | | | | | | |  __/| | |_| | (_| | | | | |
  \____|_| |_|\__,_|_|  \___|_| |_| /_/   \_\__,_|_| |_| |_|_|_| |_| |_|   |_|\__,_|\__, |_|_| |_|
                                                                                    |___/                   
\r\n";
    echo' Version: '.$church_admin_version.'-->
    <style>table.church_admin_calendar{width:';
    if(get_option('church_admin_calendar_width')){echo get_option('church_admin_calendar_width').'px}</style>';}else {echo'700px}</style>';}

}

//Build Admin Menus
add_action('admin_menu', 'church_admin_menus');
/**
 *
 * Admin menu
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
function church_admin_menus()

{

    global $level;
    add_menu_page('church_admin:Administration', __('Church Admin','church-admin'),  'publish_posts', 'church_admin/index.php', 'church_admin_main');
}

// Admin Bar Customisation
/**
 *
 * Admin Bar Menu
 *
 * @author  Andy Moyle
 * @param    null
 * @return
 * @version  0.1
 *
 */
function church_admin_admin_bar_render() {

 	global $wp_admin_bar;
 	// Add a new top level menu link
 	// Here we add a customer support URL link
	if(current_user_can('publish_posts'))
	{
			$wp_admin_bar->add_menu( array('parent' => false, 'id' => 'church_admin', 'title' => __('Church Admin','church-admin'), 'href' => admin_url().'admin.php?page=church_admin/index.php' ));
			if(church_admin_level_check('Directory'))$wp_admin_bar->add_menu(array ('parent' => 'church_admin','id'=> 'household1','title'=> __('New Household','church-admin'),'href'=>wp_nonce_url('admin.php?page=church_admin/index.php&amp;section=people&action=church_admin_new_household','new_household')) );
			if(current_user_can('manage_options'))$wp_admin_bar->add_menu(array('parent' => 'church_admin','id' => 'church_admin_settings', 'title' => __('Settings','church-admin'), 'href' => admin_url().'admin.php?page=church_admin/index.php&action=church_admin_settings' ));
			$wp_admin_bar->add_menu(array('parent' => 'church_admin','id' => 'plugin_support', 'title' => __('Plugin Support','church-admin'), 'href' => 'http://www.churchadminplugin.com/support/' ));
		}
}

// Finally we add our hook function
add_action( 'wp_before_admin_bar_render', 'church_admin_admin_bar_render' );




//main admin page function


function church_admin_main()
{
    global $wpdb,$church_admin_version;
	echo'<div class="church-admin-wrap"><!--church_admin_main-->';
	//menu at top of all admin pages
	require_once(plugin_dir_path(__FILE__).'includes/admin.new.php');
	church_admin_front_admin();
    
    $user=wp_get_current_user();
    
    
    ?>
    <div class="church-admin-content">
        <div id="church-admin-header">
            <h1 class="church-admin-title">Church Admin Plugin v<?php echo $church_admin_version;?></h1>
            
            <div id="church-admin-donate">
                <form  action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="R7YWSEHFXEU52"><input type="image"  src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif" class="aligncenter" name="submit" alt="PayPal - The safer, easier way to pay online."><img alt=""   src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1"></form>
            </div>
            <div id="church-admin-signup">
                    <form action="//thegatewaychurch.us2.list-manage.com/subscribe/post?u=de873ad10bb6b43b54744b951&amp;id=848214cef0" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
                    <div id="mc_embed_signup_scroll">
                        <strong>Sign up for news and free PDF manual</strong>
                        <?php 
                            if(!empty($user->user_firstname))echo'<input type="hidden" name="FNAME" value="'.esc_html($user->user_firstname).'"/>';
                            if(!empty($user->user_lastname))echo'<input type="hidden" name="LNAME" value="'.esc_html($user->user_lastname).'"/>';
                        ?>
                        <input type="email" value="" name="EMAIL" class="email" id="mce-EMAIL" placeholder="<?php _e('Email address','church-admin');?>" required>
                        <!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
                        <div style="position: absolute; left: -5000px;" aria-hidden="true">
                            <input type="text" name="b_de873ad10bb6b43b54744b951_848214cef0" tabindex="-1" value="">
                        </div>
                        <input type="submit" value="<?php _e('News sign up','church-admin');?>" name="subscribe" id="mc-embedded-subscribe" class="button-primary">
                    </div>
				    </form> 
            </div>
        </div>
<?php
    church_admin_detect_runtime_issues();
    //allow people to edit their own entry

	$self_edit=FALSE;
	$user_id=get_current_user_id();
	if(!empty($_GET['household_id']))$check=$wpdb->get_var('SELECT user_id FROM '.CA_PEO_TBL.' WHERE user_id="'.esc_sql($user_id).'" AND household_id="'.esc_sql($_GET['household_id']).'"');
	if(!empty($check) && $check==$user_id)$self_edit=TRUE;
	$user_id=!empty($_GET['user_id'])?$_GET['user_id']:NULL;
	$id=isset($_GET['id'])?$_GET['id']:0;
	$mtg_type=!empty($_GET['mtg_type'])?$_GET['mtg_type']:'service';
	$rota_date=!empty($_GET['rota_date'])?$_GET['rota_date']:NULL;
    $date=!empty($_GET['date'])?$_GET['date']:NULL;
	$rota_id=!empty($_GET['rota_id'])?$_GET['rota_id']:NULL;
	$copy_id=!empty($_GET['copy_id'])?$_GET['copy_id']:NULL;
    $date_id=!empty($_GET['date_id'])?$_GET['date_id']:NULL;
    $event_id=!empty($_GET['event_id'])?$_GET['event_id']:NULL;
	$email_id=!empty($_GET['email_id'])?$_GET['email_id']:NULL;
    $people_id=!empty($_GET['people_id'])?$_GET['people_id']:NULL;
    $household_id=!empty($_GET['household_id'])?$_GET['household_id']:NULL;
    $service_id=!empty($_REQUEST['service_id'])?$_REQUEST['service_id']:NULL;
    $site_id=!empty($_REQUEST['site_id'])?$_REQUEST['site_id']:NULL;
    $attendance_id=!empty($_GET['attendance_id'])?$_GET['attendance_id']:NULL;
		$ministry_id=!empty($_GET['ministry_id'])?$_GET['ministry_id']:NULL;
    $ID=!empty($_GET['ID'])?$_GET['ID']:NULL;
    $unit_id=!empty($_GET['unit_id'])?$_GET['unit_id']:NULL;
    $subunit_id=!empty($_GET['subunit_id'])?$_GET['subunit_id']:NULL;
    $funnel_id=!empty($_GET['funnel_id'])?$_GET['funnel_id']:NULL;
    $ticket_id=!empty($_GET['ticket_id'])?$_GET['ticket_id']:NULL;
    $booking_ref=!empty($_GET['booking_ref'])?$_GET['booking_ref']:NULL;
    $people_type_id=isset($_GET['people_type_id'])?$_GET['people_type_id']:NULL;
    $member_type_id=isset($_REQUEST['member_type_id'])?$_REQUEST['member_type_id']:NULL;
	$facilities_id=isset($_REQUEST['facilities_id'])?$_REQUEST['facilities_id']:NULL;
    $edit_type=!empty($_REQUEST['edit_type'])?$_REQUEST['edit_type']:'single';
    $file=!empty($_GET['file'])?$_GET['file']:NULL;
	$smallgroup_id=!empty($_GET['smallgroup_id'])?$_GET['smallgroup_id']:NULL;
    $message=!empty($_GET['message'])?$_GET['message']:NULL;
    if(!empty($_REQUEST['church_admin_search'])){if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_search($_REQUEST['church_admin_search']);}}
	elseif(isset($_GET['action']))
    {
	switch($_GET['action'])
	{
        case 'gender-update':
            if(current_user_can('manage_options'))
            {
                $genders=explode(",",$_GET['genders']);
                update_option('church_admin_gender',$genders);
                echo '<div class="notice notice-success inline"><h2>Genders updated</h2></div>';
            }
        break;
		case 'delete_cell':check_admin_referer('delete_cell');require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');church_admin_delete_cell($ID);break;
		case 'app_visits':require_once(plugin_dir_path(__FILE__).'app/app-admin.php');church_admin_app_visits($_GET['app_date']);break;
		case 'reset_readings':$wpdb->query('UPDATE '.CA_BRP_TBL.' SET passages=""');echo'Done ;-)';break;
		case 'test_email':require_once(plugin_dir_path(__FILE__).'includes/email.php');church_admin_test_email($_GET['email']);break;


		//main menu sections
		case 'gdpr-email':check_admin_referer('gdpr-email'); if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_gdpr_email();}break;
		case 'gdpr-email-test':check_admin_referer('gdpr-email'); if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_gdpr_email_test();}break;

		case'shortcodes':church_admin_shortcodes_list();break;

		case'small_groups':if(church_admin_level_check('Small Groups')){ echo church_admin_smallgroups_main();}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;


		case'ministries':if(church_admin_level_check('Directory')){church_admin_ministries_menu();break;}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;
            
            
            
            
		
		case'children':if(church_admin_level_check('Directory')){church_admin_children();}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;
		case'communication':if(church_admin_level_check('Prayer Chain')){church_admin_communication();}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;
		case'rota':if(church_admin_level_check('Rota')){require_once(plugin_dir_path(__FILE__).'includes/admin.php');church_admin_rota_main();}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;
		case'tracking':if(church_admin_level_check('Attendance')){church_admin_tracking();}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;
		
		//case 'settings':if(current_user_can('manage_options')){church_admin_settings_menu();}else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}break;
		case 'calendar':if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_new_calendar(time(),$facilities_id);}else{echo'<div class="error"><p>You don\'t have permissions</p></div>';}break;
		
		//csv import
		case'import-csv':
            if(church_admin_level_check('Directory'))
            {
                check_admin_referer('import-csv');
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
                church_admin_import_csv();
            }
        break;
        case'replicate-roles':
		case'replicate_roles':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
                church_admin_replicate_roles();
            }
        break;

		case 'edit_marital_status': if(church_admin_level_check('Directory'))
									{require_once(plugin_dir_path(__FILE__).'includes/settings.php');church_admin_edit_marital_status($ID);}
		break;
		case 'delete_marital_status': if(church_admin_level_check('Directory'))
									{require_once(plugin_dir_path(__FILE__).'includes/settings.php');church_admin_delete_marital_status($ID);}
		break;

/*************************************
*
*		APP
*
**************************************/
		case 'logout_app':require_once(plugin_dir_path(__FILE__).'app/app-admin.php');church_admin_logout_app($user_id);break;
		//case 'app_page':require_once(plugin_dir_path(__FILE__).'app/app-admin.php');church_admin_app_post();break;
		case 'app': require_once(plugin_dir_path(__FILE__).'app/app-admin.php');church_admin_app();break;
        //case 'delete_app_content':if(current_user_can('manage_options')){require_once(plugin_dir_path(__FILE__).'app/app-admin.php');church_admin_delete_current_app_content();}break;
        case 'app-settings':
            require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
            church_admin_app_settings();
            church_admin_app_member_types();
        break;
        case 'app-menu':
            require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
           church_admin_app_menu();
        break;
        case 'bible-reading-plan':
            require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
            church_admin_bible_reading_plan();
        break;  
        case 'app-member-types':
            require_once(plugin_dir_path(__FILE__).'app/app-admin.php');    
            church_admin_app_member_types();
        break;
        case 'app-logout':
            require_once(plugin_dir_path(__FILE__).'app/app-admin.php');    
            church_admin_logout_app_everyone();
        break;
        case 'app-logins':
            require_once(plugin_dir_path(__FILE__).'app/app-admin.php');    
            church_admin_app_logins();
        break;
        case 'app-visits':
           require_once(plugin_dir_path(__FILE__).'app/app-admin.php'); 
                church_admin_app_visits($date);
        break;
/*************************************
*
*		ATTENDANCE
*
**************************************/
        
        case'show-attendance':
        case 'attendance':    
            if(church_admin_level_check('Directory'))
            {
                echo'<h1>'.__('Attendance','church-admin').'</h1>';
                echo'<h2>'.__('Attendance graph','church-admin').'</h2>';
                require_once(plugin_dir_path(__FILE__).'display/graph.php');
                echo church_admin_graph($type='weekly','S/1',date("Y-01-01"),date("Y-12-31"),900,500,TRUE);
                require_once(plugin_dir_path(__FILE__).'includes/attendance.php');
                church_admin_attendance_list(1,'service');
            }else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}
        break; 
        case 'individual-attendance-csv':
            if(church_admin_level_check('Directory'))
            {      
                require_once(plugin_dir_path(__FILE__).'includes/individual_attendance.php');
	           echo church_admin_individual_attendance_csv();
            }
        break;
        case 'attendance-csv':
            if(church_admin_level_check('Directory'))
            {      
                require_once(plugin_dir_path(__FILE__).'includes/csv.php');
	           echo church_admin_attendance_csv();
            }
        break;
		case 'individual-attendance':
        case 'individual_attendance':
            require_once(plugin_dir_path(__FILE__).'includes/individual_attendance.php'); 
            echo church_admin_individual_attendance();
        break;
	    case 'church_admin_attendance_metrics':
            require_once(plugin_dir_path(__FILE__).'includes/attendance.php');
            church_admin_attendance_metrics($service_id);break;
	    case 'church_admin_attendance_list':require_once(plugin_dir_path(__FILE__).'includes/attendance.php');church_admin_attendance_list($service_id);break;
        case 'add-attendance':
        case 'edit_attendance':
            if(church_admin_level_check('Directory'))
            {            require_once(plugin_dir_path(__FILE__).'includes/attendance.php');church_admin_edit_attendance($attendance_id);
            }
        break;
	    case 'delete_attendance':check_admin_referer('delete_attendance');require_once(plugin_dir_path(__FILE__).'includes/attendance.php');church_admin_delete_attendance($attendance_id);break;


/*************************************
*
*		CALENDAR
*
**************************************/
        case 'calendar':case 'church_admin_new_calendar':if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_new_calendar(time(),$facilities_id);}break;
        case 'add-calendar':case 'church_admin_new_edit_calendar':if(church_admin_level_check('Calendar'))
		{
			require_once(plugin_dir_path(__FILE__).'includes/calendar.php');

			if(substr($id,0,4)=='item'){church_admin_event_edit(substr($id,4),NULL,$edit_type,NULL,$facilities_id);}
			else{church_admin_event_edit(NULL,NULL,NULL,$id,$facilities_id);}
		}
		break;
		case 'church_admin_calendar_list':if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_calendar();}break;

	    case 'church_admin_add_category':if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_edit_category(NULL,NULL);}break;

		case 'edit-category':case 'church_admin_edit_category':if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_edit_category($id,NULL);}break;

		case 'church_admin_delete_category':check_admin_referer('delete_category');if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_delete_category($id);}break;

		case 'church_admin_single_event_delete':check_admin_referer('single_event_delete');if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_single_event_delete($date_id,$event_id); }break;

		case 'church_admin_series_event_delete':check_admin_referer('series_event_delete');if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_series_event_delete($event_id);}break;

		case'categories':case 'church_admin_category_list':if(church_admin_level_check('Calendar'));{require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_category_list();}break;

		case 'church_admin_series_event_edit':check_admin_referer('series_event_edit');if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_event_edit($date_id,$event_id,'series',NULL,NULL);}break;

		case 'church_admin_single_event_edit':check_admin_referer('single_event_edit');if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_event_edit($date_id,$event_id,'single',NULL,NULL);}break;

		case 'church_admin_add_calendar':if(church_admin_level_check('Calendar')){require_once(plugin_dir_path(__FILE__).'includes/calendar.php');church_admin_event_edit(NULL,NULL,NULL,NULL,NULL);}break;
            
/*************************************
*
*		CHECK-IN
*
**************************************/
		case 'QRCode':require_once(plugin_dir_path(__FILE__).'includes/checkin.php');church_admin_create_QR($people_id);break;
		case 'checkin-labels':require_once(plugin_dir_path(__FILE__).'includes/checkin.php');church_admin_checkin_labels();break;
/******************************************
*
*   classes
*
*******************************************/
        case'classes':case'class':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/classes.php');
                echo'<h1>'.__("Classes",'church-admin').'</h1>';
                church_admin_classes();
            }else{echo'<div class="error"><p>You don\'t have permissions</p></div>';}
        break;

		case 'edit_class':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/classes.php');
                church_admin_edit_class($id);
            }
        break;
		case 'delete_class':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/classes.php');
                church_admin_delete_class($id);
            }
        break;
		case 'view_class':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/classes.php');
                church_admin_view_class($id);
            }
        break;

/*************************************
*
*		COMMUNICATIONS
*
**************************************/
        case 'push-message':
                if(church_admin_level_check('Bulk Email'))
                {
                    require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
	               $licence=get_option('church_admin_app_new_licence');
	               if($licence=='subscribed')ca_push_message();
                }
        break;
		case'sync-mailchimp':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/mailchimp.php');
                church_admin_mailchimp_sync();
            }
        break;
	    case 'send-mailchimp':
            if(church_admin_level_check('Bulk Email'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/email.php');
                church_admin_send_mailchimp();
            }
        break;
		case 'send-sms':
            if(church_admin_level_check('Bulk SMS'))
            {
                require_once(plugin_dir_path(__FILE__ ).'includes/sms.php');
                church_admin_send_sms();
            }
        break;
        case 'email-settings':
            if(church_admin_level_check('Bulk Email'))
            {
                require_once(plugin_dir_path(__FILE__ ).'includes/settings.php');            
                church_admin_email_settings();
            }
        break;
          case 'smtp-settings':
            if(church_admin_level_check('Bulk Email'))
            {
                require_once(plugin_dir_path(__FILE__ ).'includes/settings.php');            
                church_admin_smtp_settings();
            }
        break;          
        case 'sms-settings':
            if(church_admin_level_check('Bulk SMS'))
            {
                require_once(plugin_dir_path(__FILE__ ).'includes/settings.php');            
                church_admin_sms_settings();
            }
        break;
	    case 'send-email':
            if(church_admin_level_check('Bulk Email'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/email.php');
                church_admin_send_email(NULL);
            }
        break;
        case 'comms':case 'email_list':
                if(church_admin_level_check('Bulk Email'))
                {
                    require_once(plugin_dir_path(__FILE__).'includes/email.php');
                    church_admin_email_list();
                }
        break;
		case 'delete_email':if(church_admin_level_check('Bulk Email')){require_once(plugin_dir_path(__FILE__).'includes/email.php');church_admin_delete_email($email_id);}break;
		case 'resend_email':if(church_admin_level_check('Bulk Email')){require_once(plugin_dir_path(__FILE__).'includes/email.php');church_admin_resend($email_id);}break;
		case 'resend_new':if(church_admin_level_check('Bulk Email')){require_once(plugin_dir_path(__FILE__).'includes/email.php');church_admin_resend_new($email_id);}break;

	    case 'edit_resend':if(church_admin_level_check('Bulk Email')){require_once(plugin_dir_path(__FILE__).'includes/email.php');church_admin_send_email($email_id);}break;

	    case'church_admin_people_activity':if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/people_activity.php'); echo church_admin_recent_people_activity();}break;
/*************************************
*
*		CUSTOM FIELDS
*
**************************************/
        case 'custom-fields':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/custom_fields.php');
                echo church_admin_list_custom_fields();
            }
        break;
		case 'edit_custom_field':check_admin_referer('edit_custom_field'); echo church_admin_edit_custom_field($id);break;
		case 'delete_custom_field':check_admin_referer('delete_custom_field'); echo church_admin_delete_custom_field($id);break;
/*************************************
*
*		DIRECTORY
*
**************************************/
        case 'add-household':
            if(church_admin_level_check('Directory')||$self_edit)
            {
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
                church_admin_new_household();
            }else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}
        break;
        case 'download-csv':
            if(church_admin_level_check('Directory'))
            {
                
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
                church_admin_export_csv();
            }
        break;
        case 'recent-people':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/people_activity.php');     
                church_admin_recent_people_activity();    
            }
        break;
        case 'view-directory':
            if(church_admin_level_check('Directory'))
            {
                
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
                church_admin_view_directory();
            }
        break;
        case 'export-pdf':
            if(church_admin_level_check('Directory'))
            {
                
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
               church_admin_pdf_menu();
            }
        break;

        case 'download-csv':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');            
                church_admin_export_csv();
            }
        break;
        case'bulk_geocode':
			check_admin_referer('bulk_geocode');
			if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_bulk_geocode();}
	   break;
        case'recent-people':
			
			if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/people-activity.php');
                church_admin_recent_people_activity();
            }
	   break;     
        case'birthdays':
			
			if(church_admin_level_check('Directory'))
            {
                echo'<h1>'.__("All birthdays for next 31 days",'church-admin').'</h1>';
                require_once(plugin_dir_path(__FILE__).'includes/birthdays.php');
                echo church_admin_frontend_birthdays(0,0,31,TRUE);
            }
        break; 
        case 'bulk_not_private':if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_bulk_not_private();}break;
	    case 'gdpr_bulk_confirm':if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');gdpr_confirm_everyone();}break;
	    case 'view_person':if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_view_person($people_id);}break;
	    case 'church_admin_move_person':if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_move_person($people_id);}break;
        case 'view-address-list':case 'church_admin_address_list': if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_address_list($member_type_id);}else{echo"<p>You don't have permission to do that";}break;
	    
        case  'create_confirmed_users':
        case 'create_users':
            if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_users();}else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}
        break;
			
	    case 'church_admin_create_user':check_admin_referer('create_user');if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_create_user($people_id,$household_id);}break;
	    case 'church_admin_migrate_users':check_admin_referer('migrate_users');if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_migrate_users();}break;
	    case 'display_household':
	    if(church_admin_level_check('Directory')||$self_edit){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_display_household($household_id);}else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}break;
		
	    case 'edit_household':if(church_admin_level_check('Directory')||$self_edit){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_edit_household($household_id);}else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}break;
	    case 'delete_household':check_admin_referer('delete_household');if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_delete_household($household_id);}break;
	    case 'check-duplicates':
	    	if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/directory.php');
                church_admin_potential_duplicates();
            }else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}
	    break;
        case 'edit_people':
	    	if(church_admin_level_check('Directory')||$self_edit){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_edit_people($people_id,$household_id);}else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}
	    break;
        case 'delete-all':
            if(current_user_can('manage_options'))
            {
                church_admin_backup();
                $wpdb->query('TRUNCATE TABLE '.CA_PEO_TBL);
                $wpdb->query('TRUNCATE TABLE '.CA_HOU_TBL);
                $wpdb->query('TRUNCATE TABLE '.CA_MET_TBL);
            }
            echo'<h2>'.__('All households deleted','church-admin').'</h2>';
        break;
	    case 'delete_people':
	    if(church_admin_level_check('Directory')||$self_edit){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_delete_people($people_id,$household_id);}else{echo'<p>'.__('You do not have permission to do that','church-admin').'</p>';}break;
	    case 'church_admin_search':if(wp_verify_nonce('ca_search_nonce','ca_search_nonce')){require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_search($_POST['ca_search']);}break;
		case'church_admin_recent_visitors': require_once(plugin_dir_path(__FILE__).'includes/recent.php');echo church_admin_recent_visitors($member_type_id);break;

/*************************************
*
*		ERRORS
*
**************************************/
		case 'activation-log-clear':check_admin_referer('clear_error');church_admin_activation_log_clear();break;
            
        case 'error-log':
        case 'installation-errors':
            echo'<h1>'.__('Installation errors','church-admin').'</h1>';	
            $error=get_option('church_admin_plugin_error');
	           if(!empty($error))
	           {
		          
		          echo'<p>'.__('This is what was saved as an error during activation ','church-admin').'"'.$error.'"</p>';
		          echo'<p><a href="'.wp_nonce_url('admin.php?page=church_admin/index.php&amp;section=settings&action=activation-log-clear','clear_error').'">'.__('Clear activation errors log','church-admin').'</a></p><hr/>';
	           }
            else{
                echo'<p>'.__('No installation errors recoreded','church-admin').'</p>';
            }
        break;

/*************************************
*
*		Events
*
**************************************/
        case 'add-event':case 'edit_event':
            if(church_admin_level_check("Events"))
               {
                    require_once(plugin_dir_path(__FILE__).'includes/events.php'); 
                   church_admin_edit_event($event_id);
               }
        break;
        case 'edit_ticket_type':
            if(church_admin_level_check("Events"))
               {
                    require_once(plugin_dir_path(__FILE__).'includes/events.php'); 
                   church_admin_edit_ticket_type($event_id,$ticket_id);
               }
        break;
        case 'delete_ticket_type':
            if(church_admin_level_check("Events"))
               {
                    require_once(plugin_dir_path(__FILE__).'includes/events.php'); 
                   church_admin_delete_ticket_type($event_id,$ticket_id);
               }
        break;
        case 'view-event':
        case 'view_event':
            if(church_admin_level_check("Events"))
            {
                require_once(plugin_dir_path(__FILE__).'includes/events.php'); 
                church_admin_view_event($event_id);
            } 
        break;
		case   'delete_event':
            check_admin_referer('delete_event');require_once(plugin_dir_path(__FILE__).'includes/events.php'); church_admin_delete_event($event_id);
        break;
        case 'edit_booking':check_admin_referer('edit_booking');require_once(plugin_dir_path(__FILE__).'includes/events.php'); church_admin_edit_booking($ticket_id,$event_id,$booking_ref);break;
        case 'delete_booking':check_admin_referer('delete_booking');require_once(plugin_dir_path(__FILE__).'includes/events.php'); church_admin_delete_booking($ticket_id);break;
        case 'view_bookings':check_admin_referer('view_bookings');require_once(plugin_dir_path(__FILE__).'includes/events.php'); church_admin_view_bookings($event_id);break;
        case 'show-events':case 'events':
            if(church_admin_level_check("Events"))
               {
                   require_once(plugin_dir_path(__FILE__).'includes/events.php'); 
                   church_admin_events();
               }
        break;

            
/*************************************
*
*		FACILITIES
*
**************************************/
        case 'facilities':
        case 'church_admin_facilities':
            if(church_admin_level_check('Calendar'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/facilities.php');
                church_admin_facilities();
            }
        break;
	    case 'edit_facility':
        case 'edit-facility':
            if(church_admin_level_check('Calendar'))
            {           
                require_once(plugin_dir_path(__FILE__).'includes/facilities.php');
                church_admin_edit_facility($facilities_id);
            }
        break;
	    case 'delete_facility':
            if(church_admin_level_check('Calendar'))
            { 
            require_once(plugin_dir_path(__FILE__).'includes/facilities.php');
                church_admin_delete_facility($facilities_id);
            }
        break;
        case 'facility-bookings':
            if(church_admin_level_check('Calendar'))
            { 
            require_once(plugin_dir_path(__FILE__).'includes/facilities.php');
                church_admin_facility_bookings($facilities_id);
            }
        break;
            
/*************************************
*
*		FOLLOW UP
*
**************************************/
	    case'funnel':
        case 'church_admin_funnel_list':
            if(church_admin_level_check('Directory'))
            { 
                require_once(plugin_dir_path(__FILE__).'includes/funnel.php');church_admin_funnel_list();
            }
        break;
        case 'add-funnel':
        case 'edit_funnel':
            if(church_admin_level_check('Directory'))
            { 
                require_once(plugin_dir_path(__FILE__).'includes/funnel.php');
                church_admin_edit_funnel($funnel_id,$people_type_id);
            }
        break;
		case 'delete_funnel':check_admin_referer('delete_funnel');require_once(plugin_dir_path(__FILE__).'includes/funnel.php');church_admin_delete_funnel($funnel_id);break;
	    case 'church_admin_assign_funnel':require_once(plugin_dir_path(__FILE__).'includes/people_activity.php');church_admin_assign_funnel();break;
	    case 'church_admin_email_follow_up_activity':check_admin_referer('email_funnels');require_once(plugin_dir_path(__FILE__).'includes/people_activity.php');church_admin_email_follow_up_activity();break;
			case 'follow_up_completed':require_once(plugin_dir_path(__FILE__).'includes/funnel.php');require_once(plugin_dir_path(__FILE__).'includes/people_activity.php');church_admin_follow_up_completed($id);break;
            
            
/*************************************
*
*		HOPETEAM
*
**************************************/
		case'hope_team_jobs':check_admin_referer('hope_team_jobs');require_once(plugin_dir_path(__FILE__).'includes/hope-team.php');church_admin_hope_team_jobs($id);break;
		case'edit_hope_team_job':check_admin_referer('hope_team_jobs');require_once(plugin_dir_path(__FILE__).'includes/hope-team.php');church_admin_edit_hope_team_job($id);break;
		case'delete_hope_team_job':check_admin_referer('delete_hope_team_jobs');require_once(plugin_dir_path(__FILE__).'includes/hope-team.php');church_admin_delete_hope_team_job($id);break;
		case'edit_hope_team':check_admin_referer('edit_hope_team');require_once(plugin_dir_path(__FILE__).'includes/hope-team.php');church_admin_edit_hope_team($id);break;
/*************************************
*
*		KIDS WORK
*
**************************************/
        case "kidswork-pdf":
            if(church_admin_level_check('Directory'))
		    { 
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                church_admin_kidswork_PDF();
            }
        break;
        case "kidswork-checkin-pdf":
            if(church_admin_level_check('Directory'))
		    { 
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                church_admin_kidswork_checkin_PDF();
            }
        break;
        case 'edit_kidswork':
            if(church_admin_level_check('Directory'))
		    { 
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                church_admin_edit_kidswork($id);
            }
        break;
		case 'delete_kidswork':
            if(church_admin_level_check('Directory'))
		    {             
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                church_admin_delete_kidswork($id);
            }
        break;
		case 'kidswork':
            if(church_admin_level_check('Directory'))
		    {
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                echo'<h1>'.__("Children's Ministry",'church-admin').'</h1>';
                echo church_admin_kidswork();
            }
        break;
		case 'edit_safeguarding':
            if(church_admin_level_check('Directory'))
		    {             
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                church_admin_edit_safeguarding($people_id);
            }
        break;
        case'safeguarding':
            if(church_admin_level_check('Directory'))
		    { 
                require_once(plugin_dir_path(__FILE__).'includes/kidswork.php');
                church_admin_safeguarding_main();
            }
        break;


            
/*************************************
*
*		MEDIA
*
**************************************/
        case 'podcast':
            if(church_admin_level_check('Sermons'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
                echo'<h1>'.__('Sermon podcast files','church-admin').'</h1>';
                ca_podcast_list_files();
            }else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}
            break;
		case 'migrate_sermon_manager':
		if(church_admin_level_check('Sermons'))
		{
			require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
			church_admin_migrate_sermon_manager();
		}
		break;            
	case'list_speakers':
		if(church_admin_level_check('Sermons'))
		{
			require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
			ca_podcast_list_speakers();
		}
	break;
    case'edit_speaker':
    	    if(church_admin_level_check('Sermons'))
    	    {
    	       require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
    	       ca_podcast_edit_speaker($id);
    	    }
    break;
            case'delete_speaker':if(church_admin_level_check('Sermons')){require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');ca_podcast_delete_speaker($id);}break;
    case 'sermon-series':
    case'list_sermon_series':
            if(church_admin_level_check('Sermons'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
                ca_podcast_list_series();
            }
    break;
    case 'edit-series':
    case'edit_sermon_series':
            if(church_admin_level_check('Sermons'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
                ca_podcast_edit_series($id);
            }
    break;
            case'delete_sermon_series':if(church_admin_level_check('Sermons')){check_admin_referer('delete_sermon_series');require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');ca_podcast_delete_series($id);}break;
            case'list_files':if(church_admin_level_check('Sermons')){require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');ca_podcast_list_files();}break;
        case 'upload-mp3':
        case'edit_file':
            if(church_admin_level_check('Sermons'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
                ca_podcast_edit_file($id);
            }
        break;
            case'delete_file':if(church_admin_level_check('Sermons')){check_admin_referer('delete_podcast_file');require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');ca_podcast_delete_file($id);}break;
            case'file_delete':if(church_admin_level_check('Sermons')){check_admin_referer('file_delete');require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');ca_podcast_file_delete($file);}break;
            case'file_add':if(church_admin_level_check('Sermons')){check_admin_referer('file_add');require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');ca_podcast_file_add($file);}break;
        case'check-files':
            if(church_admin_level_check('Sermons'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
                ca_podcast_check_files();
            }
        break;
            case'podcast':if(church_admin_level_check('Sermons')){require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');if(ca_podcast_xml()){echo'<p>Podcast <a href="'.CA_POD_URL.'podcast.xml">feed</a> updated</p>';}}break;
        case'podcast-settings':
            if(church_admin_level_check('Sermons')){
                require_once(plugin_dir_path(__FILE__).'includes/podcast-settings.php');
                ca_podcast_settings();
            }
        break;

/*************************************
*
*		MEMBER TYPE
*
**************************************/
	   case 'member-types':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/member_type.php');
                church_admin_member_type();
            }
        break;
        case 'add-member-type':
        case 'church_admin_edit_member_type':
            if(church_admin_level_check('Directory'))
            {   
                require_once(plugin_dir_path(__FILE__).'includes/member_type.php');
                church_admin_edit_member_type($member_type_id);
            }
        break;
	    case 'church_admin_delete_member_type':check_admin_referer('delete_member_type');require_once(plugin_dir_path(__FILE__).'includes/member_type.php');church_admin_delete_member_type($member_type_id);break;

/*************************************
*
*		MINISTRIES
*
**************************************/
        case 'edit-ministry':
        case 'edit_ministry':
            if(church_admin_level_check('Ministries'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/departments.php');church_admin_edit_ministry($id);
            }
        break;
	    case 'delete_ministry':            
            if(church_admin_level_check('Ministries'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/departments.php');
                church_admin_delete_ministry($id);
            }
        break;
        case 'ministries-list':
        case 'ministry_list':
            if(church_admin_level_check('Ministries'))
            {
                echo'<h1>'.__('Ministries','church-admin').'</h1>';
                require_once(plugin_dir_path(__FILE__).'includes/departments.php');
                church_admin_ministries_list();
            }
        break;
       case 'view_ministry':            
            if(church_admin_level_check('Ministries'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/departments.php');
                church_admin_view_ministry($id);
            }
        break;
        case 'volunteers':
            if(church_admin_level_check('Ministries'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/volunteer.php');
	           echo church_admin_volunteer_display();
            }
        break;

/*************************************
*
*		ROTA
*
**************************************/

        case 'show-cron':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                church_admin_cron_check();
            }
        break;
	    case'view-rota':case 'church_admin_rota_list':case 'rota_list':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                church_admin_rota_list($service_id);
            }
        break;
	    case 'edit_rota': 	
	    		if(church_admin_level_check('Rota'))
	    		{
	    			require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
	    			church_admin_edit_rota($rota_date,$mtg_type,$service_id);
	    		}
	    break;
	    case 'delete_rota':
	    		if(church_admin_level_check('Rota'))
	    		{
	    			require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
	    			church_admin_delete_rota($rota_date,$mtg_type,$_GET['service_id']);
	    		}
	    break;
	    case 'email_rota':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                church_admin_email_rota($service_id,$rota_date);
            }
        break;
	    case 'auto_email_test':church_admin_auto_email_rota($service_id);break;
/*************************************
*
*		ROTA SETTINGS
*
**************************************/
	    case'rota-settings':case'church_admin_rota_settings_list':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota_settings.php');
                church_admin_rota_settings_list();
            }
        break;
        case 'edit-rota-job':case 'church_admin_edit_rota_settings':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota_settings.php');
                church_admin_edit_rota_settings($id);
            }
        break;
	    case 'church_admin_delete_rota_settings':check_admin_referer('delete_rota_settings');if(church_admin_level_check('Rota')){require_once(plugin_dir_path(__FILE__).'includes/rota_settings.php');church_admin_delete_rota_settings($id);}break;
	    case 'test-cron-rota':church_admin_auto_email_rota();break;
        case 'rota-auto-email':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                church_admin_rota_auto_email();
            }
        break;
         case 'email-rota':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                if(!empty($_GET['service_id'])&&!empty($_GET['date'])){church_admin_email_rota($service_id=1,$date);}
                else church_admin_email_rota_form();
            }
        break; 
        case'pdf-rota':
             if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                 church_admin_rota_pdf_menu();
             }
        
        break;
        case'csv-rota':
             if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
                 church_admin_rota_csv_menu();
             }
        
        break;
          case 'sms-rota':
            if(church_admin_level_check('Rota'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');           
                if(!empty($_GET['service_id']))
                {
                    church_admin_sms_rota($_GET['service_id']);
                }
                else church_admin_sms_rota_form();
            }
        break;
/*************************************
*
*		SERVICES
*
**************************************/
        case'services':case'service-list':case 'service_list':case'services-list':
            echo'<h1>'.__('Services','church-admin').'</h2>';
            if(church_admin_level_check('Small Groups'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/services.php');
                
                church_admin_service_list($message);
            }else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}
            
        break; 
         case'sites':case'site-list':
            echo'<h1>'.__('Sites','church-admin').'</h2>';
            if(church_admin_level_check('Small Groups'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sites.php');
                
                church_admin_site_list();
            }else{echo'<div class="error"><p>'.__("You don't have permissions",'church-admin').'</p></div>';}
            
        break; 
        case 'edit-service':
        case  'edit_service':
            if(church_admin_level_check('Service'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/services.php');  church_admin_edit_service($id);
            }
        break;
        case  'service-prebooking':
            if(church_admin_level_check('Service'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/covid-prebooking.php');  
                echo church_admin_covid_attendance_list();
            }
        break;
        case 'delete_service_booking'  :
                require_once(plugin_dir_path(__FILE__).'includes/covid-prebooking.php');
                echo church_admin_delete_service_booking($id);
        break;    
                case 'delete_bubble_booking'  :
                require_once(plugin_dir_path(__FILE__).'includes/covid-prebooking.php');
                echo church_admin_delete_bubble_booking($id);
        break;  
        case 'edit_bubble_booking'  :
                require_once(plugin_dir_path(__FILE__).'includes/covid-prebooking.php');
                echo church_admin_edit_bubble_booking($id);
        break;
	    case  'delete_service':check_admin_referer('delete_service');if(church_admin_level_check('Service'))
        {
            require_once(plugin_dir_path(__FILE__).'includes/services.php');  
            church_admin_delete_service($id);
        }
        break;
	    case 'delete_site':if(church_admin_level_check('Service')){require_once(plugin_dir_path(__FILE__).'includes/sites.php'); church_admin_delete_site($site_id);}break;
        case 'edit-site':
        case 'edit_site':
            if(church_admin_level_check('Service'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/sites.php'); 
                church_admin_edit_site($site_id);
            }
        break;
/*************************************
*
*       SESSIONS
*
*************************************/
        case 'sessions':                
           	require_once(plugin_dir_path(__FILE__).'includes/sessions.php');
 	      echo'<h1>'.__('Sessions','church-admin').'</h1>';
            echo church_admin_sessions();
        break;
            
/*************************************
*
*		SMALL GROUPS
*
**************************************/
        case 'smallgroups-cleanup':
            if(church_Admin_level_check('Small Groups'))
            {
                wp_enqueue_script('church_admin_google_maps_api');
				wp_enqueue_script('church_admin_frontend_sg_map_script');
                require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');
                echo church_admin_smallgroups_cleanup();
                echo'<h1>'.__('Small groups','church-admin').'</h1>';
                church_admin_smallgroup_metrics();
                church_admin_smallgroup_PDF_form();
                require_once(plugin_dir_path(__FILE__).'display/small-group-list.php');
		          echo church_admin_small_group_list(1,13,TRUE); 
            }
        break;
        case 'show-groups':
            if(church_Admin_level_check('Small Groups'))
            {
                wp_enqueue_script('church_admin_google_maps_api');
				wp_enqueue_script('church_admin_frontend_sg_map_script');
                echo'<h1>'.__('Small groups','church-admin').'</h1>';
                require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');
                church_admin_smallgroup_metrics();
                church_admin_smallgroup_PDF_form();
                require_once(plugin_dir_path(__FILE__).'display/small-group-list.php');
		          echo church_admin_small_group_list(1,13,TRUE);  
            }
        break;
		case 'delete-all-small-groups':
				check_admin_referer('delete-all-small-groups');
				if(church_Admin_level_check('Small Groups'))
				{
					//backup first as it is a nuclear option
					church_admin_backup();
					//delete all small groups
					require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');
					church_admin_delete_all_small_groups();
					echo '<p><a class="button-primary" href="'.wp_nonce_url("admin.php?page=church_admin/index.php&section=small_groups&amp;action=edit_small_group",'edit_small_group').'">'.__('Add a small group','church-admin').'</a></p>';
				}else{echo'<p>'.__("You don't have permission to do that",'church-admin').'</p>';}
		break;	
        case 'smallgroup-stucture':
 			if(church_Admin_level_check('Small Groups'))
			{
                require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');
                echo church_admin_small_group_structure();
            }
        break;
        case 'oversight-list':    
 			if(church_Admin_level_check('Small Groups'))
			{
                require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');            
                echo church_admin_oversight_list();
            }
        break;
		case'remove_from_smallgroup':
			check_admin_referer('remove');
			require_once(plugin_dir_path(__FILE__).'includes/small_groups.php');
			church_admin_remove_from_smallgroup($people_id,$smallgroup_id);
		break;
		case'whosin':check_admin_referer('whosin');if(church_admin_level_check('Small Groups')){require_once(plugin_dir_path(__FILE__).'includes/small_groups.php'); echo church_admin_whosin($id);}break;
	    case  'edit_small_group':check_admin_referer('edit_small_group');if(church_admin_level_check('Small Groups')){require_once(plugin_dir_path(__FILE__).'includes/small_groups.php'); echo church_admin_edit_small_group($id);}break;
	    case  'delete_small_group':check_admin_referer('delete_small_group');if(church_admin_level_check('Small Groups')){require_once(plugin_dir_path(__FILE__).'includes/small_groups.php'); echo church_admin_delete_small_group($id);}break;
	    case 'church_admin_small_groups':if(church_admin_level_check('Small Groups')){require_once(plugin_dir_path(__FILE__).'includes/small_groups.php'); echo church_admin_small_groups();}break;

/*************************************
*
*		SETTINGS
*
**************************************/
        case 'choose-filters':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');                 church_admin_choose_filters();
            }
        break;
        case 'refresh_backup':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');            echo'<h1>'.__("Backup",'church-admin').'</h1>';
                 echo'<div class="notice notice-success inline"><h2>'.__('Backup refreshed','church-admin').'</h2></div>';
                church_admin_backup_list();
            }
        break;
        case 'delete_all_backups':
            church_admin_delete_backup();
            require_once(plugin_dir_path(__FILE__).'includes/settings.php');
            church_admin_backup_list();
        break;
        case'restore-backup':
            if(current_user_can('manage_options'))
            {
                check_admin_referer('restore-backup');
                church_admin_restore_backup();
            }
        break;
        case 'debug-log':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');
                church_admin_debug_log();
            }
       break;
        case 'restrict-access':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');
                echo church_admin_restrict_access();
            }
       break;
        case 'people-types':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');
                church_admin_people_types_list();
            }
       break;
        case'modules':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');           
                church_admin_modules();
            }
        break;
         case'marital-status':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');           
                church_admin_marital_status();
            }
        break;       
        case'permissions':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/permissions.php');
                church_admin_permissions();
            }
        break;
		case'roles':
            if(current_user_can('manage_options'))
            {            
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');
                church_admin_roles();
            }
        break;
        case 'email-settings':
             if(current_user_can('manage_options'))
            {            
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');       
            	church_admin_email_settings();    
             }
        break;
        case 'smtp-settings':
             if(current_user_can('manage_options'))
            {            
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');       
            	church_admin_smtp_settings();    
             }
        break;
        case 'bible-version':
            if(current_user_can('manage_options'))
            {              
                require_once(plugin_dir_path(__FILE__).'app/app-admin.php');
	           church_admin_bible_version();
            }
        break;
	    case 'general-settings':
        case 'church_admin_settings':
            if(current_user_can('manage_options'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/settings.php');
                church_admin_general_settings();
            }
        break;
	    case'edit_people_type':require_once(plugin_dir_path(__FILE__).'includes/settings.php');
            echo church_admin_edit_people_type($ID);
          
        break;
	    case'delete_people_type':require_once(plugin_dir_path(__FILE__).'includes/settings.php');echo church_admin_delete_people_type($ID);echo church_admin_people_types_list();break;
/*************************************
*
*       UNITS
*
**************************************/
        case 'units':
            require_once(plugin_dir_path(__FILE__).'includes/units.php');
            church_admin_units_list();
        break;
        case 'edit-unit':
            require_once(plugin_dir_path(__FILE__).'includes/units.php');
            church_admin_edit_unit($unit_id);
        break;
        case 'show-subunits':
            require_once(plugin_dir_path(__FILE__).'includes/units.php');
            church_admin_show_subunits($unit_id);
        break;
        case 'edit-subunit':
            require_once(plugin_dir_path(__FILE__).'includes/units.php');
            church_admin_edit_subunit($unit_id,$subunit_id);
        break;
        case 'delete-unit':
            require_once(plugin_dir_path(__FILE__).'includes/units.php');
            church_admin_delete_unit($unit_id);
        break;    
        case 'delete-subunit':
            require_once(plugin_dir_path(__FILE__).'includes/units.php');
            church_admin_delete_subunit($unit_id,$subunit_id);
        break;
/*************************************
*
*       VOLUNTEERS
*
**************************************/
        case 'approve_volunteer':
            require_once(plugin_dir_path(__FILE__).'includes/volunteer.php');
            church_admin_volunteer_approval($people_id,$ministry_id);
        break;
		case 'decline_volunteer':
            require_once(plugin_dir_path(__FILE__).'includes/volunteer.php');
            church_admin_volunteer_decline($people_id,$ministry_id);
        break;    
/*************************************
*
*		DEFAULT
*
**************************************/
        case 'people':default:if(church_admin_level_check('Directory'))
       {
           require_once(plugin_dir_path(__FILE__).'includes/directory.php');
           church_admin_people_main();
       }else{echo'<p>'.__("You don't have permissions for this page",'church-admin').'</p>';}break;

	}

    }else if(church_admin_level_check('Directory'))
    {
        require_once(plugin_dir_path(__FILE__).'includes/directory.php');
        church_admin_people_main();
    }else{echo'<p>'.__("You don't have permissions for this page",'church-admin').'</p>';}

   echo'<script>// shorthand no-conflict safe document-ready function
  jQuery(function($) {

    $( document ).on( "click", ".notice-church-admin .notice-dismiss", function () {

        var type = $( this ).closest( ".notice-church-admin" ).data( "notice" );

        $.ajax( ajaxurl,
          {
            type: "POST",
            data: {
              action: "dismissed_notice_handler",
              type: type,
            }
          } );
      } );
  });</script>';
   echo'</div><!-- .church-admin-content -->';
    echo'</div><!-- .church-admin-wrap -->';
}

function church_admin_shortcode($atts, $content = null)
{
	
   
    //sort out true false issue where it gets evaluated as a string
   	foreach($atts AS $key=>$value)
   	{
   		if($value==='FALSE'||$value==='false')$atts[$key]=0;
   		if($value==='TRUE'||$value==='true')$atts[$key]=1;
   	}

   	extract(shortcode_atts(array('cache'=>1,'pdf'=>1,'zoom'=>13,'class_id'=>NULL,'day_calendar'=>TRUE,'style'=>'new','kids'=>TRUE,'height'=>500,'width'=>900,"pdf_font_resize"=>TRUE,"updateable"=>1,"restricted"=>0,"loggedin"=>1,"type" => 'address-list','people_types'=>'all','site_id'=>0,'days'=>30,'year'=>date('Y'),'service_id'=>NULL,'photo'=>0,'category'=>NULL,'weeks'=>4,'ministry_id'=>NULL,'people_type_id'=>NULL,'member_type_id'=>NULL,'kids'=>1,'map'=>0,'series_id'=>NULL,'speaker_id'=>NULL,'file_id'=>NULL,'api_key'=>NULL,'facilities_id'=>NULL,'exclude'=>NULL,'today'=>FALSE,'first_initial'=>0,'title'=>'','show_age'=>FALSE,'most_popular'=>TRUE,'order'=>'DESC','people_types'=>NULL,'title'=>"",'event_id'=>NULL,'unit_id'=>NULL,'url'=>NULL,'comments_title'=>NULL,'url'=>NULL,'hide_views'=>FALSE,'mode'=>"households","max_fields"=>10,'admin_email'=>NULL,'no_address'=>NULL), $atts));
    church_admin_posts_logout();
    $out='';

    global $wpdb;

    global $wp_query;

    	$upload_dir = wp_upload_dir();
		$path=$upload_dir['basedir'].'/church-admin-cache/';
    	//look to see if church directory is o/p on a password protected page
    	if(!empty($wp_query->post->ID))$pageinfo=get_page($wp_query->post->ID);
    	//grab page info
    	//check to see if on a password protected page
    	if(!empty($pageinfo)&& $pageinfo->post_password!=''&&isset( $_COOKIE['wp-postpass_' . COOKIEHASH] ))
    	{
			$text = __('Log out of password protected posts','church-admin');
		//text for link
		$link = site_url().'?church_admin_logout=posts_logout';
		$out.= '<p><a href="' . wp_nonce_url($link, 'posts logout') .'">' . $text . '</a></p>';
		//output logoutlink
    	}

    	//grab content
    	switch($type)
    	{
             
			case'unit':
                require_once(plugin_dir_path(__FILE__).'display/units.php');
				$out.=church_admin_display_unit($unit_id);
            break;
			case 'attendance':
				if(is_user_logged_in()&&church_admin_level_check('Directory'))
				{
					require_once(plugin_dir_path(__FILE__).'includes/individual_attendance.php');
					$out.=church_admin_individual_attendance();
				}
				else
				{
					$out.='<h3>'.__('Only logged in users with permission can use this feature','church-admin').'</h3>';
					$out.=wp_login_form(array('echo' => false));
				}
			break;
			
			case 'volunteer':
				require_once(plugin_dir_path(__FILE__).'display/volunteer.php');
				$out.=church_admin_display_volunteer();
			break;
			case 'sessions': require_once(plugin_dir_path(__FILE__).'includes/sessions.php');
				$out.=church_admin_sessions(NULL,NULL);
			break;
            case 'video':
                if(!empty($url))
                {
                    
                    $embed=church_admin_generateVideoEmbedUrl($url);
                    
                    $out.='<div class="container-fluid no-padding"><div style="position:relative;padding-top:56.25%"><iframe class="ca-video" style="position:absolute;top:0;left:0;width:100%;height:100%;" src="'.$embed['embed'].'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>';
                    $views=church_admin_youtube_api($embed['id']);
                    if(!empty($views)&& empty($hide_views))$out.='<p>'.sprintf(__('%1$s views','church-admin'),$views).'</p>';
                }
            break;
			case 'podcast':
				wp_enqueue_script('ca_podcast_audio');
				wp_enqueue_script('ca_podcast_audio_use');
				require_once(plugin_dir_path(__FILE__).'display/sermon-podcast.php');
				
				$out.=church_admin_podcast_display($series_id,$file_id,$exclude,$most_popular,$order);

			break;
            case 'player':
                wp_enqueue_script('ca_podcast_audio_use');
                require_once(plugin_dir_path(__FILE__).'display/sermon-podcast.php');
                if(!empty($file_id)){$out.=church_admin_player($file_id);}else{$out.=__('No file specified','church-admin');}
            break;
      case 'calendar':
            wp_enqueue_script('church-admin-calendar');//Jan 2020 version
			wp_enqueue_script('church_admin_calendar');
			if(empty($facilities_id)&& !empty($pdf))
			{
				$out.='<table><tr><td>'.__('Year Planner pdfs','church-admin').' </td><td>  <form name="guideform" action="'.$_SERVER['PHP_SELF'].'" method="get"><select name="guidelinks" onchange="window.location=document.guideform.guidelinks.options[document.guideform.guidelinks.selectedIndex].value"> <option selected="selected" value="">-- '.__('Choose a pdf','church-admin').' --</option>';
				for($x=0;$x<5;$x++)
				{
					$y=date('Y')+$x;
					$out.='<option value="'.home_url().'/?ca_download=yearplanner&amp;yearplanner='.wp_create_nonce('yearplanner').'&amp;year='.$y.'">'.$y.__('Year Planner','church-admin').'</option>';
				}
				$out.='</select></form></td></tr></table>';
			}
			if($style=='old'||!empty($facilities_id))
			{
            		require_once(plugin_dir_path(__FILE__).'display/calendar.php');
            		$out.=church_admin_display_calendar($facilities_id);
            }
            else
            {
            	require_once(plugin_dir_path(__FILE__).'display/calendar.new.php');
            	//$out.=church_admin_new_calendar_display('day',$day_calendar);
                $out.=church_admin_display_new_calendar();
        	}
      break;
      case 'classes':
				wp_enqueue_script('jquery-ui-datepicker');
				require_once(plugin_dir_path(__FILE__).'display/classes.php');
        		$out.=church_admin_display_classes($today);
      break;
      case 'class':
						wp_enqueue_script('jquery-ui-datepicker');
			  		require_once(plugin_dir_path(__FILE__).'display/classes.php');
        		$out.=church_admin_display_class($class_id);
      break;
    case 'facilities':
                require_once(plugin_dir_path(__FILE__).'display/calendar.php');
            	$out.=church_admin_display_calendar($facilities_id);
    break;
    case 'names':
				if(empty($loggedin)||is_user_logged_in())
				{
					require_once(plugin_dir_path(__FILE__).'/display/names.php');$out.=church_admin_names($member_type_id,$people_types);
				}
				else //login required
				{
					$out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
				}
			break;

			case 'calendar-list':
            	require_once(plugin_dir_path(__FILE__).'/display/calendar-list.php');$out.=church_admin_calendar_list($days,$category);
      break;
      case 'event_booking':
           wp_enqueue_script('church-admin-event-booking'); require_once(plugin_dir_path(__FILE__).'/display/events.php');
            $out.=church_admin_event_bookings_output($event_id);
      break;  
      
        case 'recent':
            $access=TRUE;
      		if(is_user_logged_in())
      		{
      			
      			$current_user=wp_get_current_user();
      			$people_id=$wpdb->get_var('SELECT people_id FROM '.CA_PEO_TBL.' WHERE user_id="'.intval($current_user->ID).'"');
      			$restrictedList=get_option('church-admin-restricted-access');
      			if(is_array($restrictedList)&&in_array($people_id,$restrictedList))$access=FALSE;
      		}    
			if(empty($loggedin)||is_user_logged_in() && $access)
			{
				require_once(plugin_dir_path(__FILE__).'includes/recent.php');
				$out.=church_admin_recent_display($weeks,$member_type_id);
			}
			else //login required
			{
				if(!$access && is_user_logged_in()  )$out.='<div class="notice notice-warning inline">'.__("You haven't been granted access to this infromation",'church-admin').'</div>';
                $out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
			}
			break;
            case 'phone-list':
                $access=TRUE;
                if(is_user_logged_in())
      		    {
      			
                    $current_user=wp_get_current_user();
                    $people_id=$wpdb->get_var('SELECT people_id FROM '.CA_PEO_TBL.' WHERE user_id="'.intval($current_user->ID).'"');
                    $restrictedList=get_option('church-admin-restricted-access');
                    if(is_array($restrictedList)&&in_array($people_id,$restrictedList))$access=FALSE;
      		    }
			     if(empty($loggedin)||is_user_logged_in() && $access)
			    { 
                    require_once(plugin_dir_path(__FILE__).'display/phone-list.php');
                     $out.=church_admin_frontend_phone_list($people_type_id,$member_type_id);
                }
                else //login required
			     {
					if(empty($access)) $out.='<h2>'.__('You have not been granted access to the address list','church-admin').'</h2>';
					else $out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
                }
            break;
            case 'address-list':case'addresslist':case 'directory':
      		$access=TRUE;
      		if(is_user_logged_in())
      		{
      			
      			$current_user=wp_get_current_user();
      			$people_id=$wpdb->get_var('SELECT people_id FROM '.CA_PEO_TBL.' WHERE user_id="'.intval($current_user->ID).'"');
      			$restrictedList=get_option('church-admin-restricted-access');
      			if(is_array($restrictedList)&&in_array($people_id,$restrictedList))$access=FALSE;
      		}
			if(empty($loggedin)||is_user_logged_in() && $access)
			{
				if(!empty($pdf))
				{
					switch($pdf)
					{
						case '2':
							$out.='<p><a href="'.home_url().'/?ca_download=addresslist&amp;addresslist='.wp_create_nonce('address-list','address-list').'&amp;loggedin='.$loggedin.'&amp;pdfversion=2&amp;member_type_id='.$member_type_id.'" target="_blank"> '.__('PDF version','church-admin').'</a></p>';
						break;
						default:

							$out.='<p><a  target="_blank" href="'.home_url().'/?ca_download=addresslist-family-photos&amp;loggedin='.$loggedin.'&amp;kids='.$kids.'&amp;addresslist='.wp_create_nonce('address-list','address-list' ).'&amp;member_type_id='.$member_type_id.'">'.__('PDF version','church-admin').'</a></p>';
						break;

					}
				}
					if($style=='old')
					{

            		require_once(plugin_dir_path(__FILE__).'display/address-list.old.php');
            		$out.=church_admin_frontend_directory($member_type_id,$map,$photo,$api_key,$kids,$site_id,$updateable);
	   			}
	   			else
	   			{

            		require_once(plugin_dir_path(__FILE__).'display/address-list.php');
            		$out.=church_admin_frontend_directory($member_type_id,$map,$photo,$api_key,$kids,$site_id,$updateable,$first_initial,$cache);
            		$out.=' <a href="'.get_permalink().'?ca_refresh=TRUE">'.__("Refresh",'church-admin').'</a>';
        	}
				}
				else //login required
				{
					if(empty($access)) $out.='<h2>'.__('You have not been granted access to the address list','church-admin').'</h2>';
					else $out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
				}
      break;
        case 'bible-readings':case 'bible-reading':
            require_once(plugin_dir_path(__FILE__).'display/bible-readings.php');
            $out.=church_admin_bible_reading_shortcode();    
        break;
        case 'hello':
            if(is_user_logged_in())
            {
                $user=wp_get_current_user();
                $name=$wpdb->get_var('SELECT first_name FROM '.CA_PEO_TBL.' WHERE user_id="'.intval($user->ID).'"');
                if(!empty($name))$out.=sprintf(__('Welcome back %1$s',"church-admin"),$name);
            }
        break;
        case 'small-groups-list':
				wp_enqueue_script('church_admin_google_maps_api');
				wp_enqueue_script('church_admin_frontend_sg_map_script');

            	require_once(plugin_dir_path(__FILE__).'/display/small-group-list.php');
            	$out.= church_admin_small_group_list($map,$zoom,$photo,$loggedin,$title,$pdf,$no_address);
      break;
    case 'my-group':
                require_once(plugin_dir_path(__FILE__).'/display/my-group.php');
                $out.=church_admin_my_group();
    break;
        case 'small-group-signup':
            require_once(plugin_dir_path(__FILE__).'/display/small-group-signup.php');
        	$out.=church_admin_smallgroup_signup($title,$people_types);
        break;
			case 'small-groups':
					wp_enqueue_script('church_admin_google_maps_api');
					wp_enqueue_script('church_admin_frontend_sg_map_script');
	        		require_once(plugin_dir_path(__FILE__).'/display/small-groups.php');
          			$out.=church_admin_frontend_small_groups($member_type_id,$restricted);
      break;
		case 'map':$out.=church_admin_map($atts, $content);break;
		case 'register':$out.=church_admin_register($atts, $content);break;
      case 'ministries':
            	require_once(plugin_dir_path(__FILE__).'/display/ministries.php');
            	$out.=church_admin_frontend_ministries($ministry_id,$member_type_id);
      break;
      case 'my_rota':case 'my-rota':
				if(empty($loggedin)||is_user_logged_in())
				{
            	require_once(plugin_dir_path(__FILE__).'/display/rota.php');
            	$out.=church_admin_my_rota();
				}
				else //login required
				{
					$out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
				}
			break;
			case 'rota':
				if(empty($loggedin)||is_user_logged_in())
				{
            	require_once(plugin_dir_path(__FILE__).'/display/rota.php');
            	if(!empty($_REQUEST['rota_date'])){$date=$_REQUEST['rota_date'];}else{$date=date('Y-m-d');}
            	$out.=church_admin_front_end_rota($service_id,$weeks,$pdf_font_resize,$date,$title);
				}
				else //login required
				{
								$out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
				}
			break;
      case 'rolling-average':
      case 'weekly-attendance':
      case 'monthly-attendance':
      case 'rolling-average-attendance':
            case 'graph':
					wp_enqueue_script('jquery-ui-datepicker');
					wp_enqueue_script('church_admin_google_graph_api');
				if(empty($width))$width=900;
				if(empty($height))$height=500;
				if(!empty($_POST['type']))
				{
					switch($_POST['type'])
					{
						case'weekly':$graphtype='weekly';break;
						case'rolling':$graphtype='rolling';break;
						default:$graphtype='weekly';break;
					}
				}else{$graphtype='weekly';}
				if(!empty($_POST['start'])){$start=$_POST['start'];}else{$start=date('Y-m-d',strtotime('-1 year'));}
				if(!empty($_POST['end'])){$end=$_POST['end'];}else{$end=date('Y-m-d');}
				if(!empty($_POST['service_id'])){$service_id=$_POST['service_id'];}else{$service_id='S/1';}

				require_once(plugin_dir_path(__FILE__).'display/graph.php');
				$out.=church_admin_graph($graphtype,$service_id,$start,$end,$width,$height,FALSE);
			break;
			case 'birthdays':
			if(empty($loggedin)||is_user_logged_in())
			{
				require_once(plugin_dir_path(__FILE__).'includes/birthdays.php');
                $out.=church_admin_frontend_birthdays($member_type_id,$people_type_id, $days,$show_age);
			}
			else //login required
			{
				$out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
			}

			break;
			case 'restricted':
				//restricts content to certain member_type_ids
				if(!is_user_logged_in())
				{
						 $out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
				}
                elseif(church_admin_user_member_level($member_type_id)){$out.=do_shortcode($content);}
                else{$out.=__('You are not permitted to view this content','church-admin');}
			break;
			case 'follow-up':
				if(is_user_logged_in()&& church_admin_level_check('Directory'))
				{
					require_once(plugin_dir_path(__FILE__).'includes/people_activity.php');
					return church_admin_recent_people_activity();
				}
				else{$out.=__('You are not permitted to view this content','church-admin');}
			break;
			default:
				if(empty($loggedin)||is_user_logged_in())
				{

						$out.='<p><a href="'.home_url().'/?ca_download=addresslist&amp;addresslist='.wp_create_nonce('member'.$member_type_id ).'&amp;member_type_id='.$member_type_id.'">'.__('PDF version','church-admin').'</a></p>';
        	    require_once(plugin_dir_path(__FILE__).'display/address-list.php');
         	   $out.=church_admin_frontend_directory($member_type_id,$map,$photo,$api_key,$kids,$site_id,$updateable);
					 }
					 else //login required
					 {
						 $out.='<div class="login"><h2>'.__('Please login','church-admin').'</h2>'.wp_login_form(array('echo'=>FALSE)).'</div>'.'<p><a href="'.wp_lostpassword_url(get_permalink() ).'" title="Lost Password">'.__('Help! I don\'t know my password','church-admin').'</a></p>';
					 }
       		break;
            case 'covid-prebooking':case'service-prebooking':
                require_once(plugin_dir_path(__FILE__).'display/covid-prebooking.php');
                $out.=church_admin_covid_attendance($service_id,$mode,$max_fields,$days,$admin_email);
            break;
                
    	}

//output content instead of shortcode!
return $out;
}

add_shortcode('church_admin_unsubscribe','church_admin_unsubscribe');
function church_admin_unsubscribe()
{
	$out='<p>'.__('This shortcode is deprecated','church-admin').'</p>';
	return $out;
}
add_shortcode('church_admin_recent','church_admin_recent');
function church_admin_recent($atts, $content = null)
{
    extract(shortcode_atts(array('month'=>1), $atts));
    require_once(plugin_dir_path(__FILE__).'includes/recent.php');
    $out= church_admin_recent_display($month);
	return $out;
}
add_shortcode("church_admin", "church_admin_shortcode");

add_shortcode("church_admin_map","church_admin_map");
function church_admin_map($atts, $content = null)
{
	global $wpdb;
		$out='';
	extract(shortcode_atts(array('loggedin'=>1,'width'=>"100%",'height'=>"1000px"), $atts));
	if(empty($loggedin)||is_user_logged_in())
	{
		wp_enqueue_script('church_admin_google_maps_api');
		wp_enqueue_script('church_admin_map');

    extract(shortcode_atts(array('zoom'=>13,'member_type_id'=>1,'small_group'=>1,'unattached'=>0), $atts));
    global $wpdb;

    $service=$wpdb->get_row('SELECT AVG(lat) AS lat,AVG(lng) AS lng FROM '.CA_SIT_TBL);
    $out.='<div class="church-map"><script type="text/javascript">var xml_url="'.site_url().'/?ca_download=address-xml&member_type_id='.esc_html($member_type_id).'&small_group='.esc_html($small_group).'&unattached='.esc_html($unattached).'&address-xml='.wp_create_nonce('address-xml').'";';
    $out.=' var lat='.esc_html($service->lat).';';
    $out.=' var lng='.esc_html($service->lng).';';
	$out.=' var zoom='.esc_html($zoom).';';
	$out.=' var translation=["'.__('Small Groups','church-admin').'","'.__('Unattached','church-admin').'","'.__('In a group','church-admin').'","'.__('Group','church-admin').'"];';
    $out.='jQuery(document).ready(function(){console.log("Ready to lead");
    load(lat,lng,xml_url,zoom,translation);});</script><div id="church-admin-member-map" style="width:'.$width.';height:'.$height.'"></div>';
    $out.='<div id="groups" ><p><img src="https://maps.google.com/mapfiles/kml/paddle/blu-circle.png"/>'.__('Small Group','church-admin').'<br/><img src="https://maps.google.com/mapfiles/kml/paddle/red-circle.png"/>'.__('Not in a small group','church-admin').'<br/><img src="https://maps.google.com/mapfiles/kml/paddle/grn-circle.png"/>'.__('In a small Group','church-admin').'</p></div>';
    $out.='</div>';
	}
	else {
		$out='<h3>'.__('You need to be logged in to view the map','church-admin').'</h3>'.wp_login_form(array('echo'=>false));
	}
    return $out;

}
add_shortcode("church_admin_register","church_admin_register");
function church_admin_register($atts, $content = null)
{
 		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('church_admin_form_clone');
		wp_enqueue_script('church_admin_google_map_api');
		//wp_enqueue_script('church_admin_map');
		wp_enqueue_script('church_admin_google_maps_api');
		wp_enqueue_script('church_admin_map_script');
		extract(shortcode_atts(array('admin_email'=>TRUE,'user'=>FALSE,'member_type_id'=>1,'exclude'=>NULL), $atts));
    require_once(plugin_dir_path(__FILE__).'includes/front_end_register.php');
   	$noshow=array();
   	if(!empty($exclude))
	{
		$noshow=explode(",",$exclude);
	}
    $out=church_admin_front_end_register($user,$member_type_id,$noshow,$admin_email);
    return $out;
}

function church_admin_posts_logout()
{
    if ( isset( $_GET['church_admin_logout'] ) && ( 'posts_logout' == $_GET['church_admin_logout'] ) &&check_admin_referer( 'posts logout' ))
    {
	setcookie( 'wp-postpass_' . COOKIEHASH, ' ', time() - 31536000, COOKIEPATH );
	wp_redirect( wp_get_referer() );
	die();
    }
}


add_action( 'init', 'church_admin_posts_logout' );

//end of logout functions

function church_admin_calendar_widget($args)
{
    global $wpdb;

    extract($args);
    $options=get_option('church_admin_widget');
    $title=$options['title'];

    echo $before_widget;
    if ( $title )echo $before_title . $title . $after_title;

    echo church_admin_calendar_widget_output($options['events'],$options['postit'],$title);
    echo $after_widget;
}
function church_admin_widget_init()
{
    wp_register_sidebar_widget('Church-Admin-Calendar','Church Admin Calendar','church_admin_calendar_widget');
    require_once(plugin_dir_path(__FILE__).'includes/calendar_widget.php');
    wp_register_widget_control('Church-Admin-Calendar','Church Admin Calendar','church_admin_widget_control');
}
add_action('init','church_admin_widget_init');

function church_admin_birthday_widget($args)
{
    global $wpdb;

    extract($args);
	$options=get_option('church_admin_birthday_widget');

    $title=$options['title'];
	if(empty($options['member_type_id']))$options['member_type_id']=1;
	if(empty($options['days']))$options['days']=14;
	$out=church_admin_frontend_birthdays($options['member_type_id'],"1,2,3", $options['days'],$options['showAge']);
   if(!empty($out))
   {
		echo $before_widget;
		if (!empty( $options['title']) )echo $before_title . $options['title'] . $after_title;
		require_once(plugin_dir_path(__FILE__).'includes/birthdays.php');
		echo $out;
		echo $after_widget;
	}
}
function church_admin_birthday_widget_init()
{
    wp_register_sidebar_widget('Church Admin Birthdays','Church Admin Birthdays','church_admin_birthday_widget');
    require_once(plugin_dir_path(__FILE__).'includes/birthdays.php');
    wp_register_widget_control('Church Admin Birthdays','Church Admin Birthdays','church_admin_birthday_widget_control');
}
add_action('init','church_admin_birthday_widget_init');
function church_admin_sermons_widget($args)
{
    global $wpdb;
	church_admin_latest_sermons_scripts();

    extract($args);
    $options=get_option('church_admin_latest_sermons_widget');
    $title=$options['title'];
	$limit=$options['sermons'];
    echo $before_widget;
    if ( $title )echo $before_title . esc_html($title) . $after_title;
	require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
    echo church_admin_latest_sermons_widget_output($limit,$title);
    echo $after_widget;
}
function church_admin_sermons_widget_init()
{
    wp_register_sidebar_widget('Church-Admin-Latest-Sermons','Church Admin Latest Sermons','church_admin_sermons_widget');
    require_once(plugin_dir_path(__FILE__).'includes/sermon-podcast.php');
    wp_register_widget_control('Church-Admin-Latest-Sermons','Church Admin Latest Sermons','church_admin_latest_sermons_widget_control');


}
function church_admin_latest_sermons_scripts()
{
	$ajax_nonce = wp_create_nonce("church_admin_mp3_play");
	//wp_enqueue_script('ca_podcast_audio',plugins_url('church-admin/includes/audio.min.js',dirname(__FILE__)),'',NULL);
	wp_enqueue_script('ca_podcast_audio_use');//,plugins_url('church-admin/includes/audio.use.js',dirname(__FILE__)),'',NULL);
	wp_localize_script( 'ca_podcast_audio_use', 'ChurchAdminAjax', array('security'=>$ajax_nonce, 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

}
add_action('init','church_admin_sermons_widget_init');

add_action('init','church_admin_download');
function church_admin_download()
{
    global $wpdb;
	if(!empty($_REQUEST['ca_download']))
	{
		
	$member_type_id=NULL;
	if(!empty($_REQUEST['loggedin'])){$loggedin=intval($_REQUEST['loggedin']);}else{$loggedin=FALSE;}
	if(!empty($_REQUEST['member_type_id']))$member_type_id=$_REQUEST['member_type_id'];
	if(!empty($_REQUEST['date'])){$date=$_REQUEST['date'];}else{$date=date('Y-m-d');}
	if(!empty($_REQUEST['pdf_font_resize'])){$resize=$_REQUEST['pdf_font_resize'];}else{$resize=FALSE;}
	if(!empty($_REQUEST['pdfversion'])){$pdfversion=$_REQUEST['pdfversion'];}else{$pdfversion=1;}
	if(!empty($_REQUEST['service_id'])){$service_id=intval($_REQUEST['service_id']);}else{$service_id=1;}
	if(!empty($_REQUEST['rota_id'])){$rota_id=$_REQUEST['rota_id'];}else{$rota_id=NULL;}
	if(!empty($_REQUEST['kids'])){$kids=$_REQUEST['kids'];}else{$kids=FALSE;}
	if(!empty($_REQUEST['id'])){$id=$_REQUEST['id'];}else{$id=FALSE;}
    if(!empty($_REQUEST['start_date'])){$start_date=$_REQUEST['start_date'];}else{$start_date=date('Y-m-d');}
    if(!empty($_REQUEST['end_date'])){$end_date=$_REQUEST['end_date'];}else{$end_date=date('Y-m-d',strtotime("+3 years"));}
        if(!empty($_REQUEST['showDOB'])){$showDOB=TRUE;}else{$showDOB=FALSE;}
	if(!empty($_REQUEST['service_id'])){$service_id=$_REQUEST['service_id'];}else{$service_id=FALSE;}
        if(!empty($_REQUEST['unit_id'])){$unit_id=$_REQUEST['unit_id'];}else{$unit_id=FALSE;}
        	if(!empty($_REQUEST['date_id'])){$date_id=$_REQUEST['date_id'];}else{$date_id=FALSE;}
	if(!empty($_REQUEST['title'])){$title=$_REQUEST['title'];}else{$title=__('Small Groups','church-admin');}
    switch($_REQUEST['ca_download'])
    {
        case 'service_booking_bubble_pdf':
            if(church_admin_level_check('Directory'))
            {
               require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
                church_admin_service_bubble_pdf($_GET['date_id'],$_GET['service_id'],FALSE); 
            }
        break;
        case 'service_booking_pdf':
            
            if(church_admin_level_check('Directory'))
            {
               require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
                church_admin_service_booking_pdf($_GET['date_id'],$_GET['service_id'],FALSE); 
            }
        break;
        case 'service_booking_alphabetical_pdf':
            
            if(church_admin_level_check('Directory'))
            {
               require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
                church_admin_service_booking_pdf($_GET['date_id'],$_GET['service_id'],TRUE); 
            }
        break;
        case 'unit-pdf':
            require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
            church_admin_unit_pdf($unit_id);
        break;
        case 'ical':
            require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
            church_admin_ical($date_id);
            exit();
        break;
        case 'attendance-csv':
            require_once(plugin_dir_path(__FILE__).'includes/csv.php');
            church_admin_attendance_csv_output();
        break;
        case 'bookings_csv':
            if(wp_verify_nonce($_REQUEST['_wpnonce'],'bookings_csv'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/events.php');
                church_admin_bookings_csv($_REQUEST['event_id']);
            }
        break;
        case 'bookings_pdf':
            if(wp_verify_nonce($_REQUEST['_wpnonce'],'bookings_pdf'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/events.php');
                church_admin_bookings_pdf($_REQUEST['event_id']);
            }
        break;
        case 'tickets':
            
            require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
            church_admin_tickets_pdf($_REQUEST['booking_ref']);
        break;
        case 'pdf-filter':
            if(church_admin_level_check('Directory'))
            {
                church_admin_debug("PDF filter");
                require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
                church_admin_filter_pdf();
            }else{echo __("You don't have permissions to do that",'church-admin');}
        break;
    	case'kidswork-checkin':
			if(church_admin_level_check('Kidswork'))
			{
				require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
				church_admin_kidswork_checkin_pdf($id,$service_id,$date);
			}else{echo __("You don't have permissions to do that",'church-admin');}
		break;
		case'gdpr-pdf':
            if(church_admin_level_check('Directory'))require_once(plugin_dir_path(__FILE__).'includes/directory.php');church_admin_gdpr_pdf();
        break;
    	case'address-list':
        case'addresslist':
			if(wp_verify_nonce($_GET['addresslist'],'address-list'))
			{			require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');

					switch($pdfversion)
                    {
                        case 1:default:     church_admin_address_pdf_family_photos($_REQUEST['member_type_id'],$loggedin,$showDOB);
                        break;
                        case 2:
                            church_admin_alt_photo_directory($member_type_id,$loggedin);
                        break;
                    }
                       
            }
        break;
        case'addresslist-family-photos':
			if(wp_verify_nonce($_GET['addresslist'],'address-list'))
			{
				require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
				church_admin_address_pdf_family_photos($_REQUEST['member_type_id'],$loggedin);
			}else{echo'<p>You can only download if coming from a valid link</p>';}
		break;	case'kidswork_pdf':require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_kidswork_pdf($member_type_id,$loggedin);break;
		//Rotas
        case 'rota-csv':
        case'rotacsv':
        	require_once(plugin_dir_path(__FILE__).'includes/rota.new.php');
        	church_admin_rota_csv($start_date,$end_date,$service_id);
        	
        break;
		case'rota':
		case'horizontal_rota_pdf':
			require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
			church_admin_new_rota_pdf($service_id,$date);
			break;
		/*case'rota':if(wp_verify_nonce($_GET['_wpnonce'],'rota')){require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_new_rota_pdf($service_id,$resize,$date);}else{echo'<p>You can only download if coming from a valid link</p>';}break;*/

		case 'hope_team_pdf':require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_hope_team_pdf();break;

		case'ministries_pdf':
			if(wp_verify_nonce($_REQUEST['_wpnonce'],'ministries_pdf')){
				require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
				church_admin_ministry_pdf();
			}else{
				echo'<p>You can only download if coming from a valid link</p>';
			}
		break;
        case 'csv-filter':
            if(church_admin_level_check('Directory'))
            {
                require_once(plugin_dir_path(__FILE__).'includes/csv.php');
                church_admin_people_csv();
            }
            else{echo __("You don't have permissions to do that",'church-admin');}
        break;
            
		case 'people-csv':
				if(wp_verify_nonce($_REQUEST['people-csv'],'people-csv'))
				{
					require_once(plugin_dir_path(__FILE__).'includes/csv.php');
					church_admin_people_csv();
				}
				else
				{
					echo'<p>You can only download if coming from a valid link</p>';
				}
		break;
		case 'small-group-xml':
				if(wp_verify_nonce($_REQUEST['small-group-xml'],'small-group-xml'))
				{

					require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
					church_admin_small_group_xml();
				}else{echo'<p>You can only download if coming from a valid link</p>';}
		break;
		case 'address-xml':

			require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
			church_admin_address_xml($_REQUEST['member_type_id'],$_REQUEST['small_group']);
			exit();
		break;
        case'cron-instructions':if(wp_verify_nonce($_GET['cron-instructions'],'cron-instructions')){require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_cron_pdf();}else{echo'<p>You can only download if coming from a valid link</p>';}break;

        case'yearplanner':if(wp_verify_nonce($_REQUEST['yearplanner'],'yearplanner')){require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_year_planner_pdf($_REQUEST['year']);}else{echo'<p>You can only download if coming from a valid link</p>';}break;
		case'smallgroup':
				if(wp_verify_nonce($_REQUEST['smallgroup'],'smallgroup'))
					{
						require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
						church_admin_smallgroup_pdf($_REQUEST['member_type_id'],$_GET['people_type_id'],$loggedin);
					}
					else{echo'<p>You can only download if coming from a valid link</p>';}
		break;
		case 'smallgroups':
			require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
			church_admin_smallgroups_pdf($loggedin,urldecode($title));
		break;




		case 'vcf':
            $okay=FALSE;
            if(!empty($_GET['token']))
            {
                $sql='SELECT user_id FROM '.CA_APP_TBL.' WHERE UUID="'.esc_sql(stripslashes($_GET['token'])).'"';
		          $result=$wpdb->get_var($sql);
		          if(!empty($result))$okay=TRUE;
            }
			if($okay||wp_verify_nonce($_REQUEST['_wpnonce'],$_REQUEST['id']))
			{
				require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');
				ca_vcard($_REQUEST['id']);
			}else{echo'<p>You can only download if coming from a valid link</p>';}
		break;
		case'mailinglabel':if(church_admin_level_check('Directory')){require_once(plugin_dir_path(__FILE__).'includes/pdf_creator.php');church_admin_label_pdf($member_type_id,$loggedin);}break;


    }
	exit();	
	}

}
function church_admin_delete_backup()
{
	/*
    $filename=get_option('church_admin_backup_filename');
	$upload_dir = wp_upload_dir();
	$path=$upload_dir['basedir'];
	if($filename&& file_exists($path.'/church-admin-cache/'.$filename))unlink($path.'/church-admin-cache/'.$filename);
	update_option('church_admin_backup_filename',"");
    echo'<div class="notice notice-success"><h2>'.__('Backup deleted','church-admin').'</h2></div>';
    */
    $upload_dir = wp_upload_dir();
	$path=$upload_dir['basedir'].'/church-admin-cache/*';
    $files = glob($path); // get all file names
    foreach($files as $file)
    { // iterate files
        if(is_file($file))unlink($file); // delete file
    }
    $text="<?php exit('----------------------------------------
  ___ _   _             _ _         _                 _         _
 |_ _| |_( )___    __ _| | |   __ _| |__   ___  _   _| |_      | | ___  ___ _   _ ___
  | || __|// __|  / _` | | |  / _` | '_ \ / _ \| | | | __|  _  | |/ _ \/ __| | | / __|
  | || |_  \__ \ | (_| | | | | (_| | |_) | (_) | |_| | |_  | |_| |  __/\__ \ |_| \__ \
 |___|\__| |___/  \__,_|_|_|  \__,_|_.__/ \___/ \__,_|\__|  \___/ \___||___/\__,_|___/
'); \r\n // Nothing is good! ";
    $fp = fopen($upload_dir['basedir'].'/church-admin-cache/debug_log.php', 'w');
    fwrite($fp, $text."\r\n");
    $fp = fopen($upload_dir['basedir'].'/church-admin-cache/index.php', 'w');
    fwrite($fp, $text."\r\n");
    echo'<div class="notice notice-success"><h2>'.__('Backup deleted','church-admin').'</h2></div>';
}
function church_admin_backup()
{
    global $church_admin_version,$wpdb;
    $content='';
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_ATT_TBL.'"') == CA_ATT_TBL)$content.=church_admin_datadump (CA_ATT_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_BIB_TBL.'"') == CA_BIB_TBL)$content.=church_admin_datadump (CA_BIB_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_BRP_TBL.'"') == CA_BRP_TBL)$content.=church_admin_datadump (CA_BRP_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_CAT_TBL.'"') == CA_CAT_TBL)$content.=church_admin_datadump (CA_CAT_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_CLA_TBL.'"') == CA_CLA_TBL)$content.=church_admin_datadump (CA_CLA_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_COM_TBL.'"') == CA_COM_TBL)$content.=church_admin_datadump (CA_COM_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_COV_TBL.'"') == CA_COV_TBL)$content.=church_admin_datadump (CA_COV_TBL);
		 if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_CUST_TBL.'"') == CA_CUST_TBL)$content.=church_admin_datadump (CA_CUST_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_DATE_TBL.'"') == CA_DATE_TBL)$content.=church_admin_datadump (CA_DATE_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_EVE_TBL.'"') == CA_EVE_TBL)$content.=church_admin_datadump (CA_EVE_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_BOO_TBL.'"') == CA_BOO_TBL)$content.=church_admin_datadump (CA_BOO_TBL);
    
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_PAY_TBL.'"') == CA_PAY_TBL)$content.=church_admin_datadump (CA_PAY_TBL);
    
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_TIK_TBL.'"') == CA_TIK_TBL)$content.=church_admin_datadump (CA_TIK_TBL);
if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_METRICS_TBL.'"') == CA_METRICS_TBL)$content.=church_admin_datadump (CA_METRICS_TBL);
if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_METRICS_META_TBL.'"') == CA_METRICS_META_TBL)$content.=church_admin_datadump (CA_METRICS_META_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_EBU_TBL.'"') == CA_EBU_TBL)$content.=church_admin_datadump (CA_EBU_TBL);
	if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_EMA_TBL.'"') == CA_EMA_TBL)$content.=church_admin_datadump (CA_EMA_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_FIL_TBL.'"') == CA_FIL_TBL)$content.=church_admin_datadump (CA_FIL_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_FAC_TBL.'"') == CA_FAC_TBL)$content.=church_admin_datadump (CA_FAC_TBL);
	if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_FP_TBL.'"') == CA_FP_TBL)$content.=church_admin_datadump (CA_FP_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_FUN_TBL.'"') == CA_FUN_TBL)$content.=church_admin_datadump (CA_FUN_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_HOU_TBL.'"') == CA_HOU_TBL)$content.=church_admin_datadump (CA_HOU_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_HOP_TBL.'"') == CA_HOP_TBL)$content.=church_admin_datadump (CA_HOP_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_IND_TBL.'"') == CA_IND_TBL)$content.=church_admin_datadump (CA_IND_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_KID_TBL.'"') == CA_KID_TBL)$content.=church_admin_datadump (CA_KID_TBL);
    
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_MET_TBL.'"') == CA_MET_TBL)$content.=church_admin_datadump (CA_MET_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_MTY_TBL.'"') == CA_MTY_TBL)$content.=church_admin_datadump (CA_MTY_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_PEO_TBL.'"') == CA_PEO_TBL)$content.=church_admin_datadump (CA_PEO_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_ROT_TBL.'"') == CA_ROT_TBL)$content.=church_admin_datadump (CA_ROT_TBL);
     if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_ROTA_TBL.'"') == CA_ROTA_TBL)$content.=church_admin_datadump (CA_ROTA_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_RST_TBL.'"') == CA_RST_TBL)$content.=church_admin_datadump (CA_RST_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SERM_TBL.'"') == CA_SERM_TBL)$content.=church_admin_datadump (CA_SERM_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SER_TBL.'"') == CA_SER_TBL)$content.=church_admin_datadump (CA_SER_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SERM_TBL.'"') == CA_SERM_TBL)$content.=church_admin_datadump (CA_SERM_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SMG_TBL.'"') == CA_SMG_TBL)$content.=church_admin_datadump (CA_SMG_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SIT_TBL.'"') == CA_SIT_TBL)$content.=church_admin_datadump (CA_SIT_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SES_TBL.'"') == CA_SES_TBL)$content.=church_admin_datadump (CA_SES_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SMET_TBL.'"') == CA_SMET_TBL)$content.=church_admin_datadump (CA_SMET_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_MIN_TBL.'"') == CA_MIN_TBL)$content.=church_admin_datadump (CA_MIN_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_UNI_TBL.'"') == CA_UNI_TBL)$content.=church_admin_datadump (CA_UNI_TBL);
    if ($wpdb->get_var('SHOW TABLES LIKE "'.CA_SUBU_TBL.'"') == CA_SUBU_TBL)$content.=church_admin_datadump (CA_SUBU_TBL);
    if(defined(OLD_CHURCH_ADMIN_VERSION))$content.='UPDATE '.$wpdb->prefix.'options SET option_value="'.OLD_CHURCH_ADMIN_VERSION.'" WHERE option_name="church_admin_version";'."\r\n";
    $sql='SELECT option_name, option_value FROM '.$wpdb->options.' WHERE `option_name` LIKE  "church%"';

    $options=$wpdb->get_results($sql);

    if(!empty($options))
    {
    	foreach($options AS $option)
    	{
    		$content.='DELETE FROM '.$wpdb->prefix.'options WHERE option_name="'.esc_sql($option->option_name).'";'."\r\n";
    		$content.='INSERT INTO  '.$wpdb->prefix.'options (option_name,option_value)VALUES("'.esc_sql($option->option_name).'","'.esc_sql($option->option_value).'");'."\r\n";
    	}
    }
	$length = 10;
	$randomString = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
	$filename=md5($randomString).'.sql.gz';
	update_option('church_admin_backup_filename',$filename);
	$upload_dir = wp_upload_dir();
	$path=$upload_dir['basedir'];
    if(!empty($content))
    {
		$gzdata = gzencode($content);
		$loc=$path.'/church-admin-cache/'.$filename;
		$fp = fopen($loc, 'w');
		fwrite($fp, $gzdata);
		fclose($fp);
	}

}
function church_admin_datadump ($table) {

	global $wpdb;

	$sql="select * from `$table`";
	$tablequery = $wpdb->get_results($sql,ARRAY_N);
	$num_fields=$wpdb->num_rows +1;

	if(!empty($tablequery))
	{

	    $result = "# Dump of $table \r\n";
	    $result .= "# Dump DATE : " . date("d-M-Y") ."\r\n";

	    $increment = $num_fields+1;
	    //build table structure
	    $sql = "SHOW COLUMNS FROM `$table`";
	    $query=$wpdb->get_results($sql);
	    if(!empty($query))
	    {
				$result.="DROP TABLE IF EXISTS `$table`;\r\n CREATE TABLE IF NOT EXISTS `$table` (";
				foreach($query AS $row)
				{
		    	$result.="`{$row->Field}` {$row->Type} ";
		    	if(isset($row->NULL)){$result.=" NULL ";}else {$result.=" NOT NULL ";}
		    	if($row->Key=='PRI'){$key=$row->Field;}
		    	if(!empty($row->Default))
		    	{
						$result.=" default '".$row->Default."'";
		    	}
		    	if(!empty($row->Extra)) $result.=' '.$row->Extra;
		    	$result.=',';
				}
				
	    	$result.="PRIMARY KEY (`{$key}`)) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=".$increment." ;\r\n";
	    	$result.="-- \r\n -- Dumping data for table `$table`\r\n--\r\n";
	    	//build insert for table
	    	$result.="-- \r\n -- Dumping data for table `$table`\r\n--\r\n";
			$result=str_replace("default 'CURRENT_TIMESTAMP' on update CURRENT_TIMESTAMP","default current_timestamp() on update current_timestamp()",$result);
			$result=str_replace("default 'current_timestamp()' on update current_timestamp()","default current_timestamp() on update current_timestamp()",$result);
	    	foreach($tablequery AS $row)
	    	{

					$result .= "INSERT INTO `".$table."` VALUES(";
						for($j=0; $j<count($row); $j++)
						{
		    			$row[$j] = addslashes($row[$j]);
		    			$row[$j] = str_replace("\n","\\n",$row[$j]);
		    			if (isset($row[$j])) $result .= "'{$row[$j]}'" ; else $result .= "''";
		    			if ($j<(count($row)-1)) $result .= ",";
						}
						$result .= ");\r\n";
	    	}

			}
			return $result;
	}
}

 function church_admin_activation_log_clear(){delete_option('church_admin_plugin_error');church_admin_front_admin();}



// Add a new interval of a week
// See http://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules
add_filter( 'cron_schedules', 'church_admin_add_weekly_cron_schedule' );
function church_admin_add_weekly_cron_schedule( $schedules ) {
    $schedules['weekly'] = array(
        'interval' => 604800, // 1 week in seconds
        'display'  => __( 'Once Weekly' ),
    );

    return $schedules;
}
if(!empty($_POST['email_rota_day']))
{
	$service_id=intval($_POST['service_id']);

	$en_rota_days=array(1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday');
	$email_day=(int)$_POST['email_rota_day'];
	$message=stripslashes($_POST['auto-rota-message']);
	$args=array('service_id'=>intval($service_id),'message'=>$message);
	update_option('church_admin_auto_rota_email_message',$message);

		update_option('church_admin_email_rota_day',$email_day);
		$first_run = strtotime($en_rota_days[$email_day]);
		wp_schedule_event($first_run, 'weekly','church_admin_cron_email_rota',$args);

}
add_action('church_admin_cron_email_rota','church_admin_auto_email_rota',1,2);
  /**
 *
 * Cron email rota
 *
 * @author  Andy Moyle, Mick Wall
 * @param    $service_id
 * @return   string
 * @version  0.1
 *
 */
function church_admin_auto_email_rota($service_id,$message=NULL)
{
    global $wpdb,$wp_locale;
		if(defined('CA_DEBUG'))church_admin_debug("Cron email of rota fired\r\n ".print_r($message,TRUE));
		if(defined('CA_DEBUG'))church_admin_debug('Service id '.$service_id);
  		if(empty($service_id))return FALSE;
	
		//$service=$wpdb->get_row('SELECT * FROM '.CA_SER_TBL .' WHERE service_id="'.esc_sql($service_id).'"');
        $service=$wpdb->get_row('SELECT a.*,b.venue FROM '.CA_SER_TBL.' a, '.CA_SIT_TBL.' b WHERE a.site_id=b.site_id AND a.service_id="'.esc_sql($service_id).'"');

		//MICK WALL - removed single line select.
		//$rota_date=$wpdb->get_var('SELECT rota_date FROM '.CA_ROTA_TBL.' WHERE mtg_type="service" AND service_id="'.intval($service_id).'" AND rota_date>=CURDATE() ORDER BY service_id,rota_date ASC LIMIT 1');
		require_once(plugin_dir_path(dirname(__FILE__)).'church-admin/includes/rota.new.php');
		$rotaJobs=church_admin_required_rota_jobs($service_id);

		//$rotaJobs is an array rota_task_id=>rota_task
		
		//MICK WALL
		//Change the SQL to look for DISTINCT dates in the next 1 week and then send rotas for all the dates
		$row=$wpdb->get_results('SELECT DISTINCT(rota_date), service_time FROM  wp_church_admin_new_rota WHERE mtg_type="service" AND  service_id="'.intval($service_id).'"  AND rota_date BETWEEN CURDATE() AND date_add(CURDATE(), INTERVAL 1 WEEK) ORDER BY service_id,rota_date ASC');

		foreach($row as $rota_data)
		{
			$rota_date=$rota_data->rota_date;;
			//build email
			

			//build rota with jobs
			
			//fix floated images for email
			$user_message=str_replace('class="alignleft ','style="float:left;margin-right:20px;" class="',$user_message);
			$user_message=str_replace('class="alignright ','style="float:right;margin-left:20px;" class="',$user_message);
			
			if($service->service_day!=8){$sendMessage=$message.'<h4>'.esc_html(sprintf(__('Schedule for %1$s at %2$s on %3$s at %4$s', 'church-admin' ), $service->service_name, $service->venue,$wp_locale->get_weekday($service->service_day).' '.mysql2date(get_option('date_format'),$rota_date),$service->service_time )).'</h4>';}
			//MICK WALL 
			//Update else to send date / time option.
			else{$sendMessage=$message.'<h4>'. esc_html(sprintf(__( 'Schedule for %1$s at %2$s on %3$s at %4$s', 'church-admin' ), $service->service_name, $service->venue, mysql2date(get_option('date_format'),$rota_date),$rota_data->service_time)).'</h4>';}

			
			$sendMessage.='<table><thead><tr><th>'.__('Job','church-admin').'</th><th>'.__('Who','church-admin').'</th></tr></thead><tbody>';
			$recipients=array();
			foreach($rotaJobs AS $rota_task_id=>$jobName)
			{
					$people='';

					$people=church_admin_rota_people_array($rota_date,$rota_task_id,$service_id,'service');

					if(!empty($people))
					{
						foreach($people AS $people_id=>$name)
						{

                            
							$email=$wpdb->get_var('SELECT email FROM '.CA_PEO_TBL.' WHERE people_id="'.intval($people_id).'" AND email!="" AND email_send=1 && gdpr_reason!=""');
                           //send copy to parent if a child
                            $moreData=$wpdb->get_row('SELECT * FROM '.CA_PEO_TBL.' WHERE people_id="'.intval($people_id).'"');
                            if($moreData->people_type_id!=1)
                            {
                                $parentEmail=$wpdb->get_var('SELECT email FROM '.CA_PEO_TBL.' WHERE household_id="'.intval($moreData->household_id).'" AND email!="" AND email_send=1 && gdpr_reason!="" ORDER BY people_order LIMIT 1');
                                $parentName=sprintf(__('Parent of %1$s',"church-admin"),$name);
                                if(!empty($parentEmail))$recipients[$parentName]=$parentEmail;
                            }
							if(!empty($email)&&!in_array($email,$recipients))$recipients[$name]=$email;
						}
						$sendMessage.='<tr><td>'.esc_html($jobName).'</td><td>'.esc_html(implode(", ",$people)).'</td></tr>';
					}
			}
			$sendMessage.='</table>';

			if(defined('CA_DEBUG'))church_admin_debug($sendMessage);
			//start emailing the message
			$sendMessage.='';
			if(!empty($recipients))
			{

				add_filter( 'wp_mail_from_name','church_admin_from_name' );
				add_filter( 'wp_mail_from', 'church_admin_from_email');
				add_filter('wp_mail_content_type','church_admin_email_type');
				foreach($recipients AS $name=>$email)
				{
					 	$email_content='<p>'.__('Dear','church-admin').' '.$name.',</p>'.$sendMessage;
						$whenToSend=get_option('church_admin_cron');
						if($whenToSend=='immediate'||empty($whenToSend))
						{

							add_filter( 'wp_mail_content_type', 'set_html_content_type' );
							if(wp_mail($email,__("This week's service schedule for ",'church-admin').mysql2date(get_option('date_format'),$rota_date),$email_content))
							{
							if(defined('CA_DEBUG'))church_admin_debug('Sent to '.$email);
							}
							else
							{//log errors
								global $phpmailer;
								if (isset($phpmailer)&&defined('CA_DEBUG')) {
									church_admin_debug("**********\r\n rota.new.php line303\r\n ".print_r($phpmailer->ErrorInfo,TRUE)."\r\n");
								}
							}
							remove_filter( 'wp_mail_content_type', 'set_html_content_type' );
						}
						else
						{
							QueueEmail($email,__("This week's service schedule",'church-admin'),$email_content,'',get_option('blogname'),get_option('admin_email'),'','');
						}	
				}
			}
		}	
		if(defined('CA_DEBUG'))church_admin_debug('Cron rota send finished');
		exit();
}
function church_admin_from_name( $from ) {if(!empty($_POST['from_name'])){return esc_html(stripslashes($_POST['from_name']));}else return get_option('blogname');}
function church_admin_from_email( $email ) {if(!empty($_POST['from_email'])){return esc_html(stripslashes($_POST['from_email']));}else return get_option('admin_email');}
function church_admin_debug($message)
{
    $text="<?php exit('Nothing is good!') ;?>";
	$upload_dir = wp_upload_dir();
	$debug_path=$upload_dir['basedir'].'/church-admin-cache/';
	if(file_exists($debug_path.'debug.log'))unlink($debug_path.'debug.log');
	if(!file_exists($debug_path.'debug_log.php'))
	{

		
		$fp = fopen($debug_path.'debug_log.php', 'w');
		fwrite($fp, $text."\r\n");
	}
	if(empty($fp))$fp = fopen($debug_path.'debug_log.php', 'a');
    fwrite($fp, $message."\r\n");
    fclose($fp);
    if(!file_exists($debug_path.'index.php'))
    {
        $fp = fopen($debug_path.'index.php', 'w');
        fwrite($fp, $text."\r\n");
        fclose($fp);
    }
}

register_deactivation_hook(__FILE__, 'church_admin_deactivation');

function church_admin_deactivation() {
	wp_clear_scheduled_hook('church_admin_bulk_email');
}
add_action('church_admin_bulk_email','church_admin_bulk_email');
function church_admin_bulk_email()
{

	global $wpdb;

	$max_email=get_option('church_admin_bulk_email');

	if(empty($max_email))$max_email=100;
	$sql='SELECT * FROM '.CA_EMA_TBL.' WHERE schedule="0000-00-00" OR schedule <=DATE(NOW()) LIMIT 0,'.$max_email;

	$result=$wpdb->get_results($sql);

	if(!empty($result))
	{
		foreach($result AS $row)
		{
			$headers="From: ".$row->from_name." <".$row->from_email.">\n";
			add_filter('wp_mail_content_type','church_admin_email_type');
			$email=$row->from_email;
			$from=$row->from_name;
			add_filter( 'wp_mail_from_name', 'church_admin_from_name');
			add_filter( 'wp_mail_from', 'church_admin_from_email');
			if(wp_mail($row->recipient,$row->subject,$row->message,$headers,unserialize($row->attachment)))
			{

				$wpdb->query('DELETE FROM '.CA_EMA_TBL.' WHERE email_id="'.esc_sql($row->email_id).'"');
			}else {if(defined('CA_DEBUG'))church_admin_debug( $_GLOBALS['phpmailer']->ErrorInfo);}
			remove_filter('wp_mail_content_type','church_admin_email_type');
		}
	}
}

//add donate link on config page
add_filter( 'plugin_row_meta', 'church_admin_plugin_meta_links', 10, 2 );
function church_admin_plugin_meta_links( $links, $file ) {
	$plugin = plugin_basename(__FILE__);
	// create link
	if ( $file == $plugin ) {
		return array_merge(
			$links,
			array( '<a href="http://www.churchadminplugin.com/support">Support</a>','<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7WWB7SQCRLUJ4">Donate</a>' )
		);
	}
	return $links;
}



/**
 *
 * Send out Prayer Request Post to the prayer chain
 *
 * @author  Andy Moyle
 * @param    null
 * @return   html
 * @version  0.1
 *
 */


add_action( 'transition_post_status', 'church_admin_post_message', 10, 3 );

function church_admin_post_message( $new_status, $old_status, $post ) {
	$debug=FALSE;//stop push notifications while testing
    $MailChimpSettings=get_option('church_admin_mailchimp');
    $no_push=get_option('church_admin_no_push');
	if($no_push)
    {
        church_admin_debug("No push");
        return;
    }
    
	global $wpdb,$videoURL;
	$title='';
	 $type=get_post_type( $post );
	 $sent=get_post_meta($post->ID,'Email Sent',TRUE);
     $pushOK=TRUE;
     if(!empty($_POST['church_admin_no_push']))$pushOK=FALSE;
     if(empty($sent)&& $new_status == 'publish' && $old_status != 'publish' && !empty($type) && ($type=='prayer-requests'||$type=='post'||$type=='bible-readings') && $pushOK)
     {
     	if(defined('CA_DEBUG'))church_admin_debug("*******************************\r\nPublish status changed to publish\r\n".date('Y-m-d H:i:s'));
		$user=wp_get_current_user();
		if(defined('CA_DEBUG'))church_admin_debug("User \r\n".print_r($user->data,TRUE));
		switch($type)
		{
			case 'acts-of-courage':$title=__('New Act of Courage','church-admin');$contactType='acts-of-courage';$ministry=__('Prayer requests send','church-admin');break;
			case 'prayer-requests':
				$title=__('New Prayer Request','church-admin');$contactType='prayer';
				$ministry=__('Prayer requests send','church-admin');
				
			break;
			case 'bible-readings':$title=__('New Bible Reading','church-admin');$contactType='bible';$ministry=__('Bible readings send','church-admin');break;
			case 'post':$title=__('New Blog Post','church-admin');$contactType='news';$ministry=__('News send','church-admin');break;
		}
	 
		if(defined('CA_DEBUG'))church_admin_debug("ContactType = $contactType Ministry = $ministry");
		/****************************************
		*
		* Push Notification
		*
		****************************************/
		if($_SERVER['SERVER_NAME']!="localhost")
        {
        	if(defined('CA_DEBUG'))church_admin_debug("Post type is $type");
				//app
				
				$api_key="AAAA50JK2is:APA91bE-SZWcUncaSxdbevuGOdochq7zS2fgJabNBAmbqBnmR8Lq4BoaQwG_p-JM2Ftx5rAKInlnG5RmxhWW_LcOPW9A9cQqpg7tUA1GFi1-NvX2q5YbFqnM9ZmV5xuE0PfeRWFUL1d4Te4zwzpu5qglwzZpg_JWzg";
				$headers = array('Authorization: key=' . $api_key,'Content-Type: application/json');
				
				if(defined('CA_DEBUG'))church_admin_debug("Prepping FCM bundle");
			       
                $url = 'https://fcm.googleapis.com/fcm/send';
				
				$appID=get_option('church_admin_app_id');
				if(defined('CA_DEBUG'))church_admin_debug("App id is $appID");
				
				if(!empty($appID))
				{// prep the bundle
					if(defined('CA_DEBUG'))church_admin_debug("Prepping FCM bundle");
			 		
					$headers = array('Authorization: key=' . $api_key,'Content-Type: application/json');
					$message=$title.' - '.$post->post_title;
                    
                    //updated for iOS13 which requires APNS headers
                    
                    
                    $data=array("notification"=>array("title"=>"Church App",
													  "body"=>$message,
													  "sound"=>"default",
													  //"click_action"=>"FCM_PLUGIN_ACTIVITY",
													  "icon"=>"fcm_push_icon",
													  "content_available"=> 1,
                                                      'apnsPushType'=>'alert'
													 ),
                                  "apns"=> array(
                                            'headers'=> array( 
                                                        'apns-push-type'=> 'alert',
                                                        "apns-priority"=>5,
                                                        "apns-topic"=>"com.churchadminplugin.wpchurch"
                                            ),
                                            "payload"=>array("alert"=>array("title"=>"Church App","body"=>$message),
                                                             "aps"=>array( "content-available"=>1),
                                                             "sound"=>"default","content-available"=>1
                                                            ),
                        
                                ),
								"data"=>array(  "notification_foreground"=>TRUE,
                                                "notification_body" => $message,
                                                "notification_title"=> "Church App",
                                              "notification_android_priority"=>1,
                                              "notification_ios_sound"=>"default",
                                              "sound"=>"default",
                                                "title"=>"Church App",
											  "body"=>$message,
											  "type"=>$contactType,
											  "senderName"=>$username,
												"timestamp"=>mysql2date(get_option('date_format').' '.get_option('time_format'),date("Y-m-d H:i:s"))
										),
								"to"=>"/topics/church".$appID,
								"priority"=>"high"
								);

					if(defined('CA_DEBUG'))church_admin_debug("Headers:\r\n".print_r($headers,TRUE));
					
					church_admin_debug("Data:\r\n".print_r($data,TRUE));
					$ch = curl_init ();
    				curl_setopt ( $ch, CURLOPT_URL, $url );
    				curl_setopt ( $ch, CURLOPT_POST, true );
    				curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
    				curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
	    			curl_setopt ( $ch, CURLOPT_POSTFIELDS, json_encode($data) );

    				$result = curl_exec ( $ch );
    				if(defined('CA_DEBUG'))church_admin_debug(curl_error($ch));
					if(defined('CA_DEBUG'))church_admin_debug(print_r($result,TRUE));
    				curl_close ( $ch );
				}
    	}//only send push if not localhost
		
		/***************************************
		*
		*	Email send
		*
		****************************************/
		if($_SERVER['SERVER_NAME']!="localhost")
        {
             //prayer chain emails
            $post_title = get_the_title( $post->ID );
            $post_url = get_permalink( $post->ID );

            $email_title=$title.' - '.$post->post_title;
            $content_post = get_post($post->ID);
            $content = $content_post->post_content;
           
            $content = apply_filters('the_content', $content);
            $content = str_replace(']]>', ']]&gt;', $content);
            /**************************************************
            *
            *   Fix embedded video from shortcode 2020-07-02
            *
            ***************************************************/
                $firstPart='<div class="container-fluid no-padding"><div style="position:relative;padding-top:56.25%"><iframe class="ca-video" style="position:absolute;top:0;left:0;width:100%;height:100%;" src="';
                $secondPart='" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>';
                $buttonFirstPart='<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td><table border="0" cellspacing="0" cellpadding="0"><tr><td align="center" style="border-radius: 3px;" bgcolor="#e9703e"><a href="';
                $buttonSecondPart='" target="_blank" style="font-size: 16px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; text-decoration: none;border-radius: 3px; padding: 12px 18px; border: 1px solid #e9703e; display: inline-block;">'.__('Link to video','church-admin').' &rarr;</a></td></tr></table></td></tr></table>';

                $content=str_replace($firstPart,$buttonFirstPart,$content);
                $content=str_replace($secondPart,$buttonSecondPart,$content);
                      $content=str_replace('https://www.youtube.com/embed/','https://www.youtube.com/watch?v=',$content);
                $content=str_replace('https://player.vimeo.com/video/','https://vimeo.com/',$content);
            /**************************************************
            *
            *   End of fix embedded video from shortcode 2020-07-02
            *
            ***************************************************/
            if($type=='bible-readings')
            {
                $version=get_option('church_admin_bible_version');
                $passage=get_post_meta( $post->ID ,'bible-passage',TRUE);
                if(!empty($debug))church_admin_debug('Passage:'.$passage);
                $custom_content ='<div class="ca-bible-date">'.get_the_date().'</div>';
                if(!empty($passage))$custom_content .= '<p class="ca-bible-reading"><a href="https://www.biblegateway.com/passage/?search='.urlencode($passage).'&version='.urlencode($version).'&interface=print" target="_blank" >'.esc_html($passage).'</a></p>';

                $content=$custom_content.$content;
                $email_title=__('New Bible Reading','church-admin').' - '.$passage;
            }



            $MailChimpSettings=get_option('church_admin_mailchimp');
            if(defined('CA_DEBUG'))church_admin_debug(print_r($MailChimpSettings,TRUE));


            if(empty($MailChimpSettings['api_key'])&&$_SERVER['SERVER_NAME']!="localhost")
            {
                if(defined('CA_DEBUG'))church_admin_debug("Standard email");
                //standard email 
                //prepare send
                //$sql='SELECT  DISTINCT email,CONCAT_WS(" ",first_name,last_name) AS name FROM '.CA_PEO_TBL.' WHERE  prayer_chain=1 AND email!=""';
                //FROM v1.2608 prayer requests and bible readings are ministries and people who get them in church_admin_people_meta
                $ministryID=$wpdb->get_var('SELECT ID FROM '.CA_MIN_TBL.' WHERE ministry="'.esc_sql($ministry).'"');
                $sql='SELECT a.email,CONCAT_WS(" ",a.first_name,a.last_name) AS name FROM '.CA_PEO_TBL.' a, '.CA_MET_TBL.' b WHERE a.people_id=b.people_id AND b.meta_type="'.esc_sql($type).'"  AND a.email!="" AND email_send!=0 AND gdpr_reason!=""';

                $results=$wpdb->get_results($sql);
                foreach($results AS $row)
                {
                    if(get_option('church_admin_cron')!='immediate')
                    {
                                QueueEmail($row->email, $title,'<h2>'.$email_title.'</h2>'.$content,NULL,$user->name,$user->email,'');
                                if(defined('CA_DEBUG'))church_admin_debug("Prayer chain to ".$row->email.' '.date('Y-m-d h:i:s'));
                    }
                    else
                    {
                                add_filter('wp_mail_content_type','church_admin_email_type');
                                add_filter( 'wp_mail_from_name', 'church_admin_from_name');
                                add_filter( 'wp_mail_from', 'church_admin_from_email');
                                if(!wp_mail($row->email,$email_title,'<h2>'.$email_title.'</h2>'.$content))
                                {
                                    if(defined('CA_DEBUG'))church_admin_debug("Prayer Chain email failure\r\n");
                                }
                                else{if(defined('CA_DEBUG'))church_admin_debug("Prayer chain to ".$row->email);}
                                remove_filter('wp_mail_content_type','church_admin_email_type');
                    }
                }

            }//end use native mail
            elseif(!empty($MailChimpSettings['api_key']))
            {
                if(defined('CA_DEBUG'))church_admin_debug('Using mailchimp');



                require_once(plugin_dir_path(dirname(__FILE__)).'church-admin/includes/mailchimp.inc.php');
                $MailChimp = new MailChimp($MailChimpSettings['api_key']);
                $MailChimp->verify_ssl = 'false';


                $MailChimpTags=get_option('church-admin-MailChimp-Tags');

                /**********************************************************************************************************************************
                *
                * From v2.2510 Church Admin syncs using tags not segmenting, setting option church-admin-MailChimp-Tags with array id=>tag
                *
                **********************************************************************************************************************************/
                if($MailChimpTags)
                {
                        $tagID=array_search($ministry,$MailChimpTags);//MailChimp id for the tag
                        $segment_opts =array
                            (
                                    'match' => 'any', // or 'all' or 'none'
                                    'conditions'=>array(array('condition_type'=>'StaticSegment','op'=>'static_is','field'=>'static_segment','value'=>$tagID))
                                );
                }
                else
                {
                    $mailChimpInterests=get_option('church_admin_MailChimpInterests');
                    //still using segments
                    $segment_opts =
                    array(
                                'match' => 'any', // or 'all' or 'none'
                                'conditions' => array (
                                    array(
                                    'condition_type' => 'Interests', // note capital I
                                    'field' => 'interests-'.$MailChimpSettings['ministry_id'], // ID of interest category
                                                   // This ID is tricky: it is
                                                   // the string "interests-" +
                                                   // the ID of interest category
                                                   // that you get from MailChimp
                                                   // API (31f7aec0ec)
                                    'op' => 'interestcontains', // or interestcontainsall, interestcontainsnone
                                    'value' => array (
                                        $mailChimpInterests[__('Ministries','church-admin')][$ministry]
                                    )
                                )
                            )
                    );
                }

                $user= new stdClass();
                $user->email=get_option('admin_email');
                $user->name=get_option('blogname');

                $result = $MailChimp->post("campaigns", array(
                    'type' => 'regular',
                    'recipients' => array('list_id' =>$MailChimpSettings['listID'],'segment_opts'=>$segment_opts),
                    'settings' => array('subject_line' => $email_title,'reply_to' => $user->email,'from_name' => $user->name)
                ));
                if (!$MailChimp->success()&&defined('CA_DEBUG')) {church_admin_debug( "Post Campaign Error\r\n".print_r($MailChimp->getLastError(),TRUE));church_admin_debug(print_r($MailChimp->getLastRequest(),TRUE));}
                $response = $MailChimp->getLastResponse();


                $responseObj = json_decode($response['body']);
                if(defined('CA_DEBUG'))church_admin_debug(print_r($responseObj,TRUE));
                if(!empty($responseObj->id))
                {
                    $result = $MailChimp->put('campaigns/' . $responseObj->id . '/content', array('html' =>  $content));
                    if (!$MailChimp->success()&&defined('CA_DEBUG')) {church_admin_debug( "Put Campaign Error\r\n".$MailChimp->getLastError());}

                    $result = $MailChimp->post('campaigns/' . $responseObj->id . '/actions/send');
                    if (!$MailChimp->success()&&defined('CA_DEBUG')) {church_admin_debug( "Send Campaign Error\r\n".$MailChimp->getLastError());}
                }
            }//not on localhost
		}
		//set sent field in post meta
		update_post_meta( $post->ID, 'Email Sent', 1);
	} //just published
     
}

function ca_prayer_create_posttype() {
	$labels = array(
		'name'                => _x( 'Prayer Requests', 'Post Type General Name', 'church-admin' ),
		'singular_name'       => _x( 'Prayer Request', 'Post Type Singular Name', 'church-admin' ),
		'menu_name'           => __( 'Prayer Requests', 'church-admin' ),
		'parent_item_colon'   => __( 'Parent Prayer Request', 'church-admin' ),
		'all_items'           => __( 'All Prayer Requests', 'church-admin' ),
		'view_item'           => __( 'View Prayer Request', 'church-admin' ),
		'add_new_item'        => __( 'Add New Prayer Request', 'church-admin' ),
		'add_new'             => __( 'Add New', 'church-admin' ),
		'edit_item'           => __( 'Edit Prayer Request', 'church-admin' ),
		'update_item'         => __( 'Update Prayer Request', 'church-admin' ),
		'search_items'        => __( 'Search Prayer Requests', 'church-admin' ),
		'not_found'           => __( 'Not Found', 'church-admin' ),
		'not_found_in_trash'  => __( 'Not found in Trash', 'church-admin' ),
	);
	$noPrayer=get_option('church-admin-no-prayer');
	if(empty($noPrayer))
	{
		register_post_type( 'prayer-requests',
	// CPT Options
		array(
			'labels' => $labels,
			'public' => true,
			'exclude_from_search'=>false,
			'has_archive' => true,
			'publicly_queryable'=>true,
			'show_ui'=>true,
			'supports' => array( 'thumbnail','title','editor','comments' ),
			'show_in_menu'        => TRUE,
			'show_in_nav_menus'   => TRUE
		)
	);
	}
}
add_action( 'init', 'ca_prayer_create_posttype' );

/****************************************************************************************
*
*  From v2.2520 app content has it's own post type app-content, so create app-content and move content over
*
****************************************************************************************/
add_action( 'init', 'ca_app_content_create_posttype' );
function ca_app_content_create_posttype() {
	$licence=get_option('church_admin_app_new_licence');
	if(!empty($licence) && $licence == "subscribed")
	{
		
		$labels = array(
			'name'                => _x( 'App Content', 'Post Type General Name', 'church-admin' ),
			'singular_name'       => _x( 'App Content', 'Post Type Singular Name', 'church-admin' ),
			'menu_name'           => __( 'App Content', 'church-admin' ),
			'parent_item_colon'   => __( 'Parent App Content', 'church-admin' ),
			'all_items'           => __( 'All App Content', 'church-admin' ),
			'view_item'           => __( 'View App Content', 'church-admin' ),
			'add_new_item'        => __( 'Add New App Content', 'church-admin' ),
			'add_new'             => __( 'Add New', 'church-admin' ),
			'edit_item'           => __( 'Edit App Content', 'church-admin' ),
			'update_item'         => __( 'Update App Content', 'church-admin' ),
			'search_items'        => __( 'Search App Content', 'church-admin' ),
			'not_found'           => __( 'Not Found', 'church-admin' ),
			'not_found_in_trash'  => __( 'Not found in Trash', 'church-admin' ),

		);

		register_post_type( 'app-content',
		// CPT Options
			array(
			'labels' => $labels,
			'public' => true,
			'exclude_from_search'=>true,
			'has_archive' => true,
			'publicly_queryable'=>true,
			'show_ui'=>true,
			'supports' => array( 'thumbnail','title','editor'),
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true
			)
		);
		church_admin_fix_app_default_content();
	}	
}
/****************************
*
*
* Bible Reading Plan
*
*
*****************************/

function ca_bible_reading_create_posttype() {
$noBible=get_option('church-admin-no-bible-readings');
	if(empty($noBible))
	{
	$labels = array(
		'name'                => _x( 'Bible Readings', 'Post Type General Name', 'church-admin' ),
		'singular_name'       => _x( 'Bible Reading', 'Post Type Singular Name', 'church-admin' ),
		'menu_name'           => __( 'Bible Readings', 'church-admin' ),
		'parent_item_colon'   => __( 'Parent Bible Reading', 'church-admin' ),
		'all_items'           => __( 'All Bible Readings', 'church-admin' ),
		'view_item'           => __( 'View Bible Reading', 'church-admin' ),
		'add_new_item'        => __( 'Add New Bible Reading', 'church-admin' ),
		'add_new'             => __( 'Add New', 'church-admin' ),
		'edit_item'           => __( 'Edit Bible Reading', 'church-admin' ),
		'update_item'         => __( 'Update Bible Reading', 'church-admin' ),
		'search_items'        => __( 'Search Bible Readings', 'church-admin' ),
		'not_found'           => __( 'Not Found', 'church-admin' ),
		'not_found_in_trash'  => __( 'Not found in Trash', 'church-admin' ),

	);

	register_post_type( 'bible-readings',
	// CPT Options
		array(
			'labels' => $labels,
			'public' => true,
			'exclude_from_search'=>false,
			'has_archive' => true,
			'publicly_queryable'=>true,
			'show_ui'=>true,
			'supports' => array( 'thumbnail','title','editor','comments' ),
			'show_in_menu'        => TRUE,
			'show_in_nav_menus'   => TRUE
			)
	);
	}
}

add_action( 'init', 'ca_bible_reading_create_posttype' );
/****************************
*
*
* Acts of courage
*
*
*****************************/

function ca_acts_of_courage_create_posttype() {

	$acts=get_option('church-admin-acts-of-courage');
	if($acts)
	{
		$labels = array(
		'name'                => _x( 'Acts of Courage', 'Post Type General Name', 'church-admin' ),
		'singular_name'       => _x( 'Act of Courage', 'Post Type Singular Name', 'church-admin' ),
		'menu_name'           => __( 'Acts of Courage', 'church-admin' ),
		'parent_item_colon'   => __( 'Parent Act of Courage', 'church-admin' ),
		'all_items'           => __( 'All Acts of Courage', 'church-admin' ),
		'view_item'           => __( 'View Act of Courage', 'church-admin' ),
		'add_new_item'        => __( 'Add New Acts of Courage', 'church-admin' ),
		'add_new'             => __( 'Add New', 'church-admin' ),
		'edit_item'           => __( 'Edit Acts of Courage', 'church-admin' ),
		'update_item'         => __( 'Update Act of Courage', 'church-admin' ),
		'search_items'        => __( 'Search Acts of Courage', 'church-admin' ),
		'not_found'           => __( 'Not Found', 'church-admin' ),
		'not_found_in_trash'  => __( 'Not found in Trash', 'church-admin' ),

		);

		register_post_type( 'acts-of-courage',
	// CPT Options
		array(
			'labels' => $labels,
			'public' => true,
			'exclude_from_search'=>false,
			'has_archive' => true,
			'publicly_queryable'=>true,
			'show_ui'=>true,
			'supports' => array( 'thumbnail','title','editor','comments' ),
			'show_in_menu'        => TRUE,
			'show_in_nav_menus'   => TRUE
		)
	);
}
}

add_action( 'init', 'ca_acts_of_courage_create_posttype' );
/**

* Adds a meta box to the post editing screen

*/

function ca_brp_custom_meta() {

    add_meta_box( 'ca_brp_meta', __( 'Scripture', 'church-admin' ), 'ca_brp_meta_callback', 'bible-readings','after_title','high' );

}

add_action( 'add_meta_boxes', 'ca_brp_custom_meta' );

add_action('edit_form_after_title',  'ca_move_metabox_after_title'  );

 

function ca_move_metabox_after_title () {

    global $post, $wp_meta_boxes;

    do_meta_boxes( get_current_screen(), 'after_title', $post );

    unset( $wp_meta_boxes[get_post_type( $post )]['after_title'] );

}
/**
 * Outputs the content of the meta box
 */
function ca_brp_meta_callback( $post ) 
{
    wp_nonce_field( basename( __FILE__ ), 'ca_brp__nonce' );
    $stored_meta = get_post_meta( $post->ID ,'bible-passage',TRUE);
    ?>

    <p>
        <label for="meta-text" class="ca_brp_-row-title"><?php _e( 'Bible Passage', 'church-admin' )?></label>
        <input type="text" name="meta-text" class="large-text" id="meta-text" value="<?php if ( isset ( $stored_meta ) ) echo $stored_meta; ?>" />
    </p>

    <?php
}

/**
 * Saves the custom meta input
 */
function ca_brp__meta_save( $post_id ) {

    // Checks save status
    $is_autosave = wp_is_post_autosave( $post_id );
    $is_revision = wp_is_post_revision( $post_id );
    $is_valid_nonce = ( isset( $_POST[ 'ca_brp__nonce' ] ) && wp_verify_nonce( $_POST[ 'ca_brp__nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';

    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }

    // Checks for input and sanitizes/saves if needed
    if( isset( $_POST[ 'meta-text' ] ) ) {
        update_post_meta( $post_id, 'bible-passage', sanitize_text_field( $_POST[ 'meta-text' ] ) );
    }

}
add_action( 'save_post', 'ca_brp__meta_save' );

// Add the custom columns to the bible-readings post type:
add_filter( 'manage_bible-readings_posts_columns', 'church_admin_set_custom_bible_readings_columns' );
function church_admin_set_custom_bible_readings_columns($columns) {
    
    $newcolumns=array() ;
    foreach($columns as $key=>$value) {
        if($key=='comments') {  // when we find the date column
           $newcolumns['passage'] = __( 'Bible passage', 'church-admin' ); // put the tags column before it
        }    
        $newcolumns[$key]=$value;
    }  
        
     return $newcolumns;
}

// Add the data to the custom columns for the book post type:
add_action( 'manage_bible-readings_posts_custom_column' , 'church_admin_custom_bible_readings_column', 10, 2 );
function church_admin_custom_bible_readings_column( $column, $post_id ) {
    switch ( $column ) {

        case 'passage' :
            $passages=get_post_meta($post_id,'bible-passage',true);
            if ( is_string( $passages ) )
                echo esc_html($passages);
            else
                _e( 'Unable to get passage(s)', 'church-admin' );
            break;
    }
}

function ca_bible_reading_passage( $content ) {

//this function prepends the passage to content for bible readings
	global $post;
    if ( is_single() && 'bible-readings' == get_post_type() ) {
        $version=get_option('church_admin_bible_version');
        $passage=get_post_meta( $post->ID ,'bible-passage',TRUE);
        $dayNo=get_the_date('z')+1;
        $custom_content ='<div class="ca-bible-date">'.get_the_date().' '.__('Day','church-admin').' '.$dayNo.'</div>';
		if(!empty($passage))$custom_content .= '<p class="ca-bible-reading"><a href="https://www.biblegateway.com/passage/?search='.urlencode($passage).'&version='.urlencode($version).'&interface=print" target="_blank" >'.esc_html($passage).'</a></p>';
        $custom_content .= $content;
        return $custom_content;
    } else {
        return $content;
    }
}
add_filter( 'the_content', 'ca_bible_reading_passage' );
/****************************
*
*
* Ajax operations
*
*
*****************************/





add_action('wp_ajax_church_admin_rota_dates','church_admin_ajax_rota_dates');
function church_admin_ajax_rota_dates()
{
	global $wpdb;
	//check_admin_referer('church_admin_rota_dates','nonce');
	$sql='SELECT rota_date FROM '.CA_ROTA_TBL.' WHERE mtg_type="service" AND service_id="'.intval($_REQUEST['service_id']).'" AND rota_date>=CURDATE() GROUP BY rota_date ORDER BY rota_date ASC LIMIT 12';

	$results=$wpdb->get_results($sql);
	if(!empty($results))
	{
		$out='<select name="rota_date">';
		foreach($results AS $row)
		{
			$out.='<option value="'.esc_html($row->rota_date).'">'.mysql2date(get_option('date_format'),$row->rota_date).'</option>';
		}
		$out.='</select>';

	}else{$out=__('No dates yet, create some first!','church-admin');}
    echo $out;
	exit();
}



add_action('wp_ajax_church_admin_calendar_date_display','church_admin_date');
add_action('wp_ajax_nopriv_church_admin_calendar_date_display', '');

/**
 *
 * Ajax image upload
 *
 * @author  Andy Moyle
 * @param    null
 * @return   html
 * @version  0.1
 *
 */
add_action('wp_ajax_church_admin_image_upload','church_admin_image_upload');
add_action('wp_ajax_nopriv_church_admin_image_upload', 'church_admin_image_upload');
function church_admin_image_upload()
{
	if(defined('CA_DEBUG'))church_admin_debug("********************\r\nAJAX Image upload");
	// These files need to be included as dependencies when on the front end.
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php' );
	// Let WordPress handle the upload.
	// Remember, 'my_image_upload' is the name of our file input in our form above.
	$attachment_id = media_handle_upload( 'file-0', 0 );
	if(defined('CA_DEBUG'))church_admin_debug("attachment_id: ".$attachment_id);
	
		// The image was uploaded successfully!
		$image=wp_get_attachment_image_src(  $attachment_id, "thumbnail", false );
		if(defined('CA_DEBUG'))church_admin_debug(print_r($image,TRUE));
		echo json_encode(array('src'=>$image[0],'attachment_id'=>$attachment_id));
		exit();
	

}

/**
 *
 * Popup of calendar events
 *
 * @author  Andy Moyle
 * @param    null
 * @return   html
 * @version  0.1
 *
 */
add_action('wp_ajax_church_admin_calendar_event_display','church_admin_calendar_event_display');
add_action('wp_ajax_nopriv_church_admin_calendar_event_display', 'church_admin_calendar_event_display');
function church_admin_calendar_event_display()
{
	if(defined('CA_DEBUG'))church_admin_debug('Calendar Event' .date('Y-m-d h:i:s'));
	global $wpdb;
	$date_sql=1;
	$out='';
	$dates=explode(',',$_POST['date']);
    foreach($dates AS $key=>$value){ $datesql[]='a.start_date="'.esc_sql($value).'"';}
    if(!empty($datesql)) {$date_sql=' ('.implode(' || ',$datesql).')';}else{ exit__('No event to show','church-admin');}

	$sql='SELECT a.*, b.* FROM '.CA_DATE_TBL.' a LEFT JOIN '.CA_CAT_TBL.' b ON b.cat_id = a.cat_id WHERE '.$date_sql;


	$result=$wpdb->get_results($sql);

	if(!empty($result))
	{
		foreach($result AS $row)
		{
			$out.='<div class="ca-event ">';
			$out.='<span class="ca-close">x</span>';
			$out.='<h2 style="color:'.esc_html($row->bgcolor).'">'.esc_html($row->title).'</h2>';
			$out.='<p>'.mysql2date(get_option('date_format'),$row->start_date).' '.mysql2date(get_option('time_format'),$row->start_time).' -  '.mysql2date(get_option('time_format'),$row->end_time).'</p>';
			if(!empty($row->description))$out.='<p>'.esc_html($row->description).'</p>';
			if(!empty($row->page_id))$out.='<p><a href="'.get_permalink($row->page_id).'">'.__('More information','church-admin').'</p>';
			if(!empty($row->booking_id))$out.='<p><a class="button-primary" href="'.get_permalink($row->booking_id).'">'.__('Book Now','church-admin').'</p>';
			$out.='</div>';
		}
	}
	else
	{
		$out= __('No event to show','church-admin');
	}
	echo json_encode(array('id'=>esc_html($_POST['date']),'output'=>$out));
	exit();
}

add_action( 'wp_ajax_dismissed_notice_handler', 'church_admin_ajax_notice_handler' );

//new ajax

add_action('wp_ajax_church_admin','church_admin_ajax_handler');
add_action('wp_ajax_nopriv_church_admin', 'church_admin_ajax_handler');

function church_admin_ajax_handler()
{
	global $wpdb;
		
		switch ($_REQUEST['method'])
		{
            case 'remove-from-favourites':
                //church_admin_debug(print_r($_POST,TRUE));
                check_ajax_referer('remove-from-favourites','nonce');
                $user_id=intval($_POST['user_id']);
                $what=$_POST['what'];
                $favourites=get_option('church-admin-favourites');
                $userFavourites=$favourites[$user_id];
                $key = array_search($what, $userFavourites);
               if ($key !== false)
                {
                    church_admin_debug($key);
                    unset($favourites[$user_id][$key]);
                }
                update_option('church-admin-favourites',$favourites);
                church_admin_favourites_menu($user_id);
                exit();
            break;
            case 'add-to-favourites':
                check_ajax_referer('add-to-favourites','nonce');
                $user_id=intval($_POST['user_id']);
                $what=$_POST['what'];
                $favourites=array();
                $favourites=get_option('church-admin-favourites');
                $favourites[$user_id][]=$what;
                update_option('church-admin-favourites',$favourites);
                church_admin_favourites_menu($user_id);
                exit();
            break;
            case 'email-checker':
                //check_ajax_referer('email-checker');
                $email=stripslashes($_POST['email']);
                if(is_email($email)&&email_exists($email))
                {
                    echo'Found';
                }else echo'Not-yet';
                exit();
            break;
            case 'remove-series-image':
                check_ajax_referer('remove-series-image');
                $wpdb->query('UPDATE '.CA_SER_TBL.' SET attachment_id="" WHERE series_id="'.intval($_REQUEST['series_id']).'"');
                wp_delete_attachment($_REQUEST['series_id']);
                exit();
            break;
            case 'calendar-render':
                require_once(plugin_dir_path( __FILE__) .'/display/calendar.new.php');
                $d=explode("-",$_REQUEST['date']);
                church_admin_render_month($d[1],$d[0],$d[2]);
                exit();
            break;
            case 'calendar-day-render':
                require_once(plugin_dir_path( __FILE__) .'/display/calendar.new.php');
               
                church_admin_render_day($_REQUEST['date']);
                exit();
            break;
            case 'rota-dates':
                $sql='SELECT rota_date FROM '.CA_ROTA_TBL.' WHERE mtg_type="service" AND service_id="'.intval($_REQUEST['service_id']).'" AND rota_date>=CURDATE() GROUP BY rota_date ORDER BY rota_date ASC LIMIT 12';

                $results=$wpdb->get_results($sql);
                if(!empty($results))
                {
                    $out='<select name="rota_date">';
                    foreach($results AS $row)
                    {
                        $out.='<option value="'.esc_html($row->rota_date).'">'.mysql2date(get_option('date_format'),$row->rota_date).'</option>';
                    }
                    $out.='</select>';

                }else{$out=__('No dates yet, create some first!','church-admin');}
                echo $out;
                exit();
            break;
            case 'edit-app-menu':
                check_ajax_referer('edit-app-menu','nonce');
                $chosenMenu=get_option('church_admin_app_new_menu');
                $menuItem=stripslashes($_POST['menuItem']);
                $menuTitle=stripslashes($_POST['menuTitle']);
                if(!empty($chosenMenu[$menuItem]))
                {
                    $chosenMenu[$menuItem]['item']=$menuTitle;
                }
                update_option('church_admin_app_new_menu',$chosenMenu);
                echo '<span class="ca-editable" data-item="'.$menuItem.'">'.$menuTitle.'</span>';
                exit();
            break;
            case 'app-menu-show':
                check_ajax_referer('edit-app-menu','nonce');
                $chosenMenu=get_option('church_admin_app_new_menu');
                //church_admin_debug(print_r($chosenMenu,TRUE));
                $menuItem=stripslashes($_POST['menuItem']);
                $status=$_POST['status'];
                if($status=="ON")
                {
                    $chosenMenu[$menuItem]['show']=1;
                }else  $chosenMenu[$menuItem]['show']=0;
                update_option('church_admin_app_new_menu',$chosenMenu);
                //church_admin_debug(print_r($chosenMenu,TRUE));
                echo'DONE';
                exit();
            break;    
            case 'app-menu-login':
                check_ajax_referer('edit-app-menu','nonce');
                $chosenMenu=get_option('church_admin_app_new_menu');
                //church_admin_debug(print_r($chosenMenu,TRUE));
                $menuItem=stripslashes($_POST['menuItem']);
                $status=$_POST['status'];
                if($status=="ON")
                {
                    $chosenMenu[$menuItem]['loggedinOnly']=1;
                }else  $chosenMenu[$menuItem]['loggedinOnly']=0;
                update_option('church_admin_app_new_menu',$chosenMenu);
                //church_admin_debug(print_r($chosenMenu,TRUE));
                echo'DONE';
                exit();
            break;     
            case 'add-ticket':
                require_once(plugin_dir_path( __FILE__) .'/includes/events.php');
                $x=$_REQUEST['id'];
                
                echo church_admin_event_ticket_form(NULL,$x,FALSE);
                
                exit();
            break;
            case 'event-ticket':
                require_once(plugin_dir_path( __FILE__) .'/display/events.php');
                $x=$_REQUEST['id'];
               
                $ticket=church_admin_front_end_ticket($_REQUEST['event_id'],$x,TRUE);
                echo $ticket['output'];
                exit();
            break;
            case 'event-booking':
                require_once(plugin_dir_path( __FILE__) .'/display/events.php');
                
                $booking_ref =  church_admin_save_event_booking();
                church_admin_debug($booking_ref);
                //get cost
                $cost=$wpdb->get_var('SELECT SUM(a.ticket_price) FROM '.CA_TIK_TBL.' a, '.CA_BOO_TBL.' b WHERE a.ticket_id=b.ticket_type AND b.booking_ref="'.esc_sql($booking_ref).'"');
                church_admin_debug($wpdb->last_query);
                church_admin_debug($cost);
                header('Access-Control-Max-Age: 1728000');
				header('Access-Control-Allow-Origin: *');
				header('Access-Control-Allow-Methods: *');
				header('Access-Control-Allow-Headers: Content-MD5, X-Alt-Referer');
				header('Access-Control-Allow-Credentials: true');
                $output=json_encode(array('booking_ref'=>$booking_ref,'cost'=>$cost));
                church_admin_debug($output);
                echo $output;
                exit();
                exit();
            break;
                
            case 'assign_funnel':
				check_admin_referer('assign_funnel','nonce');
				$check=$wpdb->get_var('SELECT id FROM '.CA_FP_TBL.' WHERE funnel_id="'.intval($_REQUEST['funnel_id']).'" AND people_id="'.intval($_REQUEST['people_id']).'" AND member_type_id="'.intval($_REQUEST['member_type_id']).'" AND assign_id="'.intval($_REQUEST['assign_id']).'" AND assigned_date="'.date("Y-m-d").'"');
				if(!$check)
				{
					$sql='INSERT INTO '.CA_FP_TBL .'(funnel_id,people_id,member_type_id,assign_id,assigned_date,completion_date)VALUES("'.intval($_REQUEST['funnel_id']).'","'.intval($_REQUEST['people_id']).'","'.intval($_REQUEST['member_type_id']).'","'.intval($_REQUEST['assign_id']).'","'.date("Y-m-d").'","0000-00-00")';
					if(defined('CA_DEBUG'))church_admin_debug($sql);
        			$wpdb->query($sql);
				}
				//find name of funnel
				$funnel=$wpdb->get_var('SELECT action FROM '.CA_FUN_TBL.' WHERE funnel_id="'.intval($_REQUEST['funnel_id']).'"');
				if(defined('CA_DEBUG'))church_admin_debug($wpdb->last_query);
				$response=array('people_id'=>intval($_REQUEST['people_id']));
				if(!empty($funnel)){$response['message']= sprintf(__('Assigned to %1$s','church-admin'),$funnel);}else{$response['message']=__('Oopsie','church-admin');}
				header('Access-Control-Max-Age: 1728000');
				header('Access-Control-Allow-Origin: *');
				header('Access-Control-Allow-Methods: *');
				header('Access-Control-Allow-Headers: Content-MD5, X-Alt-Referer');
				header('Access-Control-Allow-Credentials: true');
				echo json_encode($response);
				exit();
				
			break;
			case 'app_menu_order':
			
				if(defined('CA_DEBUG'))church_admin_debug("Posted".print_r($_REQUEST,TRUE));
				if(!empty($_POST['order']))
				{
					$order=explode(",",$_POST['order']);
					$appMenu=get_option('church_admin_app_new_menu');
					foreach($order AS $key=>$name)
					{
						if(defined('CA_DEBUG'))church_admin_debug("Handling $name and giving it order of $key");
						if(!empty($appMenu[$name]))$appMenu[$name]['order']=intval($key);
					}
					if(defined('CA_DEBUG'))church_admin_debug(print_r($appMenu,TRUE));
					update_option('church_admin_app_new_menu',$appMenu);
				}
			break;
			case 'update-oversight':
				check_admin_referer('update-oversight','nonce');
				if(defined('CA_DEBUG'))church_admin_debug(print_r($_POST,TRUE));
				if(!empty($_POST['name'])){$wpdb->query('UPDATE '.CA_CEL_TBL.' SET name="'.esc_sql(stripslashes($_POST['name'])).'" WHERE ID="'.intval($_POST['cell_id']).'"');}
				if(!empty($_POST['people']))
				{
					$wpdb->query('DELETE FROM '.CA_MET_TBL.' WHERE meta_type="oversight" AND ID="'.intval($_POST['cell_id']).'"');
					$autocompleted=explode(',',$_POST['people']);//string with entered names

				foreach($autocompleted AS $x=>$name)
				{
					$p_id=church_admin_get_one_id(trim($name));//get the people_id

					if(!empty($p_id))
					{
						church_admin_update_people_meta(intval($_POST['cell_id']),$p_id,'oversight');//update person as leader at that level
					}
				}
			}
				
			break;
			case 'ministry-parents':
				if(defined('CA_DEBUG'))church_admin_debug(print_r($_POST,TRUE));
				$order=explode(",",$_POST['order']);
				if(defined('CA_DEBUG'))church_admin_debug(print_r($order,TRUE));
				$wpdb->query('UPDATE '.CA_MIN_TBL.' SET parentID=NULL');
				for($x=0;$x<count($order)-1;$x++)
				{
					
					$sql='UPDATE '.CA_MIN_TBL.' SET parentID="'.intval(substr($order[$x],3)).'" WHERE ID="'.intval(substr($order[$x+1],3)).'"';
					if(defined('CA_DEBUG'))church_admin_debug($sql);
					$wpdb->query($sql);
				}
				//$sql='UPDATE '.CA_MIN_TBL.' SET parentID=NULL WHERE ID="'.intval(substr($order[$x],3)).'" ';
				if(defined('CA_DEBUG'))church_admin_debug($sql);
				$wpdb->query($sql);
			break;
			case 'remove-image':
				check_ajax_referer( 'remove-image', 'nonce' );

				switch($_POST['type'])
				{
					case'people':$wpdb->query('UPDATE '.CA_PEO_TBL.' SET attachment_id=NULL WHERE people_id="'.intval($_POST['id']).'"');break;
					case'household':$wpdb->query('UPDATE '.CA_HOU_TBL.' SET attachment_id=NULL WHERE household_id="'.intval($_POST['id']).'"');break;
				}
				echo TRUE;
				exit();
			break;
			case 'show-person':
				check_ajax_referer( 'show-person', 'security' );
				require_once(plugin_dir_path( __FILE__) .'/display/address-list.php');
				$data= church_admin_people_data(intval($_POST['id']));
				if(defined('CA_DEBUG'))church_admin_debug(print_r($data,TRUE));
				echo church_admin_formatted_household($data,$_POST['map'],$_POST['updateable'],$_POST['photo']);
				exit();
			break;
            case 'show-personv2':
				check_ajax_referer( 'show-person', 'security' );
				require_once(plugin_dir_path( __FILE__) .'/display/address-list.php');
				$data= church_admin_people_data(intval($_POST['id']));
				if(defined('CA_DEBUG'))church_admin_debug(print_r($data,TRUE));
				header('Access-Control-Max-Age: 1728000');
		          header('Access-Control-Allow-Origin: *');
		          header('Access-Control-Allow-Methods: *');
		          header('Access-Control-Allow-Headers: Content-MD5, X-Alt-Referer');
		          header('Access-Control-Allow-Credentials: true');
                    $outputData=church_admin_formatted_household($data,$_POST['map'],$_POST['updateable'],$_POST['photo']);
                echo json_encode(array('householdIndex'=>$data['household_index'],'id'=>$data['household_id'],'entry'=>$outputData));
				exit();
			break;
			//podcast
			case "podcast-file"://checked
				require_once(plugin_dir_path( __FILE__) .'/display/sermon-podcast.php');
				if(defined('CA_DEBUG'))church_admin_debug('podcast file');
				echo church_admin_podcast_file(intval($_POST['id']),FALSE);
				exit();
			break;
			case 'series-detail'://checked
				require_once(plugin_dir_path( __FILE__) .'/display/sermon-podcast.php');

				echo church_admin_podcast_series_detail(intval($_REQUEST['id']),NULL);
				exit();
			break;
			case 'latest-series-sermon':
			require_once(plugin_dir_path( __FILE__) .'/display/sermon-podcast.php');

				echo church_admin_podcast_latest_sermon(intval($_REQUEST['id']));
				exit();
			break;
			case 'more-sermons'://checked
				require_once(plugin_dir_path( __FILE__) .'/display/sermon-podcast.php');
				echo church_admin_podcast_more_files($_REQUEST['page']);
				exit();
			break;

			case 'unattach_user'://checked
				check_ajax_referer( 'church_admin_unattach_user', 'nonce' );
				church_admin_unattach_user();
			break;
			case 'autocomplete'://checked
				check_ajax_referer( 'church-admin-autocomplete', 'security' );
				church_admin_ajax_people(TRUE);
			break;
			case 'mp3_plays'://checked
				if(defined('CA_DEBUG'))church_admin_debug('Logging a play');
				
				church_admin_mp3_plays();
			break;
			case 'username_check'://checked
				church_admin_username_check();
			break;

			case 'filter'://checked
				require_once(plugin_dir_path( __FILE__) .'/includes/filter.php');

				church_admin_filter_callback();
			break;
			case 'filter_email'://checked
				require_once(plugin_dir_path( __FILE__) .'/includes/filter.php');
                //following function is in functions.php
				church_admin_filter_email_callback();
			break;
			case 'people_activate'://checked
				church_admin_people_activate_callback();
			break;
			case'note_delete':
				church_admin_note_delete_callback();
			break;
			case 'calendar_date_display':
				church_admin_date();
			break;

			case'connect_user':
				check_ajax_referer('connect_user','nonce',TRUE);
				if(church_admin_level_check('Directory'))
				{
				if(defined('CA_DEBUG'))church_admin_debug(print_r($_POST,TRUE));
				if(!empty($_POST['user_id'])&&ctype_digit($_POST['user_id']))$ID=church_admin_user_id_exists($_POST['user_id']);
				if(!empty($_POST['people_id'])&&ctype_digit($_POST['people_id'])&& !empty($ID))
				{
					$sql='UPDATE '.CA_PEO_TBL.' SET user_id="'.intval($_POST['user_id']).'" WHERE people_id="'.intval($_POST['people_id']).'"';
					if(defined('CA_DEBUG'))church_admin_debug($sql);
					$wpdb->query($sql);
					$user=get_userdata($_POST['user_id']);
					$response= json_encode(array('login'=>$user->user_login,'people_id'=>intval($_POST['people_id'])));
					if(defined('CA_DEBUG'))church_admin_debug($response);
					echo $response;
				}
				}
				exit();
			break;
			case'create_user':
				check_ajax_referer('create_user','nonce',TRUE);
				if(church_admin_level_check('Directory'))
				{
				if(defined('CA_DEBUG'))church_admin_debug(print_r($_POST,TRUE));

				if(!empty($_POST['people_id'])&&ctype_digit($_POST['people_id']))
				{
					$person=$wpdb->get_row('SELECT * FROM '.CA_PEO_TBL.' WHERE people_id="'.intval($_POST['people_id']).'"');
					if(empty($person->email))exit('No email address');
					$username=trim(strtolower($wpdb->get_var('SELECT CONCAT(first_name,last_name) FROM '.CA_PEO_TBL.' WHERE people_id="'.intval($_POST['people_id']).'"')));
					if(empty($username))exit('No names to form username');
					$x='';
					while(username_exists( $username.$x )){$x+=1;}
					$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
					$user_id = wp_create_user( $username.$x, $random_password, $person->email );
					$user_id = wp_create_user( $username.$x, $random_password, $user->email );
					$message=get_option('church_admin_user_created_email');

					if(empty($message))
					{
						$message='<p>'.__('The web team at','church-admin'). '<a href="[SITE_URL]">[SITE_URL]</a> '.__('have just created a user login for you.','church-admin').'</p><p>'.__('Your username is','church-admin').' <strong>[USERNAME]</strong></p><p>'.__('Your password is','church-admin').' <strong>[PASSWORD]</strong></p><p>'.__('We also have an app you can download for [ANDROID] and [IOS]','church-admin').' </p>';
						update_option('church_admin_user_created_email',$message);
					}
					$message=str_replace('[SITE_URL]',site_url(),$message);
					$message=str_replace('[USERNAME]',esc_html($username.$x),$message);
					$message=str_replace('[PASSWORD]',$random_password,$message);
					$page_id=church_admin_register_page_id();
					if(!empty($page_id))$message=str_replace('[EDIT_PAGE]',get_permalink($page_id),$message);
					$message=str_replace('[ANDROID]','<a href="http://www.tinyurl.com/androidChurchApp">Android</a>',$message);
					$message=str_replace('[IOS]','<a href="http://www.tinyurl.com/iOSChurchApp">iOS</a>',$message);
					$app=get_option('church_admin_app_id');
					if(!empty($app))$message.='<p>We also have an app you can download for <a href="http://www.tinyurl.com/androidChurchApp">Android</a> and <a href="http://www.tinyurl.com/iOSChurchApp">iOS</a>. You can use your username and password for the directory on it!</p>';
					$headers=array();
					$headers[] = 'From: Web team at '.site_url() .'<'.get_option('admin_email').'>';
					$headers[] = 'Cc: Web team at '.site_url() .'<'.get_option('admin_email').'>';
					add_filter('wp_mail_content_type','church_admin_email_type');
					$subject=get_option('church_admin_user_created_email_subject');

					if(empty($subject))$subject='Login for '.site_url();
					if(wp_mail($person->email,$subject,$message,$headers))
					{
		    			$out='('.__('Email sent','church-admin').')';
					}

					remove_filter('wp_mail_content_type','church_admin_email_type');
					
					$wpdb->query('UPDATE '.CA_PEO_TBL.' SET user_id="'.esc_sql($user_id).'" WHERE people_id="'.esc_sql($people_id).'"');
					$response= json_encode(array('login'=>$username.$x.' '.$out,'people_id'=>intval($_POST['people_id'])));
					if(defined('CA_DEBUG'))church_admin_debug($response);
					echo $response;

					}
				}
				exit();
			break;
			case 'individual_attendance':
					if(defined('CA_DEBUG'))church_admin_debug('Individual attendance');
					check_ajax_referer('individual_attendance','nonce',TRUE);

					$sql='SELECT * FROM '.CA_IND_TBL.' WHERE meeting_type="'.esc_sql($_GET['meeting_type']).'" AND meeting_id="'.intval($_GET['meeting_id']).'" AND `date`="'.esc_sql($_GET['date']).'"';
					if(defined('CA_DEBUG'))church_admin_debug($sql);
					$results=$wpdb->get_results($sql);
					if(defined('CA_DEBUG'))church_admin_debug(print_r($results,TRUE));
					$out=array();
					if(!empty($results))
					{
						foreach($results AS $row)
						{
							$out[]='person-'.$row->people_id;

						}
						if(defined('CA_DEBUG'))church_admin_debug(print_r($out,TRUE));
						echo json_encode($out);
					}
					exit();
			break;
			case 'image_upload':
			check_ajax_referer('church_admin_image_upload','nonce',TRUE);
				// These files need to be included as dependencies when on the front end.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				require_once( ABSPATH . 'wp-admin/includes/media.php' );
				// Let WordPress handle the upload.
				// Remember, 'my_image_upload' is the name of our file input in our form above.
				$attachment_id = media_handle_upload( 'file-0', 0 );
				if(defined('CA_DEBUG'))church_admin_debug($attachment_id);
				if ( is_wp_error( $attachment_id ) ) {
						// There was an error uploading the image.
				} else {
				// The image was uploaded successfully!
				$image=wp_get_attachment_image_src(  $attachment_id, "medium", false );
				if(defined('CA_DEBUG'))church_admin_debug(print_r($image,TRUE));
				echo json_encode(array('src'=>$image[0],'attachment_id'=>$attachment_id));
				exit();
			}
			break;
			case 'remove-app-logo':
				check_ajax_referer('remove-app-logo','nonce',TRUE);
				delete_option('church_admin_app_logo');
				echo TRUE;
				exit();
			break;
			case 'update-app-logo':
				check_ajax_referer('update-app-logo','nonce',TRUE);
				update_option('church_admin_app_logo',stripslashes($_POST['logo']));
				echo TRUE;
				exit();
			break;
			case 'category_list':
				//filter count
				require_once(plugin_dir_path( __FILE__) .'/includes/filter.php');
				echo church_admin_filter_count(null);
				exit();
			break;
			case 'edit_rota':
				church_admin_debug("Edit rota Ajax\r\n".print_r($_POST,TRUE));
				check_ajax_referer('edit_rota','nonce',TRUE);
				$rota_task_id=$_POST['rota_task_id'];
				$rota_date=$_POST['rota_date'];
				$content=stripslashes($_POST['content']);
				$id=$_POST['id'];
				$service_time=$_POST['time'];
				$service_id=intval($_POST['service_id']);
				//delete current entry
				$wpdb->query('DELETE FROM '.CA_ROTA_TBL.' WHERE rota_task_id="'.intval($rota_task_id).'" AND rota_date="'.esc_sql($rota_date).'" AND service_time="'.esc_sql($service_time).'" AND service_id="'.intval($service_id).'" AND mtg_type="service"');
				church_admin_debug($wpdb->last_query);
				$people=unserialize(church_admin_get_people_id($content));
				$peopleIDs=array_unique($people);//prevent duplication
				foreach($people AS $key=>$people_id)
				{
					
					$wpdb->query('INSERT INTO '.CA_ROTA_TBL.' (rota_date,rota_task_id,people_id,service_id,mtg_type,service_time)VALUES("'.esc_sql($rota_date).'","'.intval($rota_task_id).'","'.esc_sql($people_id).'","'.intval($service_id).'","service","'.esc_sql($service_time).'" )');
					church_admin_debug($wpdb->last_query);
				}
				$newContent='<span data-service_id="'.intval($service_id).'" data-time="'.esc_html($service_time).'" data-id="'.intval($id).'" data-date="'.$rota_date.'" data-rota_task_id="'.intval($rota_task_id).'" class="rota_edit">'.esc_html($content).'</span>'; 
				church_admin_debug($newContent);
				echo $newContent;
				exit();
			break;
		}



}

add_action('init','church_admin_receive_prayer');

function church_admin_receive_prayer()
{
	//handle front end prayer request which needs to happen later than plugins_loaded action
	global $church_admin_prayer_request_success;
	if(!empty($_POST['save_prayer_request'])&&!empty($_POST['non_spammer'])&&wp_verify_nonce($_POST['non_spammer'],'prayer-request'))
	{

		$args=array(
								'post_content'=>sanitize_textarea_field($_POST['request_content']),
								'post_title'=>wp_strip_all_tags($_POST['request_title']),
								'post_status'=>'draft',
								'post_type'=>'prayer-requests'
							);
		if(church_admin_level_check('Prayer Requests')){$args['post_status']='publish';}


		$postid = wp_insert_post($args);

		if($postid)
		{

				//the post is valid
				$church_admin_prayer_request_success='<div class="notice notice-success">';
				if($args['post_status']=='publish'){$church_admin_prayer_request_success.=__('Your prayer-request has been published','church-admin');}
				else
				{
					$church_admin_prayer_request_success.=__('Your prayer-request has been put in the moderation queue','church-admin');
					$message='<p>'.__('New prayer request draft for moderation','church-admin').'</p>';
					wp_mail(get_option('admin_email'),__('New prayer request draft for moderation','church-admin'),$message);

				}
				$church_admin_prayer_request_success.='</div>';
		}
	}
}


//submit prayer request widget
// Register and load the widget
function church_admin_load_prayer_widget() {
    register_widget( 'ca_prayer_widget' );
}
add_action( 'widgets_init', 'church_admin_load_prayer_widget' );

// Creating the widget
class ca_prayer_widget extends WP_Widget {

function __construct() {
parent::__construct(

// Base ID of your widget
'ca_prayer_widget',

// Widget name will appear in UI
__('Submit Prayer Request Widget', 'church-admin'),

// Widget description
array( 'description' => __( 'Prayer Request widget', 'church-admin' ), )
);
}

// Creating widget front-end

public function widget( $args, $instance ) {
	if(empty($ins))
$title =__('Submit prayer Request','church-admin');

// before and after widget arguments are defined by themes
echo $args['before_widget'];
if ( ! empty( $title ) )
echo $args['before_title'] . $title . $args['after_title'];

// This is where you run the code and display the output

if(!empty($_POST['non_spammer']))
{
	echo'<p>'.__('Prayer request saved for moderation','church-admin').'</p>';
}
else {
	$message=get_option('church_admin_prayer_request_message');
	if(!empty($message))echo'<p>'. esc_html($message).'</p>';
	echo'<form action="" method="POST">';
	echo'<table class="form-table"><tbody>';
	echo'<tr><th scope="row">'.__('Title','church-admin').'</th><td><input type="text" name="request_title"></td></tr>';
	echo'<tr><th scope="row">'.__('Prayer request','church-admin').'</th><td><textarea name="request_content"></textarea></td></tr>';
	echo'<tr class="widget-spam-proof">&nbsp;</td></tr>';
	echo'<tr><td cellspacing=2><input type="hidden" value="TRUE" name="save_prayer_request"/><input type="submit" value="'.__('Save','church-admin').'"/></td></tr></table>';

	echo'</form>';
	$nonce=wp_create_nonce('prayer-request');
	echo'<script>jQuery(document).ready(function($) {var content="<th scope=\"row\">'.__('Check box if not a spammer','church-admin').'</th><td><input type=\"checkbox\" name=\"non_spammer\" value=\"'.$nonce.'\"/></td></tr>";$(".widget-spam-proof").html(content);});</script>';
}
echo $args['after_widget'];
}

} // Class wpb_widget ends here


/******************************************************************************************************
*
* Use prayer request recent posts in recent posts widget when on prayer request/bible readings Archive
*
*****************************************************************************************************/

add_filter( 'widget_posts_args', 'church_admin_recent_posts_args');
add_filter('widget_comments_args', 'church_admin_recent_posts_args');
/**
 * Add CPTs to recent posts widget
 *
 * @param array $args default widget args.
 * @return array $args filtered args.
 */
function church_admin_recent_posts_args($args) {
   if(is_post_type_archive('prayer-requests')) $args['post_type'] = array('prayer-requests');
	 elseif(is_post_type_archive('bile-readings')) $args['post_type'] = array('bible-readings');
	 else {
	 $args['post_type'] = array('post');
	 }
    return $args;
}


add_action('init','church_admin_acts_courage');

function church_admin_acts_courage()
{
	//handle front end prayer request which needs to happen later than plugins_loaded action
	global $church_admin_acts_success;
	if(!empty($_POST['save_act_of_courage_request'])&&!empty($_POST['non_spammer'])&&wp_verify_nonce($_POST['non_spammer'],'acts-of-courage'))
	{

		$args=array(
								'post_content'=>sanitize_textarea_field($_POST['request_content']),
								'post_title'=>wp_strip_all_tags($_POST['request_title']),
								'post_status'=>'draft',
								'post_type'=>'acts-of-courage'
							);
		if(church_admin_level_check('Prayer Requests')){$args['post_status']='publish';}


		$postid = wp_insert_post($args);

		if($postid)
		{

				//the post is valid
				$church_admin_acts_success='<div class="notice notice-success">';
				if($args['post_status']=='publish'){$church_admin_acts_success.=__('Your act of courage has been published','church-admin');}
				else
				{
					$church_admin_acts_success.=__('Your act of courage has been put in the moderation queue','church-admin');
					$message='<p>'.__('New act of courage draft for moderation','church-admin').'</p>';
					wp_mail(get_option('admin_email'),__('New act of courage draft for moderation','church-admin'),$message);

				}
				$church_admin_acts_success.='</div>';
		}
	}
}

/**
 * Redirect user after successful login.
 *
 * @param string $redirect_to URL to redirect to.
 * @param string $request URL the user is coming from.
 * @param object $user Logged user's data.
 * @return string
 */

function church_admin_login_redirect( $redirect_to, $request, $user ) {
   	$check=get_option('church_admin_login_redirect');
   	if($check && isset($user->roles) && is_array($user->roles)) {
        //check for subscribers
        if (in_array('subscriber', $user->roles)) {
            // redirect them to another URL, in this case, the homepage 
            $redirect_to =  $check;
        }
    }

    return $redirect_to;
}

add_filter( 'login_redirect', 'church_admin_login_redirect', 10, 3 );


/**************************************************************************
*
*   Paypal IPN
*
*
***************************************************************************/
add_action( 'wp_ajax_church_admin_paypal_ipn', 'church_admin_paypal_ipn_callback' );
add_action( 'wp_ajax_nopriv_church_admin_paypal_ipn', 'church_admin_paypal_ipn_callback' );
function church_admin_paypal_ipn_callback() {
    global $wpdb;
    if(defined('CA_DEBUG'))church_admin_debug("Paypal IPN Call");
	
    // here we can verify and validate the transactions.
    
    // STEP 1: read POST data
    // Reading POSTed data directly from $_POST causes serialization issues with array data in the POST.
    // Instead, read raw POST data from the input stream.
    $raw_post_data = file_get_contents('php://input');
    $raw_post_array = explode('&', $raw_post_data);
    $myPost = array();
    foreach ($raw_post_array as $keyval) 
    {
        $keyval = explode ('=', $keyval);
        if (count($keyval) == 2)$myPost[$keyval[0]] = urldecode($keyval[1]);
    }
    // read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
    $req = 'cmd=_notify-validate';
    if (function_exists('get_magic_quotes_gpc')) {
        $get_magic_quotes_exists = true;
    }
    foreach ($myPost as $key => $value) 
    {
        if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) 
        {
            $value = urlencode(stripslashes($value));
        } 
        else 
        {
            $value = urlencode($value);
        }
        $req .= "&$key=$value";
    }
   
    // Step 2: POST IPN data back to PayPal to validate
    $ch = curl_init(CA_PAYPAL);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));


    if ( !($res = curl_exec($ch)) ) 
    {
        church_admin_debug("Got " . curl_error($ch) . " when processing IPN data");
        curl_close($ch);
        exit;
    }
    curl_close($ch);
    // inspect IPN validation result and act accordingly
    if (strcmp ($res, "VERIFIED") == 0) 
    {
        if(defined('CA_DEBUG'))church_admin_debug("Verified");
        if(defined('CA_DEBUG'))church_admin_debug(print_r($_POST,TRUE));
        // The IPN is verified, process it
        if(!empty($_POST['mc_gross1']))
        {
            $payment_amount = esc_sql($_POST['mc_gross1']);
        }
        else
        {
            $payment_amount = esc_sql($_POST['mc_gross']);
        }
        
        $txn_id = esc_sql($_POST['txn_id']);
        $payer_email = esc_sql($_POST['payer_email']);
        $booking_ref= esc_sql($_POST['custom']);
        $event_id=$wpdb->get_var('SELECT event_id FROM '.CA_BOO_TBL.' WHERE booking_ref="'.esc_sql($booking_ref).'"');
        $sql='SELECT payment_id FROM '.CA_PAY_TBL.' WHERE amount="'.$payment_amount.'" AND txn_id="'.$txn_id.'" AND payer_email="'.$payer_email.'" AND event_id="'.intval($event_id).'" AND booking_ref="'.$booking_ref.'"';
        if(defined('CA_DEBUG'))church_admin_debug($sql);
        $check=$wpdb->get_var($sql);
        
        if(empty($check))
        {
            $wpdb->query('INSERT INTO '.CA_PAY_TBL.' (amount,txn_id,payer_email,booking_ref,payment_date,event_id) VALUES ("'.$payment_amount.'","'.$txn_id.'","'.$payer_email.'","'.$booking_ref.'","'.date('Y-m-d H:i:s').'","'.intval($event_id).'")');
            if(defined('CA_DEBUG'))church_admin_debug($wpdb->last_query);
        }
    } else if (strcmp ($res, "INVALID") == 0) 
    {
        // IPN invalid, log for manual investigation
        if(defined('CA_DEBUG'))church_admin_debug("Not Verified");
    }
}


/*************************************************************
*
* Database restore
*
**************************************************************/
function church_admin_restore_backup()
{
	if(!current_user_can('manage_options')) exit();
	
	global $wpdb;
	$wpdb->show_errors();
	if(!empty($_POST['save']))
	{
		if(!empty($_FILES) && $_FILES['file']['error'] == 0)
		{
			$filename = $_FILES['file']['name'];
			$upload_dir = wp_upload_dir();
			$filedest = $upload_dir['path'] . '/' . $filename;
			if(move_uploaded_file($_FILES['file']['tmp_name'], $filedest))echo '<p>'.__('File Uploaded and saved','church-admin').'</p>';
			$fp = gzopen($filedest, "r");
			// Temporary variable, used to store current query
			$templine = '';
			
			
			$handle = gzopen($filedest, 'r');
			//while we haven't reached the end of the file
			while (!gzeof($handle)) 
			{
    			//read one line
    			$line = gzgets($handle, 4096);
			
				// Skip it if it's a comment
				if (substr($line, 0, 2) == '--' || $line == '')
    			continue;

				// Add this line to the current segment
				$templine .= $line;
				// If it has a semicolon at the end, it's the end of the query
				if (substr(trim($line), -1, 1) == ';')
				{
    				// Perform the query
    				$wpdb->query($templine);
    				// Reset temp variable to empty
    				
    				echo'<em>'.esc_html($templine).'</em><strong>processed</strong><br/>';
    				$templine = '';
				}
			}
 		echo "Tables imported successfully";
 		unlink($filedest);
		}
	}
	else
	{
		echo'<h2>Church Admin DB Backup Importer</h2>';
		
		echo'<form action="" method="POST" enctype="multipart/form-data">';
		echo'<p><label>'.__('Import database backup','church-admin').'</label><input type="file" name="file"/><input type="hidden" name="save" value="yes"/></p>';
		echo'<p><input  class="button-primary" type="submit" Value="'.__('Upload','church-admin').'"/></p></form>';	
	}


}




add_action( 'save_post', 'church_admin_sermon_check', 10,3 );
function church_admin_sermon_check($post_id, $post, $update)
{
    if(strpos( $post->post_content,'[church_admin') !== false && strpos( $post->post_content,'podcast') !== false)
    {
        
        update_option('church_admin_sermon_page',$post_id);
      
    }
}
function church_admin_find_sermon_page()
{
    global $wpdb;
    $result=$wpdb->get_var('SELECT ID FROM '.$wpdb->posts.' WHERE post_content LIKE "%[church_admin%" AND post_content LIKE"%podcast%" AND post_status="publish" LIMIT 1');
    if($result) 
    {
        $link=get_permalink($result);
      
        return $link;
    }
}

function church_admin_cpanel_fix()
{
    church_admin_debug("Cpanel fix");
    //look in CA_PATH
    function church_admin_remover($path)
    {
        if (is_dir($path)) 
        {
            if ($dh = opendir($path)) 
            {
                while (($file = readdir($dh)) !== false) 
                {
                    if($file==".ea-php-cli.cache")
                    {
                        unlink($path.$file);
                        church_admin_debug(" .ea-php-cli.cache deleted from ".$path);
                    }
                }
                    closedir($dh);
            }
        }
    }
    church_admin_remover(CA_PATH);
    church_admin_remover(CA_PATH.'/display/');
    church_admin_remover(CA_PATH.'/includes/');
    church_admin_remover(CA_PATH.'/app/');
    church_admin_remover(CA_PATH.'/gutenberg/');
    church_admin_remover(CA_PATH.'/css/');
    
    
}

/*************************************************************
*
* Add column for custom button to the app-content post type
*
*************************************************************/
// Add the custom columns to the book post type:
add_filter( 'manage_app-content_posts_columns', 'church_admin_custom_app_content_columns' );
function church_admin_custom_app_content_columns($columns) {
    unset($columns['date']);
   $columns['mybutton'] = __( 'App button code', 'church-admin' );
     $columns['date'] =__('Date','church-admin');
    return $columns;
}

// Add the data to the custom columns for the book post type:
add_action( 'manage_app-content_posts_custom_column' , 'church_admin_custom_app_content_column', 10, 2 );
function church_admin_custom_app_content_column( $column, $post_id ) {
    
    switch ( $column ) {
      
        case 'mybutton' :
            echo esc_html('<button id="myButton" class="button red" data-page="'.sanitize_title(get_the_title($post_id)).'">'.get_the_title($post_id).'</button>');
        break;
    }
}

/*************************************************************
*
* Add don't send push notification for this post meta box
*
*************************************************************/

function church_admin_add_custom_box()
{
    $screens = ['post'];
    foreach ($screens as $screen) {
        add_meta_box(
            'church_admin_no_push',           // Unique ID
            'Church Admin Post Settings',  // Box title
            'church_admin_custom_box_html',  // Content callback, must be of type callable
            $screen                   // Post type
        );
    }
}
add_action('add_meta_boxes', 'church_admin_add_custom_box');

function church_admin_custom_box_html($post)
{
    ?>
    <label for="church_admin_field">Don't send a push notification when this post is published</label>
    <input type="checkbox" name="church_admin_no_push" id="church_admin_field" value=1/>
    
    <?php
}

$church_admin_menu=array(
    'people'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('People','church-admin'),
                'link'=>$church_admin_url.'&amp;section=people',
                "dashicon"=>'dashicons-id'
            ),
    'view-address-list'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('View address list','church-admin'),
                'link'=>$church_admin_url.'&action=view-address-list&amp;section=people'
            ),
    'add-household'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Add household','church-admin'),
                'link'=>$church_admin_url.'&action=add-household&amp;section=people'
            ),
    'import-csv'=>array(
                 'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Import CSV','church-admin'),
                'link'=>$church_admin_url.'&action=import-csv&amp;section=people'
            ),
    'directory-pdf'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Directory PDF','church-admin'),
                'link'=>$church_admin_url.'&action=export-pdf&amp;section=people'
            ),
    'member-types'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Member types','church-admin'),
                'link'=>$church_admin_url.'&action=member-types&amp;section=people'
            ),
    'add-member-types'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Add member types','church-admin'),
                'link'=>$church_admin_url.'&action=add-member-type&amp;section=people'
            ),
    'custom-fields'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Custom fields','church-admin'),
                'link'=>$church_admin_url.'&action=member-types&amp;section=people'
            ),
    'create-users'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Create users ','church-admin').' &raquo;',
                'link'=>$church_admin_url.'&action=create-users&amp;section=people'
            ),
    'bulk-geocode'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Bulk geocode ','church-admin'),
                'link'=>$church_admin_url.'&action=bulk-geocode&amp;section=people'
            ),
    'download-csv'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Download CSV/labels ','church-admin').' &raquo;',
                'link'=>$church_admin_url.'&action=download-csv&amp;section=people'
            ),
    'recent-activity'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Recent people activity ','church-admin'),
                'link'=>$church_admin_url.'&action=recent-people&amp;section=people'
            ),
    'check-duplicates'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Check duplicates','church-admin'),
                'link'=>$church_admin_url.'&action=check-duplicates&amp;section=people'
            ),
    'birthdays'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Birthdays','church-admin'),
                'link'=>$church_admin_url.'&action=birthdays&amp;section=people'
            ),
    'birthdays'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Birthdays','church-admin'),
                'link'=>$church_admin_url.'&action=birthdays&amp;section=people'
            ),
    'everyone-visible'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'title'=>__('Everyone visible','church-admin'),
                'link'=>$church_admin_url.'&action=everyone-visible&amp;section=people'
            ),
    'delete-all'=>array(
                'module'=>'Directory',
                'parent'=>'people',
                'level'=>'Directory',
                'section'=>'people',
                'confirm'=>TRUE,
                'title'=>__('Delete all','church-admin'),
                'link'=>$church_admin_url.'&action=delete-all&amp;section=people'
            ),
    'childrens-work'=>array(
                'module'=>'Children',
                'parent'=>'kidswork',
                'level'=>'Directory',
                'section'=>'childrens-work',
                'title'=>__('Childrens work','church-admin'),
                'link'=>$church_admin_url.'&action=kidswork&amp;section=childrens-work',
                "dashicon"=>'dashicons-admin-users'
            ),
    'kidswork'=>array(
                'module'=>'Children',
                'parent'=>'kidswork',
                'level'=>'Directory',
                'section'=>'childrens-work',
                'title'=>__('Childrens groups','church-admin'),
                'link'=>$church_admin_url.'&action=kidswork&amp;section=childrens-work',
                "dashicon"=>'dashicons-admin-users'
            ),
    'kidswork-pdf'=>array(
                'module'=>'Children',
                'parent'=>'kidswork',
                'level'=>'Directory',
                'section'=>'childrens-work',
                'title'=>__('Childrens groups PDF','church-admin'),
                'link'=>$church_admin_url.'&action=kidswork-pdf&amp;section=childrens-work'
            ),
    'kidswork-checkin-pdf'=>array(
                'module'=>'Children',
                'parent'=>'kidswork',
                'level'=>'Directory',
                'section'=>'childrens-work',
                'title'=>__('Childrens checkin PDF','church-admin'),
                'link'=>$church_admin_url.'&action=kidswork-checkin-pdf&amp;section=childrens-work'
            ), 
    'safeguarding'=>array(
                'module'=>'Children',
                'parent'=>'kidswork',
                'level'=>'Directory',
                'section'=>'childrens-work',
                'title'=>__('Safeguarding','church-admin'),
                'link'=>$church_admin_url.'&action=safeguarding&amp;section=childrens-work'
            ),
    'classes'=>array(
                'module'=>'Classes',
                'parent'=>'classes',
                'level'=>'Directory',
                'section'=>'classes',
                'title'=>__('Classes','church-admin'),
                'link'=>$church_admin_url.'&action=classes&amp;section=classes',
                "dashicon"=>'dashicons-awards'
            ),
    'add-class'=>array(
                'module'=>'Classes',
                'parent'=>'classes',
                'level'=>'Directory',
                'section'=>'classes',
                'title'=>__('Add a class','church-admin'),
                'link'=>$church_admin_url.'&action=edit-class&amp;section=classes'
            ),
    'events'=>array(
                'module'=>'Events',
                'parent'=>'events',
                'level'=>'Events',
                'section'=>'events',
                'title'=>__('Events','church-admin'),
                'link'=>$church_admin_url.'&action=events&amp;section=events',
                "dashicon"=>'dashicons-tickets-alt'
            ),
    'add-event'=>array(
                'module'=>'Events',
                'parent'=>'events',
                'level'=>'Events',
                'section'=>'events',
                'title'=>__('Add an event','church-admin'),
                'link'=>$church_admin_url.'&action=add-event&amp;section=events'
            ),
    'attendance'=>array(
                'module'=>'Attendance',
                'parent'=>'attendance',
                'level'=>'Directory',
                'section'=>'attendance',
                'title'=>__('Attendance','church-admin'),
                'link'=>$church_admin_url.'&action=attendance&amp;section=attendance',
                "dashicon"=>'dashicons-editor-ol'
            ),
    'add-attendance'=>array(
                'module'=>'Attendance',
                'parent'=>'attendance',
                'level'=>'Directory',
                'section'=>'attendance',
                'title'=>__('Add attendance','church-admin'),
                'link'=>$church_admin_url.'&action=add-attendance&amp;section=attendance'
            ),
    'individual-attendance'=>array(
                'module'=>'Attendance',
                'parent'=>'attendance',
                'level'=>'Directory',
                'section'=>'attendance',
                'title'=>__('Individual attendance','church-admin'),
                'link'=>$church_admin_url.'&action=individual-attendance&amp;section=attendance'
            ),
    'individual-attendance-csv'=>array(
                'module'=>'Attendance',
                'parent'=>'attendance',
                'level'=>'Directory',
                'section'=>'attendance',
                'title'=>__('Individual attendance CSV','church-admin'),
                'link'=>$church_admin_url.'&action=individual-attendance-csv&amp;section=attendance'
            ),  
    'follow-up'=>array(
                'module'=>'Attendance',
                'parent'=>'follow-up',
                'level'=>'Directory',
                'section'=>'follow-up',
                'title'=>__('Individual attendance','church-admin'),
                'link'=>$church_admin_url.'&action=funnel&amp;section=follow-up',
                "dashicon"=>'dashicons-editor-ol'
            ),
    'add-funnel'=>array(
                'module'=>'Attendance',
                'parent'=>'follow-up',
                'level'=>'Directory',
                'section'=>'follow-up',
                'title'=>__('Add funnel','church-admin'),
                'link'=>$church_admin_url.'&action=add-funnel&amp;section=follow-up'
            ),
    'units'=>array(
                'module'=>'Units',
                'parent'=>'units',
                'level'=>'Directory',
                'section'=>'units',
                'title'=>__('Units','church-admin'),
                'link'=>$church_admin_url.'&action=units&amp;section=units',
                "dashicon"=>'dashicons-networking'
            ),
    'add-unit'=>array(
                'module'=>'Units',
                'parent'=>'units',
                'level'=>'Directory',
                'section'=>'units',
                'title'=>__('Add unit','church-admin'),
                'link'=>$church_admin_url.'&action=add-unit&amp;section=units'
            ),
    'groups'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Small groups','church-admin'),
                'link'=>$church_admin_url.'&action=show-groups&amp;section=groups',
                "dashicon"=>'dashicons-networking'
            ),
    'small-group-structure'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Small group structure','church-admin'),
                'link'=>$church_admin_url.'&action=small-group-structure&amp;section=groups'
            ),
    'oversight-list'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Oversight list','church-admin'),
                'link'=>$church_admin_url.'&action=oversight-liste&amp;section=groups'
            ),
    'add-group'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Add a group','church-admin'),
                'link'=>$church_admin_url.'&action=edit-groupe&amp;section=groups'
            ),
    'show-groups'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Show groups','church-admin'),
                'link'=>$church_admin_url.'&action=show-groups&amp;section=groups'
            ),
    'cleanup-groups'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Cleanup groups','church-admin'),
                'link'=>$church_admin_url.'&action=smallgroups-cleanup&amp;section=groups'
            ),
        'delete-groups'=>array(
                'module'=>'Groups',
                'parent'=>'groups',
                'confirm'=>TRUE,
                'level'=>'Groups',
                'section'=>'groups',
                'title'=>__('Delete all groups','church-admin'),
                'link'=>$church_admin_url.'&action=delete-all-groups&amp;section=groups'
            ),
        'services'=>array(
                'module'=>'Services',
                'parent'=>'services',
                'level'=>'Services',
                'section'=>'services',
                'title'=>__('Sites & Services','church-admin'),
                'link'=>$church_admin_url.'&action=services-list&amp;section=services',
                "dashicon"=>'dashicons-groups'
            ),
        'add-service'=>array(
                'module'=>'Services',
                'parent'=>'services',
                'level'=>'Services',
                'section'=>'services',
                'title'=>__('Add service','church-admin'),
                'link'=>$church_admin_url.'&action=edit-service&amp;section=services'
            ),
        'service-prebooking'=>array(
                'module'=>'Services',
                'parent'=>'services',
                'level'=>'Services',
                'section'=>'services',
                'title'=>__('Service pre-bookings','church-admin'),
                'link'=>$church_admin_url.'&action=service-prebooking&amp;section=services'
            ),
        'sites'=>array(
                'module'=>'Services',
                'parent'=>'services',
                'level'=>'Services',
                'section'=>'services',
                'title'=>__('Sites','church-admin'),
                'link'=>$church_admin_url.'&action=site-list&amp;section=services'
            ),
        'edit-sites'=>array(
                'module'=>'Services',
                'parent'=>'services',
                'level'=>'Services',
                'section'=>'services',
                'title'=>__('Add site','church-admin'),
                'link'=>$church_admin_url.'&action=site-list&amp;section=edit-site'
            ),
        'sessions'=>array(
                'module'=>'Sessions',
                'parent'=>'sessions',
                'level'=>'Sessions',
                'section'=>'sessions',
                'title'=>__('Sessions','church-admin'),
                'link'=>$church_admin_url.'&action=sessions&amp;section=sessions',
                "dashicon"=>'dashicons-groups'
            ),
    'comms'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Communications','church-admin'),
                'link'=>$church_admin_url.'&action=comms&amp;section=comms',
            "dashicon"=>'dashicons-megaphone'
            ),
    'push-message'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Push message','church-admin'),
                'link'=>$church_admin_url.'&action=push-message&amp;section=comms'
            ),
    'push-message'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Push message','church-admin'),
                'link'=>$church_admin_url.'&action=push-message&amp;section=comms'
            ),
    'sms-settings'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('SMS settings','church-admin'),
                'link'=>$church_admin_url.'&action=sms-settings&amp;section=comms'
            ),
    'send-sms'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Send SMS','church-admin'),
                'link'=>$church_admin_url.'&action=send-sms&amp;section=comms'
            ),
    'email-settings'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Email settings','church-admin'),
                'link'=>$church_admin_url.'&action=email-settings&amp;section=comms'
            ),
    'smtp-settings'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('SMTP settings','church-admin'),
                'link'=>$church_admin_url.'&action=smtp-settings&amp;section=comms'
            ),
    'send-email'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Send email','church-admin'),
                'link'=>$church_admin_url.'&action=send-email&amp;section=comms'
            ),
    'email-settings'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Email settings','church-admin'),
                'link'=>$church_admin_url.'&action=email-settings&amp;section=comms'
            ),
    'sync-mailchimp'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Sync MailChimp','church-admin'),
                'link'=>$church_admin_url.'&action=sync-mailchimp&amp;section=comms'
            ),
    'send-mailchimp'=>array(
                'module'=>'Comms',
                'parent'=>'comms',
                'level'=>'Bulk Email',
                'section'=>'comms',
                'title'=>__('Send MailChimp','church-admin'),
                'link'=>$church_admin_url.'&action=send-mailchimp&amp;section=comms'
            ),
    'rota'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('Schedule','church-admin'),
                'link'=>$church_admin_url.'&action=view-rota&amp;section=rota',
                "dashicon"=>'dashicons-editor-ol'
            ),
    'rota-settings'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('Schedule jobs','church-admin'),
                'link'=>$church_admin_url.'&action=rota-settings&amp;section=rota'
            ),
    'edit-rota-job'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('Add a schedule job','church-admin'),
                'link'=>$church_admin_url.'&action=edit-rota-job&amp;section=rota'
            ),
    'auto-email-rota'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('Auto email schedule','church-admin'),
                'link'=>$church_admin_url.'&action=rota-auto-email&amp;section=rota'
            ),
    'show-cron'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('Show schedule cron jobs','church-admin'),
                'link'=>$church_admin_url.'&action=show-cron&amp;section=rota'
            ),
    'email-rota'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('Email schedule','church-admin'),
                'link'=>$church_admin_url.'&action=email-rota&amp;section=rota'
            ),
    'sms-rota'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('SMS schedule','church-admin'),
                'link'=>$church_admin_url.'&action=sms-rota&amp;section=rota'
            ),
    'pdf-rota'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('PDF schedule','church-admin'),
                'link'=>$church_admin_url.'&action=pdf-rota&amp;section=rota'
            ),
    'csv-rota'=>array(
                'module'=>'Rota',
                'parent'=>'rota',
                'level'=>'Rota',
                'section'=>'rota',
                'title'=>__('CSV schedule','church-admin'),
                'link'=>$church_admin_url.'&action=csv-rota&amp;section=rota'
            ),
    'calendar'=>array(
                'module'=>'Calendar',
                'parent'=>'calendar',
                'level'=>'Calendar',
                'section'=>'calender',
                'title'=>__('Calendar','church-admin'),
                'link'=>$church_admin_url.'&action=calendar&amp;section=calendar',
                "dashicon"=>'dashicons-calendar-alt'
            ),
        'add-calendar'=>array(
                'module'=>'Calendar',
                'parent'=>'calendar',
                'level'=>'Calendar',
                'section'=>'calender',
                'title'=>__('Add to calendar','church-admin'),
                'link'=>$church_admin_url.'&action=add-calendar&amp;section=calendar'
            ),
    'view-categories'=>array(
                'module'=>'Calendar',
                'parent'=>'calendar',
                'level'=>'Calendar',
                'section'=>'calender',
                'title'=>__('View categories','church-admin'),
                'link'=>$church_admin_url.'&action=categories&amp;section=calendar'
            ),
    'edit-category'=>array(
                'module'=>'Calendar',
                'parent'=>'calendar',
                'level'=>'Calendar',
                'section'=>'calender',
                'title'=>__('Edit category','church-admin'),
                'link'=>$church_admin_url.'&action=edit-category&amp;section=calendar'
            ),
        'facilities'=>array(
                'module'=>'Calendar',
                'parent'=>'facilities',
                'level'=>'Calendar',
                'section'=>'facilities',
                'title'=>__('Facilities','church-admin'),
                'link'=>$church_admin_url.'&action=facilities&amp;section=facilities',
                "dashicon"=>'dashicons-calendar-alt'
            ),
        'facility-bookings'=>array(
                'module'=>'Calendar',
                'parent'=>'facilities',
                'level'=>'Calendar',
                'section'=>'facilities',
                'title'=>__('Facility bookings','church-admin'),
                'link'=>$church_admin_url.'&action=facility-bookings&amp;section=facilities'
            ),
    'ministries'=>array(
                'module'=>'Ministries',
                'parent'=>'ministries',
                'level'=>'Directory',
                'section'=>'ministries',
                'title'=>__('Ministries','church-admin'),
                'link'=>$church_admin_url.'&action=ministries-list&amp;section=ministries',
                "dashicon"=>'dashicons-networking'
            ),
    'edit-ministry'=>array(
                'module'=>'Ministries',
                'parent'=>'ministries',
                'level'=>'Directory',
                'section'=>'ministries',
                'title'=>__('Add a ministry','church-admin'),
                'link'=>$church_admin_url.'&action=edit-ministr&amp;section=ministries'
            ),
    'volunteers'=>array(
                'module'=>'Ministries',
                'parent'=>'ministries',
                'level'=>'Directory',
                'section'=>'ministries',
                'title'=>__('Volunteers','church-admin'),
                'link'=>$church_admin_url.'&action=volunteers&amp;section=ministries'
            ),
    'media'=>array(
                'module'=>'Media',
                'parent'=>'media',
                'level'=>'Sermons',
                'section'=>'media',
                'title'=>__('Media','church-admin'),
                'link'=>$church_admin_url.'&action=podcast&amp;section=media',
                "dashicon"=>'dashicons-controls-volumeon'
            ),
    'upload-mp3'=>array(
                'module'=>'Media',
                'parent'=>'media',
                'level'=>'Sermons',
                'section'=>'media',
                'title'=>__('Upload media','church-admin'),
                'link'=>$church_admin_url.'&action=upload-mp3&amp;section=media'
            ),
    'check-files'=>array(
                'module'=>'Media',
                'parent'=>'media',
                'level'=>'Sermons',
                'section'=>'media',
                'title'=>__('Add uploaded file','church-admin'),
                'link'=>$church_admin_url.'&action=check-files&amp;section=media'
            ),
    'sermon-series'=>array(
                'module'=>'Media',
                'parent'=>'media',
                'level'=>'Sermons',
                'section'=>'media',
                'title'=>__('Sermon series','church-admin'),
                'link'=>$church_admin_url.'&action=sermon-series&amp;section=media'
            ),
    'edit-series'=>array(
                'module'=>'Media',
                'parent'=>'media',
                'level'=>'Sermons',
                'section'=>'media',
                'title'=>__('Edit series','church-admin'),
                'link'=>$church_admin_url.'&action=edit-series&amp;section=media'
            ),
    'podcast-settings'=>array(
                'module'=>'Media',
                'parent'=>'media',
                'level'=>'Sermons',
                'section'=>'media',
                'title'=>__('Podcast settings','church-admin'),
                'link'=>$church_admin_url.'&action=podcast-settings&amp;section=media'
            ),
    'app'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('App','church-admin'),
                'link'=>$church_admin_url.'&action=app&amp;section=app',
                "dashicon"=>'dashicons-smartphone'
            ),
    'app-content'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('App content','church-admin'),
                'link'=>admin_url().'edit.php?post_type=app-content'
            ),
     'app-menu'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('App menu','church-admin'),
                'link'=>$church_admin_url.'&action=app-menu&amp;section=app'
            ), 
    'app-settings'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('App settings','church-admin'),
                'link'=>$church_admin_url.'&action=app-settings&amp;section=app'
            ), 
    'app-users'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('App users','church-admin'),
                'link'=>$church_admin_url.'&action=app-logins&amp;section=app'
            ),
    'app-settings'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('App settings','church-admin'),
                'link'=>$church_admin_url.'&action=app-settings&amp;section=app'
            ),
    'app-logout'=>array(
                'module'=>'app',
                'confirm'=>TRUE,
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('Logout everyone','church-admin'),
                'link'=>$church_admin_url.'&action=app-settings&amp;section=app'
            ),
    'bible-version'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('Bible version','church-admin'),
                'link'=>$church_admin_url.'&action=bible-version&amp;section=app'
            ),
    'bible-reading-plan'=>array(
                'module'=>'app',
                'parent'=>'app',
                'level'=>'Directory',
                'section'=>'app',
                'title'=>__('Bible reading plan','church-admin'),
                'link'=>$church_admin_url.'&action=bible-reading-plan&amp;section=app'
            ),
    'settings'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Settings','church-admin'),
                'link'=>$church_admin_url.'&action=general-settings&amp;section=settings',
                "dashicon"=>'dashicons-admin-generic'
            ),
    'choose-modules'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Choose modules','church-admin'),
                'link'=>$church_admin_url.'&action=modules&amp;section=settings'
            ),
    'choose-filters'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Choose filters','church-admin'),
                'link'=>$church_admin_url.'&action=choose-filters&amp;section=settings'
            ),
    'restrict-access'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Restrict access','church-admin'),
                'link'=>$church_admin_url.'&action=modules&amp;section=settings'
            ),
    'people-types'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('People types','church-admin'),
                'link'=>$church_admin_url.'&action=people-types&amp;section=settings'
            ),
    'marital-status'=>array(
                 'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Marital status','church-admin'),
                'link'=>$church_admin_url.'&action=marital-status&amp;section=settings'
            ),
    'debug-log'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Marital status','church-admin'),
                'link'=>$church_admin_url.'&action=debug-log&amp;section=settings'
            ),
    'installation-errors'=>array(
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Installation errors','church-admin'),
                'link'=>$church_admin_url.'&action=installation-errors&amp;section=settings'
            ),
    'refresh-backup'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Refresh plugin backup','church-admin'),
                'link'=>$church_admin_url.'&action=refresh-backup&amp;section=settings'
            ),
    'restore-backup'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Restore plugin backup','church-admin'),
                'link'=>$church_admin_url.'&action=restore-backup&amp;section=settings'
            ),
    'permissions'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Permissions','church-admin'),
                'link'=>$church_admin_url.'&action=permissions&amp;section=settings'
            ),
    'roles'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Roles','church-admin'),
                'link'=>$church_admin_url.'&action=roles&amp;section=settings'
            ),
    'replicate-roles'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Replicate roles','church-admin'),
                'link'=>$church_admin_url.'&action=replicate-roles&amp;section=settings'
            ),
    'reset-version'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Reset version','church-admin'),
                'link'=>$church_admin_url.'&action=reset-version&amp;section=settings'
            ),
    'shortcodes'=>array(
                'module'=>'Settings',
                'parent'=>'settings',
                'level'=>'Directory',
                'section'=>'settings',
                'title'=>__('Shortcodes','church-admin'),
                'link'=>$church_admin_url.'&action=shortcodes&amp;section=settings'
            ),
    
);