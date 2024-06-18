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
     * 中国直辖市
     *
     * @var mixed|array
     */
    private static $dictCityDirectly = [
        '北京',
        '天津',
        '重庆',
        '上海',
    ];

    private static $dictDistrictBlackTails = [
        '校区',
        '学区',
    ];

    /**
     * 中国省份
     *
     * @var mixed|array
     */
    private static $dictProvince = [
        '北京',
        '天津',
        '重庆',
        '上海',
        '河北',
        '山西',
        '辽宁',
        '吉林',
        '黑龙江',
        '江苏',
        '浙江',
        '安徽',
        '福建',
        '江西',
        '山东',
        '河南',
        '湖北',
        '湖南',
        '广东',
        '海南',
        '四川',
        '贵州',
        '云南',
        '陕西',
        '甘肃',
        '青海',
        '台湾',
        '内蒙古',
        '广西',
        '宁夏',
        '新疆',
        '西藏',
        '香港',
        '澳门',
    ];

    /**
     * 解析给定的位置信息,将其分解为更详细的地址组件
     * 
     * 此函数设计用于处理来自不同来源的位置字符串,将其标准化为包含国家、省份、城市、县/区和原始区域的数组
     * 支持识别和处理中国内地的省份、城市、县/区,以及特殊情况如直辖市和无省份标识的位置
     * 
     * @param array $location 包含位置信息的数组,预期包含国家、地区和IP字段
     * @param bool $withOriginal 是否返回原始数据,用于调试目的
     * @return array|mixed 返回一个包含解析后位置信息的数组,如果失败则返回错误信息.如果$withOriginal为true,则还包括原始位置数据
     */
    public static function parse($location, $withOriginal = false)
    {
        // 保存原始位置信息
        $org = $location;
        $result = [];
        $isChina = false;
        // 定义用于地址分割的字符串
        $separatorProvince = '省';
        $separatorCity = '市';
        $separatorCounty = '县';
        $separatorDistrict = '区';
        // 检查位置信息是否为空
        if (!$location) {
            $result['error'] = 'file open failed';
            return $result;
        }
        // 移除中国前缀以处理特殊情况
        if (strpos($location['country'], '中国') === 0) {
            $location['country'] = str_replace('中国', '', $location['country']);
        }
        // 保存原始国家和地区信息
        $location['org_country'] = $location['country'];
        $location['org_area'] = $location['area'];
        // 初始化地址组件
        $location['province'] = $location['city'] = $location['county'] = '';
        // 尝试根据省份分割国家字符串
        $_tmp_province = explode($separatorProvince, $location['country']);
        // 如果存在省份信息,则处理中国内地的地址
        if (isset($_tmp_province[1])) {
            $isChina = true;
            $location['province'] = $_tmp_province[0];
            // 尝试根据城市分割省份字符串
            if (strpos($_tmp_province[1], $separatorCity) !== false) {
                $_tmp_city = explode($separatorCity, $_tmp_province[1]);
                $location['city'] = $_tmp_city[0] . $separatorCity;
                // 尝试根据县/区分割城市字符串
                if (isset($_tmp_city[1])) {
                    if (strpos($_tmp_city[1], $separatorCounty) !== false) {
                        $_tmp_county = explode($separatorCounty, $_tmp_city[1]);
                        $location['county'] = $_tmp_county[0] . $separatorCounty;
                    }
                    // 如果没有县/区,但存在区信息,则处理区信息
                    if (!$location['county'] && strpos($_tmp_city[1], $separatorDistrict) !== false) {
                        $_tmp_qu = explode($separatorDistrict, $_tmp_city[1]);
                        $location['county'] = $_tmp_qu[0] . $separatorDistrict;
                    }
                }
            }
        } else {
            // 处理没有省份标识的位置,例如内蒙古和直辖市
            foreach (self::$dictProvince as $key => $value) {
                if (false !== strpos($location['country'], $value)) {
                    $isChina = true;
                    $location['province'] = $value;
                    // 处理直辖市
                    if (in_array($value, self::$dictCityDirectly)) {
                        $_tmp_province = explode($value, $location['country']);
                        // 市辖区
                        if (isset($_tmp_province[1])) {
                            $_tmp_province[1] = self::lTrim($_tmp_province[1], $separatorCity);
                            // 处理区信息
                            if (strpos($_tmp_province[1], $separatorDistrict) !== false) {
                                $_tmp_qu = explode($separatorDistrict, $_tmp_province[1]);
                                // 避免将校区、学区错误识别为城市
                                $isHitBlackTail = false;
                                foreach (self::$dictDistrictBlackTails as $blackTail) {
                                    if (mb_substr($_tmp_qu[0], -mb_strlen($blackTail)) == $blackTail) {
                                        $isHitBlackTail = true;
                                        break;
                                    }
                                }
                                if ((!$isHitBlackTail) && mb_strlen($_tmp_qu[0]) < 5) {
                                    $location['city'] = $_tmp_qu[0] . $separatorDistrict;
                                }
                            }
                        }
                    } else {
                        // 处理没有省份标识的其他位置
                        $_tmp_city = str_replace($location['province'], '', $location['country']);
                        $_tmp_city = self::lTrim($_tmp_city, $separatorCity);
                        if (strpos($_tmp_city, $separatorCity) !== false) {
                            $_tmp_city = explode($separatorCity, $_tmp_city);
                            $location['city'] = $_tmp_city[0] . $separatorCity;
                            // 处理县/区信息
                            if (isset($_tmp_city[1])) {
                                if (strpos($_tmp_city[1], $separatorCounty) !== false) {
                                    $_tmp_county = explode($separatorCounty, $_tmp_city[1]);
                                    $location['county'] = $_tmp_county[0] . $separatorCounty;
                                }
                                if (!$location['county'] && strpos($_tmp_city[1], $separatorDistrict) !== false) {
                                    $_tmp_qu = explode($separatorDistrict, $_tmp_city[1]);
                                    $location['county'] = $_tmp_qu[0] . $separatorDistrict;
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }
        // 如果解析出是中国,修正国家字段
        if ($isChina) {
            $location['country'] = '中国';
        }
        // 组装结果数组
        $result['ip'] = $location['ip'];
        $result['country'] = $location['country'];
        $result['province'] = $location['province'];
        $result['city'] = $location['city'];
        $result['county'] = $location['county'];
        $result['area'] = $location['country'] . $location['province'] . $location['city'] . $location['county'] . ' ' . $location['org_area'];
        $result['isp'] = self::getIsp($result['area']);
        // 如果需要,返回原始数据
        if ($withOriginal) {
            $result['org'] = $org;
        }
        // 返回查询结果
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
