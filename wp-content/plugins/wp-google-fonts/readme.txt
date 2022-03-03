=== WP Google Fonts ===
Contributors: Adrian3
Tags: Google fonts, fonts, font, type, free fonts, typography, theme, admin, plugin, css, design, plugin, template, page, posts, links, Google
Requires at least: 2.0.2
Tested up to: 5.8.1
Stable tag: 3.1.5
License: GPLv2 or later

The WP Google Fonts plugin allows you to easily add fonts from the Google Font Directory to your Wordpress theme. 

== Description ==

[Check out the latest WordPress deals for your site.](https://appsumo.com/tools/wordpress/?utm_source=sumo&utm_medium=wp-widget&utm_campaign=wp-google-fonts)  

Google's free font directory is one of the most exciting developments in web typography in a very long time. The amazing rise of this new font resource has made this plugin the most popular font plugin on Wordpress and it shows no signs of stopping. 

The WP Google Font plugin makes it even easier to use Google's free service to add high quality fonts to your Wordpress powered site. Not only does this plugin add the necessary Google code, but it also gives you the ability to assign the Google fonts to specific CSS elements of your website from within the Wordpress admin. Or if you would rather, you can target the Google fonts from your own theme's stylesheet.

Right now, the Google Directory has hundreds of great fonts, and the selection is steadily growing. As new fonts are added, we will release updates to the plugin quickly so you can start using new fonts as they become available. It truly is an exciting time to be creating websites, and I hope this plugin helps you create some great stuff. - Adrian3

== Changelog ==
Version 3.1.5
- Fix XSS vuln

Version 3.1.4
- Fix XSS vuln

Version 3.1.3
- Fix SSL cert notices on settings page for Google API requests.

Version 3.11
- Fixed a bug that affected anyone upgrading from versions 3.0 or 3.0.1 to 3.1 where your old font settings were not saved. If your settings are gone, all you have to do is select the same font in the same slot as before. The rest of your settings for that font (where it's used, custom css, etc) will still be there in many cases. In some cases you will not and you'll have to re-enter them. If they don't remember them, you can try to get them by visiting a cached version of your site on Google and copying the CSS the plugin provided in the source (view your webpage source to see it).

Version 3.1
- Rewritten back end, this time using AJAX and smarter logic so that loading the settings page is much faster 
- Fixed bug that could potentially cause browsers to stall
- updated fallback list of Google Fonts (622 families)
- Corrected some notifications that people would see if WP_DEBUG was set to true

Version 3.01
- Language support added for Arabic by Yaser Maadan (http://www.englize.com) and Slovak by Branco Radenovich (http://webhostinggeeks.com/blog/)
- Minor bug fixes
- Updated backup font list

Version 3.0
- A huge thank you to Aaron Brown (http://www.webmalama.com/) for this major plugin upgrade.
- Plugin completely reworked to dynamically sync with the ever-growing Google font library. This allows the plugin to always be current and eliminates the need for you to install an update every time Google adds new fonts.
- Increased speed (through caching and fewer calls to Google's servers)
- Added the ability to select different character sets for fonts that support this feature
- More robust handling of font weights, just check the box next to the weights you want to use.

Version 2.8
- Added 42 new font additions. As always, email me if you run into any issues with any specific fonts or font weights.

Version 2.7
- Updated plugin to include 119 new font additions from Google. 

Version 2.6
- Added another 141 fonts to keep up with Google. I can't test every font individually, so if for some reason you encounter an error please contact me and I will release an update.

Version 2.5
- Italian language support thanks to Gianni Diurno (http://gidibao.net/)
- 27 new fonts/variations added including: Abril Fatface, Adamina, Alike Angular, Antic, Changa One, Chivo, Chivo:400,900, Dorsa, Fanwood Text, Fanwood Text:400,400italic, Julee, Merienda One, Passero One, Poller One, Prata, Prociono, Sansita One, Sorts Mill Goudy, Sorts Mill Goudy:400,400italic, Spinnaker, Terminal Dosis:200, Terminal Dosis:300, Terminal Dosis, Terminal Dosis:500, Terminal Dosis:600, Terminal Dosis:700, and Terminal Dosis:800.

Version 2.4
- French language support thanks to Frédéric Serva (www.fredserva.fr)
- Thanks to Aaron Brown (http://www.webmalama.com/) for finding a bug that added extra code when off wasn't explicitly selected on some fonts. 
- Added support for 43 new fonts including: Abel, Actor, Aldrich, Alice, Alike, Andika, Aubrey, Black Ops One, Carme, Comfortaa, Coustard, Days One, Delius, Delius Swash Caps, Delius Unicase, Federo, Gentium Basic, Gentium Book Basic, Gentium+Book+Basic:400,400italic,700,700italic, Geostar, Geostar Fill, Gloria Hallelujah, Kelly Slab, Leckerli One, Marvel, Monoton, Montez, Numans, Ovo, Pompiere, Questrial, Rationale, Rochester, Rosario, Short Stack, Smokum, Snippet, Tulpen One, Unna, Vidaloka, Volkhov, Voltaire, and Yellowtail.

Version 2.3
- Confirmed compatibility with 3.2.1
- Significantly reduced size of plugin file. 
- Simplified update process so future versions of this plugin can be released quicker as Google Fonts releases more and more fonts
- Added support for more fonts including: Artifika, Lora, Kameron, Cedarville Cursive, Zeyada, La Belle Aurore, Shadows Into Light, Lobster Two, Nixie One, Goblin One, Varela, Redressed, Asset, Gravitas One, Hammersmith One, Stardos Stencil, Loved by the King, Love Ya Like A Sister, Bowlby One SC, Forum, Varela Round, Patrick Hand, Yeseva One, Give You Glory, Bowlby One, Modern Antiqua, and Istok Web.

Version 2.2
- Added support for 11 more fonts

Version 2.1
- Added support for 38 more fonts including: Aclonica, Annie Use Your Telescope, Bangers, Bigshot One, Carter One, Damion, Dawning of a New Day, Didact Gothic, Francois One, Holtwood One SC, Judson, Mako, Megrim, Metrophobic, Michroma, Miltonian Tattoo, Miltonian, Momofett, News Cycle, Nova Square, Open Sans , Open Sans Condensed, Over the Rainbow, Paytone One, Play, Quattrocento Sans, Rokkitt, Shanti, Sigmar One, Smythe, Special Elite, Sue Ellen Francisco, Swanky and Moo Moo, Terminal Dosis Light, The Girl Next Door, Ultra, Waiting for the Sunrise, and Wallpoet.

- Now works on https sites using the "Wordpress HTTPS" plugin thanks to the help of Pete Toborek.

Version 2.0
- Added support for EB Garamond, Nova Slim, Nova Script, Nova Round, Nova Oval, Nova Mono, Nova Flat, Nova Cut, Oswald, and Six Caps.

Version 1.9
- Added support for another 22 fonts: Amaranth, Anton, Architects Daughter, Astloch, Bevan, Cabin Sketch, Candal, Dancing Script, Expletus Sans, Goudy Bookletter 1911, Indie Flower, Irish Grover, Kreon, League Script, Meddon, MedievalSharp, Pacifico, PT Serif, PT Serif Caption, Quattrocento, Radley, VT323. Also confirmed compatibility Wordpress 3.1

Version 1.8
- Added support for another 18 Google Font additions: Calligraffitti, Cherry Cream Soda, Chewy, Coming Soon, Crafty Girls, Crushed, Fontdiner Swanky, Homemade Apple, Irish Growler, Kranky, Luckiest Guy, Permenant Marker, Rock Salt, Schoolbell, Slackey, Sunshiney, Unkempt, and Walter Turncoat. Also added support for full family of Vollkorn. Tested with Wordpress 3.0.4

Version 1.7
- Added support for another 5 Google Font additions: Buda, Corben, Gruppo, Lekton, and Ubuntu


Version 1.6
- Added support for 15 new Google Font additions: Allan, Anonymous Pro, Cabin, Copse, Just Another Hand, Kenia, Kristi, Lato, Maiden Orange, Merriweather, Mountains of Christmas, Orbitron, Sniglet, Syncopate, and Vibur

Version 1.5
- Added support for 17 new Google Font additions: Allerta, Allerta Stencil, Arimo, Arvo, Bentham, Coda, Cousine, Covered By Your Grace, Geo, Josefin Sans (new weights), Josefin Slab, Just Me Again Down Here
Puritan, Raleway, Tinos, UnifrakturCook, and UnifrakturMaguntia

Version 1.4 
- Added support for the 5 new Google Font additions: Cuprum, Neucha, Neutron, Philosopher, and PT Sans. 

Version 1.3 
- Corrected W3 validation errors. Added logo to the Wordpress control panel screen. 

Version 1.2 
- Added missing variants for IM Fell, moved things around to make it easier to update this plugin as Google Adds more fonts, generally cleaned up code.

Version 1.1
- Added support for different font weights (variants). Still missing a couple IM Fell variants but will add them as soon as I get a chance.

Version 1.0
- The first official release of the plugin.


== Installation ==
You can install the plugin from the "Add New" tab under the plugins section of your Wordpress admin. Or, upload the plugin folder to your server and do it the old fashioned way.


== Frequently Asked Questions ==

= How do I learn more about Google Fonts? =

Visit the Google Font Directory website at http://code.google.com/webfonts.

= Who can I contact with questions?" =

You can contact me (Adrian3) at http://adrian3.com/contact


== Screenshots ==
1. This screenshot shows the "settings" panel for the WP Google Fonts plugin.