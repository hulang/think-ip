<?php

declare(strict_types=1);

namespace think;

use think\IpParser\QQwry;
use think\IpParser\IpV6wry;
use think\IpParser\Ip2Region;
use think\IpParser\IpParserInterface;

define('IP_DATABASE_ROOT_DIR', dirname(__DIR__));

/**
 * Class IpLocation
 * @package think\Ip
 */
class IpLocation
{
    /**
     * @var string IP[v4]路径
     */
    private static $ipV4Path;
    /**
     * @var string IP[v6]路径
     */
    private static $ipV6Path;
    /**
     * @var string|null 默认数据源：ip2region（默认）/ qqwry（纯真回退）；null 时读取配置文件
     */
    private static $source = null;

    /**
     * 根据IP地址获取地理位置信息,无需解析IP类型
     * 
     * 此方法尝试根据提供的IP地址来获取其对应的地理位置信息.它首先判断IP地址是IPv4还是IPv6
     * 然后使用相应的数据库文件进行查询.如果IP地址类型不被支持,则返回一个包含错误信息的数组
     * 
     * @param string $ip 待查询的IP地址
     * @param string $ipV4Path IPv4数据库文件的路径,默认为空
     * @param string $ipV6Path IPv6数据库文件的路径,默认为空
     * @return mixed|array 如果查询成功,返回一个包含地理位置信息的数组;如果IP地址无效或查询失败,返回一个包含错误信息的数组
     */
    public static function getLocationWithoutParse($ip, $ipV4Path = '', $ipV6Path = '', $source = '')
    {
        $source = self::resolveSource($source);
        // 当提供了数据库文件路径时，覆盖静态路径（保持原有 setter 行为）
        if (strlen($ipV4Path)) {
            self::setIpV4Path($ipV4Path);
        }
        if (strlen($ipV6Path)) {
            self::setIpV6Path($ipV6Path);
        }
        $v4Path = self::$ipV4Path ?: self::defaultDbPath($source, 'v4');
        $v6Path = self::$ipV6Path ?: self::defaultDbPath($source, 'v6');

        // 根据IP地址类型,使用相应的数据库查询方法
        if (self::isIpV4($ip)) {
            $ins = self::makeParser(false, $source, $v4Path, $v6Path);
            $location = $ins->getIp($ip);
        } else if (self::isIpV6($ip)) {
            $ins = self::makeParser(true, $source, $v4Path, $v6Path);
            $location = $ins->getIp($ip);
        } else {
            // IP地址类型不被支持,返回错误信息
            $location['ip'] = $ip;
            $location['code'] = 1;
            $location['error'] = 'IP Invalid';
        }
        // 返回查询结果
        return $location;
    }

    /**
     * 按数据源与 IP 版本创建解析器
     *
     * @param bool $isV6 是否 IPv6
     * @param string $source 数据源：ip2region / qqwry
     * @param string $v4Path IPv4 库路径
     * @param string $v6Path IPv6 库路径
     * @return IpParserInterface
     */
    protected static function makeParser($isV6, $source, $v4Path, $v6Path): IpParserInterface
    {
        if ($source === 'qqwry') {
            $ins = $isV6 ? new IpV6wry() : new QQwry();
            $ins->setDBPath($isV6 ? $v6Path : $v4Path);
            return $ins;
        }
        // 默认 IP2Region，内部按 IP 版本分流 v4/v6
        return new Ip2Region($v4Path, $v6Path);
    }

    /**
     * 解析最终使用的数据源
     *
     * @param string $source 显式传入的数据源，为空时依次取静态设置、配置文件
     * @return string
     */
    private static function resolveSource(string $source): string
    {
        if ($source !== '') {
            return $source;
        }
        if (self::$source !== null) {
            return self::$source;
        }
        $config = self::loadConfig();
        return !empty($config['source']) ? $config['source'] : 'ip2region';
    }

    /**
     * 获取指定数据源的默认数据库路径（优先配置文件，回退包内约定路径）
     *
     * @param string $source 数据源
     * @param string $version v4 / v6
     * @return string
     */
    private static function defaultDbPath($source, $version): string
    {
        $config = self::loadConfig();
        $group = $source === 'qqwry' ? 'qqwry' : 'ip2region';
        $configured = isset($config[$group][$version]) ? trim((string)$config[$group][$version]) : '';
        if ($configured !== '') {
            return self::resolvePath($configured);
        }

        if ($source === 'qqwry') {
            $file = $version === 'v6' ? '/libs/ipv6wry.db' : '/libs/qqwry.dat';
        } else {
            $file = $version === 'v6' ? '/libs/ip2region_v6.xdb' : '/libs/ip2region_v4.xdb';
        }
        return self::src($file);
    }

