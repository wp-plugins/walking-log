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



if (! class_exists('wrsWalkingLogReports'))
{
  class wrsWalkingLogReports
  {
    var $Owner;
  
    function wrsWalkingLogReports($owner)
    {
      $this->Owner = $owner;
    }

    
    function GetDateRangeFromPeriod($hash, $period, &$start_date, &$end_date, &$date_select)
    {
      $date_select = '';
      
      // default selected date to the previously saved date for this form
      $last_selected = $this->Owner->GetSelectedDate($hash);
      $selected_date = $last_selected;
      
      
      // get the form hash so we can make sure any form input belongs to this short code
      if (isset($_GET['id']))
        $form_hash = $_GET['id'];
      else
        $form_hash = $hash;
        

      if ($period == 'month')
      {
        if ($hash == $form_hash && isset($_GET['wrswl-month-select']))
        {
          $selected_date = $_GET['wrswl-month-select'];
        }
        
        if (!isset($selected_date))
        {
          $selected_date = wrsWalkingLogPlugin::DateFormat('Y-m-01');
        }

        $this->Owner->VerifyDate($selected_date);
        
        
        $start_date = $selected_date;
        $end_date = wrsWalkingLogPlugin::DateFormat('Y-m-d', strtotime($start_date . ' +1 months'));
        

        // cap end date to current date        
        if ($selected_date == '' || $selected_date == wrsWalkingLogPlugin::DateFormat('Y-m-01'))
        {
          $end_date = wrsWalkingLogPlugin::DateFormat('Y-m-d', strtotime(wrsWalkingLogPlugin::DateFormat('Y-m-d') . ' +1 days'));
        }
        
        $date_select = $this->Owner->WriteMonthSelect(0, $selected_date);
        // $date_select .= '<p>sm=' . $selected_date . ', ' . 'compare=' . wrsWalkingLogPlugin::DateFormat('Y-m-01') . ', end_date=' . $end_date . ', set=' . $set_end_date . '</p>';
      }
      else if ($period == 'year')
      {
        if ($hash == $form_hash && isset($_GET['wrswl-month-select']))
        {
          $selected_date = $_GET['wrswl-month-select'];
        }
        
        if (!isset($selected_date))
        {
          $selected_date = wrsWalkingLogPlugin::DateFormat('Y');
        }

        $this->Owner->VerifyInteger($selected_date);

        
        $start_date = $selected_date . '-1-1';
        $end_date = ($selected_date + 1) . '-1-1';

        // cap end date to current date        
        if ($selected_date == wrsWalkingLogPlugin::DateFormat('Y'))
        {
          $end_date = wrsWalkingLogPlugin::DateFormat('Y-m-d', strtotime(wrsWalkingLogPlugin::DateFormat('Y-m-d') . ' +1 days'));
        }

        $date_select = $this->GetYears($selected_date);
      }
      else if ($period == 'overall')
      {
        $start_date = '1900-01-01';
        $end_date = wrsWalkingLogPlugin::DateFormat('Y-m-d', strtotime(wrsWalkingLogPlugin::DateFormat('Y-m-d') . ' +1 days'));
      }
      
      
      // remember selected value for this form
      if ($selected_date != $last_selected)
      {
        $this->Owner->SetSelectedDate($hash, $selected_date);
      }
    }

      
    function GetYears($selectedYear)
    {
      global $wpdb;
      
      $exercise_log_table_name = $this->Owner->ExerciseLogTableName;
      
      $sql = "select distinct year(log_date) as year " .
             "from $exercise_log_table_name " .
             "order by year(log_date)";
      
      $rows = $wpdb->get_results($sql);
      
      $result = '<select name="wrswl-month-select" id="wrswl-month-select">';
      
      foreach ($rows as $row)
      {
        $result .= '<option value="' . $row->year . '"';
        if ($row->year == $selectedYear)
        {
          $result .= ' selected="selected"';
        }
        
        $result .= '>' . $row->year . '</option>';
      }

      $result .= '</select>';
      
      return $result;
    }

    
    function GetExerciseLogs()
    {
      global $wpdb;

      $exercise_log_table_name = $this->Owner->ExerciseLogTableName;
      
      $sql = "select u.id as wordpress_user_id, " .
             "u.display_name, " .
             "max(log_date) as latest_log_entry " .
             "from $wpdb->users u " .
             "left outer join $exercise_log_table_name l on l.wordpress_user_id = u.id " .
             "group by u.id, u.display_name " .
             "order by max(log_date) desc, u.display_name";
      
      return $wpdb->get_results($sql);
    }

    
    function GetLogsView($user_id)
    {
      $result = '<table name="wrswl-monthly-data-table" id="wrswl-monthly-data-table">' .
                '<tr><th class="wrswl-table-header-footer">' . _x('Name', 'column header for walking log user name', 'wrs-walking-log') . '</th>' .
                '<th class="wrswl-table-header-footer">' . _x('Latest Log Entry', 'column header for latest walking log entry date', 'wrs-walking-log') . '</th></tr>';
                
      $rows = $this->GetExerciseLogs();
      foreach ($rows as $row)
      {
        $result .= '<tr>';
        $result .= '<td>' . $row->display_name . '</td>';
        $result .= '<td>' . $row->latest_log_entry . '</td>';
        $result .= '</tr>';
      }

      $result .= '</table>';
      
      return $result;
    }
    
    
    function GetRankByDistance($user_id, $start_date, $end_date, $row_count, $units)
    {
      global $wpdb;
      
      $exercise_log_table_name = $this->Owner->ExerciseLogTableName;
      
      $sql = $wpdb->prepare("select u.id as wordpress_user_id, u.display_name, sum(distance) as distance " .
                            "from $exercise_log_table_name l " .
                            "join $wpdb->users u on u.id = l.wordpress_user_id " .
                            "where l.log_date >= %s and l.log_date < %s " .
                            "group by u.id, u.display_name " .
                            "order by sum(distance) desc, u.display_name " .
                            "limit %d", $start_date, $end_date, $row_count);

      $rows = $wpdb->get_results($sql);
      
      $distance_header = $units == 'miles' ? _x('Miles', 'column header for showing distance as miles', 'wrs-walking-log') : _x('Kilometers', 'column header for showing distance as kilometers', 'wrs-walking-log');
      
      $result = '<table name="wrswl-rank-table" id="wrswl-rank-table">' .
                '<tr><th class="wrswl-table-header-footer">' . _x('Name', 'column header for walking log user name', 'wrs-walking-log') . '</th>' .
                '<th class="wrswl-table-header-footer">' . $distance_header . '</th></tr>';
      
      foreach ($rows as $row)
      {
        $result .= '<tr>';
        $result .= '<td>' . $row->display_name . '</td>';
        $result .= '<td class="wrswl-distance-column">' . round($units == 'miles' ? $row->distance : $this->Owner->MilesToKilometers($row->distance), 2) . '</td>';
        $result .= '</tr>';
      }

      $result .= '</table>';
      
      return $result;
    }
    
    
    function GetRankByTime($user_id, $start_date, $end_date, $row_count, $units)
    {
      global $wpdb;
      
      $exercise_log_table_name = $this->Owner->ExerciseLogTableName;
      
      $sql = $wpdb->prepare("select u.id as wordpress_user_id, u.display_name, sum(elapsed_time) as elapsed_time " .
                            "from $exercise_log_table_name l " .
                            "join $wpdb->users u on u.id = l.wordpress_user_id " .
                            "where l.log_date >= %s and l.log_date < %s " .
                            "group by u.id, u.display_name " .
                            "order by sum(elapsed_time) desc, u.display_name " .
                            "limit %d", $start_date, $end_date, $row_count);

      $rows = $wpdb->get_results($sql);
      
      $header = _x('Hours', 'column header for showing number of hours', 'wrs-walking-log');
      
      $result = '<table name="wrswl-rank-table" id="wrswl-rank-table">' .
                '<tr><th class="wrswl-table-header-footer">' . _x('Name', 'column header for walking log user name', 'wrs-walking-log') . '</th>' .
                '<th class="wrswl-table-header-footer">' . $header . '</th></tr>';
      
      foreach ($rows as $row)
      {
        $result .= '<tr>';
        $result .= '<td>' . $row->display_name . '</td>';
        $result .= '<td class="wrswl-distance-column">' . round($row->elapsed_time, 2) . '</td>';
        $result .= '</tr>';
      }

      $result .= '</table>';
      
      return $result;
    }


    function GetStats($user_id, $start_date, $end_date, $period, $units, $by)
    {
      global $wpdb;
      $result = '';

      $exercise_log_table_name = $this->Owner->ExerciseLogTableName;
      
      // NOTE : end date is exclusive
      $sql = $wpdb->prepare(
        "select min(log_date) as earliest_date, " .
               "sum(distance) as distance, " .
               "sum(elapsed_time) as elapsed_time " .
        "from $exercise_log_table_name " .
        "where log_date >= %s " .
              "and log_date < %s ", $start_date, $end_date);


      // filter on user if needed
      if (isset($user_id) && $user_id != -1)
      {
        $sql .= " and wordpress_user_id = $user_id";
      }      

      
      $row = $wpdb->get_row($sql);
      if ($row == null)
      {
        return '<p>' . __('No stats are available.', 'wrs-walking-log') . '</p>';
      }
      
      
      // if doing overall stats then we want to set the start date to the earliest available date so we
      // can properly calculate time based stats
      if ($period == 'overall')
      {
        $start_date = $row->earliest_date;
      }

      
      // get date intervals, some special handling required to deal with different versions of PHP
      require_once('walking_log_functions.php');
      wrsWalkingLogFunctions::GetDateIntervals($start_date, $end_date, $years, $months, $weeks, $days, $hours);

      /*      
      $interval = date_diff(new DateTime($start_date), new DateTime($end_date));
      $years = $interval->y + $interval->m / 12.0;
      $months = $interval->y * 12 + $interval->m;
      
      $months += $interval->d / wrsWalkingLogPlugin::DateFormat('t', strtotime($end_date));
      
      $weeks = $interval->days / 7;
      $days = $interval->days;
      $hours = $interval->days * 24;
      */
      
      // $result .= '<p>' . sprintf('Earliest date: %s, years: %0.2f, months: %0.2f, weeks: %0.2f, days: %d, hours: %d', $start_date, $years, $months, $weeks, $days, $hours) . '</p>';

      
      // calculate stats

      // get distance - miles, km, hours      
      if ($by == 'distance')
        $distance = $units == 'miles' ? $row->distance : $this->Owner->MilesToKilometers($row->distance);
      else
        $distance = $row->elapsed_time;
        
      $per_month = min(round($distance / $months, 2), $distance);
      $per_week = min(round($distance / $weeks, 2), $distance);
      $per_day = min(round($distance / $days, 2), $distance);
      $per_hour = min(round($distance / $hours, 2), $distance);
      
      
      // are we looking at the current period?
      $showing_current_period = true;
      $selected_date_prompt = null;
      
      if ($period == 'year')
      {
        $selected_year = wrsWalkingLogPlugin::DateFormat('Y', strtotime($start_date));
        $current_year = wrsWalkingLogPlugin::DateFormat('Y');
        $showing_current_period = ($selected_year == $current_year);
        $selected_date_prompt = $selected_year;
      }
      else if ($period == 'month')
      {
        $selected_month = wrsWalkingLogPlugin::DateFormat('Y-m-01', strtotime($start_date));
        $current_month = wrsWalkingLogPlugin::DateFormat('Y-m-01');
        $showing_current_period = ($selected_month == $current_month);
        $selected_date_prompt = date_i18n('F Y', strtotime($start_date));
      }
      
      
      // figure out the various prompts, based on stats type and measurement units
      // this is uglier than it could be, but we need to do all the string explicitly for i18n, rather than build the strings dynamically
      if ($by == 'distance')
      {
        if ($units == 'miles')
        {
          if ($period == 'year')
          {
            if ($showing_current_period)
              $period_prompt = sprintf(_n('%0.2f mile this year', '%0.2f miles this year', $distance, 'wrs-walking-log'), $distance);
            else
              $period_prompt = sprintf(_nx('%1$0.2f mile in %2%s', '%1$0.2f miles in %2$s', $distance, 'number of miles in a specific 4 digit year', 'wrs-walking-log'), $distance, $selected_date_prompt);
          }
          else if ($period == 'month')
          {
            if ($showing_current_period)
              $period_prompt = sprintf(_n('%0.2f mile this month', '%0.2f miles this month', $distance, 'wrs-walking-log'), $distance);
            else
              $period_prompt = sprintf(_n('%1$0.2f mile in %2$s', '%1$0.2f miles in %2$s', $distance, 'number of miles in a specific month and year', 'wrs-walking-log'), $distance, $selected_date_prompt);
          }
          else if ($period == 'overall')
          {
            $period_prompt = sprintf(_n('%0.2f mile overall', '%0.2f miles overall', $distance), $distance);
          }
          
          $prompt_per_month = sprintf(_n('%0.2f mile per month', '%0.2f miles per month', $per_month), $per_month);
          $prompt_per_week = sprintf(_n('%0.2f mile per week', '%0.2f miles per week', $per_week), $per_week);
          $prompt_per_day = sprintf(_n('%0.2f mile per day', '%0.2f miles per day', $per_day), $per_day);
          $prompt_per_hour = sprintf(_n('%0.2f mile per hour', '%0.2f miles per hour', $per_hour), $per_hour);
        }
        else if ($units == 'kilometers')
        {
          if ($period == 'year')
          {
            if ($showing_current_period)
              $period_prompt = sprintf(_n('%0.2f kilometer this year', '%0.2f kilometers this year', $distance, 'wrs-walking-log'), $distance);
            else
              $period_prompt = sprintf(_nx('%1$0.2f kilometer in %2%s', '%1$0.2f kilometers in %2$s', $distance, 'number of kilometers in a specific 4 digit year', 'wrs-walking-log'), $distance, $selected_date_prompt);
          }
          else if ($period == 'month')
          {
            if ($showing_current_period)
              $period_prompt = sprintf(_n('%0.2f kilometer this month', '%0.2f kilometers this month', $distance, 'wrs-walking-log'), $distance);
            else
              $period_prompt = sprintf(_n('%1$0.2f kilometer in %2$s', '%1$0.2f kilometers in %2$s', $distance, 'number of kilometers in a specific month and year', 'wrs-walking-log'), $distance, $selected_date_prompt);
          }
          else if ($period == 'overall')
          {
            $period_prompt = sprintf(_n('%0.2f kilometer overall', '%0.2f kilometers overall', $distance), $distance);
          }
          
          $prompt_per_month = sprintf(_n('%0.2f kilometer per month', '%0.2f kilometers per month', $per_month), $per_month);
          $prompt_per_week = sprintf(_n('%0.2f kilometer per week', '%0.2f kilometers per week', $per_week), $per_week);
          $prompt_per_day = sprintf(_n('%0.2f kilometer per day', '%0.2f kilometers per day', $per_day), $per_day);
          $prompt_per_hour = sprintf(_n('%0.2f kilometer per hour', '%0.2f kilometers per hour', $per_hour), $per_hour);
        }
      }
      else if ($by == 'time')
      {
        if ($period == 'year')
        {
          if ($showing_current_period)
            $period_prompt = sprintf(_n('%0.2f hour this year', '%0.2f hours this year', $distance, 'wrs-walking-log'), $distance);
          else
            $period_prompt = sprintf(_nx('%1$0.2f hour in %2%s', '%1$0.2f hours in %2$s', $distance, 'number of hours in a specific 4 digit year', 'wrs-walking-log'), $distance, $selected_date_prompt);
        }
        else if ($period == 'month')
        {
          if ($showing_current_period)
            $period_prompt = sprintf(_n('%0.2f hour this month', '%0.2f hours this month', $distance, 'wrs-walking-log'), $distance);
          else
            $period_prompt = sprintf(_n('%1$0.2f hour in %2$s', '%1$0.2f hours in %2$s', $distance, 'number of hours in a specific month and year', 'wrs-walking-log'), $distance, $selected_date_prompt);
        }
        else if ($period == 'overall')
        {
          $period_prompt = sprintf(_n('%0.2f hour overall', '%0.2f hours overall', $distance), $distance);
        }
        
        $prompt_per_month = sprintf(_n('%0.2f hour per month', '%0.2f hours per month', $per_month), $per_month);
        $prompt_per_week = sprintf(_n('%0.2f hour per week', '%0.2f hours per week', $per_week), $per_week);
        $prompt_per_day = sprintf(_n('%0.2f hour per day', '%0.2f hours per day', $per_day), $per_day);
        $prompt_per_hour = sprintf(_n('%0.2f hour per hour', '%0.2f hours per hour', $per_hour), $per_hour);
      }
      
      
      // the float formatting doesn't remove .00, so we'll do it here
      $period_prompt = str_replace('.00', '', $period_prompt);
      $prompt_per_month = str_replace('.00', '', $prompt_per_month);
      $prompt_per_week = str_replace('.00', '', $prompt_per_week);
      $prompt_per_day = str_replace('.00', '', $prompt_per_day);
      $prompt_per_hour = str_replace('.00', '', $prompt_per_hour);
      

      $result .= '<table name="wrswl-monthly-data-table" id="wrswl-monthly-data-table">';
      $result .= "  <tr class=\"wrswl-table-header-footer\"><td>$period_prompt</td></tr>\n";
      
      // no per month stats if we're showing a month period, because it's the same as the period prompt above since we're only looking at one month
      if ($period != 'month')
        $result .= "  <tr><td>$prompt_per_month</td></tr>\n";
        
      $result .= "  <tr><td>$prompt_per_week</td></tr>\n";
      $result .= "  <tr><td>$prompt_per_day</td></tr>\n";
      
      // no "per hour" stat if we're showing time based stats
      if ($by != 'time')
        $result .= "  <tr><td>$prompt_per_hour</td></tr>\n";

      $result .= '</table>';
      
      return $result;
    }
    
    
    function GetRankView($user_id, $hash, $row_count, $period, $by, $current_period_only, $units)
    {
      global $wpdb, $pagenow;
      
      $dateSelect = '';
      $result = '<div class="wrswl-rank-view">' . "\n";
      
      $this->GetDateRangeFromPeriod($hash, $period, $start_date, $end_date, $date_select);
      //$result .= sprintf('<p>Rank %s rows by %s for %s period which spans %s through %s.</p>', $row_count, $by, $period, $start_date, $end_date);
                     

      if ($current_period_only == 'no' && $date_select != '')
      {
        // build year or month selector form
        $result .= '<form method="get" name="wrsrl-ranking">';
        
        if (is_admin() && isset($_REQUEST['page']) && $pagenow == 'admin.php' && $_REQUEST['page'] == 'wrs_walking_log_menu_stats')
        {
          $result .= '  <input type="hidden" name="page" value="wrs_walking_log_menu_stats" />';
        }
        
        $result .= '  <input type="hidden" name="id" value="' . $hash . '">';
        $result .= $date_select;
        $result .= '<input type="submit" id="wrswl-select-report-date" value="' . _x('Select', 'submit button to select a date from a list', 'wrs-walking-log') . '"/>';
      }

      
      if ($by == 'distance')
        $result .= $this->GetRankByDistance($user_id, $start_date, $end_date, $row_count, $units);
      else if ($by == 'time')
        $result .= $this->GetRankByTime($user_id, $start_date, $end_date, $row_count, $units);
      
      
      if ($date_select != '')
      {
        $result .= '</form>';
      }
      
      
      $result .= "</div>\n";
      
      return $result;
    }
    
    
    function GetStatsView($user_id, $hash, $period, $by, $current_period_only, $units)
    {
      global $wpdb, $pagenow;
      
      $view = 'rank';
      $result = '<div class="wrswl-stats-view">' . "\n";
      
      
      $this->GetDateRangeFromPeriod($hash, $period, $start_date, $end_date, $date_select);
      // $result .= sprintf('<p>Stats by %s for %s period which spans %s through %s.</p>', $by, $period, $start_date, $end_date);
                     

      if ($current_period_only == 'no' && $date_select != '')
      {
        // build year or month selector form

        
        $result .= '<form method="get" name="wrsrl-ranking" id="wrsl-ranking">';
        
        if (is_admin() && isset($_REQUEST['page']) && $pagenow == 'admin.php' && $_REQUEST['page'] == 'wrs_walking_log_menu_stats')
        {
          $result .= '  <input type="hidden" name="page" value="wrs_walking_log_menu_stats" />';
        }
        
        $result .= '  <input type="hidden" name="id" value="' . $hash . '" />';
        $result .= $date_select;
        $result .= '<input type="submit" id="wrswl-select-report-date" value="' . _x('Select', 'submit button to select a date from a list', 'wrs-walking-log') . '"/>';
      }

      
      $result .= $this->GetStats($user_id, $start_date, $end_date, $period, $units, $by);
      
      if ($date_select != '')
      {
        $result .= '</form>';
      }

      
      $result .= "</div>\n";
      
      return $result;
    }
  }
}

?>