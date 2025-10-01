## UniFi Controller API client class

[![License](https://img.shields.io/github/license/Art-of-WiFi/UniFi-API-client)](https://github.com/Art-of-WiFi/UniFi-API-client/blob/main/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/art-of-wifi/unifi-api-client)](https://packagist.org/packages/art-of-wifi/unifi-api-client)
[![Downloads](https://img.shields.io/packagist/dt/art-of-wifi/unifi-api-client)](https://packagist.org/packages/art-of-wifi/unifi-api-client)
[![PHP Version](https://img.shields.io/packagist/php-v/art-of-wifi/unifi-api-client)](https://packagist.org/packages/art-of-wifi/unifi-api-client)

A PHP class that provides access to Ubiquiti's [**UniFi Network Application**](https://unifi-network.ui.com/) API.

This class is used by our API Browser tool, which can be found
[here](https://github.com/Art-of-WiFi/UniFi-API-browser).

The package can be installed manually or by using
composer/[packagist](https://packagist.org/packages/art-of-wifi/unifi-api-client) for
easy inclusion in your projects. See the [installation instructions](#Installation) below for more details.

## Supported Versions

| Software                             | Versions                                          |
|--------------------------------------|---------------------------------------------------|
| UniFi Network Application/controller | 5.x, 6.x, 7.x, 8.x, 9.x (**9.3.45 is confirmed**) |
| UniFi OS                             | 3.x, 4.1.x, 4.2.x (**4.3.6 is confirmed**)        |


## Requirements

- a server with:
  - PHP **7.4.0** or higher (use version [1.1.83](https://github.com/Art-of-WiFi/UniFi-API-client/releases/tag/v1.1.83) 
    for PHP 7.3.x and lower)
  - PHP json and PHP cURL modules enabled
- direct network connectivity between this server and the host and port (usually TCP port 8443, port 11443 for UniFi OS
  Server, or port 443 for UniFi OS consoles) where the UniFi Network Application is running
- you **must** use an admin **account with local access permissions** to access the API through this class as explained
  here: https://artofwifi.net/blog/use-local-admin-account-unifi-api-captive-portal
- do **not** use UniFi Cloud accounts and do not enable MFA/2FA for the accounts that you use with this class


## UniFi OS Support

Besides the classic "software-based" UniFi Network Application, this class also supports UniFi OS-based
controllers starting from version **1.1.47**.

These devices/services have been verified to work:
- UniFi OS Server, announcement [here](https://blog.ui.com/article/introducing-unifi-os-server)
- UniFi Dream Router (UDR)
- UniFi Dream Machine (UDM)
- UniFi Dream Machine Pro (UDM PRO)
- UniFi Cloud Key Gen2 (UCK G2), firmware version 2.0.24 or higher
- UniFi Cloud Key Gen2 Plus (UCK G2 Plus), firmware version 2.0.24 or higher
- UniFi Express (UX)
- UniFi Dream Wall (UDW)
- UniFi Cloud Gateway Ultra (UCG-Ultra)
- UniFi CloudKey Enterprise (CK-Enterprise)
- UniFi Enterprise Fortress Gateway (EFG)
- Official UniFi Hosting, details [here](https://help.ui.com/hc/en-us/articles/4415364143511)
- HostiFi UniFi Cloud Hosting, details [here](https://hostifi.com/unifi)

The class automatically detects UniFi OS consoles/servers and adjusts the URLs and several functions/methods
accordingly.

UniFi OS-based consoles require you to connect using port **443** instead of **8443** which is used for
the classic "software-based" controllers. When using **UniFi OS Server**, you are required to use port **11443**.

If your own code implements strict validation of the URL that is passed to the constructor, please adapt your logic to
allow URLs without a port suffix, with port 443 or port 11443 when working with a UniFi OS-based controller.


### Remote API access to UniFi OS-based controllers
When connecting to a UniFi OS-based gateway through the WAN interface, you need to create a specific firewall rule to
allow this. See this blog post on the Art of WiFi website for more details:
https://artofwifi.net/blog/how-to-access-the-unifi-controller-by-wan-ip-or-hostname-on-a-udm-pro

The "custom firewall rule" approach described there is the recommended method.


## Upgrading from previous versions

When upgrading from a version before **2.0.0**, please:
- change your code to use the new Exceptions that are thrown by the class
- test the client with your code for any breaking changes
- make sure you are using [Composer](#composer) to install the class because the code is no longer held within a single
  file
- see the note [here](#looking-for-version-1xx) regarding the single file version (1.x.x) of the API client


## Installation

The preferred installation method is through [Composer](https://getcomposer.org). 
Follow these [installation instructions](https://getcomposer.org/doc/00-intro.md) if you don't have Composer
installed already.

Once Composer is installed, execute this command from the shell in your project directory:

```sh
composer require art-of-wifi/unifi-api-client
```

Or manually add the package to your `composer.json` file:

```javascript
{
    "require": {
        "art-of-wifi/unifi-api-client": "^2.0"
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

## Example usage

A basic example of how to use the class:

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

#### IMPORTANT NOTES:

1. In the above example, `$site_id` is the short site "name" (usually 8 characters long) that is visible in the URL when
   managing the site in the UniFi Network Controller. For example, with this URL:

   `https://<controller IP address or FQDN>:8443/manage/site/jl3z2shm/dashboard`

   `jl3z2shm` is the short site "name" and the value to assign to $site_id.

2. The 6th optional parameter that is passed to the constructor in the above example (`true`), enables validation of
   the controller's SSL certificate, which is otherwise **disabled** by default. It is **highly recommended** to enable
   this feature in production environments where you have a valid SSL cert installed on the UniFi Controller that is
   associated with the FQDN in the `controller_url` parameter. This option was added with API client version 1.1.16.

3. Using an administrator account (`$controller_user` in the above example) with **read-only** permissions can limit 
   visibility on certain collection/object properties. See this
   [issue](https://github.com/Art-of-WiFi/UniFi-API-client/issues/129) and this 
   [issue](https://github.com/Art-of-WiFi/UniFi-API-browser/issues/94) for an example where the WPA2 password isn't
   visible for **read-only** administrator accounts.


## Exception handling

The class now throws **Exceptions** for various error conditions instead of using PHP's `trigger_error()` function. This
allows for more granular error handling in your application code.

Here is an example of how to catch these Exceptions:
```php
<?php
/**
 * PHP API usage example with Exception handling
 */
use UniFi_API\Exceptions\CurlExtensionNotLoadedException;
use UniFi_API\Exceptions\CurlGeneralErrorException;
use UniFi_API\Exceptions\CurlTimeoutException;
use UniFi_API\Exceptions\InvalidBaseUrlException;
use UniFi_API\Exceptions\InvalidSiteNameException;
use UniFi_API\Exceptions\JsonDecodeException;
use UniFi_API\Exceptions\LoginFailedException;
use UniFi_API\Exceptions\LoginRequiredException;

/**
 * load the class using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * include the config file (place your credentials etc. there if not already present)
 */
require_once 'config.php';

/**
 * initialize the UniFi API connection class, log in to the controller and request the alarms collection
 * (this example assumes you have already assigned the correct values to the variables in config.php)
 */
try {
    $unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, true);
    $login            = $unifi_connection->login();
    $results          = $unifi_connection->list_alarms(); // returns a PHP array containing alarm objects
} catch (CurlExtensionNotLoadedException $e) {
    echo 'CurlExtensionNotLoadedException: ' . $e->getMessage(). PHP_EOL;
} catch (InvalidBaseUrlException $e) {
    echo 'InvalidBaseUrlException: ' . $e->getMessage(). PHP_EOL;
} catch (InvalidSiteNameException $e) {
    echo 'InvalidSiteNameException: ' . $e->getMessage(). PHP_EOL;
} catch (JsonDecodeException $e) {
    echo 'JsonDecodeException: ' . $e->getMessage(). PHP_EOL;
} catch (LoginRequiredException $e) {
    echo 'LoginRequiredException: ' . $e->getMessage(). PHP_EOL;
} catch (CurlGeneralErrorException $e) {
    echo 'CurlGeneralErrorException: ' . $e->getMessage(). PHP_EOL;
} catch (CurlTimeoutException $e) {
    echo 'CurlTimeoutException: ' . $e->getMessage(). PHP_EOL;
} catch (LoginFailedException $e) {
    echo 'LoginFailedException: ' . $e->getMessage(). PHP_EOL;
} catch (Exception $e) {
    /** catch any other Exceptions that might be thrown */
    echo 'General Exception: ' . $e->getMessage(). PHP_EOL;
}
```

Although the PHP DocBlocks for most public methods/functions contain `@throws Exception`, it is recommended to catch
other specific Exceptions that can be thrown by the API Client class to provide more detailed error messages to your
application code.

In most cases, the class will let Exceptions bubble up to the calling code, but in some cases it will catch them and
throw a new Exception with a more specific message.

The `list_alarms.php` example in the `examples/` directory is a good starting point to see how you can implement
Exception handling.


## Functions/methods supported

The class currently supports a large and growing number of functions/methods to access the UniFi Controller API. 
Please refer to the comments/PHP DocBlocks in the source code for more details on each of the functions/methods,
their purpose, and their respective parameters.

If you are using an advanced IDE such as PHPStorm or VS Code, you can use its code completion and other
features to explore the available functions/methods thanks to the extensive PHP DocBlocks throughout the code.

For a quick overview of the available functions/methods, you can also check the API Reference here:

[API Reference](API_REFERENCE.md)


## Looking for version 1.x.x?

With versions 1.x.x of the API client, the entire client was contained within a single file which can be useful in
specific cases.
This has changed with version 2.0.0 where the code is now split across multiple files and is managed using composer.

If you are looking for the version 1.x.x code, you can tell composer to install that version by using the following
syntax in your `composer.json` file:

```javascript
{
    "require": {
        "art-of-wifi/unifi-api-client": "^1.1"
    }
}
```

Alternatively, you can download the latest 1.x.x code from the [releases page](https://github.com/Art-of-WiFi/UniFi-API-client/releases).

Whenever necessary, we will make sure to update the **version_1** branch with the latest 1.x.x code.

## Need help or have suggestions?

There is still work to be done to add functionality and further improve the usability of
this class, so all suggestions/comments are welcome. Please use the GitHub
[Issues section](https://github.com/Art-of-WiFi/UniFi-API-client/issues) or the Ubiquiti
Community forums (https://community.ubnt.com/t5/UniFi-Wireless/PHP-class-to-access-the-UniFi-controller-API-updates-and/td-p/1512870)
to share your suggestions and questions.


#### IMPORTANT NOTE:
When encountering issues with the UniFi API using other libraries, cURL or Postman, please do **not** open an Issue. Such issues will be closed immediately.
Please use the [Discussions](https://github.com/Art-of-WiFi/UniFi-API-client/discussions) section instead.


## Credits

This class is based on the initial work by the following developers:

- domwo: https://community.ui.com/questions/little-php-class-for-unifi-api/933d3fb3-b401-4499-993a-f9af079a4a3a
- fbagnol: https://github.com/fbagnol/class.unifi.php

and the API as published by Ubiquiti:

- https://dl.ui.com/unifi/8.6.9/unifi_sh_api

## Contributors

A big thanks to all the contributors who have helped with this project!

[![Contributors](https://img.shields.io/github/contributors/Art-of-WiFi/UniFi-API-client)](https://github.com/Art-of-WiFi/UniFi-API-client/graphs/contributors)

If you would like to contribute to this project, please open an issue and include
your suggestions or code there or else create a pull request.


## About Art of WiFi

Art of WiFi develops software and tools that enhance the capabilities of UniFi networks. From captive portals and
reporting solutions to device search utilities, our goal is to make UniFi deployments more powerful and easier to
manage.

If you're looking for a specific solution or just want to see what else we offer, feel free to explore our web site:
- https://www.artofwifi.net


## Important Disclaimer

Many of the functions in this API client class are not officially supported by Ubiquiti
and as such, may not be supported in future versions of the UniFi Controller API.
