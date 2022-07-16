=== New User Approve ===
Contributors: wpexpertsio
Donate link: https://newuserapprove.com
Tags: users, registration, sign up, user management, login, user approval
Requires at least: 4.0
Tested up to: 6.0
Stable tag: 2.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

New User Approve allows a site administrator to approve a user before they
are able to login to the site.

== Description ==

**New User Approve plugin automates the user registration process on your WordPress website.**

Typically, the user registration process on a WordPress website is pretty straightforward. When a new user registers, their unique identity is created in the website's database. An email is then sent to the new user that contains their login credentials. **Simple as it can be, but it goes to say that there is plenty of room for customization**.

**Introducing New User Approve** -  a new way to register users on your WordPress website. 

Here is how the process works with **New User Approve:**

1. A user registers on the site, and their ID is created.
2. An email is sent to the administrators of the site. 
3. An administrator is then given a choice to either approve or deny the registration request.
4. An email is then sent to the user indicating whether they were approved or denied. 
5. If the user is approved, an email will be sent to them that includes their login credentials. 
6. Until the user is approved, they will not be able to login to the site.

Only approved users will be allowed to log in to the site. Thus, users waiting for approval or the ones rejected will not be able to login to the site - Simple, straightforward, and effective.

The user status can be updated even after the initial approval/denial request. The admin can search approve, deny and pending users. Also, users that have been created before the activation of **New User Approve** will be treated as approved users.

**Latest Update - New User Approve v2.3** 

**Automation Made Easier With Zapier**
Zapier allows you to automate mundane tasks and processes without the need for a developer. Zapier uses a simple trigger and action for creating commands, which in the case of New User Approve are:

* User Approved – Triggers when a user is approved.
* User Denied – Triggers when a user is denied.

With Zapier, you can use the trigger events for applications like Gmail, Google Sheets, Slack and more.

**Send Invitation Codes**
Make the registration process easier by sending the invitation code to those who you want to skip the process altogether. People who user the invitation codes are auto-approved by the system.

The invitation codes will be genrated manually and work smoothly with WooCommerce's registration mechanism. They can also be edited and deactivated at any time.

**New User Approve v2.0 - Improved User Interface & Code Optimization**
The overall interface has been revised and improved for a trendy, fresh, and minimal look. The latest design includes a whole new look and experience for the layout so your users can have an experience unlike any other. 

The clean and easy user interface always helps the existing and the new customers gain confidence in your website.

