<?php

/*

Plugin Name: Walking Log
Plugin URI: http://www.willowridgesoftware.com/blog/products/walking-log-wordpress-plugin/
Description: Exercise log for tracking time and distance based exercise, such as walking or running.
Version: 1.0
Author: Dave Carlile
Author URI: http://www.crappycoding.com
License: MIT

*/


/*
  
Copyright (c) 2010 Dave Carlile (email: david@willowridgesoftware.com)


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
    var $dbVersion = '1.1';
    var $ExerciseTypeTableName;
    var $ExerciseLocationTableName;
    var $ExerciseLogTableName;
    var $Options;
    var $TimeSeparator;
    var $UninstallRequest;

    function wrsWalkingLogPlugin()
    {
      $this->TimeSeparator = ':';
      $this->InitializeTableNames();
      $this->InitializeSettings();


      // hooks
      register_activation_hook(__FILE__, array(&$this, 'ActivatePlugin'));
      register_deactivation_hook(__FILE__, array(&$this, 'DeactivatePlugin'));
      
      // admin actions
      if (is_admin())
      {
        add_action('admin_menu', array(&$this, 'AdminMenu'));
        add_action('admin_init', array(&$this, 'AdminInit'));
        add_action('admin_print_styles', array(&$this, 'PrintStyles'));
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

      if (!$this->Options['wrswl_settings_time_format'])
        $this->Options['wrswl_settings_time_format'] = 'minutes';

      if (!$this->Options['wrswl_settings_distance_format'])
        $this->Options['wrswl_settings_distance_format'] = 'miles';
        

      // uninstall request      
      $this->UninstallRequest = get_option('wrswl_settings_uninstall');
      if (! $this->UninstallRequest)
        $this->UninstallRequest = 'no';
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
      $sql = "
        log_id int NOT NULL AUTO_INCREMENT,
        log_date date NOT NULL,
        elapsed_time float NOT NULL,
        distance float NOT NULL,
        type_id int NOT NULL,
        location_id int NOT NULL,
        
        UNIQUE KEY log_id (log_id)
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


    function ActivatePlugin()
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
      }
    }


    function DeactivatePlugin()
    {
      // we want to leave this option until the plugin is actually deactivated, because we've already deleted
      // all of the other plugin fields and options
      delete_option("wrswl_settings_uninstall");
    }


    function ShowNoDisplayView()
    {
      echo '<p><em>>>> ';
      _e('Walking Log only displays on pages and single post views', 'wrs_walking_log');
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
      
      if (!is_page() && !is_single())
        $View = "no_display";

      if ($View == 'main')
        $this->ShowMainView();
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
      if (!current_user_can('manage_options'))
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
                    
      echo 'OK:<table name="wrswl-monthly-data-table" id="wrswl-monthly-data-table">';
      
      echo '    <tr>' . "\n";
      /* translators: Date the person exercised */
      echo '      <th scope="col">' . __('Date', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: The total amount of time the person exercised */
      echo '      <th scope="col">' . __('Time', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: The total distance the person walked */
      echo '      <th scope="col">' . __('Distance', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: The type of exercise */
      echo '      <th scope="col">' . __('Type', 'wrs_walking_log') . '</th>' . "\n";

      /* translators: Where the exercise took place */
      echo '      <th scope="col">' . __('Location', 'wrs_walking_log') . '</th>' . "\n";

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

        $result .= "{id:$row->type_id, name:'$row->name'}";
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

        $result .= "{id:$row->location_id, name:'$row->name'}";
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
      $maxDate = strtotime($row->max_date);      


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


    function ShowMainView()
    {
      $this->WriteNonce();
      $this->WriteMonthSelect();

      // add top edit button
      if (current_user_can('manage_options'))
      {
        echo '<input type="button" id="wrswl-edit-log-top" href="#" value="' . __('Edit Log', 'wrs_walking_log') . '" />';
      }


      // write log container
      echo '<div name="wrswl-monthly-data" id="wrswl-monthly-data"></div>';


      // add bottom edit button
      if (current_user_can('manage_options'))
      {
        echo '<div>';
        echo '  <div style="float:left"><input type="button" id="wrswl-edit-log-bottom" href="#" value="' . __('Edit Log', 'wrs_walking_log') . '" /></div>';
        
        $admin_url = admin_url('admin.php?page=wrs_walking_log_menu');
        echo '  <div style="float:right"><a id="wrswl-settings-link" href="' . $admin_url . '">Settings</a></div>';
        echo '</div>';
      }
      
      // echo '<div id="test_message"></div>';
    }


    // admin functionality
    function AdminMenu()
    {
      // general settings
      add_menu_page(__('Walking Log', 'wrs_walking_log'),
                    __('Walking Log', 'wrs_walking_log'),
                    'manage_options',
                    'wrs_walking_log_menu',
                    array(&$this, 'WriteAdminPage'));
                    
      // types and locations editing
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: General settings */
                       __('General', 'wrs_walking_log'),
                       __('General', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_menu',
                       array(&$this, WriteAdminPage));


      // types and locations editing
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: Maintenance settings - add exercise types and locations */
                       __('Maintenance', 'wrs_walking_log'),
                       __('Maintenance', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_menu_maintenance',
                       array(&$this, WriteAdminPage));

      // uninstall
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: Uninstall the plugin */
                       __('Uninstall', 'wrs_walking_log'),
                       __('Uninstall', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_menu_uninstall',
                       array(&$this, WriteAdminPage));

    }



    function WriteAdminPage()
    {
      if ($this->UninstallRequest === "uninstalled")
      {
        $this->WriteAdminUninstalledPage();
        return;
      }
      
      switch ($_GET['page'])
      {
        case 'wrs_walking_log_menu_maintenance':
          $this->WriteAdminMaintenancePage();
          break;

        case 'wrs_walking_log_menu_uninstall':
          $this->WriteAdminUninstallPage();
          break;

        case 'wrs_walking_log_menu':
        default:          
          $this->WriteAdminGeneralPage();
          break;
          
      }
    }



    function AdminInit()
    {
      register_setting('wrswl_settings_group', 'wrswl_settings', array(&$this, 'ValidateGeneralSettings'));
      register_setting('wrswl_settings_uninstall', 'wrswl_settings_uninstall', array(&$this, 'ValidateUninstallSettings'));
      //register_setting('wrswl_settings_maintenance', 'wrswl_settings_maintenance');
 
      
      if ($_GET['page'] === 'wrs_walking_log_menu')
      {
        // usage
        add_settings_section('wrswl_settings_usage', __('Usage', 'wrs_walking_log'), array(&$this, 'WriteUsageSettingsSection'), 'wrswl_walking_log');

        // general settings
        add_settings_section('wrswl_settings_general', __('General Settings', 'wrs_walking_log'), array(&$this, 'WriteGeneralSettingsSection'), 'wrswl_walking_log');
        add_settings_field('wrswl_settings_time_format', __('Exercise Time Display Format', 'wrs_walking_log'), array(&$this, 'WriteSettingTimeFormat'), 'wrswl_walking_log', 'wrswl_settings_general');
        add_settings_field('wrswl_settings_distance_format', __('Distance Display Format', 'wrs_walking_log'), array(&$this, 'WriteSettingDistanceFormat'), 'wrswl_walking_log', 'wrswl_settings_general');
      }
      
      else if ($_GET['page'] === 'wrs_walking_log_menu_uninstall')
      {
        // uninstall
        add_settings_section('wrswl_settings_uninstall', __('Uninstall Walking Log', 'wrs_walking_log'), array(&$this, 'WriteUninstallSettingsSection'), 'wrswl_walking_log');
        add_settings_field('wrswl_settings_uninstall', '', array(&$this, 'WriteSettingUninstall'), 'wrswl_walking_log', 'wrswl_settings_uninstall');
      }
    }


    function ValidateGeneralSettings($input)
    {
      // time format
      $value = trim($input['wrswl_settings_time_format']);
      if ($value !== 'minutes' /*&& $value !== 'hours'*/ && $value !== 'hh:mm:ss')
        $value = 'minutes';

      $this->Options['wrswl_settings_time_format'] = $value;


      // date format
      $value = trim($input['wrswl_settings_distance_format']);
      if ($Value !== 'miles' && $value !== 'kilometers')
        $value = 'miles';

      $this->Options['wrswl_settings_distance_format'] = $value;

      return $this->Options;
    }

    
    function ValidateUninstallSettings($input)
    {
      // uninstall request
      $value = $input;
      
      if ($value != 'no' && $value != 'uninstall' && $value != 'uninstalled')
        $value = 'no';
      
      $this->UninstallRequest = $value;
      
      return $this->UninstallRequest;
    }
    

    // usage settings
    function WriteUsageSettingsSection()
    {
      echo '<div class="wrswl-settings-section">';
      echo '<p>';
      printf(__('To display the walking log you need to include this short code somewhere in a page: %s', 'wrs_walking_log'), '<strong>[wrs_walking_log view="main"]</strong>');
      echo "</p>\n<p>";

      printf(__("You must also add a custom field with a name of %s (along with any value) to each page where you add " .
                "the short code. This is required so the plugin can load itself only on pages where it's needed.", 'wrs_walking_log'),
             '<strong>wrs_walking_log</strong>');

      echo "</p>\n";
      echo "</div>\n";
    }

    
    // general settings
    function WriteGeneralSettingsSection()
    {
      echo '<div class="wrswl-settings-section">';
      // nothing to do
    }


    function WriteSettingTimeFormat()
    {
      $checked_value = ($this->Options['wrswl_settings_time_format'] === "minutes") ? 'checked="checked"' : '';
      echo '<label title="minutes"><input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="minutes" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Minutes - 496.38', 'wrs_walking_log') . '</span></label><br />';

      //$checked_value = ($this->Options['wrswl_settings_time_format'] === "hours") ? 'checked="checked"' : '';
      //echo '<label title="hours"><input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="hours" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Hours - 8.28', 'wrs_walking_log') . '</span></label><br />';

      $checked_value = ($this->Options['wrswl_settings_time_format'] === "hh:mm:ss") ? 'checked="checked"' : '';
      echo '<label title="hh:mm:ss"><input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="hh:mm:ss" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('hhh:mm:ss - 8:16:23', 'wrs_walking_log') . '</span></label><br />';
    }


    function WriteSettingDistanceFormat()
    {
      $checked_value = ($this->Options['wrswl_settings_distance_format'] === "miles") ? 'checked="checked"' : '';
      echo '<label title="miles"><input type="radio" name="wrswl_settings[wrswl_settings_distance_format]" value="miles" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Miles', 'wrs_walking_log') . '</span></label><br />';

      $checked_value = ($this->Options['wrswl_settings_distance_format'] === "kilometers") ? 'checked="checked"' : '';
      echo '<label title="kilometers"><input type="radio" name="wrswl_settings[wrswl_settings_distance_format]" value="kilometers" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Kilometers', 'wrs_walking_log') . '</span></label><br />';

      echo "\n</div>\n";
    }


    // uninstall settings
    function WriteUninstallSettingsSection()
    {
      // nothing to do
    }


    function WriteSettingUninstall()
    {
      // nothing to do
    }


    function WriteAdminGeneralPage()
    {
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));


      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: General Settings', 'wrs_walking_log') . '</h2>' . "\n";

      echo '  <form method="post" action="options.php">' . "\n";
      echo settings_fields('wrswl_settings_group');
      echo do_settings_sections('wrswl_walking_log');

      echo '    <p><input type="submit" class="button-primary" value="' . __('Save Changes', 'wrs_walking_log') . '" /></p>' . "\n";
      echo "  </form>\n";
      echo "</div>\n";
    }


    function WriteLocationEditor()
    {
      global $wpdb;


      echo '<table class="wrswl-list-edit">';
      
      $sql = "select location_id, name, visible from $this->ExerciseLocationTableName order by location_id";
      $rows = $wpdb->get_results($sql);

      $odd = true;
      foreach ($rows as $row)
      {
        if ($odd)
          $class = ' class="odd"';
        else
          $class = '';
          
        echo '  <tr' . $class . '>';

        $odd = !$odd;

        $url = admin_url('admin.php?page=wrs_walking_log_menu_maintenance&location_id=' . $row->location_id);
        
        if ($row->visible === '1')
        {
          $action = 'hide';
          $action_i18n = __('hide', 'wrs_walking_log');
        }
        else
        {
          $action = 'show';
          $action_i18n = __('show', 'wrs_walking_log');
        }
        
        $url .= '&action=' . $action;
        $url = wp_nonce_url($url);
          
        echo '    <td style="width:300px;">' . $row->name . '</td><td>';
        
        if ($row->location_id != 1)
          echo '<a href="' . $url . '">' . $action_i18n . '</a>';
        else
          echo '&nbsp;';
          
        echo '  </td>' . "\n";
        echo "</tr>\n";
      }

      echo '</table>';
      
      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_maintenance';
      
      echo '<div style="margin-top:10px">';
      echo '<form method="post" action="' . $admin_url . '">' . "\n";
      echo '  <input type="hidden" name="action" value="add_location" />';
      
      wp_nonce_field();
      
      echo '  <input name="add_location_edit" type="text" />';
      echo '  <input type="submit" class="button-primary" value="' . __('Add Location', 'wrs_walking_log') . '" />' . "\n";
      echo '</form>';
      echo '</div>';
      
    }
    
    
    function WriteTypeEditor()
    {
      global $wpdb;
      
      echo '<table class="wrswl-list-edit">';
      
      $sql = "select type_id, name, visible from $this->ExerciseTypeTableName order by type_id";
      $rows = $wpdb->get_results($sql);

      $odd = true;
      foreach ($rows as $row)
      {
        if ($odd)
          $class = ' class="odd"';
        else
          $class = '';
          
        echo '  <tr' . $class . '>';

        $odd = !$odd;

        $url = admin_url('admin.php?page=wrs_walking_log_menu_maintenance&type_id=' . $row->type_id);
        
        if ($row->visible === '1')
        {
          $action = 'hide';
          $action_i18n = __('hide', 'wrs_walking_log');
        }
        else
        {
          $action = 'show';
          $action_i18n = __('show', 'wrs_walking_log');
        }
        
        $url .= '&action=' . $action;
        $url = wp_nonce_url($url);
          
        echo '    <td style="width:300px;">' . $row->name . '</td><td>';
        
        if ($row->type_id != 1)
          echo '<a href="' . $url . '">' . $action_i18n . '</a>';
        else
          echo '&nbsp;';
        
        echo '  </td>' . "\n";
        echo "</tr>\n";
      }

      echo '</table>';
      
      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_maintenance';
      
      echo '<div style="margin-top:10px">';
      echo '<form method="post" action="' . $admin_url . '">' . "\n";
      echo '  <input type="hidden" name="action" value="add_type" />';
      
      wp_nonce_field();
      
      echo '  <input name="add_type_edit" type="text" />';
      echo '  <input type="submit" class="button-primary" value="' . __('Add Type', 'wrs_walking_log') . '" />' . "\n";
      echo '</form>';
      echo '</div>';
      
    }
    

    function HandleUpdates()
    {
      if ($_REQUEST['action'])
      {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'])) wp_die('Request Failed');


        $action = $_REQUEST['action'];
        

        // validate action
        if ($action != 'add_type' && $action != 'add_location' &&
            $action != 'show' && $action != 'hide') wp_die('Request failed');
       
        switch ($action)
        {
          case 'hide':
          case 'show':
            if ($_REQUEST['type_id'])
              $this->ToggleExerciseTypeVisibility($_REQUEST['type_id']);
            else
              $this->ToggleExerciseLocationVisibility($_REQUEST['location_id']);
              
            break;
            
          case 'add_type':
            $value = htmlentities($_REQUEST['add_type_edit']);
            $this->InsertExerciseTypeRow($value);
            break;
            
          case 'add_location':
            $value = htmlentities($_REQUEST['add_location_edit']);
            $this->InsertExerciseLocationRow($value);
            break;
            
        }
      }
      
    }
    
    
    function WriteAdminMaintenancePage()
    {
      global $wpdb;

      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Maintenance', 'wrs_walking_log') . '</h2>' . "\n";
      
      $this->HandleUpdates();

      // exercise locations      
      echo "<h3>Exercise Locations</h3>\n";
      echo '<div class="wrswl-settings-section">';
      
      $this->WriteLocationEditor();
      
      echo '</div>';
      
      
      // exercise types
      echo "<h3>Exercise Types</h3>\n";
      echo '<div class="wrswl-settings-section">';
      
      $this->WriteTypeEditor();
      
      echo '</div>';
      
      echo "</div>\n";
    }


    function WriteAdminUninstalledPage()
    {
      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Uninstall', 'wrs_walking_log') . '</h2>' . "\n";
      
      $deactivate_url = 'plugins.php?action=deactivate&amp;plugin=walking-log/walking_log.php';
      if (function_exists('wp_nonce_url')) 
        $deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_walking-log/walking_log.php');
      
      echo '  <p>' .
           __('All Walking Log data has been deleted due to a requested uninstall. Click the link below to complete the ' .
              'uninstallation and deactivate the plugin. If you want to continue using the plugin ' .
              'you will need to deactivate it using the link below, then reactivate from the plugins menu.' , 'wrs_walking_log') .
           "  </p>\n";

      echo '  <a href="' . $deactivate_url . '">' . __('Deactivate Walking Log Plugin', 'wrs_walking_log') . '</a>' . "\n";
      echo "</div>\n";
    }
    
    function WriteAdminUninstallPage()
    {
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));


      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Uninstall', 'wrs_walking_log') . '</h2>' . "\n";


      // check for uninstall
      if ($this->UninstallRequest === 'uninstall')
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
        //unregister_setting('wrswl_settings_maintenance', 'wrswl_settings_maintenance');

        // remove tables        
        $this->DropTable($this->ExerciseTypeTableName);
        $this->DropTable($this->ExerciseLocationTableName);
        $this->DropTable($this->ExerciseLogTableName);


        $deactivate_url = 'plugins.php?action=deactivate&amp;plugin=walking-log/walking_log.php';
        if (function_exists('wp_nonce_url')) 
          $deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_walking-log/walking_log.php');
        
        echo '  <p>' .
             __('All Walking Log data has been deleted. Click the link below to complete the ' .
                'uninstallation and deactivate the plugin.', 'wrs_walking_log') .
             "  </p>\n";
  
        echo '  <a href="' . $deactivate_url . '">' . __('Deactivate Walking Log Plugin', 'wrs_walking_log') . '</a>' . "\n";
        echo "</div>\n";

        return;
      }


      echo '  <form method="post" action="options.php">' . "\n";

      echo settings_fields('wrswl_settings_uninstall');

      echo '    <div class="wrswl-settings-section">';
      
      echo '<p>' . __("Deactivating or deleting this plugin does <strong>not</strong> delete your settings, or the " .
                      "saved exercise data. This is a safety measure to make sure you don't accidentally lose your " .
                      "walking log. If you do want to delete this data you can do so here.", 'wrs_walking_log') . '</p>' . "\n";

      echo '<span style="color:red">' .
           __('Checking <em>"Yes, I do want to uninstall"</em>, and pressing the <em>"Save Changes"</em> button ' .
              'will delete all settings and exercise data, and <strong><em>cannot</em></strong> be undone.', 'wrs_walking_log') .
           '</span></p>' . "\n";

      echo '<p>' . __('If you want to preserve your data you will need to make backups of these MySQL tables:', 'wrs_walking_log') . '</p>' . "\n";

      echo "<ul>\n";
      echo '  <li><em>' . $this->ExerciseTypeTableName . '</em></li>' . "\n";
      echo '  <li><em>' . $this->ExerciseLocationTableName . '</em></li>' . "\n";
      echo '  <li><em>' . $this->ExerciseLogTableName . '</em></li>' . "\n";
      echo "</ul>\n";

      echo '<p>' . 
           __('Once the data is deleted the plugin will also be deactivated, after which you can remove it ' .
              'from WordPress by using the <em>Plugins</em> menu. Or if you want to just reset the data and ' .
              'start over you can reactivate the plugin and it will initialize all settings with their ' .
              'default values.', 'wrs_walking_log') .
           "</p>\n";

      echo '<div id="wrswl-uninstall-confirmation-container">' . "\n";
      echo '  <label title="uninstall"><input type="checkbox" name="wrswl_settings_uninstall" value="uninstall" />' . "\n";
      echo '    <span id="wrswl-uninstall-confirmation">' . __('Yes, I do want to uninstall and delete all data, and I understand this cannot be undone.', 'wrs_walking_log') . '</span>' . "\n";
      echo "  </label><br />\n";
      echo "</div>\n";
      
      echo "</div>\n";

      echo '    <p><input type="submit" class="button-primary" value="' . __('Uninstall', 'wrs_walking_log') . '" /></p>' . "\n";
      echo "  </form>\n";
      echo "</div>\n";



    }


  }  // class wrsWalkingLogPlugin
}  // if (! class_exists('wrsWalkingLogPlugin'))



 
// create class instance - this handles registering all necessary hooks, creating
// necessary tables on plugin activation, and so on
if (class_exists('wrsWalkingLogPlugin'))
  $wrs_WalkingLogPlugin = new wrsWalkingLogPlugin();



?>
