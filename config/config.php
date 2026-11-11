<?php

/**
 * think-ip 配置文件
 *
 * 数据库文件不随包分发，部署时请将 IP2Region xdb（或纯真库）放到下列路径，
 * 或填写为服务器上的绝对路径。相对路径相对于本包根目录。
 *
 * @package think
 */

return [
    // 默认数据源：ip2region（默认）/ qqwry（纯真回退）
    'source' => 'ip2region',

    // IP2Region xdb 离线库路径（IPv4 / IPv6）
    'ip2region' => [
        // 留空时使用包内约定路径 src/libs/ip2region_v4.xdb
        'v4' => '',
        // 留空时使用包内约定路径 src/libs/ip2region_v6.xdb
        'v6' => '',
    ],

    // 纯真 IP 库路径（可选回退数据源，需自备 qqwry.dat / ipv6wry.db）
    'qqwry' => [
        'v4' => '',
        'v6' => '',
    ],
];
