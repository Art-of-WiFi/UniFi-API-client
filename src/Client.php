<?php

namespace UniFi_API;

/**
 * The UniFi API client class
 *
 * This UniFi API client class is based on the work done by the following developers:
 *    domwo: https://community.ui.com/questions/little-php-class-for-unifi-api/933d3fb3-b401-4499-993a-f9af079a4a3a
 *    fbagnol: https://github.com/fbagnol/class.unifi.php
 * and the API as published by Ubiquiti:
 *    https://dl.ui.com/unifi/<UniFi controller version number>/unifi_sh_api
 *
 * @package UniFi_Controller_API_Client_Class
 * @author  Art of WiFi <info@artofwifi.net>
 * @version Release: 1.1.92
 * @license This class is subject to the MIT license that is bundled with this package in the file LICENSE.md
 * @example This directory in the package repository contains a collection of examples:
 *          https://github.com/Art-of-WiFi/UniFi-API-client/tree/master/examples
 */
class Client
{
    /** constants */
    const CLASS_VERSION        = '1.1.94';
    const CURL_METHODS_ALLOWED = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    const DEFAULT_CURL_METHOD  = 'GET';

    /**
     * protected properties
     *
     * @note do **not** directly edit the property values below, instead use the constructor or the respective
     *       getter and setter functions/methods
     */
    protected string $baseurl              = '';
    protected string $user                 = '';
    protected string $password             = '';
    protected string $site                 = '';
    protected string $version              = '';
    protected bool   $debug                = false;
    protected bool   $is_logged_in         = false;
    protected bool   $is_unifi_os          = false;
    protected int    $exec_retries         = 0;
    protected string $cookies              = '';
    protected        $last_results_raw     = null;
    protected string $last_error_message   = '';
    protected bool   $curl_ssl_verify_peer = false;
    protected int    $curl_ssl_verify_host = 0;
    protected int    $curl_http_version    = CURL_HTTP_VERSION_NONE;
    protected string $curl_method          = self::DEFAULT_CURL_METHOD;
    protected int    $curl_request_timeout = 30;
    protected int    $curl_connect_timeout = 10;
    protected string $unificookie_name     = 'unificookie';
    protected array  $curl_headers         = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Expect:',
    ];

    /**
     * Construct an instance of the UniFi API client class.
     *
     * @param string $user username to use when connecting to the UniFi controller
     * @param string $password password to use when connecting to the UniFi controller
     * @param string $baseurl optional, base URL of the UniFi controller which *must* include an 'https://' prefix,
     *                        a port suffix (e.g. :8443) is required for non-UniFi OS controllers,
     *                        do not add trailing slashes, defaults to 'https://127.0.0.1:8443'
     * @param string|null $site optional, short site name to access, defaults to 'default'
     * @param string|null $version optional, the version number of the controller, defaults to '8.0.28'
     * @param bool $ssl_verify optional, whether to validate the controller's SSL certificate or not, a value of true
     *                         is recommended for production environments to prevent potential MitM attacks, defaults
     *                         to false which disables validation of the controller's SSL certificate
     * @param string $unificookie_name optional, name of the cookie to use, defaults to 'unificookie'.
     *                                 This is only necessary when you have multiple apps using this API client on the
     *                                 same web server.
     */
    public function __construct(
        string $user,
        string $password,
        string $baseurl = 'https://127.0.0.1:8443',
        string $site = 'default',
        string $version = '8.0.28',
        bool   $ssl_verify = false,
        string $unificookie_name = 'unificookie'
    )
    {
        if (!extension_loaded('curl')) {
            trigger_error('The PHP curl extension is not loaded. Please correct this before proceeding!');
        }

        $this->check_base_url($baseurl);
        $this->check_site($site);

        $this->baseurl          = trim($baseurl);
        $this->site             = strtolower(trim($site));
        $this->user             = trim($user);
        $this->password         = trim($password);
        $this->version          = trim($version);
        $this->unificookie_name = trim($unificookie_name);

        if ($ssl_verify === true) {
            $this->curl_ssl_verify_peer = true;
            $this->curl_ssl_verify_host = 2;
        }
    }

    /**
     * This method is called as soon as there are no other references to the class instance.
     *
     * @see https://www.php.net/manual/en/language.oop5.decon.php
     * @note to force the class instance to log out when you're done, call logout()
     */
    public function __destruct()
    {
        /** if $_SESSION[$this->unificookie_name] is set, do not log out here */
        if (isset($_SESSION[$this->unificookie_name])) {
            return;
        }

        /** log out, if needed */
        if ($this->is_logged_in) {
            $this->logout();
        }
    }

    /**
     * Login to the UniFi controller
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status#client_error_responses
     * @return bool|int returns true upon success, false or the HTTP response code (typically 400, 401, or 403) upon
     *                  error
     */
    public function login()
    {
        /** skip the login process if already logged in */
        if ($this->update_unificookie()) {
            $this->is_logged_in = true;
        }

        if ($this->is_logged_in === true) {
            return true;
        }

        /** prepare cURL and options to check whether this is a "regular" controller or one based on UniFi OS */
        $ch = $this->get_curl_handle();

        $curl_options = [
            CURLOPT_URL => $this->baseurl . '/',
        ];

        curl_setopt_array($ch, $curl_options);

        /** execute the cURL request and get the HTTP response code */
        curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        /** prepare the actual login */
        $curl_options = [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode(['username' => $this->user, 'password' => $this->password]),
            CURLOPT_HTTPHEADER => $this->curl_headers,
            CURLOPT_REFERER    => $this->baseurl . '/login',
            CURLOPT_URL        => $this->baseurl . '/api/login',
        ];

        /** specific to UniFi OS-based controllers */
        if ($http_code === 200) {
            $this->is_unifi_os         = true;
            $curl_options[CURLOPT_URL] = $this->baseurl . '/api/auth/login';
        }

        curl_setopt_array($ch, $curl_options);

        /** execute the cURL request and get the HTTP response code */
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        if ($this->debug) {
            print PHP_EOL . '<pre>';
            print PHP_EOL . '-----------LOGIN-------------' . PHP_EOL;
            print_r(curl_getinfo($ch));
            print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
            print $response;
            print PHP_EOL . '-----------------------------' . PHP_EOL;
            print '</pre>' . PHP_EOL;
        }

        /** based on the HTTP response code trigger an error */
        if ($http_code >= 400) {
            trigger_error("HTTP response status received: $http_code. Probably a controller login failure");

            return $http_code;
        }

        curl_close($ch);

        /** check the HTTP response code */
        if ($http_code >= 200) {
            $this->is_logged_in = true;

            return $this->is_logged_in;
        }

        return false;
    }

    /**
     * Logout from the UniFi controller
     *
     * @return bool true upon success
     */
    public function logout(): bool
    {
        /** prepare cURL and options */
        $ch = $this->get_curl_handle();

        $curl_options = [
            CURLOPT_HEADER => true,
            CURLOPT_POST   => true,
        ];

        $logout_path = '/logout';

        if ($this->is_unifi_os) {
            $logout_path                         = '/api/auth/logout';
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'POST';

            $this->create_x_csrf_token_header();
        }

        $curl_options[CURLOPT_HTTPHEADER] = $this->curl_headers;
        $curl_options[CURLOPT_URL]        = $this->baseurl . $logout_path;

        curl_setopt_array($ch, $curl_options);

        /** execute the cURL request to logout */
        curl_exec($ch);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
            return false;
        }

        curl_close($ch);

        $this->is_logged_in = false;
        $this->cookies      = '';
        return true;
    }

    /****************************************************************
     * Functions to access UniFi controller API routes from here:
     ****************************************************************/

    /**
     * Authorize a client device
     *
     * @param string $mac client MAC address
     * @param int $minutes minutes (from now) until authorization expires
     * @param int|null $up optional, upload speed limit in kbps
     * @param int|null $down optional, download speed limit in kbps
     * @param int|null $megabytes optional, data transfer limit in MB
     * @param string|null $ap_mac optional, AP MAC address to which client is connected, should result in faster
     *                            authorization for the client device
     * @return bool true upon success
     */
    public function authorize_guest(string $mac, int $minutes, int $up = null, int $down = null, int $megabytes = null, string $ap_mac = null): bool
    {
        $payload = ['cmd' => 'authorize-guest', 'mac' => strtolower($mac), 'minutes' => $minutes];

        /** append received values for up/down/megabytes/ap_mac to the payload array to be submitted */
        if (!empty($up)) {
            $payload['up'] = $up;
        }

        if (!empty($down)) {
            $payload['down'] = $down;
        }

        if (!empty($megabytes)) {
            $payload['bytes'] = $megabytes;
        }

        if (!empty($ap_mac) && filter_var($ap_mac, FILTER_VALIDATE_MAC)) {
            $payload['ap_mac'] = strtolower($ap_mac);
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Unauthorize a client device
     *
     * @param string $mac client MAC address
     * @return bool true upon success
     */
    public function unauthorize_guest(string $mac): bool
    {
        $payload = ['cmd' => 'unauthorize-guest', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Reconnect a client device
     *
     * @param string $mac client MAC address
     * @return bool true upon success
     */
    public function reconnect_sta(string $mac): bool
    {
        $payload = ['cmd' => 'kick-sta', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Block a client device
     *
     * @param string $mac client MAC address
     * @return bool true upon success
     */
    public function block_sta(string $mac): bool
    {
        $payload = ['cmd' => 'block-sta', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Unblock a client device
     *
     * @param string $mac client MAC address
     * @return bool true upon success
     */
    public function unblock_sta(string $mac): bool
    {
        $payload = ['cmd' => 'unblock-sta', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Forget one or more client devices
     *
     * @note only supported with controller versions 5.9.X and higher, can be
     *       slow (up to 5 minutes) on larger controllers
     * @param array|string $mac array of client MAC addresses (strings) or a single MAC address string
     * @return bool true upon success
     */
    public function forget_sta($mac): bool
    {
        $payload = [
            'cmd'  => 'forget-sta',
            'macs' => array_map('strtolower', (array)$mac)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Create a new user/client-device
     *
     * @param string $mac client MAC address
     * @param string $user_group_id _id value for the user group the new user/client-device should belong to which
     *                              can be obtained from the output of list_usergroups()
     * @param string|null $name optional, name to be given to the new user/client-device
     * @param string|null $note optional, note to be applied to the new user/client-device
     * @param bool|null $is_guest optional, defines whether the new user/client-device is a guest or not
     * @param bool|null $is_wired optional, defines whether the new user/client-device is wired or not
     * @return array|bool returns an array with a single object containing details of the new user/client-device on
     *                    success, else returns false
     */
    public function create_user(
        string $mac,
        string $user_group_id,
        string $name = null,
        string $note = null,
        bool   $is_guest = null,
        bool   $is_wired = null
    )
    {
        $new_user = ['mac' => strtolower($mac), 'usergroup_id' => $user_group_id];

        if (!empty($name)) {
            $new_user['name'] = $name;
        }

        if (!empty($note)) {
            $new_user['note'] = $note;
        }

        if (!empty($is_guest)) {
            $new_user['is_guest'] = $is_guest;
        }

        if (!empty($is_wired)) {
            $new_user['is_wired'] = $is_wired;
        }

        $payload = ['objects' => [['data' => $new_user]]];

        return $this->fetch_results('/api/s/' . $this->site . '/group/user', $payload);
    }

    /**
     * Add/modify/remove a client-device note
     *
     * @param string $user_id id of the client-device to be modified
     * @param string $note optional, note to be applied to the client-device
     * @return bool true upon success
     */
    public function set_sta_note(string $user_id, string $note = ''): bool
    {
        $payload = ['note' => $note];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);
    }

    /**
     * Add/modify/remove a client device name
     *
     * @param string $user_id id of the client-device to be modified
     * @param string $name optional, name to be applied to the client device, when empty or not set,
     *                        the existing name for the client device is removed
     * @return bool true upon success
     */
    public function set_sta_name(string $user_id, string $name = ''): bool
    {
        $payload = ['name' => $name];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);
    }

    /**
     * Fetch 5-minute site stats
     *
     * @note - defaults to the past 12 hours
     *       - this function/method is only supported on controller versions 5.5.* and later
     *       - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *         the controller settings
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @return array|bool returns an array of 5-minute stats objects for the current site
     */
    public function stat_5minutes_site(int $start = null, int $end = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : $start;

        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time',
        ];

        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.site', $payload);
    }

    /**
     * Fetch hourly site stats
     *
     * TODO: add support for optional attrib parameter
     *       airtime_avg
     *
     * @note - defaults to the past 7*24 hours
     *       - "bytes" are no longer returned with controller version 4.9.1 and later
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @return array|bool returns an array of hourly stats objects for the current site
     */
    public function stat_hourly_site(int $start = null, int $end = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : $start;

        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time',
        ];

        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.site', $payload);
    }

    /**
     * Fetch daily site stats
     *
     * @note - defaults to the past 52*7*24 hours
     *       - "bytes" are no longer returned with controller version 4.9.1 and later
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @return array|bool returns an array of daily stats objects for the current site
     */
    public function stat_daily_site(int $start = null, int $end = null)
    {
        $end     = empty($end) ? (time() - (time() % 3600)) * 1000 : $end;
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : $start;

        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time',
        ];

        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.site', $payload);
    }

    /**
     * Fetch monthly site stats
     *
     * @note - defaults to the past 52 weeks (52*7*24 hours)
     *       - "bytes" are no longer returned with controller version 4.9.1 and later
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @return array|bool returns an array of monthly stats objects for the current site
     */
    public function stat_monthly_site(int $start = null, int $end = null)
    {
        $end     = empty($end) ? (time() - (time() % 3600)) * 1000 : $end;
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : $start;

        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time',
        ];

        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/monthly.site', $payload);
    }

    /**
     * Fetch 5-minutes stats for a single access point or all access points
     *
     * @note - defaults to the past 12 hours
     *       - this function/method is only supported on controller versions 5.5.* and later
     *       - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *         the controller settings
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param string|null $mac optional, AP MAC address to return stats for, when empty,
     *                      stats for all APs are returned
     * @return array|bool returns an array of 5-minute stats objects
     */
    public function stat_5minutes_aps(int $start = null, int $end = null, string $mac = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : $start;
        $attribs = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.ap', $payload);
    }

    /**
     * Fetch hourly stats for a single access point or all access points
     *
     * TODO: optionally add ["top_filter_by" => "tx_retries"] to payload
     *       add optional parameter for attribs to support:
     *       wifi_tx_attempts
     *       tx_retries
     *       wifi_tx_dropped
     *       sta_assoc_failures
     *       sta_wpa_auth_failures
     *       mac_filter_rejections
     *       sta_dhcp_failures
     *       sta_assoc_min
     *       sta_assoc_max
     *       sta_connect_time_min
     *       sta_connect_time_max
     *       sta_connect_time_total
     *       user-wlan-num_sta_connected
     *       user-wlan-num_sta_disconnected
     *       user-wlan-sta_assoc_samples
     *       user-wlan-sta_track_samples
     *       wlan-num_sta
     *
     * @note - defaults to the past 7*24 hours
     *       - make sure that the retention policy for hourly stats is set to the correct value in
     *         the controller settings
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param string|null $mac optional, AP MAC address to return stats for, when empty,
     *                       stats for all APs are returned
     * @return array|bool returns an array of hourly stats objects
     */
    public function stat_hourly_aps(int $start = null, int $end = null, string $mac = null)
    {
        $end     = empty($end) ? (time() * 1000) : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : $start;
        $attribs = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.ap', $payload);
    }

    /**
     * Fetch daily stats for a single access point or all access points
     *
     * @note - defaults to the past 7*24 hours
     *       - make sure that the retention policy for hourly stats is set to the correct value in
     *         the controller settings
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param string|null $mac optional, AP MAC address to return stats for, when empty,
     *                      stats for all APs are returned
     * @return array|bool returns an array of daily stats objects
     */
    public function stat_daily_aps(int $start = null, int $end = null, string $mac = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : $start;
        $attribs = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.ap', $payload);
    }

    /**
     * Fetch monthly stats for a single access point or all access points
     *
     * @note - defaults to the past 52 weeks (52*7*24 hours)
     *       - make sure that the retention policy for hourly stats is set to the correct value in
     *         the controller settings
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param string|null $mac optional, AP MAC address to return stats for, when empty,
     *                      stats for all APs are returned
     * @return array|bool returns an array of monthly stats objects
     */
    public function stat_monthly_aps(int $start = null, int $end = null, string $mac = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : $start;
        $attribs = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/monthly.ap', $payload);
    }

    /**
     * Fetch 5-minutes stats for a single user/client device
     *
     *
     * @note - defaults to the past 12 hours
     *       - only supported with UniFi controller versions 5.8.X and higher
     *       - make sure that the retention policy for 5-minute stats is set to the correct value in
     *         the controller settings
     *       - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance
     *         section
     * @param string $mac MAC address of the user/client device to return stats for
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                        rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets,
     *                        tx_packets, satisfaction, wifi_tx_attempts
     *                        default value is ['rx_bytes', 'tx_bytes']
     * @return array|bool returns an array of 5-minute stats objects
     */
    public function stat_5minutes_user(string $mac, int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.user', $payload);
    }

    /**
     * Fetch hourly stats for a single user/client device
     *
     * @note - defaults to the past 7*24 hours
     *       - only supported with UniFi controller versions 5.8.X and higher
     *       - make sure that the retention policy for hourly stats is set to the correct value in
     *         the controller settings
     *       - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance
     *         section
     * @param string $mac MAC address of the user/client device to return stats fo
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                        rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets,
     *                        tx_packets, satisfaction, wifi_tx_attempts
     *                        default value is ['rx_bytes', 'tx_bytes']
     * @return array|bool returns an array of hourly stats objects
     */
    public function stat_hourly_user(string $mac, int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.user', $payload);
    }

    /**
     * Fetch daily stats for a single user/client device
     *
     * @note - defaults to the past 7*24 hours
     *       - only supported with UniFi controller versions 5.8.X and higher
     *       - make sure that the retention policy for daily stats is set to the correct value in
     *         the controller settings
     *       - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance
     *         section
     * @param string $mac MAC address of the user/client device to return stats for
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                        rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets,
     *                        tx_packets, satisfaction, wifi_tx_attempts
     *                        default value is ['rx_bytes', 'tx_bytes']
     * @return array|bool returns an array of daily stats objects
     */
    public function stat_daily_user(string $mac, int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.user', $payload);
    }

    /**
     * Fetch monthly stats for a single user/client device
     *
     * @note - defaults to the past 13 weeks (52*7*24 hours)
     *       - only supported with UniFi controller versions 5.8.X and higher
     *       - make sure that the retention policy for monthly stats is set to the correct value in
     *         the controller settings
     *       - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance
     *         section
     * @param string $mac MAC address of the user/client device to return stats for
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                        rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets,
     *                        tx_packets, satisfaction, wifi_tx_attempts
     *                        default value is ['rx_bytes', 'tx_bytes']
     * @return array|bool returns an array of monthly stats objects
     */
    public function stat_monthly_user(string $mac, int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (13 * 7 * 24 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/monthly.user', $payload);
    }

    /**
     * Fetch 5-minute gateway stats
     *
     * @note - defaults to the past 12 hours
     *       - this function/method is only supported on controller versions 5.5.* and later
     *       - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *         the controller settings
     *       - requires a UniFi gateway
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                       mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                       lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                       default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array|bool returns an array of 5-minute stats objects for the gateway belonging to the current site
     */
    public function stat_5minutes_gateway(int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.gw', $payload);
    }

    /**
     * Fetch hourly gateway stats
     *
     * @note - defaults to the past 7*24 hours
     *       - requires a UniFi gateway
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                       mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                       lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                       default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array|bool returns an array of hourly stats objects for the gateway belonging to the current site
     */
    public function stat_hourly_gateway(int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.gw', $payload);
    }

    /**
     * Fetch daily gateway stats
     *
     * @note - defaults to the past 52 weeks (52*7*24 hours)
     *       - requires a UniFi gateway
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                       mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                       lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                       default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array|bool returns an array of hourly stats objects for the gateway belonging to the current site
     */
    public function stat_daily_gateway(int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? (time() - (time() % 3600)) * 1000 : $end;
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.gw', $payload);
    }

    /**
     * Fetch monthly gateway stats
     *
     * @note - defaults to the past 52 weeks (52*7*24 hours)
     *       - requires a UniFi gateway
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param array|null $attribs array containing attributes (strings) to be returned, valid values are:
     *                       mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                       lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                       default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array|bool returns an array of monthly stats objects for the gateway belonging to the current site
     */
    public function stat_monthly_gateway(int $start = null, int $end = null, array $attribs = null)
    {
        $end     = empty($end) ? (time() - (time() % 3600)) * 1000 : $end;
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : $start;
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/monthly.gw', $payload);
    }

    /**
     * Fetch speed test results
     *
     * @note - defaults to the past 24 hours
     *       - requires a UniFi gateway
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @return array|bool returns an array of speed test result objects
     */
    public function stat_speedtest_results(int $start = null, int $end = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (24 * 3600 * 1000) : $start;
        $payload = ['attrs' => ['xput_download', 'xput_upload', 'latency', 'time'], 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/archive.speedtest', $payload);
    }

    /**
     * Fetch IPS/IDS events
     *
     * @note - defaults to the past 24 hours
     *       - requires a UniFi gateway
     *       - supported in UniFi controller versions 5.9.X and higher
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param int|null $limit optional, maximum number of events to return, defaults to 10000
     * @return array|bool returns an array of IPS/IDS event objects
     */
    public function stat_ips_events(int $start = null, int $end = null, int $limit = null)
    {
        $end     = empty($end) ? time() * 1000 : $end;
        $start   = empty($start) ? $end - (24 * 3600 * 1000) : $start;
        $limit   = empty($limit) ? 10000 : $limit;
        $payload = ['start' => $start, 'end' => $end, '_limit' => $limit];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/ips/event', $payload);
    }

    /**
     * Fetch login sessions
     *
     * @note defaults to the past 7*24 hours
     *
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @param string|null $mac optional, client MAC address to return sessions for (can only be used when start and end
     *                      are also provided)
     * @param string $type optional, client type to return sessions for, can be 'all', 'guest' or 'user'; default
     *                      value is 'all'
     * @return array|bool returns an array of login session objects for all devices or a single device
     */
    public function stat_sessions(int $start = null, int $end = null, string $mac = null, string $type = 'all')
    {
        if (!in_array($type, ['all', 'guest', 'user'])) {
            return false;
        }

        $end     = empty($end) ? time() : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600) : $start;
        $payload = ['type' => $type, 'start' => $start, 'end' => $end];

        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/session', $payload);
    }

    /**
     * Fetch latest 'n' login sessions for a single client device
     *
     * @note defaults to the past 7*24 hours
     *
     * @param string $mac client MAC address
     * @param int|null $limit optional, maximum number of sessions to get (default value is 5)
     * @return array|bool returns an array of login session objects for all devices or a single device
     */
    public function stat_sta_sessions_latest(string $mac, int $limit = null)
    {
        $limit   = empty($limit) ? 5 : $limit;
        $payload = ['mac' => strtolower($mac), '_limit' => $limit, '_sort' => '-assoc_time'];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/session', $payload);
    }

    /**
     * Fetch authorizations
     *
     * @note defaults to the past 7*24 hours
     *
     * @param int|null $start optional, Unix timestamp in milliseconds
     * @param int|null $end optional, Unix timestamp in milliseconds
     * @return array|bool returns an array of authorization objects
     */
    public function stat_auths(int $start = null, int $end = null)
    {
        $end     = empty($end) ? time() : $end;
        $start   = empty($start) ? $end - (7 * 24 * 3600) : $start;
        $payload = ['start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/authorization', $payload);
    }

    /**
     * Fetch client devices that connected to the site within given timeframe
     *
     * @note $historyhours is only used to select clients that were online within that period,
     *       the returned stats per client are all-time totals, irrespective of the value of $historyhours
     * @param int $historyhours optional, hours to go back (default is 8760 hours or 1 year)
     * @return array|bool returns an array of client device objects
     */
    public function stat_allusers(int $historyhours = 8760)
    {
        $payload = ['type' => 'all', 'conn' => 'all', 'within' => $historyhours];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/alluser', $payload);
    }

    /**
     * Fetch guest devices
     *
     * @note defaults to the past 7*24 hours
     * @param int $within optional, time frame in hours to go back to list guests with valid access (default = 24*365
     *                    hours)
     * @return array|bool returns an array of guest device objects with valid access
     */
    public function list_guests(int $within = 8760)
    {
        $payload = ['within' => $within];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/guest', $payload);
    }

    /**
     * Fetch online client device(s)
     *
     * @param string|null $mac optional, the MAC address of a single online client device for which the call must be
     *                         made
     * @return array|bool returns an array of online client device objects, or in case of a single device request, returns a
     *                    single client device object, false upon error
     */
    public function list_clients(string $mac = null)
    {
        if (is_string($mac)) {
            $mac = strtolower(trim($mac));
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/sta/' . $mac);
    }

    /**
     * Fetch details for a single client device
     *
     * @param string $mac client device MAC address
     * @return array|bool returns an object with the client device information
     */
    public function stat_client(string $mac)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/user/' . strtolower(trim($mac)));
    }

    /**
     * Fetch fingerprints for client devices
     *
     * @param int $fingerprint_source the id of the client fingerprint_source, starts from 0, this matches the
     *                                fingerprint_source in the client device objects, the default value is 0
     * @return array|bool an array of fingerprints, contain dev_ids, dev_type_ids, family_ids, os_name_ids, os_class_ids
     *                    and vendor_ids, false upon error
     */
    public function list_fingerprint_devices(int $fingerprint_source = 0)
    {
        return $this->fetch_results('/v2/api/fingerprint_devices/' . $fingerprint_source);
    }

    /**
     * Assign a client device to another group
     *
     * @param string $client_id _id value of the client device to be modified
     * @param string $group_id _id value of the user group to assign client device to
     * @return bool true upon success
     */
    public function set_usergroup(string $client_id, string $group_id): bool
    {
        $payload = ['usergroup_id' => $group_id];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/user/' . trim($client_id), $payload);
    }

    /**
     * Update a client device's fixed IP address (using REST)
     *
     * @param string $client_id _id value for the client device
     * @param bool $use_fixedip determines whether to enable the fixed IP address or not
     * @param string|null $network_id optional, _id value for the network where the ip belongs to
     * @param string|null $fixed_ip optional, IP address, value of client device's fixed_ip field
     * @return array|bool returns an array containing a single object with attributes of the updated client on success
     */
    public function edit_client_fixedip(string $client_id, bool $use_fixedip, string $network_id = null, string $fixed_ip = null)
    {
        $this->curl_method = 'PUT';

        $payload = [
            '_id'         => $client_id,
            'use_fixedip' => $use_fixedip,
        ];

        if ($use_fixedip) {
            if ($network_id) {
                $payload['network_id'] = $network_id;
            }

            if ($fixed_ip) {
                $payload['fixed_ip'] = $fixed_ip;
            }
        }

        return $this->fetch_results('/api/s/' . $this->site . '/rest/user/' . trim($client_id), $payload);
    }

    /**
     * Update client device name (using REST)
     *
     * @param string $client_id _id value for the client device
     * @param string $name name of the client
     * @return array|bool returns an array containing a single object with attributes of the updated client on success
     */
    public function edit_client_name(string $client_id, string $name)
    {
        if (empty($name)) {
            return false;
        }

        $this->curl_method = 'PUT';

        $payload = [
            '_id'  => $client_id,
            'name' => $name,
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/user/' . trim($client_id), $payload);
    }

    /**
     * Fetch user groups
     *
     * @return array|bool returns an array of user group objects
     */
    public function list_usergroups()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/usergroup');
    }

    /**
     * Create a user group (using REST)
     *
     * @param string $group_name name of the user group
     * @param int $group_dn limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @param int $group_up limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @return array|bool containing a single object with attributes of the new usergroup ("_id", "name",
     *               "qos_rate_max_down", "qos_rate_max_up", "site_id") on success
     */
    public function create_usergroup(string $group_name, int $group_dn = -1, int $group_up = -1)
    {
        $payload = [
            'name'              => $group_name,
            'qos_rate_max_down' => $group_dn,
            'qos_rate_max_up'   => $group_up,
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/usergroup', $payload);
    }

    /**
     * Modify user group (using REST)
     *
     * @param string $group_id _id value of the user group
     * @param string $site_id _id value of the site
     * @param string $group_name name of the user group
     * @param int $group_dn limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @param int $group_up limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @return array|bool returns an array containing a single object with attributes of the updated usergroup on success
     */
    public function edit_usergroup(string $group_id, string $site_id, string $group_name, int $group_dn = -1, int $group_up = -1)
    {
        $this->curl_method = 'PUT';

        $payload = [
            '_id'               => $group_id,
            'name'              => $group_name,
            'qos_rate_max_down' => $group_dn,
            'qos_rate_max_up'   => $group_up,
            'site_id'           => $site_id,
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/usergroup/' . trim($group_id), $payload);
    }

    /**
     * Delete user group (using REST)
     *
     * @param string $group_id _id value of the user group to delete
     * @return bool returns true on success
     */
    public function delete_usergroup(string $group_id): bool
    {
        $this->curl_method = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/usergroup/' . trim($group_id));
    }

    /**
     * Fetch AP groups
     *
     * @return array|bool containing the current AP groups on success
     */
    public function list_apgroups()
    {
        return $this->fetch_results('/v2/api/site/' . $this->site . '/apgroups');
    }

    /**
     * Create AP group
     *
     * @param string $group_name name to assign to the AP group
     * @param array $device_macs optional, array containing the MAC addresses (strings) of the APs to add to the new
     *                            group
     * @return array|bool containing a single object with attributes of the new AP group on success
     */
    public function create_apgroup(string $group_name, array $device_macs = [])
    {
        $payload = ['device_macs' => $device_macs, 'name' => $group_name];

        return $this->fetch_results('/v2/api/site/' . $this->site . '/apgroups', $payload);
    }

    /**
     * Modify AP group
     *
     * @param string $group_id _id value of the AP group to modify
     * @param string $group_name name to assign to the AP group
     * @param array $device_macs array containing the members of the AP group which overwrites the existing
     *                            group_members (passing an empty array clears the AP member list)
     * @return array|bool containing a single object with attributes of the updated AP group on success
     */
    public function edit_apgroup(string $group_id, string $group_name, array $device_macs)
    {
        $this->curl_method = 'PUT';

        $payload = [
            '_id'            => $group_id,
            'attr_no_delete' => false,
            'name'           => $group_name,
            'device_macs'    => $device_macs,
        ];

        return $this->fetch_results('/v2/api/site/' . $this->site . '/apgroups/' . trim($group_id), $payload);
    }

    /**
     * Delete AP group
     *
     * @param string $group_id _id value of the AP group to delete
     * @return bool returns true on success
     */
    public function delete_apgroup(string $group_id): bool
    {
        $this->curl_method = 'DELETE';

        return $this->fetch_results_boolean('/v2/api/site/' . $this->site . '/apgroups/' . trim($group_id));
    }

    /**
     * Fetch firewall groups (using REST)
     *
     * @param string $group_id optional, _id value of the single firewall group to list
     * @return array|bool containing the current firewall groups or the selected firewall group on success
     */
    public function list_firewallgroups(string $group_id = '')
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id));
    }

    /**
     * Create a firewall group (using REST)
     *
     * @param string $group_name name to assign to the firewall group
     * @param string $group_type firewall group type; valid values are address-group, ipv6-address-group, port-group
     * @param array $group_members array containing the members of the new group (IPv4 addresses, IPv6 addresses or
     *                              port numbers)
     *                              (default is an empty array)
     * @return array|bool containing a single object with attributes of the new firewall group on success
     */
    public function create_firewallgroup(string $group_name, string $group_type, array $group_members = [])
    {
        if (!in_array($group_type, ['address-group', 'ipv6-address-group', 'port-group'])) {
            return false;
        }

        $payload = ['name' => $group_name, 'group_type' => $group_type, 'group_members' => $group_members];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallgroup', $payload);
    }

    /**
     * Modify a firewall group (using REST)
     *
     * @param string $group_id _id value of the firewall group to modify
     * @param string $site_id site_id value of the firewall group to modify
     * @param string $group_name name of the firewall group
     * @param string $group_type firewall group type; valid values are address-group, ipv6-address-group,
     *                              port-group,
     *                              group_type cannot be changed for an existing firewall group!
     * @param array $group_members array containing the members of the group (IPv4 addresses, IPv6 addresses or port
     *                              numbers) which overwrites the existing group_members (default is an empty array)
     * @return array|bool containing a single object with attributes of the updated firewall group on success
     */
    public function edit_firewallgroup(string $group_id, string $site_id, string $group_name, string $group_type, array $group_members = [])
    {
        if (!in_array($group_type, ['address-group', 'ipv6-address-group', 'port-group'])) {
            return false;
        }

        $this->curl_method = 'PUT';

        $payload = [
            '_id'           => $group_id,
            'name'          => $group_name,
            'group_type'    => $group_type,
            'group_members' => $group_members,
            'site_id'       => $site_id,
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id), $payload);
    }

    /**
     * Delete firewall group (using REST)
     *
     * @param string $group_id _id value of the firewall group to delete
     * @return bool returns true on success
     */
    public function delete_firewallgroup(string $group_id): bool
    {
        $this->curl_method = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id));
    }

    /**
     * Fetch firewall rules (using REST)
     *
     * @return array|bool containing the current firewall rules on success
     */
    public function list_firewallrules()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallrule');
    }

    /**
     * Fetch static routing settings (using REST)
     *
     * @param string $route_id _id value of the static route to get settings for
     * @return array|bool containing the static routes and their settings
     */
    public function list_routing(string $route_id = '')
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/routing/' . trim($route_id));
    }

    /**
     * Fetch health metrics
     *
     * @return array|bool  containing health metric objects
     */
    public function list_health()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/health');
    }

    /**
     * Fetch dashboard metrics
     *
     * @param boolean $five_minutes when true, return stats based on 5 minute intervals,
     *                              returns hourly stats by default (supported on controller versions 5.5.* and higher)
     * @return array|bool containing dashboard metric objects (available since controller version 4.9.1.alpha)
     */
    public function list_dashboard(bool $five_minutes = false)
    {
        $path_suffix = $five_minutes ? '?scale=5minutes' : null;

        return $this->fetch_results('/api/s/' . $this->site . '/stat/dashboard' . $path_suffix);
    }

    /**
     * Fetch client devices
     *
     * @return array|bool containing known client device objects
     */
    public function list_users()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/user');
    }

    /**
     * List of UniFi devices with a basic subset of properties (e.g., mac, state, adopted, disabled, type, model, name)
     *
     * @return array|bool an array containing known UniFi device objects, false upon error
     */
    public function list_devices_basic()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/device-basic');
    }

    /**
     * Fetch UniFi devices
     *
     * @param array|string $macs optional, array containing the MAC addresses (lowercase strings) of the devices
     *                           to filter by. May also be a (lowercase) string containing a single MAC address
     * @return array|bool an array containing known UniFi device objects, optionally filtered by the <macs>
     *                    parameter, false upon error
     */
    public function list_devices($macs = [])
    {
        $payload = ['macs' => array_map('strtolower', (array)$macs)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/device', $payload);
    }

    /**
     * Fetch (device) tags (using REST)
     *
     * @note this endpoint was introduced with controller versions 5.5.X
     * @return array|bool containing known device tag objects
     */
    public function list_tags()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/tag');
    }

    /**
     * Create (device) tag (using REST)
     *
     * @note this endpoint was introduced with controller versions 5.5.X
     * @param string $name required, the tag name to add
     * @param array|null $macs optional, an array of the MAC address(es) of the device(s) to tag with the new tag
     * @return bool return true on success
     */
    public function create_tag(string $name, array $macs = null): bool
    {
        $payload = ['name' => $name];

        if (is_array($macs)) {
            $payload['member_table'] = $macs;
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/tag', $payload);
    }

    /**
     * Set tagged devices (using REST)
     *
     * @note this endpoint was introduced with controller versions 5.5.X
     * @param array $macs required, an array of the MAC address(es) of the device(s) to tag
     * @param string $tag_id required, the _id value of the tag to set
     * @return bool return true on success
     */
    public function set_tagged_devices(array $macs, string $tag_id): bool
    {
        $this->curl_method = 'PUT';

        $payload = ['member_table' => $macs];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/tag/' . $tag_id, $payload);
    }

    /**
     * Get (device) tag (using REST)
     *
     * @note this endpoint was introduced with controller versions 5.5.X
     * @param string $tag_id required, the _id value of the tag to retrieve
     * @return array|bool containing matching tag objects
     */
    public function get_tag(string $tag_id)
    {
        $this->curl_method = 'GET';

        return $this->fetch_results('/api/s/' . $this->site . '/rest/tag/' . $tag_id);
    }

    /**
     * Delete (device) tag (using REST)
     *
     * @note this endpoint was introduced with controller versions 5.5.X
     * @param string $tag_id required, the _id value of the tag to set
     * @return bool return true on success
     */
    public function delete_tag(string $tag_id): bool
    {
        $this->curl_method = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/tag/' . $tag_id);
    }

    /**
     * Fetch rogue/neighboring access points
     *
     * @param int $within optional, hours to go back to list discovered "rogue" access points (default = 24 hours)
     * @return array|bool containing rogue/neighboring access point objects
     */
    public function list_rogueaps(int $within = 24)
    {
        $payload = ['within' => $within];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/rogueap', $payload);
    }

    /**
     * Fetch known rogue access points
     *
     * @return array|bool containing known rogue access point objects
     */
    public function list_known_rogueaps()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/rogueknown');
    }

    /**
     * Generate a backup
     *
     * @note this is an experimental function, please do not use unless you know exactly what you're doing
     * @param int $days number of days for which the backup must be generated
     * @return array|bool URL from where the backup file can be downloaded once generated, false upon failure
     */
    public function generate_backup(int $days = -1)
    {
        $payload = ['cmd' => 'backup', 'days' => $days];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/backup', $payload);
    }

    /**
     * Fetch auto backups
     *
     * @return array|bool containing objects with backup details on success
     */
    public function list_backups()
    {
        $payload = ['cmd' => 'list-backups'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/backup', $payload);
    }

    /**
     * Generate a backup/export of the current site
     *
     * @note this is an experimental function, please do not use unless you know exactly what you're doing
     * @return array|bool URL from where the backup/export file can be downloaded once generated, false upon failure
     */
    public function generate_backup_site()
    {
        $payload = ['cmd' => 'export-site'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/backup', $payload);
    }

    /**
     * Fetch sites
     *
     * @return array|bool a list of sites on this controller that the credentials used have access to,
     *                    together with some basic attributes for each site
     */
    public function list_sites()
    {
        return $this->fetch_results('/api/self/sites');
    }

    /**
     * Fetch sites stats
     *
     * @note this endpoint was introduced with controller version 5.2.9
     * @return array|bool statistics for all sites hosted on this controller
     */
    public function stat_sites()
    {
        return $this->fetch_results('/api/stat/sites');
    }

    /**
     * Create a site
     *
     * @param string $description the long name for the new site
     * @return array|bool false on failure or a single object with attributes of the new site ("_id", "desc", "name") on
     *                    success
     */
    public function create_site(string $description)
    {
        $payload = ['desc' => $description, 'cmd' => 'add-site'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Delete a site
     *
     * @param string $site_id _id value of the site to delete
     * @return bool true on success
     */
    public function delete_site(string $site_id): bool
    {
        $payload = ['site' => $site_id, 'cmd' => 'delete-site'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Change the current site's name
     *
     * @note immediately after the change, the site is available in the output of the list_sites() function
     * @param string $site_name the new long name for the current site
     * @return bool true on success
     */
    public function set_site_name(string $site_name): bool
    {
        $payload = ['cmd' => 'update-site', 'desc' => $site_name];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Update site country
     *
     * @param string $country_id _id value of the country key
     * @param object|array $payload a stdClass object or associative array containing the configuration to apply to
     *                              the site. Must be a (partial) object/array structured in the same manner as is
     *                              returned by list_settings() for the section with the "country" key. Valid
     *                              country codes can be obtained using the list_country_codes() function/method.
     * @return bool true on success
     */
    public function set_site_country(string $country_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/country/' . trim($country_id),
            $payload);
    }

    /**
     * Update site locale
     *
     * @param string $locale_id _id value of the locale section
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              site. Must be a (partial) object/array structured in the same manner as is
     *                              returned by list_settings() for the section with the "locale" key. Valid
     *                              timezones can be obtained in JavaScript as explained here:
     *                              https://stackoverflow.com/questions/38399465/how-to-get-list-of-all-timezones-in-javascript
     *                              or in PHP using timezone_identifiers_list().
     * @return bool true on success
     */
    public function set_site_locale(string $locale_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/locale/' . trim($locale_id),
            $payload);
    }

    /**
     * Update site snmp
     *
     * @param string $snmp_id _id value of the snmp section
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              site. Must be a (partial) object/array structured in the same manner as is returned
     *                              by list_settings() for the section with the "snmp" key.
     * @return bool true on success
     */
    public function set_site_snmp(string $snmp_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/snmp/' . trim($snmp_id), $payload);
    }

    /**
     * Update site mgmt
     *
     * @param string $mgmt_id _id value of the mgmt section
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              site. Must be a (partial) object/array structured in the same manner as is returned
     *                              by list_settings() for the section with the "mgmt" key.
     * @return bool true on success
     */
    public function set_site_mgmt(string $mgmt_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/mgmt/' . trim($mgmt_id), $payload);
    }

    /**
     * Update site guest access
     *
     * @param string $guest_access_id _id value of the guest_access section
     * @param object|array $payload stdClass object or associative array containing the configuration to apply
     *                              to the site. Must be a (partial) object/array structured in the same manner
     *                              as is returned by list_settings() for the section with the "guest_access" key.
     * @return bool true on success
     */
    public function set_site_guest_access(string $guest_access_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/guest_access/' . trim($guest_access_id),
            $payload);
    }

    /**
     * Update site ntp
     *
     * @param string $ntp_id _id value of the ntp section
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              site. Must be a (partial) object/array structured in the same manner as is returned
     *                              by list_settings() for the section with the "ntp" key.
     * @return bool true on success
     */
    public function set_site_ntp(string $ntp_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/ntp/' . trim($ntp_id), $payload);
    }

    /**
     * Update site connectivity
     *
     * @param string $connectivity_id _id value of the connectivity section
     * @param object|array $payload stdClass object or associative array containing the configuration to apply
     *                              to the site. Must be a (partial) object/array structured in the same manner
     *                              as is returned by list_settings() for the section with the "connectivity" key.
     * @return bool true on success
     */
    public function set_site_connectivity(string $connectivity_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/connectivity/' . trim($connectivity_id),
            $payload);
    }

    /**
     * Fetch admins
     *
     * @return array|bool containing administrator objects for selected site
     */
    public function list_admins()
    {
        $payload = ['cmd' => 'get-admins'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Fetch all admins
     *
     * @return array|bool containing administrator objects for all sites
     */
    public function list_all_admins()
    {
        return $this->fetch_results('/api/stat/admin');
    }

    /**
     * Invite a new admin for access to the current site
     *
     * @note - after issuing a valid request, an invite is sent to the email address provided
     *       - issuing this command against an existing admin triggers a "re-invite"
     * @param string $name name to assign to the new admin user
     * @param string $email email address to assign to the new admin user
     * @param bool $enable_sso optional, whether SSO is allowed for the new admin
     *                         default value is true which enables the SSO capability
     * @param bool $readonly optional, whether the new admin has readonly
     *                       permissions, default value is false which gives the new admin
     *                       Administrator permissions
     * @param bool $device_adopt optional, whether the new admin has permissions to
     *                           adopt devices, default value is false. With versions < 5.9.X this only applies
     *                           when readonly is true.
     * @param bool $device_restart optional, whether the new admin has permissions to
     *                             restart devices, default value is false. With versions < 5.9.X this only applies
     *                             when readonly is true.
     * @return bool true on success
     */
    public function invite_admin(
        string $name,
        string $email,
        bool   $enable_sso = true,
        bool   $readonly = false,
        bool   $device_adopt = false,
        bool   $device_restart = false
    ): bool
    {
        $email       = trim($email);
        $email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);

        if (!$email_valid) {
            trigger_error('The email address provided is invalid!');
            return false;
        }

        $payload = [
            'name'        => trim($name),
            'email'       => $email,
            'for_sso'     => $enable_sso,
            'cmd'         => 'invite-admin',
            'role'        => 'admin',
            'permissions' => [],
        ];

        if ($readonly) {
            $payload['role'] = 'readonly';
        }

        if ($device_adopt) {
            $payload['permissions'][] = 'API_DEVICE_ADOPT';
        }

        if ($device_restart) {
            $payload['permissions'][] = 'API_DEVICE_RESTART';
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Assign an existing admin to the current site
     *
     * @param string $admin_id _id value of the admin user to assign, can be obtained using the
     *                               list_all_admins() method/function
     * @param bool $readonly optional, whether the new admin has readonly
     *                               permissions, default value is false which gives the new admin
     *                               Administrator permissions
     * @param bool $device_adopt optional, whether the new admin has permissions to
     *                               adopt devices, default value is false. With versions < 5.9.X this only applies
     *                               when readonly is true.
     * @param bool $device_restart optional, whether the new admin has permissions to
     *                               restart devices, default value is false. With versions < 5.9.X this only applies
     *                               when readonly is true.
     * @return bool true on success
     */
    public function assign_existing_admin(
        string $admin_id,
        bool   $readonly = false,
        bool   $device_adopt = false,
        bool   $device_restart = false
    ): bool
    {
        $payload = [
            'cmd'         => 'grant-admin',
            'admin'       => trim($admin_id),
            'role'        => 'admin',
            'permissions' => [],
        ];

        if ($readonly) {
            $payload['role'] = 'readonly';
        }

        if ($device_adopt) {
            $payload['permissions'][] = 'API_DEVICE_ADOPT';
        }

        if ($device_restart) {
            $payload['permissions'][] = 'API_DEVICE_RESTART';
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Update an admin of the current site
     *
     * @param string $admin_id _id value of the admin user to update, can be obtained using the
     *                         list_all_admins() method/function
     * @param string $name update the name of the admin user
     * @param string $email update the email address of the admin user
     * @param string $password optionally update the password of the admin user
     * @param bool $readonly optional, whether the new admin has readonly
     *                       permissions, default value is false which gives the new admin
     *                       Administrator permissions
     * @param bool $device_adopt optional, whether the new admin has permissions to
     *                           adopt devices, default value is false. With versions < 5.9.X this only applies
     *                           when readonly is true.
     * @param bool $device_restart optional, whether the new admin has permissions to
     *                             restart devices, default value is false. With versions < 5.9.X this only applies
     *                             when readonly is true.
     * @return bool true on success
     */
    public function update_admin(
        string $admin_id,
        string $name,
        string $email,
        string $password = '',
        bool   $readonly = false,
        bool   $device_adopt = false,
        bool   $device_restart = false
    ): bool
    {
        $email       = trim($email);
        $email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);

        if (!$email_valid) {
            trigger_error('The email address provided is invalid!');
            return false;
        }

        $payload = [
            'admin'       => trim($admin_id),
            'name'        => trim($name),
            'email'       => $email,
            'cmd'         => 'update-admin',
            'role'        => 'admin',
            'x_password'  => $password,
            'permissions' => [],
        ];

        if ($readonly) {
            $payload['role'] = 'readonly';
        }

        if ($device_adopt) {
            $payload['permissions'][] = 'API_DEVICE_ADOPT';
        }

        if ($device_restart) {
            $payload['permissions'][] = 'API_DEVICE_RESTART';
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Revoke an admin from the current site
     *
     * @note only non-superadmin accounts can be revoked
     * @param string $admin_id _id value of the admin to revoke, can be obtained using the
     *                         list_all_admins() method/function
     * @return bool true on success
     */
    public function revoke_admin(string $admin_id): bool
    {
        $payload = ['cmd' => 'revoke-admin', 'admin' => $admin_id];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Delete an admin entirely
     *
     * @note Make sure the admin is revoked from all the sites before deleting
     * @param string $admin_id _id value of the admin to delete, can be obtained using the
     *                         list_all_admins() method/function
     * @return bool true on success
     */
    public function delete_admin(string $admin_id): bool
    {
        $payload = ['cmd' => 'revoke-super-admin', 'admin' => $admin_id];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Fetch WLAN groups
     *
     * @return array|bool containing known wlan_groups
     */
    public function list_wlan_groups()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/wlangroup');
    }

    /**
     * Fetch sysinfo
     *
     * @return array|bool containing known sysinfo data
     */
    public function stat_sysinfo()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/sysinfo');
    }

    /**
     * Fetch controller status
     *
     * @note login is not required
     * @return bool true upon success (controller is online)
     */
    public function stat_status(): bool
    {
        return $this->fetch_results_boolean('/status', null, false);
    }

    /**
     * Fetch full controller status
     *
     * @note login is not required
     *
     * @return bool|array status array upon success, false upon failure
     */
    public function stat_full_status()
    {
        $this->fetch_results_boolean('/status', null, false);

        return json_decode($this->get_last_results_raw());
    }

    /**
     * Fetch device name mappings
     *
     * @note login is not required
     * @return bool|array mappings array upon success, false upon failure
     */
    public function list_device_name_mappings()
    {
        $this->fetch_results_boolean('/dl/firmware/bundles.json', null, false);

        return json_decode($this->get_last_results_raw());
    }

    /**
     * Fetch self
     *
     * @return array|bool containing information about the logged-in user
     */
    public function list_self()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/self');
    }

    /**
     * Fetch vouchers
     *
     * @param int|null $create_time optional, create time of the vouchers to fetch in Unix timestamp in seconds
     * @return array|bool containing hotspot voucher objects
     */
    public function stat_voucher(int $create_time = null)
    {
        $payload = isset($create_time) ? ['create_time' => $create_time] : [];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/voucher', $payload);
    }

    /**
     * Fetch payments
     *
     * @param int|null $within optional, number of hours to go back to fetch payments
     * @return array|bool containing hotspot payments
     */
    public function stat_payment(int $within = null)
    {
        $path_suffix = isset($within) ? '?within=' . $within : '';

        return $this->fetch_results('/api/s/' . $this->site . '/stat/payment' . $path_suffix);
    }

    /**
     * Create hotspot operator (using REST)
     *
     * @param string $name name for the hotspot operator
     * @param string $x_password clear text password for the hotspot operator
     * @param string $note optional, note to attach to the hotspot operator
     * @return bool true upon success
     */
    public function create_hotspotop(string $name, string $x_password, string $note = ''): bool
    {
        $payload = ['name' => $name, 'x_password' => $x_password];
        if (!empty($note)) {
            $payload['note'] = trim($note);
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/hotspotop', $payload);
    }

    /**
     * Fetch hotspot operators (using REST)
     *
     * @return array|bool containing hotspot operators
     */
    public function list_hotspotop()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/hotspotop');
    }

    /**
     * Create voucher(s)
     *
     * @note please use the stat_voucher() method/function to retrieve the newly created voucher(s) by create_time
     * @param int $minutes minutes the voucher is valid after activation (expiration time)
     * @param int $count number of vouchers to create, default value is 1
     * @param int $quota single-use or multi-use vouchers, value '0' is for multi-use, '1' is for single-use,
     *                          'n' is for multi-use n times
     * @param string $note note text to add to voucher when printing
     * @param int|null $up upload speed limit in kbps
     * @param int|null $down download speed limit in kbps
     * @param int|null $megabytes data transfer limit in MB
     * @return array containing a single object which contains the create_time(stamp) of the voucher(s) created
     */
    public function create_voucher(
        int    $minutes,
        int    $count = 1,
        int    $quota = 0,
        string $note = '',
        int    $up = null,
        int    $down = null,
        int    $megabytes = null
    ): array
    {
        $payload = [
            'cmd'    => 'create-voucher',
            'expire' => $minutes,
            'n'      => $count,
            'quota'  => $quota,
        ];

        if (!empty($note)) {
            $payload['note'] = trim($note);
        }

        if (!is_null($up)) {
            $payload['up'] = $up;
        }

        if (!is_null($down)) {
            $payload['down'] = $down;
        }

        if (!is_null($megabytes)) {
            $payload['bytes'] = $megabytes;
        }

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/hotspot', $payload);
    }

    /**
     * Revoke voucher
     *
     * @param string $voucher_id _id value of the voucher to revoke
     * @return bool true on success
     */
    public function revoke_voucher(string $voucher_id): bool
    {
        $payload = ['_id' => $voucher_id, 'cmd' => 'delete-voucher'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/hotspot', $payload);
    }

    /**
     * Extend guest authorization
     *
     * @param string $guest_id _id value of the guest to extend the authorization for
     * @return bool true on success
     */
    public function extend_guest_validity(string $guest_id): bool
    {
        $payload = ['_id' => $guest_id, 'cmd' => 'extend'];
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/hotspot', $payload);
    }

    /**
     * Fetch port forwarding stats
     *
     * @return array|bool containing port forwarding stats
     */
    public function list_portforward_stats()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/portforward');
    }

    /**
     * Fetch DPI stats
     *
     * @return array|bool containing DPI stats
     */
    public function list_dpi_stats()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/dpi');
    }

    /**
     * Fetch filtered DPI stats
     *
     * @param string $type optional, whether to returns stats by app or by category, valid values:
     *                           'by_cat' or 'by_app'
     * @param array|null $cat_filter optional, array containing numeric category ids to filter by,
     *                           only to be combined with a "by_app" value for $type
     * @return array|bool containing filtered DPI stats
     */
    public function list_dpi_stats_filtered(string $type = 'by_cat', array $cat_filter = null)
    {
        if (!in_array($type, ['by_cat', 'by_app'])) {
            return false;
        }

        $payload = ['type' => $type];

        if (is_array($cat_filter) && $type === 'by_app') {
            $payload['cats'] = $cat_filter;
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/sitedpi', $payload);
    }

    /**
     * Fetch current channels
     *
     * @return array|bool containing currently allowed channels
     */
    public function list_current_channels()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/current-channel');
    }

    /**
     * Fetch country codes
     *
     * @note these codes following the ISO standard:
     * https://en.wikipedia.org/wiki/ISO_3166-1_numeric
     *
     * @return array|bool containing available country codes
     */
    public function list_country_codes()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/ccode');
    }

    /**
     * Fetch port forwarding settings
     *
     * @return array|bool containing port forwarding settings
     */
    public function list_portforwarding()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/portforward');
    }

    /**
     * Fetch port configurations
     *
     * @return array|bool containing port configurations
     */
    public function list_portconf()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/portconf');
    }

    /**
     * Fetch VoIP extensions
     *
     * @return array|bool containing VoIP extensions
     */
    public function list_extension()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/extension');
    }

    /**
     * Fetch site settings
     *
     * @return array|bool containing site configuration settings
     */
    public function list_settings()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/get/setting');
    }

    /**
     * Adopt a device to the selected site
     *
     * @param string|array $macs device MAC address or an array of MAC addresses
     * @return bool true on success
     */
    public function adopt_device($macs): bool
    {
        $payload = [
            'macs' => array_map('strtolower', (array)$macs),
            'cmd' => 'adopt'
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Adopt a device using custom SSH credentials
     *
     * @param string $mac device MAC address
     * @param string $ip IP to use for SSH connection
     * @param string $username SSH username
     * @param string $password SSH password
     * @param string $url inform URL to point the device to
     * @param int $port optional, SSH port
     * @param bool $ssh_key_verify optional, if true, verify the SSH key for the device
     * @return bool true on success
     */
    public function advanced_adopt_device(
        string $mac,
        string $ip,
        string $username,
        string $password,
        string $url,
        int    $port = 22,
        bool   $ssh_key_verify = true
    ): bool
    {
        $payload = [
            'cmd'          => 'adv-adopt',
            'mac'          => strtolower($mac),
            'ip'           => $ip,
            'username'     => $username,
            'password'     => $password,
            'url'          => $url,
            'port'         => $port,
            'sshKeyVerify' => $ssh_key_verify,
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Migrate one or more devices.
     *
     * @param string|array $macs single device MAC address string or an array of MAC addresses
     * @param string $inform_url inform URL to point the device to (e.g., http://10.1.0.10:9080/inform)
     * @return bool true on success
     */
    public function migrate_device($macs, string $inform_url): bool
    {
        $payload = [
            'cmd'        => 'migrate',
            'inform_url' => $inform_url,
            'macs'       => array_map('strtolower', (array)$macs)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Cancel migration for one or more devices.
     *
     * @param string|array $macs single device MAC address string or an array of MAC addresses
     * @return bool true on success
     */
    public function cancel_migrate_device($macs): bool
    {
        $payload = [
            'cmd'  => 'cancel-migrate',
            'macs' => array_map('strtolower', (array)$macs)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Reboot one or more devices.
     *
     * @param string|array $macs single device MAC address string or an array of MAC addresses
     * @param string $reboot_type optional, two options: 'soft' or 'hard', defaults to soft
     *                            - soft can be used for all devices, requests a plain restart of that device
     *                            - hard is special for PoE switches, also requests a power cycle on all PoE
     *                              capable ports. Keep in mind that a 'hard' reboot
     *                            - does *NOT* trigger a factory-reset.
     * @return bool true on success
     */
    public function restart_device($macs, string $reboot_type = 'soft'): bool
    {
        $payload = [
            'cmd'  => 'restart',
            'macs' => array_map('strtolower', (array)$macs)
        ];

        if (!empty($reboot_type) && in_array($reboot_type, ['soft', 'hard'])) {
            $payload['reboot_type'] = strtolower($reboot_type);
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Force the provision of one or more devices.
     *
     * @note unclear whether this function is still supported across all controller versions
     * @param string|array $mac single device MAC address string or an array of MAC addresses
     * @return bool true on success
     */
    public function force_provision($mac): bool
    {
        $payload = [
            'cmd'  => 'force-provision',
            'macs' => array_map('strtolower', (array)$mac)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/', $payload);
    }

    /**
     * Reboot a UniFi CloudKey
     *
     * @note this API call has no effect on UniFi controllers *not* running on a UniFi OS device
     * @return bool true on success
     */
    public function reboot_cloudkey(): bool
    {
        $payload = ['cmd' => 'reboot'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/system', $payload);
    }

    /**
     * Disable/enable an access point (using REST)
     *
     * @note - a disabled device is excluded from the dashboard status and device count and its LED and WLAN are turned off
     *       - appears to only be supported for access points
     *       - available since controller versions 5.2.X
     *
     * @param string $ap_id value of _id for the access point which can be obtained from the device list
     * @param bool $disable true disables the device, false enables the device
     * @return bool true on success
     */
    public function disable_ap(string $ap_id, bool $disable): bool
    {
        $this->curl_method = 'PUT';

        $payload = ['disabled' => $disable];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/device/' . trim($ap_id), $payload);
    }

    /**
     * Override LED mode for a device (using REST)
     *
     * @note available since controller versions 5.2.X
     * @param string $device_id value of _id for the device which can be obtained from the device list
     * @param string $override_mode off/on/default; "off" disables the LED of the device,
     *                              "on" enables the LED of the device,
     *                              "default" applies the site-wide setting for device LEDs
     * @return bool true on success
     */
    public function led_override(string $device_id, string $override_mode): bool
    {
        if (!in_array($override_mode, ['off', 'on', 'default'])) {
            return false;
        }

        $this->curl_method = 'PUT';

        $payload = ['led_override' => $override_mode];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/device/' . trim($device_id), $payload);
    }

    /**
     * Toggle flashing LED of an access point for locating purposes
     *
     * @note replaces the old set_locate_ap() and unset_locate_ap() methods/functions
     * @param string $mac device MAC address
     * @param bool $enable true enables flashing LED, false disables flashing LED
     * @return bool true on success
     */
    public function locate_ap(string $mac, bool $enable): bool
    {
        $cmd = $enable ? 'set-locate' : 'unset-locate';

        $payload = ['cmd' => $cmd, 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Toggle LEDs of all the access points ON or OFF
     *
     * @param bool $enable true switches LEDs of all the access points ON, false switches them OFF
     * @return bool true on success
     */
    public function site_leds(bool $enable): bool
    {
        $payload = ['led_enabled' => $enable];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/mgmt', $payload);
    }

    /**
     * Update access point radio settings
     *
     * @note only supported on pre-5.X.X controller versions
     * @param string $ap_id the "_id" value for the access point you wish to update
     * @param string $radio radio to update, default=ng
     * @param int $channel channel to apply
     * @param int $ht channel width, default=20
     * @param string $tx_power_mode power level, "low", "medium", or "high"
     * @param int $tx_power transmit power level, default=0
     * @return bool true on success
     */
    public function set_ap_radiosettings(string $ap_id, string $radio, int $channel, int $ht, string $tx_power_mode, int $tx_power): bool
    {
        $payload = [
            'radio_table' => [
                'radio'         => $radio,
                'channel'       => $channel,
                'ht'            => $ht,
                'tx_power_mode' => $tx_power_mode,
                'tx_power'      => $tx_power,
            ],
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/device/' . trim($ap_id), $payload);
    }

    /**
     * Assign access point to another WLAN group
     *
     * @param string $type_id WLAN type, can be either 'ng' (for WLANs 2G (11n/b/g)) or 'na' (WLANs 5G (11n/a/ac))
     * @param string $device_id _id value of the access point to be modified
     * @param string $group_id _id value of the WLAN group to assign device to
     * @return bool true on success
     */
    public function set_ap_wlangroup(string $type_id, string $device_id, string $group_id): bool
    {
        if (!in_array($type_id, ['ng', 'na'])) {
            return false;
        }

        $payload = [
            'wlan_overrides'           => [],
            'wlangroup_id_' . $type_id => $group_id,
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/device/' . trim($device_id), $payload);
    }

    /**
     * Update guest login settings
     *
     * @note both portal parameters are set to the same value!
     * @param bool $portal_enabled enable/disable the captive portal
     * @param bool $portal_customized enable/disable captive portal customizations
     * @param bool $redirect_enabled enable/disable captive portal redirect
     * @param string $redirect_url url to redirect to, must include the http/https prefix, no trailing slashes
     * @param string $x_password the captive portal (simple) password
     * @param int $expire_number number of units for the authorization expiry
     * @param int $expire_unit number of minutes within a unit (a value 60 is required for hours)
     * @param string $section_id value of _id for the site settings section where key = "guest_access", settings
     *                                  can be obtained using the list_settings() function
     * @return bool true on success
     */
    public function set_guestlogin_settings(
        bool   $portal_enabled,
        bool   $portal_customized,
        bool   $redirect_enabled,
        string $redirect_url,
        string $x_password,
        int    $expire_number,
        int    $expire_unit,
        string $section_id
    ): bool
    {
        $payload = [
            'portal_enabled'    => $portal_enabled,
            'portal_customized' => $portal_customized,
            'redirect_enabled'  => $redirect_enabled,
            'redirect_url'      => $redirect_url,
            'x_password'        => $x_password,
            'expire_number'     => $expire_number,
            'expire_unit'       => $expire_unit,
            '_id'               => $section_id,
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/guest_access/' . $section_id,
            $payload);
    }

    /**
     * Update guest login settings, base
     *
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              guest logins, must be a (partial) object/array structured in the same manner as is
     *                              returned by list_settings() for the "guest_access" section.
     * @param string $section_id
     * @return bool true on success
     */
    public function set_guestlogin_settings_base($payload, string $section_id = ''): bool
    {
        if (!empty($section_id)) {
            $section_id = '/' . $section_id;
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/guest_access' . $section_id,
            $payload);
    }

    /**
     * Update IPS/IDS settings, base
     *
     * @param object|array $payload stdClass object or associative array containing the IPS/IDS settings to apply, must
     *                              be a (partial) object/array structured in the same manner as is returned by
     *                              list_settings() for the "ips" section.
     * @return bool true on success
     */
    public function set_ips_settings_base($payload): bool
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/ips', $payload);
    }

    /**
     * Update "Super Management" settings, base
     *
     * @param string $settings_id value of _id for the site settings section where key = "super_mgmt", settings
     *                                  can be obtained using the list_settings() function
     * @param object|array $payload stdClass object or associative array containing the "Super Management" settings
     *                                  to apply, must be a (partial) object/array structured in the same manner as is
     *                                  returned by list_settings() for the "super_mgmt" section.
     * @return bool true on success
     */
    public function set_super_mgmt_settings_base(string $settings_id, $payload): bool
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/super_mgmt/' . trim($settings_id),
            $payload);
    }

    /**
     * Update "Super SMTP" settings, base
     *
     * @param string $settings_id value of _id for the site settings section where key = "super_smtp", settings
     *                                  can be obtained using the list_settings() function
     * @param object|array $payload stdClass object or associative array containing the "Super SMTP" settings to
     *                                  apply, must be a (partial) object/array structured in the same manner as is
     *                                  returned by list_settings() for the "super_smtp" section.
     * @return bool true on success
     */
    public function set_super_smtp_settings_base(string $settings_id, $payload): bool
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/super_smtp/' . trim($settings_id),
            $payload);
    }

    /**
     * Update "Super Controller Identity" settings, base
     *
     * @param string $settings_id value of _id for the site settings section where key = "super_identity",
     *                                  settings can be obtained using the list_settings() function
     * @param object|array $payload stdClass object or associative array containing the "Super Controller Identity"
     *                                  settings to apply, must be a (partial) object/array structured in the same
     *                                  manner as is returned by list_settings() for the "super_identity" section.
     * @return bool true on success
     */
    public function set_super_identity_settings_base(string $settings_id, $payload): bool
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/super_identity/' . trim($settings_id),
            $payload);
    }

    /**
     * Rename access point
     *
     * @param string $ap_id _id of the access point to rename
     * @param string $ap_name new name to assign to the access point
     * @return bool true on success
     */
    public function rename_ap(string $ap_id, string $ap_name): bool
    {
        $payload = ['name' => $ap_name];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/device/' . trim($ap_id), $payload);
    }

    /**
     * Move a device to another site
     *
     * @param string $mac MAC address of the device to move
     * @param string $site_id _id (24 char string) of the site to move the device to
     * @return bool true on success
     */
    public function move_device(string $mac, string $site_id): bool
    {
        $payload = [
            'cmd'  => 'move-device',
            'site' => $site_id,
            'mac'  => strtolower($mac)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Delete a device from the current site
     *
     * @param string $mac MAC address of the device to delete
     * @return bool true on success
     */
    public function delete_device(string $mac): bool
    {
        $payload = [
            'cmd' => 'delete-device',
            'mac' => strtolower($mac)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Fetch dynamic DNS settings (using REST)
     *
     * @return array|bool containing dynamic DNS settings
     */
    public function list_dynamicdns()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/dynamicdns');
    }

    /**
     * Create dynamic DNS settings, base (using REST)
     *
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              site, must be a
     *                              (partial) object/array structured in the same manner as is returned by
     *                              list_dynamicdns() for the site.
     * @return bool true on success
     */
    public function create_dynamicdns($payload): bool
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/dynamicdns', $payload);
    }

    /**
     * Update site dynamic DNS, base (using REST)
     *
     * @param string $dynamicdns_id _id of the settings which can be found with the list_dynamicdns() function
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to
     *                                    the site, must be a
     *                                    (partial) object/array structured in the same manner as is returned by
     *                                    list_dynamicdns() for the site.
     * @return bool true on success
     */
    public function set_dynamicdns(string $dynamicdns_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/dynamicdns/' . trim($dynamicdns_id),
            $payload);
    }

    /**
     * Fetch network settings (using REST)
     *
     * @param string $network_id optional, _id value of the network to get settings for
     * @return array|bool containing (non-wireless) networks and their settings
     */
    public function list_networkconf(string $network_id = '')
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id));
    }

    /**
     * Create a network (using REST)
     *
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              network, must be a (partial) object structured in the same manner as is returned by
     *                              list_networkconf() for the specific network type. Do not include the _id property,
     *                              it is assigned by the controller and returned upon success.
     * @return array|bool containing a single object with details of the new network on success, else returns false
     */
    public function create_network($payload)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/networkconf', $payload);
    }

    /**
     * Update network settings, base (using REST)
     *
     * @param string $network_id the "_id" value for the network you wish to update
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to
     *                                 the network, must be a (partial) object/array structured in the same manner as
     *                                 is returned by list_networkconf() for the network.
     * @return bool true on success
     */
    public function set_networksettings_base(string $network_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id),
            $payload);
    }

    /**
     * Delete a network (using REST)
     *
     * @param string $network_id _id value of the network which can be found with the list_networkconf() function
     * @return bool true on success
     */
    public function delete_network(string $network_id): bool
    {
        $this->curl_method = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id));
    }

    /**
     * Fetch wlan settings (using REST)
     *
     * @param string $wlan_id optional, _id value of the wlan to fetch the settings for
     * @return array|bool containing wireless networks and their settings, or an array containing a single wireless network
     *               when using the <wlan_id> parameter
     */
    public function list_wlanconf(string $wlan_id = '')
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id));
    }

    /**
     * Create a WLAN
     *
     * @param string $name SSID
     * @param string $x_passphrase new pre-shared key, minimal length is 8 characters, maximum length is 63,
     *                                  assign a value of null when security = 'open'
     * @param string $usergroup_id user group id that can be found using the list_usergroups() function
     * @param string $wlangroup_id wlan group id that can be found using the list_wlan_groups() function
     * @param boolean $enabled optional, enable/disable wlan
     * @param boolean $hide_ssid optional, hide/unhide wlan SSID
     * @param boolean $is_guest optional, apply guest policies or not
     * @param string $security optional, security type (open, wep, wpapsk, wpaeap)
     * @param string $wpa_mode optional, wpa mode (wpa, wpa2, ..)
     * @param string $wpa_enc optional, encryption (auto, ccmp)
     * @param boolean $vlan_enabled optional, enable/disable VLAN for this wlan (is ignored as of 1.1.73)
     * @param string|null $vlan_id optional, "_id" value of the VLAN to assign to this WLAN, can be found using
     *                                  list_networkconf()
     * @param boolean $uapsd_enabled optional, enable/disable Unscheduled Automatic Power Save Delivery
     * @param boolean $schedule_enabled optional, enable/disable wlan schedule
     * @param array $schedule optional, schedule rules
     * @param array|null $ap_group_ids optional, array of ap group ids, required for UniFi controller versions 6.0.X
     *                                  and higher
     * @param array $payload optional, array of additional parameters (wlan_bands, wpa3_support, etc.)
     * @return bool true on success
     */
    public function create_wlan(
        string $name,
        string $x_passphrase,
        string $usergroup_id,
        string $wlangroup_id,
        bool   $enabled = true,
        bool   $hide_ssid = false,
        bool   $is_guest = false,
        string $security = 'open',
        string $wpa_mode = 'wpa2',
        string $wpa_enc = 'ccmp',
        bool   $vlan_enabled = null,
        string $vlan_id = null,
        bool   $uapsd_enabled = false,
        bool   $schedule_enabled = false,
        array  $schedule = [],
        array  $ap_group_ids = null,
        array  $payload = []
    ): bool
    {
        $payload = array_merge($payload, [
            'name'             => trim($name),
            'usergroup_id'     => trim($usergroup_id),
            'wlangroup_id'     => trim($wlangroup_id),
            'enabled'          => $enabled,
            'hide_ssid'        => $hide_ssid,
            'is_guest'         => $is_guest,
            'security'         => trim($security),
            'wpa_mode'         => trim($wpa_mode),
            'wpa_enc'          => trim($wpa_enc),
            'uapsd_enabled'    => $uapsd_enabled,
            'schedule_enabled' => $schedule_enabled,
            'schedule'         => $schedule,
        ]);

        if (!empty($vlan_id)) {
            $payload['networkconf_id'] = $vlan_id;
        }

        if (!empty($x_passphrase) && $security !== 'open') {
            $payload['x_passphrase'] = trim($x_passphrase);
        }

        if (!empty($ap_group_ids)) {
            $payload['ap_group_ids'] = $ap_group_ids;
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/add/wlanconf', $payload);
    }

    /**
     * Update wlan settings, base (using REST)
     *
     * @param string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              wlan, must be a (partial) object/array structured in the same manner as is returned
     *                              by list_wlanconf() for the wlan.
     * @return bool true on success
     */
    public function set_wlansettings_base(string $wlan_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id), $payload);
    }

    /**
     * Update basic wlan settings
     *
     * @param string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param string $x_passphrase new pre-shared key, minimal length is 8 characters, maximum length is 63,
     *                             is ignored if set to null
     * @param string $name optional, SSID
     * @return bool true on success
     */
    public function set_wlansettings(string $wlan_id, string $x_passphrase, string $name = ''): bool
    {
        $payload                 = [];
        $payload['x_passphrase'] = trim($x_passphrase);

        if (!empty($name)) {
            $payload['name'] = trim($name);
        }

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * Disable/Enable wlan
     *
     * @param string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param bool $disable true disables the wlan, false enables it
     * @return bool true on success
     */
    public function disable_wlan(string $wlan_id, bool $disable): bool
    {
        $action  = !$disable;
        $payload = ['enabled' => $action];

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * Delete a wlan (using REST)
     *
     * @param string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @return bool true on success
     */
    public function delete_wlan(string $wlan_id): bool
    {
        $this->curl_method = 'DELETE';
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id));
    }

    /**
     * Update MAC filter for a wlan
     *
     * @param string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf()
     *                                   function
     * @param string $mac_filter_policy string, "allow" or "deny"; default MAC policy to apply
     * @param bool $mac_filter_enabled true enables the policy, false disables it
     * @param array $macs must contain valid MAC strings to be placed in the MAC filter list,
     *                                   replacing existing values. Existing MAC filter list can be obtained
     *                                   through list_wlanconf().
     * @return bool true on success
     */
    public function set_wlan_mac_filter(string $wlan_id, string $mac_filter_policy, bool $mac_filter_enabled, array $macs): bool
    {
        if (!in_array($mac_filter_policy, ['allow', 'deny'])) {
            return false;
        }

        $macs = array_map('strtolower', $macs);

        $payload = [
            'mac_filter_enabled' => $mac_filter_enabled,
            'mac_filter_policy'  => $mac_filter_policy,
            'mac_filter_list'    => $macs,
        ];

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * Fetch events
     *
     * @param integer $historyhours optional, hours to go back, default value is 720 hours
     * @param integer $start optional, which event number to start with (useful for paging of results), default
     *                              value is 0
     * @param integer $limit optional, number of events to return, default value is 3000
     * @return array|bool containing known events
     */
    public function list_events(int $historyhours = 720, int $start = 0, int $limit = 3000)
    {
        $payload = [
            '_sort'  => '-time',
            'within' => $historyhours,
            'type'   => null,
            '_start' => $start,
            '_limit' => $limit,
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/event', $payload);
    }

    /**
     * Fetch alarms
     *
     * @param array $payload optional, array of flags to filter by
     *                       Example: ["archived" => false, "key" => "EVT_GW_WANTransition"]
     *                       return only unarchived for a specific key
     * @return array|bool containing known alarms
     */
    public function list_alarms(array $payload = [])
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/alarm', $payload);
    }

    /**
     * Count alarms
     *
     * @param bool|null $archived optional, if true all alarms are counted, if false only non-archived (active) alarms are
     *                       counted, by default all alarms are counted
     * @return array|bool containing the alarm count
     */
    public function count_alarms(bool $archived = null)
    {
        $path_suffix = $archived === false ? '?archived=false' : null;

        return $this->fetch_results('/api/s/' . $this->site . '/cnt/alarm' . $path_suffix);
    }

    /**
     * Archive alarms(s)
     *
     * @param string $alarm_id optional, _id of the alarm to archive which can be found with the list_alarms() function,
     *                         by default all alarms are archived
     * @return bool true on success
     */
    public function archive_alarm(string $alarm_id = ''): bool
    {
        $payload = ['cmd' => 'archive-all-alarms'];

        if (!empty($alarm_id)) {
            $payload = ['_id' => $alarm_id, 'cmd' => 'archive-alarm'];
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/evtmgr', $payload);
    }

    /**
     * Check controller update
     *
     * @note triggers an update of the controller's cached, latest known version.
     * @return array|bool returns an array with a single object containing details of the current known latest
     *                    controller version info on success, else returns false
     */
    public function check_controller_update()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/fwupdate/latest-version');
    }

    /**
     * Check firmware update
     *
     * @note triggers a Device Firmware Update in Classic Settings > System settings > Maintenance
     * @return bool true upon success
     */
    public function check_firmware_update(): bool
    {
        $payload = ['cmd' => 'check-firmware-update'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/productinfo', $payload);
    }

    /**
     * Upgrade a device to the latest firmware
     *
     * @note updates the device to the latest STABLE firmware known to the controller
     * @param string $mac MAC address of the device to upgrade
     * @return bool true upon success
     */
    public function upgrade_device(string $mac): bool
    {
        $payload = ['mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/upgrade', $payload);
    }

    /**
     * Upgrade all devices of a certain type to the latest firmware
     *
     * @note updates all devices of the selected type to the latest STABLE firmware known to the controller
     * @param string $type the type of devices to upgrade, must be one of "uap", "usw", "ugw". "uap" is the default.
     * @return bool true upon success
     */
    public function upgrade_all_devices(string $type = 'uap'): bool
    {
        $payload = ['type' => strtolower($type)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/upgrade-all', $payload);
    }

    /**
     * Upgrade a device to a specific firmware file
     *
     * @note - updates the device to the firmware file at the given URL
     *       - please take great care to select a valid firmware file for the device!
     * @param string $firmware_url URL for the firmware file to upgrade the device to
     * @param string|array $macs MAC address of the device to upgrade or an array of MAC addresses
     * @return bool true upon success
     */
    public function upgrade_device_external(string $firmware_url, $macs): bool
    {
        $payload = [
            'url'  => filter_var($firmware_url, FILTER_SANITIZE_URL),
            'macs' => array_map('strtolower', (array)$macs)
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/upgrade-external', $payload);
    }

    /**
     * Start rolling upgrade.
     *
     * @note updates all UniFi devices to the latest firmware known to the controller in a
     *       staggered/rolling fashion
     * @return bool true upon success
     */
    public function start_rolling_upgrade(): bool
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/set-rollupgrade');
    }

    /**
     * Cancel rolling upgrade.
     *
     * @return bool true upon success
     */
    public function cancel_rolling_upgrade(): bool
    {
        $payload = ['cmd' => 'unset-rollupgrade'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Fetch firmware versions.
     *
     * @param string $type optional, "available" or "cached", determines which firmware types to return,
     *                     default value is "available"
     * @return array|bool containing firmware versions
     */
    public function list_firmware(string $type = 'available')
    {
        if (!in_array($type, ['available', 'cached'])) {
            return false;
        }

        $payload = ['cmd' => 'list-' . $type];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/firmware', $payload);
    }

    /**
     * Power-cycle the PoE output of a switch port
     *
     * @note - only applies to switches and their PoE ports
     *       - the port must be actually providing power
     * @param string $mac main MAC address of the switch
     * @param int $port_idx port number/index of the port to be affected
     * @return bool true upon success
     */
    public function power_cycle_switch_port(string $mac, int $port_idx): bool
    {
        $payload = ['mac' => strtolower($mac), 'port_idx' => $port_idx, 'cmd' => 'power-cycle'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Trigger an RF scan by an AP
     *
     * @param string $mac MAC address of the AP
     * @return bool true upon success
     */
    public function spectrum_scan(string $mac): bool
    {
        $payload = ['cmd' => 'spectrum-scan', 'mac' => strtolower($mac)];
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Check the RF scanning state of an AP
     *
     * @param string $mac MAC address of the AP
     * @return array|bool containing relevant information (results if available) regarding the RF scanning state of the
     *                    AP
     */
    public function spectrum_scan_state(string $mac)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/spectrum-scan/' . strtolower(trim($mac)));
    }

    /**
     * Update device settings, base (using REST)
     *
     * @param string $device_id _id of the device which can be found with the list_devices() function
     * @param object|array $payload stdClass object or associative array containing the configuration to apply to the
     *                              device, must be a (partial) object/array structured in the same manner as is returned
     *                              by list_devices() for the device.
     * @return bool true on success
     */
    public function set_device_settings_base(string $device_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/device/' . trim($device_id), $payload);
    }

    /**
     * Fetch Radius profiles (using REST)
     *
     * @note this function/method is only supported on controller versions 5.5.19 and later
     * @return array|bool objects containing all Radius profiles for the current site
     */
    public function list_radius_profiles()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/radiusprofile');
    }

    /**
     * Fetch Radius user accounts (using REST)
     *
     * @note this function/method is only supported on controller versions 5.5.19 and later
     * @return array|bool objects containing all Radius accounts for the current site
     */
    public function list_radius_accounts()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/account');
    }

    /**
     * Create a Radius user account (using REST)
     *
     * @note this function/method is only supported on controller versions 5.5.19 and later
     * @param string $name name for the new account
     * @param string $x_password password for the new account
     * @param int|null $tunnel_type optional, must be one of the following values:
     *                                    1      Point-to-Point Tunneling Protocol (PPTP)
     *                                    2      Layer Two Forwarding (L2F)
     *                                    3      Layer Two Tunneling Protocol (L2TP)
     *                                    4      Ascend Tunnel Management Protocol (ATMP)
     *                                    5      Virtual Tunneling Protocol (VTP)
     *                                    6      IP Authentication Header in the Tunnel-mode (AH)
     *                                    7      IP-in-IP Encapsulation (IP-IP)
     *                                    8      Minimal IP-in-IP Encapsulation (MIN-IP-IP)
     *                                    9      IP Encapsulating Security Payload in the Tunnel-mode (ESP)
     *                                    10     Generic Route Encapsulation (GRE)
     *                                    11     Bay Dial Virtual Services (DVS)
     *                                    12     IP-in-IP Tunneling
     *                                    13     Virtual LANs (VLAN)
     * @param int|null $tunnel_medium_type optional, must be one of the following values:
     *                                    1      IPv4 (IP version 4)
     *                                    2      IPv6 (IP version 6)
     *                                    3      NSAP
     *                                    4      HDLC (8-bit multidrop)
     *                                    5      BBN 1822
     *                                    6      802 (includes all 802 media plus Ethernet "canonical format")
     *                                    7      E.163 (POTS)
     *                                    8      E.164 (SMDS, Frame Relay, ATM)
     *                                    9      F.69 (Telex)
     *                                    10     X.121 (X.25, Frame Relay)
     *                                    11     IPX
     *                                    12     Appletalk
     *                                    13     Decnet IV
     *                                    14     Banyan Vines
     *                                    15     E.164 with NSAP format subaddress
     * @param string|null $vlan optional, VLAN to assign to the account
     * @return bool|array                 containing a single object for the newly created account upon success, else returns false
     */
    public function create_radius_account(
        string $name,
        string $x_password,
        int    $tunnel_type = null,
        int    $tunnel_medium_type = null,
        string $vlan = null
    )
    {
        $tunnel_type_values        = [null, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
        $tunnel_medium_type_values = [null, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

        if (
            !in_array($tunnel_type, $tunnel_type_values) ||
            !in_array($tunnel_medium_type, $tunnel_medium_type_values) ||
            ($tunnel_type xor $tunnel_medium_type)
        ) {
            return false;
        }

        $payload = [
            'name'       => $name,
            'x_password' => $x_password,
        ];

        if (!is_null($tunnel_type)) {
            $payload['tunnel_type'] = $tunnel_type;
        }

        if (!is_null($tunnel_medium_type)) {
            $payload['tunnel_medium_type'] = $tunnel_medium_type;
        }

        if (!is_null($vlan)) {
            $payload['vlan'] = $vlan;
        }

        return $this->fetch_results('/api/s/' . $this->site . '/rest/account', $payload);
    }

    /**
     * Update a Radius account, base (using REST)
     *
     * @note this function/method is only supported on controller versions 5.5.19 and later
     * @param string $account_id _id of the account which can be found with the list_radius_accounts() function
     * @param object|array $payload stdClass object or associative array containing the new profile to apply to the
     *                                 account, must be a (partial) object/array structured in the same manner as is
     *                                 returned by list_radius_accounts() for the account.
     * @return bool true on success
     */
    public function set_radius_account_base(string $account_id, $payload): bool
    {
        $this->curl_method = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/account/' . trim($account_id), $payload);
    }

    /**
     * Delete a Radius account (using REST)
     *
     * @note this function/method is only supported on controller versions 5.5.19 and later
     * @param string $account_id _id of the account which can be found with the list_radius_accounts() function
     * @return bool true on success
     */
    public function delete_radius_account(string $account_id): bool
    {
        $this->curl_method = 'DELETE';
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/account/' . trim($account_id));
    }

    /**
     * Execute specific stats command
     *
     * @param string $command command to execute, known valid values:
     *                        'reset-dpi', resets all DPI counters for the current site,
     *                        to be extended in the future
     * @return bool true on success
     */
    public function cmd_stat(string $command): bool
    {
        if ($command != 'reset-dpi') {
            return false;
        }

        $payload = ['cmd' => trim($command)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stat', $payload);
    }

    /**
     * Toggle Element Adoption ON or OFF
     *
     * @param bool $enable true enables Element Adoption, false disables Element Adoption
     * @return bool true on success
     */
    public function set_element_adoption(bool $enable): bool
    {
        $payload = ['enabled' => $enable];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/element_adopt', $payload);
    }

    /**
     * List device states
     *
     * @note this function returns a partial implementation of the codes listed at this URL:
     * @see https://help.ui.com/hc/en-us/articles/205231710-UniFi-UAP-Status-Meaning-Definitions
     * @return array containing translations of UniFi device "state" values to humanized form
     */
    public function list_device_states(): array
    {
        return [
            0  => 'offline',
            1  => 'connected',
            2  => 'pending adoption',
            4  => 'updating',
            5  => 'provisioning',
            6  => 'unreachable',
            7  => 'adopting',
            9  => 'adoption error',
            10 => 'adoption failed',
            11 => 'isolated',
        ];
    }

    /**
     * Custom API request
     *
     * @note Only use this method when you fully understand the behavior of the UniFi controller API.
     *       No input validation performed, to be used with care!
     * @param string $path suffix of the URL (following the port number) to pass request to, *must* start with
     *                     a "/" character
     * @param string $method optional, HTTP request type, can be GET (default), POST, PUT, PATCH, or DELETE
     * @param object|array|null $payload optional, stdClass object or associative array containing the payload to pass
     * @param string $return optional, string; determines how to return results, when "boolean" the method must
     *                       return a boolean result or "array" when the method must return an array (default)
     * @return bool|array returns results as requested, returns false on incorrect parameters
     */
    public function custom_api_request(string $path, string $method = 'GET', $payload = null, string $return = 'array')
    {
        if (!in_array($method, self::CURL_METHODS_ALLOWED)) {
            return false;
        }

        if (strpos($path, '/') !== 0) {
            return false;
        }

        $this->curl_method = $method;

        if ($return === 'array') {
            return $this->fetch_results($path, $payload);
        }

        if ($return === 'boolean') {
            return $this->fetch_results_boolean($path, $payload);
        }

        return false;
    }

    /****************************************************************
     * "Aliases" for deprecated functions from here, used to support
     * backward compatibility:
     ****************************************************************/

    /**
     * Fetch access points and other devices under management of the controller (USW, USG, and/or UnIfi OS consoles)
     *
     * @note changed function/method name to fit its purpose
     * @param string|null $mac optional, the MAC address of a single device for which the call must be made
     * @return array|bool containing known device objects (or a single device when using the <mac> parameter)
     */
    public function list_aps(string $mac = null)
    {
        trigger_error(
            'Function list_aps() has been deprecated, use list_devices() instead.',
            E_USER_DEPRECATED
        );

        return $this->list_devices($mac);
    }

    /**
     * Start flashing LED of an access point for locating purposes
     *
     * @param string $mac device MAC address
     * @return bool true on success
     */
    public function set_locate_ap(string $mac): bool
    {
        trigger_error(
            'Function set_locate_ap() has been deprecated, use locate_ap() instead.',
            E_USER_DEPRECATED
        );

        return $this->locate_ap($mac, true);
    }

    /**
     * Stop flashing LED of an access point for locating purposes
     *
     * @param string $mac device MAC address
     * @return bool true on success
     */
    public function unset_locate_ap(string $mac): bool
    {
        trigger_error(
            'Function unset_locate_ap() has been deprecated, use locate_ap() instead.',
            E_USER_DEPRECATED
        );

        return $this->locate_ap($mac, false);
    }

    /**
     * Switch LEDs of all the access points ON
     *
     * @return bool true on success
     */
    public function site_ledson(): bool
    {
        trigger_error(
            'Function site_ledson() has been deprecated, use site_leds() instead.',
            E_USER_DEPRECATED
        );

        return $this->site_leds(true);
    }

    /**
     * Switch LEDs of all the access points OFF
     *
     * @return bool true on success
     */
    public function site_ledsoff(): bool
    {
        trigger_error(
            'Function site_ledsoff() has been deprecated, use site_leds() instead.',
            E_USER_DEPRECATED
        );

        return $this->site_leds(false);
    }

    /**
     * Reboot an access point
     *
     * @param string $mac device MAC address
     * @return bool true on success
     */
    public function restart_ap(string $mac): bool
    {
        trigger_error(
            'Function restart_ap() has been deprecated, use restart_device() instead.',
            E_USER_DEPRECATED
        );

        return $this->restart_device($mac);
    }

    /****************************************************************
     * setter/getter functions from here:
     ****************************************************************/

    /**
     * Modify the private property $site
     *
     * @note this method is useful to switch between sites
     * @param string $site must be the short site name of a site to which the
     *                     provided credentials have access
     * @return string the new (short) site name
     */
    public function set_site(string $site): string
    {
        $this->check_site($site);
        $this->site = trim($site);

        return $this->site;
    }

    /**
     * Get the private property $site
     *
     * @return string the current (short) site name
     */
    public function get_site(): string
    {
        return $this->site;
    }

    /**
     * Set debug mode
     *
     * @param bool $enable true enables debug mode, false disables debug mode
     * @return bool false when a non-boolean parameter was passed
     */
    public function set_debug(bool $enable): bool
    {
        $this->debug = $enable;

        return true;
    }

    /**
     * Get the private property $debug
     *
     * @return bool the current boolean value for $debug
     */
    public function get_debug(): bool
    {
        return $this->debug;
    }

    /**
     * Get last raw results
     *
     * @param boolean $return_json true returns the results in "pretty printed" JSON format,
     *                             false returns PHP stdClass Object format (default)
     * @return false|string|object|null the raw results as returned by the controller API
     */
    public function get_last_results_raw(bool $return_json = false)
    {
        if (!is_null($this->last_results_raw)) {
            if ($return_json) {
                return json_encode($this->last_results_raw, JSON_PRETTY_PRINT);
            }

            return $this->last_results_raw;
        }

        return false;
    }

    /**
     * Get the last error message
     *
     * @return string the error message of the last method called in PHP stdClass Object format, an empty string when
     *                none available
     */
    public function get_last_error_message(): string
    {
        return $this->last_error_message;
    }

    /**
     * Get Cookie from UniFi controller (singular and plural)
     *
     * @note When the results from this method are stored in $_SESSION[$this->unificookie_name], the Class initially
     *       does not log in to the controller when a subsequent request is made using a new instance.
     *       This speeds up the overall request considerably.
     *       Only when a subsequent request fails (e.g. cookies have expired) will a new login be
     *       executed and the value of $_SESSION[$this->unificookie_name] is updated.
     *       To force the Class instance to log out automatically upon destruct, simply call logout() or unset
     *       $_SESSION[$this->unificookie_name] at the end of your code
     * @return string the UniFi controller cookie
     */
    public function get_cookie(): string
    {
        return $this->cookies;
    }

    public function get_cookies(): string
    {
        return $this->cookies;
    }

    /**
     * Get the version of the Class
     *
     * @return string semver compatible version of this class
     *                https://semver.org/
     */
    public function get_class_version(): string
    {
        return self::CLASS_VERSION;
    }

    /**
     * Set value for the private property $cookies
     *
     * @param string $cookies_value new value for $cookies
     */
    public function set_cookies(string $cookies_value)
    {
        $this->cookies = $cookies_value;
    }

    /**
     * Get the current value of the private property $unificookie_name
     *
     * @return string current value of $unificookie_name
     */
    public function get_unificookie_name(): string
    {
        return $this->unificookie_name;
    }

    /**
     * Get current request method
     *
     * @return string request type
     */
    public function get_curl_method(): string
    {
        return $this->curl_method;
    }

    /**
     * Set request method
     *
     * @param string $curl_method a valid HTTP request method
     * @return bool whether the request was successful or not
     */
    public function set_curl_method(string $curl_method): bool
    {
        if (!in_array($curl_method, self::CURL_METHODS_ALLOWED)) {
            return false;
        }

        $this->curl_method = $curl_method;

        return true;
    }

    /**
     * Get value for cURL option CURLOPT_SSL_VERIFYPEER
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     *
     * @return bool value of private property $ssl_verify_peer (cURL option CURLOPT_SSL_VERIFYPEER)
     */
    public function get_curl_ssl_verify_peer(): bool
    {
        return $this->curl_ssl_verify_peer;
    }

    /**
     * Set value for cURL option CURLOPT_SSL_VERIFYPEER
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     *
     * @param bool $curl_ssl_verify_peer should be 0/false or 1/true
     * @return bool whether the request was successful or not
     */
    public function set_curl_ssl_verify_peer(bool $curl_ssl_verify_peer): bool
    {
        $this->curl_ssl_verify_peer = $curl_ssl_verify_peer;

        return true;
    }

    /**
     * Get value for cURL option CURLOPT_SSL_VERIFYHOST
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     *
     * @return int value of private property $ssl_verify_peer (cURL option CURLOPT_SSL_VERIFYHOST)
     */
    public function get_curl_ssl_verify_host(): int
    {
        return $this->curl_ssl_verify_host;
    }

    /**
     * Set value for cURL option CURLOPT_SSL_VERIFYHOST
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     *
     * @param int $curl_ssl_verify_host should be 0, 1 or 2
     * @return bool whether the request was successful or not
     */
    public function set_curl_ssl_verify_host(int $curl_ssl_verify_host): bool
    {
        if (!in_array($curl_ssl_verify_host, [0, 1, 2])) {
            return false;
        }

        $this->curl_ssl_verify_host = $curl_ssl_verify_host;

        return true;
    }

    /**
     * Is current controller UniFi OS-based
     *
     * @return bool whether the current controller is UniFi OS-based or not
     */
    public function get_is_unifi_os(): bool
    {
        return $this->is_unifi_os;
    }

    /**
     * Set value for private property $is_unifi_os
     *
     * @param bool $is_unifi_os the new value
     * @return bool whether the request was successful or not
     */
    public function set_is_unifi_os(bool $is_unifi_os): bool
    {
        $this->is_unifi_os = $is_unifi_os;

        return true;
    }

    /**
     * Set value for the private property $connect_timeout
     *
     * @param int $timeout new value for $connect_timeout in seconds
     * @return bool whether the request was successful or not
     */
    public function set_connection_timeout(int $timeout): bool
    {
        $this->curl_connect_timeout = $timeout;

        return true;
    }

    /**
     * Get the current value of the private property $connect_timeout
     *
     * @return int current value of $connect_timeout
     */
    public function get_connection_timeout(): int
    {
        return $this->curl_connect_timeout;
    }

    /**
     * Set value for the private property $request_timeout
     *
     * @param int $timeout new value for $request_timeout in seconds
     * @return bool whether the request was successful or not
     */
    public function set_curl_request_timeout(int $timeout): bool
    {
        $this->curl_request_timeout = $timeout;

        return true;
    }

    /**
     * Get the current value of the private property $request_timeout
     *
     * @return int current value of $request_timeout
     */
    public function get_curl_request_timeout(): int
    {
        return $this->curl_request_timeout;
    }

    /**
     * Set value for the private property $curl_http_version
     *
     * @note As of cURL version 7.62.0 the default value is CURL_HTTP_VERSION_2TLS which may cause issues,
     *       this method allows you to set the value to CURL_HTTP_VERSION_1_1 when needed.
     * @param int $http_version new value for $curl_http_version, CURL_HTTP_VERSION_1_1 int(2) or
     *                          CURL_HTTP_VERSION_2TLS int(4) are recommended
     * @return bool whether the request was successful or not
     * @see https://curl.se/libcurl/c/CURLOPT_HTTP_VERSION.html
     */
    public function set_curl_http_version(int $http_version): bool
    {
        $this->curl_http_version = $http_version;

        return true;
    }

    /**
     * Get current value of the private property $curl_http_version
     *
     * @return int the current value of $request_timeout, can be CURL_HTTP_VERSION_1_1 int(2) or
     *             CURL_HTTP_VERSION_2TLS int(4)
     * @see https://curl.se/libcurl/c/CURLOPT_HTTP_VERSION.html
     */
    public function get_curl_http_version(): int
    {
        return $this->curl_http_version;
    }

    /****************************************************************
     * private and protected functions from here:
     ****************************************************************/

    /**
     * Fetch results
     *
     * Execute the cURL request and return results
     *
     * @param string $path request path
     * @param object|array|null $payload optional, PHP associative array or stdClass Object, payload to pass with the
     *                                   request
     * @param boolean $boolean optional, whether the method should return a boolean result, else return
     *                         the "data" array
     * @param boolean $login_required optional, whether the method requires to be logged in or not
     * @return bool|array returns an array with the "data" array on success, else returns false
     */
    protected function fetch_results(
        string $path,
               $payload = null,
        bool   $boolean = false,
        bool   $login_required = true
    )
    {
        /** guard clause to check if logged in when needed */
        if ($login_required && !$this->is_logged_in) {
            return false;
        }

        $this->last_results_raw = $this->exec_curl($path, $payload);

        if (is_string($this->last_results_raw)) {
            $response = json_decode($this->last_results_raw);
            $this->catch_json_last_error();

            if (isset($response->meta->rc)) {
                if ($response->meta->rc === 'ok') {
                    $this->last_error_message = '';
                    if (isset($response->data) && is_array($response->data) && !$boolean) {
                        return $response->data;
                    }

                    return true;
                }

                if ($response->meta->rc === 'error') {
                    /**
                     * an error occurred:
                     * set $this->set last_error_message if the returned error message is available
                     */
                    if (isset($response->meta->msg)) {
                        $this->last_error_message = $response->meta->msg;
                        if ($this->debug) {
                            trigger_error('Debug: Last error message: ' . $this->last_error_message);
                        }
                    }
                }
            }

            /** to deal with a response coming from the new v2 API */
            if (strpos($path, '/v2/api/') === 0) {
                if (isset($response->errorCode)) {
                    if (isset($response->message)) {
                        $this->last_error_message = $response->message;
                        if ($this->debug) {
                            trigger_error('Debug: Last error message: ' . $this->last_error_message);
                        }
                    }

                    return false;
                }

                return $response;
            }
        }

        return false;
    }

    /**
     * Fetch results where output should be boolean (true/false)
     *
     * execute the cURL request and return a boolean value
     *
     * @param string $path request path
     * @param object|array|null $payload optional, PHP associative array or stdClass Object, payload to pass with the
     *                                   request
     * @param bool $login_required optional, whether the method requires to be logged in or not
     * @return bool [description]
     */
    protected function fetch_results_boolean(string $path, $payload = null, bool $login_required = true): bool
    {
        return $this->fetch_results($path, $payload, true, $login_required);
    }

    /**
     * Capture the latest JSON error when $this->debug is true
     *
     * @return bool true upon success, false upon failure
     */
    protected function catch_json_last_error(): bool
    {
        if ($this->debug) {
            $error = 'Unknown JSON error occurred';
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    /** JSON is valid, no error has occurred and return true early */
                    return true;
                case JSON_ERROR_DEPTH:
                    $error = 'The maximum stack depth has been exceeded';

                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error = 'Invalid or malformed JSON';

                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error = 'Control character error, possibly incorrectly encoded';

                    break;
                case JSON_ERROR_SYNTAX:
                    $error = 'Syntax error, malformed JSON';

                    break;
                case JSON_ERROR_UTF8:
                    /** PHP >= 5.3.3 */
                    $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';

                    break;
                case JSON_ERROR_RECURSION:
                    /** PHP >= 5.5.0 */
                    $error = 'One or more recursive references in the value to be encoded';

                    break;
                case JSON_ERROR_INF_OR_NAN:
                    /** PHP >= 5.5.0 */
                    $error = 'One or more NAN or INF values in the value to be encoded';

                    break;
                case JSON_ERROR_UNSUPPORTED_TYPE:
                    $error = 'A value of a type that cannot be encoded was given';

                    break;
            }

            /** check whether we have PHP >= 7.0.0 */
            if (defined('JSON_ERROR_INVALID_PROPERTY_NAME') && defined('JSON_ERROR_UTF16')) {
                switch (json_last_error()) {
                    case JSON_ERROR_INVALID_PROPERTY_NAME:
                        $error = 'A property name that cannot be encoded was given';

                        break;
                    case JSON_ERROR_UTF16:
                        $error = 'Malformed UTF-16 characters, possibly incorrectly encoded';

                        break;
                }
            }

            trigger_error('JSON decode error: ' . $error);

            return false;
        }

        return true;
    }

    /**
     * Validate the submitted base URL
     *
     * @param string $baseurl the base URL to validate
     * @return bool true if base URL is a valid URL, else returns false
     */
    protected function check_base_url(string $baseurl): bool
    {
        if (!filter_var($baseurl, FILTER_VALIDATE_URL) || substr($baseurl, -1) === '/') {
            trigger_error('The URL provided is incomplete, invalid or ends with a / character!');

            return false;
        }

        return true;
    }

    /**
     * Check the (short) site name
     *
     * @param string $site the (short) site name to check
     * @return bool true if (short) site name is valid, else returns false
     */
    protected function check_site(string $site): bool
    {
        if ($this->debug && preg_match('/\s/', $site)) {
            trigger_error('The provided (short) site name may not contain any spaces');

            return false;
        }

        return true;
    }

    /**
     * Update the unificookie if sessions are enabled
     *
     * @return bool returns true when unificookie was updated, else returns false
     */
    protected function update_unificookie(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$this->unificookie_name]) && !empty($_SESSION[$this->unificookie_name])) {
            $this->cookies = $_SESSION[$this->unificookie_name];

            /** if the cookie contains a JWT, this is a UniFi OS controller */
            if (strpos($this->cookies, 'TOKEN') !== false) {
                $this->is_unifi_os = true;
            }

            return true;
        }

        return false;
    }

    /**
     * Add a cURL header containing the CSRF token from the TOKEN in our Cookie string
     *
     * @return void
     */
    protected function create_x_csrf_token_header()
    {
        if (!empty($this->cookies) && strpos($this->cookies, 'TOKEN') !== false) {
            $cookie_bits = explode('=', $this->cookies);

            if (!array_key_exists(1, $cookie_bits)) {
                return;
            }

            $jwt_components = explode('.', $cookie_bits[1]);

            if (!array_key_exists(1, $jwt_components)) {
                return;
            }

            /** remove any existing x-csrf-token headers first */
            foreach ($this->curl_headers as $index => $header) {
                if (strpos(strtolower($header), strtolower('x-csrf-token:')) !== false) {
                    unset($this->curl_headers[$index]);
                }
            }

            $this->curl_headers[] = 'x-csrf-token: ' . json_decode(base64_decode($jwt_components[1]))->csrfToken;
        }
    }

    /**
     * Callback function for cURL to extract and store cookies as needed
     *
     * @param object|resource $ch the cURL instance (type hinting is unavailable for cURL resources)
     * @param string $header_line the response header line number
     * @return int length of the header line
     */
    protected function response_header_callback($ch, string $header_line): int
    {
        if (strpos($header_line, 'unifises') !== false || strpos($header_line, 'TOKEN') !== false) {
            $cookie = trim(str_replace(['set-cookie: ', 'Set-Cookie: '], '', $header_line));

            if (!empty($cookie)) {
                $cookie_crumbs = explode(';', $cookie);
                foreach ($cookie_crumbs as $cookie_crumb) {
                    if (strpos($cookie_crumb, 'unifises') !== false) {
                        $this->cookies      = $cookie_crumb;
                        $this->is_logged_in = true;
                        $this->is_unifi_os  = false;

                        break;
                    }

                    if (strpos($cookie_crumb, 'TOKEN') !== false) {
                        $this->cookies      = $cookie_crumb;
                        $this->is_logged_in = true;
                        $this->is_unifi_os  = true;

                        break;
                    }
                }
            }
        }

        return strlen($header_line);
    }

    /**
     * Execute the cURL request
     *
     * @param string $path path for the request
     * @param object|array|null $payload optional, payload to pass with the request
     * @return bool|string response returned by the controller API, false upon error
     */
    protected function exec_curl(string $path, $payload = null)
    {
        if (!in_array($this->curl_method, self::CURL_METHODS_ALLOWED)) {
            trigger_error('an invalid HTTP request type was used: ' . $this->curl_method);
            return false;
        }

        $url = $this->baseurl . $path;

        if ($this->is_unifi_os) {
            $url = $this->baseurl . '/proxy/network' . $path;
        }

        $ch = $this->get_curl_handle();

        $curl_options = [
            CURLOPT_URL => $url,
        ];

        /** when a payload is passed */
        $json_payload = '';
        if (!empty($payload)) {
            $json_payload                     = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $curl_options[CURLOPT_POSTFIELDS] = $json_payload;


            /**
             * should not use GET (the default request type) or DELETE when passing a payload,
             * switch to POST instead
             */
            if ($this->curl_method === 'GET' || $this->curl_method === 'DELETE') {
                $this->curl_method = 'POST';
            }
        }

        switch ($this->curl_method) {
            case 'POST':
                $curl_options[CURLOPT_POST] = true;
                break;
            case 'DELETE':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'PUT':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                break;
            case 'PATCH':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                break;
        }

        if ($this->is_unifi_os && $this->curl_method !== 'GET') {
            $this->create_x_csrf_token_header();
        }

        $curl_options[CURLOPT_HTTPHEADER] = $this->curl_headers;

        curl_setopt_array($ch, $curl_options);

        /** execute the cURL request */
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        /** get the HTTP response code */
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /**
         * an HTTP response code 401 (Unauthorized) indicates the Cookie/Token has expired in which case
         * re-login is required
         */
        if ($http_code === 401) {
            if ($this->debug) {
                error_log(__FUNCTION__ . ': needed to reconnect to UniFi controller');
            }

            if ($this->exec_retries === 0) {
                /** explicitly clear the expired Cookie/Token, update other properties and log out before logging in again */
                if (isset($_SESSION[$this->unificookie_name])) {
                    $_SESSION[$this->unificookie_name] = '';
                }

                $this->is_logged_in = false;
                $this->cookies      = '';
                $this->exec_retries++;

                curl_close($ch);

                /** then login again */
                $this->login();

                /** when re-login was successful, execute the same cURL request again */
                if ($this->is_logged_in) {
                    if ($this->debug) {
                        error_log(__FUNCTION__ . ': re-logged in, calling exec_curl again');
                    }

                    return $this->exec_curl($path, $payload);
                }

                if ($this->debug) {
                    error_log(__FUNCTION__ . ': re-login failed');
                }
            }

            return false;
        }

        if ($this->debug) {
            print PHP_EOL . '<pre>';
            print PHP_EOL . '---------cURL INFO-----------' . PHP_EOL;
            print_r(curl_getinfo($ch));
            print PHP_EOL . '-------URL & PAYLOAD---------' . PHP_EOL;
            print $url . PHP_EOL;

            if (empty($json_payload)) {
                print 'empty payload';
            }

            print $json_payload;
            print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
            print $response;
            print PHP_EOL . '-----------------------------' . PHP_EOL;
            print '</pre>' . PHP_EOL;
        }

        curl_close($ch);

        /** set the method back to the default value, just in case */
        $this->curl_method = self::DEFAULT_CURL_METHOD;

        return $response;
    }

    /**
     * Create and return a new cURL handle
     *
     * @return object|resource CurlHandle object with PHP 8.* and higher, or a resource for lower PHP versions
     */
    protected function get_curl_handle()
    {
        $ch           = curl_init();
        $curl_options = [
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_HTTP_VERSION   => $this->curl_http_version,
            CURLOPT_SSL_VERIFYPEER => $this->curl_ssl_verify_peer,
            CURLOPT_SSL_VERIFYHOST => $this->curl_ssl_verify_host,
            CURLOPT_CONNECTTIMEOUT => $this->curl_connect_timeout,
            CURLOPT_TIMEOUT        => $this->curl_request_timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => [$this, 'response_header_callback'],
        ];

        if ($this->debug) {
            $curl_options[CURLOPT_VERBOSE] = true;
        }

        if (!empty($this->cookies)) {
            $curl_options[CURLOPT_COOKIESESSION] = true;
            $curl_options[CURLOPT_COOKIE]        = $this->cookies;
        }

        curl_setopt_array($ch, $curl_options);

        return $ch;
    }
}
