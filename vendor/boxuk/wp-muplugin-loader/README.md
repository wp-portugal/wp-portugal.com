# WP Must-Use Plugin Loader

[![Build Status](https://travis-ci.org/boxuk/wp-muplugin-loader.svg?branch=master)](https://travis-ci.org/boxuk/wp-muplugin-loader)

## Attribution

- This package was forked from
  [WP Must-Use Plugin Loader](https://github.com/lkwdwrd/wp-muplugin-loader)
  due to it seemingly falling into abandonment. We are happy to contribute our work back to the source should the maintainer pick up the project again.
  
## Overview

Managing plugins using the [Composer Installers](https://github.com/composer/installers) library works remarkably well. However, its handling of MU plugins leaves something to be desired.

WordPress MU (must use) Plugins are files that are placed in the `wp-content/mu-plugins/` folder. These files are loaded automatically. The problem is when a plugin is actually inside a folder. WordPress will only load .php files and doesn't drop into any directories. When the Composer Installers plugin runs, it always puts the repo into a nice contained folder. This means the Composer Installers MU plugins never actually run!

There are manual ways around this that work fine, but I want to get away from any manual steps when running the install. No extra files, just run `composer install` or `composer update` and have it work. That is what the WP Must-Use Plugin Loader does.

## Usage Instructions

In your project's `composer.json` file, require this package.

```json
"require": {
	"composer/installers": "~1.2.0",
	"johnpbloch/wordpress": "*",
	"boxuk/wp-muplugin-loader": "~1.0",
}
```
Make sure in the `extras` of your `composer.json` you have your mu-plugins path defined.

```json
"extra": {
	"installer-paths": {
		"app/wp-content/themes/{$name}": [
			"type:wordpress-theme"
		],
		"app/wp-content/plugins/{$name}": [
			"type:wordpress-plugin"
		],
		"app/wp-content/mu-plugins/{$name}": [
			"type:wordpress-muplugin"
		]
	},
	"wordpress-install-dir": "app/wp"
}
```

And that's it.

When Composer dumps it's autoload file, a file called `mu-require.php` will be placed into your mu-plugins folder. When WordPress loads this file as an MU plugin, it will find all of the plugins in folders in your MU plugins directory and include those as well.

### Changing the name of the generated file

If you need to have control over what the name of the generated file is, you can set it with the following within the extra section of your `composer.json`:

```json
"extra": {
	...
	"mu-require-file": "custom-mu-require-filename.php"
}
```

Similarly, if you wish to turn off generation of this file altogether you can do so by setting this to false:

```json
"extra": {
	...
	"mu-require-file": false
}
```

## Forcing MU Plugins

Usually when you are using MU plugins, you have some 'normal' WordPress plugins that you want to always be active. They are not always MU-Plugins, though, so it makes no sense to put the `"type": "wordpress-muplugin"` in the `composer.json` file. WP Must-Use Plugin Loader allows you to override the type from `wordpress-plugin` to `wordpress-muplugin` as needed.

To do this, define a `"force-mu"` key in `"extra"` of your `composer.json` file. This key should hold an array of slugs for plugins to force into Must-Use status.

This is compatible with [WPackagist](https://wpackagist.org/). When adding plugins from WPackagist use the plugin's normal slug, not the wp-packagist version.

```json
"require": {
	"johnpbloch/wordpress": "*",
	"boxuk/wp-muplugin-loader": "~1.0",
	"wpackagist-plugin/rest-api": "*"
},
"extra": {
	"force-mu": [
		"rest-api"
	],
	"installer-paths": {
		"app/wp-content/themes/{$name}": [
			"type:wordpress-theme"
		],
		"app/wp-content/plugins/{$name}": [
			"type:wordpress-plugin"
		],
		"app/wp-content/mu-plugins/{$name}": [
			"type:wordpress-muplugin"
		]
	},
	"wordpress-install-dir": "app/wp"
}
```

When the `rest-api` plugin is installed, instead of going in the normal plugins folder, it will be pushed over to the mu-plugins folder and loaded automatically with other Must-Use Plugins.

## Forcing Unix Directory Separators

If you work on Windows but use a Linux VM to run your development server, you may need to force unix directory separators to make sure the server can find the mu loader script. If so, there's another configuration in the `extra` block you can set:

```json
"extra": {
	"force-unix-separator": true
}
```

## Modifying the src directory of the mu plugins

You may wish to change the src directory you want the mu plugins to be loaded from. For example, on WordPress VIP projects 
you may wish to load mu plugins from client-mu-plugins using this loader. To do this you can set a constant to tell the 
mu plugin loader where your mu plugins are kept:

```php
define('MU_PLUGIN_LOADER_SRC_DIR', WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/');
```
