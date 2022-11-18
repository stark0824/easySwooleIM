<?php

namespace App\WebSocketController\V4;

use Swoole\Websocket\Server;
use App\WebSocketController\Base;
use App\Models\{ImChatModel, ImModel};
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\ORM\DbManager;
use App\Utility\Ws\{Params, ParamsCheck, Result, Category};

class AdminMessage extends Base
{
    public function pullOfflineMsg()
    {
        [$syncStamp] = Params::pullOfflineMsgParams(Category::CLIENT_TYPE_CPADMIN, $this->body['syncstamp']);

        //查询离线消息
        [$offLists, $virtualUid] = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
            $number = $redis->lLen(self::PUSH_MSG_OFFLINE_LISTS);
            $offLists = $redis->lRange(self::PUSH_MSG_OFFLINE_LISTS, 0, $number);
            $virtualUid = $redis->zRangeByScore(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN,
                (int)66666666, (int)66669666,
                ['withScores' => true, 'limit' => array(0, 20)]
            );
            return [$offLists, $virtualUid];
        }, self::REDIS_CONN_NAME);

        if (!empty($offLists) && is_array($offLists)) {
            $pullData = [];
            //把离线消息按照每个人来重新组装
            foreach ($offLists as $json) {
                $chatDetails = json_decode($json, true);
                $pullData[$chatDetails['to_uid']][] = $chatDetails;
            }
        }

        //验证客服管理员在线
        $vUid = [];
        $server = ServerManager::getInstance()->getSwooleServer();
        foreach ($virtualUid as $fd => $vid){
            $info = $server->getClientInfo($fd);
            if ($info && $info['websocket_status'] == 3) {
                $vUid[$fd] = $vid;
            }
        }

        $result = [
            'client_type' => 3,
            'msg_type' => 52,
            'code' => 200,
            'msg' => 'SUCCESS',
            'syncstamp' => time()
        ];

        if (!empty($pullData) && !empty($vUid)) {
            $uIds = array_keys($pullData);
            $row = ceil(count($uIds) / count($vUid));
            $share = array_chunk($uIds, $row, true);
            $keyDict = $vUid;
            $pushList = [];
            foreach ($share as $key => $offlineIndex) {
                $first = array_shift($vUid);
                $fd = array_search($first, $keyDict);
                foreach ($offlineIndex as $subKey => $uid) {
                    $number = 1;
                    foreach ($pullData[$uid] as $msgInfo) {
                        $body['to_uid'] = $msgInfo['to_uid'];
                        $body['contents'] = $msgInfo['contents'];
                        $body['avatar'] = $msgInfo['user_head'];
                        $body['username'] = $msgInfo['user_nickname'];
                        $body['unread'] = $number;
                        $body['chat_id'] = $msgInfo['chat_id'];
                        $pushList[$fd][$uid][] = $body;
                        $number++;
                    }
                }
            }

            foreach ( $pushList as $fd => $msgList){
                foreach ($msgList as $msgData){
                    foreach ($msgData as $msgUserData){
                        $result['body'] = $msgUserData;
                        $server->push($fd, json_encode($result));
                    }
                }
            }
        }
        //返回结果进行处理
        $result = Result::pullOfflineMsgResult(Category::CLIENT_TYPE_CPADMIN, $syncStamp);
        $this->messageBase($result);
    }

    public function readOffLineMsg()
    {
        [$toUid, $chatId, $vUid, $syncStamp] = Params::ReadOfflineParams(Category::CLIENT_TYPE_CPADMIN, $this->body['to_uid'], $this->body['chat_id'], $this->body['vuid'], $this->body['syncstamp']);

        $userHead = $this->body['user_head'] ?? '';
        $userNickname = $this->body['user_nickname'] ?? '';

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkChatid($chatId);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkVirtualUid($vUid);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkNickNameAndHead($userNickname, $userHead);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));


        $tableName = $this->_getChatTableName($toUid);
        $data = DbManager::getInstance()->invoke(function ($client) use ($chatId, $tableName) {
            $model = ImChatModel::invoke($client);
            $obj = $model->field(['im_contents', 'noce_ack', 'create_time', 'client_type'])->where(['chat_id' => $chatId])->tableName($tableName)->get();
            $data = empty($obj) ? [] : $obj->toArray();
            return $data;
        }, self::MYSQL_CONN_NAME);

        if (empty($data)) {
            //错误返回
            $msgErrorRet['code'] = 416;
            $msgErrorRet['msg'] = 'chatId error';
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $imUserArray = DbManager::getInstance()->invoke(function ($client) use ($toUid, $vUid) {
            $model = ImModel::invoke($client);
            $where = ['to_uid' => $toUid, 'virtual_uid' => $vUid];
            $imUserArray = $model->field(['virtual_uid', 'im_rid'])->where($where)->order('im_rid', 'desc')->limit(0, 1)->all()->toArray();
            $data = empty($imUserArray[0]) ? [] : $imUserArray[0];
            return $data;
        }, self::MYSQL_CONN_NAME);


        if (empty($imUserArray)) {
            $imData = [
                'virtual_uid' => $vUid,
                'to_uid' => $toUid,
                'create_time' => time()
            ];

            $imRid = DbManager::getInstance()->invoke(function ($client) use ($imData) {
                $model = ImModel::invoke($client, $imData);
                $imRid = $model->save();
                return $imRid;
            }, self::MYSQL_CONN_NAME);
        } else {
            $imRid = $imUserArray['im_rid'];
        }

        $rows = DbManager::getInstance()->invoke(function ($client) use ($chatId, $vUid, $imRid, $tableName) {
            $model = ImChatModel::invoke($client);
            return $model->where(['chat_id' => $chatId])->tableName($tableName)->update(['is_sent' => 2, 'virtual_uid' => $vUid, 'receive_uid' => $vUid, 'im_rid' => $imRid]);
        }, self::MYSQL_CONN_NAME);


        if ($rows) {
            $body['to_uid'] = $toUid;
            $body['contents'] = $data['im_contents'];
            $body['user_head'] = $userHead;
            $body['user_nickname'] = $userNickname;
            $body['chat_id'] = $chatId;
            ksort($body);
            \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($body) {
                $redis->lRem(self::PUSH_MSG_OFFLINE_LISTS, 1, json_encode($body));
            }, self::REDIS_CONN_NAME);

        }
        $result = Result::getReadOfflineMsgResult(Category::CLIENT_TYPE_CPADMIN, $syncStamp);
        $this->messageBase($result);

    }

    public function readNumberMsg()
    {
        [$toUid, $syncStamp] = Params::ReadNumberParams(Category::CLIENT_TYPE_CPADMIN, $this->body['to_uid'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));


        \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            //收到评论数
            $redis->set(Category::_getUnReadServerKeyName($toUid), 0);
        }, self::REDIS_CONN_NAME);

        $result = Result::getReadNumberMsgResult(Category::CLIENT_TYPE_CPADMIN, $toUid, $syncStamp);
        $this->messageBase($result);
    }

    public function readOnLineMsg()
    {
        [$toUid, $chatId, $vUid, $syncStamp] = Params::ReadOnlineParams(Category::CLIENT_TYPE_CPADMIN, $this->body['to_uid'], $this->body['chat_id'], $this->body['vuid'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkChatid($chatId);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkVirtualUid($vUid);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $tableName = $this->_getChatTableName($toUid);
        $data = DbManager::getInstance()->invoke(function ($client) use ($chatId, $tableName) {
            $model = ImChatModel::invoke($client);
            $obj = $model->field(['im_contents'])->where(['chat_id' => $chatId])->tableName($tableName)->get();
            $data = empty($obj) ? [] : $obj->toArray();
            return $data;
        }, self::MYSQL_CONN_NAME);

        if (empty($data)) {
            $msgErrorRet['code'] = 416;
            $msgErrorRet['msg'] = 'chatId error';
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $result = Result::getReadOnlineMsgResult(Category::CLIENT_TYPE_CPADMIN, $syncStamp);
        $this->messageBase($result);
    }
}
