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

      echo '    <p>' . __('Network Activating this plugin will activate the plugin for every blog in the network. This could take awhile if there are a lot of blogs.', 'wrs_walking_log') . "</p>\n";
      echo '    <p>' . __('Network Deactivating this plugin deactivates for every blog. This does <strong>not</strong> delete the settings or saved exercise data for any of the blogs.', 'wrs_walking_log') . "</p>\n";
      echo '    <p id="wrswl-uninstall-confirmation">' . __('Deleting the plugin on a multisite installation <strong>does not</strong> delete any settings or data. You will need to manually ' .
                   'drop the MySQL tables for each blog.', 'wrs_walking_log') . "</p>\n";
                   
      echo '    <p>' . __('If you need to delete the tables, they are all of the following form, where <em>[prefix]</em> is a WordPress defined value.', 'wrs_walking_log') . "</p>\n";
      
      echo "    <ul>\n";
      echo '      <li><em>[prefix]_exercise_type</em></li>' . "\n";
      echo '      <li><em>[prefix]_exercise_location</em></li>' . "\n";
      echo '      <li><em>[prefix]_exercise_log</em></li>' . "\n";
      echo "    </ul>\n";
      echo "  </div>\n";
      echo "</div>\n";
    }
    

    function WriteTypeEditor()
    {
      global $wpdb, $current_user;
      
      echo "\n" . '    <table class="wrswl-list-edit">' . "\n";
      
      // PHP won't expand the nested objects in the string below, so pull them out into variables
      $ExerciseTypeTableName = $this->Owner->ExerciseTypeTableName;
      $ExerciseLogTableName = $this->Owner->ExerciseLogTableName;
      $user_id = $current_user->ID;
      
      $sql = "select distinct t.type_id, t.name, t.visible, case when l.log_id is null then 0 else 1 end as locked, t.scope " .
             "from $ExerciseTypeTableName t " .
             "left outer join $ExerciseLogTableName l on l.wordpress_user_id = t.wordpress_user_id and l.type_id = t.type_id " .
             "where t.wordpress_user_id = $user_id " .
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
          /* translators: Hide a row data */
          $action = 'hide';
          $action_i18n = __('hide', 'wrs_walking_log');
        }
        else
        {
          /* translators: Show a row of data, make it visible */
          $action = 'show';
          $action_i18n = __('show', 'wrs_walking_log');
        }
        
        $url .= '&action=' . $action;
        $url = wp_nonce_url($url);
          
        echo '        <td style="width:300px;">' . $row->name . "</td>\n";
        echo '        <td>';
        
        if ($row->scope != $this->Owner->GlobalPermanentScope)
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
        
        
          /* translators: Delete a row of data */
          if ($row->scope != $this->Owner->GlobalPermanentScope)
            echo '<a href="' . $url . '">' . __('delete', 'wrs_walking_log') . '</a>';
          else
            echo '&nbsp;';
          
        }
        else
        {
          echo '&nbsp;';
        }
            
        echo "</td>\n";
        
        
        // make global/user
        echo "        <td>";

        if (current_user_can('manage_options'))
        {
          if ($row->scope == $this->Owner->UserScope)
          {
            $action = 'global';
            /* translators: Make data globally visible */
            $action_i18n = __('global', 'wrs_walking_log');
          }
          else
          {
            $action = 'user';
            /* translators: Make data visible only to the user */
            $action_i18n = __('user', 'wrs_walking_log');
          }
          
          $url .= '&action=' . $action;
          $url = wp_nonce_url($url);
            
          if ($row->scope != $this->Owner->GlobalPermanentScope)
            echo '<a href="' . $url . '">' . $action_i18n . '</a>';
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
      echo '        <input type="submit" class="button-primary" value="' . __('Add Exercise Type', 'wrs_walking_log') . '" />' . "\n";
      echo "      </form>\n";
      echo "    </div>\n";
    }
    
    
    function WriteLocationEditor()
    {
      global $wpdb, $current_user;

      echo "\n" . '    <table class="wrswl-list-edit">' . "\n";

      // PHP won't expand the nested objects in the string below, so pull them out into variables
      $ExerciseLocationTableName = $this->Owner->ExerciseLocationTableName;
      $ExerciseLogTableName = $this->Owner->ExerciseLogTableName;
      $user_id = $current_user->ID;
      
      $sql = "select distinct l.location_id, l.name, l.visible, case when o.log_id is null then 0 else 1 end as locked, l.scope " .
             "from $ExerciseLocationTableName l " .
             "left outer join $ExerciseLogTableName o on o.wordpress_user_id = l.wordpress_user_id and o.location_id = l.location_id " .
             "where l.wordpress_user_id = $user_id " .
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

        // show/hide
        if ($row->visible === '1')
        {
          $action = 'hide';
          /* translators: Hide a row of data */
          $action_i18n = __('hide', 'wrs_walking_log');
        }
        else
        {
          $action = 'show';
          /* translators: Show a row of data, make it visible */
          $action_i18n = __('show', 'wrs_walking_log');
        }
        
        $url .= '&action=' . $action;
        $url = wp_nonce_url($url);
          
        echo '        <td style="width:300px;">' . $row->name . "</td>\n";
        echo "        <td>";
        
        if ($row->scope != $this->Owner->GlobalPermanentScope)
          echo '<a href="' . $url . '">' . $action_i18n . '</a>';
        else
          echo '&nbsp;';
          
        echo "</td>\n";

        
        // delete
        echo "        <td>";
        
        if ($row->locked === '0')
        {
          $url = admin_url('admin.php?page=wrs_walking_log_menu_maintenance&location_id=' . $row->location_id);
          $url .= '&action=delete';
          $url = wp_nonce_url($url);
        
          /* translators: Delete a row of data */
          if ($row->scope != $this->Owner->GlobalPermanentScope)
            echo '<a href="' . $url . '">' . __('delete', 'wrs_walking_log') . '</a>';
          else
            echo '&nbsp;';
        }
        else
        {
          echo '&nbsp;';
        }
            
        echo "</td>\n";
        
        
        // make global/user
        echo "        <td>";

        if (current_user_can('manage_options'))
        {
          if ($row->scope == $this->Owner->UserScope)
          {
            $action = 'global';
            /* translators: Make data globally visible */
            $action_i18n = __('global', 'wrs_walking_log');
          }
          else
          {
            $action = 'user';
            /* translators: Make data visible only to the user */
            $action_i18n = __('user', 'wrs_walking_log');
          }
          
          $url .= '&action=' . $action;
          $url = wp_nonce_url($url);
            
          if ($row->scope != $this->Owner->GlobalPermanentScope)
            echo '<a href="' . $url . '">' . $action_i18n . '</a>';
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
      echo '        <input type="submit" class="button-primary" value="' . __('Add Exercise Location', 'wrs_walking_log') . '" />' . "\n";
      echo "      </form>\n";
      echo "    </div>\n";
    }
    

    
    function ValidateGeneralOptions($input)
    {
      // delete data on user delete
      if (isset($input['delete_data_on_user_delete']))
        $value = trim($input['delete_data_on_user_delete']);
      else 
        $value = 'false';
        
      if ($value !== 'true' && $value !== 'false')
        $value = 'false';

      $result['delete_data_on_user_delete'] = $value;


      // drop tables on plugin uninstall
      if (isset($input['drop_tables_on_uninstall']))
        $value = trim($input['drop_tables_on_uninstall']);
      else
        $value = 'false';
        
      if ($this->Owner->IsMultiSite)
      {
        $value = 'false';
      }
        
      if ($value !== 'true' && $value !== 'false')
        $value = 'false';

      $result['drop_tables_on_uninstall'] = $value;

      
      // allow subscriber role
      if (isset($input['allow_subscriber_role']))
        $value = trim($input['allow_subscriber_role']);
      else
        $value = 'false';
        
      if ($value !== 'true' && $value !== 'false')
        $value = 'false';

      $result['allow_subscriber_role'] = $value;
       
      
      return $result;
    }
    
    
    function ValidateGeneralSettings($input)
    {
      // time format
      $value = trim($input['wrswl_settings_time_format']);
      if ($value !== 'minutes' && $value !== 'hh:mm:ss')
        $value = 'minutes';

      $result['wrswl_settings_time_format'] = $value;


      // date format
      $value = trim($input['wrswl_settings_distance_format']);
      if ($value !== 'miles' && $value !== 'kilometers')
        $value = 'miles';

      $result['wrswl_settings_distance_format'] = $value;

      
      // privacy
      $value = trim($input['wrswl_settings_privacy']);
      if ($value != 'admin' && $value != 'user' && $value != 'author' && $value != 'public')
        $value = 'user';
        
      $result['wrswl_settings_privacy'] = $value;
      
      return $result;
    }

    
    
    function HandleGeneralUpdates()
    {
      if (!isset($_REQUEST['action'])) return false;
      if (!wp_verify_nonce($_REQUEST['_wpnonce'])) wp_die('Request Failed');

      $action = $_REQUEST['action'];
      
      // validate action
      if ($action != 'save_options') wp_die('Request failed');
     
      switch ($action)
      {
        case 'save_options':
          // validate options if user can manage them
          if (current_user_can('manage_options'))
          {
            if (isset($_REQUEST['wrswl_options']))
              $value = $_REQUEST['wrswl_options'];
            else
              $value = '';

            $options = $this->ValidateGeneralOptions($value);
          }
              
              
          // validate settings
          $settings = $this->ValidateGeneralSettings($_REQUEST['wrswl_settings']);


          // save options if user can manage them          
          if (current_user_can('manage_options'))
          {
            $this->Owner->SaveNewOptions($options);
          }
          
          // save settings
          $this->Owner->SaveNewSettings($settings);
        
        break;
      }
	  
	  return true;
    }

    
    function WriteAdminGeneralPage()
    {
      if (!$this->Owner->HasAdminAccess())
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));

      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu';
      ?>      
      
      <div class="wrap">
        <div class="icon32" id="icon-options-general"><br></div>
        <h2><?php _e('Walking Log: General Settings', 'wrs_walking_log')?></h2>
        <?php // settings_errors(); ?>
        <?php $this->HandleGeneralUpdates(); ?>
    
        <form method="post" action="<?php echo $admin_url; ?>">
          <input type="hidden" name="action" value="save_options" />
          <?php wp_nonce_field(); ?>
    
          <h3><?php _e('Usage', 'wrs_walking_log')?></h3>
          <div class="wrswl-settings-section">
            <p class="half-width"><?php printf(__('You can view and edit your log by using <em>View Your Log</em> in the admin menu.', 'wrs_walking_log'))?></p>
            <?php if (current_user_can('edit_posts')) { ?><p class="half-width"><?php printf(__('To display walking logs elsewhere you need to include this short code somewhere in a page: %s. ' .
                                                  'When visiting the page, the current user will always see their own log and be able to edit it there.', 'wrs_walking_log'), '<strong>[wrs_walking_log view="main"]</strong>')?></p>
            <p class="half-width"><?php printf(__('You can also show a specific user\'s log by including the user name in the short code: %s. Only the specified user will ' .
                                                  'be able to edit the log there, and everyone else will be able to view the log according to the user\'s privacy settings.', 'wrs_walking_log'), '<strong>[wrs_walking_log view="main" user="dave"]</strong>')?></p>
            <p class="half-width"><?php printf(__('You must also add a custom field with a name of %s with a value of <strong>1</strong> to each page where you add ' .
                                                  'the short code. This is required so the plugin can load itself only on pages where it\'s needed.', 'wrs_walking_log'), '<strong>wrs_walking_log</strong>')?></p>
            <p class="half-width"><?php printf(__('Finally, only a single short code is allowed for each page.', 'wrs_walking_log'))?></p>
            <?php } else if (current_user_can('read')) { ?><p class="half-width"><?php printf(__('An administrator may have also created a page where you can view and edit your log. ' .
                                                  'When viewing that page your log is always visible to you, and you can control who else can see your log using the privacy settings below.', 'wrs_walking_log')); ?></p>
            <?php } ?>
          </div>
          
         
          <h3><?php _e('General Settings', 'wrs_walking_log')?></h3>
          <div class="wrswl-settings-section">
          
            <table class="form-table">
              <tr valign="top">
                <th scope="row"><?php _e('Exercise Time Display Format', 'wrs_walking_log')?></th>
                <td>
                  <label title="minutes">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_time_format'] === "minutes") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="minutes" <?php echo $checked_value; ?> />
                    
                    <?php /* translators: Exercise time display formatted in number of mintes */ ?>
                    <span class="wrswl-settings-label"><?php _e('Minutes - 496.38', 'wrs_walking_log')?></span>
                  </label><br />
                  <label title="hh:mm:ss">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_time_format'] === "hh:mm:ss") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_time_format]" value="hh:mm:ss" <?php echo $checked_value; ?> />
                    
                    <?php /* translators: Exercise time display formatted in hours:minutes:seconds */ ?>
                    <span class="wrswl-settings-label"><?php _e('hhh:mm:ss - 8:16:23', 'wrs_walking_log')?></span>
                  </label><br />
                </td>
              </tr>
              
              <tr valign="top">
                <th scope="row"><?php _e('Distance Display Format', 'wrs_walking_log')?></th>
                <td>
                  <label title="miles">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_distance_format'] === "miles") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_distance_format]" value="miles" <?php echo $checked_value; ?> />
                    
                    <?php /* translators: Distance display formatted as miles */ ?>
                    <span class="wrswl-settings-label"><?php _e('Miles', 'wrs_walking_log')?></span>
                  </label><br />
                  <label title="kilometers">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_distance_format'] === "kilometers") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_distance_format]" value="kilometers" <?php echo $checked_value; ?> />
                    
                    <?php /* translators: Distance display formatted as kilometers */ ?>
                    <span class="wrswl-settings-label"><?php _e('Kilometers', 'wrs_walking_log')?></span>
                  </label><br />
                </td>
              </tr>
              
              <tr valign="top">
                <th scope="row"><?php _e('Privacy', 'wrs_walking_log')?> </th>
                <td>
                  <label title="admin">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_privacy'] === "admin") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="admin" <?php echo $checked_value; ?> />
                    <span class="wrswl-settings-label"><?php _e('Visible only to blog administrators', 'wrs_walking_log') ?></span>
                  </label><br />
                  <label title="user">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_privacy'] === "user") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="user" <?php echo $checked_value; ?> />
                    <span class="wrswl-settings-label"><?php _e('Visible only to logged in users', 'wrs_walking_log') ?></span>
                  </label><br />
                  <label title="author">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_privacy'] === "author") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="author" <?php echo $checked_value; ?> />
                    <span class="wrswl-settings-label"><?php _e('Visible only to the author of the page or post hosting the walking log', 'wrs_walking_log') ?> </span>
                  </label><br />
                  <label title="public">
                    <?php $checked_value = ($this->Owner->Settings['wrswl_settings_privacy'] === "public") ? 'checked="checked"' : ''; ?>
                    <input type="radio" name="wrswl_settings[wrswl_settings_privacy]" value="public" <?php echo $checked_value; ?> />
                    <span class="wrswl-settings-label"><?php _e('Visible to everyone', 'wrs_walking_log') ?></span>
                  </label><br />
                </td>
              </tr>
            </table>
          </div>
          
          <?php if (current_user_can('manage_options')) { ?>
          
          <h3><?php _e('Global Settings', 'wrs_walking_log')?></h3>
          <div class="wrswl-settings-section">
          
            <table class="form-table">
            
              <tr valign="top">
                <th scope="row"><?php _e('Subscriber Role', 'wrs_walking_log')?></th>
                <td>
                  <label title="delete_data_on_user_delete">
                    <?php $checked_value = ($this->Owner->Options['allow_subscriber_role'] === "true") ? 'checked="checked"' : ''; ?>
                    <input type="checkbox" name="wrswl_options[allow_subscriber_role]" value="true" <?php echo $checked_value; ?> />
                    <span class="wrswl-settings-label"><?php _e('Allow logs for Subscriber role users', 'wrs_walking_log')?></span>
                  </label><br />
                </td>
              </tr>
            
              <tr valign="top">
                <th scope="row"><?php _e('User Log Data', 'wrs_walking_log')?></th>
                <td>
                  <label title="delete_data_on_user_delete">
                    <?php $checked_value = ($this->Owner->Options['delete_data_on_user_delete'] === "true") ? 'checked="checked"' : ''; ?>
                    <input type="checkbox" name="wrswl_options[delete_data_on_user_delete]" value="true" <?php echo $checked_value; ?> />
                    <span class="wrswl-settings-label"><?php _e('Delete a user\'s Walking Log data when the user is deleted', 'wrs_walking_log')?></span>
                  </label><br />
                </td>
              </tr>

              <?php if (!$this->Owner->IsMultiSite) { ?>
              
              <tr valign="top">
                <th scope="row"><?php _e('Log Tables', 'wrs_walking_log')?></th>
                <td>
                  <label title="drop_tables_on_uinstall">
                    <?php $checked_value = ($this->Owner->Options['drop_tables_on_uninstall'] === "true") ? 'checked="checked"' : ''; ?> 
                    <input type="checkbox" name="wrswl_options[drop_tables_on_uninstall]" value="true" <?php echo $checked_value; ?> />
                    
                    <?php if ($this->Owner->IsMultiSite) { ?>
                      <span class="wrswl-settings-label"><?php _e('Drop all Walking Log tables when the plugin is uninstalled. This may take awhile there is a large number of blogs', 'wrs_walking_log')?></span>
                    <?php } else { ?>
                      <span class="wrswl-settings-label"><?php _e('Drop all Walking Log tables when the plugin is uninstalled', 'wrs_walking_log')?></span>
                    <?php } ?>
                  </label><br />
                </td>
              </tr>
              
              <?php } ?>
              
            </table>
          </div>
          <?php } ?>

          <p><input type="submit" class="button-primary" value="<?php _e('Save Changes', 'wrs_walking_log')?>" /></p>
        </form>
      </div>
      
      <?php
    }
    
    
    function WriteAdminMaintenancePage()
    {
      if (!$this->Owner->HasAdminAccess())
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));

        
      // make sure the user has the initial default values if this is the first time
      $this->Owner->CheckDefaults();
      
      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: Maintenance', 'wrs_walking_log') . '</h2>' . "\n";
      
      $this->Owner->HandleUpdates();

      $admin_url = admin_url('admin.php') . '?page=wrs_walking_log_menu_maintenance';

      echo '  <p class="half-width">' .
       __('You can only delete items if they are unused. You can hide an item to prevent it from being selected in the future, ' .
            'however the value will still display in the log for any items using it. If an item is not used in any log entries ' .
            'it can be deleted.', 'wrs_walking_log') . '</p>';


      if (current_user_can('manage_options'))
      {
        echo '  <p class="half-width">' .
         __('Administrators can make any of their items globally available to all users by toggling the <em>global/user</em> link. ' .
              'Be careful with this option since it\'s possible to cause duplicates to appear when a user already has an item ' .
              'with the same name.', 'wrs_walking_log') . '</p>';
              
      }
      
      /// test activation /////      
      // echo '<div style="margin-top:10px">';
      // echo '<form method="post" action="' . $admin_url . '">' . "\n";
      // echo '  <input type="hidden" name="action" value="test_activation" />';
      // wp_nonce_field();
      // echo '  <input type="submit" class="button-primary" value="' . __('Test Activation', 'wrs_walking_log') . '" />' . "\n";
      // echo '</form>';
      // echo '</div>';
      
      
      // exercise locations      
      echo '  <h3>' . __('Exercise Locations', 'wrs_walking_log') . "</h3>\n";
      echo '  <div class="wrswl-settings-section">' . "\n";
      
      $this->WriteLocationEditor();
      
      echo "  </div>\n";
      
      
      // exercise types
      echo '  <h3>' . __('Exercise Types', 'wrs_walking_log') . "</h3>\n";
      echo '  <div class="wrswl-settings-section">' . "\n";

      $this->WriteTypeEditor();
      
      echo "  </div>\n";
      echo "</div>\n";
    }
    
    
    function WriteAdminUninstallPage()
    {
      if (!current_user_can('manage_options')) 
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));

      ?>
      <div class="wrap">
        <div class="icon32" id="icon-options-general"><br></div>
        <h2><?php _e('Walking Log: Uninstalling', 'wrs_walking_log');?></h2>
        <div class="wrswl-settings-section">
          <p><?php _e('Deactivating this plugin does <strong>not</strong> delete your settings or the saved exercise data.', 'wrs_walking_log')?></p>
          <p id="wrswl-uninstall-confirmation"><?php _e('However, deleting the plugin <strong>does</strong> delete your settings and data if you\'ve elected ' .
                                                        'to drop the tables by checking the appropriate option in Global Settings.', 'wrs_walking_log')?></p>
          <p><?php _e('If you want to delete the plugin and preserve your data you will need to make backups of these MySQL tables:', 'wrs_walking_log')?></p>

          <ul>
            <li><em><?php echo $this->Owner->ExerciseTypeTableName;?></em></li>
            <li><em><?php echo $this->Owner->ExerciseLocationTableName?></em></li>
            <li><em><?php echo $this->Owner->ExerciseLogTableName?></em></li>
          </ul>
        </div>
	    </div>
	  <?php
    }

    
    function WriteAdminWalkingLogPage()
    {
      if (!$this->Owner->HasAdminAccess())
        wp_die(__('You do not have sufficient permissions to access this page.', 'wrs_walking_log'));
    
      echo '<div class="wrap">' . "\n";
      echo '  <div class="icon32" id="icon-options-general"><br></div>' . "\n";
      echo '  <h2>' . __('Walking Log: View', 'wrs_walking_log') . '</h2>' . "\n";
      echo '<br /><br />';

      $this->Owner->ShowMainView(true, $this->Owner->CurrentUserLogin);
      
      echo "</div>\n";
    }
    

    function WriteAdminPage()
    {
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

        case 'wrs_walking_log_menu_upgrade':
          require_once('walking_log_upgrade.php');
          wrsWalkingLogUpgrade::WriteAdminUpgradePage($this->Owner);
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
      // determine minimum required capability
      $capability = 'edit_posts';
      if ($this->Owner->Options['allow_subscriber_role'] === "true")
        $capability = 'read';
    
    
      // administration menu
      add_menu_page(__('Walking Log', 'wrs_walking_log'),
                    __('Walking Log', 'wrs_walking_log'),
                    $capability,
                    'wrs_walking_log_menu',
                    array(&$this, 'WriteAdminPage'));
                    
      // general settings
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: General settings */
                       __('General', 'wrs_walking_log'),
                       __('General', 'wrs_walking_log'),
                       $capability,
                       'wrs_walking_log_menu',
                       array(&$this, 'WriteAdminPage'));


      // types and locations editing
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: Maintenance settings - add exercise types and locations */
                       __('Maintenance', 'wrs_walking_log'),
                       __('Maintenance', 'wrs_walking_log'),
                       $capability,
                       'wrs_walking_log_menu_maintenance',
                       array(&$this, 'WriteAdminPage'));

      // view and edit the walking log
      add_submenu_page('wrs_walking_log_menu',
                       /* translators: View and edit the walking log */
                       __('View Your Log', 'wrs_walking_log'),
                       __('View Your Log', 'wrs_walking_log'),
                       $capability,
                       'wrs_walking_log_menu_view',
                       array(&$this, 'WriteAdminPage'));
                       

      // if we have legacy data then display user assignment
      if ($this->Owner->HasLegacyData)
      {
        add_submenu_page('wrs_walking_log_menu',
                         __('Upgrade', 'wrs_walking_log'),
                         __('Upgrade', 'wrs_walking_log'),
                         'manage_options',
                         'wrs_walking_log_menu_upgrade',
                         array(&$this, 'WriteAdminPage'));
      }
                       
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
      // hide legacy data notice if requested
      if (isset($_GET['wrswl_legacy_notice_dismiss']) && $_GET['wrswl_legacy_notice_dismiss'] == '0')
      {
        delete_option('wrswl_legacy_notice');
      }
    }
  }
}

?>