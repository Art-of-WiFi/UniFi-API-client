<?php
/**
 * template settings file for site provisioning example script
 *
 * contributed by: @smos
 */

// Extract shop IP addressing from numeric shop number, you could use something else, static or using a database lookup.
$octet1 = 10;
if(strlen($filnr) == 3) {
    $octet2 = floatval(substr($filnr,0,1));
    $octet3 = floatval(substr($filnr,1,2));
} else {
    $octet2 = floatval(substr($filnr,0,2));
    $octet3 = floatval(substr($filnr,2,2));
}

// Wired networks
$wirednetworks['LAN'] = array(
    'dhcpd_enabled' => true,
    'dhcpd_start' => "{$octet1}.{$octet2}.{$octet3}.100",
    'dhcpd_stop' => "{$octet1}.{$octet2}.{$octet3}.150",
    'domain_name' => 'foo.bar.nl',
    'dhcpd_dns_1' => '10.56.154.13',
    'dhcpd_dns_2' => '10.34.234.66',
    'dhcpd_ip_1' => "{$octet1}.{$octet2}.{$octet3}.254",
    'dhcpguard_enabled' => true,
    'ip_subnet' => "{$octet1}.{$octet2}.{$octet3}.254/24",
    'is_nat' => true,
    'name' => 'LAN',
    'networkgroup' => 'LAN',
    'purpose' => 'corporate',
    'site_id' => $shasite_id,
    'vlan_enabled' => false,
 );
$wirednetworks['shop-wifi'] = array(
    'enabled' => true,
    'is_nat' => true,
    'dhcpd_ip_1' => '192.168.2.254',
    'dhcpguard_enabled' => true,
    'name' => 'shop-wifi',
    'purpose' => 'vlan-only',
    'site_id' => $shasite_id,
    'vlan_enabled' => true,
    'vlan' => 10,
);
// Wireless networks
$wlannetworks["UBNT-{$filnr}"] = array(
    'enabled' => true,
    'is_guest' => true,
    'mac_filter_enabled' => false,
    'mac_filter_list' => array (),
    'mac_filter_policy' => 'allow',
    'name' => "UBNT-{$filnr}",
    'usergroup_id' => $shausergroup_id,
    'wlangroup_id' => $shawlangroup_id,
    'schedule' =>
    array (
         0 => 'mon|0800-1800',
         1 => 'tue|0800-1800',
         2 => 'wed|0800-1800',
         3 => 'thu|0800-1800',
         4 => 'fri|0800-1800',
         5 => 'sat|0800-1800',
         6 => 'sun|0800-1800',
        ),
    'schedule_enabled' => true,
    'security' => 'wpapsk',
    'site_id' => $shasite_id,
    'vlan' => '10',
    'vlan_enabled' => true,
    'wep_idx' => 1,
    'wpa_enc' => 'ccmp',
    'wpa_mode' => 'wpa2',
    'x_passphrase' => 'datisgeheim',
);
$wirednetworks['IPSEC-VPN'] = array(
	'enabled' => true,
	'ifname' => 'vti64',
	'ipsec_dh_group' => 5,
	'ipsec_dynamic_routing' => false,
	'ipsec_encryption' => 'aes128',
	'ipsec_hash' => 'sha1',
	'ipsec_key_exchange' => 'ikev1',
	'ipsec_local_ip' => gethostbyname($dyndns), // IP
	'ipsec_peer_ip' => '1.2.3.4', // Remote IP
	'ipsec_pfs' => true,
	'ipsec_profile' => 'customized',
	'is_nat' => false,
	'name' => 'IPSEC-VPN',
	'purpose' => 'site-vpn',
	'remote_vpn_subnets' => 
		array (
		  0 => '10.10.10.0/22',
		),
	'route_distance' => 30,
	'vpn_type' => 'ipsec-vpn',
	'x_ipsec_pre_shared_key' => "Super_Secret_PSK_{$filnr}",
	'site_id' => $shasite_id,
 );
$wlannetworks['CorporateWifi'] = array(
    'enabled' => true,
    'is_guest' => false,
    'mac_filter_enabled' => false,
    'mac_filter_list' => array (),
    'mac_filter_policy' => 'allow',
    'name' => "CorporateWifi",
    'usergroup_id' => $shausergroup_id,
    'wlangroup_id' => $shawlangroup_id,
    'schedule' =>
    array (
         0 => 'mon|0800-1800',
         1 => 'tue|0800-1800',
         2 => 'wed|0800-1800',
         3 => 'thu|0800-1800',
         4 => 'fri|0800-1800',
         5 => 'sat|0800-1800',
         6 => 'sun|0800-1800',
        ),
    'schedule_enabled' => true,
    'security' => 'wpapsk',
    'site_id' => $shasite_id,
    'wep_idx' => 1,
    'wpa_enc' => 'ccmp',
    'wpa_mode' => 'wpa2',
    'x_passphrase' => 'SuperSecretPassword',
);

