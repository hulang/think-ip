<?php

declare(strict_types=1);

namespace think\IpParser;

/**
 * Class IpV4
 * @package think\Ip
 */
class QQwry implements IpParserInterface
{
    /**
     * 文件路径
     * @var mixed|string
     */
    private $filePath;
    /**
     * qqwry.dat文件指针
     *
     * @var mixed|resource
     */
    private $fp;
    /**
     * 第一条IP记录的偏移地址
     *
     * @var mixed|int
     */
    private $firstIp;
    /**
     * 最后一条IP记录的偏移地址
     *
     * @var mixed|int
     */
    private $lastIp;
    /**
     * IP记录的总条数（不包含版本信息记录）
     *
     * @var mixed|int
     */
    private $totalIp;

    /**
     * 设置数据库文件的路径
     * 
     * 本函数用于设定数据库文件的存储路径.通过此路径,程序可以定位到具体的数据库文件
     * 从而进行读取或写入操作.路径的设定对于确保数据库文件的安全存储与访问至关重要
     * 
     * @param string $filePath 数据库文件的路径
     * 
     * 此参数指定数据库文件在文件系统中的路径.路径可以是绝对路径,也可以是相对路径
     * 如果是相对路径,则相对于当前执行脚本的目录.正确设置此路径是确保数据库文件能被
     * 正确访问的前提
     */
    public function setDBPath($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * 根据IP地址获取对应的国家和地区信息
     * 
     * 此函数尝试通过一个IP地址来获取其对应的国家和地区信息
     * 如果获取失败,将返回一个包含错误信息的数组
     * 
     * @param string $ip 需要查询的IP地址
     * @return array|mixed 返回一个包含IP信息的数组,如果失败则返回错误信息数组
     */
    public function getIp($ip)
    {
        try {
            // 尝试调用getAddr方法来获取IP的地址信息
            $tmp = $this->getAddr($ip);
        } catch (\Exception $exception) {
            // 如果捕获到异常,返回一个包含错误信息的数组
            return [
                'error' => $exception->getMessage(),
            ];
        }
        // 构建并返回一个包含IP、国家和地区信息的数组
        $return = [
            'ip' => $ip,
            'country' => $tmp['country'],
            'area' => $tmp['area'],
        ];
        return $return;
    }

    /**
     * 根据IP地址获取对应的地理位置信息
     * 
     * 本函数通过读取IP数据库文件,查找给定IP地址对应的地理位置信息
     * 如果数据库文件不存在或无法打开,将抛出异常
     * 
     * @param string $ip 需要查询的IP地址
     * @return array 包含地理位置信息的数组
     * @throws \Exception 如果无法打开IP数据库文件,则抛出异常
     */
    public function getAddr($ip)
    {
        $filename = $this->filePath;
        // 检查IP数据库文件是否存在,如果不存在则触发错误并抛出异常
        if (!file_exists($filename)) {
            trigger_error('Failed open ip database file!');
            throw new \Exception('Failed open ip database');
        }
        // 如果文件指针尚未初始化,则打开数据库文件并读取首尾IP地址及总IP数量
        if (is_null($this->fp)) {
            $this->fp = 0;
            if (($this->fp = fopen($filename, 'rb')) !== false) {
                $this->firstIp = $this->getLong();
                $this->lastIp = $this->getLong();
                $this->totalIp = ($this->lastIp - $this->firstIp) / 7;
            }
        }
        // 根据IP地址在数据库中查找并返回对应的地理位置信息
        $location = $this->getLocation($ip);
        return $location;
    }

    /**
     * 从文件读取一个4字节的长整型数值
     * 
     * 此私有方法用于解析二进制数据流中的长整型数值.它通过读取文件指针所指向的4个字节
     * 并将这些字节解析为一个无符号长整型(V格式码).这种方法常用于处理二进制数据文件
     * 如图像文件、音频文件等,其中包含了需要按字节精确读取的数值
     * 
     * @return int 解析出的无符号长整型数值
     */
    private function getLong()
    {
        // 使用unpack函数解析4字节为一个无符号长整型
        $result = unpack('Vlong', fread($this->fp, 4));
        // 返回解析出的长整型数值
        return $result['long'];
    }

    /**
     * 根据IP地址获取地理位置信息
     * 
     * 该方法通过二分查找在IP数据库文件中定位给定IP的范围,然后解析出对应的国家和区域信息
     * 使用了长整型和文件指针操作来处理IP数据库文件,该文件是以特定格式存储的IP范围和对应地理位置信息
     * 
     * @param string $ip 需要查询的IP地址
     * @return array|null 包含IP的开始和结束地址以及国家和区域信息的数组,如果找不到则返回null
     */
    private function getLocation($ip)
    {
        // 如果文件指针未初始化,则直接返回null
        if (!$this->fp) {
            return null;
        }
        $location['ip'] = $ip;
        $ip = $this->packIp($location['ip']);
        $l = 0;
        $u = $this->totalIp;
        $findip = $this->lastIp;
        // 使用二分查找定位IP的范围
        while ($l <= $u) {
            $i = floor(($l + $u) / 2);
            fseek($this->fp, intval($this->firstIp + $i * 7));
            $beginip = strrev(fread($this->fp, 4));
            if ($ip < $beginip) {
                $u = $i - 1;
            } else {
                fseek($this->fp, $this->getLong3());
                $endip = strrev(fread($this->fp, 4));
                if ($ip > $endip) {
                    $l = $i + 1;
                } else {
                    $findip = $this->firstIp + $i * 7;
                    break;
                }
            }
        }
        // 根据找到的IP范围,读取具体的国家和区域信息
        fseek($this->fp, (int) $findip);
        $location['beginip'] = long2ip($this->getLong());
        $offset = $this->getLong3();
        fseek($this->fp, $offset);
        $location['endip'] = long2ip($this->getLong());
        $byte = fread($this->fp, 1);
        // 解析国家和区域信息,处理不同格式的数据
        switch (ord($byte)) {
            case 1:
                $countryOffset = $this->getLong3();
                fseek($this->fp, $countryOffset);
                $byte = fread($this->fp, 1);
                switch (ord($byte)) {
                    case 2:
                        fseek($this->fp, $this->getLong3());
                        $location['country'] = $this->getString();
                        fseek($this->fp, $countryOffset + 4);
                        $location['area'] = $this->getArea();
                        break;
                    default:
                        $location['country'] = $this->getString($byte);
                        $location['area'] = $this->getArea();
                        break;
                }
                break;
            case 2:
                fseek($this->fp, $this->getLong3());
                $location['country'] = $this->getString();
                fseek($this->fp, $offset + 8);
                $location['area'] = $this->getArea();
                break;
            default:
                $location['country'] = $this->getString($byte);
                $location['area'] = $this->getArea();
                break;
        }
        // 将国家和区域信息从GBK编码转换为UTF-8编码
        $location['country'] = iconv('GBK', 'UTF-8', $location['country']);
        $location['area'] = iconv('GBK', 'UTF-8', $location['area']);
        // 处理无有效数据的情况
        if ($location['country'] == ' CZ88.NET' || $location['country'] == '纯真网络') {
            $location['country'] = '无数据';
        }
        if ($location['area'] == ' CZ88.NET') {
            $location['area'] = '';
        }
        return $location;
    }

    /**
     * 将IP地址打包为可用于比较的二进制格式
     * 
     * 此方法的目的是为了在比较IP地址时,能够忽略掉IP地址中的点分十进制格式
     * 直接以二进制的形式进行比较,这样可以更高效地对IP地址进行排序或查找等操作
     * 
     * @param string $ip 需要打包的IP地址
     * @return mixed|string 返回打包后的二进制字符串,如果IP地址无效,则返回空字符串
     */
    private function packIp($ip)
    {
        // 将IP地址转换为长整型数字,然后将其打包为四字节的网络字节序
        // 这里使用了pack函数,'N' 表示网络字节序（大端法）
        return pack('N', intval($this->ip2long($ip)));
    }

    /**
     * 将IPv4地址转换为长整型数值
     * 
     * 由于PHP的ip2long函数在处理某些IP地址时可能存在问题,因此这里实现了自定义的转换方法
     * 该方法通过将IP地址的每个部分转换为整型,并进行相应的位移和相加操作,来得到对应的长整型数值
     * 这种转换对于存储和比较IP地址非常有用,特别是在需要进行数值运算的场景中
     * 
     * @param string $ip 要转换的IPv4地址
     * @return int 转换后的长整型数值
     */
    private function ip2long($ip)
    {
        // 将IP地址按点分隔成数组
        $ip_arr = explode('.', $ip);
        // 计算IP地址的长整型数值
        // 这里通过乘以相应的2的幂（16777216, 65536, 256）和相加,来完成每个部分的转换和拼接
        $iplong = (16777216 * intval($ip_arr[0])) + (65536 * intval($ip_arr[1])) + (256 * intval($ip_arr[2])) + intval($ip_arr[3]);
        // 返回计算得到的长整型数值
        return $iplong;
    }

    /**
     * 从文件指针中读取3个字节并解析为长整型数
     * 
     * 由于PHP的unpack函数默认将字节序列解析为PHP的整型,此方法通过读取3个字节并附加一个零字节
     * 来确保解析结果为32位无符号整型.这种方法用于处理特定的二进制数据格式,其中3个字节
     * 的数值需要以特定方式解析
     * 
     * @access private
     * @return mixed 返回解析出的32位无符号整型数,如果发生错误可能返回其他类型
     */
    private function getLong3()
    {
        // 读取3个字节并附加一个零字节,用于确保解析为32位整型
        $result = unpack('Vlong', fread($this->fp, 3) . chr(0));
        // 返回解析结果中的长整型数
        return $result['long'];
    }

    /**
     * 从文件指针中读取字符串
     * 
     * 该方法用于从文件指针中逐字节读取数据,直到遇到null字符为止
     * 这种方式通常用于读取C语言风格的字符串,即以null字符结尾的字符串
     *
     * @access private
     * @param string $data 初始字符串,用于累加读取的字符
     * @return mixed|string 返回读取的字符串.如果文件指针已到达文件末尾或发生错误,则可能返回空字符串
     */
    private function getString($data = '')
    {
        // 从文件指针中读取一个字节
        $char = fread($this->fp, 1);
        // 循环直到遇到null字符（即字符串结束）
        while (ord($char) > 0) {
            // 将读取的字节追加到字符串中
            $data .= $char;
            // 读取下一个字节
            $char = fread($this->fp, 1);
        }
        // 返回累积的字符串
        return $data;
    }

    /**
     * 从文件指针中读取并返回区域信息
     *
     * 此方法用于解析区域信息的存储格式,并根据不同的标志字节读取相应的区域信息
     * 区域信息可能直接跟随在标志字节后,或者在文件的其他位置,需要通过跳转来获取
     *
     * @access private
     * @return mixed|string 返回读取到的区域信息,如果不存在区域信息,则返回空字符串
     */
    private function getArea()
    {
        // 读取标志字节
        $byte = fread($this->fp, 1);
        // 根据标志字节的值进行不同处理
        switch (ord($byte)) {
                // 如果标志字节为0,表示没有区域信息
            case 0:
                $area = '';
                break;
            case 1:
            case 2:
                // 标志字节为1或2,表示区域信息被重定向
                // 跳转到指定位置并读取区域信息
                fseek($this->fp, $this->getLong3());
                $area = $this->getString();
                break;
            default:
                // 如果标志字节为其他值,表示区域信息直接跟随在标志字节后
                $area = $this->getString($byte);
                break;
        }
        return $area;
    }

    /**
     * 析构函数用于在对象销毁时执行必要的操作
     * 当页面执行结束或对象不再被使用时,PHP会自动调用析构函数
     * 在这个析构函数中,我们检查是否存在打开的文件指针
     * 如果存在,说明文件资源仍在使用中,此时应该关闭文件指针以释放资源
     * 文件指针被关闭后,将其设置为0,表示没有打开的文件资源
     */
    public function __destruct()
    {
        // 检查是否存在打开的文件指针
        if ($this->fp) {
            // 关闭文件指针以释放资源
            fclose($this->fp);
        }
        // 将文件指针设置为0,表示没有打开的文件
        $this->fp = 0;
    }
}
