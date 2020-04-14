<?php
/**
 * This file is part of the art-of-wifi/unifi-api-client package
 *
 * This UniFi API client Class is based on the work done by the following developers:
 *    domwo: http://community.ubnt.com/t5/UniFi-Wireless/little-php-class-for-unifi-api/m-p/603051
 *    fbagnol: https://github.com/fbagnol/class.unifi.php
 * and the API as published by Ubiquiti:
 *    https://www.ubnt.com/downloads/unifi/<UniFi controller version number>/unifi_sh_api
 *
 * Copyright (c) 2017, Art of WiFi <info@artofwifi.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.md
 */

namespace UniFi_API;

/**
 * the UniFi API client Class
 */
class Client
{
    /**
     * private and protected properties
     */
    protected $baseurl             = 'https://127.0.0.1:8443';
    protected $user                = '';
    protected $password            = '';
    protected $site                = 'default';
    protected $version             = '5.6.39';
    protected $debug               = false;
    protected $is_loggedin         = false;
    protected $is_unifi_os         = false;
    protected $exec_retries        = 0;
    private $cookies               = '';
    private $request_type          = 'GET';
    private $request_types_allowed = ['GET', 'POST', 'PUT', 'DELETE'];
    private $connect_timeout       = 10;
    private $last_results_raw      = null;
    private $last_error_message    = null;
    private $curl_ssl_verify_peer  = false;
    private $curl_ssl_verify_host  = false;

    /**
     * Construct an instance of the UniFi API client Class
     * ---------------------------------------------------
     * return a new class instance
     * required parameter <user>       = string; user name to use when connecting to the UniFi controller
     * required parameter <password>   = string; password to use when connecting to the UniFi controller
     * optional parameter <baseurl>    = string; base URL of the UniFi controller which *must* include "https://" prefix,
     *                                   a port suffix (e.g. :8443) is required for non-UniFi OS controllers,
     *                                   do not add trailing slashes
     * optional parameter <site>       = string; short site name to access, defaults to "default"
     * optional parameter <version>    = string; the version number of the controller, defaults to "5.4.16"
     * optional parameter <ssl_verify> = boolean; whether to validate the controller's SSL certificate or not, a value of true is
     *                                   recommended for production environments to prevent potential MitM attacks, default value (false)
     *                                   disables validation of the controller certificate
     */
    public function __construct($user, $password, $baseurl = '', $site = '', $version = '', $ssl_verify = false)
    {
        if (!extension_loaded('curl')) {
            trigger_error('The PHP curl extension is not loaded. Please correct this before proceeding!');
        }

        $this->user     = trim($user);
        $this->password = trim($password);

        if (!empty($baseurl)) {
            $this->baseurl = trim($baseurl);
        }

        if (!empty($site)) {
            $this->site = trim($site);
        }

        if (!empty($version)) {
            $this->version = trim($version);
        }

        if ((boolean)$ssl_verify === true) {
            $this->curl_ssl_verify_peer = true;
            $this->curl_ssl_verify_host = 2;
        }

        $this->check_base_url();
        $this->check_site($this->site);
    }

    /**
     * This method is be called as soon as there are no other references to the Class instance
     * https://www.php.net/manual/en/language.oop5.decon.php
     *
     * NOTES:
     * to force the Class instance to log out automatically upon destruct, simply call logout() or unset
     * $_SESSION['unificookie'] at the end of your code
     */
    public function __destruct()
    {
        /**
         * if $_SESSION['unificookie'] is set, do not logout here
         */
        if (isset($_SESSION['unificookie'])) {
            return;
        }

        /**
         * logout, if needed
         */
        if ($this->is_loggedin) {
            $this->logout();
        }
    }

    /**
     * Login to the UniFi controller
     * -----------------------------
     * returns true upon success
     */
    public function login()
    {
        /**
         * if already logged in we skip the login process
         */
        if ($this->is_loggedin === true) {
            return true;
        }

        if ($this->update_unificookie()) {
            $this->is_loggedin = true;

            return true;
        }

        /**
         * first we check whether we have a "regular" controller or one based on UniFi OS
         */
        $ch = $this->get_curl_resource();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . '/');

