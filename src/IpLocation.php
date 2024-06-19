<?php

declare(strict_types=1);

namespace think;

use think\IpParser\QQwry;
use think\IpParser\IpV6wry;

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
    public static function getLocationWithoutParse($ip, $ipV4Path = '', $ipV6Path = '')
    {
        $location = [];
        // 当提供了IPv4数据库文件路径时,设置IPv4数据库路径
        if (strlen($ipV4Path)) {
            self::setIpV4Path($ipV4Path);
        }
        // 当提供了IPv6数据库文件路径时,设置IPv6数据库路径
        if (strlen($ipV6Path)) {
            self::setIpV6Path($ipV6Path);
        }
        // 根据IP地址类型,使用相应的数据库查询方法
        if (self::isIpV4($ip)) {
            // 查询IPv4地址
            $ins = new QQwry();
            $ins->setDBPath(self::getIpV4Path());
            $location = $ins->getIp($ip);
        } else if (self::isIpV6($ip)) {
            // 查询IPv6地址
            $ins = new IpV6wry();
            $ins->setDBPath(self::getIpV6Path());
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
    public static function getLocation($ip, $ipV4Path = '', $ipV6Path = '')
    {
        // 尝试获取原始地理位置数据,不进行任何解析
        $location = self::getLocationWithoutParse($ip, $ipV4Path, $ipV6Path);
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
     * 获取IPv4地址数据库文件的路径
     *
     * 该方法用于确定并返回用于查询IPv4地址的数据库文件路径.如果已设置了类变量`$ipV4Path`
     * 则直接返回该路径.否则,通过调用`self::src()`方法来指定默认的数据库文件路径
     * 这种设计允许灵活地配置数据库文件的位置,以适应不同的部署环境或需求
     *
     * @return mixed|string 返回IPv4地址数据库文件的路径.路径可以是类变量`$ipV4Path`的值
     * 或者是通过`self::src()`方法确定的默认路径
     */
    private static function getIpV4Path()
    {
        // 检查是否已设置了类变量$ipV4Path,如果未设置,则调用src方法来获取默认路径
        return self::$ipV4Path ?: self::src('/libs/qqwry.dat');
    }

    /**
     * 获取IPv6数据库文件的路径
     * 
     * 本函数用于确定并返回IPv6数据库文件的路径.如果已通过类属性$ipV6Path设置了路径
     * 则直接返回该路径.如果没有设置,则通过调用src方法,指定默认路径来获取数据库文件路径
     * 这样设计的目的是为了提供灵活性,允许在不同环境中配置不同的数据库文件路径
     * 
     * @return mixed|string 返回IPv6数据库文件的路径.路径可以是类属性$ipV6Path中直接指定的
     * 或者是通过调用src方法动态确定的
     */
    private static function getIpV6Path()
    {
        // 检查是否已通过类属性$ipV6Path设置了IPv6数据库文件的路径
        return self::$ipV6Path ?: self::src('/libs/ipv6wry.db');
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
