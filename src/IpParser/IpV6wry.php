<?php

declare(strict_types=1);

namespace think\IpParser;

/**
 * Class IpV6wry
 * @package think\Ip
 */
class IpV6wry implements IpParserInterface
{
    private static $filePath;
    const FORMAT = 'J2';
    private static $total = null;
    // 索引区
    private static $index_start_offset;
    private static $index_end_offset;
    private static $offlen;
    private static $iplen;
    private static $has_initialized = false;

    /**
     * 设置数据库文件的路径
     * 
     * 本函数用于设定数据库文件的存储路径.通过此路径,类可以找到并操作相应的数据库文件
     * 路径的设置对于确保数据库文件的安全存储以及类能够正确访问数据库文件至关重要
     * 
     * @param string $filePath 数据库文件的路径
     */
    public function setDBPath($filePath)
    {
        self::$filePath = $filePath;
    }

    /**
     * 根据IP地址获取国家和地区信息
     * 
     * 本函数尝试通过查询给定的IP地址,来获取该IP对应的国家和地区信息
     * 如果查询过程中发生异常,将返回一个包含错误信息的数组
     * 正常情况下,返回一个包含IP、国家和地区信息的数组
     * 
     * @param string $ip 需要查询的IP地址
     * @return array|mixed 返回包含IP信息的数组,如果查询失败,则包含错误信息
     */
    public function getIp($ip)
    {
        $result = [];
        $result['ip'] = $ip;
        $result['code'] = 1;
        $result['error'] = '';
        try {
            // 尝试查询给定IP的信息
            $tmp = self::query($ip);
            $result['code'] = 0;
            $result['country'] = $tmp['addr'][0];
            $result['area'] = $tmp['addr'][1];
        } catch (\Exception $exception) {
            // 查询异常时,返回包含错误信息的数组
            $result['error'] = $exception->getMessage();
        }
        // 构建并返回包含IP、国家和地区信息的数组
        return $result;
    }

    /**
     * 获取总数
     * 
     * 该静态方法用于获取某种资源的总数.如果总数尚未初始化,则通过读取指定文件路径中的数据来初始化
     * 为了避免重复打开和关闭文件,使用静态变量缓存总数
     * 
     * @return mixed 返回资源的总数.总数是一个数字类型
     */
    public static function total()
    {
        // 检查总数是否已经初始化
        if (null === static::$total) {
            // 打开文件以只读二进制模式
            $fd = fopen(static::$filePath, 'rb');
            // 初始化总数,该过程假设在initialize方法中完成
            static::initialize($fd);
            // 关闭文件句柄
            fclose($fd);
        }
        // 返回总数
        return static::$total;
    }

    /**
     * 初始化类的静态变量,确保仅在必要时执行一次
     * 这个方法的设计是为了在程序启动时或在特定条件下,对类的一些全局属性进行初始化
     * 它通过检查操作系统位数和PHP版本来确保运行环境的兼容性,然后从给定的文件描述符中读取
     * 某些特定的值来设置类的静态属性,这些属性可能用于后续的数据处理或索引计算
     *
     * @param int $fd 文件描述符,用于从文件中读取数据
     * @throws \RuntimeException 如果操作系统不支持64位或PHP版本低于7.0,则抛出运行时异常
     */
    public static function initialize($fd)
    {
        // 检查是否已经初始化,如果已经初始化,则不再执行
        if (!static::$has_initialized) {
            // 检查操作系统是否为64位,如果不是,则抛出异常
            if (PHP_INT_SIZE < 8) {
                throw new \RuntimeException('64bit OS supported only');
            }
            // 检查PHP版本是否大于等于7.0,如果不是,则抛出异常
            if (version_compare(PHP_VERSION, '7.0', '<')) {
                throw new \RuntimeException('php version 7.0 or greater');
            }
            // 从文件描述符中读取特定偏移量的数据,用于初始化类的静态属性
            static::$index_start_offset = static::read8($fd, 16);
            static::$offlen = static::read1($fd, 6);
            static::$iplen = static::read1($fd, 7);
            static::$total = static::read8($fd, 8);
            // 根据已读取的值计算索引结束偏移量,并设置初始化标志为true
            static::$index_end_offset = static::$index_start_offset + (static::$iplen + static::$offlen) * static::$total;
            static::$has_initialized = true;
        }
    }

