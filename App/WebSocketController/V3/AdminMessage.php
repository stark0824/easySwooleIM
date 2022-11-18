<?php

namespace App\WebSocketController\V3;

use Swoole\Websocket\Server;
use \EasySwoole\EasySwoole\Logger;
use App\WebSocketController\Base;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\ORM\DbManager;
use App\Models\{ImChatModel, ImModel};
use App\Utility\Ws\{Result, Category, Params, ParamsCheck};

class AdminMessage extends Base
{
    //客服认证，建立关系
    public function customerAuth()
    {
        //认证token 查看绑定关系
        [$toUid, $syncStamp] = Params::AutoCustomerParams(Category::CLIENT_TYPE_CPADMIN, $this->body['to_uid'], $this->body['syncstamp']);
        //存入缓存
        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $fd = $this->caller()->getClient()->getFd();
        \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($fd, $toUid) {
            $fdRet = $redis->hSet(self::PUSH_CUSTOMER_MSG_SOCKET_FD, $fd, $toUid);
            $sRet = $redis->zAdd(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN, $toUid, $fd);
            Logger::getInstance()->log('存入:PUSH_CUSTOMER_MSG_SSET_USER_LOGIN：' . $sRet . ',存入:PUSH_CUSTOMER_MSG_SOCKET_FD:' . $fdRet, Logger::LOG_LEVEL_INFO, 'pc_login');
        }, self::REDIS_CONN_NAME);

        $result = Result::getCustomerAuthResult(Category::CLIENT_TYPE_CPADMIN, $toUid, $syncStamp);
        $this->messageBase($result);
    }


    public function pushUser()
    {
        [$toUid, $virtualUid, $contents, $syncStamp] = Params::pushUserParams(Category::CLIENT_TYPE_CPADMIN, $this->body['to_uid'], $this->body['virtual_uid'], $this->body['contents'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) {
            $msgErrorRet['msg_type'] = 19;
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = ParamsCheck::checkContents($contents);
        if (!empty($msgErrorRet)) {
            $msgErrorRet['msg_type'] = 19;
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = ParamsCheck::checkVirtualUid($virtualUid);
        if (!empty($msgErrorRet)) {
            $msgErrorRet['msg_type'] = 19;
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $imStatus = true;  //假设用户已经建立链接
        $imUserJson = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            $bool = $redis->get(Category::$imUserRelationName . $toUid);
            return $bool;
        }, self::REDIS_CONN_NAME);

        $imUserArray = json_decode($imUserJson, true);
        $imUserArray = empty($imUserArray) ? [] : $imUserArray;

        if (empty($imUserArray)) {
            //如果redis不存在
            $imUserArray = DbManager::getInstance()->invoke(function ($client) use ($toUid) {
                $model = ImModel::invoke($client);
                $where = ['to_uid' => $toUid];
                $imUserArray = $model->field(['virtual_uid', 'im_rid'])->where($where)->order('im_rid', 'desc')->limit(0, 1)->all()->toArray();
                $data = empty($imUserArray[0]) ? [] : $imUserArray[0];
                return $data;
            }, self::MYSQL_CONN_NAME);
            $imStatus = false;
        }

        $msgErrorRet = ParamsCheck::checkDiffManger($virtualUid, $imUserArray['virtual_uid']);
        if (!empty($msgErrorRet)) {
            $msgErrorRet['msg_type'] = 19;
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        if (empty($imUserArray)) {
            $result = Result::pushUserResult(Category::CLIENT_TYPE_CPADMIN, 500, $syncStamp);
            $this->messageBase($result);
            return true;
        }

        $redis = \EasySwoole\RedisPool\RedisPool::defer('redis');
        $server = ServerManager::getInstance()->getSwooleServer();

        $zUidLists = $redis->zRangeByScore(self::PUSH_MSG_SSET_USER_LOGIN,
            (int)$toUid, (int)$toUid,
            ['withScores' => true, 'limit' => array(0, 5)]
        );

        //未读消息数+1
        $redis->incr(Category::_getUnReadClientKeyName($toUid));

        $ack = $this->getNoceAck();
        $data = [
            'virtual_uid' => (int)$imUserArray['virtual_uid'],
            'to_uid' => $toUid,
            'send_uid' => (int)$imUserArray['virtual_uid'],
            'receive_uid' => $toUid,
            'im_contents' => $contents,
            'is_read' => 1,
            'is_sent' => $imStatus === false ? 3 : 4,
            'client_type' => Category::CLIENT_TYPE_CPADMIN,
            'im_rid' => (int)$imUserArray['im_rid'],
            'create_time' => time(),
            'noce_ack' => $ack
        ];

        //推送成功，入库
        $tableName = $this->_getChatTableName($toUid);
        $imChatId = DbManager::getInstance()->invoke(function ($client) use ($data, $tableName) {
            $model = ImChatModel::invoke($client, $data);
            return $model->tableName($tableName)->save();
        }, self::MYSQL_CONN_NAME);

        $result = [];
        if (isset($zUidLists) && !empty($zUidLists)) {

            if ($imStatus === true) {
                $result = [
                    'client_type' => Category::CLIENT_TYPE_CPADMIN,
                    'msg_type' => 51,
                    'code' => 200,
                    'msg' => 'SUCCESS',
                    'syncstamp' => $syncStamp,
                    'body' => [
                        'to_uid' => $toUid,
                        'contents' => $contents,
                        'noce_ack' => $ack
                    ]
                ];
            } else if ($imStatus === false) {
                $unReadAll = $redis->get(Category::_getUnReadClientKeyName($toUid));
                $result = [
                    'client_type' => Category::CLIENT_TYPE_CPADMIN,
                    'msg_type' => 25,
                    'code' => 200,
                    'msg' => 'SUCCESS',
                    'syncstamp' => $syncStamp,
                    'body' => [
                        'unread' => 1,
                        'unreadall' => $unReadAll
                    ]
                ];
            }

            $fdList = array_keys($zUidLists);
            foreach ($fdList as $fdKeys) {
                $info = $server->getClientInfo($fdKeys);
                if ($info && $info['websocket_status'] == 3) {
                    $bool = $server->push($fdKeys, json_encode($result));
                    //推送成功后，移除元素
                    if ($bool) {
                        $ret[] = $bool;
                    }
                }
            }
        }

        if (($data['is_sent'] == 4) && $imChatId) {
            $code = 200;
        } else {
            $code = 503;
        }

        $result = Result::pushUserResult(Category::CLIENT_TYPE_CPADMIN, $code, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    public function customerHeart()
    {
        $result = [
            'client_type' => Category::CLIENT_TYPE_CPADMIN,
            'msg_type' => 1,
            'code' => 200,
            'msg' => 'SUCCESS',
            'syncstamp' => time(),

        ];
        $server = ServerManager::getInstance()->getSwooleServer();
        $fd = $this->caller()->getClient()->getFd();
        $server->push($fd, json_encode($result));
    }
}
