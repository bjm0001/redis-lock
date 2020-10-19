<?php
include_once '../vendor/autoload.php';
$pid = getmypid();

$lock = \qiLim\redisLock\lock::getInstance();

//1.1 获取锁
$result = $lock->lock('lock', $pid, '300');
$stock = $lock->getRedisInstance()->get('stock');

//2.验证库存是否充足
if ($result && $stock <= 0) {
    $result = $lock->unLock('lock', $pid, '300');
    echo "扣减失败，库存不足";
    die();
}
// 3. 减少库存
$stock = $lock->getRedisInstance()->decrby('stock', '1');
echo $msg = "扣减成功，库存{$stock}" . PHP_EOL;
//error_log($msg, '3', "a.log");

//4. 回收锁
$result = $lock->unLock('lock', $pid, '300');