[youtube https://www.youtube.com/watch?v=RowV3wmD_jg]

**Compatibility**
New User Approve is compatible with [WooCommerce](https://woocommerce.com/), [MemberPress](https://memberpress.com/), [WP-Foro](https://wpforo.com/), [Learndash](https://www.learndash.com/), and [Ultimate Member](https://ultimatemember.com/), [BuddyPress](https://buddypress.org/), [Zapier](https://zapier.com/).

**[Follow New User Approve on Github](https://github.com/wpexpertsio/new-user-approve)**

Further support at [newuserapprove.com](http://newuserapprove.com/).

**Custom Actions & Filters**
**New User Approve** can be customized using **custom actions and filters**. You can find out more about these by browsing the source code.

A commercial plugin that adds a config panel for customization is also available at [https://newuserapprove.com/#PricePlan](https://newuserapprove.com/#PricePlan).

**The code for this plugin is also available at Github** [https://github.com/wpexpertsio/new-user-approve](https://github.com/wpexpertsio/new-user-approve). Pull requests are welcomed.

**Filters**
- *new_user_approve_user_status* - modify the list of users shown in the tables
- *new_user_approve_request_approval_message* - modify the request approval message
- *new_user_approve_request_approval_subject* - modify the request approval subject
- *new_user_approve_approve_user_message* - modify the user approval message
- *new_user_approve_approve_user_subject* - modify the user approval subject
- *new_user_approve_deny_user_message* - modify the user denial message
- *new_user_approve_deny_user_subject* - modify the user denial subject
- *new_user_approve_pending_message* - modify message user sees after registration
- *new_user_approve_registration_message* - modify message after a successful registration
- *new_user_approve_register_instructions* - modify message that appears on registration screen
- *new_user_approve_pending_error* - error message shown to pending users when attempting to log in
- *new_user_approve_denied_error* - error message shown to denied users when attempting to log in
- *nua_input_sanitize_hook',$input,$current* - enables you to sanitize and save custom fields
- *nua_pass_create_new_user', $user_pass* - modify the password being assiged to newly created user

**Actions**
- *new_user_approve_user_approved* - after the user has been approved
- *new_user_approve_user_denied* - after the user has been denied
- *new_user_approve_approve_user* - when the user has been approved
- *new_user_approve_deny_user* - when the user has been denied
- *nua_add_settings', $this->option_page* - enables you to add custom setting fields
- *nua_enqueue_scripts_for_settings* - enables you to add custom scripts on settings page
- *nua_settings_main_tab',$action* - enables you to add custom settings tab

== DOCUMENTATION ==
[Click here](https://newuserapprove.com/documentation/#installation-guide) to view the detailed technical documentation of New User Approve Free Version. The documentation includes a step-by-step configuration guide and troubleshooting.

**New User Approve Pro Features**
[Download Premium from here](https://newuserapprove.com/#PricePlan)

* Ability to remove plugin stats from the admin dashboard.
* Remove the admin panel from the WordPress dashboard specifically added to update user status.
* Auto approve or reject users by adding them to Blacklist or Whitelist.
* Customize the welcome message displayed above the WordPress login form.
* Customize the "Pending error message" displayed to the user on the log-in page when their account is still pending approval.
* Customize the "Denied error message" displayed to the user when their account is denied approval.
* Customize the welcome message displayed above the WordPress registration form.
* Customize the "registration complete" message displayed after the user submits the registration form for approval.
* Ability To send notification emails to all admins.
* Notify admins when a user's status is updated.
* Integration with Zapier - allow you to send data between Zapier and New User Approve.
* Zapier trigger events for user approved and denied. 
* Create invitation codes for user registration form on BuddyPress.
* Disable notification emails to current site admin.
* Customize the email sent to admin/s when a user registers on the site.
* Customize the email sent to the user when their profile is approved.
* Customize the email sent to the user when their profile is denied.
* Suppress denial notification(s).
* Option to send all notification/s in the HTML format.
* Different template tags can be used in Notification Emails and other messages.
* Invitation codes to invite and approve users automatically.
* Invitation codes can be generated manually and automatically.
* Import invitation codes via CSV file.

== TRANSLATIONS ==
If you need help in translating the content of this plugin into your language, then take a look at the localization/new-user-approve.pot file, which contains all definitions and can be used with a gettext editor like Poedit (Windows). More information can be found on the [Codex](https://codex.wordpress.org/Translating_WordPress).

* Belarussian translation by [Fat Cow](http://www.fatcow.com/)
* Brazilian Portuguese translation by [leogermani](http://profiles.wordpress.org/leogermani/)
* Bulgarian translation by [spaszs](https://profiles.wordpress.org/spaszs/)
* Catalan translation by [xoanet](http://profiles.wordpress.org/xoanet/)
* Croatian translation by Nik
* Czech translation by [GazikT](http://profiles.wordpress.org/gazikt/)
* Danish translation by [GeorgWP](http://wordpress.org/support/profile/georgwp)
* Dutch translation by [Ronald Moolenaar](http://profiles.wordpress.org/moolie/)
* Estonian translation by (Rait Huusmann)(http://profiles.wordpress.org/raitulja/)
* Finnish translation by Tonttu-ukko
* French translation by [Philippe Scoffoni](http://philippe.scoffoni.net/)
* German translation by Christoph Ploedt
* Greek translation by [Leftys](http://alt3rnet.info/)
* Hebrew translation by [Udi Burg](http://blog.udiburg.com)
* Hungarian translation by Gabor Varga
* Italian translation by [Pierfrancesco Marsiaj](http://profiles.wordpress.org/pierinux/)
* Lithuanian translation by [Ksaveras](http://profiles.wordpress.org/xawiers)
* Persian translation by [alimir](http://profiles.wordpress.org/alimir)
* Polish translation by [pik256](http://wordpress.org/support/profile/1271256)
* Romanian translation by [Web Hosting Geeks](http://webhostinggeeks.com/)
* Russian translation by [Alexey](http://wordpress.org/support/profile/asel)
* Serbo-Croation translation by [Web Hosting Hub](http://www.webhostinghub.com/)
* Slovakian translation by Boris Gereg
* Spanish translation by [Eduardo Aranda](http://sinetiks.com/)
* Swedish translation by [Per Bj&auml;levik](http://pastis.tauzero.se)

== Installation ==

1. Upload new-user-approve to the wp-content/plugins directory or download from the WordPress backend (Plugins -> Add New -> search for 'new user approve')
2. Activate the plugin through the Plugins menu in WordPress
3. No configuration necessary.

== Frequently Asked Questions ==

= Why am I not getting the emails when a new user registers? =

The New User Approve plugin uses the functions provided by WordPress to send email. Make sure your host is setup correctly to send email if this happens.

= How do I customize the email address and/or name when sending notifications to users? =

This is not a function of the plugin but of WordPress. WordPress provides the *wp_mail_from* and *wp_mail_from_name* filters to allow you to customize this. There are also a number of plugins that provide a setting to change this to your liking.

* [wp mail from](http://wordpress.org/extend/plugins/wp-mailfrom/)
* [Mail From](http://wordpress.org/extend/plugins/mail-from/)

= What happens to the user's status after the plugin is deactivated? =

If you deactivate the plugin, their status doesn't matter. The status that the plugin uses is only used by the plugin. All users will be allowed to login as long as they have their username and passwords.

= Are there any known issues with the New User Approve plugin? =

We are aware of a few issues with multisite
1. The status filters on users.php do not work correctly
2. The bubble that shows next to the users link to show the number of pending users does not show

== Screenshots ==

1. User Registration Approval - Pending Users.
2. User Registration Approval - Approved Users.
3. User Registration Approval - Denied Users.
4. User Registration Approval - Zapier Settings.
5. Invitation Code Settings - Add Codes.
6. Invitation Code Settings - Settings.
7. Pro Features.

== Changelog ==

= 2.4.1 =
* Tweak – Security Fixes

= 2.4 =
* Tweak – Code improvement

= 2.3 =
* Added - Zapier Integration
* Added - Filter Hook to Filter password before user creation.
* Added - Search approve, deny and pending users.
* Improvement - Code Optimization.

= 2.1 = 
* Updated Freemius SDK Version 2.4.3

= 2.0 = 
* Updated- Plugin menu.
* Added- the invitation code functionality.
* Improved backend UI.

= 1.9.1 = 
* Added filter to enable/disable auto login on WooCommerce checkout, by default it will be enabled.

= 1.8.9 = 
* Issue in user search functionality.

= 1.8.8 =
* Freemius Library Updated.
* 'View all user' filter not working in users page.
* Disable auto login on WooCommerce checkout.

= 1.8.7 =
* Upgrade to pro menu fixed.

= 1.8.6 =
* Code optimization.

= 1.8.5 =
* Added: Support for reCaptcha on default Login and Registration page.

= 1.8.4 =
* Added: User registeration welcome email
* Added: Action Hook - new_user_approve_after_registration
* Added: Filter Hook for modify welcome email subject - new_user_approve_welcome_user_subject
* Added: Filter Hook for modify welcome email message - new_user_approve_welcome_user_message

= 1.8.3 =
* Updated Freemius SDK Version 2.4.1

= 1.8.2 =
* Code Optimization

= 1.8.1 =
* Tested upto WordPress version 5.5
* Tested for compatibility with [Memberpress](https://memberpress.com/)
* Added: Compatibility for [WooCoommerce](https://woocommerce.com/)

= 1.8 =
* Tested with WordPress 5.4
* Code Optimization

= 1.7.6 =
* Fixed: Formatting of readme.txt had line breaks where they should have been
* Fixed: Fix how deny_user() gets user_email
  * Courtesy of [jrequiroso](https://github.com/jrequiroso)
  * https://github.com/picklewagon/new-user-approve/pull/22
* Fixed: Show unapproved user error message when the user attempts to reset password
* Updated: Swedish translations
  * Courtesy of [adevade](https://github.com/adevade)
  * https://github.com/picklewagon/new-user-approve/pull/59
* Updated: Updates to admin approval screen
  * Courtesy of [adevade](https://github.com/adevade)
  * https://github.com/picklewagon/new-user-approve/pull/60
* Added: Don't allow a super admin to be denied or approved
  * https://github.com/picklewagon/new-user-approve/pull/19
* Added: readme.md to show content in github

= 1.7.5 =
* Fixed: User status filter in admin was not using database prefix
  * Courtesy of [Oizopower](https://github.com/Oizopower)
  * https://github.com/picklewagon/new-user-approve/pull/50
* Fixed: Optimize user status list so it can be used with many users
* Fixed: Updated transient to populate with user counts instead of user list
* Updated: Modify output of user counts on dashboard
* Updated: Polish translations
  * Courtesy of [pik256](http://wordpress.org/support/profile/1271256)
* Added: Missing string to translation file
  * Courtesy of [spaszs](https://profiles.wordpress.org/spaszs/)
* Added: Bulgarian translation
  * Courtesy of [spaszs](https://profiles.wordpress.org/spaszs/)

= 1.7.4 =
* Fixed: Corrected erroneous SQL query when filtering users
* Fixed: User filters
  * Courtesy of [julmuell](https://github.com/julmuell)
  * https://github.com/picklewagon/new-user-approve/pull/44
* Fixed: Show a user status in the filters only if at least one user has that status

= 1.7.3 =
* place content in metaboxes in place of dynamically pulling from website
* tested with WordPress 4.3.1

= 1.7.2 =
* tested with WordPress 4.1
* fix translation bug
* add bubble to user menu for pending users
 * Courtesy of [howdy_mcgee](https://wordpress.org/support/profile/howdy_mcgee)
 * https://wordpress.org/support/topic/get-number-of-pending-users#post-5920371

= 1.7.1 =
* fix code causing PHP notices
* don't show admin notice for registration setting if S2Member plugin is active
* fix issue causing empty password in approval email
* update translation files

= 1.7 =
* email/message tags
* refactor messages
* send admin approval email after the user has been created
* tested with WordPress 4.0
* finish updates in preparation of option addon plugin

= 1.6 =
* improve actions and filters
* refactor messages to make them easier to override
* show admin notice if the membership setting is turned off
* fix bug preventing approvals/denials when using filter
* add sidebar in admin to help with support
* unit tests
* shake the login form when attempting to login as unapproved user
* updated French translation

= 1.5.8 =
* tested with WordPress 3.9
* fix bug preventing the notice from hiding on legacy page

= 1.5.7 =
* fix bug that was preventing bulk approval/denials

= 1.5.6 =
* add more translations

= 1.5.5 =
* allow approval from legacy page

= 1.5.4 =
* fix bug that prevents emails from being sent to admins

= 1.5.3 =
* add filter for link to approve/deny users
* add filter for adding more email addresses to get notifications
* fix bug that prevents users to be approved and denied when requested
* fix bug that prevents the new user email from including a password
* fix bug that prevents search results from showing when searching users

= 1.5.2 =
* fix link to approve new users in email to admin
* fix bug with sending emails to new approved users

= 1.5.1 =
* fix bug when trying to install on a site with WP 3.5.1

= 1.5 =
* add more logic to prevent unwanted password resets
* add more translations
* minor bug fixes
* use core definition of tabs
* user query updates (requires 3.5)
* add status attribute to user profile page
* integration with core user table (bulk approve, filtering, etc.)
* tested with WordPress 3.6
* set email header when sending email
* more filters and actions

= 1.4.2 =
* fix password recovery bug if a user does not have an approve-status meta field
* add more translations
* tested with WordPress 3.5

= 1.4.1 =
* delete transient of user statuses when a user is deleted

= 1.4 =
* add filters
* honor the redirect if there is one set when registering
* add actions for when a user is approved or denied
* add a filter to bypass password reset
* add more translations
* add user counts by status to dashboard
* store the users by status in a transient

= 1.3.4 =
* remove unused screen_layout_columns filter
* tested with WordPress 3.4

= 1.3.3 =
* fix bug showing error message permanently on login page

= 1.3.2 =
* fix bug with allowing wrong passwords

= 1.3.1 =
* add czech, catalan, romanian translations
* fix formatting issues in readme.txt
* add a filter to modify who has access to approve and deny users
* remove deprecated function calls when a user resets a password
* don't allow a user to login without a password

= 1.3 =
* use the User API to retrieve a user instead of querying the db
* require at least WordPress 3.1
* add validate_user function to fix authentication problems
* add new translations
* get rid of plugin errors with WP_DEBUG set to true

= 1.2.6 =
* fix to include the deprecated code for user search

= 1.2.5 =
* add french translation

= 1.2.4 =
* add greek translation

= 1.2.3 =
* add danish translation

= 1.2.2 =
* fix localization to work correctly
* add polish translation

= 1.2.1 =
* check for the existence of the login_header function to make compatible with functions that remove it
* added "Other Notes" page in readme.txt with localization information.
* added belarusian translation files

= 1.2 =
* add localization support
* add a changelog to readme.txt
* remove plugin constants that have been defined since 2.6
* correct the use of db prepare statements/use prepare on all SQL statements
* add wp_enqueue_style for the admin style sheet

= 1.1.3 =
* replace calls to esc_url() with clean_url() to make plugin compatible with versions less than 2.8

= 1.1.2 =
* fix the admin ui tab interface for 2.8
* add a link to the users profile in the admin interface
* fix bug when using email address to retrieve lost password
* show blog title correctly on login screen
* use get_option() instead of get_settings()

= 1.1.1 =
* fix approve/deny links
* fix formatting issue with email to admin to approve user

= 1.1 =
* correctly display error message if registration is empty
* add a link to the options page from the plugin dashboard
* clean up code
* style updates
* if a user is created through the admin interface, set the status as approved instead of pending
* add avatars to user management admin page
* improvements to SQL used
* verify the user does not already exist before the process is started
* add nonces to approve and deny actions
* temporary fix for pagination bug

== Upgrade Notice ==

= 1.5.3 =
Download version 1.5.3 immediately! Some bugs have been fixed that have been affecting how the plugin worked.

= 1.5 =
A long awaited upgrade that includes better integration with WordPress core. Requires at least WordPress 3.5.

= 1.3 =
This version fixes some issues when authenticating users. Requires at least WordPress 3.1.

= 1.3.1 =
Download version 1.3.1 immediately! A bug was found in version 1.3 that allows a user to login without using password.

= 1.3.2 =
Download version 1.3.2 immediately! A bug was found in version 1.3 that allows a user to login using any password.

== Other Notes ==

The code for this plugin is also available at Github - https://github.com/picklewagon/new-user-approve. Pull requests welcomed.
