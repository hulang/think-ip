<?php

/**
 * 数据源按版本分离路由校验（不查询真实数据库，仅校验解析器选择）
 *
 * 策略：IPv4 可在 ip2region / qqwry 间切换；IPv6 固定 IP2Region，不使用纯真 IpV6wry。
 *
 * 用法：php test/route_check.php   退出码 0 全部通过，1 存在不符。
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use think\IpLocation;
use think\IpParser\QQwry;
use think\IpParser\IpV6wry;
use think\IpParser\Ip2Region;

$fail = 0;

function check(string $label, bool $ok): void
{
    global $fail;
    if ($ok) {
        echo "[PASS] {$label}\n";
    } else {
        echo "[FAIL] {$label}\n";
        $fail++;
    }
}

$ref = new ReflectionClass(IpLocation::class);

// 1. IPv4 选 qqwry -> QQwry 解析器
$m4 = $ref->getMethod('makeV4Parser');
$m4->setAccessible(true);
$v4Qq = $m4->invoke(null, 'qqwry', '/x/qqwry.dat');
check('IPv4 数据源 qqwry 选择 QQwry 解析器', $v4Qq instanceof QQwry);

// 2. IPv4 选 ip2region -> Ip2Region 解析器
$v4Xdb = $m4->invoke(null, 'ip2region', '/x/v4.xdb');
check('IPv4 数据源 ip2region 选择 Ip2Region 解析器', $v4Xdb instanceof Ip2Region);

// 3. IPv6 固定 Ip2Region，绝不是 IpV6wry
$m6 = $ref->getMethod('makeV6Parser');
$m6->setAccessible(true);
$v6 = $m6->invoke(null, '/x/v6.xdb');
check('IPv6 固定选择 Ip2Region 解析器', $v6 instanceof Ip2Region);
check('IPv6 不使用纯真 IpV6wry 解析器', !($v6 instanceof IpV6wry));

// 4. resolveV4Source 归一化与旧字符串兼容
$rs = $ref->getMethod('resolveV4Source');
$rs->setAccessible(true);
check("resolveV4Source('qqwry') = qqwry", $rs->invoke(null, 'qqwry') === 'qqwry');
check("resolveV4Source('ip2region') = ip2region", $rs->invoke(null, 'ip2region') === 'ip2region');

echo "\n路由校验共 " . (4 + 2) . " 项，失败 {$fail} 项。\n";
exit($fail > 0 ? 1 : 0);
