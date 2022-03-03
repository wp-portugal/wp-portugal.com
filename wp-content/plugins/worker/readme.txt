=== ManageWP Worker ===
Contributors: managewp,freediver
Tags: manage multiple sites, backup, security, migrate, performance, analytics, Manage WordPress, Managed WordPress, WordPress management, WordPress manager, WordPress management, site management, control multiple sites, WordPress management dashboard, administration, automate, automatic, comments, clone, dashboard, duplicate, google analytics, login, manage, managewp, multiple, multisite, remote, seo, spam
Requires at least: 3.1
Tested up to: 5.8.2
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/quick-guide-gplv3.html

A better way to manage dozens of WordPress websites.

== Description ==

So you're looking for a better way to manage WordPress websites? We have you covered! [ManageWP](https://managewp.com/ "Manage Multiple WordPress Websites") is a dashboard that helps you save time and nerves by automating your workflow, so you could focus on things that matter. It is fast, secure and free for an unlimited number of websites.

= Everything in One Place =
Just the hassle of logging into each of your websites is enough to ruin your day. ManageWP compiles the data from all of your sites on one dashboard, so you can check up on your websites in a single glance. And if you need to take a better look at a particular website, you're just a click away. [Read more](https://managewp.com/features/1-click-login "1-click login")

= Bulk actions =
57 updates on 12 sites? Update them all with a single click. And it's not just updates. Clean spam, database overhead, run security checks and more - with just one click you can do these things on all your websites at once. [Read more](https://managewp.com/features/manage-plugins-and-themes "Manage plugins & themes")

= Cloud Backup that just works =
A reliable backup is the backbone of any business. And we have a free monthly backup for all of your websites. It's, incremental, reliable, and works where other backup solutions fail. The free Backup includes monthly scheduled backup, off-site storage, 1-click restore, US/EU storage choice and the option to exclude files and folders. The premium Backup gives you on-demand backups, weekly/daily/hourly backup cycles & [more](https://managewp.com/features/backup "ManageWP Backup").

