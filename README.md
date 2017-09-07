## UniFi controller API client class

This PHP class provides access to Ubiquiti's **UniFi Controller API**. Versions 4.x.x and 5.x.x of the UniFi Controller software (version 5.5.20 has been confirmed to work) are supported. It is an independent version of the class which is used in the API browser tool [here](https://github.com/Art-of-WiFi/UniFi-API-browser).

### Donations

If you'd like to support further development of this PHP API client class, please use the PayPal donate button below. All donations go to the project maintainer.

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=M7TVNVX3Z44VN)

## Methods and functions supported

This class currently supports the following functions/methods to get/set data through the UniFi controller API:
- login()
- logout()
- add_site()
- adopt_device()
- authorize_guest()
- unauthorize_guest()
- block_sta()
- unblock_sta()
- create_hotspotop()
- create_voucher()
- delete_site()
- disable_ap()
- led_override()
- list_admins()
- list_alarms()
- count_alarms()
- upgrade_device()
- upgrade_device_external()
- spectrum_scan()
- spectrum_scan_state()
- list_devices()
- list_aps() (deprecated but still available as alias)
- list_clients()
- list_dashboard()
- list_dynamicdns()
- list_events()
- list_extension()
- list_guests()
- list_health()
- list_hotspotop()
- list_networkconf()
- list_portconf()
- list_portforward_stats()
- list_portforwarding()
- list_radius_accounts() (supported on controller version 5.5.19 and higher)
- list_rogueaps()
- list_self()
- list_settings()
- list_sites()
- list_tags() (supported on controller version 5.5.19 and higher)
- list_usergroups()
- list_users()
- list_wlan_groups()
- list_wlanconf()
- list_current_channels()
- list_dpi_stats()
- reconnect_sta()
- rename_ap()
- restart_ap()
- revoke_voucher()
- extend_guest_validity()
- set_ap_radiosettings()
- set_guestlogin_settings()
- locate_ap()
- set_locate_ap() (deprecated but still available as alias)
- unset_locate_ap() (deprecated but still available as alias)
- set_sta_name()
- set_sta_note()
- set_usergroup()
- edit_usergroup()
- add_usergroup()
- delete_usergroup()
- edit_usergroup()
- add_usergroup()
- delete_usergroup()
- set_wlansettings_base()
- set_wlansettings()
- create_wlan()
- delete_wlan()
- set_wlan_mac_filter()
- site_leds()
- site_ledsoff() (deprecated but still available as alias)
- site_ledson() (deprecated but still available as alias)
- stat_allusers()
- stat_auths()
- stat_client()
- stat_daily_site()
- stat_daily_aps()
- stat_hourly_aps()
- stat_hourly_site()
- stat_payment()
- stat_sessions()
- stat_sites()
- stat_sta_sessions_latest()
- stat_sysinfo()
- stat_voucher()

Internal functions, getters/setters:
- set_debug()
- set_site()
- get_site()
- get_cookie() (renamed from getcookie())
- get_last_results_raw()
- get_last_error_message()

Please refer to the source code for more details on each function/method and it's parameters.

## Credits

This class is largely based on the work done by the following developers:
- domwo: http://community.ubnt.com/t5/UniFi-Wireless/little-php-class-for-unifi-api/m-p/603051
- fbagnol: https://github.com/fbagnol/class.unifi.php
- and the API as published by Ubiquiti: https://www.ubnt.com/downloads/unifi/5.5.20/unifi_sh_api

## Requirements

- a web server with PHP and cURL modules installed (tested on apache2 with PHP Version 5.6.1 and cURL 7.42.1)
- network connectivity between this web server and the server and port (normally port 8443) where the UniFi controller is running

## Installation ##

You can use **Composer**, **Git** or simply **Download the Release**

### Composer

The preferred method is via [composer](https://getcomposer.org). Follow the [installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have composer installed.

Once composer is installed, simply execute this command from your project directory:

```sh
composer require art-of-wifi/unifi-api-client
```

Finally, be sure to include the autoloader in your code:

```php
require_once('vendor/autoload.php');
```

### Git

Execute the following `git` command from the shell in your project directory:

```sh
git clone https://github.com/Art-of-WiFi/UniFi-API-client.git
```

When git is done cloning, include the file containing the class like so in your code:

```php
require_once('path/to/src/Client.php');
```

### Download the Release

If you prefer not to use composer or git, you can simply [download the package](https://github.com/Art-of-WiFi/UniFi-API-client/archive/master.zip), uncompress the zip file, then include the file containing the class in your code like so:

```php
require_once('path/to/src/Client.php');
```

## Example usage

A basic example how to use the class:

```php
/**
 * load the class using the composer autoloader
 */
require_once('vendor/autoload.php');

/**
 * initialize the Unifi API connection class, log in to the controller and request the alarms collection
 * (this example assumes you have already assigned the correct values to the variables used)
 */
$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version);
$login            = $unifi_connection->login();
$results          = $unifi_connection->list_alarms(); // returns an PHP array containing alarm objects
```

Please refer to the `examples/` directory for some more detailed examples which you can use as a starting point for your own PHP code.

### NOTE:

In the example above, `$site_id` is the 8 character short site "name" which is visible in the URL when managing the site in the UniFi controller:

`https://<controller IP address or FQDN>:8443/manage/site/jl3z2shm/dashboard`

In this case, `jl3z2shm` is the value required for $site_id.

## Need help or have suggestions?

There is still work to be done to add functionality and improve the usability of this class, so all suggestions/comments are welcome. Please use the github [issue](https://github.com/Art-of-WiFi/UniFi-API-client/issues) list or the Ubiquiti Community forums (https://community.ubnt.com/t5/UniFi-Wireless/PHP-class-to-access-the-UniFi-controller-API-updates-and/td-p/1512870) to share your ideas.

## Contribute

If you would like to contribute code (improvements), please open an issue and include your code there or else create a pull request.

## Important Disclaimer

Many of these functions are not officially supported by UBNT and as such, may not be supported in future versions of the UniFi controller API.
