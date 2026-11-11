### IP数据库 IPV4 IPV6(解析为国家、省、市、县、运营商)

> **数据源声明**：本包内置 [IP2Region](https://github.com/lionsoul2014/ip2region) xdb(v3) 精简查询器（无第三方 PHP 扩展依赖），查询算法对齐官方 PHP 绑定；**数据文件不随包分发**，需自行下载后按配置放置。内置 xdb 数据由 IP2Region 社区汇总提供，归属地结果仅供参考；代码按 Apache-2.0 / MIT 开源，数据授权以 IP2Region 官方条款为准。

##### 环境

- php >= 7.2.5
- 依赖扩展：`ext-mbstring`、`ext-json`、`ext-iconv`
- 默认数据源为 IP2Region xdb 离线库（IPv4 / IPv6）
- 保留纯真 `qqwry.dat` / `ipv6wry.db` 解析器作为可选回退数据源

#### 安装

```
composer require hulang/think-ip
```

#### 数据库准备（数据自备，不随包提供）

1. 从 IP2Region 官方仓库 `data/` 目录下载 xdb 数据文件：
   - IPv4：`ip2region_v4.xdb`
   - IPv6：`ip2region_v6.xdb`
   - 下载地址：`https://github.com/lionsoul2014/ip2region/tree/master/data`
2. 将文件放到 `src/libs/`（包内约定路径），或在 `config/config.php` 中配置为服务器绝对路径。
3. 纯真回退库（可选）：自备 `qqwry.dat` / `ipv6wry.db` 并配置路径。

##### 配置

集中设置默认数据源与各库路径（留空使用包内 `src/libs/` 约定路径）。配置模板结构如下：

```php
return [
    'source' => 'ip2region',          // ip2region / qqwry
    'ip2region' => [
        'v4' => '',                   // 留空 = src/libs/ip2region_v4.xdb
        'v6' => '',                   // 留空 = src/libs/ip2region_v6.xdb
    ],
    'qqwry' => [
        'v4' => '',
        'v6' => '',
    ],
];
```

**ThinkPHP 项目**：本包 `composer.json` 通过 `extra.think.config` 声明了配置文件，安装后执行

```bash
php think vendor:publish
```

会把包内 `config/config.php` 复制为项目 `config/ip.php`，在该文件中修改路径即可（不要直接改 `vendor` 内的包文件）。已存在的 `config/ip.php` 默认不覆盖，需要强制刷新加 `--force`。

配置读取优先级：框架加载的 `ip` 配置（`config('ip')`）→ 包内 `config/config.php` → 内置默认值；非框架环境自动回落包内文件，不依赖 ThinkPHP 运行。


```php

//根据实际情况，基本上用框架(如 thinkphp,Laravel)的话不需要手动引入
//require 'vendor/autoload.php';

use think\IpLocation;

// 1. 默认查询（IP2Region 离线库，路径取配置）
echo json_encode(IpLocation::getLocation($ip), JSON_UNESCAPED_UNICODE) . PHP_EOL;

// 2. 单次调用传入数据库文件路径（依次为 IPv4、IPv6 绝对路径）
$v4Path = '/abspath/ip2region_v4.xdb';
$v6Path = '/abspath/ip2region_v6.xdb';
echo json_encode(IpLocation::getLocation($ip, $v4Path, $v6Path), JSON_UNESCAPED_UNICODE) . PHP_EOL;

// 3. 切换数据源：默认 ip2region，可切回纯真 qqwry（需自备 qqwry.dat / ipv6wry.db）
IpLocation::setSource('qqwry');
// 或单次调用指定数据源（第 4 个参数）
echo json_encode(IpLocation::getLocation($ip, '', '', 'qqwry'), JSON_UNESCAPED_UNICODE) . PHP_EOL;

```

#### 响应

获取成功
```json
{
    "code": 0,
    "error": "",
    "ip": "163.177.65.160",
    "country": "中国",
    "province": "广东省",
    "city": "深圳市",
    "county": "",
    "area": "中国广东省深圳市 联通",
    "isp": "联通"
}
```

异常（非法 IP / 数据库缺失等失败分支，`code=1`，仅返回 `ip / code / error`）
```json
{
    "ip": "not-an-ip",
    "code": 1,
    "error": "IP Invalid"
}
```

#### 测试用例

`test/` 目录提供标准化测试清单与基线结果：

- `test/samples.json`：测试 IP 清单（标准 JSON 数组，含分类与备注），覆盖国内 v4（联通/电信/移动/云厂商/公共 DNS/教育网）、境外 v4、国内 v6、境外 v6、回环/私网/链路本地等特殊地址、非法 IP。
- `test/expected.json`：当前数据库版本下的基线结果。
- `test/run.php`：测试脚本。

```bash
# 逐条运行并与基线比对，输出 PASS/FAIL（退出码 0 全部通过，1 存在不一致）
php test/run.php

# 数据库更新后，用当前结果重新采集基线
php test/run.php --update

# 额外打印完整 JSON 结果数组
php test/run.php --json
```

代表性实测结果（标准 JSON 数组，完整清单见 `test/expected.json`；结果随数据库版本变化，仅供格式参考）：

```json
[
    {
        "ip": "163.177.65.160",
        "category": "v4-cn-unicom",
        "result": {
            "code": 0,
            "error": "",
            "ip": "163.177.65.160",
            "country": "中国",
            "province": "广东省",
            "city": "深圳市",
            "county": "",
            "area": "中国广东省深圳市 联通",
            "isp": "联通"
        }
    },
    {
        "ip": "210.74.2.227",
        "category": "v4-cn-telecom",
        "result": {
            "code": 0,
            "error": "",
            "ip": "210.74.2.227",
            "country": "中国",
            "province": "北京市",
            "city": "北京市",
            "county": "",
            "area": "中国北京市北京市 电信",
            "isp": "电信"
        }
    },
    {
        "ip": "183.232.231.172",
        "category": "v4-cn-mobile",
        "result": {
            "code": 0,
            "error": "",
            "ip": "183.232.231.172",
            "country": "中国",
            "province": "广东省",
            "city": "广州市",
            "county": "",
            "area": "中国广东省广州市 移动",
            "isp": "移动"
        }
    },
    {
        "ip": "140.205.172.5",
        "category": "v4-cn-cloud",
        "result": {
            "code": 0,
            "error": "",
            "ip": "140.205.172.5",
            "country": "中国",
            "province": "上海市",
            "city": "上海市",
            "county": "",
            "area": "中国上海市上海市 阿里",
            "isp": ""
        }
    },
    {
        "ip": "223.5.5.5",
        "category": "v4-cn-isp-dns",
        "result": {
            "code": 0,
            "error": "",
            "ip": "223.5.5.5",
            "country": "中国",
            "province": "浙江省",
            "city": "杭州市",
            "county": "",
            "area": "中国浙江省杭州市 阿里",
            "isp": ""
        }
    },
    {
        "ip": "119.29.29.29",
        "category": "v4-cn-isp-dns",
        "result": {
            "code": 0,
            "error": "",
            "ip": "119.29.29.29",
            "country": "中国",
            "province": "北京市",
            "city": "北京市",
            "county": "",
            "area": "中国北京市北京市 腾讯",
            "isp": ""
        }
    },
    {
        "ip": "180.76.76.76",
        "category": "v4-cn-isp-dns",
        "result": {
            "code": 0,
            "error": "",
            "ip": "180.76.76.76",
            "country": "中国",
            "province": "北京市",
            "city": "北京市",
            "county": "",
            "area": "中国北京市北京市 百度",
            "isp": ""
        }
    },
    {
        "ip": "58.196.128.0",
        "category": "v4-cn-edu",
        "result": {
            "code": 0,
            "error": "",
            "ip": "58.196.128.0",
            "country": "中国",
            "province": "上海市",
            "city": "上海市",
            "county": "",
            "area": "中国上海市上海市 中国教育网",
            "isp": ""
        }
    },
    {
        "ip": "114.114.114.114",
        "category": "v4-cn-isp-dns",
        "result": {
            "code": 0,
            "error": "",
            "ip": "114.114.114.114",
            "country": "中国",
            "province": "江苏省",
            "city": "南京市",
            "county": "",
            "area": "中国江苏省南京市",
            "isp": ""
        }
    },
    {
        "ip": "172.217.25.14",
        "category": "v4-hk-cloud",
        "result": {
            "code": 0,
            "error": "",
            "ip": "172.217.25.14",
            "country": "中国",
            "province": "香港特别行政区",
            "city": "",
            "county": "",
            "area": "中国香港特别行政区 谷歌",
            "isp": ""
        }
    },
    {
        "ip": "8.8.8.8",
        "category": "v4-overseas",
        "result": {
            "code": 0,
            "error": "",
            "ip": "8.8.8.8",
            "country": "United States",
            "province": "California",
            "city": "",
            "county": "",
            "area": "United StatesCalifornia Google LLC",
            "isp": ""
        }
    },
    {
        "ip": "1.1.1.1",
        "category": "v4-overseas",
        "result": {
            "code": 0,
            "error": "",
            "ip": "1.1.1.1",
            "country": "Australia",
            "province": "Queensland",
            "city": "Brisbane",
            "county": "",
            "area": "AustraliaQueenslandBrisbane",
            "isp": ""
        }
    },
    {
        "ip": "9.9.9.9",
        "category": "v4-overseas",
        "result": {
            "code": 0,
            "error": "",
            "ip": "9.9.9.9",
            "country": "United States",
            "province": "North Carolina",
            "city": "",
            "county": "",
            "area": "United StatesNorth Carolina",
            "isp": ""
        }
    },
    {
        "ip": "208.67.222.222",
        "category": "v4-overseas",
        "result": {
            "code": 0,
            "error": "",
            "ip": "208.67.222.222",
            "country": "United States",
            "province": "Colorado",
            "city": "",
            "county": "",
            "area": "United StatesColorado",
            "isp": ""
        }
    },
    {
        "ip": "2409:8900:103f:14f:d7e:cd36:11af:be83",
        "category": "v6-cn-mobile",
        "result": {
            "code": 0,
            "error": "",
            "ip": "2409:8900:103f:14f:d7e:cd36:11af:be83",
            "country": "中国",
            "province": "北京市",
            "city": "北京市",
            "county": "",
            "area": "中国北京市北京市 移动",
            "isp": "移动"
        }
    },
    {
        "ip": "2400:3200::1",
        "category": "v6-cn-cloud",
        "result": {
            "code": 0,
            "error": "",
            "ip": "2400:3200::1",
            "country": "中国",
            "province": "浙江省",
            "city": "杭州市",
            "county": "",
            "area": "中国浙江省杭州市 阿里",
            "isp": ""
        }
    },
    {
        "ip": "2400:3200:baba::1",
        "category": "v6-cn-isp-dns",
        "result": {
            "code": 0,
            "error": "",
            "ip": "2400:3200:baba::1",
            "country": "中国",
            "province": "浙江省",
            "city": "杭州市",
            "county": "",
            "area": "中国浙江省杭州市 阿里",
            "isp": ""
        }
    },
    {
        "ip": "240c::6666",
        "category": "v6-cn-isp-dns",
        "result": {
            "code": 0,
            "error": "",
            "ip": "240c::6666",
            "country": "中国",
            "province": "北京市",
            "city": "北京市",
            "county": "",
            "area": "中国北京市北京市 澳门科技大学",
            "isp": ""
        }
    },
    {
        "ip": "2402:4e00::",
        "category": "v6-cn-mobile",
        "result": {
            "code": 0,
            "error": "",
            "ip": "2402:4e00::",
            "country": "中国",
            "province": "广东省",
            "city": "深圳市",
            "county": "",
            "area": "中国广东省深圳市 腾讯",
            "isp": ""
        }
    },
    {
        "ip": "2001:4860:4860::8888",
        "category": "v6-overseas",
        "result": {
            "code": 0,
            "error": "",
            "ip": "2001:4860:4860::8888",
            "country": "United States",
            "province": "Florida",
            "city": "Miami",
            "county": "",
            "area": "United StatesFloridaMiami Google LLC",
            "isp": ""
        }
    },
    {
        "ip": "2606:4700:4700::1111",
        "category": "v6-overseas",
        "result": {
            "code": 0,
            "error": "",
            "ip": "2606:4700:4700::1111",
            "country": "United Kingdom",
            "province": "England",
            "city": "London",
            "county": "",
            "area": "United KingdomEnglandLondon Cloudflare, Inc.",
            "isp": ""
        }
    },
    {
        "ip": "127.0.0.1",
        "category": "special-loopback",
        "result": {
            "code": 0,
            "error": "",
            "ip": "127.0.0.1",
            "country": "",
            "province": "",
            "city": "",
            "county": "",
            "area": "本机地址",
            "isp": ""
        }
    },
    {
        "ip": "192.168.1.1",
        "category": "special-private",
        "result": {
            "code": 0,
            "error": "",
            "ip": "192.168.1.1",
            "country": "私网地址",
            "province": "",
            "city": "",
            "county": "",
            "area": "私网地址",
            "isp": ""
        }
    },
    {
        "ip": "fe80::1",
        "category": "special-linklocal",
        "result": {
            "code": 0,
            "error": "",
            "ip": "fe80::1",
            "country": "链路本地地址",
            "province": "",
            "city": "",
            "county": "",
            "area": "链路本地地址",
            "isp": ""
        }
    },
    {
        "ip": "not-an-ip",
        "category": "invalid",
        "result": {
            "ip": "not-an-ip",
            "code": 1,
            "error": "IP Invalid"
        }
    }
]
```

#### 数据源与许可

1. [IP2Region](https://github.com/lionsoul2014/ip2region)：包内 xdb(v3) 查询算法精简移植自官方 PHP 绑定，代码采用 Apache-2.0 / MIT 双许可；随附或自备的 xdb 数据文件授权以官方仓库及数据来源条款为准，商用或再分发前请自行核实。
2. 纯真 IP 库（`qqwry.dat` / `ipv6wry.db`）：仅作为可选回退解析器保留，社区版已停止更新，需自备数据文件。
