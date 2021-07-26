<?php
namespace UniFi_API;

class LEDClient extends Client {

    protected $unifi_os_endpoint = '/proxy/led';

    /**
     * Set LED Panel group to specified value
     *
     * @param  string            $gid    group id to turn off
     * @param  string            $value  0 = off; 1 = on
     * @return bool|array                controller response
     */
    protected function groupSet($gid, $value){
        $this->method = 'PUT';
        $payload = [
            'command'         => 'config-output',
            'value' => "$value",
        ];
        return $this->fetch_results('/v2/groups/'.$gid, $payload);
    }

    /**
     * Turn on LED Panel group specified
     *
     * @param  string            $gid    group id to turn off
     * @return bool|array                controller response
     */
    function groupOn($gid){
        return $this->groupSet($gid, "1");
    }

    /**
     * Turn off LED Panel group specified
     *
     * @param  string            $gid    group id to turn off
     * @return bool|array                controller response
     */
    function groupOff($gid){
        return $this->groupSet($gid, "0");
    }
}