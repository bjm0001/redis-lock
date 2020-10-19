--
-- Created by IntelliJ IDEA.
-- User: Administrator
-- Date: 2020/10/16 0016
-- Time: 14:10
-- To change this template use File | Settings | File Templates.
--
local key = KEYS[1]
local threadId = KEYS[2]
local releaseTime = KEYS[3]

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

