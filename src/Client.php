<?php
/**
 * This file is part of the art-of-wifi/unifi-api-client package
 *
 * This UniFi API client is based on the work done by the following developers:
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
 * the UniFi API client class
 */
class Client
{
    /**
     * private properties
     */
    protected $baseurl            = 'https://127.0.0.1:8443';
    protected $site               = 'default';
    protected $version            = '5.4.16';
    protected $debug              = false;
    protected $is_loggedin        = false;
    private $cookies              = '';
    private $request_type         = 'POST';
    private $connect_timeout      = 10;
    private $last_results_raw     = null;
    private $last_error_message   = null;
    private $curl_ssl_verify_peer = false;
    private $curl_ssl_verify_host = false;

    /**
     * Construct an instance of the UniFi API client class
     * ---------------------------------------------------
     * return a new class instance
     * required parameter <user>       = string; user name to use when connecting to the UniFi controller
     * required parameter <password>   = string; password to use when connecting to the UniFi controller
     * optional parameter <baseurl>    = string; base URL of the UniFi controller, *must* include "https://" prefix and port suffix (:8443)
     * optional parameter <site>       = string; short site name to access, defaults to "default"
     * optional parameter <version>    = string; the version number of the controller, defaults to "5.4.16"
     * optional parameter <ssl_verify> = boolean; whether to validate the controller's SSL certificate or not, a value of true is
     *                                   recommended for production environments to prevent potential MitM attacks, default value (false)
     *                                   is to not validate the controller certificate
     */
    function __construct($user, $password, $baseurl = '', $site = '', $version = '', $ssl_verify = false)
    {
        if (!extension_loaded('curl')) {
            trigger_error('The PHP curl extension is not loaded. Please correct this before proceeding!');
        }

        $this->user     = trim($user);
        $this->password = trim($password);

        if (!empty($baseurl)) $this->baseurl = trim($baseurl);
        if (!empty($site)) $this->site       = trim($site);
        if (!empty($version)) $this->version = trim($version);
        if ($ssl_verify === true) {
            $this->curl_ssl_verify_peer = true;
            $this->curl_ssl_verify_host = 2;
        }

        $this->check_base_url();
        $this->check_site($this->site);
        $this->update_unificookie();
    }

    function __destruct()
    {
        /**
         * if user has $_SESSION['unificookie'] set, do not logout here
         */
        if (isset($_SESSION['unificookie'])) return;

        /**
         * logout, if needed
         */
        if ($this->is_loggedin) $this->logout();
    }

