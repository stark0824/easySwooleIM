<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: Customer 与客服相关的Http接口服务
# +----------------------------------------------------------------------

namespace App\HttpController\Api;

use App\Utility\Ws\Category;
use EasySwoole\Http\Message\Status;
use App\Models\ImModel;
use App\Models\ImChatModel;
use EasySwoole\ORM\DbManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Mysqli\QueryBuilder;

class Customer extends Base
{
    /**
     *  根据类型，接收推送的消息
     */
    public function getUserLists()
    {
        $virtualUid = empty($this->params['virtual_uid']) ? 0 : (int)$this->params['virtual_uid'];
        \EasySwoole\EasySwoole\Logger::getInstance()->log('virtual_uid:'.$this->params['virtual_uid'] ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'getUserLists');

        if (empty($virtualUid)) {
            return $this->writeJson(416, [], 'virtual_uid error');
        }

        $imUserLists = DbManager::getInstance()->invoke(function ($client) use ($virtualUid) {
            $model = ImModel::invoke($client);
            $where = ['virtual_uid' => $virtualUid, 'im_status' => 1, 'im_del' => 1];
            $imUserLists = $model->field(['im_rid', 'to_uid', 'im_top', 'im_del', 'im_flag'])->where($where)->order('im_top', 'desc')->order('im_rid', 'desc')->all()->toArray();
            return $imUserLists;
        }, self::MYSQL_CONN_NAME);

        $imUserLists = $this->arrayUniqueness($imUserLists, 'to_uid');
        $imUserLists = array_values($imUserLists);

        \EasySwoole\EasySwoole\Logger::getInstance()->log('返回Code:'.Status::CODE_OK .', imUserLists:'.json_encode($imUserLists) ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'getUserLists');

        return $this->writeJson(Status::CODE_OK, $imUserLists, Status::getReasonPhrase(Status::CODE_OK));
    }


    public function getChatRecordDetails()
    {
        $toUid = empty($this->params['to_uid']) ? 0 : (int)$this->params['to_uid'];
        $page = empty($this->params['page']) ? 1 : (int)$this->params['page'];
        $limit = empty($this->params['limit']) ? 20 : (int)$this->params['limit'];
        $begDate = empty($this->params['begDate']) ? 0 : strtotime(date($this->params['begDate']) . '00:00:00');
        $endDate = empty($this->params['endDate']) ? 0 : strtotime(date($this->params['endDate']) . '23:59:59');
        $searchKeyword = empty($this->params['search_keyword']) ? '' : trim($this->params['search_keyword']);
        $orderField = empty($this->params['order_field']) ? 'chat_id' : trim($this->params['order_field']);
        $orderSort = empty($this->params['order_sort']) ? 'desc' : trim($this->params['order_sort']);

        $start = ($page - 1) * $limit;

        if (empty($toUid)) {
            return $this->writeJson(416, ['list' => [], 'count' => 0], 'to_uid error');
        }

        $tableName = $this->_getChatTableName($toUid);
        $data = DbManager::getInstance()->invoke(function ($client) use ($toUid, $tableName, $start, $limit, $begDate, $endDate, $searchKeyword, $orderField, $orderSort) {
            $data = [];
            $model = ImChatModel::invoke($client);

            $where = "to_uid = {$toUid} AND is_sent in ( 2 , 3 , 4)";
            if (!empty($begDate) && $begDate > 0) {
                $where .= " AND create_time > $begDate ";
            }
            if (!empty($endDate) && $endDate > 0) {
                $where .= " AND create_time < $endDate ";
            }

            if (!empty($searchKeyword) && strlen($searchKeyword) > 0) {
                $where .= " AND `im_contents` LIKE '%{$searchKeyword}%' ";
            }

            $imUserChatLists = $model->field(['chat_id', 'send_uid', 'receive_uid', 'im_contents', 'client_type', 'create_time'])
                ->where($where)
                ->tableName($tableName)
                ->order($orderField, $orderSort)
                ->limit($start, $limit)
                ->all()->toArray();
            $count = $model->where($where)->tableName($tableName)->count();
            $data['list'] = $imUserChatLists;
            $data['count'] = $count;
            return $data;
        }, self::MYSQL_CONN_NAME);

        return $this->writeJson(Status::CODE_OK, $data, Status::getReasonPhrase(Status::CODE_OK));
    }

    public function arrayUniqueness($arr, $key) :array
    {
        $res = array();
        foreach ($arr as $value) {
            //查看有没有重复项
            if (isset($res[$value[$key]])) {
                //有：销毁
                unset($value[$key]);
            } else {
                $res[$value[$key]] = $value;
            }
        }
        return $res;
    }


