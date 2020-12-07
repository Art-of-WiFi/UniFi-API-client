<?php
namespace UniFi_API;

/**
 * the UniFi API client class
 *
 * This UniFi API client class is based on the work done by the following developers:
 *    domwo: http://community.ubnt.com/t5/UniFi-Wireless/little-php-class-for-unifi-api/m-p/603051
 *    fbagnol: https://github.com/fbagnol/class.unifi.php
 * and the API as published by Ubiquiti:
 *    https://www.ubnt.com/downloads/unifi/<UniFi controller version number>/unifi_sh_api
 *
 * @package UniFi Controller API client class
 * @author  Art of WiFi <info@artofwifi.net>
 * @version 1.1.61
 * @license This class is subject to the MIT license that is bundled with this package in the file LICENSE.md
 * @example This directory in the package repository contains a collection of examples:
 *          https://github.com/Art-of-WiFi/UniFi-API-client/tree/master/examples
 */
class Client
{
    /**
     * protected and private properties
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
    protected $class_version       = '1.1.61';
    private $cookies               = '';
    private $request_type          = 'GET';
    private $request_types_allowed = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    private $connect_timeout       = 10;
    private $last_results_raw      = null;
    private $last_error_message    = null;
    private $curl_ssl_verify_peer  = false;
    private $curl_ssl_verify_host  = false;

    /**
     * Construct an instance of the UniFi API client class
     *
     * @param string  $user       user name to use when connecting to the UniFi controller
     * @param string  $password   password to use when connecting to the UniFi controller
     * @param string  $baseurl    optional, base URL of the UniFi controller which *must* include an 'https://' prefix,
     *                            a port suffix (e.g. :8443) is required for non-UniFi OS controllers,
     *                            do not add trailing slashes, default value is 'https://127.0.0.1:8443'
     * @param string  $site       optional, short site name to access, defaults to 'default'
     * @param string  $version    optional, the version number of the controller
     * @param bool    $ssl_verify optional, whether to validate the controller's SSL certificate or not, a value of true is
     *                            recommended for production environments to prevent potential MitM attacks, default value (false)
     *                            disables validation of the controller certificate
     */
    public function __construct($user, $password, $baseurl = '', $site = '', $version = '', $ssl_verify = false)
    {
        if (!extension_loaded('curl')) {
            trigger_error('The PHP curl extension is not loaded. Please correct this before proceeding!');
        }

        $this->user     = trim($user);
        $this->password = trim($password);

        if (!empty($baseurl)) {
            $this->check_base_url($baseurl);
            $this->baseurl = trim($baseurl);
        }

        if (!empty($site)) {
            $this->check_site($site);
            $this->site = trim($site);
        }

        if (!empty($version)) {
            $this->version = trim($version);
        }

        if ((boolean) $ssl_verify === true) {
            $this->curl_ssl_verify_peer = true;
            $this->curl_ssl_verify_host = 2;
        }
    }

    /**
     * This method is called as soon as there are no other references to the class instance
     * https://www.php.net/manual/en/language.oop5.decon.php
     *
     * NOTE: to force the class instance to log out when you're done, simply call logout()
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
     *
     * @return bool returns true upon success
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
         * first we check whether we have a "regular" controller or one based on UniFi OS,
         * prepare cURL and options
         */
        if (!($ch = $this->get_curl_resource())) {
            return false;
        }

        $curl_options = [
            CURLOPT_HEADER => true,
            CURLOPT_POST   => true,
            CURLOPT_NOBODY => true,
            CURLOPT_URL    => $this->baseurl . '/',
        ];

        curl_setopt_array($ch, $curl_options);

        /**
         * execute the cURL request and get the HTTP response code
         */
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        /**
         * we now proceed with the actual login
         */
        $curl_options = [
            CURLOPT_NOBODY     => false,
            CURLOPT_POSTFIELDS => json_encode(['username' => $this->user, 'password' => $this->password]),
            CURLOPT_HTTPHEADER => ['content-type: application/json; charset=utf-8'],
            CURLOPT_REFERER    => $this->baseurl . '/login',
            CURLOPT_URL        => $this->baseurl . '/api/login'
        ];

        /**
         * specific to UniFi OS-based controllers
         */
        if ($http_code === 200) {
            $this->is_unifi_os         = true;
            $curl_options[CURLOPT_URL] = $this->baseurl . '/api/auth/login';
        }