    /**
     * Login to UniFi Controller
     * -------------------------
     * returns true upon success
     */
    public function login()
    {
        /**
         * if user has $_SESSION['unificookie'] set, skip the login
         */
        if (isset($_SESSION['unificookie'])) return $this->is_loggedin = true;

        $ch = $this->get_curl_obj();

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_REFERER, $this->baseurl.'/login');
        curl_setopt($ch, CURLOPT_URL, $this->baseurl.'/api/login');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $this->user, 'password' => $this->password]));

        /**
         * execute the cURL request
         */
        $content = curl_exec($ch);

        if (curl_errno($ch)) trigger_error('cURL error: '.curl_error($ch));

        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);

            print '<pre>';
            print PHP_EOL.'-----------LOGIN-------------'.PHP_EOL;
            print_r (curl_getinfo($ch));
            print PHP_EOL.'----------RESPONSE-----------'.PHP_EOL;
            print $content;
            print PHP_EOL.'-----------------------------'.PHP_EOL;
            print '</pre>';
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body        = trim(substr($content, $header_size));
        $code        = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close ($ch);

        preg_match_all('|Set-Cookie: (.*);|U', substr($content, 0, $header_size), $results);

        if (isset($results[1])) {
            $this->cookies = implode(';', $results[1]);
            if (!empty($body)) {
                if (($code >= 200) && ($code < 400)) {
                    if (strpos($this->cookies, 'unifises') !== false) return $this->is_loggedin = true;
                }

                if ($code === 400) {
                    trigger_error('We have received an HTTP response status: 400. Probably a controller login failure');
                    return $code;
                }
            }
        }

        return false;
    }

    /**
     * Logout from UniFi Controller
     * ----------------------------
     * returns true upon success
     */
    public function logout()
    {
        if (!$this->is_loggedin) return false;
        $this->exec_curl('/logout');
        $this->is_loggedin = false;
        $this->cookies     = '';
        return true;
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
     *                                    PHP stdClass Object format is returned by default
     */
    public function get_last_results_raw($return_json = false)
    {
        if ($this->last_results_raw !== null) {
            if ($return_json) return json_encode($this->last_results_raw, JSON_PRETTY_PRINT);
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
        if ($this->last_error_message !== null) return $this->last_error_message;
        return false;
    }

    /**
     * Get Cookie from UniFi Controller
     * --------------------------------
     * returns the UniFi controller cookie
     *
     * NOTES:
     * - when the results from this method are stored in $_SESSION['unificookie'], the class will initially not
     *   log in to the controller when a subsequent request is made using a new instance. This speeds up the
     *   overall request considerably. If that subsequent request fails (e.g. cookies have expired), a new login
     *   is executed automatically and the value of $_SESSION['unificookie'] is updated.
     */
    public function get_cookie()
    {
        if (!$this->is_loggedin) return false;
        return $this->cookies;
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
        if (!$this->is_loggedin) return false;
        $mac  = strtolower($mac);
        $json = ['cmd' => 'authorize-guest', 'mac' => $mac, 'minutes' => intval($minutes)];

        /**
         * if we have received values for up/down/MBytes/ap_mac we append them to the payload array to be submitted
         */
        if (isset($up))     $json['up']     = intval($up);
        if (isset($down))   $json['down']   = intval($down);
        if (isset($MBytes)) $json['bytes']  = intval($MBytes);
        if (isset($ap_mac)) $json['ap_mac'] = $ap_mac;
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/stamgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $json     = json_encode(['cmd' => 'unauthorize-guest', 'mac' => $mac]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/stamgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $json     = json_encode(['cmd' => 'kick-sta', 'mac' => $mac]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/stamgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $json     = json_encode(['cmd' => 'block-sta', 'mac' => $mac]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/stamgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $json     = json_encode(['cmd' => 'unblock-sta', 'mac' => $mac]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/stamgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Forget one or more client devices
     * ---------------------------------
     * return true on success
     * required parameter <macs> = array of client MAC addresses
     *
     * NOTE:
     * only supported with controller versions 5.9.X and higher
     */
    public function forget_sta($macs)
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['cmd' => 'forget-sta', 'macs' => $macs]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/stamgr', 'json='.$json);
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
     */
    public function create_user($mac, $user_group_id, $name = null, $note = null)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'POST';
        $new_user           = ['mac' => $mac, 'usergroup_id' => $user_group_id];
        if (!is_null($name)) $new_user['name'] = $name;
        if (!is_null($note)) {
            $new_user['note'] = $note;
            $new_user['noted'] = true;
        }
        $json               = ['objects' => [['data' => $new_user]]];
        $json               = json_encode($json);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/group/user', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $noted    = (is_null($note)) || (empty($note)) ? false : true;
        $json     = json_encode(['note' => $note, 'noted' => $noted]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/upd/user/'.trim($user_id), 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['name' => $name]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/upd/user/'.trim($user_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * 5 minutes site stats method
     * ---------------------------
     * returns an array of 5-minute stats objects for the current site
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     *
     * NOTES:
     * - defaults to the past 12 hours
     * - this function/method is only supported on controller versions 5.5.* and later
     * - make sure that the retention policy for 5 minutes stats is set to the correct value in
     *   the controller settings
     */
    public function stat_5minutes_site($start = null, $end = null)
    {
        if (!$this->is_loggedin) return false;
        $end         = is_null($end) ? ((time())*1000) : intval($end);
        $start       = is_null($start) ? $end-(12*3600*1000) : intval($start);
        $attributes  = ['bytes', 'wan-tx_bytes', 'wan-rx_bytes', 'wlan_bytes', 'num_sta', 'lan-num_sta', 'wlan-num_sta', 'time'];
        $json        = json_encode(['attrs' => $attributes, 'start' => $start, 'end' => $end]);
        $response    = $this->exec_curl('/api/s/'.$this->site.'/stat/report/5minutes.site', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Hourly site stats method
     * ------------------------
     * returns an array of hourly stats objects for the current site
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - "bytes" are no longer returned with controller version 4.9.1 and later
     */
    public function stat_hourly_site($start = null, $end = null)
    {
        if (!$this->is_loggedin) return false;
        $end         = is_null($end) ? ((time())*1000) : intval($end);
        $start       = is_null($start) ? $end-(7*24*3600*1000) : intval($start);
        $attributes  = ['bytes', 'wan-tx_bytes', 'wan-rx_bytes', 'wlan_bytes', 'num_sta', 'lan-num_sta', 'wlan-num_sta', 'time'];
        $json        = json_encode(['attrs' => $attributes, 'start' => $start, 'end' => $end]);
        $response    = $this->exec_curl('/api/s/'.$this->site.'/stat/report/hourly.site', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Daily site stats method
     * ------------------------
     * returns an array of daily stats objects for the current site
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     *
     * NOTES:
     * - defaults to the past 52*7*24 hours
     * - bytes" are no longer returned with controller version 4.9.1 and later
     */
    public function stat_daily_site($start = null, $end = null)
    {
        if (!$this->is_loggedin) return false;
        $end        = is_null($end) ? ((time()-(time() % 3600))*1000) : intval($end);
        $start      = is_null($start) ? $end-(52*7*24*3600*1000) : intval($start);
        $attributes = ['bytes', 'wan-tx_bytes', 'wan-rx_bytes', 'wlan_bytes', 'num_sta', 'lan-num_sta', 'wlan-num_sta', 'time'];
        $json       = json_encode(['attrs' => $attributes, 'start' => $start, 'end' => $end]);
        $response   = $this->exec_curl('/api/s/'.$this->site.'/stat/report/daily.site', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * 5 minutes stats method for a single access point or all access points
     * ---------------------------------------------------------------------
     * returns an array of 5-minute stats objects
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
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
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? ((time())*1000) : intval($end);
        $start    = is_null($start) ? $end-(12*3600*1000) : intval($start);
        $json     = ['attrs' => ['bytes', 'num_sta', 'time'], 'start' => $start, 'end' => $end];
        if (!is_null($mac)) $json['mac'] = strtolower($mac);
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/report/5minutes.ap', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Hourly stats method for a single access point or all access points
     * ------------------------------------------------------------------
     * returns an array of hourly stats objects
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     * optional parameter <mac>   = AP MAC address to return stats for
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - UniFi controller does not keep these stats longer than 5 hours with versions < 4.6.6
     */
    public function stat_hourly_aps($start = null, $end = null, $mac = null)
    {
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? ((time())*1000) : intval($end);
        $start    = is_null($start) ? $end-(7*24*3600*1000) : intval($start);
        $json     = ['attrs' => ['bytes', 'num_sta', 'time'], 'start' => $start, 'end' => $end];
        if (!is_null($mac)) $json['mac'] = strtolower($mac);
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/report/hourly.ap', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Daily stats method for a single access point or all access points
     * -----------------------------------------------------------------
     * returns an array of daily stats objects
     * optional parameter <start> = Unix timestamp in seconds
     * optional parameter <end>   = Unix timestamp in seconds
     * optional parameter <mac>   = AP MAC address to return stats for
     *
     * NOTES:
     * - defaults to the past 7*24 hours
     * - UniFi controller does not keep these stats longer than 5 hours with versions < 4.6.6
     */
    public function stat_daily_aps($start = null, $end = null, $mac = null)
    {
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? ((time())*1000) : intval($end);
        $start    = is_null($start) ? $end-(7*24*3600*1000) : intval($start);
        $json     = ['attrs' => ['bytes', 'num_sta', 'time'], 'start' => $start, 'end' => $end];
        if (!is_null($mac)) $json['mac'] = strtolower($mac);
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/report/daily.ap', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * 5 minutes stats method for a single user/client device
     * ------------------------------------------------------
     * returns an array of 5-minute stats objects
     * required parameter <mac>     = MAC address of user/client device to return stats for
     * optional parameter <start>   = Unix timestamp in seconds
     * optional parameter <end>     = Unix timestamp in seconds
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
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? ((time())*1000) : intval($end);
        $start    = is_null($start) ? $end-(12*3600*1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $json     = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => $mac];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/report/5minutes.user', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Hourly stats method for a a single user/client device
     * -----------------------------------------------------
     * returns an array of hourly stats objects
     * required parameter <mac>     = MAC address of user/client device to return stats for
     * optional parameter <start>   = Unix timestamp in seconds
     * optional parameter <end>     = Unix timestamp in seconds
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
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? ((time())*1000) : intval($end);
        $start    = is_null($start) ? $end-(7*24*3600*1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $json     = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => $mac];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/report/hourly.user', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Daily stats method for a single user/client device
     * --------------------------------------------------
     * returns an array of daily stats objects
     * required parameter <mac>     = MAC address of user/client device to return stats for
     * optional parameter <start>   = Unix timestamp in seconds
     * optional parameter <end>     = Unix timestamp in seconds
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
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? ((time())*1000) : intval($end);
        $start    = is_null($start) ? $end-(7*24*3600*1000) : intval($start);
        $attribs  = is_null($attribs) ? ['time', 'rx_bytes', 'tx_bytes'] : array_merge(['time'], $attribs);
        $json     = ['attrs' => $attribs, 'start' => $start, 'end' => $end, 'mac' => $mac];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/report/daily.user', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        if (!in_array($type, ['all', 'guest', 'user'])) return false;
        $end      = is_null($end) ? time() : intval($end);
        $start    = is_null($start) ? $end-(7*24*3600) : intval($start);
        $json     = ['type'=> $type, 'start' => $start, 'end' => $end];
        if (!is_null($mac)) $json['mac'] = strtolower($mac);
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/session', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $limit    = is_null($limit) ? 5 : intval($limit);
        $json     = json_encode(['mac' => $mac, '_limit' => $limit, '_sort'=> '-assoc_time']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/session', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $end      = is_null($end) ? time() : intval($end);
        $start    = is_null($start) ? $end-(7*24*3600) : intval($start);
        $json     = json_encode(['start' => $start, 'end' => $end]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/authorization', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['type' => 'all', 'conn' => 'all', 'within' => intval($historyhours)]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/alluser', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['within' => intval($within)]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/guest', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/sta/'.trim($client_mac));
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/user/'.trim($client_mac));
        return $this->process_response($response);
    }

    /**
     * List user groups
     * ----------------
     * returns an array of user group objects
     */
    public function list_usergroups()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/usergroup');
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['usergroup_id' => $group_id]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/upd/user/'.trim($user_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Update user group (using REST)
     * ------------------------------
     * returns an array containing a single object with attributes of the updated usergroup on success
     * required parameter <group_id>   = id of the user group
     * required parameter <site_id>    = id of the site
     * required parameter <group_name> = name of the user group
     * optional parameter <group_dn>   = limit download bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     * optional parameter <group_up>   = limit upload bandwidth in Kbps (default = -1, which sets bandwidth to unlimited)
     *
     */
    public function edit_usergroup($group_id, $site_id, $group_name, $group_dn = -1, $group_up = -1)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode(['_id' => $group_id, 'name' => $group_name, 'qos_rate_max_down' => intval($group_dn), 'qos_rate_max_up' => intval($group_up), 'site_id' => $site_id]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/usergroup/'.trim($group_id), $json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['name' => $group_name, 'qos_rate_max_down' => intval($group_dn), 'qos_rate_max_up' => intval($group_up)]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/usergroup', $json);
        return $this->process_response($response);
    }

    /**
     * Delete user group (using REST)
     * ------------------------------
     * returns true on success
     * required parameter <group_id> = id of the user group
     */
    public function delete_usergroup($group_id)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/usergroup/'.trim($group_id));
        return $this->process_response_boolean($response);
    }

    /**
     * List health metrics
     * -------------------
     * returns an array of health metric objects
     */
    public function list_health()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/health');
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
        if (!$this->is_loggedin) return false;
        $url_suffix = $five_minutes ? '?scale=5minutes' : null;
        $response   = $this->exec_curl('/api/s/'.$this->site.'/stat/dashboard'.$url_suffix);
        return $this->process_response($response);
    }

    /**
     * List client devices
     * -------------------
     * returns an array of known client device objects
     */
    public function list_users()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/user');
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/device/'.trim($device_mac));
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/tag');
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['within' => intval($within)]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/rogueap', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * List known rogue access points
     * ------------------------------
     * returns an array of known rogue access point objects
     */
    public function list_known_rogueaps()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/rogueknown');
        return $this->process_response($response);
    }

    /**
     * List sites
     * ----------
     * returns a list sites hosted on this controller with some details
     */
    public function list_sites()
    {
        if (!$this->is_loggedin) return false;
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
        if (!$this->is_loggedin) return false;
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['desc' => $description, 'cmd' => 'add-site']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Delete a site
     * -------------
     * return true on success
     * required parameter <site_id> = 24 char string; _id of the site to delete
     */
    public function delete_site($site_id)
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['site' => $site_id, 'cmd' => 'delete-site']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Change a site's name
     * --------------------
     * return true on success
     * required parameter <site_name> = string; the long name for the site
     *
     * NOTES: immediately after being changed, the site will be available in the output of the list_sites() function
     */
    public function set_site_name($site_name)
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['cmd' => 'update-site', 'desc' => $site_name]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site country
     * ----------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "country" key.
     *                                Valid country codes can be obtained using the list_country_codes() function/method.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_country($country_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/country/'.trim($country_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site locale
     * ---------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "locale" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_locale($locale_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/locale/'.trim($locale_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site snmp
     * -------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "snmp" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_snmp($snmp_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/snmp/'.trim($snmp_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site mgmt
     * -------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "mgmt" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_mgmt($mgmt_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/mgmt/'.trim($mgmt_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site guest access
     * ---------------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "guest_access" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_guest_access($guest_access_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/guest_access/'.trim($guest_access_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site ntp
     * ------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "ntp" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_ntp($ntp_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/ntp/'.trim($ntp_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Set site connectivity
     * ---------------------
     * required parameter <setting> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                object structured in the same manner as is returned by list_settings() for the "connectivity" key.
     *                                Do not include the _id property, it will be assigned by the controller and returned upon success.
     * return true on success
     */
    public function set_site_connectivity($connectivity_id, $setting)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode($setting);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/setting/connectivity/'.trim($connectivity_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * List admins
     * -----------
     * returns an array containing administrator objects for selected site
     */
    public function list_admins()
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['cmd' => 'get-admins']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * List all admins
     * ---------------
     * returns an array containing administrator objects for all sites
     */
    public function list_all_admins()
    {
        if (!$this->is_loggedin) return false;
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
     *                                       permissions, default value is true which gives the new admin
     *                                       administrator permissions
     * optional parameter <device_adopt>   = boolean, whether or not the new admin will have permissions to
     *                                       adopt devices, default value is false. Only applies when readonly
     *                                       is true.
     * optional parameter <device_restart> = boolean, whether or not the new admin will have permissions to
     *                                       restart devices, default value is false. Only applies when readonly
     *                                       is true.
     *
     * NOTES:
     * - after issuing a valid request, an invite will be sent to the email address provided
     * - issuing this command against an existing admin will trigger a "re-invite"
     */
    public function invite_admin($name, $email, $enable_sso = true, $readonly = false, $device_adopt = false, $device_restart = false)
    {
        if (!$this->is_loggedin) return false;
        $email_valid = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email_valid) {
            trigger_error('The email address provided is invalid!');
            return false;
        }

        $json = ['name' => trim($name), 'email' => trim($email), 'for_sso' => $enable_sso, 'cmd' => 'invite-admin'];
        if ($readonly) {
            $json['role'] = 'readonly';
            $permissions = [];
            if ($device_adopt) {
                $permissions[] = "API_DEVICE_ADOPT";
            }

            if ($device_restart) {
                $permissions[] = "API_DEVICE_RESTART";
            }

            if (count($permissions) > 0) {
                $json['permissions'] = $permissions;
            }
        }

        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Revoke an admin
     * ---------------
     * returns true on success
     * required parameter <admin_id> = id of the admin to revoke which can be obtained using the
     *                                 list_all_admins() method/function
     *
     * NOTES:
     * only non-superadmins account can be revoked
     */
    public function revoke_admin($admin_id)
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['admin' => $admin_id, 'cmd' => 'revoke-admin']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * List wlan_groups
     * ----------------
     * returns an array containing known wlan_groups
     */
    public function list_wlan_groups()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/wlangroup');
        return $this->process_response($response);
    }

    /**
     * Show sysinfo
     * ------------
     * returns an array of known sysinfo data
     */
    public function stat_sysinfo()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/sysinfo');
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/self');
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
        if (!$this->is_loggedin) return false;
        $json     = (trim($create_time) != null) ? json_encode(['create_time' => intval($create_time)]) : json_encode([]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/voucher', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $url_suffix = (($within != null) ? '?within='.intval($within) : '');
        $response   = $this->exec_curl('/api/s/'.$this->site.'/stat/payment'.$url_suffix);
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
        if (!$this->is_loggedin) return false;
        $json     = ['name' => $name, 'x_password' => $x_password];
        if (isset($note)) $json['note'] = trim($note);
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/hotspotop', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * List hotspot operators (using REST)
     * -----------------------------------
     * returns an array of hotspot operators
     */
    public function list_hotspotop()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/hotspotop');
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
    public function create_voucher($minutes, $count = 1, $quota = 0, $note = null, $up = null, $down = null, $MBytes = null)
    {
        if (!$this->is_loggedin) return false;
        $json     = ['cmd' => 'create-voucher', 'expire' => intval($minutes), 'n' => intval($count), 'quota' => intval($quota)];
        if (isset($note))   $json['note'] = trim($note);
        if (isset($up))     $json['up'] = intval($up);
        if (isset($down))   $json['down'] = intval($down);
        if (isset($MBytes)) $json['bytes'] = intval($MBytes);
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/hotspot', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Revoke voucher
     * --------------
     * return true on success
     * required parameter <voucher_id> = 24 char string; _id of the voucher to revoke
     */
    public function revoke_voucher($voucher_id)
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['_id' => $voucher_id, 'cmd' => 'delete-voucher']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/hotspot', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Extend guest validity
     * ---------------------
     * return true on success
     * required parameter <guest_id> = 24 char string; _id of the guest to extend validity
     */
    public function extend_guest_validity($guest_id)
    {
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['_id' => $guest_id, 'cmd' => 'extend']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/hotspot', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * List port forwarding stats
     * --------------------------
     * returns an array of port forwarding stats
     */
    public function list_portforward_stats()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/portforward');
        return $this->process_response($response);
    }

    /**
     * List DPI stats
     * --------------
     * returns an array of DPI stats
     */
    public function list_dpi_stats()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/dpi');
        return $this->process_response($response);
    }

    /**
     * List current channels
     * ---------------------
     * returns an array of currently allowed channels
     */
    public function list_current_channels()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/current-channel');
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/ccode');
        return $this->process_response($response);
    }

    /**
     * List port forwarding settings
     * -----------------------------
     * returns an array of port forwarding settings
     */
    public function list_portforwarding()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/portforward');
        return $this->process_response($response);
    }

    /**
     * List dynamic DNS settings
     * -------------------------
     * returns an array of dynamic DNS settings
     */
    public function list_dynamicdns()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/dynamicdns');
        return $this->process_response($response);
    }

    /**
     * List port configurations
     * ------------------------
     * returns an array of port configurations
     */
    public function list_portconf()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/portconf');
        return $this->process_response($response);
    }

    /**
     * List VoIP extensions
     * --------------------
     * returns an array of VoIP extensions
     */
    public function list_extension()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/extension');
        return $this->process_response($response);
    }

    /**
     * List site settings
     * ------------------
     * returns an array of site configuration settings
     */
    public function list_settings()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/get/setting');
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
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $json     = json_encode(['mac' => $mac, 'cmd' => 'adopt']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Reboot an access point
     * ----------------------
     * return true on success
     * required parameter <mac> = device MAC address
     */
    public function restart_ap($mac)
    {
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $json     = json_encode(['cmd' => 'restart', 'mac' => $mac]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json     = json_encode(['disabled' => (bool)$disable]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/device/'.trim($ap_id), $json);
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
        if (!$this->is_loggedin) return false;
        $this->request_type    = 'PUT';
        if (!in_array($override_mode, ['off', 'on', 'default'])) return false;
        $json     = json_encode(['led_override' => $override_mode]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/device/'.trim($device_id), $json);
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
        if (!$this->is_loggedin) return false;
        $mac      = strtolower($mac);
        $cmd      = (($enable) ? 'set-locate' : 'unset-locate');
        $json     = json_encode(['cmd' => $cmd, 'mac' => $mac]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['led_enabled' => (bool)$enable]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/set/setting/mgmt', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Update access point radio settings
     * ----------------------------------
     * return true on success
     * required parameter <ap_id>
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['radio_table' => ['radio' => $radio, 'channel' => $channel, 'ht' => $ht, 'tx_power_mode' => $tx_power_mode, 'tx_power' =>$tx_power]]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/upd/device/'.trim($ap_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Assign access point to another WLAN group
     * -----------------------------------------
     * return true on success
     * required parameter <wlantype_id>  = string; WLAN type, can be either 'ng' (for WLANs 2G (11n/b/g)) or 'na' (WLANs 5G (11n/a/ac))
     * required parameter <device_id>    = string; id of the access point to be modified
     * required parameter <wlangroup_id> = string; id of the WLAN group to assign device to
     *
     * NOTES:
     * - can for example be used to turn WiFi off
     */
    public function set_ap_wlangroup($wlantype_id, $device_id, $wlangroup_id) {
        if (!$this->is_loggedin) return false;
        if (!in_array($wlantype_id, ['ng', 'na'])) return false;
        $json     = json_encode(['wlan_overrides' => [],'wlangroup_id_'.$wlantype_id => $wlangroup_id]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/upd/device/'.trim($device_id),'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Update guest login settings
     * ---------------------------
     * return true on success
     * required parameter <portal_enabled>
     * required parameter <portal_customized>
     * required parameter <redirect_enabled>
     * required parameter <redirect_url>
     * required parameter <x_password>
     * required parameter <expire_number>
     * required parameter <expire_unit>
     * required parameter <site_id>
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
        $site_id
    ) {
        if (!$this->is_loggedin) return false;
        $json = [
            'portal_enabled'    => $portal_enabled,
            'portal_customized' => $portal_customized,
            'redirect_enabled'  => $redirect_enabled,
            'redirect_url'      => $redirect_url,
            'x_password'        => $x_password,
            'expire_number'     => $expire_number,
            'expire_unit'       => $expire_unit,
            'site_id'           => $site_id
        ];
        $json     = json_encode($json, JSON_UNESCAPED_SLASHES);
        $response = $this->exec_curl('/api/s/'.$this->site.'/set/setting/guest_access', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Update guestlogin settings, base
     * ------------------------------------------
     * return true on success
     * required parameter <network_settings> = stdClass object or associative array containing the configuration to apply to the guestlogin, must be a (partial)
     *                                         object/array structured in the same manner as is returned by list_settings() for the guest_access.
     */
    public function set_guestlogin_settings_base($guestlogin_settings) {
        if (!$this->is_loggedin) return false;
        $json     = json_encode($guestlogin_settings, JSON_UNESCAPED_SLASHES);
        $response = $this->exec_curl('/api/s/'.$this->site.'/set/setting/guest_access', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['name' => $apname]);
        $response = $this->exec_curl('/api/s/'.$this->site.'/upd/device/'.trim($ap_id), 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['site' => $site_id, 'mac' => $mac, 'cmd' => 'move-device']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = json_encode(['mac' => $mac, 'cmd' => 'delete-device']);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/sitemgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * List network settings (using REST)
     * ----------------------------------
     * returns an array of (non-wireless) networks and their settings
     */
    public function list_networkconf()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/networkconf');
        return $this->process_response($response);
    }

    /**
     * Create a network (using REST)
     * -----------------------------
     * return an array with a single object containing details of the new network on success, else return false
     * required parameter <network_settings> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                         object structured in the same manner as is returned by list_networkconf() for the specific network type.
     *                                         Do not include the _id property, it will be assigned by the controller and returned upon success.
     */
    public function create_network($network_settings)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'POST';
        $json               = json_encode($network_settings);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/networkconf', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Update network settings, base (using REST)
     * ------------------------------------------
     * return true on success
     * required parameter <network_id>
     * required parameter <network_settings> = stdClass object or associative array containing the configuration to apply to the network, must be a (partial)
     *                                         object/array structured in the same manner as is returned by list_networkconf() for the network.
     */
    public function set_networksettings_base($network_id, $network_settings)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json               = json_encode($network_settings);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/networkconf/'.trim($network_id), 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Delete a network (using REST)
     * -----------------------------
     * return true on success
     * required parameter <network_id> = 24 char string; _id of the network which can be found with the list_networkconf() function
     */
    public function delete_network($network_id)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/networkconf/'.trim($network_id));
        return $this->process_response_boolean($response);
    }

    /**
     * List wlan settings (using REST)
     * -------------------------------
     * returns an array of wireless networks and their settings, or an array containing a single wireless network when using
     * the <wlan_id> parameter
     * optional parameter <wlan_id> = 24 char string; _id of the wlan to fetch the settings for
     */
    public function list_wlanconf($wlan_id = null)
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/wlanconf/'.trim($wlan_id));
        return $this->process_response($response);
    }

    /**
     * Create a wlan
     * -------------
     * return true on success
     * required parameter <name>             = string; SSID
     * required parameter <x_passphrase>     = string; new pre-shared key, minimal length is 8 characters, maximum length is 63
     * required parameter <usergroup_id>     = string; user group id that can be found using the list_usergroups() function
     * required parameter <wlangroup_id>     = string; wlan group id that can be found using the list_wlan_groups() function
     * optional parameter <enabled>          = boolean; enable/disable wlan
     * optional parameter <hide_ssid>        = boolean; hide/unhide wlan SSID
     * optional parameter <is_guest>         = boolean; apply guest policies or not
     * optional parameter <security>         = string; security type
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
    ) {
        if (!$this->is_loggedin) return false;
        $json = [
            'name'             => $name,
            'x_passphrase'     => $x_passphrase,
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
        if (!is_null($vlan) && $vlan_enabled) $json['vlan'] = $vlan;
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/add/wlanconf', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Update wlan settings, base (using REST)
     * ---------------------------------------
     * return true on success
     * required parameter <wlan_id>
     * required parameter <wlan_settings> = stdClass object or associative array containing the configuration to apply to the wlan, must be a
     *                                      (partial) object/array structured in the same manner as is returned by list_wlanconf() for the wlan.
     */
    public function set_wlansettings_base($wlan_id, $wlan_settings)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json               = json_encode($wlan_settings);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/wlanconf/'.trim($wlan_id), 'json='.$json);
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
        $payload = (object)[];
        if (!is_null($x_passphrase)) $payload->x_passphrase = trim($x_passphrase);
        if (!is_null($name)) $payload->name = trim($name);
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
        $payload          = (object)[];
        $action           = ($disable) ? false : true;
        $payload->enabled = (bool)$action;
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
        if (!$this->is_loggedin) return false;
        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/wlanconf/'.trim($wlan_id));
        return $this->process_response_boolean($response);
    }

    /**
     * Update MAC filter for a wlan
     * ----------------------------
     * return true on success
     * required parameter <wlan_id>
     * required parameter <mac_filter_policy>  = string, "allow" or "deny"; default MAC policy to apply
     * required parameter <mac_filter_enabled> = boolean; true enables the policy, false disables it
     * required parameter <macs>               = array; must contain valid MAC strings to be placed in the MAC filter list,
     *                                           replacing existing values. Existing MAC filter list can be obtained
     *                                           through list_wlanconf().
     */
    public function set_wlan_mac_filter($wlan_id, $mac_filter_policy, $mac_filter_enabled, array $macs)
    {
        if (!in_array($mac_filter_policy, ['allow', 'deny'])) return false;
        $payload                     = (object)[];
        $payload->mac_filter_enabled = (bool)$mac_filter_enabled;
        $payload->mac_filter_policy  = $mac_filter_policy;
        $payload->mac_filter_list    = $macs;
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
        if (!$this->is_loggedin) return false;
        $json     = ['_sort' => '-time', 'within' => intval($historyhours), 'type' => null, '_start' => intval($start), '_limit' => intval($limit)];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/event', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * List alarms
     * -----------
     * returns an array of known alarms
     */
    public function list_alarms()
    {
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/list/alarm');
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
        if (!$this->is_loggedin) return false;
        $url_suffix = ($archived === false) ? '?archived=false' : null;
        $response   = $this->exec_curl('/api/s/'.$this->site.'/cnt/alarm'.$url_suffix);
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
        if (!$this->is_loggedin) return false;
        $this->request_type = 'POST';
        $json               = json_encode(['cmd' => 'archive-all-alarms']);
        if (!is_null($alarm_id)) $json = json_encode(['_id' => $alarm_id, 'cmd' => 'archive-alarm']);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/cmd/evtmgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = ['mac' => $device_mac];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr/upgrade', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = ['url' => filter_var($firmware_url, FILTER_SANITIZE_URL), 'mac' => $device_mac];
        $json     = json_encode($json, JSON_UNESCAPED_SLASHES);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr/upgrade-external', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = ['cmd' => 'set-rollupgrade'];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
        return $this->process_response_boolean($response);
    }

    /**
     * Cancel rolling upgrade
     * ---------------------
     * return true on success
     */
    public function cancel_rolling_upgrade()
    {
        if (!$this->is_loggedin) return false;
        $json     = ['cmd' => 'unset-rollupgrade'];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = ['mac' => $switch_mac, 'port_idx' => intval($port_idx), 'cmd' => 'power-cycle'];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $json     = ['cmd' => 'spectrum-scan', 'mac' => $ap_mac];
        $json     = json_encode($json);
        $response = $this->exec_curl('/api/s/'.$this->site.'/cmd/devmgr', 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/stat/spectrum-scan/'.trim($ap_mac));
        return $this->process_response($response);
    }

    /**
     * Update device settings, base (using REST)
     * -----------------------------------------
     * return true on success
     * required parameter <device_id>       = 24 char string; _id of the device which can be found with the list_devices() function
     * required parameter <device_settings> = stdClass object or associative array containing the configuration to apply to the device, must be a
     *                                        (partial) object/array structured in the same manner as is returned by list_devices() for the device.
     */
    public function set_device_settings_base($device_id, $device_settings)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json               = json_encode($device_settings);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/device/'.trim($device_id), 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/radiusprofile');
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
        if (!$this->is_loggedin) return false;
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/account');
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
        if (!$this->is_loggedin) return false;
        $tunnel_types        = [1,2,3,4,5,6,7,8,9,10,11,12,13];
        $tunnel_medium_types = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15];
        if (!in_array($tunnel_type, $tunnel_types) || !in_array($tunnel_medium_type, $tunnel_medium_types)) return false;
        $this->request_type  = 'POST';
        $account_details     = [
            'name'               => $name,
            'x_password'         => $x_password,
            'tunnel_type'        => (int) $tunnel_type,
            'tunnel_medium_type' => (int) $tunnel_medium_type
        ];
        if (isset($vlan)) $account_details['vlan'] = (int) $vlan;
        $json     = json_encode($account_details);
        $response = $this->exec_curl('/api/s/'.$this->site.'/rest/account', 'json='.$json);
        return $this->process_response($response);
    }

    /**
     * Update Radius account, base (using REST)
     * ----------------------------------------
     * return true on success
     * required parameter <account_id>      = 24 char string; _id of the account which can be found with the list_radius_accounts() function
     * required parameter <account_details> = stdClass object or associative array containing the new profile to apply to the account, must be a (partial)
     *
     *                                        object/array structured in the same manner as is returned by list_radius_accounts() for the account.
     *
     * NOTES:
     * - this function/method is only supported on controller versions 5.5.19 and later
     */
    public function set_radius_account_base($account_id, $account_details)
    {
        if (!$this->is_loggedin) return false;
        $this->request_type = 'PUT';
        $json               = json_encode($account_details);
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/account/'.trim($account_id), 'json='.$json);
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
        if (!$this->is_loggedin) return false;
        $this->request_type = 'DELETE';
        $response           = $this->exec_curl('/api/s/'.$this->site.'/rest/account/'.trim($account_id));
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

    /****************************************************************
     * Internal (private) functions from here:
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
                if (is_array($response->data)) return $response->data;
                return true;
            } elseif ($response->meta->rc === 'error') {
                /**
                 * we have an error:
                 * set $this->set last_error_message if the returned error message is available
                 */
                if (isset($response->meta->msg)) $this->last_error_message = $response->meta->msg;
                if ($this->debug) trigger_error('Debug: Last error message: '.$this->last_error_message);
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
                if (isset($response->meta->msg)) $this->last_error_message = $response->meta->msg;
                if ($this->debug) trigger_error('Debug: Last error message: '.$this->last_error_message);
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
                    $error = 'Invalid or malformed JSON.';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error = 'Control character error, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error = 'Syntax error, malformed JSON.';
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
                    $error = 'Unknown JSON error occured.';
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
     * Check the submitted base URL
     */
    private function check_base_url()
    {
        $url_valid = filter_var($this->baseurl, FILTER_VALIDATE_URL);
        if (!$url_valid) {
            trigger_error('The URL provided is incomplete or invalid!');
            return false;
        }

        $base_url_components = parse_url($this->baseurl);
        if (empty($base_url_components['port'])) {
            trigger_error('The URL provided does not have a port suffix, normally this is :8443');
            return false;
        }

        return true;
    }

    /**
     * Check the (short) site name
     */
    private function check_site($site)
    {
        if ($this->debug && strlen($site) !== 8 && $site !== 'default') {
            error_log('The provided (short) site name is probably incorrect');
        }
    }

    /**
     * Update the unificookie
     */
    private function update_unificookie()
    {
        if (isset($_SESSION['unificookie'])) $this->cookies = $_SESSION['unificookie'];
    }

    /**
     * Execute the cURL request
     */
    protected function exec_curl($path, $data = '')
    {
        $url = $this->baseurl.$path;

        $ch  = $this->get_curl_obj();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if (trim($data) != '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            if ($this->request_type === 'PUT') {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Content-Length: '.strlen($data)]);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        } else {
            curl_setopt($ch, CURLOPT_POST, false);
            if ($this->request_type === 'DELETE') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        /**
         * execute the cURL request
         */
        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            trigger_error('cURL error: '.curl_error($ch));
        }

        /**
         * has the session timed out?
         */
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $json_decoded_content = json_decode($content, true);

        if ($http_code == 401 && isset($json_decoded_content['meta']['msg']) && $json_decoded_content['meta']['msg'] === 'api.err.LoginRequired') {
            if ($this->debug) error_log('cURL debug: Needed to reconnect to UniFi Controller');

            /**
             * explicitly unset the old cookie now
             */
            if (isset($_SESSION['unificookie'])) {
                unset($_SESSION['unificookie']);
                $no_cookie_in_use = 1;
            }

            $this->login();

            /**
             * when login was okay, exec the same command again
             */
            if ($this->is_loggedin) {
                curl_close($ch);

                /**
                 * setup the cookie for the user within $_SESSION
                 */
                if (isset($no_cookie_in_use) && session_status() != PHP_SESSION_DISABLED) {
                    $_SESSION['unificookie'] = $this->cookies;
                    unset($no_cookie_in_use);
                }

                return $this->exec_curl($path, $data);
            }
        }

        if ($this->debug) {
            print '<pre>';
            print PHP_EOL.'---------cURL INFO-----------'.PHP_EOL;
            print_r (curl_getinfo($ch));
            print PHP_EOL.'-------URL & PAYLOAD---------'.PHP_EOL;
            print $url.PHP_EOL;
            print $data;
            print PHP_EOL.'----------RESPONSE-----------'.PHP_EOL;
            print $content;
            print PHP_EOL.'-----------------------------'.PHP_EOL;
            print '</pre>';
        }

        curl_close($ch);

        /**
         * set request_type value back to default, just in case
         */
        $this->request_type = 'POST';

        return $content;
    }

    /**
     * Get the cURL object
     */
    private function get_curl_obj()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->curl_ssl_verify_peer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->curl_ssl_verify_host);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);

        if ($this->debug) curl_setopt($ch, CURLOPT_VERBOSE, true);

        if ($this->cookies != '') {
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);
        }

        return $ch;
    }
}
