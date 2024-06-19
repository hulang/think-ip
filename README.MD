### IP数据库 IPV4 IPV6(解析为国家、省、市、县、运营商)

<p align="center"> 
  您是第  <img src="https://profile-counter.glitch.me/github:hulang:think-ip/count.svg" />位访问者
</p>

##### 环境

- php >= 7.2.5
- 修改自(https://github.com/itbdw/ip-database)
- 纯真IP库社区版更新(20240612)

#### 安装

```
composer require hulang/think-ip
```


```php

//根据实际情况，基本上用框架(如 thinkphp,Laravel)的话不需要手动引入
//require 'vendor/autoload.php';

use think\IpLocation;

// 配置使用
echo json_encode(IpLocation::getLocation($ip), JSON_UNESCAPED_UNICODE) . PHP_EOL;

//支持自定义文件路径
$qqwry_filepath = '/abspath/qqwry.dat';
$ipv6wry_path = '/abspath/ipv6wry.db';
echo json_encode(IpLocation::getLocation($ip, $qqwry_filepath), JSON_UNESCAPED_UNICODE) . PHP_EOL;


```

#### 响应

获取成功
```json
{
    "code": 0,
    "error": "",
    "ip": "163.177.65.160",
    "country": "中国",
    "province": "广东",
    "city": "深圳市",
    "county": "",
    "area": "中国广东深圳市 联通",
    "isp": "联通"
}
```

异常
```json
{
    "code": 1,
    "error": "ip invalid"
}
```


#### 本地测试测试

```
{
    "code": 0,
    "error": "",
    "ip": "172.217.25.14",
    "country": "美国",
    "province": "",
    "city": "",
    "county": "",
    "area": "美国 Google全球边缘网络",
    "isp": ""
}
{
    "code": 0,
    "error": "",
    "ip": "140.205.172.5",
    "country": "中国",
    "province": "上海",
    "city": "",
    "county": "",
    "area": "中国上海 阿里云",
    "isp": ""
}
{
    "code": 0,
    "error": "",
    "ip": "123.125.115.110",
    "country": "中国",
    "province": "北京",
    "city": "",
    "county": "",
    "area": "中国北京 联通\/北京百度网讯科技有限公司联通节点(BGP)",
    "isp": "联通"
}
{
    "code": 0,
    "error": "",
    "ip": "221.196.0.0",
    "country": "中国",
    "province": "天津",
    "city": "河北区",
    "county": "",
    "area": "中国天津河北区 联通",
    "isp": "联通"
}
{
    "code": 0,
    "error": "",
    "ip": "60.195.153.98",
    "country": "中国",
    "province": "北京",
    "city": "",
    "county": "",
    "area": "中国北京 鹏博士\/BGP",
    "isp": ""
}
{
    "code": 0,
    "error": "",
    "ip": "fe80:0000:0001:0000:0440:44ff:1233:5678",
    "country": "局域网",
    "province": "",
    "city": "",
    "county": "",
    "area": "局域网 本地链路单播地址",
    "isp": ""
}
{
    "code": 0,
    "error": "",
    "ip": "2409:8900:103f:14f:d7e:cd36:11af:be83",
    "country": "中国",
    "province": "北京",
    "city": "",
    "county": "",
    "area": "中国北京 中国移动CMNET网络",
    "isp": "移动"
}
{
    "code": 0,
    "error": "",
    "ip": "58.196.128.0",
    "country": "中国",
    "province": "上海",
    "city": "",
    "county": "",
    "area": "中国上海 教育网\/上海交通大学",
    "isp": ""
}
{
    "code": 0,
    "error": "",
    "ip": "2409:8a00:6c1d:81c0:51b4:d603:57d1:b5ec",
    "country": "中国",
    "province": "北京",
    "city": "",
    "county": "",
    "area": "中国北京 中国移动公众宽带",
    "isp": "移动"
}
{
    "code": 0,
    "error": "",
    "ip": "210.74.2.227",
    "country": "中国",
    "province": "北京",
    "city": "",
    "county": "",
    "area": "中国北京 鹏博士BGP",
    "isp": ""
}
```

#### 感谢
1.纯真IP库(纯真宣布社区版只支持到`2024年6月30日`,后续只能在纯真网`注册审核后`进行`下载`,无语了)

`https://www.cz88.com/geo-public`


