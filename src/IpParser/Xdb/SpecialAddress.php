<?php

declare(strict_types=1);

namespace think\IpParser\Xdb;

// Copyright 2022 The Ip2Region Authors. All rights reserved.
// Use of this source code is governed by a Apache-2.0-style
// license that can be found in the LICENSE file.
//
// 特殊用途地址段表与 CIDR 判断移植自官方 PHP 绑定的 simple() 实现。

/**
 * 特殊用途地址识别（回环 / 私网 / 链路本地 / 文档测试 / 组播 / 保留等）
 *
 * @package think\IpParser\Xdb
 */
class SpecialAddress
{
    /**
     * 返回特殊用途地址的友好名称，普通公网地址返回 null
     *
     * @param string $ip 人类可读 IP 字符串
     * @return string|null
     */
    public static function getName(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        $ranges = strlen($packed) === 4 ? self::ipv4Ranges() : self::ipv6Ranges();
        foreach ($ranges as $range) {
            if (self::inCidr($packed, $range[0])) {
                return $range[1];
            }
        }

        return null;
    }

    /**
     * IPv4 特殊用途地址段（更具体的段优先排序）
     *
     * @return array<int, array{0:string,1:string}>
     */
    private static function ipv4Ranges(): array
    {
        static $ranges = null;
        if ($ranges !== null) {
            return $ranges;
        }

        $ranges = array(
            array('0.0.0.0/32', '未指定地址'),
            array('255.255.255.255/32', '受限广播地址'),
            array('0.0.0.0/8', '本网络地址'),
            array('10.0.0.0/8', '私网地址'),
            array('100.64.0.0/10', '共享地址（运营商级 NAT）'),
            array('127.0.0.0/8', '回环地址'),
            array('169.254.0.0/16', '链路本地地址'),
            array('172.16.0.0/12', '私网地址'),
            array('192.0.0.8/32', 'IPv4 虚拟地址'),
            array('192.0.0.9/32', 'PCP 任播地址'),
            array('192.0.0.10/32', 'TURN 中继任播地址'),
            array('192.0.0.170/32', 'NAT64/DNS64 发现地址'),
            array('192.0.0.171/32', 'NAT64/DNS64 发现地址'),
            array('192.0.0.0/29', 'IPv4 服务连续性前缀'),
            array('192.0.0.0/24', 'IETF 协议分配地址'),
            array('192.0.2.0/24', '文档测试地址'),
            array('192.31.196.0/24', 'AS112 地址'),
            array('192.52.193.0/24', 'AMT 地址'),
            array('192.88.99.2/32', '6a44 中继任播地址'),
            array('192.88.99.0/24', '6to4 中继任播地址（已废弃）'),
            array('192.168.0.0/16', '私网地址'),
            array('192.175.48.0/24', 'AS112 地址'),
            array('198.18.0.0/15', '基准测试地址'),
            array('198.51.100.0/24', '文档测试地址'),
            array('203.0.113.0/24', '文档测试地址'),
            array('224.0.0.0/4', '组播地址'),
            array('240.0.0.0/4', '保留地址'),
        );

        return $ranges;
    }

    /**
     * IPv6 特殊用途地址段（更具体的段优先排序）
     *
     * @return array<int, array{0:string,1:string}>
     */
    private static function ipv6Ranges(): array
    {
        static $ranges = null;
        if ($ranges !== null) {
            return $ranges;
        }

        $ranges = array(
            array('::/128', '未指定地址'),
            array('::1/128', '回环地址'),
            array('::ffff:0:0/96', 'IPv4 映射地址'),
            array('64:ff9b::/96', 'IPv4/IPv6 转换地址'),
            array('64:ff9b:1::/48', 'IPv4/IPv6 转换地址'),
            array('100::/64', '丢弃前缀地址'),
            array('100:0:0:1::/64', '虚拟 IPv6 前缀'),
            array('2001::/32', 'Teredo 地址'),
            array('2001:1::1/128', 'PCP 任播地址'),
            array('2001:1::2/128', 'TURN 中继任播地址'),
            array('2001:1::3/128', 'DNS-SD SRP 任播地址'),
            array('2001:2::/48', '基准测试地址'),
            array('2001:3::/32', 'AMT 地址'),
            array('2001:4:112::/48', 'AS112 地址'),
            array('2001:10::/28', 'ORCHID 地址（已废弃）'),
            array('2001:20::/28', 'ORCHIDv2 地址'),
            array('2001:30::/28', 'DET 地址'),
            array('2001:db8::/32', '文档测试地址'),
            array('2002::/16', '6to4 地址'),
            array('2620:4f:8000::/48', 'AS112 地址'),
            array('3fff::/20', '文档测试地址'),
            array('5f00::/16', 'SRv6 SID 地址'),
            array('fc00::/7', '唯一本地地址（私网地址）'),
            array('fe80::/10', '链路本地地址'),
            array('ff00::/8', '组播地址'),
            array('2001::/23', 'IETF 协议分配地址'),
        );

        return $ranges;
    }

    /**
     * 判断打包二进制 IP 是否落在 CIDR 段内（字节比较，兼容 v4/v6 与 32 位环境）
     *
     * @param string $packedIp inet_pton 返回的二进制 IP
     * @param string $cidr 网段，如 10.0.0.0/8
     * @return bool
     */
    private static function inCidr(string $packedIp, string $cidr): bool
    {
        list($network, $prefixLength) = explode('/', $cidr, 2);
        $packedNetwork = @inet_pton($network);
        if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packedIp)) {
            return false;
        }

        $prefixLength = (int)$prefixLength;
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }
}