    /**
     * 根据IP地址查询对应的地理位置信息
     * 
     * 该方法通过读取预先处理的IP地址数据库文件,将IPv6地址转换为数字表示
     * 并在数据库中查找对应的地理位置记录.如果IP地址无效或数据库读取失败
     * 将抛出运行时异常.方法返回一个包含开始IP、结束IP、地址和显示地址的数组
     * 
     * @param string $ip 要查询的IPv6地址
     * @return array 包含开始IP、结束IP、地址和显示地址的数组
     * @throws \RuntimeException 如果IP地址无效或数据库读取失败
     */
    public static function query($ip)
    {
        // 将IPv6地址转换为二进制格式,如果失败抛出异常
        $ip_bin = inet_pton($ip);
        if (false === $ip_bin) {
            throw new \RuntimeException('error IPv6 address: ' . $ip);
        }
        // 检查转换后的二进制长度是否为16字节,如果不是抛出异常
        if (16 !== strlen($ip_bin)) {
            throw new \RuntimeException('error IPv6 address: ' . $ip);
        }
        // 打开数据库文件用于后续读取,文件以二进制只读模式打开
        $fd = fopen(static::$filePath, 'rb');
        // 初始化数据库读取指针等参数
        static::initialize($fd);
        // 将二进制IP地址转换为数字数组
        $ip_num_arr = unpack(static::FORMAT, $ip_bin);
        // 提取数字数组中的前半部分和后半部分
        // IP地址前半部分转换成有int
        $ip_num1 = $ip_num_arr[1];
        // IP地址后半部分转换成有int
        $ip_num2 = $ip_num_arr[2];
        // 在数据库中查找对应的IP记录,返回记录的索引
        $ip_find = static::find($fd, $ip_num1, $ip_num2, 0, static::$total);
        // 根据记录索引计算出记录在文件中的偏移量
        $ip_offset = static::$index_start_offset + $ip_find * (static::$iplen + static::$offlen);
        // 计算结束IP的偏移量
        $ip_offset2 = $ip_offset + static::$iplen + static::$offlen;
        // 从文件中读取开始IP的二进制数据,并转换为IPv6地址格式
        $ip_start = inet_ntop(pack(static::FORMAT, static::read8($fd, $ip_offset), 0));
        // 尝试读取结束IP的二进制数据,并转换为IPv6地址格式,如果失败则使用特殊地址表示
        try {
            $ip_end = inet_ntop(pack(static::FORMAT, static::read8($fd, $ip_offset2) - 1, 0));
        } catch (\RuntimeException $e) {
            $ip_end = 'FFFF:FFFF:FFFF:FFFF::';
        }
        // 从文件中读取地址记录的偏移量
        $ip_record_offset = static::read8($fd, $ip_offset + static::$iplen, static::$offlen);
        // 根据偏移量读取并解码地址记录
        $ip_addr = static::read_record($fd, $ip_record_offset);
        // 组合地址和显示地址
        $ip_addr_disp = $ip_addr[0] . ' ' . $ip_addr[1];
        // 关闭数据库文件
        if (is_resource($fd)) {
            fclose($fd);
        }
        // 返回查询结果
        return ['start' => $ip_start, 'end' => $ip_end, 'addr' => $ip_addr, 'disp' => $ip_addr_disp];
    }