        /**
         * execute the cURL request and get the HTTP response code
         */
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        if ($http_code === 200) {
            $this->is_unifi_os = true;
            curl_setopt($ch, CURLOPT_REFERER, $this->baseurl . '/login');
            curl_setopt($ch, CURLOPT_URL, $this->baseurl . '/api/auth/login');
        } else {
            curl_setopt($ch, CURLOPT_REFERER, $this->baseurl . '/login');
            curl_setopt($ch, CURLOPT_URL, $this->baseurl . '/api/login');
        }

        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $this->user, 'password' => $this->password]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['content-type: application/json; charset=utf-8']);

        /**
         * execute the cURL request and get the HTTP response code
         */
        $content   = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        if ($this->debug) {
            print PHP_EOL . '<pre>';
            print PHP_EOL . '-----------LOGIN-------------' . PHP_EOL;
            print_r(curl_getinfo($ch));
            print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
            print $content;
            print PHP_EOL . '-----------------------------' . PHP_EOL;
            print '</pre>' . PHP_EOL;
        }

        /**
         * based on the HTTP response code we either trigger an error or
         * extract the cookie from the headers
         */
        if ($http_code === 400 || $http_code === 401) {
            trigger_error("We received the following HTTP response status: $http_code. Probably a controller login failure");

            return $http_code;
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers     = substr($content, 0, $header_size);
        $body        = trim(substr($content, $header_size));

        curl_close($ch);

        /**
         * we are good to extract the cookies
         */
        if ($http_code >= 200 && $http_code < 400 && !empty($body)) {
            preg_match_all('|Set-Cookie: (.*);|Ui', $headers, $results);
            if (isset($results[1])) {
                $this->cookies = implode(';', $results[1]);

                /**
                 * accept cookies from regular UniFI controllers or from UniFi OS
                 */
                if (strpos($this->cookies, 'unifises') !== false || strpos($this->cookies, 'TOKEN') !== false) {
                    /**
                     * update the cookie value in $_SESSION['unificookie'], if it exists
                     */
                    if (isset($_SESSION['unificookie'])) {
                        $_SESSION['unificookie'] = $this->cookies;
                    }

                    return $this->is_loggedin = true;
                }
            }
        }

        return false;
    }

    /**
     * Logout from the UniFi controller
     * --------------------------------
     * returns true upon success
     */
    public function logout()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        /**
         * prepare cURL
         */
        $ch = $this->get_curl_resource();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        /**
         * constuct HTTP request headers as required
         */
        $headers = ['Content-Length: 0'];
        if ($this->is_unifi_os) {
            $logout_path = '/api/auth/logout';
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

            $csrf_token = $this->extract_csrf_token_from_cookie();
            if ($csrf_token) {
                $headers[] = 'x-csrf-token: ' . $csrf_token;
            }
        } else {
            $logout_path = '/logout';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . $logout_path);

        /**
         * execute the cURL request to logout
         */
        $logout = curl_exec($ch);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $this->is_loggedin = false;
        $this->cookies     = '';

        return true;
    }

    /****************************************************************
     * Functions to access UniFi controller API routes from here:
     ****************************************************************/

    /**
     * Authorize a client device
     * -------------------------
     * return true on success
     * required parameter <mac>     = client MAC address
     * required parameter <minutes> = minutes (from now) until authorization expires
     * optional parameter <up>      = upload speed limit in kbps
     * optional parameter <down>    = download speed limit in kbps
     * optional parameter <MBytes>  = data transfer limit in MB
     * optional parameter <ap_mac>  = AP MAC address to which client is connected, should result in faster authorization
     */
    public function authorize_guest($mac, $minutes, $up = null, $down = null, $MBytes = null, $ap_mac = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = ['cmd' => 'authorize-guest', 'mac' => strtolower($mac), 'minutes' => intval($minutes)];

        /**
         * if we have received values for up/down/MBytes/ap_mac we append them to the payload array to be submitted
         */
        if (!empty($up)) {
            $payload['up'] = intval($up);
        }

        if (!empty($down)) {
            $payload['down'] = intval($down);
        }

        if (!empty($MBytes)) {
            $payload['bytes'] = intval($MBytes);
        }

        if (isset($ap_mac)) {
            $payload['ap_mac'] = strtolower($ap_mac);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stamgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Unauthorize a client device
     * ---------------------------
     * return true on success
     * required parameter <mac> = client MAC address
     */
    public function unauthorize_guest($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'unauthorize-guest', 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stamgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Reconnect a client device
     * -------------------------
     * return true on success
     * required parameter <mac> = client MAC address
     */
    public function reconnect_sta($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'kick-sta', 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stamgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Block a client device
     * ---------------------
     * return true on success
     * required parameter <mac> = client MAC address
     */
    public function block_sta($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'block-sta', 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stamgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Unblock a client device
     * -----------------------
     * return true on success
     * required parameter <mac> = client MAC address
     */
    public function unblock_sta($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'unblock-sta', 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stamgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Forget one or more client devices
     * ---------------------------------
     * return true on success
     * required parameter <macs> = array of client MAC addresses
     *
     * NOTE:
     * only supported with controller versions 5.9.X and higher, can be
     * slow (up to 5 minutes) on larger controllers
     */
    public function forget_sta($macs)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $macs     = array_map('strtolower', $macs);
        $payload  = ['cmd' => 'forget-sta', 'macs' => $macs];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stamgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Create a new user/client-device
     * -------------------------------
     * return an array with a single object containing details of the new user/client-device on success, else return false
     * required parameter <mac>           = client MAC address
     * required parameter <user_group_id> = _id value for the user group the new user/client-device should belong to which
     *                                      can be obtained from the output of list_usergroups()
     * optional parameter <name>          = name to be given to the new user/client-device
     * optional parameter <note>          = note to be applied to the new user/client-device
     * optional parameter <is_guest>      = boolean; defines whether the new user/client-device is a guest or not
     * optional parameter <is_wired>      = boolean; defines whether the new user/client-device is wired or not
     */
    public function create_user($mac, $user_group_id, $name = null, $note = null, $is_guest = null, $is_wired = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $new_user = ['mac' => strtolower($mac), 'usergroup_id' => $user_group_id];
        if (!is_null($name)) {
            $new_user['name'] = $name;
        }

        if (!is_null($note)) {
            $new_user['note']  = $note;
            $new_user['noted'] = true;
        }

        if (!is_null($is_guest) && is_bool($is_guest)) {
            $new_user['is_guest'] = $is_guest;
        }

        if (!is_null($is_wired) && is_bool($is_wired)) {
            $new_user['is_wired'] = $is_wired;
        }

        $payload  = ['objects' => [['data' => $new_user]]];
        $response = $this->exec_curl('/api/s/' . $this->site . '/group/user', $payload);

        return $this->process_response($response);
    }

    /**
     * Add/modify/remove a client-device note
     * --------------------------------------
     * return true on success
     * required parameter <user_id> = id of the client-device to be modified
     * optional parameter <note>    = note to be applied to the client-device
     *
     * NOTES:
     * - when note is empty or not set, the existing note for the client-device will be removed and "noted" attribute set to false
     */
    public function set_sta_note($user_id, $note = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $noted    = (is_null($note)) || (empty($note)) ? false : true;
        $payload  = ['note' => $note, 'noted' => $noted];
        $response = $this->exec_curl('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Add/modify/remove a client device name
     * --------------------------------------
     * return true on success
     * required parameter <user_id> = id of the client device to be modified
     * optional parameter <name>    = name to be applied to the client device
     *
     * NOTES:
     * - when name is empty or not set, the existing name for the client device will be removed
     */
    public function set_sta_name($user_id, $name = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['name' => $name];
        $response = $this->exec_curl('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * 5 minutes site stats method
     * ---------------------------
     * returns an array of 5-minute stats objects for the current site
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     */
    public function stat_5minutes_site($start = null, $end = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end        = is_null($end) ? (time() * 1000) : intval($end);
        $start      = is_null($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attributes = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time'
        ];
        $payload  = ['attrs' => $attributes, 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/5minutes.site', $payload);

        return $this->process_response($response);
    }

    /**
     * Hourly site stats method
     * ------------------------
     * returns an array of hourly stats objects for the current site
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - "bytes" are no longer returned with controller version 4.9.1 and later
     */
    public function stat_hourly_site($start = null, $end = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end        = is_null($end) ? (time() * 1000) : intval($end);
        $start      = is_null($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attributes = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time'
        ];
        $payload  = ['attrs' => $attributes, 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/hourly.site', $payload);

        return $this->process_response($response);
    }

    /**
     * Daily site stats method
     * ------------------------
     * returns an array of daily stats objects for the current site
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     *
     * NOTES:
     * - defaults to the past 52*7*24 hours
     * - "bytes" are no longer returned with controller version 4.9.1 and later
     */
    public function stat_daily_site($start = null, $end = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end        = is_null($end) ? ((time() - (time() % 3600)) * 1000) : intval($end);
        $start      = is_null($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : intval($start);
        $attributes = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time'
        ];
        $payload  = ['attrs' => $attributes, 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/daily.site', $payload);

        return $this->process_response($response);
    }

    /**
     * 5 minutes stats method for a single access point or all access points
     * ---------------------------------------------------------------------
     * returns an array of 5-minute stats objects
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <mac>   = AP MAC address to return stats for
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     */
    public function stat_5minutes_aps($start = null, $end = null, $mac = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end        = is_null($end) ? (time() * 1000) : intval($end);
        $start      = is_null($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attributes = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attributes, 'start' => $start, 'end' => $end];
        if (!is_null($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/5minutes.ap', $payload);

        return $this->process_response($response);
    }

    /**
     * Hourly stats method for a single access point or all access points
     * ------------------------------------------------------------------
     * returns an array of hourly stats objects
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <mac>   = AP MAC address to return stats for
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - UniFi controller does not keep these stats longer than 5 hours with versions < 4.6.6
     */
    public function stat_hourly_aps($start = null, $end = null, $mac = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end        = is_null($end) ? (time() * 1000) : intval($end);
        $start      = is_null($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attributes = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attributes, 'start' => $start, 'end' => $end];
        if (!is_null($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/hourly.ap', $payload);

        return $this->process_response($response);
    }

    /**
     * Daily stats method for a single access point or all access points
     * -----------------------------------------------------------------
     * returns an array of daily stats objects
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <mac>   = AP MAC address to return stats for
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - UniFi controller does not keep these stats longer than 5 hours with versions < 4.6.6
     */
    public function stat_daily_aps($start = null, $end = null, $mac = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end        = is_null($end) ? (time() * 1000) : intval($end);
        $start      = is_null($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attributes = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attributes, 'start' => $start, 'end' => $end];
        if (!is_null($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/daily.ap', $payload);

        return $this->process_response($response);
    }

    /**
     * 5 minutes stats method for a single user/client device
     * ------------------------------------------------------
     * returns an array of 5-minute stats objects
     * required parameter <mac>     = MAC address of user/client device to return stats for
     * optional parameter <start>   = Unix timestamp in milliseconds
     * optional parameter <end>     = Unix timestamp in milliseconds
     * optional parameter <attribs> = array containing attributes (strings) to be returned, valid values are:
     *                                rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
     *                                default is ['rx_bytes', 'tx_bytes']
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - only supported with UniFi controller versions 5.8.X and higher
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     * - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance section
     */
    public function stat_5minutes_user($mac, $start = null, $end = null, $attribs = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload  = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/5minutes.user', $payload);

        return $this->process_response($response);
    }

    /**
     * Hourly stats method for a a single user/client device
     * -----------------------------------------------------
     * returns an array of hourly stats objects
     * required parameter <mac>     = MAC address of user/client device to return stats for
     * optional parameter <start>   = Unix timestamp in milliseconds
     * optional parameter <end>     = Unix timestamp in milliseconds
     * optional parameter <attribs> = array containing attributes (strings) to be returned, valid values are:
     *                                rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
     *                                default is ['rx_bytes', 'tx_bytes']
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - only supported with UniFi controller versions 5.8.X and higher
     * - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance section
     */
    public function stat_hourly_user($mac, $start = null, $end = null, $attribs = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload  = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/hourly.user', $payload);

        return $this->process_response($response);
    }

    /**
     * Daily stats method for a single user/client device
     * --------------------------------------------------
     * returns an array of daily stats objects
     * required parameter <mac>     = MAC address of user/client device to return stats for
     * optional parameter <start>   = Unix timestamp in milliseconds
     * optional parameter <end>     = Unix timestamp in milliseconds
     * optional parameter <attribs> = array containing attributes (strings) to be returned, valid values are:
     *                                rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
     *                                default is ['rx_bytes', 'tx_bytes']
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - only supported with UniFi controller versions 5.8.X and higher
     * - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance section
     */
    public function stat_daily_user($mac, $start = null, $end = null, $attribs = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload  = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/daily.user', $payload);

        return $this->process_response($response);
    }

    /**
     * 5 minutes gateway stats method
     * -------------------------------
     * returns an array of 5-minute stats objects for the gateway belonging to the current site
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <attribs> = array containing attributes (strings) to be returned, valid values are:
     *                                mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                                lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                                default is ['time', 'mem', 'cpu', 'loadavg_5']
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     * - requires a USG
     */
    public function stat_5minutes_gateway($start = null, $end = null, $attribs = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload  = ['attrs' => $attribs, 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/5minutes.gw', $payload);

        return $this->process_response($response);
    }

    /**
     * Hourly gateway stats method
     * ----------------------------
     * returns an array of hourly stats objects for the gateway belonging to the current site
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <attribs> = array containing attributes (strings) to be returned, valid values are:
     *                                mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                                lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                                default is ['time', 'mem', 'cpu', 'loadavg_5']
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - requires a USG
     */
    public function stat_hourly_gateway($start = null, $end = null, $attribs = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload  = ['attrs' => $attribs, 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/hourly.gw', $payload);

        return $this->process_response($response);
    }

    /**
     * Daily gateway stats method
     * ---------------------------
     * returns an array of daily stats objects for the gateway belonging to the current site
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <attribs> = array containing attributes (strings) to be returned, valid values are:
     *                                mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                                lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                                default is ['time', 'mem', 'cpu', 'loadavg_5']
     *
     * NOTES:
     * - defaults to the past 52*7*24 hours
     * - requires a USG
     */
    public function stat_daily_gateway($start = null, $end = null, $attribs = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? ((time() - (time() % 3600)) * 1000) : intval($end);
        $start    = is_null($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload  = ['attrs' => $attribs, 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/daily.gw', $payload);

        return $this->process_response($response);
    }

    /**
     * Method to fetch speed test results
     * ----------------------------------
     * returns an array of speed test result objects
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     *
     * NOTES:
     * - defaults to the past 24 hours
     * - requires a USG
     */
    public function stat_speedtest_results($start = null, $end = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (24 * 3600 * 1000) : intval($start);
        $payload  = ['attrs' => ['xput_download', 'xput_upload', 'latency', 'time'], 'start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/report/archive.speedtest', $payload);

        return $this->process_response($response);
    }

    /**
     * Method to fetch IPS/IDS event
     * ----------------------------------
     * returns an array of IPS/IDS event objects
     * optional parameter <start> = Unix timestamp in milliseconds
     * optional parameter <end>   = Unix timestamp in milliseconds
     * optional parameter <limit> = Maximum number of events to return, defaults to 10000
     *
     * NOTES:
     * - defaults to the past 24 hours
     * - requires a USG
     * - supported in UniFi controller versions 5.9.X and higher
     */
    public function stat_ips_events($start = null, $end = null, $limit = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? (time() * 1000) : intval($end);
        $start    = is_null($start) ? $end - (24 * 3600 * 1000) : intval($start);
        $limit    = is_null($limit) ? 10000 : intval($limit);
        $payload  = ['start' => $start, 'end' => $end, '_limit' => $limit];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/ips/event', $payload);

        return $this->process_response($response);
    }

    /**
     * Show all login sessions
     * -----------------------
     * returns an array of login session objects for all devices or a single device
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     * optional parameter <mac>   = client MAC address to return sessions for (can only be used when start and end are also provided)
     * optional parameter <type>  = client type to return sessions for, can be 'all', 'guest' or 'user'; default value is 'all'
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     */
    public function stat_sessions($start = null, $end = null, $mac = null, $type = 'all')
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!in_array($type, ['all', 'guest', 'user'])) {
            return false;
        }

        $end     = is_null($end) ? time() : intval($end);
        $start   = is_null($start) ? $end - (7 * 24 * 3600) : intval($start);
        $payload = ['type' => $type, 'start' => $start, 'end' => $end];
        if (!is_null($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/session', $payload);

        return $this->process_response($response);
    }

    /**
     * Show latest 'n' login sessions for a single client device
     * ---------------------------------------------------------
     * returns an array of latest login session objects for given client device
     * required parameter <mac>   = client MAC address
     * optional parameter <limit> = maximum number of sessions to get (default value is 5)
     */
    public function stat_sta_sessions_latest($mac, $limit = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $limit    = is_null($limit) ? 5 : intval($limit);
        $payload  = ['mac' => strtolower($mac), '_limit' => $limit, '_sort'=> '-assoc_time'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/session', $payload);

        return $this->process_response($response);
    }

    /**
     * Show all authorizations
     * -----------------------
     * returns an array of authorization objects
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     */
    public function stat_auths($start = null, $end = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $end      = is_null($end) ? time() : intval($end);
        $start    = is_null($start) ? $end - (7 * 24 * 3600) : intval($start);
        $payload  = ['start' => $start, 'end' => $end];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/authorization', $payload);

        return $this->process_response($response);
    }

    /**
     * List all client devices ever connected to the site
     * --------------------------------------------------
     * returns an array of client device objects
     * optional parameter <historyhours> = hours to go back (default is 8760 hours or 1 year)
     *
     * NOTES:
     * - <historyhours> is only used to select clients that were online within that period,
     *   the returned stats per client are all-time totals, irrespective of the value of <historyhours>
     */
    public function stat_allusers($historyhours = 8760)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['type' => 'all', 'conn' => 'all', 'within' => intval($historyhours)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/alluser', $payload);

        return $this->process_response($response);
    }

    /**
     * List guest devices
     * ------------------
     * returns an array of guest device objects with valid access
     * optional parameter <within> = time frame in hours to go back to list guests with valid access (default = 24*365 hours)
     */
    public function list_guests($within = 8760)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['within' => intval($within)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/guest', $payload);

        return $this->process_response($response);
    }

    /**
     * List online client device(s)
     * ----------------------------
     * returns an array of online client device objects, or in case of a single device request, returns a single client device object
     * optional parameter <client_mac> = the MAC address of a single online client device for which the call must be made
     */
    public function list_clients($client_mac = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/sta/' . strtolower(trim($client_mac)));

        return $this->process_response($response);
    }

    /**
     * Get details for a single client device
     * --------------------------------------
     * returns an object with the client device information
     * required parameter <client_mac> = client device MAC address
     */
    public function stat_client($client_mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/user/' . strtolower(trim($client_mac)));

        return $this->process_response($response);
    }

    /**
     * Assign client device to another group
     * -------------------------------------
     * return true on success
     * required parameter <user_id>  = id of the user device to be modified
     * required parameter <group_id> = id of the user group to assign user to
     */
    public function set_usergroup($user_id, $group_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['usergroup_id' => $group_id];
        $response = $this->exec_curl('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update client fixedip (using REST)
     * ------------------------------
     * returns an array containing a single object with attributes of the updated client on success
     * required parameter <client_id>   = _id value for the client
     * required parameter <use_fixedip> = boolean defining whether if use_fixedip is true or false
     * optional parameter <network_id>  = _id value for the network where the ip belongs to
     * optional parameter <fixed_ip>    = value of client's fixed_ip field
     *
     */
    public function edit_client_fixedip($client_id, $use_fixedip, $network_id = null, $fixed_ip = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!is_bool($use_fixedip)) {
            return false;
        }

        $this->request_type = 'PUT';
        $payload            = ['_id' => $client_id, 'use_fixedip' => $use_fixedip];
        if ($use_fixedip) {
            if ($network_id) {
                $payload['network_id'] = $network_id;
            }

            if ($fixed_ip) {
                $payload['fixed_ip'] = $fixed_ip;
            }
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/user/' . trim($client_id), $payload);

        return $this->process_response($response);
    }

    /**
     * List user groups
     * ----------------
     * returns an array of user group objects
     */
    public function list_usergroups()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/usergroup');

        return $this->process_response($response);
    }

    /**
     * Create user group (using REST)
     * ---------------------------
     * returns an array containing a single object with attributes of the new usergroup ("_id", "name", "qos_rate_max_down", "qos_rate_max_up", "site_id") on success
     * required parameter <group_name> = name of the user group
     * optional parameter <group_dn>   = limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * optional parameter <group_up>   = limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     */
    public function create_usergroup($group_name, $group_dn = -1, $group_up = -1)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['name' => $group_name, 'qos_rate_max_down' => intval($group_dn), 'qos_rate_max_up' => intval($group_up)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/usergroup', $payload);

        return $this->process_response($response);
    }

    /**
     * Modify user group (using REST)
     * ------------------------------
     * returns an array containing a single object with attributes of the updated usergroup on success
     * required parameter <group_id>   = _id value of the user group
     * required parameter <site_id>    = _id value of the site
     * required parameter <group_name> = name of the user group
     * optional parameter <group_dn>   = limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * optional parameter <group_up>   = limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     */
    public function edit_usergroup($group_id, $site_id, $group_name, $group_dn = -1, $group_up = -1)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $payload = [
            '_id'               => $group_id,
            'name'              => $group_name,
            'qos_rate_max_down' => intval($group_dn),
            'qos_rate_max_up'   => intval($group_up),
            'site_id'           => $site_id
        ];

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/usergroup/' . trim($group_id), $payload);

        return $this->process_response($response);
    }

    /**
     * Delete user group (using REST)
     * ------------------------------
     * returns true on success
     * required parameter <group_id> = _id value of the user group
     */
    public function delete_usergroup($group_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/usergroup/' . trim($group_id));

        return $this->process_response_boolean($response);
    }

    /**
     * List firewall groups (using REST)
     * ----------------------------------
     * returns an array containing the current firewall groups or the selected firewall group on success
     * optional parameter <group_id> = _id value of the single firewall group to list
     */
    public function list_firewallgroups($group_id = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id));

        return $this->process_response($response);
    }

    /**
     * Create firewall group (using REST)
     * ----------------------------------
     * returns an array containing a single object with attributes of the new firewall group on success
     * required parameter <group_name>    = name to assign to the firewall group
     * required parameter <group_type>    = firewall group type; valid values are address-group, ipv6-address-group, port-group
     * optional parameter <group_members> = array containing the members of the new group (IPv4 addresses, IPv6 addresses or port numbers)
     *                                      (default is an empty array)
     */
    public function create_firewallgroup($group_name, $group_type, $group_members = [])
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!in_array($group_type, ['address-group', 'ipv6-address-group', 'port-group'])) {
            return false;
        }

        $payload            = ['name' => $group_name, 'group_type' => $group_type, 'group_members' => $group_members];
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/firewallgroup', $payload);

        return $this->process_response($response);
    }

    /**
     * Modify firewall group (using REST)
     * ----------------------------------
     * returns an array containing a single object with attributes of the updated firewall group on success
     * required parameter <group_id>      = _id value of the firewall group to modify
     * required parameter <site_id>       = site_id value of the firewall group to modify
     * required parameter <group_name>    = name of the firewall group
     * required parameter <group_type>    = firewall group type; valid values are address-group, ipv6-address-group, port-group,
     *                                      group_type cannot be changed for an existing firewall group!
     * optional parameter <group_members> = array containing the members of the group (IPv4 addresses, IPv6 addresses or port numbers)
     *                                      which will overwrite the existing group_members (default is an empty array)
     *
     *
     */
    public function edit_firewallgroup($group_id, $site_id, $group_name, $group_type, $group_members = [])
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!in_array($group_type, ['address-group', 'ipv6-address-group', 'port-group'])) {
            return false;
        }

        $this->request_type = 'PUT';
        $payload = [
            '_id'           => $group_id,
            'name'          => $group_name,
            'group_type'    => $group_type,
            'group_members' => $group_members,
            'site_id'       => $site_id
        ];

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id), $payload);

        return $this->process_response($response);
    }

    /**
     * Delete firewall group (using REST)
     * ----------------------------------
     * returns true on success
     * required parameter <group_id> = _id value of the firewall group to delete
     */
    public function delete_firewallgroup($group_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id));

        return $this->process_response_boolean($response);
    }

    /**
     * List firewall rules (using REST)
     * ----------------------------------
     * returns an array containing the current firewall rules on success
     */
    public function list_firewallrules()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/firewallrule');

        return $this->process_response($response);
    }

    /**
     * List health metrics
     * -------------------
     * returns an array of health metric objects
     */
    public function list_health()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/health');

        return $this->process_response($response);
    }

    /**
     * List dashboard metrics
     * ----------------------
     * returns an array of dashboard metric objects (available since controller version 4.9.1.alpha)
     * optional parameter <five_minutes> = boolean; if true, return stats based on 5 minute intervals,
     *                                     returns hourly stats by default (supported on controller versions 5.5.* and higher)
     */
    public function list_dashboard($five_minutes = false)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $url_suffix = $five_minutes ? '?scale=5minutes' : null;
        $response   = $this->exec_curl('/api/s/' . $this->site . '/stat/dashboard' . $url_suffix);

        return $this->process_response($response);
    }

    /**
     * List client devices
     * -------------------
     * returns an array of known client device objects
     */
    public function list_users()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/user');

        return $this->process_response($response);
    }

    /**
     * List access points and other devices under management of the controller (USW and/or USG devices)
     * ------------------------------------------------------------------------------------------------
     * returns an array of known device objects (or a single device when using the <device_mac> parameter)
     * optional parameter <device_mac> = the MAC address of a single device for which the call must be made
     */
    public function list_devices($device_mac = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/device/' . strtolower(trim($device_mac)));

        return $this->process_response($response);
    }

    /**
     * List (device) tags (using REST)
     * -------------------------------
     * returns an array of known device tag objects
     *
     * NOTES: this endpoint was introduced with controller versions 5.5.X
     */
    public function list_tags()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/tag');

        return $this->process_response($response);
    }

    /**
     * List rogue/neighboring access points
     * ------------------------------------
     * returns an array of rogue/neighboring access point objects
     * optional parameter <within> = hours to go back to list discovered "rogue" access points (default = 24 hours)
     */
    public function list_rogueaps($within = 24)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['within' => intval($within)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/rogueap', $payload);

        return $this->process_response($response);
    }

    /**
     * List known rogue access points
     * ------------------------------
     * returns an array of known rogue access point objects
     */
    public function list_known_rogueaps()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/rogueknown');

        return $this->process_response($response);
    }

    /**
     * Generate backup
     * ---------------------------
     * returns a URL from where the backup file can be downloaded once generated
     *
     * NOTES:
     * this is an experimental function, please do not use unless you know exactly
     * what you're doing
     */
    public function generate_backup()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'backup'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/backup', $payload);

        return $this->process_response($response);
    }

    /**
     * List auto backups
     * ---------------------------
     * return an array containing objects with backup details on success
     */
    public function list_backups()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'list-backups'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/backup', $payload);

        return $this->process_response($response);
    }

    /**
     * List sites
     * ----------
     * returns a list sites hosted on this controller with some details
     */
    public function list_sites()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/self/sites');

        return $this->process_response($response);
    }

    /**
     * List sites stats
     * ----------------
     * returns statistics for all sites hosted on this controller
     *
     * NOTES: this endpoint was introduced with controller version 5.2.9
     */
    public function stat_sites()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/stat/sites');

        return $this->process_response($response);
    }

    /**
     * Create a site
     * -------------
     * returns an array containing a single object with attributes of the new site ("_id", "desc", "name") on success
     * required parameter <description> = the long name for the new site
     *
     * NOTES: immediately after being added, the new site will be available in the output of the "list_sites" function
     */
    public function create_site($description)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['desc' => $description, 'cmd' => 'add-site'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response($response);
    }

    /**
     * Delete a site
     * -------------
     * return true on success
     * required parameter <site_id> = 24 char string; _id value of the site to delete
     */
    public function delete_site($site_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['site' => $site_id, 'cmd' => 'delete-site'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Change the current site's name
     * ------------------------------
     * return true on success
     * required parameter <site_name> = string; the new long name for the current site
     *
     * NOTES: immediately after being changed, the site will be available in the output of the list_sites() function
     */
    public function set_site_name($site_name)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'update-site', 'desc' => $site_name];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site country
     * ----------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "country" key.
     *                                Valid country codes can be obtained using the list_country_codes() function/method.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_country($country_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/country/' . trim($country_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site locale
     * ---------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "locale" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_locale($locale_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/locale/' . trim($locale_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site snmp
     * -------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "snmp" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_snmp($snmp_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/snmp/' . trim($snmp_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site mgmt
     * -------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "mgmt" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_mgmt($mgmt_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/mgmt/' . trim($mgmt_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site guest access
     * ---------------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "guest_access" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_guest_access($guest_access_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/guest_access/' . trim($guest_access_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site ntp
     * ------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "ntp" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_ntp($ntp_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/ntp/' . trim($ntp_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Set site connectivity
     * ---------------------
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "connectivity" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_connectivity($connectivity_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/setting/connectivity/' . trim($connectivity_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * List admins
     * -----------
     * returns an array containing administrator objects for selected site
     */
    public function list_admins()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'get-admins'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response($response);
    }

    /**
     * List all admins
     * ---------------
     * returns an array containing administrator objects for all sites
     */
    public function list_all_admins()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/stat/admin');

        return $this->process_response($response);
    }

    /**
     * Invite a new admin for access to the current site
     * -------------------------------------------------
     * returns true on success
     * required parameter <name>           = string, name to assign to the new admin user
     * required parameter <email>          = email address to assign to the new admin user
     * optional parameter <enable_sso>     = boolean, whether or not SSO will be allowed for the new admin
     *                                       default value is true which enables the SSO capability
     * optional parameter <readonly>       = boolean, whether or not the new admin will have readonly
     *                                       permissions, default value is false which gives the new admin
     *                                       Administrator permissions
     * optional parameter <device_adopt>   = boolean, whether or not the new admin will have permissions to
     *                                       adopt devices, default value is false. With versions < 5.9.X this only applies
     *                                       when readonly is true.
     * optional parameter <device_restart> = boolean, whether or not the new admin will have permissions to
     *                                       restart devices, default value is false. With versions < 5.9.X this only applies
     *                                       when readonly is true.
     *
     * NOTES:
     * - after issuing a valid request, an invite will be sent to the email address provided
     * - issuing this command against an existing admin will trigger a "re-invite"
     */
    public function invite_admin(
        $name,
        $email,
        $enable_sso = true,
        $readonly = false,
        $device_adopt = false,
        $device_restart = false
    ) {
        if (!$this->is_loggedin) {
            return false;
        }

        $email_valid = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email_valid) {
            trigger_error('The email address provided is invalid!');

            return false;
        }

        $permissions = [];
        $payload     = ['name' => trim($name), 'email' => trim($email), 'for_sso' => $enable_sso, 'cmd' => 'invite-admin', 'role' => 'admin'];

        if ($readonly) {
            $payload['role'] = 'readonly';
        }

        if ($device_adopt) {
            $permissions[] = 'API_DEVICE_ADOPT';
        }

        if ($device_restart) {
            $permissions[] = 'API_DEVICE_RESTART';
        }

        $payload['permissions'] = $permissions;
        $response               = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Assign an existing admin to the current site
     * --------------------------------------------
     * returns true on success
     * required parameter <admin_id>       = 24 char string; _id value of the admin user to assign, can be obtained using the
     *                                       list_all_admins() method/function
     * optional parameter <readonly>       = boolean, whether or not the new admin will have readonly
     *                                       permissions, default value is false which gives the new admin
     *                                       Administrator permissions
     * optional parameter <device_adopt>   = boolean, whether or not the new admin will have permissions to
     *                                       adopt devices, default value is false. With versions < 5.9.X this only applies
     *                                       when readonly is true.
     * optional parameter <device_restart> = boolean, whether or not the new admin will have permissions to
     *                                       restart devices, default value is false. With versions < 5.9.X this only applies
     *                                       when readonly is true.
     */
    public function assign_existing_admin($admin_id, $readonly = false, $device_adopt = false, $device_restart = false)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $permissions = [];
        $payload     = ['cmd' => 'grant-admin', 'admin' => trim($admin_id), 'role' => 'admin'];

        if ($readonly) {
            $payload['role'] = 'readonly';
        }

        if ($device_adopt) {
            $permissions[] = 'API_DEVICE_ADOPT';
        }

        if ($device_restart) {
            $permissions[] = 'API_DEVICE_RESTART';
        }

        $payload['permissions'] = $permissions;
        $response               = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Revoke an admin from the current site
     * -------------------------------------
     * returns true on success
     * required parameter <admin_id> = _id value of the admin to revoke, can be obtained using the
     *                                 list_all_admins() method/function
     *
     * NOTES:
     * only non-superadmin accounts can be revoked
     */
    public function revoke_admin($admin_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'revoke-admin', 'admin' => $admin_id];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * List wlan_groups
     * ----------------
     * returns an array containing known wlan_groups
     */
    public function list_wlan_groups()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/wlangroup');

        return $this->process_response($response);
    }

    /**
     * Show sysinfo
     * ------------
     * returns an array of known sysinfo data
     */
    public function stat_sysinfo()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/sysinfo');

        return $this->process_response($response);
    }

    /**
     * Get controller status
     * ---------------------
     * returns true upon success (controller is online)
     *
     * NOTES: in order to get useful results (e.g. controller version) you can call get_last_results_raw()
     * immediately after this method
     */
    public function stat_status()
    {
        $response = $this->exec_curl('/status');

        return $this->process_response_boolean($response);
    }

    /**
     * List self
     * ---------
     * returns an array of information about the logged in user
     */
    public function list_self()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/self');

        return $this->process_response($response);
    }

    /**
     * List vouchers
     * -------------
     * returns an array of hotspot voucher objects
     * optional parameter <create_time> = Unix timestamp in seconds
     */
    public function stat_voucher($create_time = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = (trim($create_time) != null) ? ['create_time' => intval($create_time)] : [];
        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/voucher', $payload);

        return $this->process_response($response);
    }

    /**
     * List payments
     * -------------
     * returns an array of hotspot payments
     * optional parameter <within> = number of hours to go back to fetch payments
     */
    public function stat_payment($within = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $url_suffix = (($within != null) ? '?within=' . intval($within) : '');
        $response   = $this->exec_curl('/api/s/' . $this->site . '/stat/payment' . $url_suffix);

        return $this->process_response($response);
    }

    /**
     * Create hotspot operator (using REST)
     * ------------------------------------
     * return true upon success
     * required parameter <name>       = name for the hotspot operator
     * required parameter <x_password> = clear text password for the hotspot operator
     * optional parameter <note>       = note to attach to the hotspot operator
     */
    public function create_hotspotop($name, $x_password, $note = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = ['name' => $name, 'x_password' => $x_password];
        if (isset($note)) {
            $payload['note'] = trim($note);
        }
        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/hotspotop', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * List hotspot operators (using REST)
     * -----------------------------------
     * returns an array of hotspot operators
     */
    public function list_hotspotop()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/hotspotop');

        return $this->process_response($response);
    }

    /**
     * Create voucher(s)
     * -----------------
     * returns an array containing a single object which contains the create_time(stamp) of the voucher(s) created
     * required parameter <minutes> = minutes the voucher is valid after activation (expiration time)
     * optional parameter <count>   = number of vouchers to create, default value is 1
     * optional parameter <quota>   = single-use or multi-use vouchers, value '0' is for multi-use, '1' is for single-use,
     *                                'n' is for multi-use n times
     * optional parameter <note>    = note text to add to voucher when printing
     * optional parameter <up>      = upload speed limit in kbps
     * optional parameter <down>    = download speed limit in kbps
     * optional parameter <MBytes>  = data transfer limit in MB
     *
     * NOTES: please use the stat_voucher() method/function to retrieve the newly created voucher(s) by create_time
     */
    public function create_voucher(
        $minutes,
        $count  = 1,
        $quota  = 0,
        $note   = null,
        $up     = null,
        $down   = null,
        $MBytes = null
    ) {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = [
            'cmd'    => 'create-voucher',
            'expire' => intval($minutes),
            'n'      => intval($count),
            'quota'  => intval($quota)
        ];

        if (isset($note)) {
            $payload['note'] = trim($note);
        }

        if (!empty($up)) {
            $payload['up'] = intval($up);
        }

        if (!empty($down)) {
            $payload['down'] = intval($down);
        }

        if (!empty($MBytes)) {
            $payload['bytes'] = intval($MBytes);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/hotspot', $payload);

        return $this->process_response($response);
    }

    /**
     * Revoke voucher
     * --------------
     * return true on success
     * required parameter <voucher_id> = 24 char string; _id value of the voucher to revoke
     */
    public function revoke_voucher($voucher_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['_id' => $voucher_id, 'cmd' => 'delete-voucher'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/hotspot', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Extend guest validity
     * ---------------------
     * return true on success
     * required parameter <guest_id> = 24 char string; _id value of the guest to extend validity
     */
    public function extend_guest_validity($guest_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['_id' => $guest_id, 'cmd' => 'extend'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/hotspot', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * List port forwarding stats
     * --------------------------
     * returns an array of port forwarding stats
     */
    public function list_portforward_stats()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/portforward');

        return $this->process_response($response);
    }

    /**
     * List DPI stats
     * --------------
     * returns an array of DPI stats
     */
    public function list_dpi_stats()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/dpi');

        return $this->process_response($response);
    }

    /**
     * List filtered DPI stats
     * -----------------------
     * returns an array of fileterd DPI stats
     * optional parameter <type>       = whether to returns stats by app or by category, valid values:
     *                                   'by_cat' or 'by_app'
     * optional parameter <cat_filter> = an array containing numeric category ids to filter by,
     *                                   only to be combined with a "by_app" value for $type
     */
    public function list_dpi_stats_filtered($type = 'by_cat', $cat_filter = null)
    {
        if (!$this->is_loggedin || !in_array($type, ['by_cat', 'by_app'])) {
            return false;
        }

        $payload = ['type' => $type];

        if ($cat_filter !== null && $type == 'by_app' && is_array($cat_filter)) {
            $payload['cats'] = $cat_filter;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/sitedpi', $payload);

        return $this->process_response($response);
    }

    /**
     * List current channels
     * ---------------------
     * returns an array of currently allowed channels
     */
    public function list_current_channels()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/current-channel');

        return $this->process_response($response);
    }

    /**
     * List country codes
     * ------------------
     * returns an array of available country codes
     *
     * NOTES:
     * these codes following the ISO standard:
     * https://en.wikipedia.org/wiki/ISO_3166-1_numeric
     */
    public function list_country_codes()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/ccode');

        return $this->process_response($response);
    }

    /**
     * List port forwarding settings
     * -----------------------------
     * returns an array of port forwarding settings
     */
    public function list_portforwarding()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/portforward');

        return $this->process_response($response);
    }

    /**
     * List dynamic DNS settings
     * -------------------------
     * returns an array of dynamic DNS settings
     */
    public function list_dynamicdns()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/dynamicdns');

        return $this->process_response($response);
    }

    /**
     * List port configurations
     * ------------------------
     * returns an array of port configurations
     */
    public function list_portconf()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/portconf');

        return $this->process_response($response);
    }

    /**
     * List VoIP extensions
     * --------------------
     * returns an array of VoIP extensions
     */
    public function list_extension()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/extension');

        return $this->process_response($response);
    }

    /**
     * List site settings
     * ------------------
     * returns an array of site configuration settings
     */
    public function list_settings()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/get/setting');

        return $this->process_response($response);
    }

    /**
     * Adopt a device to the selected site
     * -----------------------------------
     * return true on success
     * required parameter <mac> = device MAC address
     */
    public function adopt_device($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['mac' => strtolower($mac), 'cmd' => 'adopt'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Reboot a device
     * ----------------------
     * return true on success
     * required parameter <mac>  = device MAC address
     * optional parameter <type> = string; two options: 'soft' or 'hard', defaults to soft
     *                             soft can be used for all devices, requests a plain restart of that device
     *                             hard is special for PoE switches and besides the restart also requests a
     *                             power cycle on all PoE capable ports. Keep in mind that a 'hard' reboot
     *                             does *NOT* trigger a factory-reset, as it somehow could suggest.
     */
    public function restart_device($mac, $type = 'soft')
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = ['cmd' => 'restart', 'mac' => strtolower($mac)];
        if (!is_null($type) && in_array($type, ['soft', 'hard'])) {
            $payload['type'] = strtolower($type);
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Force provision of a device
     * ---------------------------
     * return true on success
     * required parameter <mac> = device MAC address
     */
    public function force_provision($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['mac' => strtolower($mac), 'cmd' => 'force-provision'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);


        return $this->process_response_boolean($response);
    }

    /**
     * Reboot a UniFi CloudKey
     * -----------------------
     * return true on success
     *
     * This API call does nothing on UniFi controllers *not* running on a UniFi CloudKey device
     */
    public function reboot_cloudkey()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'reboot'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/system', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Disable/enable an access point (using REST)
     * -------------------------------------------
     * return true on success
     * required parameter <ap_id>   = 24 char string; value of _id for the access point which can be obtained from the device list
     * required parameter <disable> = boolean; true will disable the device, false will enable the device
     *
     * NOTES:
     * - a disabled device will be excluded from the dashboard status and device count and its LED and WLAN will be turned off
     * - appears to only be supported for access points
     * - available since controller versions 5.2.X
     */
    public function disable_ap($ap_id, $disable)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!is_bool($disable)) {
            return false;
        }

        $this->request_type = 'PUT';
        $payload            = ['disabled' => $disable];
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/device/' . trim($ap_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Override LED mode for a device (using REST)
     * -------------------------------------------
     * return true on success
     * required parameter <device_id>     = 24 char string; value of _id for the device which can be obtained from the device list
     * required parameter <override_mode> = string, off/on/default; "off" will disable the LED of the device,
     *                                      "on" will enable the LED of the device,
     *                                      "default" will apply the site-wide setting for device LEDs
     *
     * NOTES:
     * - available since controller versions 5.2.X
     */
    public function led_override($device_id, $override_mode)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        if (!in_array($override_mode, ['off', 'on', 'default'])) {
            return false;
        }

        $payload  = ['led_override' => $override_mode];
        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/device/' . trim($device_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Toggle flashing LED of an access point for locating purposes
     * ------------------------------------------------------------
     * return true on success
     * required parameter <mac>    = device MAC address
     * required parameter <enable> = boolean; true will enable flashing LED, false will disable
     *
     * NOTES:
     * replaces the old set_locate_ap() and unset_locate_ap() methods/functions
     */
    public function locate_ap($mac, $enable)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!is_bool($enable)) {
            return false;
        }

        $cmd      = (($enable) ? 'set-locate' : 'unset-locate');
        $payload  = ['cmd' => $cmd, 'mac' => strtolower($mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Toggle LEDs of all the access points ON or OFF
     * ----------------------------------------------
     * return true on success
     * required parameter <enable> = boolean; true will switch LEDs of all the access points ON, false will switch them OFF
     */
    public function site_leds($enable)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!is_bool($enable)) {
            return false;
        }

        $payload  = ['led_enabled' => $enable];
        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/mgmt', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update access point radio settings
     * ----------------------------------
     * return true on success
     * required parameter <ap_id>               = the "_id" value for the access point you wish to update
     * required parameter <radio>(default=ng)
     * required parameter <channel>
     * required parameter <ht>(default=20)
     * required parameter <tx_power_mode>
     * required parameter <tx_power>(default=0)
     *
     * NOTES:
     * - only supported on pre-5.X.X controller versions
     */
    public function set_ap_radiosettings($ap_id, $radio, $channel, $ht, $tx_power_mode, $tx_power)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = [
            'radio_table' => [
                'radio'         => $radio,
                'channel'       => $channel,
                'ht'            => $ht,
                'tx_power_mode' => $tx_power_mode,
                'tx_power'      => $tx_power
            ]
        ];

        $response = $this->exec_curl('/api/s/' . $this->site . '/upd/device/' . trim($ap_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Assign access point to another WLAN group
     * -----------------------------------------
     * return true on success
     * required parameter <type_id>   = string; WLAN type, can be either 'ng' (for WLANs 2G (11n/b/g)) or 'na' (WLANs 5G (11n/a/ac))
     * required parameter <device_id> = string; _id value of the access point to be modified
     * required parameter <group_id>  = string; _id value of the WLAN group to assign device to
     */
    public function set_ap_wlangroup($type_id, $device_id, $group_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!in_array($type_id, ['ng', 'na'])) {
            return false;
        }

        $payload = [
            'wlan_overrides'           => [],
            'wlangroup_id_' . $type_id => $group_id
        ];

        $response = $this->exec_curl('/api/s/' . $this->site . '/upd/device/' . trim($device_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update guest login settings
     * ---------------------------
     * return true on success
     * required parameter <portal_enabled>    = boolean; enable/disable the captive portal
     * required parameter <portal_customized> = boolean; enable/disable captive portal customizations
     * required parameter <redirect_enabled>  = boolean; enable/disable captive portal redirect
     * required parameter <redirect_url>      = string; url to redirect to, must include the http/https prefix, no trailing slashes
     * required parameter <x_password>        = string; the captive portal (simple) password
     * required parameter <expire_number>     = numeric; number of units for the authorization expiry
     * required parameter <expire_unit>       = numeric; number of minutes within a unit (a value 60 is required for hours)
     * required parameter <section_id>        = 24 char string; value of _id for the site settings section where key = "guest_access", settings can be obtained
     *                                          using the list_settings() function
     *
     * NOTES:
     * - both portal parameters are set to the same value!
     */
    public function set_guestlogin_settings(
        $portal_enabled,
        $portal_customized,
        $redirect_enabled,
        $redirect_url,
        $x_password,
        $expire_number,
        $expire_unit,
        $section_id
    )
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = [
            'portal_enabled'    => $portal_enabled,
            'portal_customized' => $portal_customized,
            'redirect_enabled'  => $redirect_enabled,
            'redirect_url'      => $redirect_url,
            'x_password'        => $x_password,
            'expire_number'     => $expire_number,
            'expire_unit'       => $expire_unit,
            '_id'               => $section_id
        ];

        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/guest_access', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update guest login settings, base
     * ------------------------------------------
     * return true on success
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the guest login, must be a (partial)
     *                                object/array structured in the same manner as is returned by list_settings() for the "guest_access" section.
     */
    public function set_guestlogin_settings_base($payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/guest_access', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update IPS/IDS settings, base
     * ------------------------------------------
     * return true on success
     * required parameter <payload> = stdClass object or associative array containing the IPS/IDS settings to apply, must be a (partial)
     *                                object/array structured in the same manner as is returned by list_settings() for the "ips" section.
     */
    public function set_ips_settings_base($payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/ips', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update "Super Management" settings, base
     * ------------------------------------------
     * return true on success
     * required parameter <settings_id> = 24 char string; value of _id for the site settings section where key = "super_mgmt", settings can be obtained
     *                                    using the list_settings() function
     * required parameter <payload>     = stdClass object or associative array containing the "Super Management" settings to apply, must be a (partial)
     *                                    object/array structured in the same manner as is returned by list_settings() for the "super_mgmt" section.
     */
    public function set_super_mgmt_settings_base($settings_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/super_mgmt/' . trim($settings_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update "Super SMTP" settings, base
     * ------------------------------------------
     * return true on success
     * required parameter <settings_id> = 24 char string; value of _id for the site settings section where key = "super_smtp", settings can be obtained
     *                                    using the list_settings() function
     * required parameter <payload>     = stdClass object or associative array containing the "Super SMTP" settings to apply, must be a (partial)
     *                                    object/array structured in the same manner as is returned by list_settings() for the "super_smtp" section.
     */
    public function set_super_smtp_settings_base($settings_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/super_smtp/' . trim($settings_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update "Super Controller Identity" settings, base
     * ------------------------------------------
     * return true on success
     * required parameter <settings_id> = 24 char string; value of _id for the site settings section where key = "super_identity", settings can be obtained
     *                                    using the list_settings() function
     * required parameter <payload>     = stdClass object or associative array containing the "Super Controller Identity" settings to apply, must be a (partial)
     *                                    object/array structured in the same manner as is returned by list_settings() for the "super_identity" section.
     */
    public function set_super_identity_settings_base($settings_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/set/setting/super_identity/' . trim($settings_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Rename access point
     * -------------------
     * return true on success
     * required parameter <ap_id>
     * required parameter <apname>
     */
    public function rename_ap($ap_id, $apname)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['name' => $apname];
        $response = $this->exec_curl('/api/s/' . $this->site . '/upd/device/' . trim($ap_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Move a device to another site
     * -----------------------------
     * return true on success
     * required parameter <mac>     = string; MAC address of the device to move
     * required parameter <site_id> = 24 char string; _id of the site to move the device to
     */
    public function move_device($mac, $site_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['site' => $site_id, 'mac' => strtolower($mac), 'cmd' => 'move-device'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Delete a device from the current site
     * -------------------------------------
     * return true on success
     * required parameter <mac> = string; MAC address of the device to delete
     */
    public function delete_device($mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['mac' => strtolower($mac), 'cmd' => 'delete-device'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/sitemgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * List network settings (using REST)
     * ----------------------------------
     * returns an array of (non-wireless) networks and their settings
     * optional parameter <network_id> = string; _id value of the network to get settings for
     */
    public function list_networkconf($network_id = '')
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id));

        return $this->process_response($response);
    }

    /**
     * Create a network (using REST)
     * -----------------------------
     * return an array with a single object containing details of the new network on success, else return false
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_networkconf() for the specific network type.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     */
    public function create_network($payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/networkconf', $payload);

        return $this->process_response($response);
    }

    /**
     * Update network settings, base (using REST)
     * ------------------------------------------
     * return true on success
     * required parameter <network_id> = the "_id" value for the network you wish to update
     * required parameter <payload>    = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                   object/array structured in the same manner as is returned by list_networkconf() for the network.
     */
    public function set_networksettings_base($network_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Delete a network (using REST)
     * -----------------------------
     * return true on success
     * required parameter <network_id> = 24 char string; _id value of the network which can be found with the list_networkconf() function
     */
    public function delete_network($network_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id));

        return $this->process_response_boolean($response);
    }

    /**
     * List wlan settings (using REST)
     * -------------------------------
     * returns an array of wireless networks and their settings, or an array containing a single wireless network when using
     * the <wlan_id> parameter
     * optional parameter <wlan_id> = 24 char string; _id value of the wlan to fetch the settings for
     */
    public function list_wlanconf($wlan_id = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id));

        return $this->process_response($response);
    }

    /**
     * Create a wlan
     * -------------
     * return true on success
     * required parameter <name>             = string; SSID
     * required parameter <x_passphrase>     = string; new pre-shared key, minimal length is 8 characters, maximum length is 63,
     *                                         assign a value of null when security = 'open'
     * required parameter <usergroup_id>     = string; user group id that can be found using the list_usergroups() function
     * required parameter <wlangroup_id>     = string; wlan group id that can be found using the list_wlan_groups() function
     * optional parameter <enabled>          = boolean; enable/disable wlan
     * optional parameter <hide_ssid>        = boolean; hide/unhide wlan SSID
     * optional parameter <is_guest>         = boolean; apply guest policies or not
     * optional parameter <security>         = string; security type (open, wep, wpapsk, wpaeap)
     * optional parameter <wpa_mode>         = string; wpa mode (wpa, wpa2, ..)
     * optional parameter <wpa_enc>          = string; encryption (auto, ccmp)
     * optional parameter <vlan_enabled>     = boolean; enable/disable vlan for this wlan
     * optional parameter <vlan>             = string; vlan id
     * optional parameter <uapsd_enabled>    = boolean; enable/disable Unscheduled Automatic Power Save Delivery
     * optional parameter <schedule_enabled> = boolean; enable/disable wlan schedule
     * optional parameter <schedule>         = string; schedule rules
     * -----------------
     * TODO: Check parameter values
     */
    public function create_wlan(
        $name,
        $x_passphrase,
        $usergroup_id,
        $wlangroup_id,
        $enabled          = true,
        $hide_ssid        = false,
        $is_guest         = false,
        $security         = 'open',
        $wpa_mode         = 'wpa2',
        $wpa_enc          = 'ccmp',
        $vlan_enabled     = false,
        $vlan             = null,
        $uapsd_enabled    = false,
        $schedule_enabled = false,
        $schedule         = []
    )
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = [
            'name'             => $name,
            'usergroup_id'     => $usergroup_id,
            'wlangroup_id'     => $wlangroup_id,
            'enabled'          => $enabled,
            'hide_ssid'        => $hide_ssid,
            'is_guest'         => $is_guest,
            'security'         => $security,
            'wpa_mode'         => $wpa_mode,
            'wpa_enc'          => $wpa_enc,
            'vlan_enabled'     => $vlan_enabled,
            'uapsd_enabled'    => $uapsd_enabled,
            'schedule_enabled' => $schedule_enabled,
            'schedule'         => $schedule,
        ];

        if (!is_null($vlan) && $vlan_enabled) {
            $payload['vlan'] = $vlan;
        }

        if (!empty($x_passphrase) && $security !== 'open') {
            $payload['x_passphrase'] = $x_passphrase;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/add/wlanconf', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update wlan settings, base (using REST)
     * ---------------------------------------
     * return true on success
     * required parameter <wlan_id> = the "_id" value for the WLAN you wish to update
     * required parameter <payload> = stdClass object or associative array containing the configuration to apply to the wlan, must be a
     *                                (partial) object/array structured in the same manner as is returned by list_wlanconf() for the wlan.
     */
    public function set_wlansettings_base($wlan_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Update basic wlan settings
     * --------------------------
     * return true on success
     * required parameter <wlan_id>
     * required parameter <x_passphrase> = new pre-shared key, minimal length is 8 characters, maximum length is 63,
     *                                     will be ignored if set to null
     * optional parameter <name>
     */
    public function set_wlansettings($wlan_id, $x_passphrase, $name = null)
    {
        $payload = [];
        if (!is_null($x_passphrase)) {
            $payload['x_passphrase'] = trim($x_passphrase);
        }

        if (!is_null($name)) {
            $payload['name'] = trim($name);
        }

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * Disable/Enable wlan
     * -------------------
     * return true on success
     * required parameter <wlan_id>
     * required parameter <disable> = boolean; true disables the wlan, false enables it
     */
    public function disable_wlan($wlan_id, $disable)
    {
        if (!is_bool($disable)) {
            return false;
        }

        $action  = $disable ? false : true;
        $payload = ['enabled' => $action];

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * Delete a wlan (using REST)
     * --------------------------
     * return true on success
     * required parameter <wlan_id> = 24 char string; _id of the wlan which can be found with the list_wlanconf() function
     */
    public function delete_wlan($wlan_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id));

        return $this->process_response_boolean($response);
    }

    /**
     * Update MAC filter for a wlan
     * ----------------------------
     * return true on success
     * required parameter <wlan_id>            = the "_id" value for the WLAN you wish to update
     * required parameter <mac_filter_policy>  = string, "allow" or "deny"; default MAC policy to apply
     * required parameter <mac_filter_enabled> = boolean; true enables the policy, false disables it
     * required parameter <macs>               = array; must contain valid MAC strings to be placed in the MAC filter list,
     *                                           replacing existing values. Existing MAC filter list can be obtained
     *                                           through list_wlanconf().
     */
    public function set_wlan_mac_filter($wlan_id, $mac_filter_policy, $mac_filter_enabled, array $macs)
    {
        if (!is_bool($mac_filter_enabled)) {
            return false;
        }

        if (!in_array($mac_filter_policy, ['allow', 'deny'])) {
            return false;
        }

        $macs    = array_map('strtolower', $macs);
        $payload = [
            'mac_filter_enabled' => (bool) $mac_filter_enabled,
            'mac_filter_policy'  => $mac_filter_policy,
            'mac_filter_list'    => $macs
        ];

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * List events
     * -----------
     * returns an array of known events
     * optional parameter <historyhours> = hours to go back, default value is 720 hours
     * optional parameter <start>        = which event number to start with (useful for paging of results), default value is 0
     * optional parameter <limit>        = number of events to return, default value is 3000
     */
    public function list_events($historyhours = 720, $start = 0, $limit = 3000)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = [
            '_sort'  => '-time',
            'within' => intval($historyhours),
            'type'   => null,
            '_start' => intval($start),
            '_limit' => intval($limit)
        ];

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/event', $payload);

        return $this->process_response($response);
    }

    /**
     * List alarms
     * -----------
     * returns an array of known alarms
     */
    public function list_alarms()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/list/alarm');

        return $this->process_response($response);
    }

    /**
     * Count alarms
     * ------------
     * returns an array containing the alarm count
     * optional parameter <archived> = boolean; if true all alarms will be counted, if false only non-archived (active) alarms will be counted
     */
    public function count_alarms($archived = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $url_suffix = ($archived === false) ? '?archived=false' : null;
        $response   = $this->exec_curl('/api/s/' . $this->site . '/cnt/alarm' . $url_suffix);

        return $this->process_response($response);
    }

    /**
     * Archive alarms(s)
     * -----------------
     * return true on success
     * optional parameter <alarm_id> = 24 char string; _id of the alarm to archive which can be found with the list_alarms() function,
     *                                 if not provided, *all* un-archived alarms for the current site will be archived!
     */
    public function archive_alarm($alarm_id = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload = ['cmd' => 'archive-all-alarms'];
        if (!is_null($alarm_id)) {
            $payload = ['_id' => $alarm_id, 'cmd' => 'archive-alarm'];
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/evtmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Upgrade a device to the latest firmware
     * ---------------------------------------
     * return true on success
     * required parameter <device_mac> = MAC address of the device to upgrade
     *
     * NOTES:
     * - updates the device to the latest firmware known to the controller
     */
    public function upgrade_device($device_mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['mac' => strtolower($device_mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr/upgrade', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Upgrade a device to a specific firmware file
     * --------------------------------------------
     * return true on success
     * required parameter <firmware_url> = URL for the firmware file to upgrade the device to
     * required parameter <device_mac>   = MAC address of the device to upgrade
     *
     * NOTES:
     * - updates the device to the firmware file at the given URL
     * - please take great care to select a valid firmware file for the device!
     */
    public function upgrade_device_external($firmware_url, $device_mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['url' => filter_var($firmware_url, FILTER_SANITIZE_URL), 'mac' => strtolower($device_mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr/upgrade-external', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Start rolling upgrade
     * ---------------------
     * return true on success
     *
     * NOTES:
     * - updates all access points to the latest firmware known to the controller in a
     *   staggered/rolling fashion
     */
    public function start_rolling_upgrade()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'set-rollupgrade'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Cancel rolling upgrade
     * ---------------------
     * return true on success
     */
    public function cancel_rolling_upgrade()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'unset-rollupgrade'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Power-cycle the PoE output of a switch port
     * -------------------------------------------
     * return true on success
     * required parameter <switch_mac> = string; main MAC address of the switch
     * required parameter <port_idx>   = integer; port number/index of the port to be affected
     *
     * NOTES:
     * - only applies to switches and their PoE ports...
     * - port must be actually providing power
     */
    public function power_cycle_switch_port($switch_mac, $port_idx)
    {
        if (!$this->is_loggedin) {
            return false;
        }
        $payload  = ['mac' => strtolower($switch_mac), 'port_idx' => intval($port_idx), 'cmd' => 'power-cycle'];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Trigger an RF scan by an AP
     * ---------------------------
     * return true on success
     * required parameter <ap_mac> = MAC address of the AP
     */
    public function spectrum_scan($ap_mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $payload  = ['cmd' => 'spectrum-scan', 'mac' => strtolower($ap_mac)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/devmgr', $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Check the RF scanning state of an AP
     * ------------------------------------
     * returns an object with relevant information (results if available) regarding the RF scanning state of the AP
     * required parameter <ap_mac> = MAC address of the AP
     */
    public function spectrum_scan_state($ap_mac)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/stat/spectrum-scan/' . strtolower(trim($ap_mac)));

        return $this->process_response($response);
    }

    /**
     * Update device settings, base (using REST)
     * -----------------------------------------
     * return true on success
     * required parameter <device_id> = 24 char string; _id of the device which can be found with the list_devices() function
     * required parameter <payload>   = stdClass object or associative array containing the configuration to apply to the device, must be a
     *                                  (partial) object/array structured in the same manner as is returned by list_devices() for the device.
     */
    public function set_device_settings_base($device_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/device/' . trim($device_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * List Radius profiles (using REST)
     * --------------------------------------
     * returns an array of objects containing all Radius profiles for the current site
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     */
    public function list_radius_profiles()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/radiusprofile');

        return $this->process_response($response);
    }

    /**
     * List Radius user accounts (using REST)
     * --------------------------------------
     * returns an array of objects containing all Radius accounts for the current site
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     */
    public function list_radius_accounts()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/account');

        return $this->process_response($response);
    }

    /**
     * Create a Radius user account (using REST)
     * -----------------------------------------
     * returns an array containing a single object for the newly created account upon success, else returns false
     * required parameter <name>               = string; name for the new account
     * required parameter <x_password>         = string; password for the new account
     * required parameter <tunnel_type>        = integer; must be one of the following values:
     *                                              1      Point-to-Point Tunneling Protocol (PPTP)
     *                                              2      Layer Two Forwarding (L2F)
     *                                              3      Layer Two Tunneling Protocol (L2TP)
     *                                              4      Ascend Tunnel Management Protocol (ATMP)
     *                                              5      Virtual Tunneling Protocol (VTP)
     *                                              6      IP Authentication Header in the Tunnel-mode (AH)
     *                                              7      IP-in-IP Encapsulation (IP-IP)
     *                                              8      Minimal IP-in-IP Encapsulation (MIN-IP-IP)
     *                                              9      IP Encapsulating Security Payload in the Tunnel-mode (ESP)
     *                                              10     Generic Route Encapsulation (GRE)
     *                                              11     Bay Dial Virtual Services (DVS)
     *                                              12     IP-in-IP Tunneling
     *                                              13     Virtual LANs (VLAN)
     * required parameter <tunnel_medium_type> = integer; must be one of the following values:
     *                                              1      IPv4 (IP version 4)
     *                                              2      IPv6 (IP version 6)
     *                                              3      NSAP
     *                                              4      HDLC (8-bit multidrop)
     *                                              5      BBN 1822
     *                                              6      802 (includes all 802 media plus Ethernet "canonical format")
     *                                              7      E.163 (POTS)
     *                                              8      E.164 (SMDS, Frame Relay, ATM)
     *                                              9      F.69 (Telex)
     *                                              10     X.121 (X.25, Frame Relay)
     *                                              11     IPX
     *                                              12     Appletalk
     *                                              13     Decnet IV
     *                                              14     Banyan Vines
     *                                              15     E.164 with NSAP format subaddress
     * optional parameter <vlan>               = integer; VLAN to assign to the account
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     */
    public function create_radius_account($name, $x_password, $tunnel_type, $tunnel_medium_type, $vlan = null)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $tunnel_types        = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
        $tunnel_medium_types = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        if (!in_array($tunnel_type, $tunnel_types) || !in_array($tunnel_medium_type, $tunnel_medium_types)) {
            return false;
        }

        $payload = [
            'name'               => $name,
            'x_password'         => $x_password,
            'tunnel_type'        => (int) $tunnel_type,
            'tunnel_medium_type' => (int) $tunnel_medium_type
        ];

        if (isset($vlan)) {
            $payload['vlan'] = (int) $vlan;
        }

        $response = $this->exec_curl('/api/s/' . $this->site . '/rest/account', $payload);

        return $this->process_response($response);
    }

    /**
     * Update Radius account, base (using REST)
     * ----------------------------------------
     * return true on success
     * required parameter <account_id> = 24 char string; _id of the account which can be found with the list_radius_accounts() function
     * required parameter <payload>    = stdClass object or associative array containing the new profile to apply to the account, must be a (partial)
     *                                   object/array structured in the same manner as is returned by list_radius_accounts() for the account.
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     */
    public function set_radius_account_base($account_id, $payload)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'PUT';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/account/' . trim($account_id), $payload);

        return $this->process_response_boolean($response);
    }

    /**
     * Delete a Radius account (using REST)
     * ------------------------------------
     * return true on success
     * required parameter <account_id> = 24 char string; _id of the account which can be found with the list_radius_accounts() function
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     */
    public function delete_radius_account($account_id)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/' . $this->site . '/rest/account/' . trim($account_id));

        return $this->process_response_boolean($response);
    }

    /**
     * Execute specific stats command
     * ------------------------------
     * return true on success
     * required parameter <command>  = string; command to execute, known valid values
     *                                 'reset-dpi': reset all DPI counters for the current site
     */
    public function cmd_stat($command)
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!in_array($command, ['reset-dpi'])) {
            return false;
        }

        $payload  = ['cmd' => trim($command)];
        $response = $this->exec_curl('/api/s/' . $this->site . '/cmd/stat', $payload);

        return $this->process_response_boolean($response);
    }

    /****************************************************************
     * "Aliases" for deprecated functions from here, to support
     * backward compatibility:
     ****************************************************************/

    /**
     * List access points and other devices under management of the controller (USW and/or USG devices)
     * ------------------------------------------------------------------------------------------------
     * returns an array of known device objects (or a single device when using the <device_mac> parameter)
     * optional parameter <device_mac> = the MAC address of a single device for which the call must be made
     *
     * NOTE:
     * changed function/method name to fit it's purpose
     */
    public function list_aps($device_mac = null)
    {
        trigger_error(
            'Function list_aps() has been deprecated, use list_devices() instead.',
            E_USER_DEPRECATED
        );

        return $this->list_devices($device_mac);
    }

    /**
     * Start flashing LED of an access point for locating purposes
     * -----------------------------------------------------------
     * return true on success
     * required parameter <mac> = device MAC address
     */
    public function set_locate_ap($mac)
    {
        trigger_error(
            'Function set_locate_ap() has been deprecated, use locate_ap() instead.',
            E_USER_DEPRECATED
        );

        return $this->locate_ap($mac, true);
    }

    /**
     * Stop flashing LED of an access point for locating purposes
     * ----------------------------------------------------------
     * return true on success
     * required parameter <mac> = device MAC address
     */
    public function unset_locate_ap($mac)
    {
        trigger_error(
            'Function unset_locate_ap() has been deprecated, use locate_ap() instead.',
            E_USER_DEPRECATED
        );

        return $this->locate_ap($mac, false);
    }

    /**
     * Switch LEDs of all the access points ON
     * ---------------------------------------
     * return true on success
     */
    public function site_ledson()
    {
        trigger_error(
            'Function site_ledson() has been deprecated, use site_leds() instead.',
            E_USER_DEPRECATED
        );

        return $this->site_leds(true);
    }

    /**
     * Switch LEDs of all the access points OFF
     * ----------------------------------------
     * return true on success
     */
    public function site_ledsoff()
    {
        trigger_error(
            'Function site_ledsoff() has been deprecated, use site_leds() instead.',
            E_USER_DEPRECATED
        );

        return $this->site_leds(false);
    }

    /**
     * Reboot an access point
     * ----------------------
     * return true on success
     * required parameter <mac> = device MAC address
     */
    public function restart_ap($mac)
    {
        trigger_error(
            'Function restart_ap() has been deprecated, use restart_device() instead.',
            E_USER_DEPRECATED
        );

        return $this->restart_device($mac);
    }


    /**
     * Custom API request
     * ------------------
     * returns results as requested, returns false on incorrect parameters
     * required parameter <path>         = string; suffix of the URL (following the port number) to pass request to, *must* start with a "/" character
     * optional parameter <request_type> = string; HTTP request type, can be GET (default), POST, PUT, or DELETE
     * optional parameter <payload>      = stdClass object or associative array containing the payload to pass
     * optional parameter <return>       = string; determines how to return results, value must be "boolean" when the method must return a
     *                                     boolean result (true/false) or "array" when the method must return data as an array
     *
     * NOTE:
     * Only use this method when you fully understand the behavior of the UniFi controller API. No input validation is performed, to be used with care!
     */
    public function custom_api_request($path, $request_type = 'GET', $payload = null, $return = 'array')
    {
        if (!$this->is_loggedin) {
            return false;
        }

        if (!in_array($request_type, $this->request_types_allowed)) {
            return false;
        }

        $this->request_type = $request_type;
        $response           = $this->exec_curl($path, $payload);

        if ($return === 'array') {
            return $this->process_response($response);
        } elseif ($return === 'boolean') {
            return $this->process_response_boolean($response);
        }

        return false;
    }

    /****************************************************************
     * setter/getter functions from here:
     ****************************************************************/

    /**
     * Set site
     * --------
     * modify the private property site, returns the new (short) site name
     * required parameter <site> = string; must be the short site name of a site to which the
     *                             provided credentials have access
     *
     * NOTE:
     * this method can be useful when switching between sites
     */
    public function set_site($site)
    {
        $this->check_site($site);
        $this->site = trim($site);

        return $this->site;
    }

    /**
     * Get site
     * --------
     * get the value of private property site, returns the current (short) site name
     */
    public function get_site()
    {
        return $this->site;
    }

    /**
     * Set debug mode
     * --------------
     * sets debug mode to true or false, returns false if a non-boolean parameter was passed
     * required parameter <enable> = boolean; true will enable debug mode, false will disable it
     */
    public function set_debug($enable)
    {
        if ($enable === true || $enable === false) {
            $this->debug = $enable;

            return true;
        }

        trigger_error('Error: the parameter for set_debug() must be boolean');

        return false;
    }

    /**
     * Get debug mode
     * --------------
     * get the value of private property debug, returns the current boolean value for debug
     */
    public function get_debug()
    {
        return $this->debug;
    }

    /**
     * Get last raw results
     * --------------------
     * returns the raw results of the last method called, returns false if unavailable
     * optional parameter <return_json> = boolean; true will return the results in "pretty printed" json format,
     *                                    false returns PHP stdClass Object format (default)
     */
    public function get_last_results_raw($return_json = false)
    {
        if ($this->last_results_raw !== null) {
            if ($return_json) {
                return json_encode($this->last_results_raw, JSON_PRETTY_PRINT);
            }

            return $this->last_results_raw;
        }

        return false;
    }

    /**
     * Get last error message
     * ----------------------
     * returns the error message of the last method called in PHP stdClass Object format, returns false if unavailable
     */
    public function get_last_error_message()
    {
        if ($this->last_error_message !== null) {
            return $this->last_error_message;
        }

        return false;
    }

    /**
     * Get Cookie from UniFi controller (singular and plural)
     * ------------------------------------------------------
     * returns the UniFi controller cookie
     *
     * NOTES:
     * - when the results from this method are stored in $_SESSION['unificookie'], the Class will initially not
     *   log in to the controller when a subsequent request is made using a new instance. This speeds up the
     *   overall request considerably. Only when a subsequent request fails (e.g. cookies have expired) is a new login
     *   executed and the value of $_SESSION['unificookie'] updated.
     * - to force the Class instance to log out automatically upon destruct, simply call logout() or unset
     *   $_SESSION['unificookie'] at the end of your code
     */
    public function get_cookie()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        return $this->cookies;
    }

    public function get_cookies()
    {
        if (!$this->is_loggedin) {
            return false;
        }

        return $this->cookies;
    }

    /******************************************************************
     * other getter/setter functions/methods from here, use with care!
     ******************************************************************/

    public function set_cookies($cookies_value)
    {
        $this->cookies = $cookies_value;
    }

    public function get_request_type()
    {
        return $this->request_type;
    }

    public function set_request_type($request_type)
    {

        if (!in_array($request_type, $this->request_types_allowed)) {
            return false;
        }

        $this->request_type = $request_type;

        return true;
    }

    public function get_ssl_verify_peer()
    {
        return $this->curl_ssl_verify_peer;
    }

    /**
     * set the value for cURL option CURLOPT_SSL_VERIFYPEER, should be 0/false or 1/true
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     */
    public function set_ssl_verify_peer($ssl_verify_peer)
    {
        if (!in_array($ssl_verify_peer, [0, false, 1, true])) {
            return false;
        }

        $this->curl_ssl_verify_peer = $ssl_verify_peer;

        return true;
    }

    public function get_ssl_verify_host()
    {
        return $this->curl_ssl_verify_host;
    }

    /**
     * set the value for cURL option CURLOPT_SSL_VERIFYHOST, should be 0/false or 2
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     */
    public function set_ssl_verify_host($ssl_verify_host)
    {
        if (!in_array($ssl_verify_host, [0, false, 2])) {
            return false;
        }

        $this->curl_ssl_verify_host = $ssl_verify_host;

        return true;
    }

    public function get_is_unifi_os()
    {
        return $this->is_unifi_os;
    }

    public function set_is_unifi_os($is_unifi_os)
    {
        if (!in_array($is_unifi_os, [0, false, 1, true])) {
            return false;
        }

        $this->is_unifi_os = $is_unifi_os;

        return true;
    }

    public function set_connection_timeout($timeout)
    {
        $this->connect_timeout = $timeout;
    }

    public function get_connection_timeout()
    {
        return $this->connect_timeout;
    }

    /****************************************************************
     * internal (private and protected) functions from here:
     ****************************************************************/

    /**
     * Process regular responses where output is the content of the data array
     */
    protected function process_response($response_json)
    {
        $response = json_decode($response_json);
        $this->catch_json_last_error();
        $this->last_results_raw = $response;
        if (isset($response->meta->rc)) {
            if ($response->meta->rc === 'ok') {
                $this->last_error_message = null;
                if (is_array($response->data)) {
                    return $response->data;
                }

                return true;
            } elseif ($response->meta->rc === 'error') {
                /**
                 * we have an error:
                 * set $this->set last_error_message if the returned error message is available
                 */
                if (isset($response->meta->msg)) {
                    $this->last_error_message = $response->meta->msg;
                }
                if ($this->debug) {
                    trigger_error('Debug: Last error message: ' . $this->last_error_message);
                }
            }
        }

        return false;
    }

    /**
     * Process responses where output should be boolean (true/false)
     */
    protected function process_response_boolean($response_json)
    {
        $response = json_decode($response_json);
        $this->catch_json_last_error();
        $this->last_results_raw = $response;
        if (isset($response->meta->rc)) {
            if ($response->meta->rc === 'ok') {
                $this->last_error_message = null;

                return true;
            } elseif ($response->meta->rc === 'error') {
                /**
                 * we have an error:
                 * set $this->last_error_message if the returned error message is available
                 */
                if (isset($response->meta->msg)) {
                    $this->last_error_message = $response->meta->msg;
                }
                if ($this->debug) {
                    trigger_error('Debug: Last error message: ' . $this->last_error_message);
                }
            }
        }

        return false;
    }

    /**
     * Capture the latest JSON error when $this->debug is true
     */
    private function catch_json_last_error()
    {
        if ($this->debug) {
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    // JSON is valid, no error has occurred
                    $error = '';
                    break;
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
                    // PHP >= 5.3.3
                    $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_RECURSION:
                    // PHP >= 5.5.0
                    $error = 'One or more recursive references in the value to be encoded';
                    break;
                case JSON_ERROR_INF_OR_NAN:
                    // PHP >= 5.5.0
                    $error = 'One or more NAN or INF values in the value to be encoded';
                    break;
                case JSON_ERROR_UNSUPPORTED_TYPE:
                    $error = 'A value of a type that cannot be encoded was given';
                    break;
                case JSON_ERROR_INVALID_PROPERTY_NAME:
                    // PHP >= 7.0.0
                    $error = 'A property name that cannot be encoded was given';
                    break;
                case JSON_ERROR_UTF16:
                    // PHP >= 7.0.0
                    $error = 'Malformed UTF-16 characters, possibly incorrectly encoded';
                    break;
                default:
                    // we have an unknown error
                    $error = 'Unknown JSON error occured';
                    break;
            }

            if ($error !== '') {
                trigger_error('JSON decode error: ' . $error);

                return false;
            }
        }

        return true;
    }

    /**
     * validate the submitted base URL
     */
    private function check_base_url()
    {
        $url_valid = filter_var($this->baseurl, FILTER_VALIDATE_URL);
        if (!$url_valid) {
            trigger_error('The URL provided is incomplete or invalid!');

            return false;
        }

        return true;
    }

    /**
     * Check the (short) site name
     */
    private function check_site($site)
    {
        if ($this->debug && preg_match("/\s/", $site)) {
            trigger_error('The provided (short) site name may not contain any spaces');

            return false;
        }

        return true;
    }

    /**
     * Update the unificookie if sessions are enabled
     */
    private function update_unificookie()
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['unificookie']) && !empty($_SESSION['unificookie'])) {
            $this->cookies = $_SESSION['unificookie'];

            /**
             * if we have a JWT in our cookie we know we're dealing with a UniFi OS controller
             */
            if (strpos($this->cookies, 'TOKEN') !== false) {
                $this->is_unifi_os = true;
            }

            return true;
        }

        return false;
    }

    /**
     * Extract the CSRF token from our Cookie string
     */
    private function extract_csrf_token_from_cookie()
    {
        if ($this->cookies !== '') {
            $cookie_bits = explode('=', $this->cookies);
            if (!empty($cookie_bits) && array_key_exists(1, $cookie_bits)) {
                $jwt = $cookie_bits[1];
            } else {
                return false;
            }

            $jwt_components = explode('.', $jwt);
            if (!empty($jwt_components) && array_key_exists(1, $jwt_components)) {
                $jwt_payload = $jwt_components[1];
            } else {
                return false;
            }

            return json_decode(base64_decode($jwt_payload))->csrfToken;
        }

        return false;
    }

    /**
     * Execute the cURL request
     */
    protected function exec_curl($path, $payload = null)
    {
        if (!in_array($this->request_type, $this->request_types_allowed)) {
            trigger_error('an invalid HTTP request type was used: ' . $this->request_type);
        }

        if (!($ch = $this->get_curl_resource())) {
            trigger_error('$ch as returned by get_curl_resource() is not a resource');
        } else {
            $json_payload = '';

            if ($this->is_unifi_os) {
                $url = $this->baseurl . '/proxy/network' . $path;
            } else {
                $url = $this->baseurl . $path;
            }

            curl_setopt($ch, CURLOPT_URL, $url);

            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                $json_payload = json_encode($payload, JSON_UNESCAPED_SLASHES);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);

                $headers = [
                    'Content-Type: application/json;charset=UTF-8',
                    'Content-Length: ' . strlen($json_payload)
                ];

                if ($this->is_unifi_os) {
                    $csrf_token = $this->extract_csrf_token_from_cookie();
                    if ($csrf_token) {
                        $headers[] = 'x-csrf-token: ' . $csrf_token;
                    }
                }

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                /**
                 * we shouldn't be using GET (the default request type) or DELETE when passing a payload,
                 * we switch to POST instead
                 */
                if ($this->request_type === 'GET' || $this->request_type === 'DELETE') {
                    $this->request_type = 'POST';
                }

                if ($this->request_type === 'PUT') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                }

                if ($this->request_type === 'POST') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                }
            }

            if ($this->request_type === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }

            /**
             * execute the cURL request
             */
            $content = curl_exec($ch);
            if (curl_errno($ch)) {
                trigger_error('cURL error: ' . curl_error($ch));
            }

            /**
             * fetch the HTTP response code
             */
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            /**
             * an HTTP response code 401 (Unauthorized) indicates the Cookie/Token has expired in which case
             * we need to login again.
             */
            if ($http_code == 401) {
                if ($this->debug) {
                    error_log(__FUNCTION__ . ': needed to reconnect to UniFi controller');
                }

                if ($this->exec_retries == 0) {
                    /**
                     * explicitly clear the expired Cookie/Token, update other properties and log out before logging in again
                     */
                    if (isset($_SESSION['unificookie'])) {
                        $_SESSION['unificookie'] = '';
                    }

                    $this->is_loggedin = false;
                    $this->exec_retries++;
                    curl_close($ch);

                    /**
                     * then login again
                     */
                    $this->login();

                    /**
                     * when re-login was successful, simply execute the same cURL request again
                     */
                    if ($this->is_loggedin) {
                        if ($this->debug) {
                            error_log(__FUNCTION__ . ': re-logged in, calling exec_curl again');
                        }

                        return $this->exec_curl($path, $payload);
                    } else {
                        if ($this->debug) {
                            error_log(__FUNCTION__ . ': re-login failed');
                        }

                        return false;
                    }
                } else {
                    return false;
                }
            }

            if ($this->debug) {
                print PHP_EOL . '<pre>';
                print PHP_EOL . '---------cURL INFO-----------' . PHP_EOL;
                print_r(curl_getinfo($ch));
                print PHP_EOL . '-------URL & PAYLOAD---------' . PHP_EOL;
                print $url . PHP_EOL;
                if (empty($json_payload)) {
                    print 'empty payload';
                } else {
                    print $json_payload;
                }

                print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
                print $content;
                print PHP_EOL . '-----------------------------' . PHP_EOL;
                print '</pre>' . PHP_EOL;
            }

            curl_close($ch);

            /**
             * set request_type value back to default, just in case
             */
            $this->request_type = 'GET';

            return $content;

        }

        return false;
    }

    /**
     * Create a new cURL resource and return a cURL handle,
     * returns false on errors
     */
    protected function get_curl_resource()
    {
        $ch = curl_init();
        if (is_resource($ch)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->curl_ssl_verify_peer);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->curl_ssl_verify_host);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);

            if ($this->debug) {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
            }

            if ($this->cookies != '') {
                curl_setopt($ch, CURLOPT_COOKIESESSION, true);
                curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);
            }

            return $ch;
        }

        return false;
    }
}