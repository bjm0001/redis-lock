<?php


namespace qiLim\redisLock\Script;


class TryLock extends \Predis\Command\ScriptCommand
{

    protected function getKeysCount()
    {
        return 3;
    }


    /**
     * Notes:
     * User: QiLin
     * @return string
     */
    public function getScript()
    {
        return <<<LUA
local key = KEYS[1]
local threadId = KEYS[2]
local releaseTime = KEYS[3]

if (redis.call("exists", key) == 0) then
    redis.call("hset", key, threadId, '1')
    redis.call("expire", key, releaseTime)
    return true;
end
if (redis.call("hexists", key, threadId) == 1) then
    redis.call("hincrby", key, threadId, '1')
    redis.call("expire", key, releaseTime)
    return true;
end
return false;
LUA;

    }

}