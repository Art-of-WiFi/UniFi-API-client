## UniFi Controller API client class

A PHP class which provides access to Ubiquiti's **UniFi Controller API**, versions 4.x.x and 5.x.x of the UniFi Controller software are supported (version 5.6.18 has been confirmed to work). It's a standalone version of the class which is used in our API browser tool which can be found [here](https://github.com/Art-of-WiFi/UniFi-API-browser).

This class can be installed using composer/[packagist](https://packagist.org/packages/art-of-wifi/unifi-api-client) for easy inclusion in your projects.

### Donations

If you find this PHP API client class useful and wish to support it's further development, please use the PayPal donate button below. All donations go to the project maintainer.

[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=M7TVNVX3Z44VN)

## Methods and functions supported

The class currently supports the following functions/methods to get/post/put/delete data through the UniFi Controller API:
- login()
- logout()
- adopt_device()
- archive_alarm()
- authorize_guest()
- block_sta()
- count_alarms()
- create_hotspotop()
- create_network()
- create_radius_account()
- create_site()
- create_usergroup()
- create_voucher()
- create_wlan()
- delete_network()
- delete_radius_account()
- delete_site()
- delete_usergroup()
- delete_wlan()
- disable_ap()
- edit_usergroup()
- extend_guest_validity()
- led_override()
- list_admins()
- list_alarms()
- list_aps() (deprecated but still available as alias)
- list_clients()
- list_current_channels()
- list_dashboard()
- list_devices()
- list_dpi_stats()
- list_dynamicdns()
- list_events()
- list_extension()
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
- list_self()
- list_settings()
- list_sites()
- list_tags() (supported on controller version 5.5.19 and higher)
- list_usergroups()
- list_users()
- list_wlan_groups()
- list_wlanconf()
- locate_ap()
- power_cycle_switch_port()
- reconnect_sta()
- rename_ap()
- restart_ap()
- revoke_voucher()
- set_ap_radiosettings()
- set_device_settings_base()
- set_guestlogin_settings()
- set_locate_ap() (deprecated but still available as alias)
- set_networksettings_base()
- set_radius_account_base()
- set_sta_name()
- set_sta_note()
- set_usergroup()
- set_wlan_mac_filter()
- set_wlansettings()
- set_wlansettings_base()
- site_leds()
- site_ledsoff() (deprecated but still available as alias)
- site_ledson() (deprecated but still available as alias)
- spectrum_scan()
- spectrum_scan_state()
- stat_allusers()
- stat_auths()
- stat_client()
- stat_5minutes_aps() (supported on controller version 5.5.* and higher)
- stat_hourly_aps()
- stat_daily_aps()
- stat_5minutes_site() (supported on controller version 5.5.* and higher)
- stat_hourly_site()
- stat_daily_site()
- stat_payment()
- stat_sessions()
- stat_sites()
- stat_sta_sessions_latest()
- stat_status()
- stat_sysinfo()
- stat_voucher()
- unauthorize_guest()
- unblock_sta()
- unset_locate_ap() (deprecated but still available as alias)
- upgrade_device()
- upgrade_device_external()

Internal functions, getters/setters:
- set_debug()
- set_site()
- set_site()
- get_site()
- get_cookie() (renamed from getcookie())
- get_last_results_raw()
- get_last_error_message()

Please refer to the source code for more details on the functions/methods and their parameters.

## Requirements

- a web server with PHP and cURL modules installed (tested on apache2 with PHP Version 5.6.1 and cURL 7.42.1)
- network connectivity between this web server and the server and port (normally TCP port 8443) where the UniFi Controller is running

## Installation ##

You can use [Composer](#composer), [Git](#git) or simply [Download the Release](#download-the-release) to install the API client class.

### Composer

The preferred method is via [composer](https://getcomposer.org). Follow the [installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have composer installed.

Once composer is installed, simply execute this command from the shell in your project directory:

```sh
composer require art-of-wifi/unifi-api-client
```

 Or you can manually add the package to your composer.json file:

```javascript
{
    "require": {
        "art-of-wifi/unifi-api-client": "^1.1"
    }
}
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
$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, true);
$login            = $unifi_connection->login();
$results          = $unifi_connection->list_alarms(); // returns a PHP array containing alarm objects
```

Please refer to the `examples/` directory for some more detailed examples which you can use as a starting point for your own PHP code.

#### IMPORTANT NOTES:

1. The last parameter (`true`) that is passed to the constructor, enables validation of the controller's SSL certificate which is otherwise **disabled** by default. It is highly recommended to enable this feature in production environments where you have a valid SSL cert installed on the UniFi Controller, and which is associated with the FQDN of the server as used in the `controller_url` parameter. This option was added with API client version 1.1.16.

2. In the example above, `$site_id` is the 8 character short site "name" which is visible in the URL when managing the site in the UniFi Controller:

...`https://<controller IP address or FQDN>:8443/manage/site/jl3z2shm/dashboard`

...In this case, `jl3z2shm` is the value required for $site_id.

## Need help or have suggestions?

There is still work to be done to add functionality and improve the usability of this class, so all suggestions/comments are welcome. Please use the github [issue](https://github.com/Art-of-WiFi/UniFi-API-client/issues) list or the Ubiquiti Community forums (https://community.ubnt.com/t5/UniFi-Wireless/PHP-class-to-access-the-UniFi-controller-API-updates-and/td-p/1512870) to share your ideas/questions.

## Contribute

If you would like to contribute code (improvements), please open an issue and include your code there or else create a pull request.

## Credits

This class is largely based on the work done by the following developers:
- domwo: http://community.ubnt.com/t5/UniFi-Wireless/little-php-class-for-unifi-api/m-p/603051
- fbagnol: https://github.com/fbagnol/class.unifi.php
- and the API as published by Ubiquiti: https://dl.ubnt.com/unifi/5.6.18-8261dc5066/unifi_sh_api

## Important Disclaimer

Many of the functions in this API client class are not officially supported by UBNT and as such, may not be supported in future versions of the UniFi Controller API.
