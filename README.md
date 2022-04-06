## UniFi Controller API client class

A PHP class that provides access to Ubiquiti's [**UniFi Network Controller**](https://unifi-network.ui.com/) API.

UniFi Network Controller software versions 4.X.X, 5.X.X, 6.X.X, and 7.X.X (version 7.0.23 has been confirmed to work)
are supported as well as UniFi OS-based controllers. This class is used by our API browser tool which can be found
[here](https://github.com/Art-of-WiFi/UniFi-API-browser).

The package can be installed manually or by using
composer/[packagist](https://packagist.org/packages/art-of-wifi/unifi-api-client) for
easy inclusion in your projects.

## Requirements

- a server with:
  - PHP 5.5.0 or higher 
  - PHP json and PHP cURL modules
  - tested on Apache 2.4 with PHP 5.6.1 and cURL 7.42.1 and with PHP 7.4.9 and cURL 7.68.0
- direct network connectivity between this server and the host and port (usually TCP port 8443 or port 443 for 
  UniFi OS) where the UniFi Controller is running
- you must use **accounts with local access**, not UniFi Cloud accounts, to access the UniFi Controller API 
  through this class

## UniFi OS Support

Support for UniFi OS-based controllers has been added as of version 1.1.47:
- UniFi Dream Router (UDR)
- UniFi Dream Machine (UDM)
- UniFi Dream Machine Pro (UDM PRO)
- Cloud Key Gen2 (UCK G2), firmware version 2.0.24 or higher
- Cloud Key Gen2 Plus (UCK G2 Plus), firmware version 2.0.24 or higher

The class automatically detects UniFi OS-based controllers and adjusts URLs and several functions/methods accordingly.
If your own code implements strict validation of the URL that is passed to the constructor, please adapt your
logic to allow URLs without a port suffix or with port 443 when working with a UniFi OS-based controller.

Please test all methods you plan on using thoroughly before using the API Client with
UniFi OS devices in a production environment.

## Installation

Use [Composer](#composer), [Git](#git) or simply [Download the Release](#download-the-release) to install the 
API client class.

### Composer

The preferred installation method is through [composer](https://getcomposer.org). 
Follow these [installation instructions](https://getcomposer.org/doc/00-intro.md) if you don't have composer
installed already.

Once composer is installed, simply execute this command from the shell in your project
directory:

```sh
composer require art-of-wifi/unifi-api-client
```

Or manually add the package to your composer.json file:

```javascript
{
    "require": {
        "art-of-wifi/unifi-api-client": "^1.1"
    }
}
```

Finally, be sure to include the autoloader in your code:

```php
require_once 'vendor/autoload.php';
```

### Git

Execute the following `git` command from the shell in your project directory:

```sh
git clone https://github.com/Art-of-WiFi/UniFi-API-client.git
```

When git is done cloning, include the file containing the class like so in your code:

```php
require_once 'path/to/src/Client.php';
```

### Download the Release

If you prefer not to use composer or git,
simply [download the package](https://github.com/Art-of-WiFi/UniFi-API-client/archive/master.zip), unpack the zip
file, then include the file containing the class in your code like so:

```php
require_once 'path/to/src/Client.php';
```

## Example usage

A basic example how to use the class:

```php
/**
 * load the class using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * initialize the UniFi API connection class, log in to the controller and request the alarms collection
 * (this example assumes you have already assigned the correct values to the variables used)
 */
$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, true);
$login            = $unifi_connection->login();
$results          = $unifi_connection->list_alarms(); // returns a PHP array containing alarm objects
```

Please refer to the `examples/` directory for some more detailed examples which can be used as a starting point for your
own PHP code.

#### IMPORTANT NOTES:

1. In the above example, `$site_id` is the short site "name" (usually 8 characters long) that is visible in the URL when
   managing the site in the UniFi Network Controller. For example with this URL:

   `https://<controller IP address or FQDN>:8443/manage/site/jl3z2shm/dashboard`

   `jl3z2shm` is the short site "name" and the value to assign to $site_id.

2. The 6th optional parameter that is passed to the constructor in the above example (`true`), enables validation of
   the controller's SSL certificate which is otherwise **disabled** by default. It is **highly recommended** to enable
   this feature in production environments where you have a valid SSL cert installed on the UniFi Controller that is
   associated with the FQDN in the `controller_url` parameter. This option was added with API client version 1.1.16.

3. Using an administrator account (`$controller_user` in the above example) with **read-only** permissions can limit 
   visibility on certain collection/object properties. See this
   [issue](https://github.com/Art-of-WiFi/UniFi-API-client/issues/129) and this 
   [issue](https://github.com/Art-of-WiFi/UniFi-API-browser/issues/94) for an example where the WPA2 password isn't
   visible for **read-only** administrator accounts.

## Functions/methods supported

The class currently supports the following functions/methods to GET/POST/PUT/DELETE data
through the UniFi Controller API. Please refer to the comments in the source code for
more details on each of the functions/methods and their respective parameters.

- login()
- logout()
- adopt_device()
- archive_alarm()
- assign_existing_admin()
- authorize_guest()
- block_sta()
- cancel_rolling_upgrade()
- check_controller_update()
- check_firmware_update()
- cmd_stat()
- count_alarms()
- create_apgroup() (supported with controller versions 6.0.X and higher)
- create_dynamicdns()
- create_firewallgroup()
- create_hotspotop()
- create_network()
- create_radius_account()
- create_site()
- create_user()
- create_usergroup()
- create_voucher()
- create_wlan()
- custom_api_request()
- delete_apgroup() (supported with controller versions 6.0.X and higher)
- delete_device()
- delete_firewallgroup()
- delete_network()
- delete_radius_account()
- delete_site()
- delete_usergroup()
- delete_wlan()
- disable_ap()
- edit_apgroup() (supported with controller versions 6.0.X and higher)
- edit_client_fixedip()
- edit_client_name()
- edit_firewallgroup()
- edit_usergroup()
- extend_guest_validity()
- forget_sta() (supported on controller version 5.9.X and higher)
- invite_admin()
- led_override()
- list_admins()
- list_alarms()
- list_all_admins()
- list_apgroups() (supported with controller versions 6.0.X and higher)
- list_aps() (deprecated but still available as alias)
- list_backups()
- list_clients()
- list_country_codes()
- list_current_channels()
- list_dashboard()
- list_device_name_mappings()
- list_devices()
- list_dpi_stats()
- list_dynamicdns()
- list_events()
- list_extension()
- list_firewallgroups()
- list_firmware()
- list_guests()
- list_health()
- list_hotspotop()
- list_known_rogueaps()
- list_networkconf()
- list_portconf()
- list_portforward_stats()
- list_portforwarding()
- list_radius_accounts() (supported on controller version 5.5.19 and higher)
- list_radius_profiles()
- list_rogueaps()
- list_routing()
- list_self()
- list_settings()
- list_sites()
- list_tags() (supported on controller version 5.5.19 and higher)
- list_usergroups()
- list_users()
- list_wlan_groups()
- list_wlanconf()
- locate_ap()
- move_device()
- power_cycle_switch_port()
- reboot_cloudkey()
- reconnect_sta()
- rename_ap()
- restart_ap() (deprecated but still available as alias)
- restart_device()
- revoke_admin()
- revoke_voucher()
- set_ap_radiosettings()
- set_device_settings_base()
- set_dynamicdns()
- set_element_adoption() (supported on controller version 5.13.X and higher)
- set_guestlogin_settings()
- set_guestlogin_settings_base()
- set_ips_settings_base() (supported on controller version 5.9.10 and higher)
- set_locate_ap() (deprecated but still available as alias)
- set_networksettings_base()
- set_radius_account_base()
- set_site_connectivity()
- set_site_country()
- set_site_guest_access()
- set_site_locale()
- set_site_mgmt()
- set_site_name()
- set_site_ntp()
- set_site_snmp()
- set_sta_name()
- set_sta_note()
- set_super_identity_settings_base()
- set_super_mgmt_settings_base()
- set_super_smtp_settings_base()
- set_usergroup()
- set_wlan_mac_filter()
- set_wlansettings()
- set_wlansettings_base()
- site_leds()
- site_ledsoff() (deprecated but still available as alias)
- site_ledson() (deprecated but still available as alias)
- spectrum_scan()
- spectrum_scan_state()
- start_rolling_upgrade()
- stat_5minutes_aps() (supported on controller version 5.5.X and higher)
- stat_5minutes_gateway() (supported on controller version 5.7.X and higher)
- stat_5minutes_site() (supported on controller version 5.5.X and higher)
- stat_5minutes_user (supported on controller version 5.7.X and higher)
- stat_allusers()
- stat_auths()
- stat_client()
- stat_daily_aps()
- stat_daily_gateway() (supported on controller version 5.7.X and higher)
- stat_daily_site()
- stat_daily_user() (supported on controller version 5.7.X and higher)
- stat_full_status()
- stat_hourly_aps()
- stat_hourly_gateway() (supported on controller version 5.7.X and higher)
- stat_hourly_site()
- stat_hourly_user() (supported on controller version 5.7.X and higher)
- stat_ips_events() (supported on controller version 5.9.10 and higher)
- stat_monthly_aps()
- stat_monthly_gateway()
- stat_monthly_site()
- stat_monthly_user()
- stat_payment()
- stat_sessions()
- stat_sites()
- stat_speedtest_results()
- stat_sta_sessions_latest()
- stat_status()
- stat_sysinfo()
- stat_voucher()
- unauthorize_guest()
- unblock_sta()
- unset_locate_ap() (deprecated but still available as alias)
- upgrade_device()
- upgrade_device_external()

Other functions, getters/setters:

- get_class_version()
- get_cookie() (renamed from getcookie(), deprecated but still available, use get_cookies() instead)
- get_cookies()
- get_curl_connection_timeout()
- get_curl_http_version()
- get_curl_method()
- get_curl_request_timeout()
- get_curl_request_timeout()
- get_curl_ssl_verify_host()
- get_curl_ssl_verify_peer()
- get_debug()
- get_is_unifi_os()
- get_last_error_message()
- get_last_results_raw()
- get_site()
- set_connection_timeout()
- set_cookies()
- set_curl_http_version()
- set_curl_request_timeout()
- set_curl_ssl_verify_host()
- set_curl_ssl_verify_peer()
- set_debug()
- set_is_unifi_os()
- set_request_method()
- set_request_timeout()
- set_site()

## Need help or have suggestions?

There is still work to be done to add functionality and further improve the usability of
this class, so all suggestions/comments are welcome. Please use the GitHub
[issue list](https://github.com/Art-of-WiFi/UniFi-API-client/issues) or the Ubiquiti
Community forums (https://community.ubnt.com/t5/UniFi-Wireless/PHP-class-to-access-the-UniFi-controller-API-updates-and/td-p/1512870)
to share your suggestions and questions.

## Contribute

If you would like to contribute code (improvements), please open an issue and include
your code there or else create a pull request.

## Credits

This class is based on the initial work by the following developers:

- domwo: https://community.ui.com/questions/little-php-class-for-unifi-api/933d3fb3-b401-4499-993a-f9af079a4a3a
- fbagnol: https://github.com/fbagnol/class.unifi.php

and the API as published by Ubiquiti:

- https://dl.ui.com/unifi/7.0.23/unifi_sh_api

## Important Disclaimer

Many of the functions in this API client class are not officially supported by Ubiquiti
and as such, may not be supported in future versions of the UniFi Controller API.
