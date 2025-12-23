# UniFi API Client Reference

This document provides a comprehensive reference for all public methods available in the UniFi API Client class.
We do our best to keep this document up-to-date with the latest version of the API client.

## Table of Contents
1. [Connection Management](#connection-management)
2. [Client Management](#client-management)
3. [Access Point Management](#access-point-management)
4. [AP Group Management](#ap-group-management)
5. [Site Management](#site-management)
6. [Network Management](#network-management)
7. [WLAN Configuration](#wlan-configuration)
8. [User Management](#user-management)
9. [User Group Management](#user-group-management)
10. [Guest Management](#guest-management)
11. [Voucher and Hotspot Management](#voucher-and-hotspot-management)
12. [Firewall Management](#firewall-management)
13. [Routing and Health](#routing-and-health)
14. [Device Management](#device-management)
15. [Firmware and Updates](#firmware-and-updates)
16. [Tag Management](#tag-management)
17. [Rogue AP Management](#rogue-ap-management)
18. [Events and Alarms](#events-and-alarms)
19. [Backup Management](#backup-management)
20. [Admin Management](#admin-management)
21. [WLAN Group Management](#wlan-group-management)
22. [DNS Management](#dns-management)
23. [DPI and Port Statistics](#dpi-and-port-statistics)
24. [Dynamic DNS Management](#dynamic-dns-management)
25. [RADIUS Management](#radius-management)
26. [Advanced Settings](#advanced-settings)
27. [System Management](#system-management)
28. [Statistics](#statistics)
29. [Debug and Utility Methods](#debug-and-utility-methods)

## Connection Management

### Constructor
```php
public function __construct(
    string $user,
    string $password,
    string $baseurl = 'https://127.0.0.1:8443',
    ?string $site = 'default',
    ?string $version = '8.0.28',
    bool $ssl_verify = false,
    string $unificookie_name = 'unificookie'
)
```
Creates a new instance of the UniFi API client.

**Parameters:**
- `$user` (string): Username for the UniFi controller
- `$password` (string): Password for the UniFi controller
- `$baseurl` (string): Base URL of the UniFi controller (must include 'https://' prefix, port suffix like :8443 required for non-UniFi OS controllers)
- `$site` (string|null): Short site name to access (default: 'default')
- `$version` (string|null): Controller version number (default: '8.0.28')
- `$ssl_verify` (bool): Whether to validate SSL certificate of the UniFi controller (default: false)
- `$unificookie_name` (string): Name of the cookie to use (default: 'unificookie')

**Throws:**
- `CurlExtensionNotLoadedException`
- `InvalidBaseUrlException`
- `InvalidSiteNameException`

### Login
```php
public function login(): bool
```
Logs in to the UniFi controller.

**Returns:** bool - true upon successful login

**Throws:**
- `LoginFailedException`
- `CurlTimeoutException`
- `CurlGeneralErrorException`

### Logout
```php
public function logout(): bool
```
Logs out from the UniFi controller.

**Returns:** bool - true upon success

**Throws:**
- `CurlGeneralErrorException`
- `CurlTimeoutException`

## Client Management

### List Clients
```php
public function list_clients(?string $mac = null): array
```
Lists all clients or a specific client.

**Parameters:**
- `$mac` (string|null): MAC address of the client to list

**Returns:** array - Array of client objects

### Block Client
```php
public function block_sta(string $mac): bool
```
Blocks a client from accessing the network.

**Parameters:**
- `$mac` (string): MAC address of the client to block

**Returns:** bool - true upon success

### Unblock Client
```php
public function unblock_sta(string $mac): bool
```
Unblocks a client from accessing the network.

**Parameters:**
- `$mac` (string): MAC address of the client to unblock

**Returns:** bool - true upon success

### Reconnect Client
```php
public function reconnect_sta(string $mac): bool
```
Reconnects a client to the network.

**Parameters:**
- `$mac` (string): MAC address of the client to reconnect

**Returns:** bool - true upon success

### Forget Client
```php
public function forget_sta(string $mac): bool
```
Removes a client from the controller's memory.

**Parameters:**
- `$mac` (string): MAC address of the client to forget

**Returns:** bool - true upon success

### Set Client Note
```php
public function set_sta_note(string $user_id, string $note = ''): bool
```
Sets a note for a specific client.

**Parameters:**
- `$user_id` (string): ID of the client
- `$note` (string): Note to set (default: empty string)

**Returns:** bool - true upon success

### Set Client Name
```php
public function set_sta_name(string $user_id, string $name = ''): bool
```
Sets the name for a specific client.

**Parameters:**
- `$user_id` (string): ID of the client
- `$name` (string): Name to set (default: empty string)

**Returns:** bool - true upon success

### List Active Clients
```php
public function list_active_clients(bool $include_traffic_usage = true, bool $include_unifi_devices = true): array
```
Lists all currently active clients.

**Parameters:**
- `$include_traffic_usage` (bool): Whether to include traffic usage information (default: true)
- `$include_unifi_devices` (bool): Whether to include UniFi devices (default: true)

**Returns:** array - Array of active client objects

### List Client History
```php
public function list_clients_history(bool $only_non_blocked = true, bool $include_unifi_devices = true, int $within_hours = 0): array
```
Lists historical client information.

**Parameters:**
- `$only_non_blocked` (bool): Whether to only show non-blocked clients (default: true)
- `$include_unifi_devices` (bool): Whether to include UniFi devices (default: true)
- `$within_hours` (int): Hours of history to retrieve (default: 0)

**Returns:** array - Array of historical client objects

### List Fingerprint Devices
```php
public function list_fingerprint_devices(int $fingerprint_source = 0): array
```
Lists devices identified by fingerprinting.

**Parameters:**
- `$fingerprint_source` (int): Source of fingerprint data (default: 0)

**Returns:** array - Array of fingerprint device objects

### Set Client User Group
```php
public function set_usergroup(string $client_id, string $group_id): bool
```
Sets the user group for a client.

**Parameters:**
- `$client_id` (string): ID of the client
- `$group_id` (string): ID of the user group to assign

**Returns:** bool - true upon success

### Edit Client Fixed IP
```php
public function edit_client_fixedip(string $client_id, bool $use_fixedip, ?string $network_id = null, ?string $fixed_ip = null): bool
```
Configures fixed IP settings for a client.

**Parameters:**
- `$client_id` (string): ID of the client
- `$use_fixedip` (bool): Whether to use a fixed IP
- `$network_id` (string|null): ID of the network to use
- `$fixed_ip` (string|null): Fixed IP address to assign

**Returns:** bool - true upon success

### Edit Client Name
```php
public function edit_client_name(string $client_id, string $name): bool
```
Edits the name of a client.

**Parameters:**
- `$client_id` (string): ID of the client
- `$name` (string): New name for the client

**Returns:** bool - true upon success

## Access Point Management

### List Access Points
```php
public function list_aps(?string $mac = null): array
```
Lists all access points or a specific access point.

**Parameters:**
- `$mac` (string|null): MAC address of the AP to list

**Returns:** array - Array of access point objects

### Set AP Radio Settings
```php
public function set_ap_radiosettings(string $ap_id, string $radio, int $channel, int $ht, string $tx_power_mode, int $tx_power): bool
```
Configures radio settings for an access point.

**Parameters:**
- `$ap_id` (string): ID of the access point
- `$radio` (string): Radio to configure ('ng' for 2.4GHz or 'na' for 5GHz)
- `$channel` (int): Channel to set
- `$ht` (int): Channel width in MHz (e.g., 20, 40, 80, 160)
- `$tx_power_mode` (string): TX power mode ('auto', 'medium', 'high', 'low', or 'custom')
- `$tx_power` (int): TX power level in dBm (only used when tx_power_mode is 'custom')

**Returns:** bool - true upon success

### Set AP WLAN Group
```php
public function set_ap_wlangroup(string $type_id, string $device_id, string $group_id): bool
```
Assigns a WLAN group to an access point.

**Parameters:**
- `$type_id` (string): Type identifier
- `$device_id` (string): Device ID of the access point
- `$group_id` (string): WLAN group ID to assign

**Returns:** bool - true upon success

### Rename AP
```php
public function rename_ap(string $ap_id, string $ap_name): bool
```
Renames an access point.

**Parameters:**
- `$ap_id` (string): ID of the access point
- `$ap_name` (string): New name for the access point

**Returns:** bool - true upon success

### Locate AP
```php
public function locate_ap(string $mac, bool $enable): bool
```
Enables or disables LED locate mode on an access point.

**Parameters:**
- `$mac` (string): MAC address of the access point
- `$enable` (bool): true to enable locate mode, false to disable

**Returns:** bool - true upon success

## AP Group Management

### List AP Groups
```php
public function list_apgroups(): array
```
Lists all AP groups.

**Returns:** array - Array of AP group objects

### Create AP Group
```php
public function create_apgroup(string $group_name, array $device_macs = []): bool
```
Creates a new AP group.

**Parameters:**
- `$group_name` (string): Name of the new group
- `$device_macs` (array): Array of MAC addresses of APs to include in the group

**Returns:** bool - true upon success

### Edit AP Group
```php
public function edit_apgroup(string $group_id, string $group_name, array $device_macs): bool
```
Edits an existing AP group.

**Parameters:**
- `$group_id` (string): ID of the group to edit
- `$group_name` (string): New name for the group
- `$device_macs` (array): Array of MAC addresses of APs to include in the group

**Returns:** bool - true upon success

### Delete AP Group
```php
public function delete_apgroup(string $group_id): bool
```
Deletes an AP group.

**Parameters:**
- `$group_id` (string): ID of the group to delete

**Returns:** bool - true upon success

## Site Management

### List Sites
```php
public function list_sites(): array
```
Lists all sites available to the current user.

**Returns:** array - Array of site objects

### Site Statistics
```php
public function stat_sites(): array
```
Retrieves statistics for all sites.

**Returns:** array - Array of site statistics

### Create Site
```php
public function create_site(string $description): bool
```
Creates a new site.

**Parameters:**
- `$description` (string): Description/name of the new site

**Returns:** bool - true upon success

### Delete Site
```php
public function delete_site(string $site_id): bool
```
Deletes a site.

**Parameters:**
- `$site_id` (string): ID of the site to delete

**Returns:** bool - true upon success

### Set Site Name
```php
public function set_site_name(string $site_name): bool
```
Sets the name of the current site.

**Parameters:**
- `$site_name` (string): New name for the site

**Returns:** bool - true upon success

### Set Site Country
```php
public function set_site_country(string $country_id, $payload): bool
```
Sets the country settings for the current site.

**Parameters:**
- `$country_id` (string): ID of the country settings
- `$payload` (object|array): Country configuration data

**Returns:** bool - true upon success

### Set Site Locale
```php
public function set_site_locale(string $locale_id, $payload): bool
```
Sets the locale settings for the current site.

**Parameters:**
- `$locale_id` (string): ID of the locale settings
- `$payload` (object|array): Locale configuration data

**Returns:** bool - true upon success

### Set Site SNMP
```php
public function set_site_snmp(string $snmp_id, $payload): bool
```
Sets the SNMP settings for the current site.

**Parameters:**
- `$snmp_id` (string): ID of the SNMP settings
- `$payload` (object|array): SNMP configuration data

**Returns:** bool - true upon success

### Set Site Management
```php
public function set_site_mgmt(string $mgmt_id, $payload): bool
```
Sets the management settings for the current site.

**Parameters:**
- `$mgmt_id` (string): ID of the management settings
- `$payload` (object|array): Management configuration data

**Returns:** bool - true upon success

### Set Site Guest Access
```php
public function set_site_guest_access(string $guest_access_id, $payload): bool
```
Sets the guest access settings for the current site.

**Parameters:**
- `$guest_access_id` (string): ID of the guest access settings
- `$payload` (object|array): Guest access configuration data

**Returns:** bool - true upon success

### Set Site NTP
```php
public function set_site_ntp(string $ntp_id, $payload): bool
```
Sets the NTP settings for the current site.

**Parameters:**
- `$ntp_id` (string): ID of the NTP settings
- `$payload` (object|array): NTP configuration data

**Returns:** bool - true upon success

### Set Site Connectivity
```php
public function set_site_connectivity(string $connectivity_id, $payload): bool
```
Sets the connectivity settings for the current site.

**Parameters:**
- `$connectivity_id` (string): ID of the connectivity settings
- `$payload` (object|array): Connectivity configuration data

**Returns:** bool - true upon success

### Site LEDs
```php
public function site_leds(bool $enable): bool
```
Enables or disables LEDs for all devices at the site level.

**Parameters:**
- `$enable` (bool): true to enable LEDs, false to disable

**Returns:** bool - true upon success

## Network Management

### List Networks
```php
public function list_networkconf(string $network_id = ''): array
```
Lists all non-wireless networks for the current site.

**Parameters:**
- `$network_id` (string): Optional _id value of the network to get settings for

**Returns:** array - Array of network objects

### Create Network
```php
public function create_network($payload): array|bool
```
Creates a new network.

**Parameters:**
- `$payload` (object|array): stdClass object or associative array containing the network configuration. Must be structured in the same manner as returned by list_networkconf()

**Returns:** array|bool - Array containing a single object with details of the new network on success, false on failure

### Update Network Settings
```php
public function set_networksettings_base(string $network_id, $payload): bool
```
Updates network settings.

**Parameters:**
- `$network_id` (string): The "_id" value for the network to update
- `$payload` (object|array): Configuration to apply to the network

**Returns:** bool - true upon success

### Delete Network
```php
public function delete_network(string $network_id): bool
```
Deletes a network.

**Parameters:**
- `$network_id` (string): _id value of the network to delete

**Returns:** bool - true upon success

## WLAN Configuration

### List WLAN Configurations
```php
public function list_wlanconf(string $wlan_id = ''): array
```
Lists wireless network configurations.

**Parameters:**
- `$wlan_id` (string): Optional _id value of the WLAN to fetch settings for

**Returns:** array - Array of wireless network objects

### Create WLAN
```php
public function create_wlan(
    string $name,
    string $x_passphrase,
    string $usergroup_id,
    string $wlangroup_id,
    bool $enabled = true,
    bool $hide_ssid = false,
    bool $is_guest = false,
    string $security = 'open',
    string $wpa_mode = 'wpa2',
    string $wpa_enc = 'ccmp',
    ?bool $vlan_enabled = null,
    ?string $vlan_id = null,
    bool $uapsd_enabled = false,
    bool $schedule_enabled = false,
    array $schedule = [],
    ?array $ap_group_ids = null,
    array $payload = []
): bool
```
Creates a new wireless network.

**Parameters:**
- `$name` (string): SSID name
- `$x_passphrase` (string): Pre-shared key (8-63 characters, or null when security='open')
- `$usergroup_id` (string): User group ID (from list_usergroups())
- `$wlangroup_id` (string): WLAN group ID (from list_wlan_groups())
- `$enabled` (bool): Enable/disable WLAN (default: true)
- `$hide_ssid` (bool): Hide/unhide SSID (default: false)
- `$is_guest` (bool): Apply guest policies (default: false)
- `$security` (string): Security type: 'open', 'wep', 'wpapsk', 'wpaeap' (default: 'open')
- `$wpa_mode` (string): WPA mode: 'wpa', 'wpa2', 'wpa3' (default: 'wpa2')
- `$wpa_enc` (string): Encryption: 'auto', 'ccmp', 'tkip' (default: 'ccmp')
- `$vlan_enabled` (bool|null): Enable/disable VLAN (ignored as of v1.1.73)
- `$vlan_id` (string|null): VLAN "_id" from list_networkconf()
- `$uapsd_enabled` (bool): Enable U-APSD (default: false)
- `$schedule_enabled` (bool): Enable WLAN schedule (default: false)
- `$schedule` (array): Schedule rules array
- `$ap_group_ids` (array|null): Array of AP group IDs (required for controller v6.0+)
- `$payload` (array): Additional parameters (wlan_bands, wpa3_support, etc.)

**Returns:** bool - true upon success

### Set WLAN Settings (Base)
```php
public function set_wlansettings_base(string $wlan_id, $payload): bool
```
Updates WLAN settings with full configuration control.

**Parameters:**
- `$wlan_id` (string): _id of the WLAN to update
- `$payload` (object|array): Configuration to apply, structured like list_wlanconf() output

**Returns:** bool - true upon success

### Set WLAN Settings
```php
public function set_wlansettings(string $wlan_id, string $x_passphrase, string $name = ''): bool
```
Updates basic WLAN settings (password and optionally name).

**Parameters:**
- `$wlan_id` (string): _id of the WLAN to update
- `$x_passphrase` (string): New pre-shared key
- `$name` (string): Optional new SSID name

**Returns:** bool - true upon success

### Disable/Enable WLAN
```php
public function disable_wlan(string $wlan_id, bool $disable): bool
```
Disables or enables a WLAN.

**Parameters:**
- `$wlan_id` (string): _id of the WLAN
- `$disable` (bool): true to disable, false to enable

**Returns:** bool - true upon success

### Delete WLAN
```php
public function delete_wlan(string $wlan_id): bool
```
Deletes a WLAN.

**Parameters:**
- `$wlan_id` (string): _id of the WLAN to delete

**Returns:** bool - true upon success

### Set WLAN MAC Filter
```php
public function set_wlan_mac_filter(string $wlan_id, string $mac_filter_policy, bool $mac_filter_enabled, array $macs): bool
```
Configures MAC address filtering for a WLAN.

**Parameters:**
- `$wlan_id` (string): _id of the WLAN
- `$mac_filter_policy` (string): Filter policy ('allow' or 'deny')
- `$mac_filter_enabled` (bool): Enable/disable MAC filtering
- `$macs` (array): Array of MAC addresses to filter

**Returns:** bool - true upon success

## User Management

### List Users
```php
public function list_users(): array
```
Lists all users for the current site.

**Returns:** array - Array of user objects

### Create User
```php
public function create_user(
    string $name,
    string $mac,
    ?string $hostname = null,
    ?string $note = null
): bool
```
Creates a new user.

**Parameters:**
- `$name` (string): Name of the new user
- `$mac` (string): MAC address of the user
- `$hostname` (string|null): Hostname of the user
- `$note` (string|null): Note about the user

**Returns:** bool - true upon success

## User Group Management

### List User Groups
```php
public function list_usergroups(): array
```
Lists all user groups.

**Returns:** array - Array of user group objects

### Create User Group
```php
public function create_usergroup(string $group_name, int $group_dn = -1, int $group_up = -1): bool
```
Creates a new user group.

**Parameters:**
- `$group_name` (string): Name of the new group
- `$group_dn` (int): Download speed limit in Kbps (default: -1 for unlimited)
- `$group_up` (int): Upload speed limit in Kbps (default: -1 for unlimited)

**Returns:** bool - true upon success

### Edit User Group
```php
public function edit_usergroup(string $group_id, string $site_id, string $group_name, int $group_dn = -1, int $group_up = -1): bool
```
Edits an existing user group.

**Parameters:**
- `$group_id` (string): ID of the group to edit
- `$site_id` (string): ID of the site
- `$group_name` (string): New name for the group
- `$group_dn` (int): Download speed limit in Kbps (default: -1 for unlimited)
- `$group_up` (int): Upload speed limit in Kbps (default: -1 for unlimited)

**Returns:** bool - true upon success

### Delete User Group
```php
public function delete_usergroup(string $group_id): bool
```
Deletes a user group.

**Parameters:**
- `$group_id` (string): ID of the group to delete

**Returns:** bool - true upon success

## Guest Management

### List Guests
```php
public function list_guests(int $within = 8760): array
```
Lists all guest clients.

**Parameters:**
- `$within` (int): Hours of history to retrieve (default: 8760 = 1 year)

**Returns:** array - Array of guest client objects

### Authorize Guest
```php
public function authorize_guest(string $mac, int $minutes, ?int $up = null, ?int $down = null, ?int $megabytes = null, ?string $ap_mac = null): bool
```
Authorizes a guest client to access the network.

**Parameters:**
- `$mac` (string): MAC address of the guest client
- `$minutes` (int): Duration of authorization in minutes
- `$up` (int|null): Upload speed limit in Kbps
- `$down` (int|null): Download speed limit in Kbps
- `$megabytes` (int|null): Data usage limit in MB
- `$ap_mac` (string|null): MAC address of specific AP to connect to

**Returns:** bool - true upon success

### Unauthorize Guest
```php
public function unauthorize_guest(string $mac): bool
```
Revokes guest client authorization.

**Parameters:**
- `$mac` (string): MAC address of the guest client

**Returns:** bool - true upon success

### Extend Guest Validity
```php
public function extend_guest_validity(string $guest_id): bool
```
Extends the authorization period for a guest.

**Parameters:**
- `$guest_id` (string): _id of the guest to extend authorization for

**Returns:** bool - true upon success

## Voucher and Hotspot Management

### Create Hotspot Operator
```php
public function create_hotspotop(string $name, string $x_password, string $note = ''): bool
```
Creates a new hotspot operator account.

**Parameters:**
- `$name` (string): Name for the hotspot operator
- `$x_password` (string): Clear text password for the hotspot operator
- `$note` (string): Optional note to attach to the hotspot operator

**Returns:** bool - true upon success

### List Hotspot Operators
```php
public function list_hotspotop(): array
```
Lists all hotspot operators.

**Returns:** array - Array of hotspot operator objects

### Create Voucher
```php
public function create_voucher(
    int $minutes,
    int $count = 1,
    int $quota = 0,
    string $note = '',
    ?int $up = null,
    ?int $down = null,
    ?int $megabytes = null
): array
```
Creates guest access voucher(s).

**Parameters:**
- `$minutes` (int): Minutes the voucher is valid after activation
- `$count` (int): Number of vouchers to create (default: 1)
- `$quota` (int): Single or multi-use (0=multi-use, 1=single-use, n=multi-use n times, default: 0)
- `$note` (string): Note text to add when printing voucher
- `$up` (int|null): Upload speed limit in Kbps
- `$down` (int|null): Download speed limit in Kbps
- `$megabytes` (int|null): Data transfer limit in MB

**Returns:** array - Array containing create_time timestamp of the voucher(s)

**Note:** Use stat_voucher() to retrieve newly created voucher(s) by create_time

### Revoke Voucher
```php
public function revoke_voucher(string $voucher_id): bool
```
Revokes a voucher.

**Parameters:**
- `$voucher_id` (string): _id value of the voucher to revoke

**Returns:** bool - true upon success

## Firewall Management

### List Firewall Groups
```php
public function list_firewallgroups(string $group_id = ''): array
```
Lists all firewall groups or a specific group.

**Parameters:**
- `$group_id` (string): ID of specific group to list (default: empty string for all)

**Returns:** array - Array of firewall group objects

### Create Firewall Group
```php
public function create_firewallgroup(string $group_name, string $group_type, array $group_members = []): bool
```
Creates a new firewall group.

**Parameters:**
- `$group_name` (string): Name of the new group
- `$group_type` (string): Type of the group (e.g., 'address-group', 'port-group', 'ipv6-address-group')
- `$group_members` (array): Array of group members

**Returns:** bool - true upon success

### Edit Firewall Group
```php
public function edit_firewallgroup(string $group_id, string $site_id, string $group_name, string $group_type, array $group_members = []): bool
```
Edits an existing firewall group.

**Parameters:**
- `$group_id` (string): ID of the group to edit
- `$site_id` (string): ID of the site
- `$group_name` (string): New name for the group
- `$group_type` (string): Type of the group
- `$group_members` (array): Array of group members

**Returns:** bool - true upon success

### Delete Firewall Group
```php
public function delete_firewallgroup(string $group_id): bool
```
Deletes a firewall group.

**Parameters:**
- `$group_id` (string): ID of the group to delete

**Returns:** bool - true upon success

### List Firewall Rules
```php
public function list_firewallrules(): array
```
Lists all firewall rules.

**Returns:** array - Array of firewall rule objects

## Routing and Health

### List Routing
```php
public function list_routing(string $route_id = ''): array
```
Lists all routing information or a specific route.

**Parameters:**
- `$route_id` (string): ID of specific route to list (default: empty string for all)

**Returns:** array - Array of routing objects

### List Health
```php
public function list_health(): array
```
Lists system health information.

**Returns:** array - Array containing health information

### List Dashboard
```php
public function list_dashboard(bool $five_minutes = false): array
```
Lists dashboard information.

**Parameters:**
- `$five_minutes` (bool): Whether to get 5-minute data (default: false)

**Returns:** array - Array containing dashboard information

## Device Management

### List Devices Basic
```php
public function list_devices_basic(): array
```
Lists basic information about all devices.

**Returns:** array - Array of basic device information

### List Devices
```php
public function list_devices($macs = []): array
```
Lists detailed information about devices.

**Parameters:**
- `$macs` (array|string): Array of MAC addresses or single MAC address of specific devices to list

**Returns:** array - Array of device objects

### Adopt Device
```php
public function adopt_device($macs): bool
```
Adopts one or more devices.

**Parameters:**
- `$macs` (array|string): Array of MAC addresses or single MAC address to adopt

**Returns:** bool - true upon success

### Advanced Adopt Device
```php
public function advanced_adopt_device(
    string $mac,
    string $inform_url = '',
    string $ip = '',
    string $type = 'unifi'
): bool
```
Adopts a device with advanced options.

**Parameters:**
- `$mac` (string): MAC address of the device
- `$inform_url` (string): Custom inform URL
- `$ip` (string): IP address of the device
- `$type` (string): Device type (default: 'unifi')

**Returns:** bool - true upon success

### Migrate Device
```php
public function migrate_device($macs, string $inform_url): bool
```
Migrates device(s) to another controller.

**Parameters:**
- `$macs` (array|string): MAC address(es) of device(s) to migrate
- `$inform_url` (string): Inform URL of the target controller

**Returns:** bool - true upon success

### Cancel Migrate Device
```php
public function cancel_migrate_device($macs): bool
```
Cancels device migration.

**Parameters:**
- `$macs` (array|string): MAC address(es) of device(s)

**Returns:** bool - true upon success

### Restart Device
```php
public function restart_device($macs, string $reboot_type = 'soft'): bool
```
Restarts one or more devices.

**Parameters:**
- `$macs` (array|string): MAC address(es) of device(s) to restart
- `$reboot_type` (string): Type of reboot: 'soft' or 'hard' (default: 'soft')

**Returns:** bool - true upon success

### Force Provision
```php
public function force_provision($mac): bool
```
Forces provisioning of a device.

**Parameters:**
- `$mac` (string|array): MAC address of device to provision

**Returns:** bool - true upon success

### Reboot CloudKey
```php
public function reboot_cloudkey(): bool
```
Reboots the CloudKey or UniFi OS Console.

**Returns:** bool - true upon success

### Disable/Enable AP
```php
public function disable_ap(string $ap_id, bool $disable): bool
```
Disables or enables an access point.

**Parameters:**
- `$ap_id` (string): ID of the access point
- `$disable` (bool): true to disable, false to enable

**Returns:** bool - true upon success

### LED Override
```php
public function led_override(string $device_id, string $override_mode): bool
```
Overrides LED settings for a device.

**Parameters:**
- `$device_id` (string): ID of the device
- `$override_mode` (string): LED mode: 'default', 'on', 'off'

**Returns:** bool - true upon success

### Move Device
```php
public function move_device(string $mac, string $site_id): bool
```
Moves a device to another site.

**Parameters:**
- `$mac` (string): MAC address of the device
- `$site_id` (string): ID of the target site

**Returns:** bool - true upon success

### Delete Device
```php
public function delete_device(string $mac): bool
```
Deletes a device from the controller.

**Parameters:**
- `$mac` (string): MAC address of the device to delete

**Returns:** bool - true upon success

### Set Device Settings (Base)
```php
public function set_device_settings_base(string $device_id, $payload): bool
```
Updates device settings with full configuration control.

**Parameters:**
- `$device_id` (string): _id of the device
- `$payload` (object|array): Configuration to apply

**Returns:** bool - true upon success

### Power Cycle Switch Port
```php
public function power_cycle_switch_port(string $mac, int $port_idx): bool
```
Power cycles a switch port (PoE).

**Parameters:**
- `$mac` (string): MAC address of the switch
- `$port_idx` (int): Port index to power cycle

**Returns:** bool - true upon success

### Spectrum Scan
```php
public function spectrum_scan(string $mac): bool
```
Starts a spectrum scan on an access point.

**Parameters:**
- `$mac` (string): MAC address of the AP

**Returns:** bool - true upon success

### Spectrum Scan State
```php
public function spectrum_scan_state(string $mac): array
```
Retrieves spectrum scan state/results.

**Parameters:**
- `$mac` (string): MAC address of the AP

**Returns:** array - Spectrum scan state data

## Firmware and Updates

### Check Controller Update
```php
public function check_controller_update(): array
```
Checks for controller software updates.

**Returns:** array - Update information

### Get UniFi OS Console Update
```php
public function get_update_os_console(): array
```
Retrieves available UniFi OS console updates.

**Returns:** array - Update information

**Note:** Only for UniFi OS-based controllers

### Update UniFi OS Console
```php
public function update_os_console(): bool
```
Initiates UniFi OS console update.

**Returns:** bool - true upon success

**Note:** Only for UniFi OS-based controllers

### Check Firmware Update
```php
public function check_firmware_update(): bool
```
Checks for device firmware updates.

**Returns:** bool - true when updates are available

### Upgrade Device
```php
public function upgrade_device(string $mac): bool
```
Upgrades firmware on a single device.

**Parameters:**
- `$mac` (string): MAC address of the device

**Returns:** bool - true upon success

### Upgrade All Devices
```php
public function upgrade_all_devices(string $type = 'uap'): bool
```
Upgrades firmware on all devices of a specific type.

**Parameters:**
- `$type` (string): Device type: 'uap' (default), 'usw', 'ugw', 'uxg'

**Returns:** bool - true upon success

### Upgrade Device (External Firmware)
```php
public function upgrade_device_external(string $firmware_url, $macs): bool
```
Upgrades device firmware from external URL.

**Parameters:**
- `$firmware_url` (string): URL to firmware file
- `$macs` (array|string): MAC address(es) of device(s) to upgrade

**Returns:** bool - true upon success

### Start Rolling Upgrade
```php
public function start_rolling_upgrade(array $payload = ['uap', 'usw', 'ugw', 'uxg']): bool
```
Starts a rolling firmware upgrade.

**Parameters:**
- `$payload` (array): Array of device types to include in rolling upgrade

**Returns:** bool - true upon success

### Cancel Rolling Upgrade
```php
public function cancel_rolling_upgrade(): bool
```
Cancels an ongoing rolling upgrade.

**Returns:** bool - true upon success

### List Firmware
```php
public function list_firmware(string $type = 'available'): array
```
Lists firmware versions.

**Parameters:**
- `$type` (string): Type of firmware list: 'available', 'cached' (default: 'available')

**Returns:** array - Array of firmware objects

## Tag Management

### List Tags
```php
public function list_tags(): array
```
Lists all tags.

**Returns:** array - Array of tag objects

### Create Tag
```php
public function create_tag(string $name, ?array $macs = null): bool
```
Creates a new tag.

**Parameters:**
- `$name` (string): Name of the new tag
- `$macs` (array|null): Array of MAC addresses to tag

**Returns:** bool - true upon success

### Set Tagged Devices
```php
public function set_tagged_devices(array $macs, string $tag_id): bool
```
Sets devices for a specific tag.

**Parameters:**
- `$macs` (array): Array of MAC addresses to tag
- `$tag_id` (string): ID of the tag

**Returns:** bool - true upon success

### Get Tag
```php
public function get_tag(string $tag_id): array
```
Retrieves information about a specific tag.

**Parameters:**
- `$tag_id` (string): ID of the tag

**Returns:** array - Array containing tag information

### Delete Tag
```php
public function delete_tag(string $tag_id): bool
```
Deletes a tag.

**Parameters:**
- `$tag_id` (string): ID of the tag to delete

**Returns:** bool - true upon success

## Rogue AP Management

### List Rogue APs
```php
public function list_rogueaps(int $within = 24): array
```
Lists rogue access points.

**Parameters:**
- `$within` (int): Hours of history to retrieve (default: 24)

**Returns:** array - Array of rogue AP objects

### List Known Rogue APs
```php
public function list_known_rogueaps(): array
```
Lists known rogue access points.

**Returns:** array - Array of known rogue AP objects

## Events and Alarms

### List Events
```php
public function list_events(int $historyhours = 720, int $start = 0, int $limit = 3000): array
```
Lists system events.

**Parameters:**
- `$historyhours` (int): Hours of history to retrieve (default: 720 = 30 days)
- `$start` (int): Starting index for pagination (default: 0)
- `$limit` (int): Maximum number of events to return (default: 3000)

**Returns:** array - Array of event objects

### List Alarms
```php
public function list_alarms(array $payload = []): array
```
Lists alarms.

**Parameters:**
- `$payload` (array): Optional filter parameters

**Returns:** array - Array of alarm objects

### Count Alarms
```php
public function count_alarms(?bool $archived = null): int
```
Counts alarms.

**Parameters:**
- `$archived` (bool|null): Filter by archived status (true=archived, false=active, null=all)

**Returns:** int - Number of alarms

### Archive Alarm
```php
public function archive_alarm(string $alarm_id = ''): bool
```
Archives an alarm.

**Parameters:**
- `$alarm_id` (string): ID of the alarm to archive (empty to archive all)

**Returns:** bool - true upon success

## Backup Management

### Generate Backup
```php
public function generate_backup(int $days = -1): array
```
Generates a backup of the controller.

**Parameters:**
- `$days` (int): Number of days of history to include (default: -1 for all)

**Returns:** array - Array containing backup information

### Download Backup
```php
public function download_backup(string $filepath): bool
```
Downloads a backup file.

**Parameters:**
- `$filepath` (string): Path where to save the backup file

**Returns:** bool - true upon success

### List Backups
```php
public function list_backups(): array
```
Lists available backups.

**Returns:** array - Array of backup objects

### Generate Site Backup
```php
public function generate_backup_site(): array
```
Generates a backup of the current site.

**Returns:** array - Array containing site backup information

## Admin Management

### List Admins
```php
public function list_admins(): array
```
Lists all administrators for the current site.

**Returns:** array - Array of admin objects

### List All Admins
```php
public function list_all_admins(): array
```
Lists all administrators including super admins across all sites.

**Returns:** array - Array of admin objects

### Invite Admin
```php
public function invite_admin(
    string $name,
    string $email,
    bool $for_super = false,
    ?string $cmd = null,
    ?string $role = null
): bool
```
Invites a new administrator.

**Parameters:**
- `$name` (string): Name of the new admin
- `$email` (string): Email address of the new admin
- `$for_super` (bool): Whether to invite as super admin (default: false)
- `$cmd` (string|null): Additional command parameters
- `$role` (string|null): Role to assign

**Returns:** bool - true upon success

### Assign Existing Admin
```php
public function assign_existing_admin(
    string $admin_id,
    string $name,
    bool $for_super = false,
    ?string $cmd = null,
    ?string $role = null
): bool
```
Assigns an existing admin to a site.

**Parameters:**
- `$admin_id` (string): ID of the admin
- `$name` (string): Name of the admin
- `$for_super` (bool): Whether to assign as super admin (default: false)
- `$cmd` (string|null): Additional command parameters
- `$role` (string|null): Role to assign

**Returns:** bool - true upon success

### Update Admin
```php
public function update_admin(
    string $admin_id,
    string $name,
    bool $for_super = false,
    ?string $cmd = null,
    ?string $role = null
): bool
```
Updates an administrator's settings.

**Parameters:**
- `$admin_id` (string): ID of the admin to update
- `$name` (string): New name for the admin
- `$for_super` (bool): Whether to set as super admin (default: false)
- `$cmd` (string|null): Additional command parameters
- `$role` (string|null): Role to assign

**Returns:** bool - true upon success

### Revoke Admin
```php
public function revoke_admin(string $admin_id): bool
```
Revokes an administrator's access to the current site.

**Parameters:**
- `$admin_id` (string): ID of the admin to revoke

**Returns:** bool - true upon success

### Delete Admin
```php
public function delete_admin(string $admin_id): bool
```
Deletes an administrator completely.

**Parameters:**
- `$admin_id` (string): ID of the admin to delete

**Returns:** bool - true upon success

### Grant Super Admin
```php
public function grant_super_admin(string $admin_id): bool
```
Grants super admin privileges to an administrator.

**Parameters:**
- `$admin_id` (string): ID of the admin

**Returns:** bool - true upon success

## WLAN Group Management

### List WLAN Groups
```php
public function list_wlan_groups(): array
```
Lists all WLAN groups.

**Returns:** array - Array of WLAN group objects

## DNS Management

### List DNS Records
```php
public function list_dns_records(): ?array
```
Lists all DNS records configured in the UniFi controller.

**Returns:** array|null - Array of DNS record objects, or null if no records exist

### Create DNS Record
```php
public function create_dns_record(string $record_type, string $value, string $key, ?int $ttl = null, bool $enabled = true): ?object
```
Creates a new DNS record in the UniFi controller.

**Parameters:**
- `$record_type` (string): Type of DNS record (e.g., 'A', 'AAAA', 'CNAME', 'MX', 'TXT')
- `$value` (string): Value of the DNS record
- `$key` (string): Key/name for the DNS record
- `$ttl` (int|null): Time-to-live value in seconds (default: null)
- `$enabled` (bool): Whether the record is enabled (default: true)

**Returns:** object|null - Created DNS record object, or null if creation failed

### Delete DNS Record
```php
public function delete_dns_record(string $record_id): bool
```
Deletes a DNS record from the UniFi controller.

**Parameters:**
- `$record_id` (string): ID of the DNS record to delete

**Returns:** bool - true upon success

## DPI and Port Statistics

### List Port Forward Statistics
```php
public function list_portforward_stats(): array
```
Retrieves port forwarding statistics.

**Returns:** array - Array of port forwarding statistics

### List DPI Statistics
```php
public function list_dpi_stats(): array
```
Retrieves Deep Packet Inspection statistics.

**Returns:** array - Array of DPI statistics

### List Filtered DPI Statistics
```php
public function list_dpi_stats_filtered(string $type = 'by_cat', ?array $cat_filter = null): array
```
Retrieves filtered DPI statistics.

**Parameters:**
- `$type` (string): Return stats 'by_cat' (by category) or 'by_app' (by application) (default: 'by_cat')
- `$cat_filter` (array|null): Array of numeric category IDs to filter by (only with 'by_app' type)

**Returns:** array - Array of filtered DPI statistics

### List Current Channels
```php
public function list_current_channels(): array
```
Lists currently allowed WiFi channels.

**Returns:** array - Array of channel information

### List Country Codes
```php
public function list_country_codes(): array
```
Lists available country codes.

**Returns:** array - Array of country codes (ISO 3166-1 numeric standard)

### List Port Forwarding
```php
public function list_portforwarding(): array
```
Lists port forwarding rules.

**Returns:** array - Array of port forwarding rules

### List Port Configurations
```php
public function list_portconf(): array
```
Lists port configurations.

**Returns:** array - Array of port configuration objects

### List Extensions
```php
public function list_extension(): array
```
Lists VoIP extensions.

**Returns:** array - Array of VoIP extension objects

## Dynamic DNS Management

### List Dynamic DNS
```php
public function list_dynamicdns(): array
```
Lists dynamic DNS entries.

**Returns:** array - Array of dynamic DNS objects

### Create Dynamic DNS
```php
public function create_dynamicdns($payload): bool
```
Creates a dynamic DNS entry.

**Parameters:**
- `$payload` (object|array): Configuration structured like list_dynamicdns() output

**Returns:** bool - true upon success

### Update Dynamic DNS
```php
public function set_dynamicdns(string $dynamicdns_id, $payload): bool
```
Updates a dynamic DNS entry.

**Parameters:**
- `$dynamicdns_id` (string): _id of the dynamic DNS entry
- `$payload` (object|array): Configuration to apply

**Returns:** bool - true upon success

## RADIUS Management

### List RADIUS Profiles
```php
public function list_radius_profiles(): array
```
Lists RADIUS profiles.

**Returns:** array - Array of RADIUS profile objects

**Note:** Supported on controller versions 5.5.19+

### List RADIUS Accounts
```php
public function list_radius_accounts(): array
```
Lists RADIUS accounts.

**Returns:** array - Array of RADIUS account objects

**Note:** Supported on controller versions 5.5.19+

### Create RADIUS Account
```php
public function create_radius_account(
    string $name,
    string $x_password,
    ?int $tunnel_type = null,
    ?int $tunnel_medium_type = null,
    ?string $vlan = null
): array|bool
```
Creates a RADIUS account.

**Parameters:**
- `$name` (string): Name for the RADIUS account
- `$x_password` (string): Password for the account
- `$tunnel_type` (int|null): Tunnel type (1-13, see RADIUS standards)
- `$tunnel_medium_type` (int|null): Tunnel medium type (1-15, see RADIUS standards)
- `$vlan` (string|null): VLAN to assign

**Returns:** array|bool - Array containing created account object upon success, false on failure

**Note:** Supported on controller versions 5.5.19+

### Update RADIUS Account
```php
public function set_radius_account_base(string $account_id, $payload): bool
```
Updates a RADIUS account.

**Parameters:**
- `$account_id` (string): _id of the account
- `$payload` (object|array): Configuration to apply

**Returns:** bool - true upon success

**Note:** Supported on controller versions 5.5.19+

### Delete RADIUS Account
```php
public function delete_radius_account(string $account_id): bool
```
Deletes a RADIUS account.

**Parameters:**
- `$account_id` (string): _id of the account to delete

**Returns:** bool - true upon success

**Note:** Supported on controller versions 5.5.19+

## Advanced Settings

### Set Guest Login Settings
```php
public function set_guestlogin_settings(
    bool $portal_enabled,
    bool $portal_customized,
    bool $redirect_enabled,
    string $redirect_url,
    string $x_password,
    int $expire_number,
    int $expire_unit,
    string $section_id
): bool
```
Updates guest login settings.

**Parameters:**
- `$portal_enabled` (bool): Enable guest portal
- `$portal_customized` (bool): Use customized portal
- `$redirect_enabled` (bool): Enable redirect after authentication
- `$redirect_url` (string): URL to redirect to
- `$x_password` (string): Guest access password
- `$expire_number` (int): Expiration number
- `$expire_unit` (int): Expiration unit (minutes, hours, days)
- `$section_id` (string): _id of the guest access section

**Returns:** bool - true upon success

### Set Guest Login Settings (Base)
```php
public function set_guestlogin_settings_base($payload, string $section_id = ''): bool
```
Updates guest login settings with full configuration control.

**Parameters:**
- `$payload` (object|array): Configuration structured like list_settings() output for "guest_access"
- `$section_id` (string): Optional _id of the guest access section

**Returns:** bool - true upon success

### Set IPS Settings (Base)
```php
public function set_ips_settings_base($payload): bool
```
Updates IPS/IDS settings.

**Parameters:**
- `$payload` (object|array): Configuration structured like list_settings() output for "ips"

**Returns:** bool - true upon success

### Set Super Management Settings (Base)
```php
public function set_super_mgmt_settings_base(string $settings_id, $payload): bool
```
Updates "Super Management" settings.

**Parameters:**
- `$settings_id` (string): _id for the site settings where key='super_mgmt'
- `$payload` (object|array): Configuration to apply

**Returns:** bool - true upon success

### Set Super SMTP Settings (Base)
```php
public function set_super_smtp_settings_base(string $settings_id, $payload): bool
```
Updates "Super SMTP" settings.

**Parameters:**
- `$settings_id` (string): _id for the site settings where key='super_smtp'
- `$payload` (object|array): Configuration to apply

**Returns:** bool - true upon success

### Set Super Identity Settings (Base)
```php
public function set_super_identity_settings_base(string $settings_id, $payload): bool
```
Updates "Super Controller Identity" settings.

**Parameters:**
- `$settings_id` (string): _id for the site settings where key='super_identity'
- `$payload` (object|array): Configuration to apply

**Returns:** bool - true upon success

### Set Element Adoption
```php
public function set_element_adoption(bool $enable): bool
```
Enables or disables element adoption.

**Parameters:**
- `$enable` (bool): true to enable, false to disable

**Returns:** bool - true upon success

### Execute Stats Command
```php
public function cmd_stat(string $command): bool
```
Executes a specific stats command.

**Parameters:**
- `$command` (string): Command to execute (currently only 'reset-dpi' is supported)

**Returns:** bool - true upon success

## System Management

### List Device Name Mappings
```php
public function list_device_name_mappings(): array
```
Lists device name mappings.

**Returns:** array - Array of device name mapping objects

### List Self
```php
public function list_self(): array
```
Lists information about the current user.

**Returns:** array - Array containing current user information

### List Settings
```php
public function list_settings(): array
```
Lists all settings for the current site.

**Returns:** array - Array of settings objects

### System Information
```php
public function stat_sysinfo(): array
```
Retrieves system information.

**Returns:** array - Array containing system information

### Get System Log
```php
public function get_system_log(
    string $class = 'device-alert',
    ?int $start = null,
    ?int $end = null,
    int $page_number = 0,
    int $page_size = 100,
    array $custom_payload = []
): array
```
Retrieves system logs.

**Parameters:**
- `$class` (string): Log class to retrieve (default: 'device-alert')
- `$start` (int|null): Start timestamp
- `$end` (int|null): End timestamp
- `$page_number` (int): Page number for pagination (default: 0)
- `$page_size` (int): Number of entries per page (default: 100)
- `$custom_payload` (array): Additional custom parameters

**Returns:** array - Array of log entries

### List Models
```php
public function list_models(): ?object
```
Lists device models known to the site.

**Returns:** object|null - Object containing device model information

### List Device States
```php
public function list_device_states(): array
```
Lists device states.

**Returns:** array - Array of device state objects

### Custom API Request
```php
public function custom_api_request(string $path, string $method = 'GET', $payload = null, string $return = 'array'): mixed
```
Makes a custom API request to the controller.

**Parameters:**
- `$path` (string): API endpoint path
- `$method` (string): HTTP method: 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' (default: 'GET')
- `$payload` (mixed): Request payload for POST/PUT/PATCH requests
- `$prefix_path` (bool): Whether to prefix the path with the controller API base URL (default: true)
- `$return` (string): Return type: 'array', 'json', 'boolean' (default: 'array')

**Returns:** mixed - Response in the specified format

## Statistics

### Site Statistics
```php
public function stat_5minutes_site(?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_hourly_site(?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_daily_site(?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_monthly_site(?int $start = null, ?int $end = null, ?array $attribs = null): array
```
Retrieves site statistics at different time intervals.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp
- `$attribs` (array|null): Array of attributes to retrieve

**Returns:** array - Array containing site statistics

### Access Point Statistics
```php
public function stat_5minutes_aps(?int $start = null, ?int $end = null, ?string $mac = null, ?array $attribs = null): array
public function stat_hourly_aps(?int $start = null, ?int $end = null, ?string $mac = null, ?array $attribs = null): array
public function stat_daily_aps(?int $start = null, ?int $end = null, ?string $mac = null, ?array $attribs = null): array
public function stat_monthly_aps(?int $start = null, ?int $end = null, ?string $mac = null, ?array $attribs = null): array
```
Retrieves access point statistics at different time intervals.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp
- `$mac` (string|null): MAC address of specific AP
- `$attribs` (array|null): Array of attributes to retrieve

**Returns:** array - Array containing AP statistics

### User Statistics
```php
public function stat_5minutes_user(?string $mac = null, ?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_hourly_user(?string $mac = null, ?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_daily_user(?string $mac = null, ?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_monthly_user(?string $mac = null, ?int $start = null, ?int $end = null, ?array $attribs = null): array
```
Retrieves user statistics at different time intervals.

**Parameters:**
- `$mac` (string|null): MAC address of specific user
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp
- `$attribs` (array|null): Array of attributes to retrieve

**Returns:** array - Array containing user statistics

### Gateway Statistics
```php
public function stat_5minutes_gateway(?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_hourly_gateway(?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_daily_gateway(?int $start = null, ?int $end = null, ?array $attribs = null): array
public function stat_monthly_gateway(?int $start = null, ?int $end = null, ?array $attribs = null): array
```
Retrieves gateway statistics at different time intervals.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp
- `$attribs` (array|null): Array of attributes to retrieve

**Returns:** array - Array containing gateway statistics

### Speed Test Results
```php
public function stat_speedtest_results(?int $start = null, ?int $end = null): array
```
Retrieves speed test results.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp

**Returns:** array - Array containing speed test results

### IPS Events
```php
public function stat_ips_events(?int $start = null, ?int $end = null, ?int $limit = null): array
```
Retrieves IPS (Intrusion Prevention System) events.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp
- `$limit` (int|null): Maximum number of events to retrieve

**Returns:** array - Array containing IPS events

### Sessions
```php
public function stat_sessions(?int $start = null, ?int $end = null, ?string $mac = null, ?string $type = 'all'): array
```
Retrieves session information.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp
- `$mac` (string|null): MAC address of specific client
- `$type` (string): Type of sessions to retrieve (default: 'all')

**Returns:** array - Array containing session information

### Latest Client Sessions
```php
public function stat_sta_sessions_latest(string $mac, ?int $limit = null): array
```
Retrieves the latest sessions for a specific client.

**Parameters:**
- `$mac` (string): MAC address of the client
- `$limit` (int|null): Maximum number of sessions to retrieve

**Returns:** array - Array containing latest client sessions

### Authentication Events
```php
public function stat_auths(?int $start = null, ?int $end = null): array
```
Retrieves authentication events.

**Parameters:**
- `$start` (int|null): Start time in Unix timestamp
- `$end` (int|null): End time in Unix timestamp

**Returns:** array - Array containing authentication events

### All Users
```php
public function stat_allusers(int $historyhours = 8760): array
```
Retrieves information about all users.

**Parameters:**
- `$historyhours` (int): Hours of history to retrieve (default: 8760 = 1 year)

**Returns:** array - Array containing user information

### Client Statistics
```php
public function stat_client(string $mac): array
```
Retrieves statistics for a specific client.

**Parameters:**
- `$mac` (string): MAC address of the client

**Returns:** array - Array containing client statistics

### System Status
```php
public function stat_status(): bool
public function stat_full_status(): array
```
Retrieves system status information.

**Returns:**
- `stat_status()`: bool - true if system is operational
- `stat_full_status()`: array - Array containing detailed system status

### Voucher Statistics
```php
public function stat_voucher(?int $create_time = null): array
```
Retrieves voucher statistics.

**Parameters:**
- `$create_time` (int|null): Creation timestamp of specific voucher

**Returns:** array - Array containing voucher statistics

### Payment Statistics
```php
public function stat_payment(?int $within = null): array
```
Retrieves payment statistics.

**Parameters:**
- `$within` (int|null): Time window in seconds

**Returns:** array - Array containing payment statistics

## Debug and Utility Methods

### Set Debug Mode
```php
public function set_debug(bool $enable): bool
```
Enables or disables debug mode.

**Parameters:**
- `$enable` (bool): true to enable debug mode, false to disable

**Returns:** bool - Current debug status

### Get Debug Status
```php
public function get_debug(): bool
```
Retrieves the current debug mode status.

**Returns:** bool - true if debug mode is enabled

### Get Last Error Message
```php
public function get_last_error_message(): string
```
Retrieves the last error message.

**Returns:** string - Last error message

### Get Last Results
```php
public function get_last_results_raw(bool $return_json = false): mixed
```
Retrieves the last raw results from the API.

**Parameters:**
- `$return_json` (bool): Whether to return results as JSON string (default: false)

**Returns:** mixed - Last raw results (array or JSON string)

### Get Cookie
```php
public function get_cookie(): string
public function get_cookies(): string
```
Retrieves the current session cookie(s).

**Returns:** string - Current cookie value

### Get Cookie Creation Time
```php
public function get_cookies_created_at(): int
```
Retrieves the timestamp when the cookies were created.

**Returns:** int - Timestamp of cookie creation

### Set Cookies
```php
public function set_cookies(string $cookies_value): void
```
Sets the session cookies manually.

**Parameters:**
- `$cookies_value` (string): Cookie value to set

### Get UniFi Cookie Name
```php
public function get_unificookie_name(): string
```
Retrieves the UniFi cookie name.

**Returns:** string - Cookie name

### Get/Set Site
```php
public function get_site(): string
public function set_site(string $site): string
```
Gets or sets the current site.

**Parameters (set_site):**
- `$site` (string): Short site name to switch to

**Returns:** string - Current site name

### Get/Set cURL Method
```php
public function get_curl_method(): string
public function set_curl_method(string $curl_method): string
```
Gets or sets the cURL HTTP method.

**Parameters (set_curl_method):**
- `$curl_method` (string): HTTP method ('GET', 'POST', 'PUT', 'DELETE', 'PATCH')

**Returns:** string - Current cURL method

**Throws (set_curl_method):**
- `InvalidCurlMethodException`

### Get/Set SSL Verification
```php
public function get_curl_ssl_verify_peer(): bool
public function set_curl_ssl_verify_peer(bool $curl_ssl_verify_peer): bool

public function get_curl_ssl_verify_host(): int
public function set_curl_ssl_verify_host(int $curl_ssl_verify_host): bool
```
Gets or sets SSL verification settings.

**Parameters:**
- `$curl_ssl_verify_peer` (bool): Enable/disable peer verification
- `$curl_ssl_verify_host` (int): Host verification level (0, 1, or 2)

**Returns:** Current setting value

### Get/Set UniFi OS Flag
```php
public function get_is_unifi_os(): bool
public function set_is_unifi_os(bool $is_unifi_os): bool
```
Gets or sets the UniFi OS flag.

**Parameters (set_is_unifi_os):**
- `$is_unifi_os` (bool): true if controller is UniFi OS-based

**Returns:** bool - Current UniFi OS flag status

### Get/Set Connection Timeout
```php
public function get_connection_timeout(): int
public function set_connection_timeout(int $timeout): bool
```
Gets or sets the connection timeout.

**Parameters (set_connection_timeout):**
- `$timeout` (int): Connection timeout in seconds

**Returns:**
- `get_connection_timeout()`: int - Current timeout in seconds
- `set_connection_timeout()`: bool - true upon success

### Get/Set Request Timeout
```php
public function get_curl_request_timeout(): int
public function set_curl_request_timeout(int $timeout): bool
```
Gets or sets the cURL request timeout.

**Parameters (set_curl_request_timeout):**
- `$timeout` (int): Request timeout in seconds

**Returns:**
- `get_curl_request_timeout()`: int - Current timeout in seconds
- `set_curl_request_timeout()`: bool - true upon success

### Get/Set HTTP Version
```php
public function get_curl_http_version(): int
public function set_curl_http_version(int $http_version): bool
```
Gets or sets the HTTP version for cURL requests.

**Parameters (set_curl_http_version):**
- `$http_version` (int): cURL HTTP version constant (e.g., CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_2_0)

**Returns:**
- `get_curl_http_version()`: int - Current HTTP version
- `set_curl_http_version()`: bool - true upon success

### Get Class Version
```php
public function get_class_version(): string
```
Retrieves the version of this API client class.

**Returns:** string - Class version (currently '2.0.9')

---

## Notes

- Most methods require a successful login() before use
- Methods that accept the `$site_id` parameter typically default to the current site if not specified
- Many methods accept flexible `$payload` parameters that should be structured like their corresponding list/get methods
- Boolean return values typically indicate success (true) or failure (false)
- Array return values contain response data from the controller
- For UniFi OS-based controllers, some methods and endpoints differ from traditional controllers
- Timestamps are typically Unix timestamps (seconds since epoch)
- MAC addresses should be in lowercase format with colons (e.g., 'aa:bb:cc:dd:ee:ff')

## Version Information

This documentation corresponds to UniFi API Client version **2.0.9**.

For more examples and usage information, visit the [GitHub repository](https://github.com/Art-of-WiFi/UniFi-API-client).
