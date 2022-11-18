<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: 全部作者系统通知消息 - 计划任务
# +----------------------------------------------------------------------

namespace App\Crontab;

use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Redis\Redis;
use \EasySwoole\RedisPool\RedisPool;
use Throwable;

class PushAllAuthorNoticeMsg extends AbstractCronTask
{
    const REDIS_CONN_NAME = 'redis';
    const  PUSH_MSG_AUTHOR_LIST = 'PUSH_MSG_AUTHOR_LIST';
    const PUSH_MSG_AUTHOR_NOTICE_SYSTEM = 'PUSH_MSG_AUTHOR_NOTICE_SYSTEM';

    protected $pushMsg = [
        "msg_type" => 6,
        "code" => 200,
        "msg" => 'SUCCESS',
        'noce_ack' => '',
        "body" => []
    ];
    protected $limit = 1000;

    public static function getRule(): string
    {
        return '*/5 * * * *';
    }

    public static function getTaskName(): string
    {
        return 'PushAllAuthorNoticeMsg';
    }

    function run($taskId, $workerIndex)
    {
        $json = RedisPool::invoke(function (Redis $redis) {
            return $redis->lPop(self::PUSH_MSG_AUTHOR_LIST);
        }, self::REDIS_CONN_NAME);

        $redis = RedisPool::defer('redis');
        //$body = [];
        if (isset($json) && !empty($json)) {
            //全员消息通知
            $server = ServerManager::getInstance()->getSwooleServer();
            $start = 0;
            $limit = 1000;
            while (true) {
                $list = $redis->zRange(self::PUSH_MSG_AUTHOR_NOTICE_SYSTEM, $start, $limit);
                if (!$list) {
                    $redis->lRem(self::PUSH_MSG_AUTHOR_LIST, 1, $json);
                    break;
                }
                
                foreach ($list as $fd) {
                    $info = $server->getClientInfo($fd);
                    if ($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME) {
                        $server->push($fd, json_encode($this->pushMsg));
                    }
                }
                $start += $limit;
                $limit = $start + $limit;
            }
        }
    }

    function onException(Throwable $throwable, $taskId, $workerIndex)
    {
        echo $throwable->getMessage();
    }

}
