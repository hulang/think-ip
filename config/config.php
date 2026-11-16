<?php

/**
 * think-ip 配置文件
 *
 * 数据源策略：
 * - IPv4：可在 ip2region / qqwry（纯真）之间切换；
 * - IPv6：固定使用 IP2Region，不支持纯真 ipv6wry.db。
 *
 * 数据库文件不随包分发，部署时请将对应 xdb / 纯真库放到下列路径，
 * 或填写为服务器上的绝对路径；相对路径相对于本包根目录。
 *
 * @package think
 */

return [
    // IPv4 数据源：ip2region（默认）/ qqwry（纯真回退）；IPv6 固定 IP2Region
    'source' => [
        'v4' => 'ip2region',
    ],

    // IP2Region 离线库路径（IPv4 / IPv6），留空使用包内约定路径
    'ip2region' => [
        'v4' => '',
        'v6' => '',
    ],

    // 纯真 IP 库路径（仅 IPv4 可用；IPv6 不支持纯真库）
    'qqwry' => [
        'v4' => '',
    ],
];
