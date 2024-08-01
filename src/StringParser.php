<?php

declare(strict_types=1);

namespace think;

class StringParser
{
    /**
     * 运营商词典
     *
     * @var mixed|array
     */
    private static $dictIsp = [
        '联通',
        '移动',
        '铁通',
        '电信',
        '长城',
        '聚友',
    ];

    /**
     * 解析给定的位置信息,将其分解为更详细的地址组件
     * 
     * 此函数设计用于处理来自不同来源的位置字符串,将其标准化为包含国家、省份、城市、县/区和原始区域的数组
     * 支持识别和处理中国内地的省份、城市、县/区,以及特殊情况如直辖市和无省份标识的位置
     * 
     * @param array $location 包含位置信息的数组,预期包含国家、地区和IP字段
     * @return array|mixed 返回一个包含解析后位置信息的数组,如果失败则返回错误信息
     */
    public static function parse($location)
    {
        $result = [];
        $result['code'] = 1;
        $result['error'] = 'file open failed';
        $result['ip'] = '';
        $result['country'] = '';
        $result['province'] = '';
        $result['city'] = '';
        $result['county'] = '';
        $result['area'] = '';
        $result['isp'] = '';
        if (!empty($location)) {
            $result['ip'] = $location['ip'];
            // 如果是本机IP
            if ($location['ip'] == '127.0.0.1') {
                $result['code'] = 0;
                $result['error'] = '';
                $result['area'] = '本机地址';
            } else {
                $result['code'] = 0;
                $result['error'] = '';
                $result['ip'] = $location['ip'];
                // 分割字符串
                $arrArea = explode('–', $location['country']);
                $strArea = str_replace('–', '', $location['country']);
                $result['country'] = $arrArea[0];
                $result['province'] = $arrArea[1];
                $result['city'] = $arrArea[2];
                if ($arrArea[1] == $arrArea[2]) {
                    $result['city'] = '';
                }
                $result['county'] = '';
                $result['area'] = join(' ', [$strArea, $location['area']]);
                $result['isp'] = self::getIsp($location['area']);
            }
        }
        return $result;
    }

    /**
     * 根据字符串内容判断可能的互联网服务提供商
     * 
     * 本函数通过遍历一个预定义的字典,查找字符串中是否包含特定的ISP（互联网服务提供商）关键词
     * 如果找到,就返回该ISP的名称.这个方法用于初步判断数据来源或拥有者
     * 
     * @param string $str 要检查的字符串
     * @return mixed 返回找到的ISP名称,如果没找到则返回空字符串
     */
    private static function getIsp($str)
    {
        // 初始化返回值为空字符串
        $ret = '';
        // 遍历预定义的ISP字典
        foreach (self::$dictIsp as $k => $v) {
            // 如果字符串中包含当前ISP的关键词
            if (false !== strpos($str, $v)) {
                // 更新返回值为当前ISP的名称
                $ret = $v;
                // 中断循环,因为找到了匹配的ISP
                break;
            }
        }
        // 返回找到的ISP名称或空字符串
        return $ret;
    }

    /**
     * 移除字符串开头的指定单词
     * 
     * 该函数用于从给定字符串的开始位置移除一个指定的单词.如果指定的单词确实位于字符串的开始位置
     * 则移除该单词并返回剩余的字符串.如果指定的单词不在字符串的开始位置,或字符串为空
     * 则原样返回字符串
     * 
     * @param string $word 待处理的字符串
     * @param string $w 需要移除的单词
     * @return string 处理后的字符串
     */
    private static function lTrim($word, $w)
    {
        // 使用不区分大小写的字符串查找函数定位指定单词在字符串中的位置
        $pos = mb_stripos($word, $w);
        // 检查指定单词是否位于字符串的开始位置
        if ($pos === 0) {
            // 如果是,则移除该单词,注意使用多字节字符串函数以处理多字节字符集
            $word = mb_substr($word, 1);
        }
        // 返回处理后的字符串
        return $word;
    }
}
