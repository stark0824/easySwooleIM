<?php

namespace App\WebSocketController\V5;

use Swoole\Websocket\Server;
use App\WebSocketController\Base;
use App\Server\RedisServer\FdServer;
use App\Utility\Ws\{Result, Category, LogRequest, CheckRequest as checkRequest};
use App\Server\RedisServer\CountServer;

class AndroidMessage extends Base
{
    protected $commonKey = ['to_uid', 'syncstamp', 'identity'];
    protected $clearKey = ['to_uid', 'syncstamp', 'number'];

    /**
     * 签约作者分组确认 identity 1 普通作者 2签约作者
     */
    public function setAuthor()
    {
        $log = new LogRequest('setAuthor', Category::CLIENT_TYPE_ANDROID);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $uid = $this->request->getToken($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);
        $identity = $this->request->getIdentity($this->body['identity']);

        $fdServer = new FdServer();
        //存入缓存
        $fd = $this->caller()->getClient()->getFd();
        $fdServer->setAuthorFd($uid, $fd);
        if (2 == $identity) {
            $fdServer->setSignAuthorFd($uid, $fd);
        }
        $result = Result::getSetAuthorResult(Category::CLIENT_TYPE_ANDROID, $uid, $syncStamp);
        $this->messageBase($result);
        return true;
    }


    public function logoutAuthor()
    {
        $log = new LogRequest('logoutAuthor', Category::CLIENT_TYPE_ANDROID);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);
        $identity = $this->request->getIdentity($this->body['identity']);

        //取出Fd队列关系中的Uid
        $fd = $this->caller()->getClient()->getFd();
        $fdServer = new FdServer();


        //回收Fd关系
        $fdServer->recoveryAuthorFd($fd,$identity);
        $result = Result::getLogoutAuthorResult(Category::CLIENT_TYPE_ANDROID, $toUid, $syncStamp);
        $this->messageBase($result);
        return true;
    }


    public function clearReadNumber()
    {
        $log = new LogRequest('clearReadNumber', Category::CLIENT_TYPE_ANDROID);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->clearKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $countServer = new CountServer();
        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);
        $number = $this->request->getUnread($this->body['number']);
        $unreadNumber = $countServer->getMessageUnread($toUid);
        $diffNumber = $unreadNumber - $number;
        if($diffNumber > 0){
            $countServer->setMessageUnread($toUid,$diffNumber);
        }else if($diffNumber == 0 || $diffNumber < 0){
            $countServer->setMessageUnread($toUid,0);
        }

        $result = Result::unReadMessageResult(Category::CLIENT_TYPE_ANDROID, $syncStamp);
        $this->messageBase($result);
        return true;
    }
}