    public function setCustomerTop()
    {

        $imTop = empty($this->params['im_top']) ? 1 : $this->params['im_top'];
        $imRid = empty($this->params['im_rid']) ? 0 : $this->params['im_rid'];

        if (empty($imRid) || empty($imTop)) {
            return $this->writeJson(416, [], 'params error');
        }

        DbManager::getInstance()->invoke(function ($client) use ($imRid, $imTop) {
            $model = ImModel::invoke($client);
            $where = ['im_rid' => $imRid];
            $bool = $model->where($where)->update(['im_top' => $imTop]);
            return $bool;
        }, self::MYSQL_CONN_NAME);

        return $this->writeJson(Status::CODE_OK, [], Status::getReasonPhrase(Status::CODE_OK));

    }

    public function setCustomerFlag()
    {

        $imRid = empty($this->params['im_rid']) ? 0 : $this->params['im_rid'];

        if (empty($imRid)) {
            return $this->writeJson(416, [], 'params error');
        }
        TaskManager::getInstance()->async(function () use ($imRid) {
            DbManager::getInstance()->invoke(function ($client) use ($imRid) {
                $model = ImModel::invoke($client);
                $where = ['im_rid' => $imRid];
                $imData = $model->field(['im_flag'])->where($where)->get()->toArray();
                $imFlag = $imData['im_flag'] == 1 ? 2 : 1;
                $bool = $model->where($where)->update(['im_flag' => $imFlag]);
                return $bool;
            }, self::MYSQL_CONN_NAME);
        });

        return $this->writeJson(Status::CODE_OK, [], Status::getReasonPhrase(Status::CODE_OK));

    }

    public function setCustomerDel()
    {

        $imRid = empty($this->params['im_rid']) ? 0 : $this->params['im_rid'];

        if (empty($imRid)) {
            return $this->writeJson(416, [], 'params error');
        }

        DbManager::getInstance()->invoke(function ($client) use ($imRid) {
            $model = ImModel::invoke($client);
            $where = ['im_rid' => $imRid];
            $bool = $model->where($where)->update(['im_del' => 2]);
            return $bool;
        }, self::MYSQL_CONN_NAME);

        return $this->writeJson(Status::CODE_OK, [], Status::getReasonPhrase(Status::CODE_OK));
    }