    /**
     * 从文件描述符中读取记录
     * 
     * 本函数用于根据文件描述符和偏移量读取特定格式的记录.记录可能包含一个或两个位置信息
     * 如果记录标志为1,表示该记录是一个链接,需要通过链接地址递归读取实际记录
     * 如果记录标志为2,表示该记录包含两个位置信息；否则,只包含一个位置信息
     * 
     * @param resource $fd 文件描述符,用于标识要读取的文件
     * @param int $offset 偏移量,指示从文件的哪个位置开始读取
     * @return array 返回一个包含位置信息的数组.数组的第一个元素是主位置信息
     *               第二个元素是可选的次位置信息
     */
    public static function read_record($fd, $offset)
    {
        // 初始化记录数组,默认包含两个空字符串位置
        $record = [0 => '', 1 => ''];
        // 读取记录标志,用于确定记录的格式和处理方式
        $flag = static::read1($fd, $offset);
        // 如果记录标志为1,表示存在链接,需递归读取实际记录
        if ($flag == 1) {
            // 读取链接位置的偏移量
            $location_offset = static::read8($fd, $offset + 1, static::$offlen);
            // 通过链接位置递归读取实际记录
            return static::read_record($fd, $location_offset);
        }
        // 读取主位置信息
        $record[0] = static::read_location($fd, $offset);
        // 如果记录标志为2,读取次位置信息
        if ($flag == 2) {
            // 次位置信息的偏移量计算基于主位置信息和预定义的偏移长度
            $record[1] = static::read_location($fd, $offset + static::$offlen + 1);
        } else {
            // 如果记录标志不为2,次位置信息的偏移量计算基于主位置信息的长度
            $record[1] = static::read_location($fd, $offset + strlen($record[0]) + 1);
        }
        // 返回包含位置信息的数组
        return $record;
    }

    /**
     * 从文件描述符中读取位置信息
     * 
     * 此函数用于根据给定的文件描述符和偏移量读取位置信息.它处理了重定向的情况,并返回实际的位置信息
     * 
     * @param resource $fd 文件描述符,用于标识要读取的文件或流
     * @param int $offset 偏移量,指示从文件的哪个位置开始读取
     * @return string 返回读取到的位置信息.如果读取失败或偏移量为0,则返回空字符串
     */
    public static function read_location($fd, $offset)
    {
        // 如果偏移量为0,表示没有有效的位置信息需要读取,直接返回空字符串
        if ($offset == 0) {
            return '';
        }
        // 读取偏移量指向的标志位,用于判断接下来如何处理
        $flag = static::read1($fd, $offset);
        // 如果标志位为0,表示读取失败,返回空字符串
        if ($flag == 0) {
            return '';
        }
        // 如果标志位为2,表示存在重定向,需要更新偏移量并递归调用本函数以处理重定向
        if ($flag == 2) {
            // 读取重定向的偏移量长度,并更新偏移量
            $offset = static::read8($fd, $offset + 1, static::$offlen);
            // 递归调用本函数,使用新的偏移量尝试再次读取位置信息
            return static::read_location($fd, $offset);
        }
        // 如果标志位既不是0也不是2,表示没有重定向,直接读取位置信息
        return static::readstr($fd, $offset);
    }

    /**
     * 二分查找指定IP范围内的记录
     * 
     * 该静态方法用于在预处理的IP数据文件中查找包含给定IP范围的第一个记录的索引
     * 使用二分查找算法提高查找效率,适用于大规模IP数据的快速查询
     * 
     * @param resource $fd 文件描述符,指向已打开的IP数据文件
     * @param string $ip_num1 起始IP地址,以整数形式表示
     * @param string $ip_num2 结束IP地址,以整数形式表示
     * @param int $l 查找范围的左边界索引
     * @param int $r 查找范围的右边界索引
     * @return int 返回找到的记录的索引,如果未找到则返回左边界索引
     */
    public static function find($fd, $ip_num1, $ip_num2, $l, $r)
    {
        // 如果左边界加1大于等于右边界,说明范围只有一个元素或为空,直接返回左边界
        if ($l + 1 >= $r) {
            return $l;
        }
        // 计算中间索引,并根据索引计算出文件中的偏移量
        $m = intval(($l + $r) / 2);
        $offset = static::$index_start_offset + $m * (static::$iplen + static::$offlen);
        // 从文件中读取中间索引对应的起始IP地址
        $m_ip1 = static::read8($fd, $offset, static::$iplen);
        // 初始化中间索引对应的结束IP地址
        $m_ip2 = 0;
        // 根据IP长度,处理结束IP地址的读取和位移
        if (static::$iplen <= 8) {
            $m_ip1 <<= 8 * (8 - static::$iplen);
        } else {
            $m_ip2 = static::read8($fd, $offset + 8, static::$iplen - 8);
            $m_ip2 <<= 8 * (16 - static::$iplen);
        }
        // 比较起始IP地址,如果小于中间索引的起始IP,则在左半部分继续查找
        if (static::uint64cmp($ip_num1, $m_ip1) < 0) {
            return static::find($fd, $ip_num1, $ip_num2, $l, $m);
        }
        // 如果大于中间索引的起始IP,则在右半部分继续查找
        if (static::uint64cmp($ip_num1, $m_ip1) > 0) {
            return static::find($fd, $ip_num1, $ip_num2, $m, $r);
        }
        // 比较结束IP地址,如果小于中间索引的结束IP,则在左半部分继续查找
        if (static::uint64cmp($ip_num2, $m_ip2) < 0) {
            return static::find($fd, $ip_num1, $ip_num2, $l, $m);
        }
        // 如果结束IP地址大于等于中间索引的结束IP,则在右半部分继续查找
        return static::find($fd, $ip_num1, $ip_num2, $m, $r);
    }

