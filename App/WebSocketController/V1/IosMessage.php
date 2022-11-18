<?php

namespace App\WebSocketController\V1;

use Swoole\Websocket\Server;
use App\Library\IMessage\iMessageController;
use App\WebSocketController\Base;
use App\Server\ChangPeiServer\UserServer;
use App\Server\RedisServer\FdServer;
use App\Server\MysqlServer\PushMsg;
use App\Utility\Ws\{Result,Category,LogRequest,CheckRequest as checkRequest};

class IosMessage extends Base implements iMessageController
{
    protected $loginKey = ['token', 'syncstamp'];
    protected $commonKey = ['to_uid', 'syncstamp'];
    protected $receivedKey = ['to_uid', 'noce_ack', 'syncstamp'];

    /**
     * heart Websocket用户心跳检测
     */
    public function heart()
    {
        $this->heartBase();
    }

    /**
     * login Websocket用户认证
     */
    public function login()
    {
        $log = new LogRequest('login', Category::CLIENT_TYPE_IOS);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->loginKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $token = $this->request->getToken($this->body['token']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        $userServer = new UserServer();
        $fdServer = new FdServer();

        $uid = $userServer->getUserId($token);

        $msgErrorRet = checkRequest::checkValue('uid', $uid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        //存入缓存
        $fd = $this->caller()->getClient()->getFd();

        $fdServer->setSocketFd($fd, $uid);

        $result = Result::getLoginResult(Category::CLIENT_TYPE_IOS, $uid, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * logout 用户主动退出登陆 3
     */
    public function logout()
    {
        $log = new LogRequest('logout', Category::CLIENT_TYPE_IOS);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        //取出Fd队列关系中的Uid
        $fd = $this->caller()->getClient()->getFd();
        $fdServer = new FdServer();
        $fUid = $fdServer->getSocketUid((int)$fd);

        $msgErrorRet = checkRequest::checkEmpty('Fuid', $fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        //回收Fd关系
        $fdServer->recoverySocketFd($fd);
        $result = Result::getLogoutResult(Category::CLIENT_TYPE_IOS, $toUid, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * pull 用户主动拉取信息 5
     */
    public function pull()
    {
        $log = new LogRequest('pull', Category::CLIENT_TYPE_IOS);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        $fd = $this->caller()->getClient()->getFd();
        $fdServer = new FdServer();
        $fUid = $fdServer->getSocketUid($fd);

        $msgErrorRet = checkRequest::checkEmpty('Fuid', $fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $model = new PushMsg();
        $unread = $model->getUnreadByUid($toUid);
        $noceAck = $this->getNoceAck();

        //异步处理新增数据
        $model->addPull($noceAck, $toUid, $unread, Category::CLIENT_TYPE_IOS);
        $result = Result::getPullResult(Category::CLIENT_TYPE_PC, $toUid, $unread, $noceAck, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * read 消息已读 7
     */
    public function read()
    {
        $log = new LogRequest('read', Category::CLIENT_TYPE_IOS);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        $fdServer = new FdServer();
        $fd = $this->caller()->getClient()->getFd();
        $fUid = $fdServer->getSocketUid($fd);

        $msgErrorRet = checkRequest::checkEmpty('Fuid', $fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        //把未读的数据读取出来，根据主键修改数据
        $model = new PushMsg();
        $count = $model->getUnreadNumber($toUid);

        if (0 == $count) {
            $result = Result::getReadResult(Category::CLIENT_TYPE_IOS, $toUid, $syncStamp);
            $this->messageBase($result);
            return true;
        }

        //异步变更已读数据
        $model->setReadMsg($toUid, $count);
        $noceAck = $this->getNoceAck();
        $model->addRead($noceAck, $toUid, Category::CLIENT_TYPE_IOS);

        $result = Result::getReadResult(Category::CLIENT_TYPE_IOS, $toUid, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    /**
     * received Ack回执收到确认 8
     */
    public function received()
    {
        $log = new LogRequest('received', Category::CLIENT_TYPE_IOS);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->receivedKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);
        $noceAck = $this->request->getNoceAck($this->body['noce_ack']);

        $fd = $this->caller()->getClient()->getFd();
        $fdServer = new FdServer();
        $fUid = $fdServer->getSocketUid($fd);

        $msgErrorRet = checkRequest::checkEmpty('Fuid', $fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $model = new PushMsg();
        $count = $model->getReceivedCount($toUid, $noceAck);

        if ($count == 0) {
            $result = Result::getReceivedResult(Category::CLIENT_TYPE_IOS, $noceAck, $syncStamp);
            $this->response()->setMessage(json_encode($result));
            return true;
        }
        $model->updateReceivedSent($toUid, $noceAck, $count);

        $result = Result::getReceivedResult(Category::CLIENT_TYPE_IOS, $noceAck, $syncStamp);
        $this->response()->setMessage(json_encode($result));
        return true;
    }
}
