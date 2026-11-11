<?php

declare(strict_types=1);

namespace think\IpParser;

use think\IpParser\Xdb\Searcher;
use think\IpParser\Xdb\SpecialAddress;

/**
 * IP2Region(xdb) 离线库解析器
 *
 * 包内置精简 xdb(v3) 查询器，同时支持 IPv4 / IPv6，
 * 返回结构与纯真解析器保持一致，供 StringParser 统一解析。
 *
 * @package think\Ip
 */
class Ip2Region implements IpParserInterface
{
    /**
     * IPv4 数据库路径
     * @var string|null
     */
    private $v4Path;

    /**
     * IPv6 数据库路径
     * @var string|null
     */
    private $v6Path;

    /**
     * IPv4 查询器实例（懒加载）
     * @var Searcher|null
     */
    private $searcherV4 = null;

    /**
     * IPv6 查询器实例（懒加载）
     * @var Searcher|null
     */
    private $searcherV6 = null;

    /**
     * @param string|null $v4Path IPv4 xdb 路径
     * @param string|null $v6Path IPv6 xdb 路径
     */
    public function __construct($v4Path = null, $v6Path = null)
    {
        $this->v4Path = $v4Path;
        $this->v6Path = $v6Path;
    }

    /**
     * 设置数据库文件路径（兼容接口契约）
     *
     * IP2Region 同时持有 v4/v6 两个库，路径优先通过构造函数传入；
     * 此方法保留以满足 IpParserInterface，默认作为 IPv4 路径覆盖。
     *
     * @param string $filePath 数据库文件路径
     */
    public function setDBPath($filePath)
    {
        $this->v4Path = $filePath;
        $this->searcherV4 = null;
    }

    /**
     * 获取 IP 归属地信息
     *
     * @param string $ip 待查询 IPv4 / IPv6 地址
     * @return array 与纯真解析器同构的结果数组
     */
    public function getIp($ip)
    {
        $result = [
            'ip'      => $ip,
            'code'    => 1,
            'error'   => '',
            'country' => '',
            'area'    => '',
        ];

        try {
            $isV6 = false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            $isV4 = false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if (!$isV4 && !$isV6) {
                $result['error'] = 'invalid ip address';
                return $result;
            }

            // 特殊用途地址优先返回友好名称
            $special = SpecialAddress::getName($ip);
            if ($special !== null) {
                $result['code'] = 0;
                $result['country'] = $special;
                $result['area'] = '';
                return $result;
            }

            $searcher = $isV6 ? $this->searcherV6() : $this->searcherV4();
            $region = $searcher->search($ip);

            if ($region === '') {
                $result['error'] = 'no location';
                return $result;
            }

            $parts = explode('|', $region);
            $country  = $this->normalize(isset($parts[0]) ? $parts[0] : '');
            $province = $this->normalize(isset($parts[1]) ? $parts[1] : '');
            $city     = $this->normalize(isset($parts[2]) ? $parts[2] : '');
            $isp      = $this->normalize(isset($parts[3]) ? $parts[3] : '');

            // 拼出现有契约的国家–省–市 串，运营商放入 area 供 StringParser 匹配
            $geo = array_filter([$country, $province, $city], function ($v) {
                return $v !== '';
            });

            $result['country'] = implode('–', $geo);
            $result['area'] = $isp;
            $result['code'] = 0;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * 懒加载 IPv4 查询器
     *
     * @return Searcher
     * @throws \Exception
     */
    private function searcherV4(): Searcher
    {
        if ($this->searcherV4 === null) {
            $this->searcherV4 = $this->makeSearcher($this->v4Path, 4);
        }
        return $this->searcherV4;
    }

    /**
     * 懒加载 IPv6 查询器
     *
     * @return Searcher
     * @throws \Exception
     */
    private function searcherV6(): Searcher
    {
        if ($this->searcherV6 === null) {
            $this->searcherV6 = $this->makeSearcher($this->v6Path, 6);
        }
        return $this->searcherV6;
    }

    /**
     * 构造指定版本的查询器
     *
     * @param string|null $dbPath 数据库路径
     * @param int $version 4 或 6
     * @return Searcher
     * @throws \Exception
     */
    private function makeSearcher(?string $dbPath, int $version): Searcher
    {
        if (empty($dbPath)) {
            throw new \Exception('ip2region xdb database path is not configured');
        }
        return new Searcher($dbPath, $version);
    }

    /**
     * 归一化字段值：xdb 中用 0 / Reserved / 内网IP 表示空
     *
     * @param mixed $value
     * @return string
     */
    private function normalize($value): string
    {
        $value = (string)$value;
        if ($value === '0' || $value === 'Reserved' || $value === '内网IP') {
            return '';
        }
        return $value;
    }
}
