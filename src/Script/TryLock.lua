local key = KEYS[1]
local threadId = KEYS[1]
local releaseTime = KEYS[2]

if (redis.call("exists", key) == 0) then
    redis.call("hset", key, threadId, '1')
    redis.call("expire", key, releaseTime)
    return 1
end

if (redis.call("hexists", threadId) == 1) then
    redis.call("hincrby", key, threadId, '1')
    redis.call("expire", key, releaseTime)
    return 1
end
return 0