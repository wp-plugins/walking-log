<?php

/*

Plugin Name: Walking Log
Plugin URI: http://www.willowridgesoftware.com/apps.php
Description: Exercise log for tracking time and distance based exercise, such as walking or running.
Version: 1.3
Author: Dave Carlile
Author URI: http://www.crappycoding.com
License: MIT

*/


/*
  
Copyright (c) 2012 - 2013 Dave Carlile (email: david@willowridgesoftware.com)


Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

if (! class_exists('wrsWalkingLogPlugin'))
{
  class wrsWalkingLogPlugin
  {
    var $LegacyScope = 0;
    var $GlobalScope = 1;
    var $UserScope = 2;
    var $GlobalPermanentScope = 3;
  
    var $Admin;
    var $Reports;
    var $dbVersion = '1.3';
    var $ExerciseTypeTableName;
    var $ExerciseLocationTableName;
    var $ExerciseLogTableName;
    var $Settings;
    var $Options;
    var $TimeSeparator;
    var $IsMultiSite;
    var $IsNetworkWide;
    var $CurrentUserCanEditLog;
    var $CurrentUserId;
    var $CurrentUserLogin;
    var $AdminNotices;
    var $LegacyNotice;
    var $HasLegacyData;

    
    function wrsWalkingLogPlugin()
    {
      $this->IsMultiSite = function_exists('is_multisite') && is_multisite();
      $this->IsNetworkWide = isset($_GET['networkwide']) && $_GET['networkwide'] == 1;
      $this->TimeSeparator = ':';
      $this->InitializeTableNames();

      // hooks
      register_activation_hook(__FILE__, array(&$this, 'ActivatePlugin'));
      register_deactivation_hook(__FILE__, array(&$this, 'DeactivatePlugin'));

      // admin actions
      if (is_admin())
      {
        require_once('walking_log_admin.php');
        $this->Admin = new wrsWalkingLogAdmin($this);
        
        if ($this->IsMultiSite)
        {
          add_action('network_admin_menu', array(&$this->Admin, 'NetworkAdminMenu'));
        }

        add_action('admin_notices', array(&$this, 'WriteAdminNotices'));
        add_action('admin_menu', array(&$this->Admin, 'AdminMenu'));
        add_action('admin_init', array(&$this->Admin, 'AdminInit'));
        add_action('admin_print_styles', array(&$this, 'PrintStyles'));
        
        // legacy log user assignment notice
        $this->LegacyNotice = get_option("wrswl_legacy_notice");
        $this->HasLegacyData = get_option("wrswl_has_legacy");
      }

      
      // multi-site event actions
      if ($this->IsMultiSite)
      {
        add_action('wpmu_new_blog', array(&$this, 'NewBlog'), 10, 6);
        //add_action('wpmu_delete_blog', array(&$this, 'DeleteBlog'), 10, 1);
        
        add_filter('wpmu_drop_tables', array(&$this, 'DropTablesForBlog'));
      }

      // event actions
      add_action('plugins_loaded', array(&$this, 'PluginsLoaded'));
      add_action('wp_print_scripts', array(&$this, 'PrintScripts'));
      add_action('wp_print_styles', array(&$this, 'PrintStyles'));
      add_action('wp_print_footer_scripts', array(&$this, 'PrintFooterScripts'));
      add_action('deleted_user', array(&$this, 'DeleteUser'));
      add_action('remove_user_from_blog', array(&$this, 'RemoveUserFromBlog'));

      // ajax actions
      add_action('wp_ajax_get_log', array(&$this, 'GetLogForDate'));
      add_action('wp_ajax_nopriv_get_log', array(&$this, 'GetLogForDate'));
      add_action('wp_ajax_delete_log', array(&$this, 'DeleteLogForID'));
      add_action('wp_ajax_update_log', array(&$this, 'UpdateLogForID'));
      add_action('wp_ajax_insert_log', array(&$this, 'InsertLog'));
    }


    function GetUserSettings($user_id)
    {
      global $wpdb;
      
      // user settings
      $result = get_user_meta($user_id, $wpdb->prefix . 'wrswl_settings', true);

      if (!isset($result['wrswl_settings_time_format']))
        $result['wrswl_settings_time_format'] = $this->Options['default_time_format']; // 'minutes';

      if (!isset($result['wrswl_settings_distance_format']))
        $result['wrswl_settings_distance_format'] = $this->Options['default_distance_format']; // 'miles';
      
      if (!isset($result['wrswl_settings_privacy']))
        $result['wrswl_settings_privacy'] = 'public';
        
      return $result;
    }
    
    
    function InitializeSettings()
    {
      // global options
      $this->Options = get_option('wrswl_options');
      
      if (!isset($this->Options['delete_data_on_user_delete']))
        $this->Options['delete_data_on_user_delete'] = 'false';
      
      if (!isset($this->Options['drop_tables_on_uninstall']))
        $this->Options['drop_tables_on_uninstall'] = 'false';
        
      if (!isset($this->Options['allow_subscriber_role']))
        $this->Options['allow_subscriber_role'] = 'false';
        
      if (!isset($this->Options['default_time_format']))
        $this->Options['default_time_format'] = 'minutes';

      if (!isset($this->Options['default_distance_format']))
        $this->Options['default_distance_format'] = 'miles';
        
      // user settings - note that these must be initialized after options so global options
      // can override user settings if no user settings are found
      $this->Settings = $this->GetUserSettings($this->CurrentUserId);
    }
	
  
    function GetSelectedDate($hash)
    {
      $key = 'selected_date_' . $hash;
      if (isset($this->Settings[$key]))
        return $this->Settings[$key];
      else
        return null;
    }

    
    function SetSelectedDate($hash, $value)
    {
      $key = 'selected_date_' . $hash;
      if (isset($value))
        $this->Settings[$key] = $value;
      else if (isset($this->Settings[$key]))
        unset($this->Settings[$key]);
        
      $this->SaveNewSettings($this->Settings);
    }
    
    
    function SaveNewSettings($settings)
    {
      global $wpdb;
      $this->Settings = $settings;
      update_user_meta($this->CurrentUserId, $wpdb->prefix . 'wrswl_settings', $settings);
    }

    
    function SaveNewOptions($options)
    {
      $this->Options = $options;
      update_option('wrswl_options', $options);
    }
    

    function LegacyDataHasBeenAssigned()
    {
      return (isset($_REQUEST['action']) && $_REQUEST['action'] == 'assign' && 
             isset($_REQUEST['page']) && $_REQUEST['page'] == 'wrs_walking_log_menu_upgrade' &&
             !isset($_REQUEST['change_user_button']));
    }
    
    
    function WriteAdminNotices()
    {
    
      // legacy log user assignment notice
      if (isset($this->LegacyNotice) && $this->LegacyNotice == 1 && !$this->LegacyDataHasBeenAssigned())
      {
        $skip = false;
        if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'wrs_walking_log_menu_upgrade')
          $skip = true;
          
        if (!$skip)
        {
          $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_upgrade';
        
          $this->AdminNotice(sprintf(__('You recently upgraded the Walking Log plugin to Version 1.2. Log data from a previous version needs to be assigned to a blog user. ' .
                             'Please visit the <a href="%s">Upgrade</a> page in Walking Log Settings to resolve this issue.', 
                             'wrs-walking-log'), $admin_url));
        }
      }


      if (!isset($this->AdminNotices)) return;
      if (!is_array($this->AdminNotices)) return;
      
      foreach ($this->AdminNotices as $notice)
      {
        echo '<div class="updated"><p>' . $notice . '</p></div>';
      }
    }

    
    function AdminNotice($message)
    {
      $this->AdminNotices[] = $message;
    }

    
    function ClearLegacyNotice()
    {
      delete_option('wrswl_legacy_notice');
      unset($this->LegacyNotice);
    }
    
    
    function ClearLegacyData()
    {
      delete_option('wrswl_has_legacy');
      unset($this->HasLegacyData);
    }
    
    
    function RefreshUserInfo()
    {    
      global $current_user;
      get_currentuserinfo();
      $this->CurrentUserId = $current_user->ID;
      $this->CurrentUserLogin = $current_user->user_login;
    }
    

    function PluginsLoaded()
    {
      add_shortcode('wrs_walking_log', array(&$this, 'HandleShortCodes'));
      $this->RefreshUserInfo();
      $this->InitializeSettings();
    }
    
    
    function CheckDefaults()
    {
      global $wpdb;
      
      if ($this->CurrentUserId == 0) return;
      if (!current_user_can('read')) return;
      
      // if this is the first time this user has access the plugin the do some user initialization
      $first_run_for_user = get_user_meta($this->CurrentUserId, $wpdb->prefix . 'wrswl_first_run', true);
      if ($first_run_for_user == "")
      {
        // insert defaults for this user - the user shouldn't have any of this data, but we'll make sure just in case
        $row_count = $wpdb->get_var("select count(0) from $this->ExerciseTypeTableName where wordpress_user_id = $this->CurrentUserId and scope = $this->UserScope");
        
        if ($row_count == 0)
        {
          $this->InsertExerciseTypeRow($this->CurrentUserId, $this->UserScope, _x('Walking', 'type of exercise', 'wrs-walking-log'));
          $this->InsertExerciseTypeRow($this->CurrentUserId, $this->UserScope, _x('Hiking', 'type of exercise', 'wrs-walking-log'));
          $this->InsertExerciseTypeRow($this->CurrentUserId, $this->UserScope, _x('Jogging', 'type of exercise', 'wrs-walking-log'));
          $this->InsertExerciseTypeRow($this->CurrentUserId, $this->UserScope, _x('Biking', 'type of exercise', 'wrs-walking-log'));
        }
      }
      
      update_user_meta($this->CurrentUserId, $wpdb->prefix . 'wrswl_first_run', true);
    }

    
    function IsWalkingLogPage()
    {
      global $wp_query, $pagenow;

      // if admin screen then scripts are only needed for the log view page
      if (is_admin() && isset($_REQUEST['page']) && $pagenow == 'admin.php')
      {
      
        $page = $_REQUEST['page'];
        return $page == 'wrs_walking_log_menu_view' or $page == 'wrs_walking_log_menu_upgrade' or $page == 'wrs_walking_log_menu' or
               $page == 'wrs_walking_log_menu_maintenance' or $page == 'wrs_walking_log_network_menu' or $page == 'wrs_walking_log_menu_stats' or
               $page == 'wrs_walking_log_menu_help' or $page == 'wrs_walking_log_menu_uninstall';
      }
      
      // make sure it's a single page or post
      if (!is_page() && ! is_single()) return false;
      
      // get the page or post object so we can grab the ID
      $page = $wp_query->get_queried_object();

      // see if the page has a walking log custom field
      $keys = get_post_custom_keys($page->ID);
      
      if ($keys)
        return (in_array('wrs_walking_log', $keys));
      else
        return false;
    }

    
    function PrintStyles()
    {
      if ($this->IsWalkingLogPage())
      {	
        wp_enqueue_style('wrs_walking_log', plugins_url('css/walking_log.css', __FILE__));
      }
    }


    function PrintScripts()
    {
      global $wp_locale;
      
      if (! $this->IsWalkingLogPage()) return;

      // load translation      
      load_plugin_textdomain('wrs-walking-log', null, basename(dirname(__FILE__)));      

      // create walking log settings object
      $exercise_types = $this->GetExerciseTypes();
      $exercise_locations = $this->GetExerciseLocations();
      $time_format = $this->Settings['wrswl_settings_time_format'];
      $distance_format = $this->Settings['wrswl_settings_distance_format'];
      $decimal_point = $wp_locale->number_format['decimal_point'];
      $thousands_separator = $wp_locale->number_format['thousands_sep'];
      
      ?>
      
      <script type="text/javascript">
        /* <![CDATA[ */
        var wrsWalkingLogSettings = 
        {
          admin_url: '<?php echo admin_url("admin-ajax.php") ?>',
          exerciseTypes: <?php echo $exercise_types ?>,
          exerciseLocations: <?php echo $exercise_locations ?>,
          timeFormat: '<?php echo $time_format ?>',
          distanceFormat: '<?php echo $distance_format ?>',
          thousandsSeparator: '<?php echo $thousands_separator ?>',
          decimalPoint: '<?php echo $decimal_point ?>',
          timeSeparator: '<?php echo $this->TimeSeparator ?>',
          updatingMsg: '<?php _ex('Updating...', 'progress message while updating an entry in the walking log', 'wrs-walking-log') ?>',
          deletingMsg: '<?php _ex('Deleting...', 'progress message while deleting an entry from the walking log', 'wrs-walking-log') ?>',
          loadingMsg: '<?php _ex('Loading...', 'progress message while loading the walking log', 'wrs-walking-log') ?>',
          errorMsg: '<?php _ex('Something went wrong. Refresh the page and try again.', 'error message while loading or editing the walking log', 'wrs-walking-log') ?>'
        };
        /* ]]> */
      </script>
      
      <?php

      // queue up the required javascript code and libraries      
      wp_enqueue_script('wrs_walking_log', plugins_url('js/walking_log.js', __FILE__), array('jquery'));
    }


    function PrintFooterScripts()
    {
    }

    
    function RemoveUserFromBlog($user_id, $blog_id)
    {
      // the proper blog is already active when WordPress calls this, but we need to get the table names
      $this->InitializeTableNames();
      $this->DeleteUserForBlog($user_id);
    }
    
    
    function DeleteUser($user_id)
    {
      $this->DeleteUserForBlog($user_id);
    }
    
    
    function DeleteUserForBlog($user_id)
    {
      global $wpdb;

      // remove user settings
	    delete_user_meta($user_id, $wpdb->prefix . 'wrswl_settings');
      delete_user_meta($user_id, $wpdb->prefix . 'wrswl_first_run');


      // remove user data - make sure we have the options for the currently active blog
      $options = get_option('wrswl_options'); 
      
      if ($options['delete_data_on_user_delete'] === 'true')
      {
        $sql = $wpdb->prepare("delete from $this->ExerciseTypeTableName where wordpress_user_id = $user_id");
        $wpdb->query($sql);
        
        $sql = $wpdb->prepare("delete from $this->ExerciseLocationTableName where wordpress_user_id = $user_id");
        $wpdb->query($sql);
        
        $sql = $wpdb->prepare("delete from $this->ExerciseLogTableName where wordpress_user_id = $user_id");
        $wpdb->query($sql);
      }
    }
    

    function InitializeTableNames()
    {
      global $wpdb;
      
      $this->ExerciseTypeTableName = $wpdb->prefix . 'exercise_type';
      $this->ExerciseLocationTableName = $wpdb->prefix . 'exercise_location';
      $this->ExerciseLogTableName = $wpdb->prefix . 'exercise_log';
    }

    
    function DropTable($prefixed_table_name)
    {
      global $wpdb;
      $wpdb->query("drop table if exists $prefixed_table_name");
    }

    
    function CreateTable($prefixed_table_name, $sql)
    {
      global $wpdb;
      
      // create the table if it doesn't already exist
      if ($wpdb->get_var("show tables like '$prefixed_table_name'") != $prefixed_table_name) 
      {
        $sql_create_table = "CREATE TABLE " . $prefixed_table_name . "  (  " . $sql . "  )  ;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_create_table);

        add_option("wrswl_db_version", $this->dbVersion);

        return true;
      }

      return false;
    }


    function DeleteExerciseType($user_id, $id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("delete from $this->ExerciseTypeTableName where type_id = %d and wordpress_user_id = $user_id", $id);
      $wpdb->query($sql);
    }
    
    
    function DeleteExerciseLocation($user_id, $id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("delete from $this->ExerciseLocationTableName where location_id = %d and wordpress_user_id = $user_id", $id);
      $wpdb->query($sql);
    }
    
    
    function ToggleExerciseTypeVisibility($user_id, $id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("update $this->ExerciseTypeTableName set visible = case when visible = 0 then 1 else 0 end " .
                            "where type_id = %d and wordpress_user_id = $user_id", $id);
      $wpdb->query($sql);
    }
    
    
    function ToggleExerciseLocationVisibility($user_id, $id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("update $this->ExerciseLocationTableName set visible = case when visible = 0 then 1 else 0 end " .
                            "where location_id = %d and wordpress_user_id = $user_id", $id);
      $wpdb->query($sql);
    }


    function SetExerciseTypeScope($user_id, $id, $new_scope)
    {
      global $wpdb;
      $sql = $wpdb->prepare("update $this->ExerciseTypeTableName set scope = %s " .
                            "where type_id = %d and wordpress_user_id = %d", $new_scope, $id, $user_id);
      $wpdb->query($sql);
    }
    
    
    function SetExerciseLocationScope($user_id, $id, $new_scope)
    {
      global $wpdb;
      $sql = $wpdb->prepare("update $this->ExerciseLocationTableName set scope = %s " .
                            "where location_id = %d and wordpress_user_id = %d", $new_scope, $id, $user_id);
      $wpdb->query($sql);
    }

    
    function InsertExerciseTypeRow($user_id, $scope, $name)
    {
      global $wpdb;
      $sql = $wpdb->prepare("insert into $this->ExerciseTypeTableName (type_id, wordpress_user_id, name, visible, scope) values(0, %d, %s, 1, %d);", $user_id, $name, $scope);
      $wpdb->query($sql);
    }


    function InsertExerciseLocationRow($user_id, $scope, $name)
    {
      global $wpdb;
      $sql = $wpdb->prepare("insert into $this->ExerciseLocationTableName (location_id, wordpress_user_id, name, visible, scope) values(0, %d, %s, 1, %d);", $user_id, $name, $scope);
      $wpdb->query($sql);
    }


    function CreateExerciseTypeTable($user_id)
    {
      $sql = "
        type_id int NOT NULL AUTO_INCREMENT, 
        wordpress_user_id int NOT NULL,
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        scope tinyint NOT NULL,
        UNIQUE KEY type_id (type_id),
        KEY user_type (wordpress_user_id, type_id)
      ";

      if ($this->CreateTable($this->ExerciseTypeTableName, $sql))
      {
        $this->InsertExerciseTypeRow($user_id, $this->GlobalPermanentScope, _x('Other', 'an exercise type or location that isn\'t specifically listed', 'wrs-walking-log'));
      }
    }


    function CreateExerciseLocationTable($user_id)
    {
      $sql = "
        location_id int NOT NULL AUTO_INCREMENT,
        wordpress_user_id int NOT NULL,
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        scope tinyint NOT NULL,
        UNIQUE KEY location_id (location_id),
        KEY user_location (wordpress_user_id, location_id)
      ";
      
      if ($this->CreateTable($this->ExerciseLocationTableName, $sql))
      {
        $this->InsertExerciseLocationRow($user_id, $this->GlobalPermanentScope, _x('Other', 'an exercise type or location that isn\'t specifically listed', 'wrs-walking-log'));
      }
    }


    function InsertLogRow($user_id, $log_date, $elapsed_time, $distance, $type_id, $location_id)
    {
      global $wpdb;
      
      $sql = $wpdb->prepare("insert into $this->ExerciseLogTableName (log_id, wordpress_user_id, log_date, elapsed_time, distance, type_id, location_id) " .
                   "values(0, %d, %s, %f, %f, %d, %d)", $user_id, $log_date, $elapsed_time, $distance, $type_id, $location_id);

      $wpdb->query($sql);

      return $wpdb->insert_id;
    }

    
    function CreateExerciseLogTable()
    {
      global $wpdb;
      
      $sql = "
        log_id int NOT NULL AUTO_INCREMENT,
        wordpress_user_id int NOT NULL,
        log_date date NOT NULL,
        elapsed_time float NOT NULL,
        distance float NOT NULL,
        type_id int NOT NULL,
        location_id int NOT NULL,
        UNIQUE KEY log_id (log_id),
        KEY user_type (wordpress_user_id, type_id),
        KEY user_location (wordpress_user_id, location_id)
      ";
      
      $this->CreateTable($this->ExerciseLogTableName, $sql);
    }


    function SwitchBlog($blog_id)
    {
      switch_to_blog($blog_id);
      $this->InitializeTableNames();
    }
    
    
    function RestoreBlog()
    {
      restore_current_blog();
      $this->InitializeTableNames();
    }

    
    function NewBlog($blog_id, $user_id, $domain, $path, $site_id, $meta)
    {
      $this->SwitchBlog($blog_id);
      $this->ActivateForBlog();
      $this->RestoreBlog();
    }

    
    function DropTablesForBlog($tables)
    {
      global $wpdb;
      
      $this->DeactivateForBlog();
      $this->InitializeTableNames();

      // add our tables
      $tables[] = $this->ExerciseTypeTableName;
      $tables[] = $this->ExerciseLocationTableName;
      $tables[] = $this->ExerciseLogTableName;

      return $tables;
    }
    
    
    function ActivatePlugin()
    {
      global $wpdb;

      // if network wide activation then activate for all blogs, otherwise just for the currently active blog
      if ($this->IsMultiSite && $this->IsNetworkWide)
      {
        $blogs = $wpdb->get_col($wpdb->prepare("select blog_id from $wpdb->blogs"));

        foreach ($blogs as $blog_id)
        {
          $this->SwitchBlog($blog_id);
          $this->ActivateForBlog();
        }

        $this->RestoreBlog();
      }
      else
      {
        $this->ActivateForBlog();
      }
    }
	
	
    function ActivateForBlog()
    {	
      // create and populate tables if they don't exist - blog activator ads the global values
      $this->CreateExerciseTypeTable($this->CurrentUserId);
      $this->CreateExerciseLocationTable($this->CurrentUserId);
      $this->CreateExerciseLogTable($this->CurrentUserId);
      
      
      // upgrade tables
      $installed_ver = get_option("wrswl_db_version");

      if ($installed_ver != $this->dbVersion)
      {
        require_once('walking_log_upgrade.php');
        wrsWalkingLogUpgrade::UpgradePlugin($this, $installed_ver, $has_legacy_data);
        
        // set option to control admin notice regarding legacy data
        if ($has_legacy_data)
        {
          add_option("wrswl_has_legacy", 1);
          add_option("wrswl_legacy_notice", 1);
        }
      }
    }


    function DeactivatePlugin()
    {
      global $wpdb;
      
      // if network wide activation then activate for all blogs, otherwise just for the currently active blog
      if ($this->IsMultiSite && $this->IsNetworkWide)
      {
        $blogs = $wpdb->get_col($wpdb->prepare("select blog_id from $wpdb->blogs"));

        foreach ($blogs as $blog_id)
        {
          $this->SwitchBlog($blog_id);
          $this->DeactivateForBlog();
        }

        $this->RestoreBlog();
      }
      else
      {
        $this->DeactivateForBlog();
      }
    }
	
	
    function DeactivateForBlog()
    {
    }
    
    
    function UninstallPlugin()
    {
      global $wpdb;
      
      // make sure existing settings are loaded
      $this->InitializeSettings();
      
      
      // unregister settings, NOTE : these are no longer used,  but we'll keep the unregister in for awhile to make sure they get deleted
      unregister_setting('wrswl_settings_group', 'wrswl_settings', array(&$this->Admin, 'ValidateSettings'));
      unregister_setting('wrswl_settings_uninstall', 'wrswl_settings_uninstall', array(&$this->Admin, 'ValidateUninstallSettings'));
      delete_option('wrswl_settings');
      delete_option('wrswl_settings_uninstall');
      

      // remove things we registered
      remove_shortcode('wrs_walking_log');
      delete_option('wrswl_db_version');
      delete_option('wrswl_options');


      // remove metadata entries for all users      
      delete_metadata('user', 0, $wpdb->prefix . 'wrswl_first_run', '', true);
      delete_metadata('user', 0, $wpdb->prefix . 'wrswl_settings', '', true);
      
      
      // remove tables, note that this is only dropping the tables in the current blog
      if (!$this->IsMultiSite && $this->Options['drop_tables_on_uninstall'] === 'true')
      {
        $this->DropTable($this->ExerciseTypeTableName);
        $this->DropTable($this->ExerciseLocationTableName);
        $this->DropTable($this->ExerciseLogTableName);
      }
    }

    
    function HasAdminAccess()
    {
      $capability = 'edit_posts';
      if ($this->Options['allow_subscriber_role'] === "true")
        $capability = 'read';
        
      return current_user_can($capability);
    } 
    
    
    
    function ShowNoDisplayView($message)
    {
      $result = '<p><em>' .
                $message .
                '</em></p>';
      return $result;
    }
    

    function ShowPrivateView()
    {
      $result = '<p><em>' .
                __('This Walking Log is private.', 'wrs-walking-log') .
                '</em></p>';
      return $result;
    }

    
    function CheckViewPermissions($user_id, $default_view, &$no_display_message)
    {
      global $current_user;
      
      
      $view = $default_view;
      $no_display_message = null;
    
   
      $settings = $this->GetUserSettings($user_id);
              
             
      // never display if not a page or single post      
      if (!is_admin() && !is_page() && !is_single())
      {
        $no_display_message = __('Walking Log only displays on pages and single post views.');
        $view = 'no_display';
      }
      else if ($user_id == -1)
      {
        // nothing to do - this is for summarized stats for multiple users, so always global permissions
      }

      // can't display current user log if no user logged in
      else if (!isset($user_id) && !is_user_logged_in())
      {
        $no_display_message = __('You don\'t have a Walking Log.');
        $view = 'no_display';
      }

      // logged in user always has access to their own log, and admin users always have access to all logs
      // with the exception that a subscriber role user might not have their own log
      else if ($user_id == $this->CurrentUserId || current_user_can('manage_options'))
      {
        // are subscribers allowed?
        if ($this->Options['allow_subscriber_role'] === 'false' && !current_user_can('edit_posts'))
        {
          $no_display_message = __('You don\'t have a Walking Log.');
          $view = 'no_display';
        }
        else
        {
          // kind of odd logic here, but just keep the current $view settings, note that this 
          // must fall after the is_user_logged_in check above
        }
      }
      
      // display private message if admin privacy level and user is not admin
      else if ($settings['wrswl_settings_privacy'] == 'admin' && !current_user_can('manage_options'))
        $view = 'private_display';
        
      // display private message if user level and no user logged in
      else if ($settings['wrswl_settings_privacy'] == 'user' && !is_user_logged_in())
        $view = 'private_display';
        
      // display private message if author level and user is not author
      else if ($settings['wrswl_settings_privacy'] == 'author' && get_the_author() != $current_user->display_name)
        $view = 'private_display';

        
      return $view;
    }
    
    
    function GetMainView($user_id, $hash)
    {
      $view = $this->CheckViewPermissions($user_id, 'main', $no_display_message);
      
      $mode = 'view';
      if (isset($_REQUEST['mode']))
      {
        $mode = $_REQUEST['mode'];
        if ($mode != 'edit' && $mode != 'view')
          $mode = 'view';
      }

      $result = '<div class="wrswl-log-view">' . "\n";
      
      if ($view == 'main')
        $result .= $this->ShowMainView(false, $user_id, $hash, $mode);
      else if ($view == 'private_display')
        $result .= $this->ShowPrivateView();
      else if ($view == 'no_display')
        $result .= $this->ShowNoDisplayView($no_display_message);
        
      $result .= "</div>\n";
      
      return $result;
    }
    
    
    function GetLogsView($user_id, $hash)
    {
      require_once('walking_log_reports.php');
      $this->Reports = new wrsWalkingLogReports($this);
      return $this->Reports->GetLogsView($user_id);
    }


    function GetRankView($user_id, $hash, $row_count, $period, $by, $current_period_only, $units)
    {
      require_once('walking_log_reports.php');
      $this->Reports = new wrsWalkingLogReports($this);
      return $this->Reports->GetRankView($user_id, $hash, $row_count, $period, $by, $current_period_only, $units);
    }
    
    
    function GetStatsView($user_id, $hash, $period, $by, $current_period_only, $units)
    {
      $view = $this->CheckViewPermissions($user_id, 'stats', $no_display_message);
      
      if ($view == 'stats')
      {
        require_once('walking_log_reports.php');
        $this->Reports = new wrsWalkingLogReports($this);
        return $this->Reports->GetStatsView($user_id, $hash, $period, $by, $current_period_only, $units);
      }
      else if ($view == 'private_display')
        return $this->ShowPrivateView();
      else if ($view == 'no_display')
        return $this->ShowNoDisplayView($no_display_message);
      else
        return "";
    }
    

    function HandleShortCodes($atts)
    {
      extract(shortcode_atts(array(
                'view' => 'unknown',
                'user' => '',
                'rows' => 5,
                'period' => 'overall',
                'by' => 'distance',
                'current_period_only' => 'no',
                'units' => 'miles',
                'id' => 'srialfb'
              ), $atts));

        
      // user="all" parameter is only valid for stats views
      if ($user == 'all' && $view != 'stats')
      {
        $user = '';
      }

      
      // if a user id is specified then validate it
      if ($user == 'all')
      {
        $user_id = -1;
      }
      else if ($user != '')
      {
        $user_info = new WP_user($user);
        $user_id = $user_info->ID;
        
        if ($user_id == 0)
        {
          return __('The blog user name specified on the walking log short code is invalid.');
        }
      }
      else
      {
        $user_id = null;
      }

      
      // get user id, either current user, or specific user
      if (!isset($user_id))
      {
        $user_id = $this->CurrentUserId;
        if ($user_id == 0) $user_id = null;
      }

      $validation_result = '';      


      // validate rows
      if (intval($rows) != $rows || $rows < 1 || $rows > 50)
      {
        $validation_result .= '<li>' . __('The "rows" parameter is invalid. Valid values are the numbers 1-50.') . '</li>';
      }
      
      // validate period
      if ($period != 'month' && $period != 'year' && $period != 'overall')
      {
        $validation_result .= '<li>' . __('The "period" parameter is invalid. Valid values are: month, year, overall.') . '</li>';
      }
      
      // validate by
      if ($by != 'distance' && $by != 'time')
      {
        $validation_result .= '<li>' . __('The "distance" parameter is invalid. Valid values are: distance, time.') . '</li>';
      }
      
      // validate current period only
      if ($current_period_only != 'yes' && $current_period_only != 'no')
      {
        $validation_result .= '<li>' . __('The "current_period_only" parameter is invalid. Valid values are: yes, no.') . '</li>';
      }
      
      // validate units
      if ($units != 'miles' && $units != 'kilometers')
      {
        $validation_result .= '<li>' . __('The "units" parameter is invalid. Valid values are: miles, kilometers.') . '</li>';
      }

      // return errors
      if ($validation_result != '')
      {
        return '<ul>' . $validation_result . '</ul>';
      }
      
      
      // generate a hash based on the input parameters, so multiple forms can know which form data applies
      $hash = md5($view . $user . $rows . $period . $by . $current_period_only . $units . $id);
      
              
      if ($view == 'main')
        return $this->GetMainView($user_id, $hash);
      else if ($view == 'logs')
        return $this->GetLogsView($user_id, $hash);
      else if ($view == 'rank')
        return $this->GetRankView($user_id, $hash, $rows, $period, $by, $current_period_only, $units);
      else if ($view == 'stats')
        return $this->GetStatsView($user_id, $hash, $period, $by, $current_period_only, $units);
      else
      {
        return sprintf(_x('Unknown view (%s)', 'error message displayed when an invalid view attribute is used on the short code', 'wrs-walking-log'), $view);
      }
    }


    function InsertLog()
    {
      global $wpdb;

      $this->RefreshUserInfo();
      
      // get user id and verify nonce
      if (isset($_REQUEST['action_id']))
        $user_id = $_REQUEST['action_id'];
      else
        $user_id = 0;

      check_ajax_referer('wrswl_nonce_' . $user_id, 'nonce', true);

      
      // look up user info and update permissions
      //$user = get_userdata($user_id);
      $this->UpdateLogPermissions($user_id); // $user->user_login);
      $this->VerifyWritePermissions();
      
      $log_date = $_POST['log_date'];
      $elapsed_time = $_POST['elapsed_time'];
      $distance = $_POST['distance'];
      $type_id = $_POST['type_id'];
      $location_id = $_POST['location_id'];

      $this->VerifyDate($log_date);
      $this->VerifyFloat($elapsed_time);
      $this->VerifyFloat($distance);
      $this->VerifyInteger($type_id);
      $this->VerifyInteger($location_id);
      
      $id = $this->InsertLogRow($user_id, $log_date, $elapsed_time, $distance, $type_id, $location_id);

      // return ok, along with new ID      
      echo "OK:$id";
      die();
    }

    
    function UpdateLogForID()
    {
      global $wpdb;
      
      $this->RefreshUserInfo();
      
      // get user id and verify nonce
      if (isset($_REQUEST['action_id']))
        $user_id = $_REQUEST['action_id'];
      else
        $user_id = 0;
        
      check_ajax_referer('wrswl_nonce_' . $user_id, 'nonce', true);
        

      // look up user info and update permissions
      // $user = get_userdata($user_id);
      $this->UpdateLogPermissions($user_id); // $user->user_login);
      $this->VerifyWritePermissions();

      $id = $_POST['row_id'];
      $log_date = $_POST['log_date'];
      $elapsed_time = $_POST['elapsed_time'];
      $distance = $_POST['distance'];
      $type_id = $_POST['type_id'];
      $location_id = $_POST['location_id'];

      $this->VerifyInteger($id);
      $this->VerifyDate($log_date);
      $this->VerifyFloat($elapsed_time);
      $this->VerifyFloat($distance);
      $this->VerifyInteger($type_id);
      $this->VerifyInteger($location_id);

      
      $sql = $wpdb->prepare("update $this->ExerciseLogTableName " .
                            "set log_date = %s, " .
                            "    elapsed_time = %f, " .
                            "    distance = %f, " .
                            "    type_id = %d, " .
                            "    location_id = %d " .
                            "where log_id = %d and " .
                            "      wordpress_user_id = %d; ",
                            $log_date, $elapsed_time, $distance, $type_id, $location_id, $id, $user_id);
                            
      $wpdb->query($sql);

      echo "OK:0";
      die();
    }

    
    function DeleteLogForID()
    {
      global $wpdb;

      $this->RefreshUserInfo();
      
      // get user id and verify nonce
      if (isset($_REQUEST['action_id']))
        $user_id = $_REQUEST['action_id'];
      else
        $user_id = 0;
        
      check_ajax_referer('wrswl_nonce_' . $user_id, 'nonce', true);
        

      // look up user info and update permissions
      //$user = get_userdata($user_id);
      $this->UpdateLogPermissions($user_id); // $user->user_login);
      $this->VerifyWritePermissions();
      
      $id = $_POST['row_id'];
      $this->VerifyInteger($id);
      
      $sql = $wpdb->prepare("delete from $this->ExerciseLogTableName where log_id = %d and wordpress_user_id = %d", $id, $user_id);
      $results = $wpdb->query($sql);
      
      echo 'OK';
      die();
    }

    


    function VerifyWritePermissions()
    {
      if (!$this->CurrentUserCanEditLog)
        die('Request Failed');
    }



    function VerifyDate($value) 
    { 
      $time = strtotime($value); 
  
      if (!is_numeric($time)) die('Request Failed');

      $month = date('m', $time); 
      $day   = date('d', $time); 
      $year  = date('Y', $time); 
  
      if (!checkdate($month, $day, $year)) die('Request Failed');
    } 


    function VerifyFloat($value)
    {
      if (!is_numeric($value)) die('Request Failed');
    }


    function VerifyInteger($value)
    {
      if (intval($value) != $value) die('Request Failed');
    }    
    
    
    function NumberFormat($value, $decimals = 0)
    {
      // if we add the grouping characters there are potential editing issues we need to deal with,
      // so just leave them out for now
      //return number_format_i18n($value, $decimals);

      return sprintf('%.' . $decimals . 'f', $value);
    }
    
    
    function MinutesToHHMMSS($minutes)
    {
      $seconds = intval(round($minutes * 60), 0);

      $hours = intval($seconds / 3600);
      $seconds %= 3600;
      
      $minutes = intval($seconds / 60);
      $seconds %= 60;

      $seconds = round($seconds, 0);

      return $this->NumberFormat($hours) . 
             sprintf($this->TimeSeparator . '%02d' . $this->TimeSeparator . '%02d', $minutes, $seconds);
    }

    
    function MilesToKilometers($miles)
    {
      return $miles * 1.609344;
    }


    function GetLogTable($date, $user_id, $hash, $mode)
    {
      global $wpdb;
      
      $result = '';
      
      $sql = "select log_id, " .
                    "date_format(e.log_date, '%e - %a') as log_date, " .
                    "date_format(e.log_date, '%Y%m%d') as log_order, " .
                    "e.elapsed_time as elapsed_time, " .
                    "e.distance as distance, " .
                    "t.type_id, " .
                    "l.location_id, " .
                    "t.name as exercise_type, " .
                    "l.name as exercise_location " .
             "from $this->ExerciseLogTableName e " .
             "join $this->ExerciseTypeTableName t on t.type_id = e.type_id " .
             "join $this->ExerciseLocationTableName l on l.location_id = e.location_id " .
             "where e.log_date >= '$date' " .
             "      and e.log_date <  date_add('$date', interval 1 month) " .
             "      and e.wordpress_user_id = $user_id " .
                     
             "union " .
             
             "select -1 as log_id, " .
                    "'Total' as log_date, " .
                    "'190001' as log_order, " .
                    "coalesce(sum(e.elapsed_time), 0) as elapsed_time, " .
                    "coalesce(sum(e.distance), 0) as distance, " .
                    "0 as type_id, " .
                    "0 as location_id, " .
                    "'-' as exercise_type, " .
                    "'-' as exercise_location " .
             "from $this->ExerciseLogTableName e " .
             "where e.log_date >= '$date' " .
             "      and e.log_date <  date_add('$date', interval 1 month) " .
             "      and e.wordpress_user_id = $user_id " .
                     
             "order by log_order desc, log_id";

      $results = $wpdb->get_results($sql);
                    
      $result .= '<table name="wrswl-monthly-data-table" id="wrswl-monthly-data-table">' . "\n";
      $result .= '  <tr>' . "\n";
      $result .= '    <th scope="col" class="wrswl-date-row">' . _x('Date', 'column header for the date the person exercised', 'wrs-walking-log') . '</th>' . "\n";
      $result .= '    <th scope="col" class="wrswl-time-row">' . _x('Time', 'column header for the total amount of time the person exercised', 'wrs-walking-log') . '</th>' . "\n";
      $result .= '    <th scope="col" class="wrswl-distance-row">' . _x('Distance', 'column header for the total distance the person walked or ran', 'wrs-walking-log') . '</th>' . "\n";
      $result .= '    <th scope="col" class="wrswl-type-row">' . _x('Type', 'column header for the type of exercise', 'wrs-walking-log') . '</th>' . "\n";
      $result .= '    <th scope="col" class="wrswl-location-row">' . _x('Location', 'column header for where the exercise took place', 'wrs-walking-log') . '</th>' . "\n";
      $result .= '  </tr>' . "\n";
  
      foreach ($results as $row)
      {
        $time = $row->elapsed_time;
        if ($this->Settings['wrswl_settings_time_format'] === 'minutes')
          $time = $this->NumberFormat($time, 2);
        else if ($this->Settings['wrswl_settings_time_format'] === 'hours')
          $time = $this->NumberFormat($time / 60.0, 2);
        else
          $time = $this->MinutesToHHMMSS($time);
          
        $distance = $row->distance;
        if ($this->Settings['wrswl_settings_distance_format'] !== 'miles')
          $distance = $this->MilesToKilometers($distance);
        $distance = $this->NumberFormat($distance, 2);

        
        $result .= "  <tr id=\"row-$row->log_id\">\n";
        $result .= "    <td class=\"wrswl-date-row\" id=\"date-" . rtrim(substr($row->log_date, 0, 2)) . "\">$row->log_date</td>\n" .
                   "    <td class=\"wrswl-time-row\">$time</td>\n" .
                   "    <td class=\"wrswl-distance-row\">$distance</td>\n" .
                   "    <td class=\"wrswl-type-row\" id=\"type-id-$row->type_id\">$row->exercise_type</td>\n" .
                   "    <td class=\"wrswl-location-row\" id=\"location-id-$row->location_id\">\n" .
                   "      <div>$row->exercise_location</div>\n";

        if ($mode == 'edit' && $row->log_id != -1)
        {
          $result .= "      <div class=\"wrswl-rowactions\">" .
                            '<a class="wrswl-edit-inline" href="?action=edit&id=' . $row->log_id . '">' . _x('edit', 'edit a row in the exercise log', 'wrs-walking-log') . '</a> | ' .
                            '<a class="wrswl-delete-inline" href="?action=delete&id=' . $row->log_id . '">' . _x('delete', 'delete a row from the exercise log', 'wrs-walking-log') . '</a></div>' . "\n";
        }
        
        $result .= "    </td>\n";
        $result .= "  </tr>\n";
      }                             

      $result .= "</table>\n";
      
      return $result;
    }
    
    
    function GetLogForDate()
    {
      global $wpdb;
      
      $this->RefreshUserInfo();
      

      // get user id and verify nonce
      if (isset($_REQUEST['action_id']))
        $user_id = $_REQUEST['action_id'];
      else
        $user_id = 0;
        
      check_ajax_referer('wrswl_nonce_' . $user_id, 'nonce', true);
      

      $date = $_GET['date'];

      $this->VerifyDate($date);
      
      echo 'OK:' . $this->GetLogTable($date, $user_id, '', 'edit');

      die();
    }
    

    function GetExerciseTypes()
    {
      global $wpdb;

      $sql = "select type_id, name from $this->ExerciseTypeTableName where " .
             "(wordpress_user_id = $this->CurrentUserId or scope = $this->GlobalPermanentScope or scope = $this->GlobalScope) " .
             "and visible = 1 order by type_id";
      $rows = $wpdb->get_results($sql);

      $result = '[';

      foreach ($rows as $row)
      {
        if ($result != '[')
          $result .= ', ';

        $result .= "{id:$row->type_id, name:" . json_encode($row->name) . "}";
      }

      $result .= ']';

      return $result;
    }


    function GetExerciseLocations()
    {
      global $wpdb;

      $sql = "select location_id, name from $this->ExerciseLocationTableName where " .
             "(wordpress_user_id = $this->CurrentUserId or scope = $this->GlobalPermanentScope or scope = $this->GlobalScope) " .
             "and visible = 1 order by location_id";
      $rows = $wpdb->get_results($sql);

      $result = '[';

      foreach ($rows as $row)
      {
        if ($result != '[')
          $result .= ', ';

        $result .= "{id:$row->location_id, name:" . json_encode($row->name) . "}";
      }

      $result .= ']';

      return $result;
    }   


    function WriteMonthSelect($user_id, $selectedMonth)
    {
      global $wpdb, $wp_locale;

      $result = '';
      
      $sql = "select coalesce(min(log_date), current_date()) as min_date, " .
             "       coalesce(max(log_date), current_date()) as max_date " .
             "from $this->ExerciseLogTableName ";
             
      if ($user_id != 0)
      {
        $sql .= "where wordpress_user_id = $user_id ";
      }
      
      
      $results = $wpdb->get_results($sql);
      $row = $results[0];

      $minDate = strtotime($row->min_date);
      $maxDate = time(); // strtotime($row->max_date);      

      $earlier_offset = '';
      $later_offset = '';
      if ($user_id != 0)
      {
        $earlier_offset = ' -3 months';
        $next_offset = ' +3 months';
      }
      
      $minDate = strtotime(wrsWalkingLogPlugin::DateFormat("Y-m-1", $minDate) . $earlier_offset);
      $maxDate = strtotime(wrsWalkingLogPlugin::DateFormat("Y-m-1", $maxDate) . $later_offset);
      $date = $minDate;
      
      $thisMonth = strtotime(wrsWalkingLogPlugin::DateFormat("Y-m-1"));
      if ($selectedMonth != '')
      {
        $thisMonth = strtotime($selectedMonth);
      }
      
      $result .= '<select name="wrswl-month-select" id="wrswl-month-select">' . "\n";

      while ($date <= $maxDate)
      {
        // value = yyyy-mm-01
        // text  = month_name yyyy
      
        if ($date == $thisMonth)
          $result .= '<option selected="selected" value="' . wrsWalkingLogPlugin::DateFormat('Y-m-d', $date) . '">' . date_i18n('F Y', $date) . '</option>' . "\n";
        else
          $result .= '<option value="' . wrsWalkingLogPlugin::DateFormat('Y-m-d', $date) . '">' . date_i18n('F Y', $date) . '</option>' . "\n";
          
        $date = strtotime(wrsWalkingLogPlugin::DateFormat("Y-m-d", $date) . " +1 months");
      }
      
      $result .= '</select>' . "\n";
      return $result;
    }

    
    function UpdateLogPermissions($user_id)
    {
      // only users with read capability can edit - seems odd, but we want to include subscribers
      if (!current_user_can('read'))
      {
        $this->CurrentUserCanEditLog = false;
        return;
      }
    
    
      // assume we can edit 
      $this->CurrentUserCanEditLog = true;
      
      
      // the current user can always edit his log if on an admin page
      if (is_admin()) return;

      // if there is no user specified in the shortcode then we're always diplaying the
      // current user's log, and that user can edit his own log
      //if ($user == '') return;
      
      // otherwise a specific user's log is being displayed, so we need to 
      // restrict editing rights to the user who owns the log
      if ($this->CurrentUserId == $user_id) return;


      // no editing allowed for any other situation      
      $this->CurrentUserCanEditLog = false;
    }
    
    
    function ShowMainView($is_admin_page, $user_id, $hash, $mode)
    {
      global $wpdb, $pagenow;
      
      // make sure the user has the initial default values if this is the first time
      $this->CheckDefaults();

      $result = '';

      
      // default selected date to the previously saved date for this form
      $last_selected = $this->GetSelectedDate($hash);
      $selected_date = $last_selected;
      
      // get the form hash so we can make sure any form input belongs to this short code
      if (isset($_GET['id']))
        $form_hash = $_GET['id'];
      else
        $form_hash = $hash;

      if ($hash == $form_hash && isset($_GET['wrswl-month-select']))
      {
        $selected_date = $_GET['wrswl-month-select'];
      }
      
      if (!isset($selected_date))
      {
        $selected_date = wrsWalkingLogPlugin::DateFormat('Y-m-01');
      }

      $this->VerifyDate($selected_date);
      
      
      // create nonce and write out nonce field      
      $nonce = wp_create_nonce('wrswl_nonce_' . $user_id);
      $nonce_field = '<input type="hidden" id="wrswl_nonce" name="wrswl_nonce" value="' . $nonce . '" />';
      $result .= $nonce_field;
      
      // write out user id field - ajax requests must pass this
      $result .= '<input type="hidden" id="wrswl_id" name="wrswl_id" value="' . $user_id . '" />';
      

      // uncomment this to allow the javascript code to show trace messages during ajax calls
      // $result .= "\n" . '<div id="trace_message"></div>' . "\n";
  
  
      $result .= '<form method="get" name="wrsrl-month-select">';
      if (is_admin() && isset($_REQUEST['page']) && $pagenow == 'admin.php' && $_REQUEST['page'] == 'wrs_walking_log_menu_stats')
      {
        $result .= '  <input type="hidden" name="page" value="wrs_walking_log_menu_view" />';
      }
      
      $result .= '  <input type="hidden" name="id" value="' . $hash . '">';
      $result .= $this->WriteMonthSelect($user_id, $selected_date);
      $result .= '  <input type="submit" name="wrswl-select-date" id="wrswl-select-date" value="' . _x('Select', 'submit button to select a date from a list', 'wrs-walking-log') . '"/>';


      // add top edit button
      $this->UpdateLogPermissions($user_id);
      if ($this->CurrentUserCanEditLog)
      {
        $result .= '  <input style="display:none" type="button" id="wrswl-edit-log-top" href="#" value="' . _x('Edit Log', 'edit the walking log', 'wrs-walking-log') . '" />';
      }
      $result .= '</form>';
        


      // write log container and initial log for the selected month - this will be replaced by the browser if javascript is enabled
      $result .= '<div name="wrswl-monthly-data" id="wrswl-monthly-data">';
      $result .= $this->GetLogTable($selected_date, $user_id, $hash, $mode);
      $result .= '</div>';

      
      // add bottom edit button
      if ($this->CurrentUserCanEditLog)
      {
        $result .= '<div>';
        $result .= '  <div style="float:left"><input style="display:none" type="button" id="wrswl-edit-log-bottom" href="#" value="' . _x('Edit Log', 'edit the walking log', 'wrs-walking-log') . '" /></div>';
        
        $admin_url = admin_url('admin.php?page=wrs_walking_log_menu');
        
        if (!$is_admin_page)
        {
          $result .= '  <div style="float:right"><a id="wrswl-settings-link" href="' . $admin_url . '">' . _x('Settings', 'go to the walking log settings admin page', 'wrs-walking-log') . '</a></div>';
        }
        
        $result .= '</div>';
      }
      
      
      // remember selected value for this form
      if ($selected_date != $last_selected)
      {
        $this->SetSelectedDate($hash, $selected_date);
      }
      
      return $result;
    }


    function HandleUpdates()
    {
      if (!isset($_REQUEST['action'])) return;
      if (!wp_verify_nonce($_REQUEST['_wpnonce'])) wp_die('Request Failed');

      $action = $_REQUEST['action'];
      
      // validate action
      if ($action != 'add_type' && $action != 'add_location' &&
          $action != 'show' && $action != 'hide' && $action != 'delete' &&
          $action != 'test_activation' && $action != 'global' && $action != 'user')
        wp_die('Request failed');

        
      switch ($action)
      {
        case 'delete':
          if (isset($_REQUEST['type_id']))
          {
            $type_id = $_REQUEST['type_id'];
            $this->VerifyInteger($type_id);
            $this->DeleteExerciseType($this->CurrentUserId, $type_id);
          }
          else if (isset($_REQUEST['location_id']))
          {
            $location_id = $_REQUEST['location_id'];
            $this->VerifyInteger($location_id);
            $this->DeleteExerciseLocation($this->CurrentUserId, $location_id);
          }
          
          break;
          
        case 'global':
        case 'user':
          // only admin users can do this
          if (!current_user_can('manage_options')) wp_die('Request failed.');
          
          if ($action == 'global')
            $new_scope = $this->GlobalScope;
          else 
            $new_scope = $this->UserScope;
          
          if (isset($_REQUEST['type_id']))
          {
            $type_id = $_REQUEST['type_id'];
            $this->VerifyInteger($type_id);
            $this->SetExerciseTypeScope($this->CurrentUserId, $type_id, $new_scope);
          }
          else if (isset($_REQUEST['location_id']))
          {
            $location_id = $_REQUEST['location_id'];
            $this->VerifyInteger($location_id);
            $this->SetExerciseLocationScope($this->CurrentUserId, $location_id, $new_scope);
          }
          
          break;
          
      
        case 'hide':
        case 'show':
          if (isset($_REQUEST['type_id']))
          {
            $type_id = $_REQUEST['type_id'];
            $this->VerifyInteger($type_id);
            $this->ToggleExerciseTypeVisibility($this->CurrentUserId, $type_id);
          }
          else if (isset($_REQUEST['location_id']))
          {
            $location_id = $_REQUEST['location_id'];
            $this->VerifyInteger($location_id);
            $this->ToggleExerciseLocationVisibility($this->CurrentUserId, $location_id);
          }
            
          break;
          
        case 'add_type':
          if (isset($_REQUEST['add_type_edit']))
          {
            $value = htmlentities(stripslashes($_REQUEST['add_type_edit']));
            $this->InsertExerciseTypeRow($this->CurrentUserId, $this->UserScope, $value);
          }
          break;
          
        case 'add_location':
          if (isset($_REQUEST['add_location_edit']))
          {
            $value = htmlentities(stripslashes($_REQUEST['add_location_edit']));
            $this->InsertExerciseLocationRow($this->CurrentUserId, $this->UserScope, $value);
          }
          break;
          
        case 'test_activation':
          $this->IsMultiSite = false;
          $this->IsNetworkWide = false;
          $this->ActivatePlugin();
          break;
          
      }
    }

      
    public static function DateFormat($date_format_string, $unix_timestamp = false) 
    {
      if ($unix_timestamp === false)
      {
        $unix_timestamp = current_time('timestamp');  // obey configured time zone
      }

      return date($date_format_string, $unix_timestamp);
    }
  }  // class wrsWalkingLogPlugin
}  // if (! class_exists('wrsWalkingLogPlugin'))


// create class instance - this handles registering all necessary hooks, creating
// necessary tables on plugin activation, and so on
if (class_exists('wrsWalkingLogPlugin'))
  $wrs_WalkingLogPlugin = new wrsWalkingLogPlugin();

?>
