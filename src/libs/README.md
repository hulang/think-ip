# 离线 IP 数据文件放置目录（不随包分发）

将自备的 `ip2region_v4.xdb` / `ip2region_v6.xdb` 放到本目录；
如需 IPv4 走纯真库，再自备 `qqwry.dat`（仅 IPv4，IPv6 不使用纯真 `ipv6wry.db`），
或在 `config/config.php` 中配置为绝对路径。
