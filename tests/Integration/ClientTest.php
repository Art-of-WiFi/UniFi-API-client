<?php

namespace UniFi_API\Tests\Integration;

use UniFi_API\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    protected static $client;

    public static function setUpBeforeClass(): void
    {
        self::$client = new Client('unifi', 'unifi');
        self::$client->add_default_admin();
    }

    public function testLogin()
    {
        $this->assertTrue(self::$client->login());
    }

    public function testStatSysinfo()
    {
        $stat_sysinfo = self::$client->stat_sysinfo();
        $this->assertCount(1, $stat_sysinfo);
        $this->assertObjectHasAttribute('timezone', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('autobackup', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('build', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('version', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('data_retention_days', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('data_retention_time_in_hours_for_5minutes_scale', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('data_retention_time_in_hours_for_hourly_scale', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('data_retention_time_in_hours_for_daily_scale', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('data_retention_time_in_hours_for_monthly_scale', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('data_retention_time_in_hours_for_others', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('update_available', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('update_downloaded', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('live_chat', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('store_enabled', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('hostname', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('name', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('ip_addrs', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('inform_port', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('https_port', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('override_inform_host', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('image_maps_use_google_engine', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('radius_disconnect_running', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('facebook_wifi_registered', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('sso_app_id', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('sso_app_sec', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('unsupported_device_count', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('unsupported_device_list', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('unifi_go_enabled', $stat_sysinfo[0]);
        $this->assertObjectHasAttribute('default_site_device_auth_password_alert', $stat_sysinfo[0]);

        if (\version_compare($stat_sysinfo[0]->version, '6') >= 0) {
            $this->assertObjectHasAttribute('uptime', $stat_sysinfo[0]);
            $this->assertObjectHasAttribute('anonymous_controller_id', $stat_sysinfo[0]);
            $this->assertObjectHasAttribute('has_webrtc_support', $stat_sysinfo[0]);
        }

        if (\version_compare($stat_sysinfo[0]->version, '7') >= 0) {
            $this->assertObjectHasAttribute('debug_setting_preference', $stat_sysinfo[0]);
            $this->assertObjectHasAttribute('debug_mgmt', $stat_sysinfo[0]);
            $this->assertObjectHasAttribute('debug_system', $stat_sysinfo[0]);
            $this->assertObjectHasAttribute('debug_device', $stat_sysinfo[0]);
            $this->assertObjectHasAttribute('debug_sdn', $stat_sysinfo[0]);
        }
    }

    public function testListCountryCodes()
    {
        $country_codes = self::$client->list_country_codes();
        $this->assertGreaterThanOrEqual(168, $country_codes);

        foreach ($country_codes as $country_code) {
            $this->assertObjectHasAttribute('code', $country_code);
            $this->assertObjectHasAttribute('name', $country_code);
            $this->assertObjectHasAttribute('key', $country_code);
        }
    }
}
