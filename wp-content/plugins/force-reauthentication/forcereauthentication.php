<?php
/*
Plugin Name: Force ReAuthentication
Version: 1.3.1
Plugin URI: https://github.com/shrkey/forcereauthentication
Description: Allows the admin user to force user accounts to reauthenticate
Author: Shrkey
Author URI: http://shrkey.com
Text_domain: shrkey

Copyright 2013 (email: team@shrkey.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Include the plugin functions
require_once('includes/functions.php');

if( is_admin() ) {
	require_once('classes/admin.forcereauthentication.php');
}

// We need to add this part so we always log out regardless
require_once('classes/public.forcereauthentication.php');