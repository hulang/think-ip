<?php

declare(strict_types=1);

namespace think\IpParser;

interface IpParserInterface
{
    function setDBPath($filePath);
    /**
     * @param $ip
     * @return mixed ['ip', 'country', 'area']
     */
    function getIp($ip);
}
