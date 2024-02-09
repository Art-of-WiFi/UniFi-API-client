## UniFi Controller API client class

A PHP class that provides access to Ubiquiti's [**UniFi Network Application**](https://unifi-network.ui.com/) API.

UniFi Network Application software versions 5.X.X, 6.X.X, 7.X.X, and 8.X.X (version **8.1.104** has been confirmed to work)
are supported as well as Network Applications on **UniFi OS-based consoles** (UniFi OS **3.2.12** has been confirmed to work).
This class is used by our API Browser tool, which can be found
[here](https://github.com/Art-of-WiFi/UniFi-API-browser).

The package can be installed manually or by using
composer/[packagist](https://packagist.org/packages/art-of-wifi/unifi-api-client) for
easy inclusion in your projects.


## Requirements

- a server with:
  - PHP **7.4.0** or higher (use version [1.1.83](https://github.com/Art-of-WiFi/UniFi-API-client/releases/tag/v1.1.83) for PHP 7.3.x and lower)
  - PHP json and PHP cURL modules
  - tested on Apache 2.4 with PHP 7.4.27 and cURL 7.60.0 and with PHP 8.2.12 and cURL 7.81.0
- direct network connectivity between this server and the host and port (usually TCP port 8443 or port 443 for 
  UniFi OS) where the UniFi Controller is running
- you **must** use **accounts with local access permissions** to access the UniFi Controller API through this class
- do not use UniFi Cloud accounts and do not enable 2FA for the accounts that you use with this class


## UniFi OS Support

Support for UniFi OS-based controllers has been added as of version 1.1.47. These devices have been verified to work:
- UniFi Dream Router (UDR)
- UniFi Dream Machine (UDM)
- UniFi Dream Machine Pro (UDM PRO)
- UniFi Cloud Key Gen2 (UCK G2), firmware version 2.0.24 or higher
- UniFi Cloud Key Gen2 Plus (UCK G2 Plus), firmware version 2.0.24 or higher
- UniFi Cloud Console, details [here](https://help.ui.com/hc/en-us/articles/4415364143511)
- UniFi Express (UX)

The class automatically detects UniFi OS consoles and adjusts the URLs and several functions/methods accordingly.

UniFi OS consoles require you to connect using port **443** instead of **8443** which is used for "software-based"
controllers. If your own code implements strict validation of the URL that is passed to the constructor, please adapt
your logic to allow URLs without a port suffix or with port 443 when working with a UniFi OS-based controller.


### Remote API access to UniFi OS-based controllers
When connecting to a UniFi OS gateway through the WAN interface, you need to create a specific firewall rule to
allow this. See this blog post on the Art of WiFi website for more details:
https://artofwifi.net/blog/how-to-access-the-unifi-controller-by-wan-ip-or-hostname-on-a-udm-pro

The "custom firewall rule" approach described there is the recommended method.


## Upgrading from a previous version

When upgrading from a version before **1.1.84**, please:
- make sure you are using PHP **7.4** or higher
- test the client with your code for any breaking changes; the class methods now have strict parameter type hints and 
  response types which can break your code in certain cases where the wrong type is passed or a different response type
  is expected back


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

Finally, be sure to include the composer autoloader in your code if your framework doesn't already do this for you:

```php
/**
 * load the class using the composer autoloader
 */
require_once 'vendor/autoload.php';
```


### Git

Execute the following `git` command from the shell in your project directory:

```sh
git clone https://github.com/Art-of-WiFi/UniFi-API-client.git
```

When git is done cloning, include the file containing the class like so in your code:

```php
/**
 * load the class directly instead of using the composer autoloader
 */
require_once 'path/to/src/Client.php';
```


### Download the Release

If you prefer not to use composer or git,
simply [download the package](https://github.com/Art-of-WiFi/UniFi-API-client/archive/master.zip), unpack the zip
file, then include the file containing the class in your code like so:

```php
/**
 * load the class directly instead of using the composer autoloader
 */
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

The class currently supports the following functions/methods to access the UniFi Controller API. This list is sorted
alphabetically. Please refer to the comments in the source code for more details on each of the functions/methods,
their purpose, and their respective parameters.

- adopt_device()
- advanced_adopt_device()
- archive_alarm()
- assign_existing_admin()
- authorize_guest()
- block_sta()
- cancel_rolling_upgrade()
- check_controller_update()
- check_firmware_update()
- cmd_stat()
- count_alarms()
- create_apgroup()
- create_dynamicdns()
- create_firewallgroup()
- create_hotspotop()
- create_network()
- create_radius_account()
- create_user()
- create_usergroup()
- create_voucher()
- create_wlan()
- custom_api_request()
- delete_apgroup()
- delete_device()
- delete_firewallgroup()
- delete_network()
- delete_radius_account()
- delete_site()
- delete_usergroup()
- delete_wlan()
- disable_ap()
- disable_wlan()
- edit_apgroup()
- edit_client_fixedip()
- edit_client_name()
- edit_firewallgroup()
- edit_usergroup()
- extend_guest_validity()
- forget_sta()
- generate_backup()
- generate_backup_site()
- get_class_version()
- get_cookie()
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
- invite_admin()
- led_override()
- list_admins()
- list_all_admins()
- list_alarms()
- list_aps()
- list_backups()
- list_clients()
- list_country_codes()
- list_current_channels()
- list_dashboard()
- list_device_name_mappings()
- list_device_states()
- list_devices()
- list_devices_basic()
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
- list_radius_accounts()
- list_radius_profiles()
- list_self()
- list_settings()
- list_sites()
- list_tags()
- list_users()
- list_wlan_groups()
- list_wlanconf()
- locate_ap()
- login()
- logout()
- move_device()
- power_cycle_switch_port()
- reboot_cloudkey()
- rename_ap()
- revoke_admin()
- revoke_voucher()
- set_ap_radiosettings()
- set_ap_wlangroup()
- set_connection_timeout()
- set_cookies()
- set_curl_http_version()
- set_curl_request_timeout()
- set_curl_ssl_verify_host()
- set_curl_ssl_verify_peer()
- set_debug()
- set_device_settings_base()
- set_dynamicdns()
- set_element_adoption()
- set_guestlogin_settings()
- set_guestlogin_settings_base()
- set_ips_settings_base()
- set_is_unifi_os()
- set_locate_ap() (deprecated but still available as alias)
- set_networksettings_base()
- set_radius_account_base()
- set_request_method()
- set_request_timeout()
- set_site()
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
- spectrum_scan()
- spectrum_scan_state()
- start_rolling_upgrade()
- stat_5minutes_aps()
- stat_5minutes_gateway()
- stat_5minutes_site()
- stat_5minutes_user()
- stat_allusers()
- stat_auths()
- stat_client()
- stat_daily_aps()
- stat_daily_gateway()
- stat_daily_site()
- stat_daily_user()
- stat_full_status()
- stat_hourly_aps()
- stat_hourly_gateway()
- stat_hourly_site()
- stat_hourly_user()
- stat_ips_events()
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


## Need help or have suggestions?

There is still work to be done to add functionality and further improve the usability of
this class, so all suggestions/comments are welcome. Please use the GitHub
[Issues section](https://github.com/Art-of-WiFi/UniFi-API-client/issues) or the Ubiquiti
Community forums (https://community.ubnt.com/t5/UniFi-Wireless/PHP-class-to-access-the-UniFi-controller-API-updates-and/td-p/1512870)
to share your suggestions and questions.


#### IMPORTANT NOTE:
When encountering issues with the UniFi API using other libraries, cURL or Postman, please do **not** open an Issue. Such issues will be closed immediately.
Please use the [Discussions](https://github.com/Art-of-WiFi/UniFi-API-client/discussions) section instead.


## Contribute

If you would like to contribute code (improvements), please open an issue and include
your code there or else create a pull request.


## Credits

This class is based on the initial work by the following developers:

- domwo: https://community.ui.com/questions/little-php-class-for-unifi-api/933d3fb3-b401-4499-993a-f9af079a4a3a
- fbagnol: https://github.com/fbagnol/class.unifi.php

and the API as published by Ubiquiti:

- https://dl.ui.com/unifi/8.0.26/unifi_sh_api


## Important Disclaimer

Many of the functions in this API client class are not officially supported by Ubiquiti
and as such, may not be supported in future versions of the UniFi Controller API.
