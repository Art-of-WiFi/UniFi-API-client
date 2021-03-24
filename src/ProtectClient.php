<?php
namespace UniFi_API;

class ProtectClient extends Client {
    /**
     * Protect endpoint
     * @var string
     */
    protected $unifi_os_endpoint  = '/proxy/protect';

    /**
     * Get all cameras.
     *
     * @return array|bool
     */
    public function getCameras()
    {
        return $this->fetch_results('/api/cameras');
    }

    /**
     * Get camera data by id.
     *
     * @param string $cameraId
     * @return array|bool
     */
    public function getCamera($cameraId)
    {
        return $this->fetch_results('/api/cameras/' . $cameraId);
    }

    /**
     * Get current snapshot for camera.
     *
     * @param string $cameraId
     * @return string
     */
    public function getCurrentCameraSnapshotUrl($cameraId)
    {
        return $this->baseurl . $this->unifi_os_endpoint . '/api/cameras/' . $cameraId . '/snapshot';
    }

    /**
     * @param string $path Path where to save the file.
     * @param string $cameraId
     * @return bool
     */
    public function downloadCurrentCameraSnapshot($path, $cameraId)
    {
        return $this->downloadFile($this->getCurrentCameraSnapshotUrl($cameraId), $path);
    }

    /**
     * Get protect events.
     *
     * @param int $start Start date as timestamp
     * @param int $end  End date as timestamp
     * @param int $limit
     * @return array|bool
     */
    public function getEvents($start, $end, $limit = null)
    {
        $parameters = '?start=' . $start . '000'.  '&end=' . $end . '999';

        if ($limit !== null) {
            $parameters .= '&limit=' . $limit;
        }

        return $this->fetch_results('/api/events' . $parameters);
    }

    /**
     * Get URL to download video.
     *
     * @param string $cameraId
     * @param int $start Start date as timestamp with microseconds (time(). '000')
     * @param int $end End date as timestamp with microseconds (time(). '999')
     * @param int $channel optional defaults to 0
     * @return string
     */
    public function getVideoDownloadUrl($cameraId, $start, $end, $channel = 0)
    {
        return $this->baseurl . $this->unifi_os_endpoint .  '/api/video/export?camera=' . $cameraId . '&start=' . $start .'&end=' . $end . '&channel=' . $channel;
    }

    /**
     * Download video to given path.
     *
     * @param string $path Path where to save the file.
     * @param string $cameraId
     * @param int $start Start date as timestamp with microseconds (time(). '000')
     * @param int $end End date as timestamp with microseconds (time(). '999')
     * @param int $channel
     * @return bool
     */
    public function downloadVideo($path, $cameraId, $start, $end, $channel = 0)
    {
        $url = $this->getVideoDownloadUrl($cameraId, $start, $end, $channel);

        return $this->downloadFile($url, $path);
    }

    /**
     * Generates thumbnail url for event.
     *
     * @param string $eventId Event Id
     * @return string
     */
    public function getEventThumbnailUrl($eventId)
    {
        return $this->baseurl . $this->unifi_os_endpoint . '/api/events/' . $eventId . '/thumbnail';
    }

    /**
     * @param string $path Path where to save the file.
     * @param string $eventId
     * @return bool
     */
    public function downloadEventThumbnail($path, $eventId)
    {
        $url = $this->getEventThumbnailUrl($eventId);

        return $this->downloadFile($url, $path);
    }

    /**
     * @param string $url
     * @param string $path
     * @return bool
     */
    protected function downloadFile($url, $path)
    {
        /**
         * guard clause to check if logged in when needed
         */
        if (!$this->is_loggedin) {
            return false;
        }

        if (!($ch = $this->get_curl_resource())) {
            \trigger_error('get_curl_resource() did not return a resource');

            return false;
        }

        $fp = \fopen($path, 'wb');

        \curl_setopt($ch, \CURLOPT_URL, $url);
        \curl_setopt($ch, \CURLOPT_FILE, $fp);
        $response = curl_exec($ch);
        if (\curl_errno($ch)) {
            \trigger_error('cURL error: ' . \curl_error($ch));
        }
        /**
         * fetch the HTTP response code
         */
        $http_code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /**
         * an HTTP response code 401 (Unauthorized) indicates the Cookie/Token has expired in which case
         * re-login is required
         */
        if ($http_code === 401) {
            if ($this->debug) {
                \error_log(__FUNCTION__ . ': needed to reconnect to UniFi controller');
            }

            if ($this->exec_retries === 0) {
                /**
                 * explicitly clear the expired Cookie/Token, update other properties and log out before logging in again
                 */
                if (isset($_SESSION['unificookie'])) {
                    $_SESSION['unificookie'] = '';
                }

                $this->is_loggedin = false;
                $this->cookies     = '';
                $this->exec_retries++;
                \curl_close($ch);

                /**
                 * then login again
                 */
                $this->login();

                /**
                 * when re-login was successful, simply execute the same cURL request again
                 */
                if ($this->is_loggedin) {
                    if ($this->debug) {
                        \error_log(__FUNCTION__ . ': re-logged in, calling exec_curl again');
                    }

                    return $this->downloadFile($url, $path);
                }

                if ($this->debug) {
                    \error_log(__FUNCTION__ . ': re-login failed');
                }
            }

            return false;
        }

        if ($this->debug) {
            print PHP_EOL . '<pre>';
            print PHP_EOL . '---------cURL INFO-----------' . PHP_EOL;
            \print_r(\curl_getinfo($ch));
            print PHP_EOL . '-------URL & PAYLOAD---------' . PHP_EOL;
            print $url . PHP_EOL;

            print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
            print $response;
            print PHP_EOL . '-----------------------------' . PHP_EOL;
            print '</pre>' . PHP_EOL;
        }

        \curl_close($ch);
        \fclose($fp);

        return true;
    }

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
         * guard clause to check if logged in when needed
         */
        if ($login_required && !$this->is_loggedin) {
            return false;
        }

        $this->last_results_raw = $this->exec_curl($path, $payload);

        if (\is_string($this->last_results_raw)) {
            $response = \json_decode($this->last_results_raw, false);
            $this->catch_json_last_error();

            return $response;
        }

        return false;
    }
}