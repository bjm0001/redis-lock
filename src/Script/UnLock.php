<?php


namespace qiLim\redisLock\Script;


class UnLock extends \Predis\Command\ScriptCommand
{
    protected function getKeysCount()
    {
        return 3;
    }

    public function getScript()
    {
        return <<<LUA
local key = KEYS[1]
local threadId = KEYS[2]
local releaseTime = KEYS[3]
-- 验证是否是当前线程的锁
if (redis.call('hexists', key, threadId) == 0) then
    return 0;
end
local count = redis.call('hincrby', key, threadId, '-1')

if (count > 0) then
    redis.call('expire', key, releaseTime);
    return count;
end
redis.call('del', key);
return 0;
LUA;

    }

}