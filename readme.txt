=== Plugin Name ===
Contributors: CrappyCodingGuy
Donate link: 
Tags: exercise, walking, hiking
Requires at least: 3.0
Tested up to: 3.5.1
Stable tag: 1.3

Exercise log for tracking time and distance based exercise, such as walking or running.

== Description ==

Walking Log is a WordPress plugin for tracking time and distance based exercise, such as walking or hiking. 
The plugin allows you to track date, exercise time in minutes, distance, type (e.g. walking), and location.

Each blog user has his or her own log which can be viewed and edited within the admin pages, or it can be
placed in a post or page with various viewing and editing permissions by using short codes. Additional
short codes allow the display of rankings reports and user or overall statistics.

== Installation ==

1. Upload the 'walking-log' folder to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Create a page, or identify an existing page, that will be used to host the log
1. Add this short code to the page: [wrs_walking_log view="main"]
1. Add a custom field to the page with a name of wrs_walking_log and a value of 1. This is required so 
   the plugin can load itself only on pages where it's needed.
1. Add new exercise types and locations using the Walking Log admin pages.
1. If you're upgrading a previous installation you will be asked to assign existing log data to a blog user.
1. Additional instructions can be found in the plugin's admin help page.


== Frequently Asked Questions ==

= How do I add new exercise types or locations? =

Add new exercise types and locations using the Walking Log/Maintenance settings admin menu.

= Does the plugin clean up after itself when it's deleted? =

Yes, the plugin cleans up all options and database tables it creates when running as a single blog.
When running in a multisite network it will not delete tables since this could be a very time consuming
operation on a large network. This could also be a dangerous operation since there is a risk of
accidentally deleting a lot of data.

== Changelog ==

= 1.3 =
* Added new short codes for ranking and statistics.
* Added help admin page with improved instructions for short code usage.
* Added admin page to display user stats.
* Added global default settings that control various parameters when no user is signed in.
* Fixed bug with short code handling that prevented placing html before the shortcode.

= 1.2 =
* The logs are now tracked by blog user, rather than having a single log per blog.
* Added the ability to show a log for a specific user.
* Some optimizations to prevent loading scripts when not needed.
* Added admin options to control how data is preserved or deleted when the plugin is uninstalled.
* Admin can flag exercise types and locations as global so they show for all users.
* Security enhancements.

= 1.1 =
* Multisite network is now supported.
* Added log viewing and editing from within admin pages.
* Style sheet improvements so the default styles work better with different themes/font sizes - might still require tweaking on themes with unusual font sizes.
* Added privacy settings to provide some flexibility in who can see the log.
* Enabled deleting types and locations that are no longer used.
* Fixed several PHP notices and other bugs.
* The uninstall page provides info about what needs backed up before deleting, but all uninstall functionality is now handled when deleting the plugin from plugin admin.


= 1.0 =
* Initial version.

== Upgrade Notice ==

= 1.3 =
New short codes for displaying user ranking and statistics.

= 1.2 =
Logs are now user based rather than a single global log, optimizations, security enhancements.

= 1.1 =
Log viewing and editing from admin pages, multisite now supported, new privacy settings, style sheet improvements, bug fixes.

= 1.0 =
Initial version.

== Screenshots ==

1. Sample log imbedded in a page
2. Add exercise types and locations

== License ==

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

