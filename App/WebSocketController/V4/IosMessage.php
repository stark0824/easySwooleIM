<?php

namespace App\WebSocketController\V4;

use Swoole\Websocket\Server;
use App\WebSocketController\Base;
use App\Models\ImChatModel;
use EasySwoole\ORM\DbManager;
use App\Utility\Ws\{Params, ParamsCheck, Result, Category};
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;

class IosMessage extends Base
{
    /**
     * 客户端发送给服务端的离线消息
     */
    public function pushOffLineMsg()
    {
        [$toUid, $contents, $userNickname, $userHead, $syncStamp] = Params::pushOffLineParams(
            Category::CLIENT_TYPE_IOS,
            $this->body['to_uid'],
            $this->body['contents'],
            $this->body['user_head'],
            $this->body['user_nickname'],
            $this->body['syncstamp']
        );

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkContents($contents);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkNickNameAndHead($userNickname, $userHead);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        //相同时间戳，加了2秒的锁，以防止异常
        [$imUserRelationJson,$offMsgLock] = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            $imUserRelationJson = $redis->get(Category::$imUserRelationName . $toUid);
            $offMsgLock = $redis->get('offMsgLock_' . $toUid);
            return [$imUserRelationJson,$offMsgLock];
        }, self::REDIS_CONN_NAME);


        if($offMsgLock){
            $result = Result::getOfflineResult(Category::CLIENT_TYPE_IOS, 200, $syncStamp);
            $this->messageBase($result);
            return true;
        }

        \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid,$contents,$syncStamp) {
            $msgMd5 = md5($toUid.$contents.$syncStamp);
            $redis->setEx('offMsgLock_' . $toUid,2,$msgMd5);
        }, self::REDIS_CONN_NAME);

        if ($imUserRelationJson) {
            //关系存在，说明客服重新上线
            $imArr = json_decode($imUserRelationJson, true);

            if ($imArr['virtual_uid'] && $imArr['virtual_uid'] > 0) {
                //检测是否有在线客服
                $virtualUid = intval($imArr['virtual_uid']);
                $customerStatus = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($virtualUid) {
                    return $redis->zRangeByScore(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN,
                        $virtualUid, $virtualUid,
                        ['withScores' => true, 'limit' => array(0, 20)]
                    );
                }, self::REDIS_CONN_NAME);

                $server = ServerManager::getInstance()->getSwooleServer();
                $onlineCustomer = [];
                foreach ($customerStatus as $fd => $uVid) {
                    //检测是否在线
                    $info = $server->getClientInfo($fd);
                    if (3 == $info['websocket_status']) {
                        $onlineCustomer[] = $uVid;
                    }
                }

                if ($onlineCustomer) {
                    $result = Result::getOfflineResult(Category::CLIENT_TYPE_IOS, 307, $syncStamp);
                    $this->messageBase($result);
                    return true;
                }
            }
        }

        $data = [
            'virtual_uid' => 0,
            'to_uid' => $toUid,
            'send_uid' => $toUid,
            'receive_uid' => 0,
            'im_contents' => $contents,
            'is_sent' => 4,
            'client_type' => Category::CLIENT_TYPE_IOS,
            'create_time' => time(),
            'noce_ack' => $this->getNoceAck()
        ];

        $tableName = $this->_getChatTableName($toUid);
        $imChatId = DbManager::getInstance()->invoke(function ($client) use ($data, $tableName) {
            $model = ImChatModel::invoke($client, $data);
            return $model->tableName($tableName)->save();
        }, self::MYSQL_CONN_NAME);

        if ($imChatId) {
            \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
                $redis->incr(Category::_getUnReadServerKeyName($toUid));
            }, self::REDIS_CONN_NAME);

            //需要发送的消息体
            $body['to_uid'] = $toUid;
            $body['contents'] = $contents;
            $body['user_head'] = $userHead;
            $body['user_nickname'] = $userNickname;
            $body['chat_id'] = $imChatId;
            ksort($body);
            //查询是否存在链接关系

            \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($body) {
                $redis->rPush(self::PUSH_MSG_OFFLINE_LISTS, json_encode($body));
            }, self::REDIS_CONN_NAME);

            $code = 200;
        } else {
            $code = 500;
        }

        $result = Result::getOfflineResult(Category::CLIENT_TYPE_IOS, $code, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * 在线消息收到确认接口
     */
    public function chatReceived()
    {
        [$toUid, $noceAck,$syncStamp] = Params::ReceivedParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['noce_ack'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkUid($toUid);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        //查询是否存在链接关系
        $tableName = $this->_getChatTableName($toUid);
        TaskManager::getInstance()->async(function () use ($toUid, $tableName,$noceAck) {
            DbManager::getInstance()->invoke(function ($client) use ($toUid, $tableName,$noceAck) {
                $model = ImChatModel::invoke($client);
                $where = ['to_uid' => $toUid, 'noce_ack' => $noceAck ];
                $model->tableName($tableName)->where($where)->update(['is_sent' => 2]);
            }, self::MYSQL_CONN_NAME);
        });

        \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            $redis->set(Category::_getUnReadClientKeyName($toUid), 0);
        }, self::REDIS_CONN_NAME);

        $result = Result::getReadOffLineMsg(Category::CLIENT_TYPE_IOS, 200, $syncStamp);
        $this->messageBase($result);
        return true;
    }


}