    /**
     * 从文件描述符中读取原始数据
     * 
     * 此函数用于从文件描述符中读取指定大小的数据.它支持通过偏移量来指定从文件的哪个位置开始读取
     * 这是对fread函数的简单封装,提供了更灵活的读取方式
     * 
     * @param resource $fd 文件描述符,来自于fopen返回的资源
     * @param int $offset 可选参数,指定从文件的哪个位置开始读取.如果未指定,则从文件当前位置开始读取
     * @param int $size 指定要读取的数据大小.如果为0,则读取到文件末尾
     * @return string 返回读取到的原始数据
     */
    public static function readraw($fd, $offset = null, $size = 0)
    {
        // 如果指定了偏移量,则移动文件指针到指定位置
        if (!is_null($offset)) {
            fseek($fd, $offset);
        }
        // 从文件描述符中读取指定大小的数据并返回
        return fread($fd, $size);
    }

    /**
     * 从文件描述符中读取一个字节
     * 
     * 此函数用于从给定的文件描述符中读取一个字节,并以整数形式返回该字节的值
     * 如果提供了偏移量,则会尝试跳转到指定的位置进行读取
     * 
     * @param resource $fd 文件描述符,用于标识要读取的文件或流
     * @param int|null $offset 可选参数,指定从文件的哪个位置开始读取.单位为字节
     * @return int 读取到的字节的值,以整数形式表示
     */
    public static function read1($fd, $offset = null)
    {
        // 如果提供了偏移量,则跳转到指定位置
        if (!is_null($offset)) {
            fseek($fd, $offset);
        }
        // 读取一个字节
        $a = fread($fd, 1);
        // 解包字节为整数,并返回解包后的值
        return @unpack("C", $a)[1];
    }

    /**
     * 从文件描述符中读取64位的偏移量
     * 
     * 此函数用于从文件中按照大端字节序读取一个64位的整数.如果提供了偏移量,则会先跳转到指定位置
     * 主要用于处理大型文件中需要读取特定位置数据的场景
     * 
     * @param resource $fd 文件描述符,代表打开的文件
     * @param int $offset 可选参数,指定从文件的哪个位置开始读取,默认为null,表示从当前位置读取
     * @param int $size 指定读取的字节大小,本函数固定为8,用于读取64位整数
     * @return int 返回读取到的64位整数
     */
    public static function read8($fd, $offset = null, $size = 8)
    {
        // 如果指定了偏移量,则跳转到指定位置
        if (!is_null($offset)) {
            fseek($fd, $offset);
        }
        // 读取指定字节大小的数据,并在末尾添加8个零字节,用于保证解包时数据长度正确
        $a = fread($fd, $size) . "\0\0\0\0\0\0\0\0";
        // 使用unpack函数将读取到的数据解包为大端字节序的64位整数
        return @unpack("P", $a)[1];
    }

