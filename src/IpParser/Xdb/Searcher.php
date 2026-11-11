<?php

declare(strict_types=1);

namespace think\IpParser\Xdb;

// Copyright 2022 The Ip2Region Authors. All rights reserved.
// Use of this source code is governed by a Apache-2.0-style
// license that can be found in the LICENSE file.
//
// 本类为 ip2region xdb(v3) 查询算法的精简移植，仅保留本包所需的文件读取模式，
// 算法与二进制布局对齐官方 binding/php/xdb/Searcher。

/**
 * 内置 xdb 离线库查询器（IPv4 / IPv6）
 *
 * @package think\IpParser\Xdb
 */
class Searcher
{
    /** 文件头信息长度（字节） */
    const HEADER_INFO_LENGTH = 256;

    /** 向量索引列数 */
    const VECTOR_INDEX_COLS = 256;

    /** 单个向量索引槽位大小（字节：起始指针 4 + 结束指针 4） */
    const VECTOR_INDEX_SIZE = 8;

    /** IPv4 段索引大小：4 字节 IP + 2 字节数据长度 + 4 字节数据指针 */
    const V4_SEGMENT_INDEX_SIZE = 14;

    /** IPv6 段索引大小：16 字节 IP + 2 字节数据长度 + 4 字节数据指针 */
    const V6_SEGMENT_INDEX_SIZE = 38;

    /** @var int IP 版本：4 或 6 */
    private $version;

    /** @var int IP 二进制字节长度 */
    private $bytes;

    /** @var int 段索引大小 */
    private $segmentIndexSize;

    /** @var resource|null 文件句柄 */
    private $handle = null;

    /**
     * @param string $dbFile xdb 文件绝对路径
     * @param int $version IP 版本：4 或 6
     * @throws \Exception 文件无法打开时抛出
     */
    public function __construct(string $dbFile, int $version)
    {
        $this->version = $version === 6 ? 6 : 4;
        $this->bytes = $this->version === 6 ? 16 : 4;
        $this->segmentIndexSize = $this->version === 6
            ? self::V6_SEGMENT_INDEX_SIZE
            : self::V4_SEGMENT_INDEX_SIZE;

        $handle = @fopen($dbFile, 'r');
        if ($handle === false) {
            throw new \Exception(sprintf('failed to open xdb file `%s`', $dbFile));
        }
        $this->handle = $handle;
    }

    /**
     * 查询 IP 归属地原始地区串
     *
     * @param string $ip 人类可读 IP 字符串
     * @return string 地区串 `国家|省份|城市|ISP|iso2`，未命中返回空串
     * @throws \Exception IP 无效或读取失败时抛出
     */
    public function search(string $ip): string
    {
        $ipBytes = @inet_pton($ip);
        if ($ipBytes === false || strlen($ipBytes) !== $this->bytes) {
            throw new \Exception(sprintf('invalid ip address `%s`', $ip));
        }

        return $this->searchByBytes($ipBytes);
    }

