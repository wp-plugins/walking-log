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

?>

          <h3><?php _e('Usage Overview', 'wrs-walking-log')?></h3>
          <div class="wrswl-settings-section">
            <p class="half-width"><?php printf(__('View and edit your log by clicking <em>View Your Log</em> in the admin menu. ' .
                                                  'View log statistics by clicking <em>View Your Stats</em> in the main menu. ' .
                                                  'Add new exercise types and locations by clicking <em>Maintenance</em>.', 'wrs-walking-log'))?></p>
            
            <?php if (current_user_can('edit_posts')) { ?>

              <p class="half-width"><?php printf(__('To display walking logs, stats, or ranking elsewhere you will need to include a short code on the page or ' .
                                                    'post where you want it to display. These short codes are described in the following sections, along ' .
                                                    'with various parameters you can pass to control what gets displayed.', 'wrs-walking-log'))?></p> 
              <p class="half-width"><?php printf(__('You must also add a custom field with a name of %s with a value of <strong>1</strong> to each page where you add ' .
                                                    'the short code. This is required so the plugin can load itself only on pages where it\'s needed.', 'wrs-walking-log'), 
                                                    '<strong>wrs_walking_log</strong>')?></p>
          
            <?php } else if (current_user_can('read')) {  ?>
            
              <p class="half-width"><?php printf(__('An administrator may have also created a page where you can view and edit your log, or see reports on your stats. ' .
                                                    'When viewing that page your log is always visible to you, and you can control who else can see your log using the ' .
                                                    'privacy settings in the <em>General</em> settings menu.', 'wrs-walking-log')); ?></p>
            <?php } ?>
          </div>
            
          
          <?php if (current_user_can('edit_posts')) { ?>
          <h3><?php _e('Log View Short Code', 'wrs-walking-log')?></h3>
          <div class="wrswl-settings-section">
            <p class="half-width"><?php printf(__('Use this short code to display a walking log for the user that is currently signed in: %s. ' .
                                                  'The signed in user will see their own log when visiting the page. If the user only has subscriber permissions ' .
                                                  'and the option for disabling subscriber logs is checked then that user will receive a message stating that they ' .
                                                  'have no log. Similarly if no user is logged on they will receive that same message.', 'wrs-walking-log'), 
                                                  '<strong>[wrs_walking_log view="main"]</strong>') ?></p>
            
            <p class="half-width"><?php printf(__('You can also show a specific user\'s log by including the user name in the short code: %s. Only the specified user will ' .
                                                  'be able to edit the log there, and everyone else will be able to view the log according to the user\'s privacy settings. ' .  
                                                  'Note that if you want the log to be displayed for visitors who aren\'t logged in you must use this form of the short code.',
                                                  'wrs-walking-log'), '<strong>[wrs_walking_log view="main" user="dave"]</strong>')?></p>
                                                  
            <p class="half-width"><?php printf(__('Only a single log can appear on a page or post, so only include a single short code for this view. Stats and ranking, discussed ' .
                                                  'below, can appear multiple times on a page.', 'wrs-walking-log'))?></p>
          </div>
          
          <h3><?php _e('Stats and Ranking Short Code', 'wrs-walking-log')?></h3>
          <div class="wrswl-settings-section">
            <p class="half-width"><?php printf(__('')) ?></p>
            
            <p class="half-width"><?php printf(__('Use %s to display user rankings, and %s to display statistics.', 'wrs-walking-log'), 
                                                  '<strong>[wrs_walking_log view="rank"]</strong>', '<strong>[wrs_walking_log view="stats"]</strong>')?></p>
            <p class="half-width"><?php printf(__('Both of these have similar parameters to control how the reporting is displayed:', 'wrs-walking-log'))?></p>

            <ul class="wrswl-help-list">
              <li class="half-width"><?php printf(__('%s: Show distance stats or rank by distance.', 'wrs-walking-log'), '<strong>by="distance"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Show time stats or rank by time.', 'wrs-walking-log'), '<strong>by="time"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Show stats aggregated or ranked by month.', 'wrs-walking-log'), '<strong>period="month"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Show stats aggregated or ranked by year.', 'wrs-walking-log'), '<strong>period="year"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Show stats aggregated or ranked for all time.', 'wrs-walking-log'), '<strong>period="overall"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Display distance units in miles.', 'wrs-walking-log'), '<strong>units="miles"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Display distance units in kilometers.', 'wrs-walking-log'), '<strong>units="kilometers"</strong>')?></li>
              <li class="half-width"><?php printf(__('%s: Allow users to select which period to display.', 'wrs-walking-log'), '<strong>current_period_only="no"</strong>')?></li>              
              <li class="half-width"><?php printf(__('%s: Show stats or rankings for the current period only. Don\t allow users to select a different period.', 'wrs-walking-log'), '<strong>current_period_only="yes"</strong>')?></li>              
            </ul>
            
            <p class="half-width"><?php printf(__('Parameters specific to the %s short code:', 'wrs-walking-log'), '<strong>rank</strong>')?></p>
            <ul class="wrswl-help-list">
              <li class="half-width"><?php printf(__('%s: Controls the number of ranked rows to display. Valid values are 1 - 50.', 'wrs-walking-log'), '<strong>rows="5"</strong>')?></li>
            </ul>
            
            <p class="half-width"><?php printf(__('Parameters specific to the %s short code:', 'wrs-walking-log'), '<strong>stats</strong>')?></p>
            <ul class="wrswl-help-list">
              <li class="half-width"><?php printf(__('%s: Display stats just for this blog user. Leaving this out defaults to stats aggregated for just the current user. To aggregate for all users use %s.', 'wrs-walking-log'), '<strong>user="dave"</strong>', '<strong>user="all"</strong>')?></li>
            </ul>
          </div>
          
          <h3><?php _e('Examples', 'wrs-walking-log')?></h3>
          <div class="wrswl-settings-section">
            <p class="half-width"><strong>[wrs_walking_log view="rank" by="distance" period="month" rows="10"]</strong>
            <p class="half-width"><strong>[wrs_walking_log view="stats" by="distance" period="year" user="dave"]</strong>
          </div>
          <?php } ?>
 
