<?php
$env = parse_ini_file('.env', false, INI_SCANNER_RAW);

if ($env === false) {
    die('.env dosyası okunamadı');
}

define('WHOIS_API_ENDPOINT', $env['WHOIS_API_ENDPOINT'] ?? 'https://instant.who.sb/api/v1/check');
define('GODADDY_AFFILIATE_URL', $env['GODADDY_AFFILIATE_URL'] ?? '');
define('NAMECHEAP_AFFILIATE_URL', $env['NAMECHEAP_AFFILIATE_URL'] ?? '');
define('GOOGLE_DOMAINS_AFFILIATE_URL', $env['GOOGLE_DOMAINS_AFFILIATE_URL'] ?? '');
define('DEFAULT_COM_PRICE', $env['DEFAULT_COM_PRICE'] ?? 11.99);
define('DEFAULT_NET_PRICE', $env['DEFAULT_NET_PRICE'] ?? 10.99);
define('DEFAULT_ORG_PRICE', $env['DEFAULT_ORG_PRICE'] ?? 12.99);

error_reporting(E_ALL);
ini_set('display_errors', 1); 