        curl_setopt_array($ch, $curl_options);

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
            if (array_key_exists(1, $results)) {
                $this->cookies = implode(';', $results[1]);

                /**
                 * accept cookies from regular UniFi controllers or from UniFi OS
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
     *
     * @return bool returns true upon success
     */
    public function logout()
    {
        /**
         * prepare cURL and options
         */
        if (!($ch = $this->get_curl_resource())) {
            return false;
        }

        $curl_options = [
            CURLOPT_HEADER => true,
            CURLOPT_POST   => true
        ];

        /**
         * constuct HTTP request headers as required
         */
        $headers     = ['Content-Length: 0'];
        $logout_path = '/logout';
        if ($this->is_unifi_os) {
            $logout_path = '/api/auth/logout';
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'POST';

            $csrf_token = $this->extract_csrf_token_from_cookie();
            if ($csrf_token) {
                $headers[] = 'x-csrf-token: ' . $csrf_token;
            }
        }

        $curl_options[CURLOPT_HTTPHEADER] = $headers;
        $curl_options[CURLOPT_URL]        = $this->baseurl . $logout_path;

        curl_setopt_array($ch, $curl_options);

        /**
         * execute the cURL request to logout
         */
        curl_exec($ch);

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
     *
     * @param  string $mac       client MAC address
     * @param  int    $minutes   minutes (from now) until authorization expires
     * @param  int    $up        optional, upload speed limit in kbps
     * @param  int    $down      optional, download speed limit in kbps
     * @param  int    $megabytes optional, data transfer limit in MB
     * @param  int    $ap_mac    optional, AP MAC address to which client is connected, should result in faster authorization
     * @return bool              returns true upon success
     */
    public function authorize_guest($mac, $minutes, $up = null, $down = null, $megabytes = null, $ap_mac = null)
    {
        $payload = ['cmd' => 'authorize-guest', 'mac' => strtolower($mac), 'minutes' => intval($minutes)];

        /**
         * if we have received values for up/down/megabytes/ap_mac we append them to the payload array to be submitted
         */
        if (!is_null($up)) {
            $payload['up'] = intval($up);
        }

        if (!is_null($down)) {
            $payload['down'] = intval($down);
        }

        if (!is_null($megabytes)) {
            $payload['bytes'] = intval($megabytes);
        }

        if (!is_null($ap_mac)) {
            $payload['ap_mac'] = strtolower($ap_mac);
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Unauthorize a client device
     *
     * @param  string $mac client MAC address
     * @return bool        returns true upon success
     */
    public function unauthorize_guest($mac)
    {
        $payload = ['cmd' => 'unauthorize-guest', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Reconnect a client device
     *
     * @param  string $mac client MAC address
     * @return bool        returns true upon success
     */
    public function reconnect_sta($mac)
    {
        $payload = ['cmd' => 'kick-sta', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Block a client device
     *
     * @param  string $mac client MAC address
     * @return bool        returns true upon success
     */
    public function block_sta($mac)
    {
        $payload = ['cmd' => 'block-sta', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Unblock a client device
     *
     * @param  string $mac client MAC address
     * @return bool        returns true upon success
     */
    public function unblock_sta($mac)
    {
        $payload = ['cmd' => 'unblock-sta', 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Forget one or more client devices
     *
     * NOTE:
     * only supported with controller versions 5.9.X and higher, can be
     * slow (up to 5 minutes) on larger controllers
     *
     * @param  array $macs array of client MAC addresses (strings)
     * @return bool        returns true upon success
     */
    public function forget_sta($macs)
    {
        $payload = ['cmd' => 'forget-sta', 'macs' => array_map('strtolower', $macs)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stamgr', $payload);
    }

    /**
     * Create a new user/client-device
     *
     * @param  string     $mac           client MAC address
     * @param  string     $user_group_id _id value for the user group the new user/client-device should belong to which
     *                                   can be obtained from the output of list_usergroups()
     * @param  string     $name          optional, name to be given to the new user/client-device
     * @param  string     $note          optional, note to be applied to the new user/client-device
     * @param  bool       $is_guest      optional, defines whether the new user/client-device is a guest or not
     * @param  bool       $is_wired      optional, defines whether the new user/client-device is wired or not
     * @return array|bool                returns an array with a single object containing details of the new user/client-device on success, else returns false
     */
    public function create_user($mac, $user_group_id, $name = null, $note = null, $is_guest = null, $is_wired = null)
    {
        $new_user = ['mac' => strtolower($mac), 'usergroup_id' => $user_group_id];
        if (!empty($name)) {
            $new_user['name'] = $name;
        }

        if (!empty($note)) {
            $new_user['note']  = $note;
            $new_user['noted'] = true;
        }

        if (!empty($is_guest) && is_bool($is_guest)) {
            $new_user['is_guest'] = $is_guest;
        }

        if (!empty($is_wired) && is_bool($is_wired)) {
            $new_user['is_wired'] = $is_wired;
        }

        $payload = ['objects' => [['data' => $new_user]]];

        return $this->fetch_results('/api/s/' . $this->site . '/group/user', $payload);
    }

    /**
     * Add/modify/remove a client-device note
     *
     * @param  string $user_id id of the client-device to be modified
     * @param  string $note    optional, note to be applied to the client-device, when empty or not set,
     *                         the existing note for the client-device is removed and "noted" attribute set to false
     * @return bool            returns true upon success
     */
    public function set_sta_note($user_id, $note = null)
    {
        $noted   = empty($note) ? false : true;
        $payload = ['note' => $note, 'noted' => $noted];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);
    }

    /**
     * Add/modify/remove a client device name
     *
     * @param  string $user_id id of the client-device to be modified
     * @param  string $name    optional, name to be applied to the client device, when empty or not set,
     *                         the existing name for the client device is removed
     * @return bool            returns true upon success
     */
    public function set_sta_name($user_id, $name = null)
    {
        $payload = ['name' => $name];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);
    }

    /**
     * Fetch 5 minutes site stats
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     *
     * @param  int   $start optional, Unix timestamp in milliseconds
     * @param  int   $end   optional, Unix timestamp in milliseconds
     * @return array        returns an array of 5-minute stats objects for the current site
     */
    public function stat_5minutes_site($start = null, $end = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time'
        ];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.site', $payload);
    }

    /**
     * Fetch hourly site stats
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - "bytes" are no longer returned with controller version 4.9.1 and later
     *
     * @param  int   $start optional, Unix timestamp in milliseconds
     * @param  int   $end   optional, Unix timestamp in milliseconds
     * @return array        returns an array of hourly stats objects for the current site
     */
    public function stat_hourly_site($start = null, $end = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time'
        ];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.site', $payload);
    }

    /**
     * Fetch daily site stats
     *
     * NOTES:
     * - defaults to the past 52*7*24 hours
     * - "bytes" are no longer returned with controller version 4.9.1 and later
     *
     * @param  int   $start optional, Unix timestamp in milliseconds
     * @param  int   $end   optional, Unix timestamp in milliseconds
     * @return array        returns an array of daily stats objects for the current site
     */
    public function stat_daily_site($start = null, $end = null)
    {
        $end     = empty($end) ? (time() - (time() % 3600)) * 1000 : intval($end);
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : intval($start);
        $attribs = [
            'bytes',
            'wan-tx_bytes',
            'wan-rx_bytes',
            'wlan_bytes',
            'num_sta',
            'lan-num_sta',
            'wlan-num_sta',
            'time'
        ];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.site', $payload);
    }

    /**
     * Fetch 5 minutes stats for a single access point or all access points
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     *
     * @param  int    $start optional, Unix timestamp in milliseconds
     * @param  int    $end   optional, Unix timestamp in milliseconds
     * @param  string $mac   optional, AP MAC address to return stats for, when empty,
     *                       stats for all APs are returned
     * @return array         returns an array of 5-minute stats objects
     */
    public function stat_5minutes_aps($start = null, $end = null, $mac = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : intval($start);
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
     * NOTES:
     * - defaults to the past 7*24 hours
     * - make sure that the retention policy for hourly stats is set to the correct value in
     *   the controller settings
     *
     * @param  int    $start optional, Unix timestamp in milliseconds
     * @param  int    $end   optional, Unix timestamp in milliseconds
     * @param  string $mac   optional, AP MAC address to return stats for, when empty,
     *                       stats for all APs are returned
     * @return array         returns an array of hourly stats objects
     */
    public function stat_hourly_aps($start = null, $end = null, $mac = null)
    {
        $end     = empty($end) ? (time() * 1000) : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
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
     * NOTES:
     * - defaults to the past 7*24 hours
     * - make sure that the retention policy for hourly stats is set to the correct value in
     *   the controller settings
     *
     * @param  int    $start optional, Unix timestamp in milliseconds
     * @param  int    $end   optional, Unix timestamp in milliseconds
     * @param  string $mac   optional, AP MAC address to return stats for, when empty,
     *                       stats for all APs are returned
     * @return array         returns an array of daily stats objects
     */
    public function stat_daily_aps($start = null, $end = null, $mac = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs = ['bytes', 'num_sta', 'time'];
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];
        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.ap', $payload);
    }

    /**
     * Fetch 5 minutes stats for a single user/client device
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - only supported with UniFi controller versions 5.8.X and higher
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     * - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance section
     *
     * @param  string $mac     MAC address of user/client device to return stats for
     * @param  int    $start   optional, Unix timestamp in milliseconds
     * @param  int    $end     optional, Unix timestamp in milliseconds
     * @param  array  $attribs array containing attributes (strings) to be returned, valid values are:
     *                         rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
     *                         default is ['rx_bytes', 'tx_bytes']
     * @return array           returns an array of 5-minute stats objects
     */
    public function stat_5minutes_user($mac, $start = null, $end = null, $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.user', $payload);
    }

    /**
     * Fetch hourly stats for a single user/client device
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - only supported with UniFi controller versions 5.8.X and higher
     * - make sure that the retention policy for hourly stats is set to the correct value in
     *   the controller settings
     * - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance section
     *
     * @param  string $mac     MAC address of user/client device to return stats fo
     * @param  int    $start   optional, Unix timestamp in milliseconds
     * @param  int    $end     optional, Unix timestamp in milliseconds
     * @param  array  $attribs array containing attributes (strings) to be returned, valid values are:
     *                         rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
     *                         default is ['rx_bytes', 'tx_bytes']
     * @return array           returns an array of hourly stats objects
     */
    public function stat_hourly_user($mac, $start = null, $end = null, $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.user', $payload);
    }

    /**
     * Fetch daily stats for a single user/client device
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - only supported with UniFi controller versions 5.8.X and higher
     * - make sure that the retention policy for daily stats is set to the correct value in
     *   the controller settings
     * - make sure that "Clients Historical Data" has been enabled in the UniFi controller settings in the Maintenance section
     *
     * @param  string $mac     MAC address of user/client device to return stats for
     * @param  int    $start   optional, Unix timestamp in milliseconds
     * @param  int    $end     optional, Unix timestamp in milliseconds
     * @param  array  $attribs array containing attributes (strings) to be returned, valid values are:
     *                         rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
     *                         default is ['rx_bytes', 'tx_bytes']
     * @return array           returns an array of daily stats objects
     */
    public function stat_daily_user($mac, $start = null, $end = null, $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs = empty($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => strtolower($mac)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.user', $payload);
    }

    /**
     * Fetch 5 minutes gateway stats
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     * - requires a USG
     *
     * @param  int   $start   optional, Unix timestamp in milliseconds
     * @param  int   $end     optional, Unix timestamp in milliseconds
     * @param  array $attribs array containing attributes (strings) to be returned, valid values are:
     *                        mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                        lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                        default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array          returns an array of 5-minute stats objects for the gateway belonging to the current site
     */
    public function stat_5minutes_gateway($start = null, $end = null, $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (12 * 3600 * 1000) : intval($start);
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/5minutes.gw', $payload);
    }

    /**
     * Fetch hourly gateway stats
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - requires a USG
     *
     * @param  int   $start   optional, Unix timestamp in milliseconds
     * @param  int   $end     optional, Unix timestamp in milliseconds
     * @param  array $attribs array containing attributes (strings) to be returned, valid values are:
     *                        mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                        lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                        default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array          returns an array of hourly stats objects for the gateway belonging to the current site
     */
    public function stat_hourly_gateway($start = null, $end = null, $attribs = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600 * 1000) : intval($start);
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/hourly.gw', $payload);
    }

    /**
     * Fetch daily gateway stats
     *
     * NOTES:
     * - defaults to the past 52*7*24 hours
     * - requires a USG
     *
     * @param  int   $start   optional, Unix timestamp in milliseconds
     * @param  int   $end     optional, Unix timestamp in milliseconds
     * @param  array $attribs array containing attributes (strings) to be returned, valid values are:
     *                        mem, cpu, loadavg_5, lan-rx_errors, lan-tx_errors, lan-rx_bytes,
     *                        lan-tx_bytes, lan-rx_packets, lan-tx_packets, lan-rx_dropped, lan-tx_dropped
     *                        default is ['time', 'mem', 'cpu', 'loadavg_5']
     * @return array          returns an array of hourly stats objects for the gateway belonging to the current site
     */
    public function stat_daily_gateway($start = null, $end = null, $attribs = null)
    {
        $end     = empty($end) ? (time() - (time() % 3600)) * 1000 : intval($end);
        $start   = empty($start) ? $end - (52 * 7 * 24 * 3600 * 1000) : intval($start);
        $attribs = empty($attribs) ? ['time', 'mem', 'cpu', 'loadavg_5'] : array_merge(['time'], $attribs);
        $payload = ['attrs' => $attribs, 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/daily.gw', $payload);
    }

    /**
     * Fetch speed test results
     *
     * NOTES:
     * - defaults to the past 24 hours
     * - requires a USG
     *
     * @param  int   $start optional, Unix timestamp in milliseconds
     * @param  int   $end   optional, Unix timestamp in milliseconds
     * @return array        returns an array of speed test result objects
     */
    public function stat_speedtest_results($start = null, $end = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (24 * 3600 * 1000) : intval($start);
        $payload = ['attrs' => ['xput_download', 'xput_upload', 'latency', 'time'], 'start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/report/archive.speedtest', $payload);
    }


    /**
     * Fetch IPS/IDS events
     *
     * NOTES:
     * - defaults to the past 24 hours
     * - requires a USG
     * - supported in UniFi controller versions 5.9.X and higher
     *
     * @param  int   $start optional, Unix timestamp in milliseconds
     * @param  int   $end   optional, Unix timestamp in milliseconds
     * @param  int   $limit optional, maximum number of events to return, defaults to 10000
     * @return array        returns an array of IPS/IDS event objects
     */
    public function stat_ips_events($start = null, $end = null, $limit = null)
    {
        $end     = empty($end) ? time() * 1000 : intval($end);
        $start   = empty($start) ? $end - (24 * 3600 * 1000) : intval($start);
        $limit   = empty($limit) ? 10000 : intval($limit);
        $payload = ['start' => $start, 'end' => $end, '_limit' => $limit];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/ips/event', $payload);
    }

    /**
     * Fetch login sessions
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     *
     * @param  int    $start optional, Unix timestamp in milliseconds
     * @param  int    $end   optional, Unix timestamp in milliseconds
     * @param  string $mac   optional, client MAC address to return sessions for (can only be used when start and end are also provided)
     * @param  string $type  optional, client type to return sessions for, can be 'all', 'guest' or 'user'; default value is 'all'
     * @return array         returns an array of login session objects for all devices or a single device
     */
    public function stat_sessions($start = null, $end = null, $mac = null, $type = 'all')
    {
        if (!in_array($type, ['all', 'guest', 'user'])) {
            return false;
        }

        $end     = empty($end) ? time() : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600) : intval($start);
        $payload = ['type' => $type, 'start' => $start, 'end' => $end];
        if (!empty($mac)) {
            $payload['mac'] = strtolower($mac);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/session', $payload);
    }

    /**
     * Fetch latest 'n' login sessions for a single client device
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     *
     * @param  string $mac   client MAC address
     * @param  int    $limit optional, maximum number of sessions to get (default value is 5)
     * @return array         returns an array of login session objects for all devices or a single device
     */
    public function stat_sta_sessions_latest($mac, $limit = null)
    {
        $limit   = empty($limit) ? 5 : intval($limit);
        $payload = ['mac' => strtolower($mac), '_limit' => $limit, '_sort'=> '-assoc_time'];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/session', $payload);
    }

    /**
     * Fetch authorizations
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     *
     * @param  int   $start optional, Unix timestamp in milliseconds
     * @param  int   $end   optional, Unix timestamp in milliseconds
     * @return array        returns an array of authorization objects
     */
    public function stat_auths($start = null, $end = null)
    {
        $end     = empty($end) ? time() : intval($end);
        $start   = empty($start) ? $end - (7 * 24 * 3600) : intval($start);
        $payload = ['start' => $start, 'end' => $end];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/authorization', $payload);
    }

    /**
     * Fetch client devices that connected to the site within given timeframe
     *
     * NOTES:
     * - <historyhours> is only used to select clients that were online within that period,
     *   the returned stats per client are all-time totals, irrespective of the value of <historyhours>
     *
     * @param  int   $historyhours optional, hours to go back (default is 8760 hours or 1 year)
     * @return array               returns an array of client device objects
     */
    public function stat_allusers($historyhours = 8760)
    {
        $payload = ['type' => 'all', 'conn' => 'all', 'within' => intval($historyhours)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/alluser', $payload);
    }

    /**
     * Fetch guest devices
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     *
     * @param  int   $within optional, time frame in hours to go back to list guests with valid access (default = 24*365 hours)
     * @return array         returns an array of guest device objects with valid access
     */
    public function list_guests($within = 8760)
    {
        $payload = ['within' => intval($within)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/guest', $payload);
    }

    /**
     * Fetch online client device(s)
     *
     * @param  string $client_mac optional, the MAC address of a single online client device for which the call must be made
     * @return array              returns an array of online client device objects, or in case of a single device request, returns a single client device object
     */
    public function list_clients($client_mac = null)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/sta/' . strtolower(trim($client_mac)));
    }

    /**
     * Fetch details for a single client device
     *
     * @param  string $client_mac optional, client device MAC address
     * @return array              returns an object with the client device information
     */
    public function stat_client($client_mac)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/user/' . strtolower(trim($client_mac)));
    }

    /**
     * Assign client device to another group
     *
     * @param  string $user_id  id of the user device to be modified
     * @param  string $group_id id of the user group to assign user to
     * @return bool             returns true upon success
     */
    public function set_usergroup($user_id, $group_id)
    {
        $payload = ['usergroup_id' => $group_id];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/user/' . trim($user_id), $payload);
    }

    /**
     * Update client fixedip (using REST)
     *
     * @param  string $client_id   _id value for the client
     * @param  bool   $use_fixedip determines whether use_fixedip is true or false
     * @param  string $network_id  optional, _id value for the network where the ip belongs to
     * @param  string $fixed_ip    optional, IP address, value of client's fixed_ip field
     * @return array               returns an array containing a single object with attributes of the updated client on success
     */
    public function edit_client_fixedip($client_id, $use_fixedip, $network_id = null, $fixed_ip = null)
    {
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

        return $this->fetch_results('/api/s/' . $this->site . '/rest/user/' . trim($client_id), $payload);
    }

    /**
     * Fetch user groups
     *
     * @return array returns an array of user group objects
     */
    public function list_usergroups()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/usergroup');
    }

    /**
     * Create user group (using REST)
     *
     * @param  string $group_name name of the user group
     * @param  int    $group_dn   limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @param  int    $group_up   limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @return array              containing a single object with attributes of the new usergroup ("_id", "name", "qos_rate_max_down", "qos_rate_max_up", "site_id") on success
     */
    public function create_usergroup($group_name, $group_dn = -1, $group_up = -1)
    {
        $payload = ['name' => $group_name, 'qos_rate_max_down' => intval($group_dn), 'qos_rate_max_up' => intval($group_up)];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/usergroup', $payload);
    }

    /**
     * Modify user group (using REST)
     *
     * @param  string $group_id   _id value of the user group
     * @param  string $site_id    _id value of the site
     * @param  string $group_name name of the user group
     * @param  int    $group_dn   limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @param  int    $group_up   limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * @return array              returns an array containing a single object with attributes of the updated usergroup on success
     */
    public function edit_usergroup($group_id, $site_id, $group_name, $group_dn = -1, $group_up = -1)
    {
        $this->request_type = 'PUT';
        $payload = [
            '_id'               => $group_id,
            'name'              => $group_name,
            'qos_rate_max_down' => intval($group_dn),
            'qos_rate_max_up'   => intval($group_up),
            'site_id'           => $site_id
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/usergroup/' . trim($group_id), $payload);
    }

    /**
     * Delete user group (using REST)
     *
     * @param  string $group_id _id value of the user group to delete
     * @return bool             returns true on success
     */
    public function delete_usergroup($group_id)
    {
        $this->request_type = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/usergroup/' . trim($group_id));
    }

    /**
     * Fetch AP groups
     *
     * @return array returns an array containing the current AP groups on success
     */
    public function list_apgroups()
    {
        return $this->fetch_results('/v2/api/site/' . $this->site . '/apgroups/' . trim($group_id));
    }

    /**
     * Create AP group
     *
     * @param  string $group_name  name to assign to the AP group
     * @param  array  $device_macs optional, array containing the MAC addresses (strings) of the APs to add to the new group
     * @return object              returns a single object with attributes of the new AP group on success
     */
    public function create_apgroup($group_name, $device_macs = [])
    {
        $payload = ['device_macs' => $device_macs, 'name' => $group_name];

        return $this->fetch_results('/v2/api/site/' . $this->site . '/apgroups', $payload);
    }

    /**
     * Modify AP group
     *
     * @param  string $group_id    _id value of the AP group to modify
     * @param  string $group_name  name to assign to the AP group
     * @param  array  $device_macs array containing the members of the AP group which overwrites the existing
     *                             group_members (passing an empty array clears the AP member list)
     * @return object              returns a single object with attributes of the updated AP group on success
     */
    public function edit_apgroup($group_id, $group_name, $device_macs)
    {
        $this->request_type = 'PUT';
        $payload = [
            '_id'            => $group_id,
            'attr_no_delete' => false,
            'name'           => $group_name,
            'device_macs'    => $device_macs
        ];

        return $this->fetch_results('/v2/api/site/' . $this->site . '/apgroups/' . trim($group_id), $payload);
    }

    /**
     * Delete AP group
     *
     * @param  string $group_id _id value of the AP group to delete
     * @return bool             returns true on success
     */
    public function delete_apgroup($group_id)
    {
        $this->request_type = 'DELETE';

        return $this->fetch_results_boolean('/v2/api/site/' . $this->site . '/apgroups/' . trim($group_id));
    }

    /**
     * Fetch firewall groups (using REST)
     *
     * @param  string $group_id optional, _id value of the single firewall group to list
     * @return array            containing the current firewall groups or the selected firewall group on success
     */
    public function list_firewallgroups($group_id = null)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id));
    }

    /**
     * Create firewall group (using REST)
     *
     * @param  string $group_name    name to assign to the firewall group
     * @param  string $group_type    firewall group type; valid values are address-group, ipv6-address-group, port-group
     * @param  array  $group_members array containing the members of the new group (IPv4 addresses, IPv6 addresses or port numbers)
     *                               (default is an empty array)
     * @return array                 containing a single object with attributes of the new firewall group on success
     */
    public function create_firewallgroup($group_name, $group_type, $group_members = [])
    {
        if (!in_array($group_type, ['address-group', 'ipv6-address-group', 'port-group'])) {
            return false;
        }

        $payload = ['name' => $group_name, 'group_type' => $group_type, 'group_members' => $group_members];

        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallgroup', $payload);
    }

    /**
     * Modify firewall group (using REST)
     *
     * @param  string $group_id      _id value of the firewall group to modify
     * @param  string $site_id       site_id value of the firewall group to modify
     * @param  string $group_name    name of the firewall group
     * @param  string $group_type    firewall group type; valid values are address-group, ipv6-address-group, port-group,
     *                               group_type cannot be changed for an existing firewall group!
     * @param  array  $group_members array containing the members of the group (IPv4 addresses, IPv6 addresses or port numbers)
     *                               which overwrites the existing group_members (default is an empty array)
     * @return array                 containing a single object with attributes of the updated firewall group on success
     */
    public function edit_firewallgroup($group_id, $site_id, $group_name, $group_type, $group_members = [])
    {
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

        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id), $payload);
    }

    /**
     * Delete firewall group (using REST)
     *
     * @param  string $group_id _id value of the firewall group to delete
     * @return bool             returns true on success
     */
    public function delete_firewallgroup($group_id)
    {
        $this->request_type = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/firewallgroup/' . trim($group_id));
    }

    /**
     * Fetch firewall rules (using REST)
     *
     * @return array  containing the current firewall rules on success
     */
    public function list_firewallrules()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/firewallrule');
    }

    /**
     * Fetch static routing settings (using REST)
     *
     * @param  string $route_id _id value of the static route to get settings for
     * @return array            containing the static routes and their settings
     */
    public function list_routing($route_id = '')
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/routing/' . trim($route_id));
    }

    /**
     * Fetch health metrics
     *
     * @return array  containing health metric objects
     */
    public function list_health()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/health');
    }

    /**
     * Fetch dashboard metrics
     *
     * @param  boolean $five_minutes when true, return stats based on 5 minute intervals,
     *                               returns hourly stats by default (supported on controller versions 5.5.* and higher)
     * @return array                 containing dashboard metric objects (available since controller version 4.9.1.alpha)
     */
    public function list_dashboard($five_minutes = false)
    {
        $path_suffix = $five_minutes ? '?scale=5minutes' : null;

        return $this->fetch_results('/api/s/' . $this->site . '/stat/dashboard' . $path_suffix);
    }

    /**
     * Fetch client devices
     *
     * @return array  containing known client device objects
     */
    public function list_users()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/user');
    }

    /**
     * Fetch UniFi devices
     *
     * @param  string $device_mac optional, the MAC address of a single UniFi device for which the call must be made
     * @return array              containing known UniFi device objects (or a single device when using the <device_mac> parameter)
     */
    public function list_devices($device_mac = null)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/device/' . strtolower(trim($device_mac)));
    }

    /**
     * Fetch (device) tags (using REST)
     *
     * NOTES: this endpoint was introduced with controller versions 5.5.X
     *
     * @return array  containing known device tag objects
     */
    public function list_tags()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/tag');
    }

