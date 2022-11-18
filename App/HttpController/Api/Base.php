<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: HttpController  基类，主要和主站进行交互
# +----------------------------------------------------------------------


namespace App\HttpController\Api;

use EasySwoole\Http\AbstractInterface\Controller;
use App\Models\PushMsgModel;
use EasySwoole\EasySwoole\Task\TaskManager;


class Base extends Controller
{
    const PUSH_MSG_NOTICE_SYSTEM = 'PUSH_MSG_NOTICE_SYSTEM';
    const PUSH_MSG_NOTICE_LIST = 'PUSH_MSG_NOTICE_LIST';
    const PUSH_MSG_COMMENT_LISTS = 'PUSH_MSG_COMMENT_LISTS';

    const PUSH_UNREAD_NUMBER_All = 'PUSH_UNREAD_NUMBER_All';

    const PUSH_CUSTOMER_MSG_SSET_USER_LOGIN = 'PUSH_CUSTOMER_MSG_SSET_USER_LOGIN';
    const PUSH_CUSTOMER_MSG_SOCKET_FD = 'PUSH_CUSTOMER_MSG_SOCKET_FD';

    protected $params = []; //公共参数
    protected $method;
    protected $redis = null;
    protected $tableNum = 128;

    const MYSQL_CONN_NAME = 'mysql-msg';
    const REDIS_CONN_NAME = 'redis';

    /**
     * 接收参数初始化方法
     * @param string|null $action
     * @return bool|null
     */
    protected function onRequest(?string $action): ?bool
    {
        //接收参数
        $this->params = $this->request()->getRequestParam();
        $this->method = $this->request()->getMethod();
        return true;
    }


    function onException(\Throwable $throwable): void
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

    /**
     * 异步提交mysql数据
     * @param $pushMsg
     * @param $uid
     * @return false
     */
    protected function addAsyncMysql( array $pushMsg,  int $uid): ?bool
    {
        $tableName = $this->_getTableName($uid);

        if (empty($pushMsg) || empty($tableName) || empty($uid)) return false;

        TaskManager::getInstance()->async(function () use ($pushMsg, $tableName) {
            \EasySwoole\ORM\DbManager::getInstance()->invoke(function (\EasySwoole\ORM\Db\ClientInterface $client) use ($pushMsg, $tableName) {
                $model = PushMsgModel::invoke($client, $pushMsg);
                $model->tableName($tableName)->save();
            }, self::MYSQL_CONN_NAME);
        });
    }


    protected function _getTableName(int $uid): string
    {
        $tableIndex = intval($uid % $this->tableNum);
        return 'user_push_msg_' . $tableIndex;
    }

    protected function _getChatTableName(int $uid): string
    {
        $tableIndex = intval($uid % 10);
        return 'im_user_chat_record_' . $tableIndex;
    }
}
