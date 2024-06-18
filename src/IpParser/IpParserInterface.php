<?php

declare(strict_types=1);

namespace think\IpParser;

interface IpParserInterface
{
    /**
     * 设置数据库文件的路径
     * 
     * 本函数用于设定数据库文件的存储路径.这在应用程序需要访问特定路径下的数据库文件时非常有用
     * 通过正确设置此路径,应用程序能够确保数据文件被存储在安全且易于访问的位置
     * 
     * @param string $filePath 数据库文件的路径.此路径应包括文件名和扩展名
     *                        路径可以是绝对路径,也可以是相对于当前工作目录的相对路径
     */
    function setDBPath($filePath);

    /**
     * 获取IP地址的相关信息
     * 
     * 该函数接受一个IP地址作为参数,旨在返回与该IP地址相关的信息
     * 目前函数的实现仅仅是返回了传入的IP地址,但可以根据需求扩展为返回更丰富的信息
     * 如IP地址的地理位置信息、是否为私有IP等
     * 
     * @param string $ip IP地址,可以是IPv4或IPv6格式
     */
    function getIp($ip);
}
