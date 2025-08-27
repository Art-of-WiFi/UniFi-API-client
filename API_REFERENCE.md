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
7. [User Management](#user-management)
8. [User Group Management](#user-group-management)
9. [Guest Management](#guest-management)
10. [Firewall Management](#firewall-management)
11. [Routing and Health](#routing-and-health)
12. [Device Management](#device-management)
13. [Tag Management](#tag-management)
14. [Rogue AP Management](#rogue-ap-management)
15. [Backup Management](#backup-management)
16. [Admin Management](#admin-management)
17. [WLAN Group Management](#wlan-group-management)
18. [DNS Management](#dns-management)
19. [System Management](#system-management)
20. [Statistics](#statistics)
21. [Debug and Utility Methods](#debug-and-utility-methods)

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
- `$baseurl` (string): Base URL of the UniFi controller (must include 'https://' prefix)
- `$site` (string|null): Short site name to access (default: 'default')
- `$version` (string|null): Controller version number (default: '8.0.28')
- `$ssl_verify` (bool): Whether to validate SSL certificate (default: false)
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
public function list_clients(?string $mac = null, ?string $site_id = null): array
```
Lists all clients or a specific client.

**Parameters:**
- `$mac` (string|null): MAC address of the client to list
- `$site_id` (string|null): Site ID to use (default: current site)

**Returns:** array - Array of client objects

### Block Client
```php
public function block_sta(string $mac, ?string $site_id = null): bool
```
Blocks a client from accessing the network.

**Parameters:**
- `$mac` (string): MAC address of the client to block
- `$site_id` (string|null): Site ID to use (default: current site)

**Returns:** bool - true upon success

### Unblock Client
```php
public function unblock_sta(string $mac, ?string $site_id = null): bool
```
Unblocks a client from accessing the network.

**Parameters:**
- `$mac` (string): MAC address of the client to unblock
- `$site_id` (string|null): Site ID to use (default: current site)

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
public function list_aps(?string $mac = null, ?string $site_id = null): array
```
Lists all access points or a specific access point.

**Parameters:**
- `$mac` (string|null): MAC address of the AP to list
- `$site_id` (string|null): Site ID to use (default: current site)

**Returns:** array - Array of access point objects

### Set AP Radio Settings
```php
public function set_ap_radiosettings(
    string $ap_id,
    ?string $radio = null,
    ?string $channel = null,
    ?string $ht = null,
    ?string $tx_power_mode = null,
    ?string $tx_power = null,
    ?string $site_id = null
): bool
```
Configures radio settings for an access point.

**Parameters:**
- `$ap_id` (string): ID of the access point
- `$radio` (string|null): Radio to configure ('ng' or 'na')
- `$channel` (string|null): Channel to set
- `$ht` (string|null): HT mode to set
- `$tx_power_mode` (string|null): TX power mode to set
- `$tx_power` (string|null): TX power level to set
- `$site_id` (string|null): Site ID to use (default: current site)

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

### Create Site
```php
public function create_site(string $name, string $desc = ''): bool
```
Creates a new site.

**Parameters:**
- `$name` (string): Name of the new site
- `$desc` (string): Description of the new site

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
- `$payload` (mixed): Country configuration data

**Returns:** bool - true upon success

### Set Site Locale
```php
public function set_site_locale(string $locale_id, $payload): bool
```
Sets the locale settings for the current site.

**Parameters:**
- `$locale_id` (string): ID of the locale settings
- `$payload` (mixed): Locale configuration data

**Returns:** bool - true upon success

### Set Site SNMP
```php
public function set_site_snmp(string $snmp_id, $payload): bool
```
Sets the SNMP settings for the current site.

**Parameters:**
- `$snmp_id` (string): ID of the SNMP settings
- `$payload` (mixed): SNMP configuration data

**Returns:** bool - true upon success

### Set Site Management
```php
public function set_site_mgmt(string $mgmt_id, $payload): bool
```
Sets the management settings for the current site.

**Parameters:**
- `$mgmt_id` (string): ID of the management settings
- `$payload` (mixed): Management configuration data

**Returns:** bool - true upon success

### Set Site Guest Access
```php
public function set_site_guest_access(string $guest_access_id, $payload): bool
```
Sets the guest access settings for the current site.

**Parameters:**
- `$guest_access_id` (string): ID of the guest access settings
- `$payload` (mixed): Guest access configuration data

**Returns:** bool - true upon success

### Set Site NTP
```php
public function set_site_ntp(string $ntp_id, $payload): bool
```
Sets the NTP settings for the current site.

**Parameters:**
- `$ntp_id` (string): _id of the NTP settings
- `$payload` (mixed): NTP configuration data

**Returns:** bool - true upon success

### Set Site Connectivity
```php
public function set_site_connectivity(string $connectivity_id, $payload): bool
```
Sets the connectivity settings for the current site.

**Parameters:**
- `$connectivity_id` (string): ID of the connectivity settings
- `$payload` (mixed): Connectivity configuration data

**Returns:** bool - true upon success

## Network Management

### List Networks
```php
public function list_networkconf(?string $network_id = '')
```
Lists all non-wireless networks for the current site. 

**Parameters:**
- `$network_id` (string|null): _id value of the network to get settings for

**Returns:** array - Array of network objects

### Create Network
```php
public function create_network(
    string $name,
    string $vlan_id,
    string $purpose = 'corporate',
    ?string $subnet = null,
    ?string $dhcpd_enabled = null,
    ?string $dhcpd_start = null,
    ?string $dhcpd_stop = null,
    ?string $dhcpd_leasetime = null,
    ?string $site_id = null
): bool
```
Creates a new network.

**Parameters:**
- `$name` (string): Name of the new network
- `$vlan_id` (string): VLAN ID for the network
- `$purpose` (string): Purpose of the network (default: 'corporate')
- `$subnet` (string|null): Subnet for the network
- `$dhcpd_enabled` (string|null): Whether DHCP is enabled
- `$dhcpd_start` (string|null): DHCP start address
- `$dhcpd_stop` (string|null): DHCP stop address
- `$dhcpd_leasetime` (string|null): DHCP lease time
- `$site_id` (string|null): Site ID to use (default: current site)

**Returns:** bool - true upon success

## User Management

### List Users
```php
public function list_users(?string $site_id = null): array
```
Lists all users or users for a specific site.

**Parameters:**
- `$site_id` (string|null): Site ID to use (default: current site)

**Returns:** array - Array of user objects

### Create User
```php
public function create_user(
    string $name,
    string $mac,
    ?string $hostname = null,
    ?string $note = null,
    ?string $site_id = null
): bool
```
Creates a new user.

**Parameters:**
- `$name` (string): Name of the new user
- `$mac` (string): MAC address of the user
- `$hostname` (string|null): Hostname of the user
- `$note` (string|null): Note about the user
- `$site_id` (string|null): Site ID to use (default: current site)

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
- `$within` (int): Hours of history to retrieve (default: 8760)

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

## Firewall Management

### List Firewall Groups
```php
public function list_firewallgroups(string $group_id = ''): array
```
Lists all firewall groups or a specific group.

**Parameters:**
- `$group_id` (string): ID of specific group to list (default: empty string)

**Returns:** array - Array of firewall group objects

### Create Firewall Group
```php
public function create_firewallgroup(string $group_name, string $group_type, array $group_members = []): bool
```
Creates a new firewall group.

**Parameters:**
- `$group_name` (string): Name of the new group
- `$group_type` (string): Type of the group
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
- `$route_id` (string): ID of specific route to list (default: empty string)

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
- `$macs` (array): Array of MAC addresses of specific devices to list

**Returns:** array - Array of device objects

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

## Backup Management

### Generate Backup
```php
public function generate_backup(int $days = -1): array
```
Generates a backup of the controller.

**Parameters:**
- `$days` (int): Number of days of history to include (default: -1)

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
Lists all administrators.

**Returns:** array - Array of admin objects

### List All Admins
```php
public function list_all_admins(): array
```
Lists all administrators including super admins.

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
Revokes an administrator's access.

**Parameters:**
- `$admin_id` (string): ID of the admin to revoke

**Returns:** bool - true upon success

### Delete Admin
```php
public function delete_admin(string $admin_id): bool
```
Deletes an administrator.

**Parameters:**
- `$admin_id` (string): ID of the admin to delete

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
- `$record_type` (string): Type of DNS record (e.g., 'A', 'AAAA', 'CNAME', etc.)
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
- `$historyhours` (int): Hours of history to retrieve (default: 8760)

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
- `$create_time` (int|null): Creation time of specific voucher

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
public function set_debug(bool $debug): void
```
Enables or disables debug mode.

**Parameters:**
- `$debug` (bool): Whether to enable debug mode

### Get Last Error Message
```php
public function get_last_error_message(): string
```
Retrieves the last error message.

**Returns:** string - Last error message

### Get Last Results
```php
public function get_last_results_raw(): mixed
```
Retrieves the last raw results from the API.

**Returns:** mixed - Last raw results

### Get Cookies
```php
public function get_cookies(): string
```
Retrieves the current cookies.

**Returns:** string - Current cookies

### Get Cookie Creation Time
```php
public function get_cookies_created_at(): int
```
Retrieves the timestamp when the cookies were created.

**Returns:** int - Timestamp of cookie creation

### Get Base URL
```php
public function get_baseurl(): string
```
Retrieves the base URL of the UniFi controller.

**Returns:** string - Base URL

### Get Site
```php
public function get_site(): string
```
Retrieves the current site name.

**Returns:** string - Current site name

### Get Version
```php
public function get_version(): string
```
Retrieves the controller version.

**Returns:** string - Controller version

### Get Class Version
```php
public function get_class_version(): string
```
Retrieves the version of this API client class.

**Returns:** string - Class version 