<?php
/**
 * Created by PhpStorm.
 * User: stark
 * Date: 2020-11-12
 * Time: 09:28
 */

namespace App\Server\RedisServer;

use App\Server\Server;
use \EasySwoole\EasySwoole\Logger;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

class FdServer extends Server
{

    public function setSocketFd(int $fd, int $uid)
    {
        RedisPool::invoke(function (Redis $redis) use ($fd, $uid) {
            $fdRet = $redis->hSet(self::PUSH_MSG_SOCKET_FD, $fd, $uid);
            $sRet = $redis->zAdd(self::PUSH_MSG_SSET_USER_LOGIN, $uid, $fd);
            $log = ['PUSH_MSG_SOCKET_FD' => (string)$fdRet, 'PUSH_MSG_SSET_USER_LOGIN' => (string)$sRet];
            Logger::getInstance()->log('执行结果:' . json_encode($log), Logger::LOG_LEVEL_INFO, 'setSocketFd');
        }, self::REDIS_CONN_NAME);
    }


    public function getSocketUid(int $fd): int
    {
        $fUid = RedisPool::invoke(function (Redis $redis) use ($fd) {
            $fUid = $redis->hGet(self::PUSH_MSG_SOCKET_FD, $fd);
            $log = ['PUSH_MSG_SOCKET_FD' => $fUid];
            Logger::getInstance()->log('执行结果:' . json_encode($log), Logger::LOG_LEVEL_INFO, 'getSocketUid');
            return $fUid;
        }, self::REDIS_CONN_NAME);
        return intval($fUid) ?? 0;
    }

    public function recoverySocketFd(int $fd)
    {
        RedisPool::invoke(function (Redis $redis) use ($fd) {
            $zRemDel = $redis->zRem(self::PUSH_MSG_SSET_USER_LOGIN, $fd);
            $msgDel = $redis->hDel(self::PUSH_MSG_SOCKET_FD, $fd);
            $log = ['PUSH_MSG_SOCKET_FD' => (string)$msgDel, 'PUSH_MSG_SSET_USER_LOGIN' => (string)$zRemDel];
            Logger::getInstance()->log('执行结果:' . json_encode($log), Logger::LOG_LEVEL_INFO, 'recoverySocketFd');
        }, self::REDIS_CONN_NAME);
    }

    public function setAuthorFd(int $uid, int $fd)
    {
        RedisPool::invoke(function (Redis $redis) use ($fd, $uid) {
            $sRet = $redis->zAdd(self::PUSH_MSG_AUTHOR_NOTICE_SYSTEM, $uid, $fd);
            $log = ['PUSH_MSG_AUTHOR_NOTICE_SYSTEM' => (string)$sRet];
            Logger::getInstance()->log('执行结果:' . json_encode($log), Logger::LOG_LEVEL_INFO, 'setAuthorFd');
        }, self::REDIS_CONN_NAME);
    }

    public function setSignAuthorFd(int $uid, int $fd)
    {
        RedisPool::invoke(function (Redis $redis) use ($fd, $uid) {
            $sRet = $redis->zAdd(self::PUSH_MSG_SIGN_AUTHOR_NOTICE_SYSTEM, $uid, $fd);
            $log = ['PUSH_MSG_SIGN_AUTHOR_NOTICE_SYSTEM' => (string)$sRet];
            Logger::getInstance()->log('执行结果:' . json_encode($log), Logger::LOG_LEVEL_INFO, 'setSignAuthorFd');
        }, self::REDIS_CONN_NAME);
    }


    public function recoveryAuthorFd(int $fd,int $identity)
    {
        RedisPool::invoke(function (Redis $redis) use ($fd,$identity) {
            $zRemDel = $redis->zRem(self::PUSH_MSG_AUTHOR_NOTICE_SYSTEM, $fd);
            if(2 == $identity){
                $zSignRemDel = $redis->zRem(self::PUSH_MSG_SIGN_AUTHOR_NOTICE_SYSTEM, $fd);
            }
            $log = ['PUSH_MSG_AUTHOR_NOTICE_SYSTEM' => (string)$zRemDel];
            Logger::getInstance()->log('执行结果:' . json_encode($log), Logger::LOG_LEVEL_INFO, 'recoveryAuthorFd');
        }, self::REDIS_CONN_NAME);
    }

}