    /**
     * 解析文件路径：绝对路径原样返回，相对路径相对于包根目录
     *
     * @param string $path
     * @return string
     */
    private static function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $path;
        }
        // Unix 绝对路径、Windows 盘符路径
        if ($path[0] === '/' || $path[0] === '\\' || preg_match('/^[A-Za-z]:/', $path)) {
            return $path;
        }
        return self::root('/' . ltrim($path, '/\\'));
    }

    /**
     * 读取包内配置文件并与默认配置合并
     *
     * @return array
     */
    private static function loadConfig(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = self::defaultConfig();
        $file = self::root('/config/config.php');
        if (is_file($file)) {
            $custom = require $file;
            if (is_array($custom)) {
                $config = array_merge($config, $custom);
                foreach (['ip2region', 'qqwry'] as $group) {
                    if (isset($custom[$group]) && is_array($custom[$group])) {
                        $config[$group] = array_merge($config[$group], $custom[$group]);
                    }
                }
            }
        }

        return $config;
    }

    /**
     * 默认配置
     *
     * @return array
     */
    private static function defaultConfig(): array
    {
        return [
            'source'    => 'ip2region',
            'ip2region' => ['v4' => '', 'v6' => ''],
            'qqwry'     => ['v4' => '', 'v6' => ''],
        ];
    }

    /**
     * 设置默认数据源
     *
     * @param string $source ip2region / qqwry
     */
    public static function setSource($source)
    {
        self::$source = $source;
    }

    /**
     * 获取当前默认数据源
     *
     * @return string
     */
    public static function getSource()
    {
        return self::resolveSource('');
    }

    /**
     * 根据IP地址获取地理位置信息
     * 
     * 本函数尝试通过IP地址来确定地理位置,支持IPv4和IPv6.首先,它会调用getLocationWithoutParse方法来获取原始地理位置数据
     * 如果数据获取成功,那么这些数据将被进一步处理和解析,以提供更易读和使用的格式.如果数据获取失败,本函数将直接返回错误信息
     * 
     * @param string $ip 需要查询的IP地址
     * @param string $ipV4Path IPv4数据库文件的路径,为空时使用默认数据库
     * @param string $ipV6Path IPv6数据库文件的路径,为空时使用默认数据库
     * @return mixed|array 返回解析后的地理位置信息数组,如果查询失败则返回包含错误信息的数组
     */
    public static function getLocation($ip, $ipV4Path = '', $ipV6Path = '', $source = '')
    {
        // 尝试获取原始地理位置数据,不进行任何解析
        $location = self::getLocationWithoutParse($ip, $ipV4Path, $ipV6Path, $source);
        // 检查是否获取到了错误信息
        if ($location['code'] == 1) {
            return $location;
        }
        // 对获取到的地理位置数据进行解析和处理,以提供更友好的格式
        $result = StringParser::parse($location);
        return $result;
    }

    /**
     * 设置IPv4路径
     * 
     * 该静态方法用于设置IPv4的路径.此路径可能用于存储或访问与IPv4相关的数据或资源
     * 调用此方法将允许在类的其他部分中访问更新后的路径值
     * 
     * @param string $path 代表IPv4路径的字符串.此参数应包含所需的路径信息
     *                     以便正确地存储或访问IPv4相关的数据或资源
     */
    public static function setIpV4Path($path)
    {
        self::$ipV4Path = $path;
    }

    /**
     * 设置IPv6路径
     * 
     * 该静态方法用于设置IPv6地址的路径.通过调用此方法,可以更改IPv6路径的默认值
     * 这对于处理与IPv6相关的网络请求或数据存储特别有用,允许应用程序根据需要配置特定的IPv6路径
     * 
     * @param string $path 设置IPv6地址的路径
     */
    public static function setIpV6Path($path)
    {
        self::$ipV6Path = $path;
    }

    /**
     * 检查给定的IP地址是否为有效的IPv4地址
     * 
     * 该方法使用PHP的filter_var函数来验证IP地址是否为有效的IPv4地址
     * 它通过传递FILTER_VALIDATE_IP过滤器和FILTER_FLAG_IPV4标志来指定仅验证IPv4地址
     * 如果IP地址有效,该方法返回true;否则返回false
     * 
     * @param string $ip 要验证的IP地址
     * @return bool 如果IP地址是有效的IPv4地址,则返回true;否则返回false
     */
    private static function isIpV4($ip)
    {
        // 使用filter_var函数验证IP地址是否为有效的IPv4地址,并返回验证结果.
        return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 检查给定的IP地址是否为IPv6地址
     * 
     * 本函数使用PHP的filter_var函数来验证IP地址是否为有效的IPv6地址
     * 它通过传递FILTER_VALIDATE_IP过滤器并使用FILTER_FLAG_IPV6标志来指定IPv6地址的验证
     * 如果IP地址有效,函数将返回true;否则,返回false
     * 
     * @param string $ip 要验证的IP地址
     * @return bool 如果IP地址是有效的IPv6地址,则返回true;否则返回false
     */
    private static function isIpV6($ip)
    {
        return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 构建源文件的完整路径
     * 
     * 该方法旨在方便地构造源文件的URL或文件路径.它通过将给定的文件名与固定的/src路径相结合
     * 来获取相对于项目根目录的源文件路径.这种方法有利于保持代码的可维护性和路径的一致性
     * 
     * @param string $filename 文件名或路径片段.这可以是单独的文件名,也可以包括子目录
     * @return mixed|string 返回构建好的完整路径.路径将以/src开头,后跟传入的文件名
     *                      如果出现错误或无法构建路径,则可能返回原始的文件名参数
     */
    public static function src($filename)
    {
        // 通过将'/src'与文件名拼接,构造源文件的路径,并调用root方法来处理这个路径
        // root方法可能是用于处理路径,确保其相对于项目根目录的正确性
        return self::root('/src' . $filename);
    }

    /**
     * 获取文件的根目录路径
     * 
     * 此函数用于构造并返回指定文件相对于根目录的完整路径.根目录路径是通过预定义的常量IP_DATABASE_ROOT_DIR来确定的
     * 使用此函数可以方便地获取到任何相对于根目录的文件路径,而无需手动拼接字符串,提高了代码的可读性和可维护性
     * 
     * @param string $filename 需要获取路径的文件名或路径
     * @return string 返回构造好的根目录路径与文件名的组合
     */
    public static function root($filename)
    {
        // 返回根目录路径与文件名的组合
        return IP_DATABASE_ROOT_DIR . $filename;
    }
}