= Safe updates =
Updating plugins & themes is a huge pain, so we came with this: a backup is automatically created before each update. After the update, the system checks the website and rolls back automatically if something's wrong. And the best part is that you can set these updates to run at 3am, when the website traffic as its lowest.
[Read more](https://managewp.com/features/safe-updates "Safe Updates").

= Client Report =
Summarize your hard work in a professional looking report and send it to your clients to showcase your work. The free Client Report includes basic customization and on-demand reports. The premium Client Report lets you white label and automate your reports. [Read more](https://managewp.com/features/backup "Client Report")

= Performance and Security Checks =
Slow or infected websites are bad for business. Luckily, you can now keep tabs on your websites with regular performance & security checks. The free [Security Check](https://managewp.com/features/security-check "security check") & [Performance Check](https://managewp.com/features/performance-scan "performance check") come with fully functional checks and logging. Premium versions let you fully automate the checks, and get an SMS or an email if something's wrong.

= Google Analytics integration =
Connect multiple Google Analytics accounts, and keep track of all the important metrics from one place.  [Read more](https://managewp.com/features/analytics "Google Analytics integration")

= Uptime Monitor (premium add-on) =
Be the first to know when your website is down with both email and SMS notifications, and get your website back online before anyone else notices. [Read more](https://managewp.com/features/uptime-monitor "Uptime Monitor")

= Cloning & Migration (bundled with premium Backup add-on) =
What used to take you hours of work and nerves of steel is now a one-click operation. Pick a source website, pick a destination website, click Go. Within minutes, youw website will be alive and kicking on a new server. Yeah, it's that easy. [Read more](https://managewp.com/features/clone "Cloning & migration")

= SEO Ranking (premium add-on) =
Be on top of your website rankings and figure out which keywords work best for you, as well as keeping on eye on your competitors. This way you will know how well you stack up against them. [Read more](https://managewp.com/features/seo-ranking "SEO Ranking")

= White Label (premium add-on) =
Rename or completely hide the ManageWP Worker plugin. Clients don’t need to know what you are using to manage their websites. [Read more](https://managewp.com/features/white-label "White Label")

= Is This All? =
No way! We've got a bunch of other awesome features, both free and premium, you can check out on our [ManageWP features page](https://managewp.com/features "ManageWP Features")

Check out the [ManageWP promo video](https://vimeo.com/220647227).

https://vimeo.com/220647227

== Changelog ==
= 4.9.13 =
- Fix: Resolved old PHP version compatibility issues.

= 4.9.12 =
- Fix: Resolved Worker white-labeling not working as expected.

= 4.9.11 =
- Fix: Ensure full compatibility with PHP 8.1

= 4.9.10 =
- Fix: Resolved compatibility issue with WooCommerce payments
- Fix: Set plugin version for MU plugin loader

= 4.9.9 =

- Fix: Resolved edge case compatibility issue with some sites
- New: Added "Disconnect all" option in the Connection Management in wp-admin
- Worker update tested to the latest version of WordPress
- Minor wording changes

= 4.9.7 =

- Update logic for calculating table overhead

= 4.9.6 =

- Updated logic for generating archive name for File Manager tool.
- Update tested up to version for the plugin.
- Fix: Edge case where a backup might fail due to root WP paths.

= 4.9.3 =

- Update tested up to version for the plugin.
- Fix: Potential crash when the hit count option is not defined.

= 4.9.2 =

- Added fallback for downloading/archiving files for the File Manager tool, when zip extension is not available
- Fix: Worker plugin branding within WP 5.2 Admin Site Health page plugins list

= 4.9.1 =

- Fix: Handle updates on WP Engine hosted websites properly.

= 4.9.0 =

- New: Support for a future release of file management.
- We will stop supporting PHP 5.2 in the next version.

= 4.8.1 =

- Fix: Edge case where a backup might fail due to API call payload.

= 4.8.0 =

- New: Support for automatic detection of post content changes for Link Monitoring.

= 4.7.8 =

- Fix: Edge case when there are no plugins active, the plugin would cause a fatal error.

= 4.7.7 =

- Fix: Edge cases where one click login might fail due to the Host header changing.

= 4.7.5 =

- Fix: Edge cases where key fetching might fail and cause the connection to stop working.

= 4.7.0 =

- Improvement: Translations for the new Connection Management dialog.
- Fix: An error that might occur when activating the Worker plugin.

= 4.6.6 =

- Fix: Omit extra query parameters for One Click Login after a successful login.

= 4.6.5 =

- Fix: Edge cases where the Worker plugin might not be able to communicate with our system.

= 4.6.4 =

- New: Allow multiple ManageWP/Pro Sites accounts to connect to a single Worker plugin.

= 4.6.3 =

- Fix: Edge cases when Local Sync was unsuccessful.
- Fix: WooCommerce database upgrade not showing up on the ManageWP/Pro Sites dashboard.

= 4.6.2 =

- Fix: Local Sync tool improvements.

= 4.6.1 =

- Fix: Worker auto-recovery on PHP 7.
- Fix: Replaced eval function that triggered false positives with some security plugins.

= 4.6.0 =

- New: Localhost Sync has reached the closed beta stage. Stay tuned for more info!

= 4.5.0 =

- Improvement: Removed deprecated ManageWP Classic code.

= 4.4.0 =

- Fix: Communication failing with a website behind CloudFlare, that has warnings turned on, and currently has warnings.

= 4.3.4 =

- Improvement: The Worker plugin can now only be activated network wide on multisite installs.
- Fix: Edge cases where the connection key was not visible.
- Fix: Edge cases with Multisite communication failure.

= 4.3.3 =

- Improvement: Always force the correct charset for database backups.
- Improvement: The Worker plugin is now fully compatible with WordPress 4.9.

= 4.3.2 =

- Fix: The Worker plugin threw an exception while recovering from failed update.

= 4.3.1 =

- Fix: The Worker plugin could not fetch keys for the new communication system in some cases.

= 4.3.0 =

- New: Ability to install/update Envato plugins and themes.
- New: WooCommerce database upgrade support.
- New: More secure and flexible communication between the Worker plugin and the ManageWP servers.

== Installation ==

1. Create an account on [ManageWP.com](https://managewp.com/ "Manage Multiple WordPress Sites")
2. Follow the steps to add your first website
3. Celebrate!

Seriously, it's that easy! If you want more detailed instructions, check out our [User Guide](https://managewp.com/guide/getting-started/add-website-managewp-dashboard "Add your website to ManageWP")

== Screenshots ==

1. ManageWP dashboard with a thumbnail view of 20 websites
2. Tags and stars help you organize your websites
3. A summary of available updates and health of all your websites
4. Track your website performance regularly, so you could know right away if something goes wrong
5. Managing plugins and themes is just as easy with 100 websites as with 3 websites
6. Client Report is an executive summary of everything you've done for your client
7. Cloud backups with detailed information about each restore point
8. Uptime Monitor logs up and down events, and notifies you via email and SMS
9. Aside from being able to white label the ManageWP Worker plugin, you can also add a support form on the client's website

== License ==

This file is part of ManageWP Worker.

ManageWP Worker is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

ManageWP Worker is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with ManageWP Worker. If not, see <https://www.gnu.org/licenses/>.


== Frequently Asked Questions ==

= Is ManageWP free? =

ManageWP is using the freemium model. All the core features are free for an unlimited number of websites. And for those of you who need more, we have a set of premium features to help you out.

= Do you offer support for free users? =

Yes. No matter if you're free or premium user, we are here for you 24/7. Expect a 1h average response time and a 65% answer resolution in the first reply.

= How much do premium ManageWP features cost? =

Our pricing is highly flexible, we don't charge anything upfront. The usage is calculated on a per-website, per-addon basis, like Amazon Web Services. Check out our [pricing page](https://managewp.com/pricing "ManageWP pricing page") for more info.

= Is ManageWP secure? =

Yes. All of our code is developed in-house and we have a top notch security team. With half a million websites managed since 2012 we did not have a single security incident. We've accomplished this through high standards, vigilance and the help of security researchers, through our [white hat security program](https://managewp.com/white-hat-reward).

= I have websites on several different hosts. Will ManageWP work all of them? =

Yes. ManageWP plays nice with all major hosts, and 99% of the small ones.

= Does ManageWP work with multisites? =

Yes, multisite networks are fully supported, including the ability to backup and clone a multisite network.

= Does ManageWP work with WordPress.com sites? =

No. ManageWP works only with self-hosted WordPress sites.

= Worker plugin can connect to ManageWP and Pro Sites. What is the difference between the two? =

[ManageWP](https://managewp.com "ManageWP website") is focused on the hosting-agnostic WordPress website management. [Pro Sites](https://www.godaddy.com/pro "GoDaddy Pro Sites website") is the GoDaddy version of the service. It's part of the GoDaddy Pro program, which incorporates different tools for website & client management, lead generation, and tighter integration with other GoDaddy products.

= I have problems adding my site =

Make sure you use the latest version of the Worker plugin on the site you are trying to add. If you still have problems, check our dedicated [FAQ page](https://managewp.com/troubleshooting/site-connection/why-cant-i-add-some-of-my-sites "Add site FAQ") or [contact us](https://managewp.com/contact "ManageWP Contact").

= How does ManageWP compare to backup plugins like BackupBuddy, Backwpup, UpdraftPlus, WP-DB-Backup ? =

There is a limit to what a PHP based backup can do, that's why we've built a completely different backup - cloud based, incremental, it keeps working long after others have failed.

= How does ManageWP compare with clone plugins like Duplicator, WP Migrate DB, All-in-One WP Migration, XCloner ? =

These solutions are simple A-B cloning solutions that tend to break in critical moments. ManageWP does it more intelligently. We first upload the backup archive to a cloud infrastructure that we control, and then we transfer it to the destination website. This effectively compartmentalizes the process into two separate steps, making the whole cloning experience much more robust and stress free.

= Is Worker PHP7 compatible? =

Yes, ManageWP Worker is fully compatible with PHP7. We also have chunks of backward compatible code, that triggers in case you're still running PHP5.x - if your code check comes up with a compatibility flag, just ignore it.


Got more questions? [Contact us!](https://managewp.com/contact "ManageWP Contact")