    /**
     * 从文件描述符中读取字符串
     * 
     * 该方法用于从文件描述符中按字符读取数据,直到遇到null字符为止.如果提供了偏移量,则会从指定位置开始读取
     * 这是在处理某些二进制数据文件或协议时常用的读取策略
     * 
     * @param resource $fd 文件描述符,代表打开的文件或流
     * @param int|null $offset 可选参数,指定从文件的哪个位置开始读取.如果为null,则从当前位置开始
     * @return string 返回读取到的字符串.如果没有读取到任何数据(即文件描述符无效或已到达文件末尾),则返回空字符串
     */
    public static function readstr($fd, $offset = null)
    {
        // 如果提供了偏移量,则跳转到指定位置
        if (!is_null($offset)) {
            fseek($fd, $offset);
        }
        // 初始化用于存储读取字符的字符串变量
        $str = '';
        // 从文件描述符中读取一个字符,并检查是否为null字符(字符串结束标志)
        $chr = static::read1($fd, $offset);
        while ($chr != 0) {
            // 将读取到的字符追加到字符串中
            $str .= chr($chr);
            // 移动到下一个字符位置
            $offset++;
            // 从新位置读取下一个字符
            $chr = static::read1($fd, $offset);
        }
        // 返回读取到的字符串
        return $str;
    }

    /**
     * 将IPv4地址转换为整数
     * 
     * 该函数用于将一个IPv4地址转换为其对应的32位无符号整数表示
     * 这种转换在处理IP地址时非常有用,例如进行IP范围检查或排序
     * 
     * @param string $ip IPv4地址
     * @return int 32位无符号整数表示的IP地址
     */
    public static function ip2num($ip)
    {
        // 使用inet_pton将IPv4地址转换为二进制格式
        // 然后使用unpack以网络字节序(N)的方式将二进制数据解包为整数
        return unpack("N", inet_pton($ip))[1];
    }

    /**
     * 将一个32位的无符号整型数值转换为IPv4地址字符串格式
     * 
     * 此函数用于将网络字节序的32位无符号整数转换为点分十进制的IPv4地址格式
     * 这是一个静态方法,可以直接通过类名调用
     *
     * @param int $nip 32位的无符号整型数值,代表一个IPv4地址
     * @return string 返回点分十进制格式的IPv4地址
     */
    public static function inet_ntoa($nip)
    {
        // 初始化一个数组,用于存放每段IP地址
        $ip = [];
        // 从高位到低位遍历整型数值,提取每段IP地址
        for ($i = 3; $i > 0; $i--) {
            // 计算当前段的IP地址值,并将其转换为整型
            $ip_seg = intval($nip / pow(256, $i));
            // 将当前段的IP地址值添加到数组中
            $ip[] = $ip_seg;
            // 从nip中减去已经提取的当前段IP地址值
            $nip -= $ip_seg * pow(256, $i);
        }
        // 将剩余的最低位IP地址值添加到数组中
        $ip[] = $nip;
        // 使用点号连接数组中的所有元素,形成点分十进制的IPv4地址,并返回
        return join('.', $ip);
    }

    /**
     * 比较两个64位无符号整数的大小
     * 
     * 由于PHP的整数类型处理方式,当整数超过一定范围时,其符号位会被忽略
     * 这个函数旨在提供一种正确比较两个可能超过PHP整数范围的无符号整数的方法
     * 
     * @param mixed $a 第一个无符号整数,可以是任何可以转换为整数的类型
     * @param mixed $b 第二个无符号整数,可以是任何可以转换为整数的类型
     * @return int 返回比较结果,-1表示$a小于$b,0表示$a等于$b,1表示$a大于$b
     */
    public static function uint64cmp($a, $b)
    {
        // 当两个数都是非负数或者都是负数时,使用PHP的内置比较运算符进行比较
        if ($a >= 0 && $b >= 0 || $a < 0 && $b < 0) {
            return $a <=> $b;
        }
        // 如果一个数是非负数,另一个数是负数,则非负数总是更大的
        if ($a >= 0 && $b < 0) {
            return -1;
        }
        // 如果执行到这里,说明一个数是负数,另一个数是非负数,则非负数总是更大的
        return 1;
    }
}
