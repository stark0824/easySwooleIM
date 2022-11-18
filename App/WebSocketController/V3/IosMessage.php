<?php

namespace App\WebSocketController\V3;

use Swoole\Websocket\Server;
use App\Utility\Http\OAuth;
use App\WebSocketController\Base;
use EasySwoole\ORM\DbManager;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use App\Utility\Ws\{Params, ParamsCheck, Result, Category, Robot};
use App\Models\{ImModel, ImChatModel};

class IosMessage extends Base
{
    /**
     * 自动回复列表
     */
    public function getReplyRobot()
    {
        [$toUid, $syncStamp] = Params::autoReplyRobotParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $replyJson = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
            return $redis->get(Category::$reply);
        }, self::REDIS_CONN_NAME);

        $data = [];
        if (empty($replyJson)) {
            //超时或异常使用旧数据
            $data = $this->getFileReplyData();
        } else {
            $replyArr = json_decode($replyJson, true);
            if (!empty($replyArr) && is_array($replyArr)) {
                $data = $this->getCacheReplyData($replyArr);
            }
        }
        $result = Result::getRobotReplyListsResult(Category::CLIENT_TYPE_IOS, $data, $syncStamp);
        $this->messageBase($result);
    }

    /**
     * 根据问题编号，回复问题
     */
    public function autoReplyRobot()
    {
        [$toUid, $qNumber, $syncStamp] = Params::AutoRobotParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['q_number'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkQnumber($qNumber);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $answer = Robot::getRobotArray($qNumber);

        $result = Result::getRobotReplyResult(Category::CLIENT_TYPE_IOS, $answer, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * 问题是否有更新
     */
    public function checkRobotTimeStamp()
    {
        $this->RobotTimeStampBase(Category::CLIENT_TYPE_IOS);
    }

    /**
     * 建立链接
     */
    public function openCustomer()
    {
        [$toUid, $token, $syncStamp] = Params::openCustomerParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['token'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkTokenAndSyncstamp($token, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        //设置分布式锁,3s之内只能请求一次
        $lock = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            return $redis->get(Category::$openLock . $toUid);
        }, self::REDIS_CONN_NAME);

        if ($lock) {
            $msgErrorRet['code'] = 416;
            $msgErrorRet['msg'] = 'Please try again';
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }
        //查询是否存在链接关系
        $imUserRelation = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            $redis->setEx(Category::$openLock . $toUid, 3, $toUid);
            return $redis->get(Category::$imUserRelationName . $toUid);
        }, self::REDIS_CONN_NAME);

        if ($imUserRelation) {
            $imUser = json_decode($imUserRelation, true);
            $customerName = empty($imUser['customer_name']) ? '' : $imUser['customer_name'];
            $customerHead = empty($imUser['customer_head']) ? '' : $imUser['customer_head'];
            $result = Result::getOpenCustomerResult(Category::CLIENT_TYPE_IOS, $toUid, $customerName, 200, $syncStamp, $customerHead);
            $this->messageBase($result);
            return true;
        }

        //2.获取CpAdmin客服人员
        $customerLists = OAuth::getCustomerLists($token);

        if (empty($customerLists)) {
            $code = 406;
            $result = Result::getOpenCustomerResult(Category::CLIENT_TYPE_IOS, $toUid, '', $code, $syncStamp);
            $this->messageBase($result);
            return true;
        }

        //检测是否有在线客服
        $virtualUid = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
            return $redis->zRangeByScore(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN,
                66666666, 66669666, ['withScores' => true, 'limit' => array(0, 20)]);
        }, self::REDIS_CONN_NAME);


        if (empty($virtualUid)) {
            $code = 503;
            $result = Result::getOpenCustomerResult(Category::CLIENT_TYPE_IOS, $toUid, '', $code, $syncStamp);
            $this->messageBase($result);
            return true;
        }

        $server = ServerManager::getInstance()->getSwooleServer();
        $onlineCustomer = [];
        foreach ($virtualUid as $fd => $uVid) {
            //检测是否在线
            $info = $server->getClientInfo($fd);
            if (3 == $info['websocket_status']) {
                $onlineCustomer[] = $uVid;
            }
        }

        if (empty($onlineCustomer)) {
            $code = 503;
            $result = Result::getOpenCustomerResult(Category::CLIENT_TYPE_IOS, $toUid, '', $code, $syncStamp);
            $this->messageBase($result);
            return true;
        }

        //如果上次接待客服在线，优先分配
        $imRelation = DbManager::getInstance()->invoke(function ($client) use ($toUid) {
            $model = ImModel::invoke($client);
            $where = ['to_uid' => $toUid, 'im_status' => 2, 'im_del' => 1];
            $imUserArray = $model->field(['virtual_uid', 'im_rid'])->where($where)->order('im_rid', 'desc')->limit(0, 1)->all()->toArray();
            $data = empty($imUserArray[0]) ? [] : $imUserArray[0];
            return $data;
        }, self::MYSQL_CONN_NAME);

        //遍历客服管理员的关系
        $customerNameDict = [];
        foreach ($customerLists as $key => $val) {
            $customerNameDict[$val['virtual_uid']]['customer_name'] = (string)$val['customer_name'];
            $customerNameDict[$val['virtual_uid']]['customer_head'] = (string)$val['customer_head'];
        }

        if (isset($imRelation) && !empty($imRelation) && in_array($imRelation['virtual_uid'], $onlineCustomer)) {
            $customerName = $customerNameDict[$imRelation['virtual_uid']]['customer_name'];
            $customerHead = $customerNameDict[$imRelation['virtual_uid']]['customer_head'];
            $data = [
                'virtual_uid' => intval($imRelation['virtual_uid']),
                'to_uid' => $toUid,
                'create_time' => time(),
                'im_rid' => (int)$imRelation['im_rid'],
                'customer_name' => $customerName,
                'customer_head' => $customerHead
            ];

            $bool = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid, $data) {
                return $redis->set(Category::$imUserRelationName . $toUid, json_encode($data));
            }, self::REDIS_CONN_NAME);

            if ($bool) {
                $result = Result::getOpenCustomerResult(Category::CLIENT_TYPE_IOS, $toUid, $customerName, 200, $syncStamp,$customerHead);
                $this->messageBase($result);
                return true;
            }
        }

        //3.首次分配，进行随机分配
        $maxIndex = count($onlineCustomer) - 1;
        $index = rand(0, $maxIndex);
        $vUid = $onlineCustomer[$index];
        $customerName = $customerNameDict[$vUid]['customer_name'];
        $customerHead = $customerNameDict[$vUid]['customer_head'];
        $imData = [
            'virtual_uid' => $vUid,
            'to_uid' => $toUid,
            'create_time' => time()
        ];

        $imRid = DbManager::getInstance()->invoke(function ($client) use ($imData) {
            $model = ImModel::invoke($client, $imData);
            return $model->save();
        }, self::MYSQL_CONN_NAME);

        $redisRst = false;
        if ($imRid) {
            $imData['im_rid'] = $imRid;
            $imData['customer_name'] = $customerName;
            $imData['customer_head'] = $customerHead;
            $redisRst = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid, $imData) {
                return $redis->set(Category::$imUserRelationName . $toUid, json_encode($imData));
            }, self::REDIS_CONN_NAME);
        }

        if ($imRid && $redisRst) {
            $code = 200;
        } else {
            //Mysql和Redis不一致，修改Mysql状态,异步处理
            TaskManager::getInstance()->async(function () use ($imRid) {
                DbManager::getInstance()->invoke(function ($client) use ($imRid) {
                    $model = ImModel::invoke($client);
                    $model->where('im_rid', (int)$imRid)->update(['is_delete' => 2]);
                }, self::MYSQL_CONN_NAME);
            });
            $code = 500;
        }
        $result = Result::getOpenCustomerResult(Category::CLIENT_TYPE_IOS, $toUid, $customerName, $code, $syncStamp,$customerHead);
        $this->messageBase($result);
        return true;
    }

    /**
     * 关闭链接
     */
    public function closeCustomer()
    {
        [$toUid, $syncStamp] = Params::closeCustomerParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        //查询是否存在链接关系
        $imUserJson = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            return $redis->get(Category::$imUserRelationName . $toUid);
        }, self::REDIS_CONN_NAME);

        $imUserArray = json_decode($imUserJson, true);
        $msgErrorRet = ParamsCheck::checkImUser($imUserArray);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $rows = false;
        if (!empty($imUserArray)) {
            $imRid = $imUserArray['im_rid'];
            $rows = DbManager::getInstance()->invoke(function ($client) use ($imRid) {
                $model = ImModel::invoke($client);
                return $model->where(['im_rid' => $imRid])->update(['im_status' => 2]);
            }, self::MYSQL_CONN_NAME);
        }

        if ($rows) {
            \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
                $redis->del(Category::$imUserRelationName . $toUid);
            }, self::REDIS_CONN_NAME);
        }

        $result = Result::closeCustomerResult(Category::CLIENT_TYPE_IOS);
        $this->messageBase($result);
        return true;
    }

    /**
     * 给客服发送消息
     */
    public function pushCustomer()
    {
        [$toUid, $userNickname, $userHead, $contents, $syncStamp] = Params::pushCustomerParams(
            Category::CLIENT_TYPE_IOS,
            $this->body['to_uid'],
            $this->body['user_nickname'],
            $this->body['user_head'],
            $this->body['contents'],
            $this->body['syncstamp']
        );

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkContents($contents);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $msgErrorRet = ParamsCheck::checkNickNameAndHead($userNickname, $userHead);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $imUserJson = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            return $redis->get(Category::$imUserRelationName . $toUid);
        }, self::REDIS_CONN_NAME);

        $imUserArray = json_decode($imUserJson, true);
        $msgErrorRet = ParamsCheck::checkImUser($imUserArray);
        if (!empty($msgErrorRet)) {
            $result = Result::getPushCustomerResult(Category::CLIENT_TYPE_IOS, $msgErrorRet['code'], $syncStamp);
            $this->messageBase($result);
            return true;
        }

        //1.消息标记未发送，入库
        $data = [
            'virtual_uid' => (int)$imUserArray['virtual_uid'],
            'to_uid' => $toUid,
            'send_uid' => $toUid,
            'receive_uid' => (int)$imUserArray['virtual_uid'],
            'im_contents' => $contents,
            'is_sent' => 2,
            'client_type' => Category::CLIENT_TYPE_IOS,
            'im_rid' => (int)$imUserArray['im_rid'],
            'create_time' => time(),
            'noce_ack' => $this->getNoceAck()
        ];

        $tableName = $this->_getChatTableName($toUid);
        $imChatId = DbManager::getInstance()->invoke(function ($client) use ($data, $tableName) {
            $model = ImChatModel::invoke($client, $data);
            return $model->tableName($tableName)->save();
        }, self::MYSQL_CONN_NAME);

        //发送
        $redis = \EasySwoole\RedisPool\RedisPool::defer('redis');
        $server = ServerManager::getInstance()->getSwooleServer();

        $zUidLists = $redis->zRangeByScore(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN,
            (int)$imUserArray['virtual_uid'], (int)$imUserArray['virtual_uid'],
            ['withScores' => true, 'limit' => array(0, 5)]
        );

        $pushStatus = false;
        if ($imChatId) {
            //入库成功，未读消息+1
            $redis->incr(Category::_getUnReadServerKeyName($toUid));

            //入库成功，执行推送逻辑
            $unread = $redis->get(Category::_getUnReadServerKeyName($toUid));
            //推送消息
            if (isset($zUidLists) && !empty($zUidLists)) {
                //随机推送给CpAdmin客服
                $result = [
                    'client_type' => 3,
                    'msg_type' => 50,
                    'code' => 200,
                    'msg' => 'SUCCESS',
                    'syncstamp' => $syncStamp,
                    'body' => [
                        'to_uid' => $toUid,
                        'contents' => $contents,
                        'username' => empty($userNickname) ? '' : trim($userNickname),
                        'avatar' => empty($userHead) ? '' : trim($userHead),
                        'unread' => empty($unread) ? '' : $unread,
                        'chat_id' => $imChatId
                    ]
                ];

                $fdList = array_keys($zUidLists);
                foreach ($fdList as $fdKeys) {
                    $info = $server->getClientInfo($fdKeys);
                    if ($info && $info['websocket_status'] == 3) {
                        $bool = $server->push($fdKeys, json_encode($result));
                        //推送成功后，移除元素
                        if ($bool) {
                            $pushStatus = true;
                        }
                    }
                }
            }
        }

        if ($pushStatus) {
            $code = 200;
        } else {
            $code = 503;
        }
        $result = Result::getPushCustomerResult(Category::CLIENT_TYPE_IOS, $code, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * 获取是否存在离线消息
     */
    public function pullChatRecord()
    {
        [$toUid, $syncStamp] = Params::pullChatRecordParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        $tableName = $this->_getChatTableName($toUid);
        $imUserChatLists = DbManager::getInstance()->invoke(function ($client) use ($toUid, $tableName) {
            $model = ImChatModel::invoke($client);
            $where = ['to_uid' => $toUid, 'receive_uid' => $toUid, 'is_sent' => 3];
            $count = $model->tableName($tableName)->where($where)->count();
            return $model->field(['im_contents', 'create_time', 'noce_ack'])->where($where)->tableName($tableName)->order('chat_id', 'desc')->limit(0, $count)->all()->toArray();
        }, self::MYSQL_CONN_NAME);

        $chat = [];
        if (!empty($imUserChatLists) && is_array($imUserChatLists)) {
            foreach ($imUserChatLists as $key => $value) {
                $chat[$key]['im_contents'] = $value['im_contents'];
                $chat[$key]['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
            }
        }

        $result = Result::getPullChatRecordResult(Category::CLIENT_TYPE_IOS, $chat, $toUid);
        $this->messageBase($result);
        return true;
    }

    /**
     * 确认收到离线消息
     */
    public function readCustomer()
    {
        [$toUid, $syncStamp] = Params::readCustomerParams(Category::CLIENT_TYPE_IOS, $this->body['to_uid'], $this->body['syncstamp']);

        $msgErrorRet = ParamsCheck::checkTouidAndSyncstamp($toUid, $syncStamp);
        if (!empty($msgErrorRet)) return $this->response()->setMessage(json_encode($msgErrorRet));

        //查询是否存在链接关系
        $tableName = $this->_getChatTableName($toUid);
        TaskManager::getInstance()->async(function () use ($toUid, $tableName) {
            DbManager::getInstance()->invoke(function ($client) use ($toUid, $tableName) {
                $model = ImChatModel::invoke($client);
                $where = ['to_uid' => $toUid, 'is_sent' => 3];
                $model->tableName($tableName)->where($where)->update(['is_sent' => 2]);
            }, self::MYSQL_CONN_NAME);
        });

        \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($toUid) {
            $redis->set(Category::_getUnReadClientKeyName($toUid), 0);
        }, self::REDIS_CONN_NAME);

        $result = Result::getReadCustomerResult(Category::CLIENT_TYPE_IOS);
        $this->messageBase($result);
        return true;
    }

}
