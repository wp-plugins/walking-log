<?php

/*

Plugin Name: Walking Log
Plugin URI: http://www.willowridgesoftware.com/apps.php
Description: Exercise log for tracking time and distance based exercise, such as walking or running.
Version: 1.1
Author: Dave Carlile
Author URI: http://www.crappycoding.com
License: MIT

*/


/*
  
Copyright (c) 2012 Dave Carlile (email: david@willowridgesoftware.com)


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
    var $Admin;
    var $dbVersion = '1.2';
    var $ExerciseTypeTableName;
    var $ExerciseLocationTableName;
    var $ExerciseLogTableName;
    var $Options;
    var $TimeSeparator;
    var $UninstallRequest;
    var $IsMultiSite;
    var $IsNetworkWide;
    var $CurrentUserIsAuthor;
    var $CurrentUserCanEditLog;

    
    function wrsWalkingLogPlugin()
    {
      $this->IsMultiSite = function_exists('is_multisite') && is_multisite();
      $this->IsNetworkWide = isset($_GET['networkwide']) && $_GET['networkwide'] == 1;
      $this->TimeSeparator = ':';
      $this->InitializeTableNames();
      $this->InitializeSettings();

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
        
        add_action('admin_menu', array(&$this->Admin, 'AdminMenu'));
        add_action('admin_init', array(&$this->Admin, 'AdminInit'));
        add_action('admin_print_styles', array(&$this, 'PrintStyles'));
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

      // ajax actions
      add_action('wp_ajax_get_log', array(&$this, 'GetLogForDate'));
      add_action('wp_ajax_nopriv_get_log', array(&$this, 'GetLogForDate'));
      add_action('wp_ajax_delete_log', array(&$this, 'DeleteLogForID'));
      add_action('wp_ajax_update_log', array(&$this, 'UpdateLogForID'));
      add_action('wp_ajax_insert_log', array(&$this, 'InsertLog'));
    }


    function InitializeSettings()
    {
      $this->Options = get_option('wrswl_settings');

      if (!isset($this->Options['wrswl_settings_time_format']))
        $this->Options['wrswl_settings_time_format'] = 'minutes';

      if (!isset($this->Options['wrswl_settings_distance_format']))
        $this->Options['wrswl_settings_distance_format'] = 'miles';
        
      if (!isset($this->Options['wrswl_settings_privacy']))
        $this->Options['wrswl_settings_privacy'] = 'public';
        
      // uninstall request      
      $this->UninstallRequest = get_option('wrswl_settings_uninstall');
      if (! $this->UninstallRequest)
        $this->UninstallRequest = 'no';
    }


    function RefreshUserInfo()
    {    
      global $current_user;
      get_currentuserinfo();
      $this->CurrentUserIsAuthor = get_the_author() == $current_user->display_name;
      $this->CurrentUserCanEditLog = current_user_can('manage_options') || $this->CurrentUserIsAuthor;
    }
    

    function PluginsLoaded()
    {
      add_shortcode('wrs_walking_log', array(&$this, 'HandleShortCodes'));
    }


    function IsWalkingLogPage()
    {
      global $wp_query;

      if (is_admin()) return true;
      
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
      load_plugin_textdomain('wrs_walking_log', null, basename(dirname(__FILE__)));      

      // create walking log settings object
      $exercise_types = $this->GetExerciseTypes();
      $exercise_locations = $this->GetExerciseLocations();
      $time_format = $this->Options['wrswl_settings_time_format'];
      $distance_format = $this->Options['wrswl_settings_distance_format'];
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
          timeSeparator: '<?php echo $this->TimeSeparator ?>'
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

        return TRUE;
      }

      return FALSE;
    }


    function AlterTable($prefixed_table_name, $sql)
    {
      global $wpdb;

      $sql_create_table = "CREATE TABLE " . $prefixed_table_name . "  (  " . $sql . "  )  ;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql_create_table);
    }


    function DeleteExerciseType($id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("delete from $this->ExerciseTypeTableName where type_id = %d", $id);
      $wpdb->query($sql);
    }
    
    
    function DeleteExerciseLocation($id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("delete from $this->ExerciseLocationTableName where location_id = %d", $id);
      $wpdb->query($sql);
    }
    
    
    function ToggleExerciseTypeVisibility($id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("update $this->ExerciseTypeTableName set visible = case when visible = 0 then 1 else 0 end where type_id = %d", $id);
      $wpdb->query($sql);
    }
    
    
    function ToggleExerciseLocationVisibility($id)
    {
      global $wpdb;
      $sql = $wpdb->prepare("update $this->ExerciseLocationTableName set visible = case when visible = 0 then 1 else 0 end where location_id = %d", $id);
      $wpdb->query($sql);
    }
    
    
    function InsertExerciseTypeRow($name)
    {
      global $wpdb;
      $sql = $wpdb->prepare("insert into $this->ExerciseTypeTableName (type_id, name, visible) values(0, %s, 1);", $name);
      $wpdb->query($sql);
    }


    function InsertExerciseLocationRow($name)
    {
      global $wpdb;
      $sql = $wpdb->prepare("insert into $this->ExerciseLocationTableName (location_id, name, visible) values(0, %s, 1);", $name);
      $wpdb->query($sql);
    }


    function CreateExerciseTypeTable()
    {
      $sql = "
        type_id int NOT NULL AUTO_INCREMENT, 
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        UNIQUE KEY type_id (type_id)
      ";

      if ($this->CreateTable($this->ExerciseTypeTableName, $sql))
      {
        /* translators: An exercise type or location not specifically listed */
        $this->InsertExerciseTypeRow(__('Other', 'wrs_walking_log'));
        $this->InsertExerciseTypeRow(__('Walking', 'wrs_walking_log'));
        $this->InsertExerciseTypeRow(__('Hiking', 'wrs_walking_log'));
        $this->InsertExerciseTypeRow(__('Jogging', 'wrs_walking_log'));
        $this->InsertExerciseTypeRow(__('Biking', 'wrs_walking_log'));
      }
    }


    function CreateExerciseLocationTable()
    {
      $sql = "
        location_id int NOT NULL AUTO_INCREMENT,
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        UNIQUE KEY location_id (location_id)
      ";
      
      if ($this->CreateTable($this->ExerciseLocationTableName, $sql))
      {
        $this->InsertExerciseLocationRow(__('Other', 'wrs_walking_log'));
      }
    }


    function InsertLogRow($log_date, $elapsed_time, $distance, $type_id, $location_id, $verify_nonce = true)
    {
      global $wpdb;

      if ($verify_nonce)
        $this->VerifyNonce();

      $wpdb->query($wpdb->prepare("insert into $this->ExerciseLogTableName (log_id, log_date, elapsed_time, distance, type_id, location_id) " .
                   "values(0, %s, %f, %f, %d, %d)", $log_date, $elapsed_time, $distance, $type_id, $location_id));
                   
      return $wpdb->insert_id;
    }

    
    function CreateExerciseLogTable()
    {
      global $wpdb;
      
      $sql = "
        log_id int NOT NULL AUTO_INCREMENT,
        log_date date NOT NULL,
        elapsed_time float NOT NULL,
        distance float NOT NULL,
        type_id int NOT NULL,
        location_id int NOT NULL,
        UNIQUE KEY log_id (log_id),
        KEY type_id (type_id),
        KEY location_id (location_id)
      ";
      
      $this->CreateTable($this->ExerciseLogTableName, $sql);
    }


    function UpdateExerciseTypeTable_11()
    {
      global $wpdb;

      $sql = "
        type_id int NOT NULL AUTO_INCREMENT, 
        name varchar(60) NOT NULL,
        visible tinyint NULL,
        UNIQUE KEY type_id (type_id)
      ";

      $this->AlterTable($this->ExerciseTypeTableName, $sql);

      // initialize new column
      $sql = $wpdb->prepare("update $this->ExerciseTypeTableName set visible = 1 where visible is null");
      $wpdb->query($sql);
    }


    function UpdateExerciseLocationTable_11()
    {
      global $wpdb;

      $sql = "
        location_id int NOT NULL AUTO_INCREMENT,
        name varchar(60) NOT NULL,
        visible tinyint NULL,
        UNIQUE KEY location_id (location_id)
      ";

      $this->AlterTable($this->ExerciseLocationTableName, $sql);

      // initialize new column
      $sql = $wpdb->prepare("update $this->ExerciseLocationTableName set visible = 1 where visible is null ");
      $wpdb->query($sql);
    }

    
    function UpdateExerciseLogTable_12()
    {
      global $wpdb;
      
      $sql = "
        log_id int NOT NULL AUTO_INCREMENT,
        log_date date NOT NULL,
        elapsed_time float NOT NULL,
        distance float NOT NULL,
        type_id int NOT NULL,
        location_id int NOT NULL,
        UNIQUE KEY log_id (log_id),
        KEY type_id (type_id),
        KEY location_id (location_id)
      ";
      
      $this->AlterTable($this->ExerciseLogTableName, $sql);
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
      // make sure the uninstall request option isn't around, in case we somehow got
      // deactivated without removing it
      delete_option("wrswl_settings_uninstall");

      // create and populate tables if they don't exist
      $this->CreateExerciseTypeTable();
      $this->CreateExerciseLocationTable();
      $this->CreateExerciseLogTable();

	
      // upgrade tables
      $installed_ver = get_option("wrswl_db_version");

      if ($installed_ver != $this->dbVersion)
      {
        
        // 1.0 -> 1.1
        if ($installed_ver == '1.0')
        {
          $this->UpdateExerciseTypeTable_11();
          $this->UpdateExerciseLocationTable_11();

          $installed_ver = '1.1';
          update_option("wrswl_db_version", $installed_ver);
        }
        
        // 1.1 -> 1.2
        if ($installed_ver == '1.1')
        {
          $this->UpdateExerciseLogTable_12();
          $installed_ver = '1.2';
          update_option("wrswl_db_version", $installed_ver);
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
      // we want to leave this option until the plugin is actually deactivated, because we've already deleted
      // all of the other plugin fields and options
      delete_option("wrswl_settings_uninstall");
    }
    
    
    function UninstallPlugin()
    {
      // flag that we've uninstalled        
      update_option("wrswl_settings_uninstall", "uninstalled");


      // remove things we registered
      remove_shortcode('wrs_walking_log');
      delete_option("wrswl_db_version");
      delete_option("wrswl_settings");

      
      // unregister settings        
      unregister_setting('wrswl_settings_group', 'wrswl_settings', array(&$this, 'ValidateSettings'));
      unregister_setting('wrswl_settings_uninstall', 'wrswl_settings_uninstall', array(&$this, 'ValidateUninstallSettings'));

      
      // remove tables, note that this is only dropping the tables in the current blog
      if (!$this->IsMultiSite)
      {
        $this->DropTable($this->ExerciseTypeTableName);
        $this->DropTable($this->ExerciseLocationTableName);
        $this->DropTable($this->ExerciseLogTableName);
      }
    }
    
    
    function ShowNoDisplayView()
    {
      echo '<p><em>>>> ';
      _e('Walking Log only displays on pages and single post views.', 'wrs_walking_log');
      echo ' <<<</em></p>';
    }
    

    function ShowPrivateView()
    {
      echo '<p><em>>>> ';
      _e('This Walking Log is private.', 'wrs_walking_log');
      echo ' <<<</em></p>';
    }


    // [wrs_walking_log view="main"]
    function HandleShortCodes($atts)
    {
      extract(shortcode_atts(array(
                'view' => 'unknown'
              ), $atts));


      $View = $view;

      // TODO : clean value

      $this->RefreshUserInfo();
      
      // never display if not a page or single post      
      if (!is_page() && !is_single())
        $View = 'no_display';
        
      // display private message if admin privacy level and user is not admin
      else if ($this->Options['wrswl_settings_privacy'] == 'admin' && !current_user_can('manage_options'))
        $View = 'private_display';
        
      // display private message if user level and no user logged in
      else if ($this->Options['wrswl_settings_privacy'] == 'user' && !is_user_logged_in())
        $View = 'private_display';
        
      // display private message if author level and user is not author
      else if ($this->Options['wrswl_settings_privacy'] == 'author' && !$this->CurrentUserIsAuthor)
        $View = 'private_display';

        

      if ($View == 'main')
        $this->ShowMainView(false);
      else if ($View == 'private_display')
        $this->ShowPrivateView();
      else if ($View == 'no_display')
        $this->ShowNoDisplayView();
      else
      {
        /* translators: Error message displayed when an invalid view attribute is used */
        printf(__('Unknown view (%s)', 'wrs_walking_log'), $view);
      }

      return;
    }


    function InsertLog()
    {
      global $wpdb;

      $this->VerifyWritePermissions();
      $this->VerifyNonce();
      
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
      
      $id = $this->InsertLogRow($log_date, $elapsed_time, $distance, $type_id, $location_id);

      // return ok, along with new ID      
      echo "OK:$id";
      die();
    }

    
    function UpdateLogForID()
    {
      global $wpdb;
      
      $this->VerifyWritePermissions();
      $this->VerifyNonce();
      
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
                            "where log_id = %d; ",
                            $log_date, $elapsed_time, $distance, $type_id, $location_id, $id);
                            
      $wpdb->query($sql);

      echo "OK:0";
      die();
    }


    function WriteNonce()
    {      
      wp_nonce_field('wrswl_nonce', 'wrswl_nonce');
    }   


    function VerifyWritePermissions()
    {
      $this->RefreshUserInfo();
      
      if (!$this->CurrentUserCanEditLog)
      //if (!current_user_can('manage_options') && !$this->CurrentUserIsAuthor)
        die('Request Failed');
    }


    function VerifyNonce()
    {
      if (!wp_verify_nonce($_REQUEST['nonce'], 'wrswl_nonce'))
        die('Request failed');
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
    
    
    function DeleteLogForID()
    {
      global $wpdb;

      $this->VerifyWritePermissions();
      $this->VerifyNonce();

      $id = $_POST['row_id'];

      $this->VerifyInteger($id);

      
      $sql = $wpdb->prepare("delete from $this->ExerciseLogTableName where log_id = %d", $id);
      $results = $wpdb->query($sql);
      
      echo 'OK';
      die();
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


    function GetLogForDate()
    {
      global $wpdb;
      
      $this->VerifyNonce();
      
      $date = $_GET['date'];

      $this->VerifyDate($date);


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
                     
             "order by log_order desc, log_id";

      $results = $wpdb->get_results($sql);
                    
      echo 'OK:<table name="wrswl-monthly-data-table" id="wrswl-monthly-data-table" >';
      
      echo '    <tr>' . "\n";
      /* translators: Date the person exercised */
      echo '      <th scope="col" class="wrswl-date-row">' . __('Date', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: The total amount of time the person exercised */
      echo '      <th scope="col" class="wrswl-time-row">' . __('Time', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: The total distance the person walked */
      echo '      <th scope="col" class="wrswl-distance-row">' . __('Distance', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: The type of exercise */
      echo '      <th scope="col" class="wrswl-type-row">' . __('Type', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: Where the exercise took place */
      echo '      <th scope="col" class="wrswl-location-row">' . __('Location', 'wrs_walking_log') . '</th>' . "\n";

      echo '    </tr>' . "\n";
  
      foreach ($results as $row)
      {
        $time = $row->elapsed_time;
        if ($this->Options['wrswl_settings_time_format'] === 'minutes')
          $time = $this->NumberFormat($time, 2);
        else if ($this->Options['wrswl_settings_time_format'] === 'hours')
          $time = $this->NumberFormat($time / 60.0, 2);
        else
          $time = $this->MinutesToHHMMSS($time);
          
        $distance = $row->distance;
        if ($this->Options['wrswl_settings_distance_format'] !== 'miles')
          $distance = $this->MilesToKilometers($distance);
        $distance = $this->NumberFormat($distance, 2);
          
        
        echo "    <tr id=\"row-$row->log_id\">\n";
        echo "      <td class=\"wrswl-date-row\" id=\"date-" . rtrim(substr($row->log_date, 0, 2)) . "\">$row->log_date</td>" .
                    "<td class=\"wrswl-time-row\">$time</td>" .
                    "<td class=\"wrswl-distance-row\">$distance</td>" .
                    "<td class=\"wrswl-type-row\" id=\"type-id-$row->type_id\">$row->exercise_type</td>" .
                    "<td class=\"wrswl-location-row\" id=\"location-id-$row->location_id\">\n" .
                      "<div>$row->exercise_location</div>\n";

        echo          "<div class=\"wrswl-rowactions\">" .

                            /* translators: Edit a row in the exercise log */
                            '<a class="wrswl-edit-inline" href="#">' . __('edit', 'wrs_walking_log') . '</a> | ' .

                            /* translators: Delete a row from the exercise log */
                            '<a class="wrswl-delete-inline" href="#">' . __('delete', 'wrs_walking_log') . '</a></div>' . "\n";
        echo        "</td>\n";
        echo "    </tr>\n";
      }                             

      echo "</table>";
      die();
    }
    

    function GetExerciseTypes()
    {
      global $wpdb;

      $sql = "select type_id, name from $this->ExerciseTypeTableName where visible = 1 order by type_id";
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

      $sql = "select location_id, name from $this->ExerciseLocationTableName where visible = 1 order by location_id";
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


    function WriteMonthSelect()
    {
      global $wpdb, $wp_locale;
      
      $sql = "select coalesce(min(log_date), current_date()) as min_date, " .
             "       coalesce(max(log_date), current_date()) as max_date " .
             "from $this->ExerciseLogTableName ";
      
      $results = $wpdb->get_results($sql);
      $row = $results[0];

      $minDate = strtotime($row->min_date);
      $maxDate = time(); // strtotime($row->max_date);      

      $minDate = strtotime(date_i18n("Y-m-1", $minDate) . " -3 months");
      $maxDate = strtotime(date_i18n("Y-m-1", $maxDate) . " +3 months");
      $date = $minDate;
      
      $thisMonth = strtotime(date_i18n("y-m-1"));
           
      echo '<select name="wrswl-month-select" id="wrswl-month-select">' . "\n";

      while ($date <= $maxDate)
      {
        // value = yyyy-mm-01
        // text  = month_name yyyy
        
        if ($date == $thisMonth)
          echo '<option selected="selected" value="' . date_i18n('Y-m-d', $date) . '">' . date_i18n('F Y', $date) . '</option>' . "\n";
        else
          echo '<option value="' . date_i18n('Y-m-d', $date) . '">' . date_i18n('F Y', $date) . '</option>' . "\n";
          
        $date = strtotime(date_i18n("Y-m-d", $date) . " +1 months");
      }
      
      
      echo '</select>' . "\n";
    }


    function ShowMainView($IsAdminPage)
    {
      $this->WriteNonce();
      
      // uncomment this to allow the javascript code to show trace messages during ajax calls
      // echo "\n" . '<div id="trace_message"></div>' . "\n";
      
      $this->WriteMonthSelect();
      $this->RefreshUserInfo();
      
      // add top edit button
      if ($this->CurrentUserCanEditLog)
      {
        echo '<input type="button" id="wrswl-edit-log-top" href="#" value="' . __('Edit Log', 'wrs_walking_log') . '" />';
      }


      // write log container
      echo '<div name="wrswl-monthly-data" id="wrswl-monthly-data"></div>';


      // add bottom edit button
      if ($this->CurrentUserCanEditLog)
      {
        echo '<div>';
        echo '  <div style="float:left"><input type="button" id="wrswl-edit-log-bottom" href="#" value="' . __('Edit Log', 'wrs_walking_log') . '" /></div>';
        
        $admin_url = admin_url('admin.php?page=wrs_walking_log_menu');
        
        if (!$IsAdminPage)
        {
          echo '  <div style="float:right"><a id="wrswl-settings-link" href="' . $admin_url . '">Settings</a></div>';
        }
        
        echo '</div>';
      }
    }


    function HandleUpdates()
    {
      if (!isset($_REQUEST['action'])) return;
      if (!wp_verify_nonce($_REQUEST['_wpnonce'])) wp_die('Request Failed');

      $action = $_REQUEST['action'];
      
      // validate action
      if ($action != 'add_type' && $action != 'add_location' &&
          $action != 'show' && $action != 'hide' && $action != 'delete' &&
          $action != 'test_activation') wp_die('Request failed');
     
      switch ($action)
      {
        case 'delete':
          if (isset($_REQUEST['type_id']))
          {
            $type_id = $_REQUEST['type_id'];
            $this->VerifyInteger($type_id);
            $this->DeleteExerciseType($type_id);
          }
          else if (isset($_REQUEST['location_id']))
          {
            $location_id = $_REQUEST['location_id'];
            $this->VerifyInteger($location_id);
            $this->DeleteExerciseLocation($location_id);
          }
          
          break;
      
        case 'hide':
        case 'show':
          if (isset($_REQUEST['type_id']))
          {
            $type_id = $_REQUEST['type_id'];
            $this->VerifyInteger($type_id);
            $this->ToggleExerciseTypeVisibility($type_id);
          }
          else if (isset($_REQUEST['location_id']))
          {
            $location_id = $_REQUEST['location_id'];
            $this->VerifyInteger($location_id);
            $this->ToggleExerciseLocationVisibility($location_id);
          }
            
          break;
          
        case 'add_type':
          if (isset($_REQUEST['add_type_edit']))
          {
            $value = htmlentities(stripslashes($_REQUEST['add_type_edit']));
            $this->InsertExerciseTypeRow($value);
          }
          break;
          
        case 'add_location':
          if (isset($_REQUEST['add_location_edit']))
          {
            $value = htmlentities(stripslashes($_REQUEST['add_location_edit']));
            $this->InsertExerciseLocationRow($value);
          }
          break;
          
        // case 'test_activation':
          // $this->IsMultiSite = true;
          // $this->IsNetworkWide = true;
          // $this->ActivatePlugin();
          // break;
          
      }
    }
  }  // class wrsWalkingLogPlugin
}  // if (! class_exists('wrsWalkingLogPlugin'))


// create class instance - this handles registering all necessary hooks, creating
// necessary tables on plugin activation, and so on
if (class_exists('wrsWalkingLogPlugin'))
  $wrs_WalkingLogPlugin = new wrsWalkingLogPlugin();

?>
