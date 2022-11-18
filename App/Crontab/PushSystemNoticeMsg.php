<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: 推送系统通知消息 - 计划任务
# +----------------------------------------------------------------------

namespace App\Crontab;

use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\ServerManager;

class PushSystemNoticeMsg extends AbstractCronTask
{
    const REDIS_CONN_NAME = 'redis';
    const  PUSH_MSG_NOTICE_SYSTEM = 'PUSH_MSG_NOTICE_SYSTEM';
    protected $pushMsg = [
                "msg_type" => 6,
                "code" => 200,
                "msg" => 'SUCCESS',
                'noce_ack' => '',
                "body" => []
            ];
    protected $limit = 100;
    protected $len = 1;

    public static function getRule(): string
    {
        return '*/5 * * * *';
    }

    public static function getTaskName(): string
    {
        return  'PushSystemNoticeMsg';
    }

    function run( $taskId, $workerIndex)
    {

        $json = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
            return $redis->lPop(self::PUSH_MSG_NOTICE_SYSTEM);
        }, self::REDIS_CONN_NAME);

        if(isset($json) && !empty($json)){
            $body = [];
            //全员消息通知
            $server = ServerManager::getInstance()->getSwooleServer();
            $start_fd = 0;
            while(true)
            {
                $conn_list = $server->getClientList( $start_fd, $this->limit );
                if ($conn_list===false || count($conn_list) === 0 || empty($conn_list))
                {
                    break;
                }
                $start_fd = end($conn_list);
                foreach ($conn_list as $fd){
                    $info = $server->getClientInfo($fd);
                    if ($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME) {
                        $server->push($fd, json_encode($this->pushMsg));
                    }
                }
            }
        }
    }

    function onException(\Throwable $throwable, $taskId, $workerIndex)
    {
        echo $throwable->getMessage();
    }

}