    public function recoveryImUser()
    {
        $virtualUid = empty($this->params['virtual_uid']) ? 0 : $this->params['virtual_uid'];
        \EasySwoole\EasySwoole\Logger::getInstance()->log('接收到的参数:virtual_uid:' . $virtualUid, \EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO, 'recoveryImUser');

        if (empty($virtualUid)) {
            return $this->writeJson(416, [], 'params error');
        }

        TaskManager::getInstance()->async(function () use ($virtualUid) {
            //清除mysql关系
            $userLists = DbManager::getInstance()->invoke(function ($client) use ($virtualUid) {
                $model = ImModel::invoke($client);
                $getWhere = ['virtual_uid' => (int)$virtualUid, 'im_status' => 1];
                $userLists = $model->field(['to_uid'])->where($getWhere)->all()->toArray();
                if (!empty($userLists)) {
                    $model->where($getWhere)->update(['im_status' => 2]);
                }
                return $userLists;
            }, self::MYSQL_CONN_NAME);

            if (!empty($userLists)) {
                \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($userLists) {
                    foreach ($userLists as $val) {
                        $redis->del(Category::$imUserRelationName . $val['to_uid']);
                        \EasySwoole\EasySwoole\Logger::getInstance()->log('del：' . Category::$imUserRelationName . $val['to_uid'], \EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO, 'del');
                    }
                }, self::REDIS_CONN_NAME);
            }
        });
        return $this->writeJson(Status::CODE_OK, [], Status::getReasonPhrase(Status::CODE_OK));
    }

    public function getChatRecordCount()
    {
        $toUid = empty($this->params['to_uid']) ? 0 : (int)$this->params['to_uid'];

        if (empty($toUid)) {
            return $this->writeJson(416, [], 'to_uid error');
        }

        $tableName = $this->_getChatTableName($toUid);
        $count = DbManager::getInstance()->invoke(function ($client) use ($toUid, $tableName) {
            $model = ImChatModel::invoke($client);
            $where = ['to_uid' => $toUid];  //,'is_sent' => 2
            $count = $model->field(['chat_id', 'send_uid', 'receive_uid', 'im_contents', 'client_type', 'create_time'])->tableName($tableName)->where($where)->count();
            return $count;
        }, self::MYSQL_CONN_NAME);
        return $this->writeJson(Status::CODE_OK, $count, Status::getReasonPhrase(Status::CODE_OK));
    }


    public function getUserAll()
    {
        $page = empty($this->params['page']) ? 1 : (int)$this->params['page'];
        $limit = empty($this->params['limit']) ? 20 : (int)$this->params['limit'];
        $searchKeyword = empty($this->params['search_keyword']) ? '' : (int)$this->params['search_keyword'];
        $orderSort = empty($this->params['order_sort']) ? '' : trim($this->params['order_sort']);

        $start = ($page - 1) * $limit;
        $queryBuild = new QueryBuilder();

        $order = "order by im_rid desc ";

        if (isset($orderSort) && !empty($orderSort) && in_array($orderSort, ['asc', 'desc'])) {
            $order = "order by to_uid {$orderSort} ";
        }

        if (!empty($searchKeyword) && (int)$searchKeyword > 0) {
            $countSql = " select  DISTINCT to_uid from im_user_relation where to_uid = {$searchKeyword} ";
            $sql = "select  DISTINCT to_uid from im_user_relation where to_uid = {$searchKeyword} {$order} limit {$start},{$limit}";
        } else {
            $countSql = "select DISTINCT to_uid from im_user_relation ";
            $sql = "select DISTINCT to_uid from im_user_relation {$order} limit {$start},{$limit}";
        }
        $queryBuild->raw($sql);
        $obj = DbManager::getInstance()->query($queryBuild, true, self::MYSQL_CONN_NAME);
        $data = $obj->getResult();


        $queryBuild->raw($countSql);
        $countObj = DbManager::getInstance()->query($queryBuild, true, self::MYSQL_CONN_NAME);
        $dataCount = $countObj->getResult();
        $logData = [Status::CODE_OK, ['count' => count($dataCount), 'list' => $data], Status::getReasonPhrase(Status::CODE_OK)];
        \EasySwoole\EasySwoole\Logger::getInstance()->log('imUserLists:'.json_encode($logData) ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'getUserAll');
        return $this->writeJson(Status::CODE_OK, ['count' => count($dataCount), 'list' => $data], Status::getReasonPhrase(Status::CODE_OK));
    }

    /**
     * 新增客服管理员时回收缓存
     */
    public function recovery()
    {
        return $this->writeJson(Status::CODE_OK, [], Status::getReasonPhrase(Status::CODE_OK));
    }

    public function userOnline()
    {
        $data = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
            $data = $redis->keys(Category::$imUserRelationName . '*');
            return $data;
        }, self::REDIS_CONN_NAME);
        return $this->writeJson(Status::CODE_OK, count($data), Status::getReasonPhrase(Status::CODE_OK));
    }

    public function getOnline()
    {

        $virtualUid = empty($this->params['virtual_uid']) ? 0 : (int)$this->params['virtual_uid'];

        if (empty($virtualUid)) {
            return $this->writeJson(416, [], 'params error');
        }

        $data = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($virtualUid) {

            $data = $redis->zRangeByScore(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN,
                (int)$virtualUid, (int)$virtualUid,
                ['withScores' => true, 'limit' => array(0, 5)]
            );
            return $data;
        }, self::REDIS_CONN_NAME);

        $server = ServerManager::getInstance()->getSwooleServer();
        $onlineCustomer = [];
        foreach ($data as $fd => $vuid) {
            $info = $server->getClientInfo($fd);
            if (3 == (int)$info['websocket_status']) {
                $onlineCustomer[$fd] = $vuid;
            }
        }

        $count = count($onlineCustomer);
        $diff = array_diff_key($data, $onlineCustomer);
        //进行差集计算，存在无效关系，清除关系
        if (!empty($diff) && is_array($diff)) {
            $haseLogin = array_keys($diff);
            \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($haseLogin) {

                foreach ($haseLogin as $member) {
                    $redis->zRem(self::PUSH_CUSTOMER_MSG_SSET_USER_LOGIN, (int)$member);
                    $redis->hDel(self::PUSH_CUSTOMER_MSG_SOCKET_FD, (int)$member);
                }
            }, self::REDIS_CONN_NAME);
        }
        return $this->writeJson(Status::CODE_OK, $count, Status::getReasonPhrase(Status::CODE_OK));
    }

    /**
     * 客服v4 新增
     */

    public function getReplyLists()
    {
        $data = empty($this->params['data']) ? '' : (string)$this->params['data'];
        \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($data) {
            $redis->set(Category::$reply, $data);
            $redis->set(Category::$replyTimestamp, time());
        }, self::REDIS_CONN_NAME);
        return $this->writeJson(Status::CODE_OK, [], Status::getReasonPhrase(Status::CODE_OK));
    }

}
