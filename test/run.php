<?php

/**
 * think-ip 离线归属地测试脚本
 *
 * 用法：
 *   php test/run.php            逐条运行 samples.json，与 expected.json 比对并输出 PASS/FAIL
 *   php test/run.php --update   用当前数据库结果重新生成 expected.json
 *   php test/run.php --json     额外打印完整 JSON 结果数组
 *
 * 说明：归属地结果依赖自备的 xdb / 纯真库版本，数据库更新后字段可能变化，
 *       此时用 --update 重新采集 expected.json。
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "未找到 vendor/autoload.php，请先在包根目录执行 composer install。\n");
    exit(2);
}

require $autoload;

use think\IpLocation;

$samplesFile = __DIR__ . '/samples.json';
$expectedFile = __DIR__ . '/expected.json';

$samples = json_decode((string)file_get_contents($samplesFile), true);
if (!is_array($samples)) {
    fwrite(STDERR, "samples.json 解析失败。\n");
    exit(2);
}

// 逐条运行，组装标准化结果
$results = [];
foreach ($samples as $sample) {
    $ip = $sample['ip'];
    $results[] = [
        'ip'       => $ip,
        'category' => isset($sample['category']) ? $sample['category'] : '',
        'note'     => isset($sample['note']) ? $sample['note'] : '',
        'result'   => IpLocation::getLocation($ip),
    ];
}

$update = in_array('--update', $argv, true);
$printJson = in_array('--json', $argv, true);

if ($update) {
    file_put_contents(
        $expectedFile,
        json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
    );
    echo "expected.json 已更新，共 " . count($results) . " 条用例。\n";
    exit(0);
}

if ($printJson) {
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

if (!is_file($expectedFile)) {
    fwrite(STDERR, "expected.json 不存在，请先运行 php test/run.php --update 生成基线结果。\n");
    exit(2);
}

$expected = json_decode((string)file_get_contents($expectedFile), true);
if (!is_array($expected)) {
    fwrite(STDERR, "expected.json 解析失败。\n");
    exit(2);
}

$fail = 0;
foreach ($results as $i => $current) {
    $baseline = isset($expected[$i]) ? $expected[$i]['result'] : null;
    if ($baseline === null || $baseline != $current['result']) {
        $fail++;
        echo "[FAIL] {$current['ip']} ({$current['category']}) {$current['note']}\n";
        echo "  expected: " . json_encode($baseline, JSON_UNESCAPED_UNICODE) . "\n";
        echo "  actual:   " . json_encode($current['result'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "[PASS] {$current['ip']} ({$current['category']}) {$current['note']}\n";
    }
}

echo "\n共 " . count($results) . " 条用例，失败 " . $fail . " 条。\n";
exit($fail > 0 ? 1 : 0);
