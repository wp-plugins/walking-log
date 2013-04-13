<?php

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

if (! class_exists('wrsWalkingLogUpgrade'))
{
  class wrsWalkingLogUpgrade
  {
    static $ExerciseTypeTableName;
    static $ExerciseLocationTableName;
    static $ExerciseLogTableName;
  
    public static function UpgradePlugin($context, &$installed_ver, &$has_legacy_data)
    {
      global $wpdb;
    
      wrsWalkingLogUpgrade::$ExerciseTypeTableName = $context->ExerciseTypeTableName;
      wrsWalkingLogUpgrade::$ExerciseLocationTableName = $context->ExerciseLocationTableName;
      wrsWalkingLogUpgrade::$ExerciseLogTableName = $context->ExerciseLogTableName;
    
    
      // 1.0 -> 1.1
      if ($installed_ver == '1.0')
      {
        wrsWalkingLogUpgrade::UpgradeExerciseTypeTable_11();
        wrsWalkingLogUpgrade::UpgradeExerciseLocationTable_11();

        $installed_ver = '1.1';
        update_option("wrswl_db_version", $installed_ver);
      }
      
      // 1.1 -> 1.2
      if ($installed_ver == '1.1')
      {
        wrsWalkingLogUpgrade::UpgradeExerciseLogTable_12();
        $installed_ver = '1.2';
        update_option("wrswl_db_version", $installed_ver);
      }
      
      // 1.2 -> 1.3
      $has_legacy_data = false;
      
      if ($installed_ver == '1.2')
      {
        wrsWalkingLogUpgrade::UpgradeExerciseTypeTable_13();
        wrsWalkingLogUpgrade::UpgradeExerciseLocationTable_13();
        wrsWalkingLogUpgrade::UpgradeExerciseLogTable_13();
        $installed_ver = '1.3';
        update_option("wrswl_db_version", $installed_ver);

        
        // check for legacy data
        $sql = "
          select sum(rows) as row_count
          from (
              select count(0) as rows from " . wrsWalkingLogUpgrade::$ExerciseTypeTableName . " where scope = 0
              union all
              select count(0) as rows from " . wrsWalkingLogUpgrade::$ExerciseLocationTableName . " where scope = 0
              union all
              select count(0) as rows from " . wrsWalkingLogUpgrade::$ExerciseLogTableName . " where scope = 0
          ) a";

        $row_count = $wpdb->get_var($sql);
        $has_legacy_data = $row_count > 0;
      }
    }


    static function UpgradeExerciseLogTable_13()
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
        scope tinyint NOT NULL,
        UNIQUE KEY log_id (log_id),
        KEY user_type (wordpress_user_id, type_id),
        KEY user_location (wordpress_user_id, location_id)
      ";
      
      wrsWalkingLogUpgrade::AlterTable(wrsWalkingLogUpgrade::$ExerciseLogTableName, $sql);
      
      // note that scope will be initialized to 0, which means legacy scope - the user will need
      // to assign any legacy log data to a specific user
    }
    
    
    static function UpgradeExerciseTypeTable_13()
    {
      global $wpdb;

      $sql = "
        type_id int NOT NULL AUTO_INCREMENT, 
        wordpress_user_id int NOT NULL,
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        scope tinyint NOT NULL,
        UNIQUE KEY type_id (type_id),
        KEY user_type (wordpress_user_id, type_id)
      ";

      wrsWalkingLogUpgrade::AlterTable(wrsWalkingLogUpgrade::$ExerciseTypeTableName, $sql);
      
      // note that scope will be initialized to 0, which means legacy scope - the user will need
      // to assign any legacy log data to a specific user - however, the "Other" values will
      // always be global permanent scoped, so we need to update that value here
      $sql = $wpdb->prepare("update " . wrsWalkingLogUpgrade::$ExerciseTypeTableName . " set scope = 3 where type_id = 1");
      $wpdb->query($sql);
    }


    static function UpgradeExerciseLocationTable_13()
    {
      global $wpdb;

      $sql = "
        location_id int NOT NULL AUTO_INCREMENT,
        wordpress_user_id int NOT NULL,
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        scope tinyint NOT NULL,
        UNIQUE KEY location_id (location_id),
        KEY user_location (wordpress_user_id, location_id)
      ";

      wrsWalkingLogUpgrade::AlterTable(wrsWalkingLogUpgrade::$ExerciseLocationTableName, $sql);
      
      // note that scope will be initialized to 0, which means legacy scope - the user will need
      // to assign any legacy log data to a specific user - however, the "Other" values will
      // always be global permanent scoped, so we need to update that value here
      $sql = $wpdb->prepare("update " . wrsWalkingLogUpgrade::$ExerciseLocationTableName . " set scope = 3 where location_id = 1");
      $wpdb->query($sql);
    }
    

    static function UpgradeExerciseLogTable_12()
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
      
      wrsWalkingLogUpgrade::AlterTable(wrsWalkingLogUpgrade::$ExerciseLogTableName, $sql);
    }
    
    
    static function UpgradeExerciseTypeTable_11()
    {
      global $wpdb;

      $sql = "
        type_id int NOT NULL AUTO_INCREMENT, 
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        UNIQUE KEY type_id (type_id)
      ";

      wrsWalkingLogUpgrade::AlterTable(wrsWalkingLogUpgrade::$ExerciseTypeTableName, $sql);

      // initialize new column
      $sql = $wpdb->prepare("update wrsWalkingLogUpgrade::$ExerciseTypeTableName set visible = 1 where visible is null");
      $wpdb->query($sql);
    }


    static function UpgradeExerciseLocationTable_11()
    {
      global $wpdb;

      $sql = "
        location_id int NOT NULL AUTO_INCREMENT,
        name varchar(60) NOT NULL,
        visible tinyint NOT NULL,
        UNIQUE KEY location_id (location_id)
      ";

      wrsWalkingLogUpgrade::AlterTable(wrsWalkingLogUpgrade::$ExerciseLocationTableName, $sql);

      // initialize new column
      $sql = $wpdb->prepare("update wrsWalkingLogUpgrade::$ExerciseLocationTableName set visible = 1 where visible is null ");
      $wpdb->query($sql);
    }


    static function AlterTable($prefixed_table_name, $sql)
    {
      global $wpdb;
      $sql_create_table = "CREATE TABLE " . $prefixed_table_name . "  (  " . $sql . "  )  ;";
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql_create_table);
    }
    
    
    public static function ShowUserSearch($username)
    {
	  ?>
      <p class="half-width"><?php _e('The previous version of the Walking Log plugin didn\'t assign logs to users; there was a single log for the entire blog. ' .
           'This version has been upgraded to allow tracking a separate log for each blog user. As a result any data from the previous ' .
           'version needs to be assign to a blog user, and that\'s what you can do right here. Enter the username you want to assign ' .
           'the log to and press the <em>Search</em> button and follow the directions.', 'wrs_walking_log')?></p>
           
      <p class="info-text"><?php _e('Please note that you cannot assign the log to a user that already has a log or any exercise type or location data.', 'wrs_walking_log')?></p>
      <p class="info-text"><?php _e('Also note that the user must have edit_posts capability. All of the default WordPress roles have this capability, except Subscriber.', 'wrs_walking_log')?></p>
     
      <div style="margin-top:3em">
        <form method="post" action="<?php echo admin_url('admin.php') . '?page=wrs_walking_log_menu_upgrade'?>">
          <input type="hidden" name="action" value="search" />
          <?php wp_nonce_field();?>
          <label for="search_user_edit"><?php _e('Blog Username', 'wrs_walking_log')?></label>
          <input name="search_user_edit" type="text" value="<?php echo $username?>" />
          <input type="submit" class="button-primary" value="<?php _e('Search for User', 'wrs_walking_log')?>" />
        </form>
      </div>
	  <?php
    }

    
    
    public static function WriteAdminUpgradePage($owner)
    {
      global $wpdb;
    
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));
      
      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Upgrade', 'wrs_walking_log') . '</h2>' . "\n";
      

      // we can hide the notice now
      $owner->ClearLegacyNotice();
      
      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_upgrade';

      if (isset($_REQUEST['action']))
        $action = $_REQUEST['action'];
      else
        $action = '';
        
      switch ($action)
      {
        case 'assign':
          if (!wp_verify_nonce($_REQUEST['_wpnonce'])) wp_die('Request Failed');
          
          $username = '';
          if (isset($_REQUEST['search_user_edit']))
            $username = $_REQUEST['search_user_edit'];
          $username = htmlentities(stripslashes($username));
            
          if ($username == '')
          {
            break;
          }

            
          // if the user pressed the "change_user_button" then we want to go back to the user search form
          if (isset($_REQUEST['change_user_button']))
          {
            wrsWalkingLogUpgrade::ShowUserSearch($username);
            break;
          }
          
          
          // get user info
          $user = new WP_user($username);
          if ($user->ID == 0)
          {
            echo '<p class="error-text">' . __('This should not happen, but for some reason the user cannot be identified properly. Please try the search again.', 'wrs_walking_log') . '</p>' . "\n";
            wrsWalkingLogUpgrade::ShowUserSearch($username);
            break;
          }

          
          // update data          
          $sql = $wpdb->prepare("update " . $owner->ExerciseTypeTableName . " set wordpress_user_id = %d, scope = %d where wordpress_user_id = 0 and scope = 0", $user->ID, $owner->UserScope);
          $wpdb->query($sql);

          $sql = $wpdb->prepare("update " . $owner->ExerciseLocationTableName . " set wordpress_user_id = %d, scope = %d where wordpress_user_id = 0 and scope = 0", $user->ID, $owner->UserScope);
          $wpdb->query($sql);

          $sql = $wpdb->prepare("update " . $owner->ExerciseLogTableName . " set wordpress_user_id = %d, scope = %d where wordpress_user_id = 0 and scope = 0", $user->ID, $owner->UserScope);
          $wpdb->query($sql);
          
          // copy old global options over to user-specific settings
          $options = get_option('wrswl_settings');
          if (isset($options) && is_array($options))
          {
            update_user_meta($user->ID, $wpdb->prefix . 'wrswl_settings', $options);
            delete_option('wrswl_settings');
          }
          
          $owner->ClearLegacyNotice();
          $owner->ClearLegacyData();
      
          $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu';
      
          // printf(__('Assigned Log to %s', 'wrs_walking_log'), $user->user_login);
	  
          echo '<h3>' . __('Legacy Log Assigned', 'wrs_walking_log') . '</h3>' . "\n";
          echo '<p class="info-text half-width">'; 
          printf(__('The legacy Walking Log data has been assigned to %s.', 'wrs_walking_log'), "<em>$user->user_login</em>");
          echo ' ';
          printf(__('Follow the Usage directions in <a href="%s">General Settings</a> to configure the new user-centric log features.', 
		            'wrs_walking_log'), $admin_url) . '</p>' . "\n";
          break;
          
          
          
        case 'search':
          if (!isset($_REQUEST['_wpnonce'])) wp_die('Request Failed');
          if (!wp_verify_nonce($_REQUEST['_wpnonce'])) wp_die('Request Failed');
        
          $username = '';
          if (isset($_REQUEST['search_user_edit']))
            $username = $_REQUEST['search_user_edit'];
            
          $username = htmlentities(stripslashes($username));
            
          if ($username == '')
          {
            echo '<p class="error-text">' . __('Please enter a valid username.', 'wrs_walking_log') . '</p>' . "\n";
            wrsWalkingLogUpgrade::ShowUserSearch($username);
          }
          else
          {
            $okay = true;
            $user = new WP_user($username);
            $user_id = $user->ID;
            
            // make sure the user can access this blog
            if ($user_id != 0)
            {
            }

            // found user?
            if ($user_id == 0)
            {
              echo '<p class="error-text">';
              printf(__('Did not find %s on this blog. Please try another user.', 'wrs_walking_log'), "<em>$username</em>");
              echo '</p>' . "\n";
              wrsWalkingLogUpgrade::ShowUserSearch($username);
              break;
            }

            
            // display name
            echo '<ul style="list-style-type:square; padding-left:2em;padding-top:1em;">' . "\n";
            echo '  <li class="success-text">"';
            printf(__('%s is a valid user name.', 'wrs_walking_log'), "<em>$user->user_login</em>");
            
            $display = trim($user->first_name . ' ' . $user->last_name);
            if ($display != "")
            {
              echo ' (<em>' . $display . '</em>)';
            }
            echo ".</li>\n";
              

            // does user have edit_posts cap?
            if (!$user->has_cap('edit_posts'))
            {
              echo '  <li class="error-text">' . __('The user does not have permission to edit posts.', 'wrs_walking_log') . '</li>' . "\n";
              $okay = false;
            }
            else
            {
              echo '  <li class="success-text">' . __('The user has permission to edit posts.', 'wrs_walking_log') . '</li>' . "\n";
            }
            

            // does user have existing log data?
            $sql = "
              select sum(rows) as row_count
              from (
                  select count(0) as rows from " . $owner->ExerciseTypeTableName . " where wordpress_user_id = $user->ID
                  union all
                  select count(0) as rows from " . $owner->ExerciseLocationTableName . " where wordpress_user_id = $user->ID
                  union all
                  select count(0) as rows from " . $owner->ExerciseLogTableName . " where wordpress_user_id = $user->ID
              ) a";

              
            $row_count = $wpdb->get_var($sql);
            if ($row_count != 0)
            {
              echo '  <li class="error-text">' . __('The user already has Walking Log data.', 'wrs_walking_log') . '</li>' . "\n";
              $okay = false;
            }
            else
            {
              echo '  <li class="success-text">' . __('The user does not have any current Walking Log data.', 'wrs_walking_log') . '</li>' . "\n";
            }
            
            echo "</ul>\n";
            
            
            if (!$okay)
            {
              echo '<p class="error-text">' . __('The user failed one more more criteria and cannot accept a new Walking Log assignment.', 'wrs_walking_log') . '</p>' . "\n";
              echo '<p class="error-text half-width", half-width">' . __('If you\'re trying to assign to the currently logged in user and that user didn\'t have a log before, ' .
                                         'you may have visited the Maintenance or View Log pages before coming here, in which case ' .
                                         'some default data would have been created for Exercise Types. You can visit the Maintenance page,  ' .
                                         'delete all of the default Exercise Types, then come back here and retry the assignment.', 'wrs_walking_log') . '</p>' . "\n";
              wrsWalkingLogUpgrade::ShowUserSearch($username);
              break;
            }
            

            // if we get here then the log can be assigned to the user login, give the user a button to push to execute
            echo '<p class="info-text">' . __('The user passes all criteria and can accept a new Walking Log assignment.', 'wrs_walking_log') . '</p>' . "\n";
            echo '<p class="error-text">' . __('Assigning the legacy Walking Log data to a user is a one-time operation and is permanent. Be sure before you proceed.', 'wrs_walking_log') . '</p>' . "\n";
            
            echo '    <div style="margin-top:10px">' . "\n";
            echo '      <form method="post" action="' . $admin_url . '">' . "\n";
            echo '        <input type="hidden" name="action" value="assign" />' . "\n";
            echo '        <input type="hidden" name="search_user_edit" value=' . $user->user_login . " />\n";
            echo '        ';
              
            wp_nonce_field();
            
            echo '        <input type="submit" class="button-primary" name="assign_button" value="';

            /* translators: Assign legacy log data to a blog user */
            printf(__('Assign Log to %s', 'wrs_walking_log'), $user->user_login);
            echo '" />' . "\n";

            echo '        <input type="submit" class="button-secondary" name="change_user_button" value="' . __('Select Different User', 'wrs_walking_log') . '" />' . "\n";            
            echo "      </form>\n";
            echo "    </div>\n";
          }
           
          break;
          
        default:
          wrsWalkingLogUpgrade::ShowUserSearch('');
          break;
      }
      
      echo "</div>\n";
    }
    
  }
}

?>