// Unset this network for test shops
if(preg_match("/[0-9][9][0-9]+)/si", $filnr))
    unset($wlannetworks['CorporateWifi']);

// Site settings template
$sitesettings['connectivity'] = array(
    'enabled' => true,
    'key' => 'connectivity',
    'site_id' => $shasite_id,
    'uplink_type' => 'gateway',
);
$sitesettings['guest_access'] = array(
    'auth' => 'none',
    'key' => 'guest_access',
    'redirect_https' => true,
    'redirect_to_https' => false,
    'restricted_subnet_1' => '192.168.0.0/16',
    'restricted_subnet_2' => '172.16.0.0/12',
    'restricted_subnet_3' => '10.0.0.0/8',
    'site_id' => $shasite_id,
);
$sitesettings['country'] = array(
    'code' => '528',
    'key' => 'country',
    'site_id' => $shasite_id,
);
$sitesettings['locale'] = array(
    'key' => 'locale',
    'site_id' => $shasite_id,
    'timezone' => 'Europe/Amsterdam',
);
$sitesettings['porta'] = array(
    'key' => 'porta',
    'site_id' => $shasite_id,
    'ugw3_wan2_enabled' => false,
);
$sitesettings['snmp'] = array(
    'community' => 'esenempee',
    'key' => 'snmp',
    'site_id' => $shasite_id,
);
$sitesettings['rsyslogd'] = array(
    'key' => 'rsyslogd',
    'port' => '514',
    'site_id' => $shasite_id,
);/*
$sitesettings['auto_speedtest'] = array(
    'enabled' => false,
    'interval' => 20,
    'key' => 'auto_speedtest',
    'site_id' => $shasite_id,
);*/
$sitesettings['usg'] = array(
	'broadcast_ping' => false,
	'firewall_guest_default_log' => false,
	'firewall_lan_default_log' => false,
	'firewall_wan_default_log' => false,
	'ftp_module' => true,
	'gre_module' => true,
	'h323_module' => true,
	'icmp_timeout' => 30,
	'key' => 'usg',
	'mdns_enabled' => false,
	'mss_clamp' => 'auto',
	'offload_accounting' => true,
	'offload_l2_blocking' => true,
	'offload_sch' => true,
	'other_timeout' => 600,
	'pptp_module' => true,
	'receive_redirects' => false,
	'send_redirects' => true,
	'sip_module' => true,
	'syn_cookies' => true,
	'tcp_close_timeout' => 10,
	'tcp_close_wait_timeout' => 60,
	'tcp_established_timeout' => 7440,
	'tcp_fin_wait_timeout' => 120,
	'tcp_last_ack_timeout' => 30,
	'tcp_syn_recv_timeout' => 60,
	'tcp_syn_sent_timeout' => 120,
	'tcp_time_wait_timeout' => 120,
	'tftp_module' => true,
	'udp_other_timeout' => 30,
	'udp_stream_timeout' => 180,
	'upnp_enabled' => false,
	'upnp_nat_pmp_enabled' => true,
	'upnp_secure_mode' => true,
	'site_id' => $shasite_id,
);
$sitesettings['ntp'] = array(
    'key' => 'ntp',
    'ntp_server_1' => 'ntp.xs4all.nl',
    'ntp_server_2' => '0.ubnt.pool.ntp.org',
    'site_id' => $shasite_id,
);
/*
$sitesettings['usg'] = array(
    'broadcast_ping' => false,
    'ftp_module' => true,
    'gre_module' => true,
    'h323_module' => true,
    'key' => 'usg',
    'mdns_enabled' => false,
    'mss_clamp' => 'auto',
    'offload_accounting' => true,
    'offload_l2_blocking' => true,
    'offload_sch' => true,
    'pptp_module' => true,
    'receive_redirects' => false,
    'send_redirects' => true,
    'sip_module' => true,
    'site_id' => $shasite_id,
    'syn_cookies' => true,
    'tftp_module' => true,
    'upnp_enabled' => false,
    'upnp_nat_pmp_enabled' => true,
    'upnp_secure_mode' => true,
);*/
$sitesettings['mgmt'] = array(
    'advanced_feature_enabled' => false,
    'alert_enabled' => true,
    'auto_upgrade' => true,
    'key' => 'mgmt',
    'led_enabled' => true,
    'site_id' => $shasite_id,
    'unifi_idp_enabled' => true,
    'x_ssh_auth_password_enabled' => true,
    'x_ssh_bind_wildcard' => false,
    'x_ssh_enabled' => true,
);
$fwgroups['Site6'] = array(
	'group_members' => 
	array (
		0 => '2001:db8:1::/64',
		1 => '2a01:db8:2::1',
	),
	'group_type' => 'ipv6-address-group',
	'name' => 'Site6',
	'site_id' => $shasite_id,
);
$fwgroups['Site4'] = array(
	'group_members' => 
		array (
			0 => '1.2.3.4/27',
			1 => '10.0.0.0/22',
			2 => '5.6.7.8',
		),
	'group_type' => 'address-group',
	'name' => 'Site4',
	'site_id' => $shasite_id,
);
$fwgroups['ManagementPorts'] = array(
   'group_members' => 
		array (
			0 => '22',
			1 => '80',
			2 => '443'
		),
	'group_type' => 'port-group',
	'name' => 'ManagementPorts',
	'site_id' => $shasite_id,
);
$fwrules['WanICMP4'] = array (
	'action' => 'accept',
	'dst_address' => '',
	'dst_firewallgroup_ids' => 
	array (
		),
	'dst_networkconf_id' => '',
	'dst_networkconf_type' => 'NETv4',
	'rule_index' => '2000',
	'enabled' => true,
	'icmp_typename' => '',
	'ipsec' => '',
	'logging' => false,
	'name' => 'WanICMP4',
	'protocol' => 'icmp',
	'protocol_match_excepted' => false,
	'ruleset' => 'WAN_LOCAL',
	'src_address' => '',
	'src_firewallgroup_ids' => 
	array (
		0 => $fwgroup_id['Site4'],
	),
	'src_mac_address' => '',
	'src_networkconf_id' => '',
	'src_networkconf_type' => 'NETv4',
	'state_established' => true,
	'state_invalid' => false,
	'state_new' => true,
	'state_related' => true,
);
$fwrules['WanICMP6'] = array (
	'action' => 'accept',
	'dst_address' => '',
	'dst_firewallgroup_ids' => 
	array (
		),
	'dst_networkconf_id' => '',
	'dst_networkconf_type' => 'NETv4',
	'rule_index' => '2000',
	'enabled' => true,
	'icmpv6_typename' => '',
	'logging' => false,
	'name' => 'WanICMP6',
	'protocol_v6' => 'icmpv6',
	'protocol_match_excepted' => false,
	'ruleset' => 'WANv6_LOCAL',
	'src_firewallgroup_ids' => 
	array (
		0 => $fwgroup_id['Site6'],
	),
	'src_mac_address' => '',
	'state_established' => true,
	'state_invalid' => false,
	'state_new' => true,
	'state_related' => true,
);
$switchconfig[0] = array (
	'site_id' => $shasite_id,
	'name' => "Switch-US24-{$filnr}",
	'config_network' => array(
		'dns1' => '1.2.3.4',
		'dns2' => '5.6.7.8',
		'dnssuffix' => 'ad.site.nl',
		'gateway' => "{$octet1}.{$octet2}.{$octet3}.254",
		'ip' => "{$octet1}.{$octet2}.{$octet3}.5",
		'netmask' => '255.255.255.0',
		'type' => 'dhcp',
	)
);
$switchconfig[1] = array (
	'site_id' => $shasite_id,
	'name' => "Switch-US8-{$filnr}",
	'config_network' => array(
		'dns1' => '1.2.3.4.',
		'dns2' => '5.6.7.8',
		'dnssuffix' => 'ad.site.nl',
		'gateway' => "{$octet1}.{$octet2}.{$octet3}.254",
		'ip' => "{$octet1}.{$octet2}.{$octet3}.6",
		'netmask' => '255.255.255.0',
		'type' => 'dhcp'
	),
	'port_overrides' => array (
		0 => array (
			'port_idx' => 8,
			'poe_mode' => 'passthrough',
			'lldpmed_notify_enabled' => true,
		)
	)
);
$ugwconfig[0] = array (
	'site_id' => $shasite_id,
	'name' => "Router-{$filnr}",
	'ethernet_overrides' => array (
		0 => array(
		 'ifname' => 'eth0',
		 'networkgroup' => 'WAN',
		),
		1 => array(
		 'ifname' => 'eth1',
		 'networkgroup' => 'LAN',
		),
		2 => array(
		 'ifname' => 'eth2',
		 'networkgroup' => 'WAN2',
		)
	)
);


