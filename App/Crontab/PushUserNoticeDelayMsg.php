<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: 推送用户个人消息
# +----------------------------------------------------------------------

namespace App\Crontab;

use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\ServerManager;


class PushUserNoticeDelayMsg extends AbstractCronTask
{
    const PUSH_MSG_SSET_USER_LOGIN = 'PUSH_MSG_SSET_USER_LOGIN';
    const PUSH_MSG_NOTICE_DELAY_LIST = 'PUSH_MSG_NOTICE_DELAY_LIST';

    protected $limit = 1000; //每次执行查询的条数
    protected $timeOut =  1296000;   //15天

    public static function getRule(): string
    {
        return '*/11 * * * *';
    }

    public static function getTaskName(): string
    {
        return  'PushUserNoticeDelayMsg';
    }

    function run( $taskId, $workerIndex)
    {
        $redis = \EasySwoole\RedisPool\RedisPool::defer('redis');
        $server = ServerManager::getInstance()->getSwooleServer();
        //消费队列
        $data = $redis->lRange(self::PUSH_MSG_NOTICE_DELAY_LIST, 0, $this->limit );
        if (!empty($data) && is_array($data)){
            $pushLists = [];
            $lRemList = [];
            foreach ($data as $json){
                $msgPushInfo = json_decode($json,true);
                if(isset($msgPushInfo['to_uid']) && !empty($msgPushInfo['to_uid'])){
                    $delayList = [];
                    $diff_unix_times = time() - $msgPushInfo['create_time'];
                    if( $diff_unix_times > $this->timeOut  ){
                        $delayList[] = $json;
                    }else{
                        //查询消息是否已经存在
                        $lRemList[$msgPushInfo['to_uid']][] = $json;
                        $pushLists[$msgPushInfo['to_uid']]['uid'] = $msgPushInfo['to_uid'];
                        $pushLists[$msgPushInfo['to_uid']]['noce_ack'] = $msgPushInfo['noce_ack'];
                        if(isset($msgPushInfo['msg'])){
                            $pushLists[$msgPushInfo['to_uid']]['msg'] = json_decode($msgPushInfo['msg'],true);
                        }
                    }
                } else {
                    $redis->lRem(self::PUSH_MSG_NOTICE_DELAY_LIST, 0, $json);
                }
            }

            if(isset($pushLists) && !empty($pushLists)){
                foreach ($pushLists as $value){
                    $body['content'] = $value['msg']['content'];
                    $body['send_id'] = intval($value['msg']['send_id']);
                    $body['model_type'] = isset($value['msg']['model_type']) ? intval($value['msg']['model_type']) : 1;
                    $pushMsg = [
                        "msg_type" => 6,
                        "code" => 200,
                        "msg" => 'SUCCESS',
                        "body" => $body,
                        'noce_ack' => $value['noce_ack']
                    ];
                    $zUidLists = $redis->zRangeByScore(self::PUSH_MSG_SSET_USER_LOGIN,
                        (int)$value['uid'],(int)$value['uid'],
                        ['withScores' => true, 'limit' => array(0, 5)]
                    );
                    if(isset($zUidLists) && !empty($zUidLists)){
                        $fdList = array_keys($zUidLists);
                        foreach ($fdList as $fdKeys){
                            $ret = [];
                            $bool = $server->push($fdKeys,json_encode($pushMsg));
                            //推送成功后，移除元素
                            if($bool){
                                $ret[] = $bool;
                            }
                        }
                        //推送成功后，移除元素
                        if(!empty($ret) ||count($fdList) >=2 ){
                            foreach ($lRemList[$value['uid']] as $lVal) {
                                $redis->lRem(self::PUSH_MSG_NOTICE_DELAY_LIST, 1, $lVal);
                            }
                        }
                    }
                }
            }


            //对超时数据进行处理
            if(isset($delayList) && !empty($delayList)){
                foreach ($delayList as $delValue){
                    $redis->lRem(self::PUSH_MSG_NOTICE_DELAY_LIST, 0, $delValue);
                }
            }
        }
    }

    function onException(\Throwable $throwable,  $taskId,  $workerIndex)
    {
        echo $throwable->getMessage();
    }
}
