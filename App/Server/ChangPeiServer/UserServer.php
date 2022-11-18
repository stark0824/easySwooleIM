<?php
/**
 * Created by PhpStorm.
 * User: stark
 * Date: 2020-11-12
 * Time: 09:28
 */

namespace App\Server\ChangPeiServer;

use App\Utility\Http\OAuth;
use App\Server\Server;

class UserServer extends Server
{
    public function getUserId(string $token) :int
    {
        $uid = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($token) {
            $uid = $redis->get($token);
            if (!isset($uid) || empty($uid)) {
                //远程验证token
                $uid = OAuth::getUserInfo($token);
                if (isset($uid) && !empty($uid) && intval($uid) > 0) {
                    //存入缓存时间，过期时间小于 7300s
                    $expireTime = 3650 + rand(1, 3000);
                    $redis->setEx($token, $expireTime, $uid);
                }
                return $uid;
            } else {
                return $uid;
            }
        }, self::REDIS_CONN_NAME);
        return intval($uid);
    }


    public function checkSign(string $token) :int
    {
        $uid = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($token) {
            $uid = $redis->get($token);
            if (!isset($uid) || empty($uid)) {
                //远程验证token
                $uid = OAuth::getUserInfo($token);
                if (isset($uid) && !empty($uid) && intval($uid) > 0) {
                    //存入缓存时间，过期时间小于 7300s
                    $expireTime = 3650 + rand(1, 3000);
                    $redis->setEx($token, $expireTime, $uid);
                }
                return $uid;
            } else {
                return $uid;
            }
        }, self::REDIS_CONN_NAME);
        return intval($uid);
    }

}