    /**
     * Fetch rogue/neighboring access points
     *
     * @param  int   $within optional, hours to go back to list discovered "rogue" access points (default = 24 hours)
     * @return array         containing rogue/neighboring access point objects
     */
    public function list_rogueaps($within = 24)
    {
        $payload = ['within' => intval($within)];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/rogueap', $payload);
    }

    /**
     * Fetch known rogue access points
     *
     * @return array  containing known rogue access point objects
     */
    public function list_known_rogueaps()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/rogueknown');
    }

    /**
     * Generate backup
     *
     * NOTES:
     * this is an experimental function, please do not use unless you know exactly what you're doing
     *
     * @return string URL from where the backup file can be downloaded once generated
     */
    public function generate_backup()
    {
        $payload = ['cmd' => 'backup'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/backup', $payload);
    }

    /**
     * Fetch auto backups
     *
     * @return array  containing objects with backup details on success
     */
    public function list_backups()
    {
        $payload = ['cmd' => 'list-backups'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/backup', $payload);
    }

    /**
     * Fetch sites
     *
     * @return array  containing a list of sites hosted on this controller with some details
     */
    public function list_sites()
    {
        return $this->fetch_results('/api/self/sites');
    }

    /**
     * Fetch sites stats
     *
     * NOTES: this endpoint was introduced with controller version 5.2.9
     *
     * @return array  containing statistics for all sites hosted on this controller
     */
    public function stat_sites()
    {
        return $this->fetch_results('/api/stat/sites');
    }

    /**
     * Create a site
     * @param  string $description the long name for the new site
     * @return array               containing a single object with attributes of the new site ("_id", "desc", "name") on success
     */
    public function create_site($description)
    {
        $payload = ['desc' => $description, 'cmd' => 'add-site'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Delete a site
     *
     * @param  string $site_id _id value of the site to delete
     * @return bool            true on success
     */
    public function delete_site($site_id)
    {
        $payload = ['site' => $site_id, 'cmd' => 'delete-site'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Change the current site's name
     *
     * NOTES: immediately after being changed, the site is available in the output of the list_sites() function
     *
     * @param  string $site_name the new long name for the current site
     * @return bool              true on success
     */
    public function set_site_name($site_name)
    {
        $payload = ['cmd' => 'update-site', 'desc' => $site_name];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Update site country
     *
     * @param  string       $country_id _id value of the country key
     * @param  object|array $payload    stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                                  object/array structured in the same manner as is returned by list_settings() for the section with the "country" key.
     *                                  Valid country codes can be obtained using the list_country_codes() function/method.
     *                                  Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                     true on success
     */
    public function set_site_country($country_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/country/' . trim($country_id), $payload);
    }

    /**
     * Update site locale
     *
     * @param  string       $locale_id _id value of the locale section
     * @param  object|array $payload   stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                                 object/array structured in the same manner as is returned by list_settings() for section with the the "locale" key.
     *                                 Valid timezones can be obtained in Javascript as explained here:
     *                                 https://stackoverflow.com/questions/38399465/how-to-get-list-of-all-timezones-in-javascript
     *                                 or in PHP using timezone_identifiers_list().
     *                                 Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                    true on success
     */
    public function set_site_locale($locale_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/locale/' . trim($locale_id), $payload);
    }

    /**
     * Update site snmp
     *
     * @param  string       $snmp_id _id value of the snmp section
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                               object/array structured in the same manner as is returned by list_settings() for the section with the "snmp" key.
     *                               Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                  true on success
     */
    public function set_site_snmp($snmp_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/snmp/' . trim($snmp_id), $payload);
    }

    /**
     * Update site mgmt
     *
     * @param  string       $mgmt_id _id value of the mgmt section
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                               object/array structured in the same manner as is returned by list_settings() for the section with the "mgmt" key.
     *                               Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                  true on success
     */
    public function set_site_mgmt($mgmt_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/mgmt/' . trim($mgmt_id), $payload);
    }

    /**
     * Update site guest access
     *
     * @param string       $guest_access_id _id value of the guest_access section
     * @param object|array $payload         stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                                      object/array structured in the same manner as is returned by list_settings() for the section with the "guest_access" key.
     *                                      Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                         true on success
     */
    public function set_site_guest_access($guest_access_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/guest_access/' . trim($guest_access_id), $payload);
    }

    /**
     * Update site ntp
     *
     * @param  string       $ntp_id  _id value of the ntp section
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                               object/array structured in the same manner as is returned by list_settings() for the section with the "ntp" key.
     *                               Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                  true on success
     */
    public function set_site_ntp($ntp_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/ntp/' . trim($ntp_id), $payload);
    }

    /**
     * Update site connectivity
     *
     * @param  string       $connectivity_id _id value of the connectivity section
     * @param  object|array $payload         stdClass object or associative array containing the configuration to apply to the site, must be a (partial)
     *                                       object/array structured in the same manner as is returned by list_settings() for the section with the "connectivity" key.
     *                                       Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return bool                          true on success
     */
    public function set_site_connectivity($connectivity_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/setting/connectivity/' . trim($connectivity_id), $payload);
    }

    /**
     * Fetch admins
     *
     * @return array containing administrator objects for selected site
     */
    public function list_admins()
    {
        $payload = ['cmd' => 'get-admins'];

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Fetch all admins
     *
     * @return array containing administrator objects for all sites
     */
    public function list_all_admins()
    {
        return $this->fetch_results('/api/stat/admin');
    }

    /**
     * Invite a new admin for access to the current site
     *
     * NOTES:
     * - after issuing a valid request, an invite is sent to the email address provided
     * - issuing this command against an existing admin triggers a "re-invite"
     *
     * @param  string $name           name to assign to the new admin user
     * @param  string $email          email address to assign to the new admin user
     * @param  bool   $enable_sso     optional, whether or not SSO is allowed for the new admin
     *                                default value is true which enables the SSO capability
     * @param  bool   $readonly       optional, whether or not the new admin has readonly
     *                                permissions, default value is false which gives the new admin
     *                                Administrator permissions
     * @param  bool   $device_adopt   optional, whether or not the new admin has permissions to
     *                                adopt devices, default value is false. With versions < 5.9.X this only applies
     *                                when readonly is true.
     * @param  bool   $device_restart optional, whether or not the new admin has permissions to
     *                                restart devices, default value is false. With versions < 5.9.X this only applies
     *                                when readonly is true.
     * @return bool                   true on success
     */
    public function invite_admin(
        $name,
        $email,
        $enable_sso     = true,
        $readonly       = false,
        $device_adopt   = false,
        $device_restart = false
    ) {
        $email_valid = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email_valid) {
            trigger_error('The email address provided is invalid!');

            return false;
        }

        $payload = [
            'name'        => trim($name),
            'email'       => trim($email),
            'for_sso'     => $enable_sso,
            'cmd'         => 'invite-admin',
            'role'        => 'admin',
            'permissions' => []
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
     * @param  string $admin_id       _id value of the admin user to assign, can be obtained using the
     *                                list_all_admins() method/function
     * @param  bool   $readonly       optional, whether or not the new admin has readonly
     *                                permissions, default value is false which gives the new admin
     *                                Administrator permissions
     * @param  bool   $device_adopt   optional, whether or not the new admin has permissions to
     *                                adopt devices, default value is false. With versions < 5.9.X this only applies
     *                                when readonly is true.
     * @param  bool   $device_restart optional, whether or not the new admin has permissions to
     *                                restart devices, default value is false. With versions < 5.9.X this only applies
     *                                when readonly is true.
     * @return bool                   true on success
     */
    public function assign_existing_admin($admin_id, $readonly = false, $device_adopt = false, $device_restart = false)
    {
        $payload = [
            'cmd'         => 'grant-admin',
            'admin'       => trim($admin_id),
            'role'        => 'admin',
            'permissions' => []
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
     * NOTES:
     * only non-superadmin accounts can be revoked
     *
     * @param  string $admin_id _id value of the admin to revoke, can be obtained using the
     *                          list_all_admins() method/function
     * @return bool             true on success
     */
    public function revoke_admin($admin_id)
    {
        $payload = ['cmd' => 'revoke-admin', 'admin' => $admin_id];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Fetch wlan_groups
     *
     * @return array containing known wlan_groups
     */
    public function list_wlan_groups()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/wlangroup');
    }

    /**
     * Fetch sysinfo
     *
     * @return array containing known sysinfo data
     */
    public function stat_sysinfo()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/sysinfo');
    }

    /**
     * Fetch controller status
     *
     * NOTES: in order to get useful results (e.g. controller version) you can call get_last_results_raw()
     * immediately after this method
     *
     * @return bool true upon success (controller is online)
     */
    public function stat_status()
    {
        return $this->fetch_results_boolean('/status', null, false);
    }

    /**
     * Fetch self
     *
     * @return array containing information about the logged in user
     */
    public function list_self()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/self');
    }

    /**
     * Fetch vouchers
     *
     * @param  int   $create_time optional, create time of the vouchers to fetch in Unix timestamp in seconds
     * @return array              containing hotspot voucher objects
     */
    public function stat_voucher($create_time = null)
    {
        $payload = trim($create_time) != null ? ['create_time' => intval($create_time)] : [];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/voucher', $payload);
    }

    /**
     * Fetch payments
     *
     * @param  int   $within optional, number of hours to go back to fetch payments
     * @return array         containing hotspot payments
     */
    public function stat_payment($within = null)
    {
        $path_suffix = $within != null ? '?within=' . intval($within) : '';

        return $this->fetch_results('/api/s/' . $this->site . '/stat/payment' . $path_suffix);
    }

    /**
     * Create hotspot operator (using REST)
     *
     * @param  string $name       name for the hotspot operator
     * @param  string $x_password clear text password for the hotspot operator
     * @param  string $note       optional, note to attach to the hotspot operator
     * @return bool               true upon success
     */
    public function create_hotspotop($name, $x_password, $note = null)
    {
        $payload = ['name' => $name, 'x_password' => $x_password];
        if (!is_null($note)) {
            $payload['note'] = trim($note);
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/hotspotop', $payload);
    }

    /**
     * Fetch hotspot operators (using REST)
     *
     * @return array containing hotspot operators
     */
    public function list_hotspotop()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/hotspotop');
    }

    /**
     * Create voucher(s)
     *
     * NOTES: please use the stat_voucher() method/function to retrieve the newly created voucher(s) by create_time
     *
     * @param  int    $minutes   minutes the voucher is valid after activation (expiration time)
     * @param  int    $count     number of vouchers to create, default value is 1
     * @param  int    $quota     single-use or multi-use vouchers, value '0' is for multi-use, '1' is for single-use,
       *                         'n' is for multi-use n times
     * @param  string $note      note text to add to voucher when printing
     * @param  int    $up        upload speed limit in kbps
     * @param  int    $down      download speed limit in kbps
     * @param  int    $megabytes data transfer limit in MB
     * @return array             containing a single object which contains the create_time(stamp) of the voucher(s) created
     */
    public function create_voucher(
        $minutes,
        $count  = 1,
        $quota  = 0,
        $note   = null,
        $up     = null,
        $down   = null,
        $megabytes = null
    ) {
        $payload = [
            'cmd'    => 'create-voucher',
            'expire' => intval($minutes),
            'n'      => intval($count),
            'quota'  => intval($quota)
        ];

        if (!is_null($note)) {
            $payload['note'] = trim($note);
        }

        if (!is_null($up)) {
            $payload['up'] = intval($up);
        }

        if (!is_null($down)) {
            $payload['down'] = intval($down);
        }

        if (!is_null($megabytes)) {
            $payload['bytes'] = intval($megabytes);
        }

        return $this->fetch_results('/api/s/' . $this->site . '/cmd/hotspot', $payload);
    }

    /**
     * Revoke voucher
     *
     * @param  string $voucher_id _id value of the voucher to revoke
     * @return bool               true on success
     */
    public function revoke_voucher($voucher_id)
    {
        $payload = ['_id' => $voucher_id, 'cmd' => 'delete-voucher'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/hotspot', $payload);
    }

    /**
     * Extend guest authorization
     *
     * @param  string $guest_id _id value of the guest to extend the authorization for
     * @return bool             true on success
     */
    public function extend_guest_validity($guest_id)
    {
        $payload = ['_id' => $guest_id, 'cmd' => 'extend'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/hotspot', $payload);
    }

    /**
     * Fetch port forwarding stats
     *
     * @return array containing port forwarding stats
     */
    public function list_portforward_stats()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/portforward');
    }

    /**
     * Fetch DPI stats
     *
     * @return array containing DPI stats
     */
    public function list_dpi_stats()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/dpi');
    }

    /**
     * Fetch filtered DPI stats
     *
     * @param  string $type       optional, whether to returns stats by app or by category, valid values:
     *                            'by_cat' or 'by_app'
     * @param  array  $cat_filter optional, array containing numeric category ids to filter by,
     *                            only to be combined with a "by_app" value for $type
     * @return array              containing filtered DPI stats
     */
    public function list_dpi_stats_filtered($type = 'by_cat', $cat_filter = null)
    {
        if (!in_array($type, ['by_cat', 'by_app'])) {
            return false;
        }

        $payload = ['type' => $type];

        if (!is_null($cat_filter) && $type == 'by_app' && is_array($cat_filter)) {
            $payload['cats'] = $cat_filter;
        }

        return $this->fetch_results('/api/s/' . $this->site . '/stat/sitedpi', $payload);
    }

    /**
     * Fetch current channels
     *
     * @return array containing currently allowed channels
     */
    public function list_current_channels()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/current-channel');
    }

    /**
     * Fetch country codes
     *
     * NOTES:
     * these codes following the ISO standard:
     * https://en.wikipedia.org/wiki/ISO_3166-1_numeric
     *
     * @return array containing available country codes
     */
    public function list_country_codes()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/ccode');
    }

    /**
     * Fetch port forwarding settings
     *
     * @return array containing port forwarding settings
     */
    public function list_portforwarding()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/portforward');
    }

    /**
     * Fetch port configurations
     *
     * @return array containing port configurations
     */
    public function list_portconf()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/portconf');
    }

    /**
     * Fetch VoIP extensions
     *
     * @return array containing VoIP extensions
     */
    public function list_extension()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/extension');
    }

    /**
     * Fetch site settings
     *
     * @return array containing site configuration settings
     */
    public function list_settings()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/get/setting');
    }

    /**
     * Adopt a device to the selected site
     *
     * @param  string $mac device MAC address
     * @return bool        true on success
     */
    public function adopt_device($mac)
    {
        $payload = ['mac' => strtolower($mac), 'cmd' => 'adopt'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Reboot a device
     *
     * @param  string $mac         device MAC address
     * @param  string $reboot_type optional, two options: 'soft' or 'hard', defaults to soft
     *                             soft can be used for all devices, requests a plain restart of that device
     *                             hard is special for PoE switches and besides the restart also requests a
     *                             power cycle on all PoE capable ports. Keep in mind that a 'hard' reboot
     *                             does *NOT* trigger a factory-reset.
     * @return bool                true on success
     */
    public function restart_device($mac, $reboot_type = 'soft')
    {
        $payload = ['cmd' => 'restart', 'mac' => strtolower($mac)];
        if (!empty($reboot_type) && in_array($reboot_type, ['soft', 'hard'])) {
            $payload['reboot_type'] = strtolower($reboot_type);
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Force provision of a device
     *
     * @param  string $mac device MAC address
     * @return bool        true on success
     */
    public function force_provision($mac)
    {
        $payload = ['mac' => strtolower($mac), 'cmd' => 'force-provision'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Reboot a UniFi CloudKey
     *
     * NOTE:
     * This API call has no effect on UniFi controllers *not* running on a UniFi CloudKey device
     *
     * @return bool true on success
     */
    public function reboot_cloudkey()
    {
        $payload = ['cmd' => 'reboot'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/system', $payload);
    }

    /**
     * Disable/enable an access point (using REST)
     *
     * NOTES:
     * - a disabled device is excluded from the dashboard status and device count and its LED and WLAN are turned off
     * - appears to only be supported for access points
     * - available since controller versions 5.2.X
     *
     * @param  string $ap_id   value of _id for the access point which can be obtained from the device list
     * @param  bool   $disable true disables the device, false enables the device
     * @return bool            true on success
     */
    public function disable_ap($ap_id, $disable)
    {
        if (!is_bool($disable)) {
            return false;
        }

        $this->request_type = 'PUT';
        $payload            = ['disabled' => $disable];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/device/' . trim($ap_id), $payload);
    }

    /**
     * Override LED mode for a device (using REST)
     *
     * NOTES:
     * - available since controller versions 5.2.X
     *
     * @param  string $device_id     value of _id for the device which can be obtained from the device list
     * @param  string $override_mode off/on/default; "off" disables the LED of the device,
     *                               "on" enables the LED of the device,
     *                               "default" applies the site-wide setting for device LEDs
     * @return bool                  true on success
     */
    public function led_override($device_id, $override_mode)
    {
        if (!in_array($override_mode, ['off', 'on', 'default'])) {
            return false;
        }

        $this->request_type = 'PUT';
        $payload            = ['led_override' => $override_mode];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/device/' . trim($device_id), $payload);
    }

    /**
     * Toggle flashing LED of an access point for locating purposes
     *
     * NOTES:
     * replaces the old set_locate_ap() and unset_locate_ap() methods/functions
     *
     * @param  string $mac    device MAC address
     * @param  bool   $enable true enables flashing LED, false disables flashing LED
     * @return bool           true on success
     */
    public function locate_ap($mac, $enable)
    {
        if (!is_bool($enable)) {
            return false;
        }

        $cmd     = $enable ? 'set-locate' : 'unset-locate';
        $payload = ['cmd' => $cmd, 'mac' => strtolower($mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Toggle LEDs of all the access points ON or OFF
     *
     * @param  bool $enable true switches LEDs of all the access points ON, false switches them OFF
     * @return bool         true on success
     */
    public function site_leds($enable)
    {
        if (!is_bool($enable)) {
            return false;
        }

        $payload = ['led_enabled' => $enable];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/mgmt', $payload);
    }

    /**
     * Update access point radio settings
     *
     * NOTES:
     * - only supported on pre-5.X.X controller versions
     *
     * @param  string  $ap_id         the "_id" value for the access point you wish to update
     * @param  string  $radio         radio to update, default=ng
     * @param  int     $channel       channel to apply
     * @param  int     $ht            channel width, default=20
     * @param  string  $tx_power_mode power level, "low", "medium", or "high"
     * @param  int     $tx_power      transmit power level, default=0
     * @return bool                   true on success
     */
    public function set_ap_radiosettings($ap_id, $radio, $channel, $ht, $tx_power_mode, $tx_power)
    {
        $payload = [
            'radio_table' => [
                'radio'         => $radio,
                'channel'       => $channel,
                'ht'            => $ht,
                'tx_power_mode' => $tx_power_mode,
                'tx_power'      => $tx_power
            ]
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/device/' . trim($ap_id), $payload);
    }

    /**
     * Assign access point to another WLAN group
     *
     * @param  string $type_id   WLAN type, can be either 'ng' (for WLANs 2G (11n/b/g)) or 'na' (WLANs 5G (11n/a/ac))
     * @param  string $device_id _id value of the access point to be modified
     * @param  string $group_id  _id value of the WLAN group to assign device to
     * @return bool              true on success
     */
    public function set_ap_wlangroup($type_id, $device_id, $group_id)
    {
        if (!in_array($type_id, ['ng', 'na'])) {
            return false;
        }

        $payload = [
            'wlan_overrides'           => [],
            'wlangroup_id_' . $type_id => $group_id
        ];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/device/' . trim($device_id), $payload);
    }

    /**
     * Update guest login settings
     *
     * NOTES:
     * - both portal parameters are set to the same value!
     *
     * @param  bool    $portal_enabled    enable/disable the captive portal
     * @param  bool    $portal_customized enable/disable captive portal customizations
     * @param  bool    $redirect_enabled  enable/disable captive portal redirect
     * @param  string  $redirect_url      url to redirect to, must include the http/https prefix, no trailing slashes
     * @param  string  $x_password        the captive portal (simple) password
     * @param  int     $expire_number     number of units for the authorization expiry
     * @param  int     $expire_unit       number of minutes within a unit (a value 60 is required for hours)
     * @param  string  $section_id        value of _id for the site settings section where key = "guest_access", settings can be obtained
     *                                    using the list_settings() function
     * @return bool                       true on success
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
    ) {
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

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/guest_access', $payload);
    }

    /**
     * Update guest login settings, base
     *
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the guest login, must be a (partial)
     *                               object/array structured in the same manner as is returned by list_settings() for the "guest_access" section.
     * @return bool                  true on success
     */
    public function set_guestlogin_settings_base($payload)
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/guest_access', $payload);
    }

    /**
     * Update IPS/IDS settings, base
     *
     * @param  object|array $payload stdClass object or associative array containing the IPS/IDS settings to apply, must be a (partial)
     *                               object/array structured in the same manner as is returned by list_settings() for the "ips" section.
     * @return bool                  true on success
     */
    public function set_ips_settings_base($payload)
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/ips', $payload);
    }

    /**
     * Update "Super Management" settings, base
     *
     * @param  string       $settings_id value of _id for the site settings section where key = "super_mgmt", settings can be obtained
     *                                   using the list_settings() function
     * @param  object|array $payload     stdClass object or associative array containing the "Super Management" settings to apply, must be a (partial)
     *                                   object/array structured in the same manner as is returned by list_settings() for the "super_mgmt" section.
     * @return bool                      true on success
     */
    public function set_super_mgmt_settings_base($settings_id, $payload)
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/super_mgmt/' . trim($settings_id), $payload);
    }

    /**
     * Update "Super SMTP" settings, base
     *
     * @param  string       $settings_id value of _id for the site settings section where key = "super_smtp", settings can be obtained
     *                                   using the list_settings() function
     * @param  object|array $payload     stdClass object or associative array containing the "Super SMTP" settings to apply, must be a (partial)
     *                                   object/array structured in the same manner as is returned by list_settings() for the "super_smtp" section.
     * @return bool                      true on success
     */
    public function set_super_smtp_settings_base($settings_id, $payload)
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/super_smtp/' . trim($settings_id), $payload);
    }

    /**
     * Update "Super Controller Identity" settings, base
     *
     * @param  string       $settings_id value of _id for the site settings section where key = "super_identity", settings can be obtained
     *                                   using the list_settings() function
     * @param  object|array $payload     stdClass object or associative array containing the "Super Controller Identity" settings to apply, must be a (partial)
     *                                   object/array structured in the same manner as is returned by list_settings() for the "super_identity" section.
     * @return bool                      true on success
     */
    public function set_super_identity_settings_base($settings_id, $payload)
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/super_identity/' . trim($settings_id), $payload);
    }

    /**
     * Rename access point
     *
     * @param  string $ap_id  _id of the access point to rename
     * @param  string $apname new name to assign to the access point
     * @return bool           true on success
     */
    public function rename_ap($ap_id, $apname)
    {
        $payload = ['name' => $apname];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/upd/device/' . trim($ap_id), $payload);
    }

    /**
     * Move a device to another site
     *
     * @param  string $mac     MAC address of the device to move
     * @param  string $site_id _id (24 char string) of the site to move the device to
     * @return bool            true on success
     */
    public function move_device($mac, $site_id)
    {
        $payload = ['site' => $site_id, 'mac' => strtolower($mac), 'cmd' => 'move-device'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Delete a device from the current site
     *
     * @param  string $mac MAC address of the device to delete
     * @return bool            true on success
     */
    public function delete_device($mac)
    {
        $payload = ['mac' => strtolower($mac), 'cmd' => 'delete-device'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/sitemgr', $payload);
    }

    /**
     * Fetch dynamic DNS settings (using REST)
     *
     * @return array containing dynamic DNS settings
     */
    public function list_dynamicdns()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/dynamicdns');
    }

    /**
     * Create dynamic DNS settings, base (using REST)
     *
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the site, must be a
     *                               (partial) object/array structured in the same manner as is returned by list_dynamicdns() for the site.
     * @return bool                  true on success
     */
    public function create_dynamicdns($payload)
    {
        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/dynamicdns', $payload);
    }

    /**
     * Update site dynamic DNS, base (using REST)
     *
     * @param  string       $dynamicdns_id _id of the settings which can be found with the list_dynamicdns() function
     * @param  object|array $payload       stdClass object or associative array containing the configuration to apply to the site, must be a
     *                                     (partial) object/array structured in the same manner as is returned by list_dynamicdns() for the site.
     * @return bool                        true on success
     */
    public function set_dynamicdns($dynamicdns_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/dynamicdns/' . trim($dynamicdns_id), $payload);
    }

    /**
     * Fetch network settings (using REST)
     * @param  string $network_id optional, _id value of the network to get settings for
     * @return array              containing (non-wireless) networks and their settings
     */
    public function list_networkconf($network_id = '')
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id));
    }

    /**
     * Create a network (using REST)
     *
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_networkconf() for the specific network type.
     *                                Do not include the _id property, it is assigned by the controller and returned upon success.
     * @return array|bool             containing a single object with details of the new network on success, else returns false
     */
    public function create_network($payload)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/networkconf', $payload);
    }

    /**
     * Update network settings, base (using REST)
     *
     * @param  string       $network_id the "_id" value for the network you wish to update
     * @param  object|array $payload    stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                  object/array structured in the same manner as is returned by list_networkconf() for the network.
     * @return bool                     true on success
     */
    public function set_networksettings_base($network_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id), $payload);
    }

    /**
     * Delete a network (using REST)
     *
     * @param  string $network_id _id value of the network which can be found with the list_networkconf() function
     * @return bool                   true on success
     */
    public function delete_network($network_id)
    {
        $this->request_type = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/networkconf/' . trim($network_id));
    }

    /**
     * Fetch wlan settings (using REST)
     *
     * @param  string $wlan_id optional, _id value of the wlan to fetch the settings for
     * @return array           containing wireless networks and their settings, or an array containing a single wireless network when using
     *                         the <wlan_id> parameter
     */
    public function list_wlanconf($wlan_id = null)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id));
    }

    /**
     * Create a wlan
     *
     * @param  string  $name             SSID
     * @param  string  $x_passphrase     new pre-shared key, minimal length is 8 characters, maximum length is 63,
     *                                         assign a value of null when security = 'open'
     * @param  string  $usergroup_id     user group id that can be found using the list_usergroups() function
     * @param  string  $wlangroup_id     wlan group id that can be found using the list_wlan_groups() function
     * @param  boolean $enabled          optional, enable/disable wlan
     * @param  boolean $hide_ssid        optional, hide/unhide wlan SSID
     * @param  boolean $is_guest         optional, apply guest policies or not
     * @param  string  $security         optional, security type (open, wep, wpapsk, wpaeap)
     * @param  string  $wpa_mode         optional, wpa mode (wpa, wpa2, ..)
     * @param  string  $wpa_enc          optional, encryption (auto, ccmp)
     * @param  boolean $vlan_enabled     optional, enable/disable vlan for this wlan
     * @param  int     $vlan             optional, vlan id
     * @param  boolean $uapsd_enabled    optional, enable/disable Unscheduled Automatic Power Save Delivery
     * @param  boolean $schedule_enabled optional, enable/disable wlan schedule
     * @param  array   $schedule         optional, schedule rules
     * @return bool                      true on success
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
    ) {
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

        if (!empty($vlan) && $vlan_enabled) {
            $payload['vlan'] = $vlan;
        }

        if (!empty($x_passphrase) && $security !== 'open') {
            $payload['x_passphrase'] = $x_passphrase;
        }

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/add/wlanconf', $payload);
    }

    /**
     * Update wlan settings, base (using REST)
     *
     * @param  string       $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param  object|array $payload stdClass object or associative array containing the configuration to apply to the wlan, must be a
     *                               (partial) object/array structured in the same manner as is returned by list_wlanconf() for the wlan.
     * @return bool                  true on success
     */
    public function set_wlansettings_base($wlan_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id), $payload);
    }

    /**
     * Update basic wlan settings
     *
     * @param string $wlan_id      the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param string $x_passphrase new pre-shared key, minimal length is 8 characters, maximum length is 63,
     *                             is ignored if set to null
     * @param string $name         optional, SSID
     * @return bool                true on success
     */
    public function set_wlansettings($wlan_id, $x_passphrase, $name = null)
    {
        $payload = [];
        $payload['x_passphrase'] = trim($x_passphrase);

        if (!empty($name)) {
            $payload['name'] = trim($name);
        }

        return $this->set_wlansettings_base($wlan_id, $payload);
    }

    /**
     * Disable/Enable wlan
     *
     * @param  string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param  bool   $disable true disables the wlan, false enables it
     * @return bool            true on success
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
     *
     * @param  string $wlan_id the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @return bool            true on success
     */
    public function delete_wlan($wlan_id)
    {
        $this->request_type = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/wlanconf/' . trim($wlan_id));
    }

    /**
     * Update MAC filter for a wlan
     *
     * @param  string $wlan_id            the "_id" value for the WLAN which can be found with the list_wlanconf() function
     * @param  string $mac_filter_policy  string, "allow" or "deny"; default MAC policy to apply
     * @param  bool   $mac_filter_enabled true enables the policy, false disables it
     * @param  array  $macs               must contain valid MAC strings to be placed in the MAC filter list,
     *                                    replacing existing values. Existing MAC filter list can be obtained
     *                                    through list_wlanconf().
     * @return bool                       true on success
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
     * Fetch events
     *
     * @param  integer $historyhours optional, hours to go back, default value is 720 hours
     * @param  integer $start        optional, which event number to start with (useful for paging of results), default value is 0
     * @param  integer $limit        optional, number of events to return, default value is 3000
     * @return array                 containing known events
     */
    public function list_events($historyhours = 720, $start = 0, $limit = 3000)
    {
        $payload = [
            '_sort'  => '-time',
            'within' => intval($historyhours),
            'type'   => null,
            '_start' => intval($start),
            '_limit' => intval($limit)
        ];

        return $this->fetch_results('/api/s/' . $this->site . '/stat/event', $payload);
    }

    /**
     * Fetch alarms
     *
     * @param  array  $payload optional, array of flags to filter by
     *                         Example: ["archived" => false, "key" => "EVT_GW_WANTransition"]
     *                         return only unarchived for a specific key
     * @return array           containing known alarms
     */
    public function list_alarms($payload = [])
    {
        return $this->fetch_results('/api/s/' . $this->site . '/list/alarm', $payload);
    }

    /**
     * Count alarms
     *
     * @param  bool  $archived if true all alarms are counted, if false only non-archived (active) alarms are counted
     * @return array           containing the alarm count
     */
    public function count_alarms($archived = null)
    {
        $path_suffix = $archived === false ? '?archived=false' : null;

        return $this->fetch_results('/api/s/' . $this->site . '/cnt/alarm' . $path_suffix);
    }

    /**
     * Archive alarms(s)
     *
     * @param  string $alarm_id _id of the alarm to archive which can be found with the list_alarms() function,
     * @return bool             true on success
     */
    public function archive_alarm($alarm_id = null)
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
     * NOTE:
     * triggers an update of the controller cached known latest version.
     *
     * @return array|bool returns an array with a single object containing details of the current known latest controller version info
     *                    on success, else returns false
     */
    public function check_controller_update()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/fwupdate/latest-version');
    }

    /**
     * Check firmware update
     *
     * NOTE:
     * triggers a Device Firmware Update in Classic Settings > System settings > Maintenance
     *
     * @return bool returns true upon success
     */
    public function check_firmware_update()
    {
        $payload = ['cmd' => 'check-firmware-update'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/productinfo', $payload);
    }

    /**
     * Upgrade a device to the latest firmware
     *
     * NOTES:
     * - updates the device to the latest STABLE firmware known to the controller
     *
     * @param  string $device_mac MAC address of the device to upgrade
     * @return bool               returns true upon success
     */
    public function upgrade_device($device_mac)
    {
        $payload = ['mac' => strtolower($device_mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/upgrade', $payload);
    }

    /**
     * Upgrade a device to a specific firmware file
     *
     * NOTES:
     * - updates the device to the firmware file at the given URL
     * - please take great care to select a valid firmware file for the device!
     *
     * @param  string $firmware_url URL for the firmware file to upgrade the device to
     * @param  string $device_mac   MAC address of the device to upgrade
     * @return bool                 returns true upon success
     */
    public function upgrade_device_external($firmware_url, $device_mac)
    {
        $payload = ['url' => filter_var($firmware_url, FILTER_SANITIZE_URL), 'mac' => strtolower($device_mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr/upgrade-external', $payload);
    }

    /**
     * Start rolling upgrade
     *
     * NOTES:
     * - updates all access points to the latest firmware known to the controller in a
     *   staggered/rolling fashion
     *
     * @return bool returns true upon success
     */
    public function start_rolling_upgrade()
    {
        $payload = ['cmd' => 'set-rollupgrade'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Cancel rolling upgrade
     *
     * @return bool returns true upon success
     */
    public function cancel_rolling_upgrade()
    {
        $payload = ['cmd' => 'unset-rollupgrade'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Fetch firmware versions
     *
     * @param  string $type "available" or "cached", determines which firmware types to return
     * @return array        containing firmware versions
     */
    public function list_firmware($type = 'available')
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
     * NOTES:
     * - only applies to switches and their PoE ports...
     * - port must be actually providing power
     *
     * @param  string $switch_mac main MAC address of the switch
     * @param  int    $port_idx   port number/index of the port to be affected
     * @return bool               returns true upon success
     */
    public function power_cycle_switch_port($switch_mac, $port_idx)
    {
        $payload = ['mac' => strtolower($switch_mac), 'port_idx' => intval($port_idx), 'cmd' => 'power-cycle'];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Trigger an RF scan by an AP
     *
     * @param  string $ap_mac MAC address of the AP
     * @return bool           returns true upon success
     */
    public function spectrum_scan($ap_mac)
    {
        $payload = ['cmd' => 'spectrum-scan', 'mac' => strtolower($ap_mac)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/devmgr', $payload);
    }

    /**
     * Check the RF scanning state of an AP
     *
     * @param  string $ap_mac MAC address of the AP
     * @return object         containing relevant information (results if available) regarding the RF scanning state of the AP
     */
    public function spectrum_scan_state($ap_mac)
    {
        return $this->fetch_results('/api/s/' . $this->site . '/stat/spectrum-scan/' . strtolower(trim($ap_mac)));
    }

    /**
     * Update device settings, base (using REST)
     *
     * @param  string       $device_id _id of the device which can be found with the list_devices() function
     * @param  object|array $payload   stdClass object or associative array containing the configuration to apply to the device, must be a
     *                                 (partial) object/array structured in the same manner as is returned by list_devices() for the device.
     * @return bool                    true on success
     */
    public function set_device_settings_base($device_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/device/' . trim($device_id), $payload);
    }

    /**
     * Fetch Radius profiles (using REST)
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     *
     * @return array objects containing all Radius profiles for the current site
     */
    public function list_radius_profiles()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/radiusprofile');
    }

    /**
     * Fetch Radius user accounts (using REST)
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     *
     * @return array objects containing all Radius accounts for the current site
     */
    public function list_radius_accounts()
    {
        return $this->fetch_results('/api/s/' . $this->site . '/rest/account');
    }

    /**
     * Create a Radius user account (using REST)
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     *
     * @param  string $name               name for the new account
     * @param  string $x_password         password for the new account
     * @param  int    $tunnel_type        must be one of the following values:
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
     * @param  int    $tunnel_medium_type must be one of the following values:
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
     * @param  int    $vlan               optional, VLAN to assign to the account
     * @return array                      containing a single object for the newly created account upon success, else returns false
     */
    public function create_radius_account($name, $x_password, $tunnel_type, $tunnel_medium_type, $vlan = null)
    {
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

        if (!is_null($vlan)) {
            $payload['vlan'] = (int) $vlan;
        }

        return $this->fetch_results('/api/s/' . $this->site . '/rest/account', $payload);
    }

    /**
     * Update Radius account, base (using REST)
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     *
     * @param  string       $account_id _id of the account which can be found with the list_radius_accounts() function
     * @param  object|array $payload    stdClass object or associative array containing the new profile to apply to the account, must be a (partial)
     *                                  object/array structured in the same manner as is returned by list_radius_accounts() for the account.
     * @return bool                     true on success
     */
    public function set_radius_account_base($account_id, $payload)
    {
        $this->request_type = 'PUT';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/account/' . trim($account_id), $payload);
    }

    /**
     * Delete a Radius account (using REST)
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     *
     * @param  string $account_id _id of the account which can be found with the list_radius_accounts() function
     * @return bool               true on success
     */
    public function delete_radius_account($account_id)
    {
        $this->request_type = 'DELETE';

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/rest/account/' . trim($account_id));
    }

    /**
     * Execute specific stats command
     *
     * @param  string $command command to execute, known valid values:
     *                         'reset-dpi', resets all DPI counters for the current site
     * @return bool            true on success
     */
    public function cmd_stat($command)
    {
        if (!in_array($command, ['reset-dpi'])) {
            return false;
        }

        $payload = ['cmd' => trim($command)];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/cmd/stat', $payload);
    }

    /**
    * Toggle Element Adoption ON or OFF
     *
     * @param  bool $enable true enables Element Adoption, false disables Element Adoption
     * @return bool         true on success
     */
    public function set_element_adoption($enable)
    {
        if (!is_bool($enable)) {
            return false;
        }

        $payload = ['enabled' => $enable];

        return $this->fetch_results_boolean('/api/s/' . $this->site . '/set/setting/element_adopt', $payload);
    }

    /**
     * Custom API request
     *
     * NOTE:
     * Only use this method when you fully understand the behavior of the UniFi controller API. No input validation is performed, to be used with care!
     *
     * @param  string       $path         suffix of the URL (following the port number) to pass request to, *must* start with a "/" character
     * @param  string       $request_type optional, HTTP request type, can be GET (default), POST, PUT, PATCH, or DELETE
     * @param  object|array $payload      optional, stdClass object or associative array containing the payload to pass
     * @param  string       $return       optional, string; determines how to return results, when "boolean" the method must return a
     *                                    boolean result (true/false) or "array" when the method must return an array
     * @return bool|array                 returns results as requested, returns false on incorrect parameters
     */
    public function custom_api_request($path, $request_type = 'GET', $payload = null, $return = 'array')
    {
        if (!in_array($request_type, $this->request_types_allowed)) {
            return false;
        }

        if (strpos($path, '/') !== 0) {
            return false;
        }

        $this->request_type = $request_type;

        if ($return === 'array') {
            return $this->fetch_results($path, $payload);
        } elseif ($return === 'boolean') {
            return $this->fetch_results_boolean($path, $payload);
        }

        return false;
    }

    /****************************************************************
     * "Aliases" for deprecated functions from here, used to support
     * backward compatibility:
     ****************************************************************/

    /**
     * Fetch access points and other devices under management of the controller (USW and/or USG devices)
     *
     * NOTE:
     * changed function/method name to fit it's purpose
     *
     * @param  string $device_mac optional, the MAC address of a single device for which the call must be made
     * @return array              containing known device objects (or a single device when using the <device_mac> parameter)
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
     *
     * @param  string $mac device MAC address
     * @return bool        true on success
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
     *
     * @param  string $mac device MAC address
     * @return bool        true on success
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
     *
     * @return bool true on success
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
     *
     * @return bool true on success
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
     *
     * @param  string $mac device MAC address
     * @return bool        true on success
     */
    public function restart_ap($mac)
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
     * NOTE:
     * this method is useful to switch between sites
     *
     * @param  string $site string; must be the short site name of a site to which the
     *                              provided credentials have access
     * @return string               the new (short) site name
     */
    public function set_site($site)
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
    public function get_site()
    {
        return $this->site;
    }

    /**
     * Set debug mode
     *
     * @param  bool $enable true enables debug mode, false disables debug mode
     * @return bool         false when a non-boolean parameter was passed
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
     * Get the private property $debug
     *
     * @return bool the current boolean value for $debug
     */
    public function get_debug()
    {
        return $this->debug;
    }

    /**
     * Get last raw results
     *
     * @param  boolean       $return_json true returns the results in "pretty printed" json format,
     *                                    false returns PHP stdClass Object format (default)
     * @return object|string              the raw results as returned by the controller API
     */
    public function get_last_results_raw($return_json = false)
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
     * Get last error message
     *
     * @return object|bool the error message of the last method called in PHP stdClass Object format, returns false if unavailable
     */
    public function get_last_error_message()
    {
        if (!is_null($this->last_error_message)) {
            return $this->last_error_message;
        }

        return false;
    }

    /**
     * Get Cookie from UniFi controller (singular and plural)
     *
     * NOTES:
     * - when the results from this method are stored in $_SESSION['unificookie'], the Class initially does not
     *   log in to the controller when a subsequent request is made using a new instance. This speeds up the
     *   overall request considerably. Only when a subsequent request fails (e.g. cookies have expired) is a new login
     *   executed and the value of $_SESSION['unificookie'] updated.
     * - to force the Class instance to log out automatically upon destruct, simply call logout() or unset
     *   $_SESSION['unificookie'] at the end of your code
     *
     * @return string the UniFi controller cookie
     */
    public function get_cookie()
    {
        return $this->cookies;
    }

    public function get_cookies()
    {
        return $this->cookies;
    }

    /**
     * Get version of the Class
     *
     * @return string semver compatible version of this class
     */
    public function get_class_version()
    {
        return $this->class_version;
    }

    /******************************************************************
     * other getter/setter functions/methods from here, use with care!
     ******************************************************************/

    /**
     * Set value for the private property $cookies
     *
     * @param string $cookies_value new value for $cookies
     */
    public function set_cookies($cookies_value)
    {
        $this->cookies = $cookies_value;
    }

    /**
     * Get current request type
     *
     * @return string request type
     */
    public function get_request_type()
    {
        return $this->request_type;
    }

    /**
     * Set request type
     *
     * @param string $request_type a valid HTTP request type
     * @return bool                whether request was successful or not
     */
    public function set_request_type($request_type)
    {

        if (!in_array($request_type, $this->request_types_allowed)) {
            return false;
        }

        $this->request_type = $request_type;

        return true;
    }

    /**
     * Get value for cURL option CURLOPT_SSL_VERIFYPEER
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     *
     * @return bool value of private property $curl_ssl_verify_peer (cURL option CURLOPT_SSL_VERIFYPEER)
     */
    public function get_ssl_verify_peer()
    {
        return $this->curl_ssl_verify_peer;
    }

    /**
     * Set value for cURL option CURLOPT_SSL_VERIFYPEER
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     *
     * @param int|bool $ssl_verify_peer should be 0/false or 1/true
     */
    public function set_ssl_verify_peer($ssl_verify_peer)
    {
        if (!in_array($ssl_verify_peer, [0, false, 1, true])) {
            return false;
        }

        $this->curl_ssl_verify_peer = $ssl_verify_peer;

        return true;
    }


    /**
     * Get value for cURL option CURLOPT_SSL_VERIFYHOST
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     *
     * @return bool value of private property $curl_ssl_verify_peer (cURL option CURLOPT_SSL_VERIFYHOST)
     */
    public function get_ssl_verify_host()
    {
        return $this->curl_ssl_verify_host;
    }

    /**
     * Set value for cURL option CURLOPT_SSL_VERIFYHOST
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     *
     * @param int|bool $ssl_verify_host should be 0/false or 2
     */
    public function set_ssl_verify_host($ssl_verify_host)
    {
        if (!in_array($ssl_verify_host, [0, false, 2])) {
            return false;
        }

        $this->curl_ssl_verify_host = $ssl_verify_host;

        return true;
    }

    /**
     * Is current controller UnifiOS-based/UbiOS-based
     *
     * @return bool whether current controller is UnifiOS-based/UbiOS-based
     */
    public function get_is_unifi_os()
    {
        return $this->is_unifi_os;
    }

    /**
     * Set value for private property $is_unifi_os
     *
     * @param  bool|int $is_unifi_os new value, must be 0, 1, true or false
     * @return bool                  whether request was successful or not
     */
    public function set_is_unifi_os($is_unifi_os)
    {
        if (!in_array($is_unifi_os, [0, false, 1, true])) {
            return false;
        }

        $this->is_unifi_os = $is_unifi_os;

        return true;
    }

    /**
     * Set value for the private property $connect_timeout
     *
     * @param int $timeout new value for $connect_timeout in seconds
     */
    public function set_connection_timeout($timeout)
    {
        $this->connect_timeout = $timeout;
    }

    /**
     * Get current value of the private property $connect_timeout
     *
     * @return int current value if $connect_timeout
     */
    public function get_connection_timeout()
    {
        return $this->connect_timeout;
    }

    /****************************************************************
     * internal (private and protected) functions from here:
     ****************************************************************/

    /**
     * Fetch results
     *
     * execute the cURL request and return results
     *
     * @param  string       $path           request path
     * @param  object|array $payload        optional, PHP associative array or stdClass Object, payload to pass with the request
     * @param  boolean      $boolean        optional, whether the method should return a boolean result, else return
     *                                      the "data" array
     * @param  boolean      $login_required optional, whether the method requires to be logged in or not
     * @return bool|array                   [description]
     */
    protected function fetch_results($path, $payload = null, $boolean = false, $login_required = true)
    {
        /**
         * guard clause to check if we are logged in when needed
         */
        if ($login_required && !$this->is_loggedin) {
            return false;
        }

        $response = json_decode($this->exec_curl($path, $payload));
        $this->catch_json_last_error();
        $this->last_results_raw = $response;
        if (isset($response->meta->rc)) {
            if ($response->meta->rc === 'ok') {
                $this->last_error_message = null;
                if (is_array($response->data) && !$boolean) {
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
                    if ($this->debug) {
                        trigger_error('Debug: Last error message: ' . $this->last_error_message);
                    }
                }
            }
        }

        /**
         * to deal with a response coming from the new v2 API
         */
        if (strpos($path, '/v2/api/') === 0) {
            if (isset($response->errorCode)) {
                if (isset($response->message)) {
                    $this->last_error_message = $response->message;
                    if ($this->debug) {
                        trigger_error('Debug: Last error message: ' . $this->last_error_message);
                    }
                }
            } else {
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
     * @param  string       $path           request path
     * @param  object|array $payload        optional, PHP associative array or stdClass Object, payload to pass with the request
     * @param  bool         $login_required optional, whether the method requires to be logged in or not
     * @return bool                         [description]
     */
    protected function fetch_results_boolean($path, $payload = null, $login_required = true)
    {
        return $this->fetch_results($path, $payload, true, $login_required);
    }

    /**
     * Capture the latest JSON error when $this->debug is true
     *
     * @return bool returns true upon success, false upon failure
     */
    private function catch_json_last_error()
    {
        if ($this->debug) {
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    // JSON is valid, no error has occurred and we return true early
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

            trigger_error('JSON decode error: ' . $error);

            return false;
        }

        return true;
    }

    /**
     * Validate the submitted base URL
     *
     * @param  string $baseurl the base URL to validate
     * @return bool            true if base URL is a valid URL, else returns false
     */
    private function check_base_url($baseurl)
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
     * @param  string $site the (short) site name to check
     * @return bool         true if (short) site name is valid, else returns false
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
     */

    /**
     * Update the unificookie if sessions are enabled
     *
     * @return bool true when unificookie was updated, else return false
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
     *
     * @return bool|string the CSRF token upon success or false when unable to extract the CSRF token
     */
    private function extract_csrf_token_from_cookie()
    {
        if (!empty($this->cookies)) {
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
     */

    /**
     * Execute the cURL request
     *
     * @param  string       $path    path for the request
     * @param  object|array $payload optional, payload to pass with the request
     * @return bool|array            response returned by the controller API
     */
    protected function exec_curl($path, $payload = null)
    {
        if (!in_array($this->request_type, $this->request_types_allowed)) {
            trigger_error('an invalid HTTP request type was used: ' . $this->request_type);
        }

        if (!($ch = $this->get_curl_resource())) {
            trigger_error('$ch as returned by get_curl_resource() is not a resource');

            return false;
        }

        $json_payload = '';

        if ($this->is_unifi_os) {
            $url = $this->baseurl . '/proxy/network' . $path;
        } else {
            $url = $this->baseurl . $path;
        }

        /**
         * prepare cURL options
         */
        $curl_options = [
            CURLOPT_URL => $url
        ];

        if (!is_null($payload)) {
            $json_payload                     = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $curl_options[CURLOPT_POST]       = true;
            $curl_options[CURLOPT_POSTFIELDS] = $json_payload;

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

            $curl_options[CURLOPT_HTTPHEADER] = $headers;

            /**
             * we shouldn't be using GET (the default request type) or DELETE when passing a payload,
             * switch to POST instead
             */
            switch ($this->request_type) {
                case 'GET':
                    $this->request_type = 'POST';
                    break;
                case 'DELETE':
                    $this->request_type = 'POST';
                    break;
                case 'PUT':
                    $curl_options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                    break;
            }
        }

        switch ($this->request_type) {
            case 'DELETE':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'PATCH':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                break;
            case 'POST':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'POST';
                break;
        }

        curl_setopt_array($ch, $curl_options);

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

    /**
     * Create a new cURL resource and return a cURL handle
     *
     * @return object|bool cURL handle upon success, false upon failure
     */
    protected function get_curl_resource()
    {
        $ch = curl_init();
        if (is_object($ch)) {
            $curl_options = [
                CURLOPT_SSL_VERIFYPEER => $this->curl_ssl_verify_peer,
                CURLOPT_SSL_VERIFYHOST => $this->curl_ssl_verify_host,
                CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
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

        return false;
    }
}
