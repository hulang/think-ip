<?php
/**
 * @author Zhao Binyan <itbudaoweng@gmail.com>
 * @since  2015-06-11
 */

//you do not need to do this if use composer!
//require dirname(__DIR__) . '/src/IpParser/IpParserInterface.php';
//
//require dirname(__DIR__) . '/src/IpLocation.php';
//require dirname(__DIR__) . '/src/IpParser/QQwry.php';
//require dirname(__DIR__) . '/src/IpParser/IpV6wry.php';
//require dirname(__DIR__) . '/src/StringParser.php';

//需要 composer install 或者去掉上面注释，并注释这一行
include dirname(__DIR__) .'/vendor/autoload.php';

$input = getopt("i:", [], $id);

use think\Ip\IpLocation;

$ips = [
    "172.217.25.14",//美国
    "140.205.172.5",//杭州
    "123.125.115.110",//北京
    "221.196.0.0",//
    "60.195.153.98",

    //bug ip 都是涉及到直辖市的
    "218.193.183.35", //"province":"上海交通大学闵行校区",
    "210.74.2.227", //,"province":"北京工业大学","city":"",
    "162.105.217.0", //,"province":"北京大学万柳学区","ci

    "fe80:0000:0001:0000:0440:44ff:1233:5678",
    "2409:8900:103f:14f:d7e:cd36:11af:be83",
    "2400:3200:baba::1",//阿里云



];

if (isset($input['i']) || isset($input['ip'])) {
    $ips = [];

    if (isset($input['i'])) {
        $ips[] = $input['i'];
    }

    if (isset($input['ip'])) {
        $ips[] = $input['ip'];
    }
}

foreach ($ips as $ip) {
    echo json_encode(IpLocation::getLocation($ip), JSON_UNESCAPED_UNICODE) . "\n";
}


