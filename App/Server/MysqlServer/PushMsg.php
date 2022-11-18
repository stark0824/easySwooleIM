<?php
/**
 * Created by PhpStorm.
 * User: stark
 * Date: 2020-11-12
 * Time: 09:28
 */

namespace App\Server\MysqlServer;

use App\Models\PushMsgModel;
use App\Server\Server;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Db\ClientInterface;
use \EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;

class PushMsg extends Server
{

    protected function _getTableName(int $uid) :string
    {
        $tableIndex = intval($uid % 128);
        return 'user_push_msg_' . $tableIndex;
    }

    public function getUnreadByUid(int $uid) :int
    {
        $tableName = $this->_getTableName($uid);
        $pushMsgInfo = DbManager::getInstance()->invoke(function (ClientInterface $client) use ($tableName, $uid) {
            $pushMsgModel = PushMsgModel::invoke($client);
            $where = ['to_uid' => $uid, 'is_read' => 0];
            $pushMsgRead = $pushMsgModel->tableName($tableName)->field(['push_id'])->where($where)->limit(0, 1)->all();
            $pushMsgInfo = [];
            if (isset($pushMsgRead) && !empty($pushMsgRead)) {
                $pushMsgInfo = $pushMsgRead->toArray();
            }
            return $pushMsgInfo;
        }, self::MYSQL_CONN_NAME);

        $unread = empty($pushMsgInfo) ? 0 : 1;
        Logger::getInstance()->log('tableName:' . $tableName . ',to_uid:' . $uid . ',unread:' . $unread, Logger::LOG_LEVEL_INFO, 'getUnreadByUid');
        return $unread;
    }


    public function getUnreadNumber(int $toUid) :int
    {
        //把未读的数据读取出来，根据主键修改数据
        $where = ['to_uid' => $toUid, 'is_read' => 0];
        $tableName = $this->_getTableName($toUid);

        $count = DbManager::getInstance()->invoke(function (ClientInterface $client) use ($tableName, $where) {
            $pushMsgModel = PushMsgModel::invoke($client);
            $count = $pushMsgModel->tableName($tableName)->where($where)->count();
            return empty($count) ? 0 : (int)$count;
        }, self::MYSQL_CONN_NAME);

        Logger::getInstance()->log('tableName:' . $tableName . ',to_uid:' . $toUid . '未读查询结果_count：' . $count, Logger::LOG_LEVEL_INFO, 'getUnreadNumber');

        return $count;
    }


    public function setReadMsg(int $toUid, int $count)
    {

        $where = ['to_uid' => $toUid, 'is_read' => 0];
        $tableName = $this->_getTableName($toUid);

        TaskManager::getInstance()->async(function () use ($where, $tableName, $count) {
            DbManager::getInstance()->invoke(function (ClientInterface $client) use ($tableName, $where, $count) {
                $pushMsgModel = PushMsgModel::invoke($client);
                $pushMsgData = $pushMsgModel->tableName($tableName)->field(['push_id'])->where($where)->limit(0, $count)->all()->toArray();
                if (isset($pushMsgData) && !empty($pushMsgData)) {
                    $push_ids = array_column($pushMsgData, 'push_id');
                    $pushMsgModel->tableName($tableName)->where('push_id', $push_ids, 'in')->update(['is_read' => 1]);
                }
            }, self::MYSQL_CONN_NAME);
        });
    }


    public function getReceivedCount(int $toUid, string $ack) :int
    {

        //把未读的数据读取出来，根据主键修改数据
        $where = ['noce_ack' => $ack, 'is_sent' => 1];
        $tableName = $this->_getTableName($toUid);

        $count = DbManager::getInstance()->invoke(function (ClientInterface $client) use ($tableName, $where) {
            $PushMsgModel = PushMsgModel::invoke($client);
            $count = $PushMsgModel->tableName($tableName)->where($where)->count();
            return intval($count) ?? 0;
        }, self::MYSQL_CONN_NAME);
        Logger::getInstance()->log('noce_ack：' . $ack . '是否已推送:count' . $count, Logger::LOG_LEVEL_INFO, 'getReceivedCount');
        return $count;
    }


    public function updateReceivedSent(int $toUid, string $ack, int $count)
    {
        //异步修改数据
        $where = ['noce_ack' => $ack, 'is_sent' => 1];
        $tableName = $this->_getTableName($toUid);
        TaskManager::getInstance()->async(function () use ($where, $tableName, $count) {
            DbManager::getInstance()->invoke(function (ClientInterface $client) use ($where, $tableName, $count) {
                $pushMsgModel = PushMsgModel::invoke($client);
                $pushMsgObj = $pushMsgModel->tableName($tableName)
                    ->field(['push_id'])
                    ->get($where);
                if (isset($pushMsgObj) && !empty($pushMsgObj)) {
                    $pushMsgData = $pushMsgObj->toArray();
                    $pushMsgModel->tableName($tableName)->update(['is_sent' => 2], ['push_id' => $pushMsgData['push_id']]);
                }
            }, self::MYSQL_CONN_NAME);
        });
    }


    //下面是添加

    public function addPull(string $noceAck, int $toUid,  int $unRead, int $clientType)
    {
        //消息入库
        $pushMsg = [
            'client_type' => $clientType,
            'noce_ack' => $noceAck,
            'to_uid' => $toUid,
            'is_sent' => 1,
            'is_read' => 1, #消息已读
            'msg_type' => 5,
            'msg_extend' => json_encode(['unread' => $unRead]),
            'create_time' => time(),
            'update_time' => time()
        ];
        $this->add($toUid, $pushMsg);
    }


    public function addRead(string $noceAck, int $toUid, int $clientType)
    {
        //消息入库
        $pushMsg = [
            'client_type' => $clientType,
            'msg_type' => 7,
            'noce_ack' => $noceAck,
            'to_uid' => $toUid,
            'is_sent' => 1,
            'is_read' => 1, #消息已读
            'msg_extend' => '',
            'create_time' => time(),
            'update_time' => time()
        ];
        $this->add($toUid, $pushMsg);
    }


    public function addComment(int $toUid, string $ack)
    {
        $pushMsg = [
            'noce_ack' => $ack,
            'to_uid' => $toUid,
            'is_sent' => 1,
            'is_read' => 0, #消息未读
            'msg_type' => 4,
            'client_type' => 0,
            'msg_extend' => '',
            'create_time' => time(),
            'update_time' => 0
        ];
        $this->add($toUid, $pushMsg);
    }

    public function addNotice(int $toUid, string $ack, string $msg)
    {
        $pushMsg = [
            'noce_ack' => $ack,
            'to_uid' => $toUid,
            'is_sent' => 1,
            'is_read' => 0, #消息未读
            'msg_type' => 6,
            'client_type' => 0,
            'msg_extend' => $msg,
            'create_time' => time(),
            'update_time' => 0
        ];
        $this->add($toUid, $pushMsg);
    }

    /**
     * @param $toUid
     * @param $pushMsg
     * 异步添加消息红点的公共方法
     */
    public function add(int $toUid, array $pushMsg)
    {
        $tableName = $this->_getTableName($toUid);
        TaskManager::getInstance()->async(function () use ($pushMsg, $tableName) {
            DbManager::getInstance()->invoke(function (ClientInterface $client) use ($pushMsg, $tableName) {
                $model = PushMsgModel::invoke($client, $pushMsg);
                $push_id = $model->tableName($tableName)->save();
                Logger::getInstance()->log('tableName:' . $tableName . ',push_id:' . $push_id, Logger::LOG_LEVEL_INFO, 'add');
            }, self::MYSQL_CONN_NAME);

        });
    }

}
