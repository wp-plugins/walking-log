<?php

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

if (! class_exists('wrsWalkingLogAdmin'))
{
  class wrsWalkingLogAdmin
  {
    var $Owner;
  
    function wrsWalkingLogAdmin($owner)
    {
      $this->Owner = $owner;
    }

    
    function WriteNetworkAdminUninstallPage()
    {
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));


      echo "\n" . '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: General Information', 'wrs_walking_log') . '</h2>' . "\n";
      echo '  <div class="wrswl-settings-section">' . "\n";

      echo '    <p>' . __('Network Activating this plugin will activate the plugin for every blog in the network. This could take awhile if there are a lot of blogs.') . "</p>\n";
      echo '    <p>' . __('Network Deactivating this plugin deactivates for every blog. This does <strong>not</strong> delete the settings or saved exercise data for any of the blogs.') . "</p>\n";
      echo '    <p id="wrswl-uninstall-confirmation">' . __('Deleting the plugin <strong>does not</strong> delete any settings or data. You will need to manually ' .
                   'drop the MySQL tables for each blog.') . "</p>\n";
                   
      echo '    <p>' . __('If you need to delete the tables, they are all of the following form, where <em>[prefix]</em> is a WordPress defined value.', 'wrs_walking_log') . "</p>\n";
      
      echo "    <ul>\n";
      echo '      <li><em>[prefix]_exercise_type</em></li>' . "\n";
      echo '      <li><em>[prefix]_exercise_location</em></li>' . "\n";
      echo '      <li><em>[prefix]_exercise_log</em></li>' . "\n";
      echo "    </ul>\n";

      echo "  </div>\n";
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
    
    
    function WriteTypeEditor()
    {
      global $wpdb;
      
      echo "\n" . '    <table class="wrswl-list-edit">' . "\n";
      
      // PHP won't expand the nested objects in the string below, so pull them out into variables
      $ExerciseTypeTableName = $this->Owner->ExerciseTypeTableName;
      $ExerciseLogTableName = $this->Owner->ExerciseLogTableName;
      
      $sql = "select distinct t.type_id, t.name, t.visible, case when l.log_id is null then 0 else 1 end as locked " .
             "from $ExerciseTypeTableName t " .
             "left outer join $ExerciseLogTableName l on l.type_id = t.type_id " .
             "order by t.type_id";
      
      $rows = $wpdb->get_results($sql);

      $odd = true;
      foreach ($rows as $row)
      {
        if ($odd)
          $class = ' class="odd"';
        else
          $class = '';
          
        echo "      <tr$class>\n";

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
          
        echo '        <td style="width:300px;">' . $row->name . "</td>\n";
        echo '        <td>';
        
        if ($row->type_id != 1)
          echo '<a href="' . $url . '">' . $action_i18n . '</a>';
        else
          echo '&nbsp;';
        
        echo '</td>' . "\n";
        
        
        echo "        <td>";
        
        if ($row->locked === '0')
        {
          $url = admin_url('admin.php?page=wrs_walking_log_menu_maintenance&type_id=' . $row->type_id);
          $url .= '&action=delete';
          $url = wp_nonce_url($url);
        
          if ($row->type_id != 1)
            echo '<a href="' . $url . '">' . __('delete', 'wrs_walking_log') . '</a>';
          else
            echo '&nbsp;';
          
        }
        else
        {
          echo '&nbsp;';
        }
            
        echo "</td>\n";
        echo "      </tr>\n";
      }

      echo "    </table>\n\n";
      
      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_maintenance';
      
      echo '    <div style="margin-top:10px">' . "\n";
      echo '      <form method="post" action="' . $admin_url . '">' . "\n";
      echo '        <input type="hidden" name="action" value="add_type" />' . "\n";
      
      echo '        ';
      
      wp_nonce_field();
      
      echo '        <input name="add_type_edit" type="text" />' . "\n";
      echo '        <input type="submit" class="button-primary" value="' . __('Add Type', 'wrs_walking_log') . '" />' . "\n";
      echo "      </form>\n";
      echo "    </div>\n";
    }
    
    
    function WriteLocationEditor()
    {
      global $wpdb;

      echo "\n" . '    <table class="wrswl-list-edit">' . "\n";

      // PHP won't expand the nested objects in the string below, so pull them out into variables
      $ExerciseLocationTableName = $this->Owner->ExerciseLocationTableName;
      $ExerciseLogTableName = $this->Owner->ExerciseLogTableName;
      
      $sql = "select distinct l.location_id, l.name, l.visible, case when o.log_id is null then 0 else 1 end as locked " .
             "from $ExerciseLocationTableName l " .
             "left outer join $ExerciseLogTableName o on o.location_id = l.location_id " .
             "order by l.location_id";

      $rows = $wpdb->get_results($sql);

      $odd = true;
      foreach ($rows as $row)
      {
        if ($odd)
          $class = ' class="odd"';
        else
          $class = '';
          
        echo "      <tr$class>\n";

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
          
        echo '        <td style="width:300px;">' . $row->name . "</td>\n";
        echo "        <td>";
        
        if ($row->location_id != 1)
          echo '<a href="' . $url . '">' . $action_i18n . '</a>';
        else
          echo '&nbsp;';
          
        echo "</td>\n";

        
        echo "        <td>";
        
        if ($row->locked === '0')
        {
          $url = admin_url('admin.php?page=wrs_walking_log_menu_maintenance&location_id=' . $row->location_id);
          $url .= '&action=delete';
          $url = wp_nonce_url($url);
        
          if ($row->location_id != 1)
            echo '<a href="' . $url . '">' . __('delete', 'wrs_walking_log') . '</a>';
          else
            echo '&nbsp;';
          
        }
        else
        {
          echo '&nbsp;';
        }
            
        echo "</td>\n";
        echo "      </tr>\n";
      }

      echo "    </table>\n\n";
      
      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_maintenance';
      
      echo '    <div style="margin-top:10px">' . "\n";
      echo '      <form method="post" action="' . $admin_url . '">' . "\n";
      echo '        <input type="hidden" name="action" value="add_location" />' . "\n";
      
      echo '        ';
      
      wp_nonce_field();
      
      echo '        <input name="add_location_edit" type="text" />' . "\n";
      echo '        <input type="submit" class="button-primary" value="' . __('Add Location', 'wrs_walking_log') . '" />' . "\n";
      echo "      </form>\n";
      echo "    </div>\n";
    }
    
    
    function WriteAdminGeneralPage()
    {
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));

      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: General Settings', 'wrs_walking_log') . '</h2>' . "\n";

      echo '  <form method="post" action="options.php">' . "\n";
      settings_errors();
      echo settings_fields('wrswl_settings_group');
      echo do_settings_sections('wrswl_walking_log');

      echo '    <p><input type="submit" class="button-primary" value="' . __('Save Changes', 'wrs_walking_log') . '" /></p>' . "\n";
      echo "  </form>\n";
      echo "</div>\n";
    }
    
    
    function WriteAdminMaintenancePage()
    {
      global $wpdb;

      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Maintenance', 'wrs_walking_log') . '</h2>' . "\n";
      
      $this->Owner->HandleUpdates();

      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_maintenance';

      echo '  <p>You can only delete items if they are unused. You can hide an item to prevent it from being selected in the future, ' .
              'however the value will still display in the log for any items using it. If an item is not used in any log entries ' .
              'it can be deleted.</p>';
      
      ///// test network activation /////      
      // echo '<div style="margin-top:10px">';
      // echo '<form method="post" action="' . $admin_url . '">' . "\n";
      // echo '  <input type="hidden" name="action" value="test_activation" />';
      // wp_nonce_field();
      // echo '  <input type="submit" class="button-primary" value="' . __('Test Activation', 'wrs_walking_log') . '" />' . "\n";
      // echo '</form>';
      // echo '</div>';
      
      
      // exercise locations      
      echo "  <h3>Exercise Locations</h3>\n";
      echo '  <div class="wrswl-settings-section">';
      
      $this->WriteLocationEditor();
      
      echo '  </div>';
      
      
      // exercise types
      echo "  <h3>Exercise Types</h3>\n";
      echo '  <div class="wrswl-settings-section">';

      $this->WriteTypeEditor();
      
      echo '  </div>';
      echo "</div>\n";
    }
    
    
    function WriteAdminUninstallPage()
    {
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));


      echo "\n" . '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Uninstalling', 'wrs_walking_log') . '</h2>' . "\n";
      echo '  <div class="wrswl-settings-section">' . "\n";

      echo '    <p>' . __('Deactivating this plugin does <strong>not</strong> delete your settings or the saved exercise data.') . "</p>\n";
      echo '    <p id="wrswl-uninstall-confirmation">' . __('However, deleting the plugin <strong>does</strong> delete your settings and data.') . "</p>\n";
      echo '    <p>' . __('If you want to delete the plugin and preserve your data you will need to make backups of these MySQL tables:', 'wrs_walking_log') . "</p>\n";
      

      echo "    <ul>\n";
      echo '      <li><em>' . $this->Owner->ExerciseTypeTableName . '</em></li>' . "\n";
      echo '      <li><em>' . $this->Owner->ExerciseLocationTableName . '</em></li>' . "\n";
      echo '      <li><em>' . $this->Owner->ExerciseLogTableName . '</em></li>' . "\n";
      echo "    </ul>\n";

      echo "  </div>\n";
      echo "</div>\n";
    }

    
    function WriteAdminWalkingLogPage()
    {
      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: View', 'wrs_walking_log') . '</h2>' . "\n";
      echo '<br /><br />';
      
      $this->Owner->ShowMainView(true);
      
      echo "</div>\n";
    }
    

    // usage settings
    function WriteUsageSettingsSection()
    {
      echo '<div class="wrswl-settings-section">';

      echo '<p>';
      printf(__('You can view and edit the log by using View Log in the admin menu.', 'wrs_walking_log'), '<strong>[wrs_walking_log view="main"]</strong>');
      echo "</p>\n";
      
      echo '<p>';
      printf(__('To display the walking log elsewhere you need to include this short code somewhere in a page: %s', 'wrs_walking_log'), '<strong>[wrs_walking_log view="main"]</strong>');
      echo "</p>\n";

      echo "<p>";
      printf(__("You must also add a custom field with a name of %s, and a value of anything to each page where you add " .
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
      $checked_value = ($this->Owner->Options['wrswl_settings_time_format'] === "minutes") ? 'checked="checked"' : '';
      echo '<label title="minutes"><input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="minutes" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Minutes - 496.38', 'wrs_walking_log') . '</span></label><br />';

      //$checked_value = ($this->Options['wrswl_settings_time_format'] === "hours") ? 'checked="checked"' : '';
      //echo '<label title="hours"><input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="hours" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Hours - 8.28', 'wrs_walking_log') . '</span></label><br />';

      $checked_value = ($this->Owner->Options['wrswl_settings_time_format'] === "hh:mm:ss") ? 'checked="checked"' : '';
      echo '<label title="hh:mm:ss"><input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="hh:mm:ss" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('hhh:mm:ss - 8:16:23', 'wrs_walking_log') . '</span></label><br />';
    }


    function WriteSettingDistanceFormat()
    {
      $checked_value = ($this->Owner->Options['wrswl_settings_distance_format'] === "miles") ? 'checked="checked"' : '';
      echo '<label title="miles"><input type="radio" name="wrswl_settings[wrswl_settings_distance_format]" value="miles" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Miles', 'wrs_walking_log') . '</span></label><br />';

      $checked_value = ($this->Owner->Options['wrswl_settings_distance_format'] === "kilometers") ? 'checked="checked"' : '';
      echo '<label title="kilometers"><input type="radio" name="wrswl_settings[wrswl_settings_distance_format]" value="kilometers" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Kilometers', 'wrs_walking_log') . '</span></label><br />';

      echo "\n</div>\n";
    }

    
    function WriteSettingPrivacy()
    {
      $checked_value = ($this->Owner->Options['wrswl_settings_privacy'] === "admin") ? 'checked="checked"' : '';
      echo '<label title="admin"><input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="admin" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Visible only to blog administrators', 'wrs_walking_log') . '</span></label><br />';

      $checked_value = ($this->Owner->Options['wrswl_settings_privacy'] === "user") ? 'checked="checked"' : '';
      echo '<label title="user"><input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="user" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Visible only to logged in users', 'wrs_walking_log') . '</span></label><br />';
      
      $checked_value = ($this->Owner->Options['wrswl_settings_privacy'] === "author") ? 'checked="checked"' : '';
      echo '<label title="author"><input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="author" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Visible only to the author of the page or post hosting the walking log', 'wrs_walking_log') . '</span></label><br />';

      $checked_value = ($this->Owner->Options['wrswl_settings_privacy'] === "public") ? 'checked="checked"' : '';
      echo '<label title="public"><input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="public" ' . $checked_value . '/><span class="wrswl-settings-label">' . __('Visible to everyone', 'wrs_walking_log') . '</span></label><br />';
      
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


    function ValidateGeneralSettings($input)
    {

      // time format
      $value = trim($input['wrswl_settings_time_format']);
      if ($value !== 'minutes' /*&& $value !== 'hours'*/ && $value !== 'hh:mm:ss')
        $value = 'minutes';

      $this->Owner->Options['wrswl_settings_time_format'] = $value;


      // date format
      $value = trim($input['wrswl_settings_distance_format']);
      if ($Value !== 'miles' && $value !== 'kilometers')
        $value = 'miles';

      $this->Owner->Options['wrswl_settings_distance_format'] = $value;

      
      // privacy
      $value = trim($input['wrswl_settings_privacy']);
      if ($value != 'admin' && $value != 'user' && $value != 'author' && $value != 'public')
        $value = 'user';
        
      $this->Owner->Options['wrswl_settings_privacy'] = $value;
      
      return $this->Owner->Options;
    }

    
    function ValidateUninstallSettings($input)
    {
      // uninstall request
      $value = $input;
      
      if ($value != 'no' && $value != 'uninstall' && $value != 'uninstalled')
        $value = 'no';
      
      $this->Owner->UninstallRequest = $value;
      
      return $this->Owner->UninstallRequest;
    }
    
    
    function WriteAdminPage()
    {
      if ($this->Owner->UninstallRequest === "uninstalled")
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
          
        case 'wrs_walking_log_menu_view':
          $this->WriteAdminWalkingLogPage();
          break;

        case 'wrs_walking_log_menu':
        default:          
          $this->WriteAdminGeneralPage();
          break;
      }
    }


    // network admin functionality
    function NetworkAdminMenu()
    {
      // general settings
      add_menu_page(__('Walking Log', 'wrs_walking_log'),
                    __('Walking Log', 'wrs_walking_log'),
                    'manage_options',
                    'wrs_walking_log_network_menu',
                    array(&$this, 'WriteNetworkAdminPage'));
                    
      add_submenu_page('wrs_walking_log_admin_menu',
                       /* translators: Uninstall the plugin */
                       __('General', 'wrs_walking_log'),
                       __('General', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_network_menu',
                       array(&$this, 'WriteNetworkAdminPage'));
    }
    
    
    // admin functionality
    function AdminMenu()
    {
      // administration menu
      add_menu_page(__('Walking Log', 'wrs_walking_log'),
                    __('Walking Log', 'wrs_walking_log'),
                    'manage_options',
                    'wrs_walking_log_menu',
                    array(&$this, 'WriteAdminPage'));
                    
      // general settings
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: General settings */
                       __('General', 'wrs_walking_log'),
                       __('General', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_menu',
                       array(&$this, 'WriteAdminPage'));


      // types and locations editing
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: Maintenance settings - add exercise types and locations */
                       __('Maintenance', 'wrs_walking_log'),
                       __('Maintenance', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_menu_maintenance',
                       array(&$this, 'WriteAdminPage'));

      // view and edit the walking log
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: View and edit the walking log */
                       __('View Log', 'wrs_walking_log'),
                       __('View Log', 'wrs_walking_log'),
                       'manage_options',
                       'wrs_walking_log_menu_view',
                       array(&$this, 'WriteAdminPage'));
                       
      // uninstall
      if (!$this->Owner->IsMultiSite)
      {
        add_submenu_page('wrs_walking_log_menu',
                         /* translators: Notes on uninstalling the plugin */
                         __('Uninstalling', 'wrs_walking_log'),
                         __('Uninstalling', 'wrs_walking_log'),
                         'manage_options',
                         'wrs_walking_log_menu_uninstall',
                         array(&$this, 'WriteAdminPage'));
      }

    }


    function WriteNetworkAdminPage()
    {
      switch ($_GET['page'])
      {
        case 'wrs_walking_log_network_menu':
        default:
          $this->WriteNetworkAdminUninstallPage();
          break;
      }
    }

    
    function AdminInit()
    {
      register_setting('wrswl_settings_group', 'wrswl_settings', array(&$this, 'ValidateGeneralSettings'));
      register_setting('wrswl_settings_uninstall', 'wrswl_settings_uninstall', array(&$this, 'ValidateUninstallSettings'));
 
      if (!isset($_GET['page'])) return;

      if ($_GET['page'] === 'wrs_walking_log_menu')
      {
        // usage
        add_settings_section('wrswl_settings_usage', __('Usage', 'wrs_walking_log'), array(&$this, 'WriteUsageSettingsSection'), 'wrswl_walking_log');

        // general settings
        add_settings_section('wrswl_settings_general', __('General Settings', 'wrs_walking_log'), array(&$this, 'WriteGeneralSettingsSection'), 'wrswl_walking_log');
        add_settings_field('wrswl_settings_time_format', __('Exercise Time Display Format', 'wrs_walking_log'), array(&$this, 'WriteSettingTimeFormat'), 'wrswl_walking_log', 'wrswl_settings_general');
        add_settings_field('wrswl_settings_distance_format', __('Distance Display Format', 'wrs_walking_log'), array(&$this, 'WriteSettingDistanceFormat'), 'wrswl_walking_log', 'wrswl_settings_general');
        add_settings_field('wrswl_settings_privacy', __('Privacy', 'wrs_walking_log'), array(&$this, 'WriteSettingPrivacy'), 'wrswl_walking_log', 'wrswl_settings_general');        
      }
      
      else if ($_GET['page'] === 'wrs_walking_log_menu_uninstall')
      {
        // uninstall
        add_settings_section('wrswl_settings_uninstall', __('Uninstall Walking Log', 'wrs_walking_log'), array(&$this, 'WriteUninstallSettingsSection'), 'wrswl_walking_log');
        add_settings_field('wrswl_settings_uninstall', '', array(&$this, 'WriteSettingUninstall'), 'wrswl_walking_log', 'wrswl_settings_uninstall');
      }
    }
  }
}

?>
