<?php

namespace qiLim\redisLock;


use Pheanstalk\Pheanstalk;

/**
 * @property $redisInstance \Predis\Client;
 * Class lock
 * @package qiLin\redisLock
 */
class lock
{
    private static $instance = null, $redisInstance = null, $beanstalkInstance = null;

    public $message = '';

    const REDIS_CONFIG = [
        'scheme' => 'tcp',
        'host' => 'redis',
        'port' => 6379
    ];

    const BEANSTALK_CONFIG = 'beanstalkd';

    /**
     * 通道名称
     */
    const CHANNEL_NAME = "control_channel";
    /**
     * 退出指定
     */
    const CONTROL_QUIT_COMMAND = "quit_loop";

    private function __construct(array $options)
    {
        self::$redisInstance = $this->createRedisInstance(isset($options['redis']) && is_array($options['redis']) ? $options['redis'] : self::REDIS_CONFIG);
        self::$beanstalkInstance = $this->createBeanstalkInstance(isset($options['beanstalkd']) && $options['beanstalkd'] ? $options['beanstalkd'] : self::BEANSTALK_CONFIG);
    }

    private function createRedisInstance(array $config = [])
    {
        $redis = new \Predis\Client($config ?: self::REDIS_CONFIG);
        $redis->getProfile()->defineCommand('unlock', 'qiLim\redisLock\Script\UnLock');
        $redis->getProfile()->defineCommand('lock', 'qiLim\redisLock\Script\TryLock');
        return $redis;
    }

    private function createBeanstalkInstance($host)
    {
        return Pheanstalk::create($host ?: self::BEANSTALK_CONFIG);
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    public static function getInstance(array $options = []): lock
    {
        if (is_null(self::$instance)) {
            return self::$instance = new self($options);
        }
        return self::$instance;

    }

    public function getRedisInstance()
    {
        return self::$redisInstance;
    }

    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Notes:
     * User: QiLin
     * Date: 2020/10/18 0018
     * Time: 11:18
     * @param string $lockKey
     * @param string $threadId
     * @param string $releaseTime
     * @return int
     */
    public function lock(string $lockKey, string $threadId, string $releaseTime)
    {
        $result = self::$redisInstance->lock($lockKey, $threadId, $releaseTime);
        if ($result) {
            return (int)$result;
        }
        //todo 通过redis消息订阅实现会存在一个问题，在高并发的情况下，发布消息的客户端比订阅的客户端先打开先发布消息 ，而订阅的客户端还未打开，则消息发布出去，存在死锁
//        echo "订阅消息...." . PHP_EOL;
//        $client = self::createRedisInstance(self::REDIS_CONFIG + array('read_write_timeout' => 0));
//        $pubSub = $client->pubSubLoop();
//        $pubSub->subscribe(self::CHANNEL_NAME);
//        foreach ($pubSub as $message) {
//            switch ($message->channel) {
//                case 'control_channel':
//                    if ($message->payload == self::CONTROL_QUIT_COMMAND) {
//                        echo "得到订阅消息：{$message->payload}" . PHP_EOL;
//                        $pubSub->unsubscribe();
//                    }
//                    break;
//            }
//        }
//        unset($pubSub);

        self::$beanstalkInstance->watch(self::CHANNEL_NAME);
        $job = self::$beanstalkInstance->reserve();
        try {
            $jobPayload = $job->getData();
            if ($jobPayload && $jobPayload == self::CONTROL_QUIT_COMMAND) {
                self::$beanstalkInstance->delete($job);
            }
        } catch (\Exception $e) {
            // handle exception.
            // and let some other worker retry.
            self::$beanstalkInstance->release($job);
        }
        return $this->lock($lockKey, $threadId, $releaseTime);
    }

    /**
     * Notes:
     * User: QiLin
     * Date: 2020/10/18 0018
     * Time: 11:16
     * @param string $lockKey
     * @param string $threadId
     * @param string $releaseTime
     * @return bool|int 0代表没有未提交的锁，大于0代码还有几把锁未提交 false代表向通道消息发送失败
     */
    public function unLock(string $lockKey, string $threadId, string $releaseTime)
    {
        $result = self::$redisInstance->unlock($lockKey, $threadId, $releaseTime);
        if ($result) {
            return $result;
        }
//        $sendMsg = self::$redisInstance->publish(self::CHANNEL_NAME, self::CONTROL_QUIT_COMMAND);
        self::$beanstalkInstance->useTube(self::CHANNEL_NAME)->put(self::CONTROL_QUIT_COMMAND, Pheanstalk::DEFAULT_PRIORITY, 0, 3);
        return 0;
    }
}