<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: WebSocketController  基类
# +----------------------------------------------------------------------

namespace App\WebSocketController;

use EasySwoole\Socket\AbstractInterface\Controller;
use App\Utility\Ws\Robot;
use App\Utility\Ws\Category;
use App\Utility\Ws\GetRequest;
use App\Utility\Ws\CheckRequest as checkRequest;

abstract class Base extends Controller
{
    protected $body = [];
    protected $request = [];
    protected $noce_ack = [];
    protected $redis = NULL;
    protected $checkAction = [
        'heart', 'login', 'logout', 'pushUser', 'customerAuth', 'getReplyRobot', 'autoReplyRobot', 'checkRobotTimeStamp'
        , 'readOffLineMsg', 'readOnLineMsg', 'pullChatRecord', 'errorBody', 'chatReceived', 'readNumberMsg', 'setAuthor', 'logoutAuthor'
    ];

    protected $msg_type = null;
    protected $token = '';
    protected $syncstamp = '';

    const PUSH_MSG_PULL_LISTS = 'PUSH_MSG_PULL_LISTS';
    const PUSH_MSG_READ_LISTS = 'PUSH_MSG_READ_LISTS';
    const PUSH_MSG_USER_LOGIN = 'PUSH_MSG_USER_LOGIN';
    const PUSH_MSG_SOCKET_FD = 'PUSH_MSG_SOCKET_FD';
    const PUSH_CUSTOMER_MSG_SOCKET_FD = 'PUSH_CUSTOMER_MSG_SOCKET_FD';
    const PUSH_MSG_SSET_USER_LOGIN = 'PUSH_MSG_SSET_USER_LOGIN';
    const PUSH_CUSTOMER_MSG_SSET_USER_LOGIN = 'PUSH_CUSTOMER_MSG_SSET_USER_LOGIN';
    const MYSQL_CONN_NAME = 'mysql-msg';
    const REDIS_CONN_NAME = 'redis';
    const MAX_LINK_NUMBER = 10000;
    const PUSH_MSG_OFFLINE_LISTS = 'PUSH_MSG_OFFLINE_LISTS';
    const PUSH_UNREAD_SERVER_All = 'PUSH_UNREAD_SERVER_All';

    protected function actionNotFound(?string $actionName)
    {
        $ret['code'] = 404;
        $ret['msg'] = 'action not found!';
        $this->response()->setMessage(json_encode($ret));
    }


    protected function onRequest(?string $actionName): bool
    {
        $this->body = $this->caller()->getArgs();
        $this->request = new GetRequest();
        $fd = $this->caller()->getClient()->getFd();
        //验证是否登陆
        if (!in_array($actionName, $this->checkAction)) {
            if (isset($this->body['to_uid']) && !empty($this->body['to_uid'])) {
                $result = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($fd) {
                    $result = $redis->hExists(self::PUSH_MSG_SOCKET_FD, $fd);
                    return $result;
                }, self::REDIS_CONN_NAME);

                if (0 == $result || $result == false) {
                    $msgErrorRet['code'] = 403;
                    $msgErrorRet['msg'] = 'Please log in first';
                    $this->response()->setMessage(json_encode($msgErrorRet));
                    return false;
                }
            }
        }

        return true;
    }

    public function onException(\Throwable $throwable): void
    {
        $message = $throwable->getMessage();
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        $message = $message . ',文件位置:' . $file . '第' . $line . '行' . PHP_EOL;
        \EasySwoole\EasySwoole\Logger::getInstance()->log($message, \EasySwoole\EasySwoole\Logger::LOG_LEVEL_ERROR, 'Error');
    }

    /**
     * @return string 生成通信唯一的ack标识
     */
    protected function getNoceAck(): string
    {
        mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
        $charId = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $ack = substr($charId, 0, 8) . $hyphen
            . substr($charId, 8, 4) . $hyphen
            . substr($charId, 12, 4) . $hyphen
            . substr($charId, 16, 4) . $hyphen
            . substr($charId, 20, 12);
        return $ack;
    }


    protected function afterAction(?string $actionName)
    {
    }

    protected function heartBase()
    {

        if (empty($this->body['client_type'])) {
            $this->body['client_type'] = 1;
        }
        $result = [
            'client_type' => $this->body['client_type'],
            'msg_type' => 1,
            'code' => 200,
            'msg' => 'SUCCESS',
            'syncstamp' => time(),

        ];
        $this->response()->setMessage(json_encode($result));
    }


    protected function messageBase($msgErrorRet)
    {
        if (!empty($msgErrorRet)) {
            $this->response()->setMessage(json_encode($msgErrorRet));
            unset($msgErrorRet);
            return true;
        }
    }


    protected function _getTableName(int $uid): string
    {
        $tableIndex = intval($uid % 128);
        return 'user_push_msg_' . $tableIndex;
    }


    protected function _getChatTableName(int $uid): string
    {
        $tableIndex = intval($uid % 10);
        return 'im_user_chat_record_' . $tableIndex;
    }

    protected function RobotTimeStampBase($client_type)
    {
        $timestamp = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
            return $redis->get(Category::$replyTimestamp);
        }, self::REDIS_CONN_NAME);

        $timestamp = empty($timestamp) ? strtotime('2022-3-1 00:00:00') : $timestamp;

        $result = [
            'client_type' => $client_type,
            'msg_type' => 24,
            'code' => 200,
            'msg' => 'SUCCESS',
            'body' => ['timestamp' => $timestamp]
        ];
        $this->messageBase($result);
        return true;
    }

    protected function getFileReplyData()
    {
        $robotLists = Robot::$robotArray;
        $data = [];
        $category = [1 => '充值', 2 => '账号', 3 => '读者', 4 => '作者'];
        foreach ($robotLists as $key => $val) {
            $data[$val['category']]['category'] = (int)$val['category'];
            $data[$val['category']]['cateName'] = $category[(int)$val['category']];
            $children['title'] = $val['title'];
            $children['answer'] = str_replace(' ', '', $val['answer']);
            $children['number'] = $key;
            $data[$val['category']]['data'][] = $children;
        }
        $data = array_values($data);
        return $data;
    }

    protected function getCacheReplyData(array $replyArr): array
    {
        $data = [];
        $categoryName = [1 => '充值', 2 => '账号', 3 => '读者', 4 => '作者'];
        foreach ($replyArr as $val) {
            if (!in_array($val['h_category'], array_keys($categoryName))) {
                continue;
            }
            $data[$val['h_category']]['category'] = (int)$val['h_category'];
            $data[$val['h_category']]['cateName'] = $categoryName[(int)$val['h_category']];
            $children['title'] = $val['h_title'];
            $children['answer'] = str_replace(' ', '', $val['h_answer']);
            $children['number'] = 'Q' . $val['id'];
            $data[$val['h_category']]['data'][] = $children;
        }
        $data = array_values($data);
        return $data;
    }

    protected function checkRequest(array $data)
    {
        $msgErrorRet = checkRequest::requestData($data, $this->body);
        if (!empty($msgErrorRet)) {
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }
    }
}
