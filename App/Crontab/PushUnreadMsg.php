<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: 推送评论消息
# +----------------------------------------------------------------------

namespace App\Crontab;

use App\Utility\Ws\Category;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\ServerManager;


class PushUnreadMsg extends AbstractCronTask
{
    const PUSH_UNREAD_NUMBER_All = 'PUSH_UNREAD_NUMBER_All';
    const PUSH_MSG_SSET_USER_LOGIN = 'PUSH_MSG_SSET_USER_LOGIN';
    protected $limit = 1000; //每次执行查询的条数

    public static function getRule(): string
    {
        return '*/1 * * * *';
    }

    public static function getTaskName(): string
    {
        return  'PushUnreadMsg';
    }

    function run( $taskId, $workerIndex)
    {
        $redis = \EasySwoole\RedisPool\RedisPool::defer('redis');
        $server = ServerManager::getInstance()->getSwooleServer();
        //消费队列
        //每分钟消费limit条
        $unreadList = $redis->lRange(self::PUSH_UNREAD_NUMBER_All, 0, $this->limit );

        if(isset($unreadList) && !empty($unreadList) && is_array($unreadList)){
            //根据uid读取出所有建立链接的端，最多5个
            $unreadList = array_unique($unreadList);
            foreach ( $unreadList as $uid ){

                $zUidLists = $redis->zRangeByScore(self::PUSH_MSG_SSET_USER_LOGIN,
                    (int)$uid,(int)$uid,
                    ['withScores' => true, 'limit' => array(0, 5)]
                );

                if(isset($zUidLists) && !empty($zUidLists)){
                    //收到评论数
                    $unReadFromCommentsNumbers  =  $redis->get(Category::_getUnReadFromCommentsKeyName($uid));
                    //系统消息数
                    $unReadMessageNumbers = $redis->get(Category::_getUnReadMessageKeyName($uid));

                    $unReadFromCommentsNumbers = !empty($unReadFromCommentsNumbers) ? intval($unReadFromCommentsNumbers) : 0;
                    $unReadMessageNumbers = !empty($unReadMessageNumbers) ? intval($unReadMessageNumbers) : 0;

                    $body = [
                        'unread_from_comments_numbers' =>  $unReadFromCommentsNumbers,
                        'unread_message_numbers' => $unReadMessageNumbers ,
                        'unread_all' => array_sum([$unReadFromCommentsNumbers,$unReadMessageNumbers])
                    ];

                    $unread_msg_data = [
                        'msg_type' => 14,
                        'code' => 200,
                        'msg' => 'SUCCESS',
                        'body'  => $body
                    ];
                    $fdList = array_keys($zUidLists);
                    foreach ($fdList as $fdKeys){
                        $server->push($fdKeys,json_encode($unread_msg_data));
                    }
                    $redis->lRem(self::PUSH_UNREAD_NUMBER_All, 1, $uid);
                }else{
                    $redis->lRem(self::PUSH_UNREAD_NUMBER_All, 1, $uid);
                }
            }
        }
    }

    function onException(\Throwable $throwable,  $taskId,  $workerIndex)
    {
        echo $throwable->getMessage();
    }

    /**
     * @return string 生成通信唯一的ack标识
     */
    protected function getNoceAck(){
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $noce_ack =// "{"
            substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
        return $noce_ack;
    }
}