    /**
     * 按二进制 IP 字节查询
     *
     * @param string $ipBytes inet_pton 返回的打包二进制
     * @return string 地区串，未命中返回空串
     * @throws \Exception
     */
    public function searchByBytes(string $ipBytes): string
    {
        if (strlen($ipBytes) !== $this->bytes) {
            throw new \Exception(sprintf('invalid ip address (v%d expected)', $this->version));
        }

        // 依据向量索引定位段索引块
        $il0 = ord($ipBytes[0]) & 0xFF;
        $il1 = ord($ipBytes[1]) & 0xFF;
        $idx = $il0 * self::VECTOR_INDEX_COLS * self::VECTOR_INDEX_SIZE
            + $il1 * self::VECTOR_INDEX_SIZE;

        $buff = $this->read(self::HEADER_INFO_LENGTH + $idx, 8);
        $sPtr = self::leUint32($buff, 0);
        $ePtr = self::leUint32($buff, 4);

        // 空指针表示该段无数据
        if ($sPtr == 0 || $ePtr == 0) {
            return '';
        }

        $bytes = strlen($ipBytes);
        $dBytes = $bytes << 1;
        $idxSize = $this->segmentIndexSize;
        $indexRange = $ePtr - $sPtr;

        if ($indexRange < 0 || $indexRange % $idxSize !== 0) {
            throw new \UnexpectedValueException(
                sprintf('invalid segment index range: start=%d end=%d size=%d', $sPtr, $ePtr, $idxSize)
            );
        }

        $dataLen = 0;
        $dataPtr = 0;
        $l = 0;
        $h = intdiv($indexRange, $idxSize);

        while ($l <= $h) {
            $m = ($l + $h) >> 1;
            $p = $sPtr + $m * $idxSize;
            $seg = $this->read($p, $idxSize);

            if ($this->ipSubCompare($ipBytes, $seg, 0) < 0) {
                $h = $m - 1;
            } else if ($this->ipSubCompare($ipBytes, $seg, $bytes) > 0) {
                $l = $m + 1;
            } else {
                $dataLen = self::leUint16($seg, $dBytes);
                $dataPtr = self::leUint32($seg, $dBytes + 2);
                break;
            }
        }

        if ($dataLen == 0) {
            return '';
        }

        return $this->read($dataPtr, $dataLen);
    }

    /**
     * IP 字节与段索引内 IP 比较
     *
     * IPv4 索引按小端倒序存储，逐字节倒序比较；IPv6 按大端直比。
     *
     * @param string $ip1 查询 IP 二进制
     * @param string $buff 段索引缓冲区
     * @param int $offset 缓冲区起始偏移
     * @return int -1 / 0 / 1
     */
    private function ipSubCompare(string $ip1, string $buff, int $offset): int
    {
        if ($this->version === 4) {
            $len = strlen($ip1);
            $eIdx = $offset + $len;
            for ($i = 0, $j = $eIdx - 1; $i < $len; $i++, $j--) {
                $i1 = ord($ip1[$i]) & 0xFF;
                $i2 = ord($buff[$j]) & 0xFF;
                if ($i1 > $i2) {
                    return 1;
                }
                if ($i1 < $i2) {
                    return -1;
                }
            }
            return 0;
        }

        $r = strcmp($ip1, substr($buff, $offset, strlen($ip1)));
        return $r < 0 ? -1 : ($r > 0 ? 1 : 0);
    }

    /**
     * 从文件指定偏移读取定长字节
     *
     * @param int $offset 偏移量
     * @param int $len 字节数
     * @return string
     * @throws \Exception
     */
    private function read(int $offset, int $len): string
    {
        if (fseek($this->handle, $offset) === -1) {
            throw new \Exception(sprintf('failed to fseek to offset %d', $offset));
        }

        $buff = fread($this->handle, $len);
        if ($buff === false || strlen($buff) !== $len) {
            throw new \Exception(
                sprintf('incomplete read at offset %d: expected %d bytes, got %d', $offset, $len, strlen((string)$buff))
            );
        }

        return $buff;
    }

    /**
     * 小端序读取无符号 32 位整数
     */
    private static function leUint32(string $b, int $idx): int
    {
        $val = ord($b[$idx])
            | (ord($b[$idx + 1]) << 8)
            | (ord($b[$idx + 2]) << 16)
            | (ord($b[$idx + 3]) << 24);

        // 32 位系统下把有符号整数转为无符号
        if ($val < 0 && PHP_INT_SIZE == 4) {
            $val = (int)sprintf('%u', $val);
        }

        return $val;
    }

    /**
     * 小端序读取无符号 16 位整数
     */
    private static function leUint16(string $b, int $idx): int
    {
        return ord($b[$idx]) | (ord($b[$idx + 1]) << 8);
    }

    /**
     * 关闭文件句柄
     */
    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
