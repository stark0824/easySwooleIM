<?php

namespace App\WebSocketController\V2;

use Swoole\Websocket\Server;
use App\WebSocketController\Base;
use App\Server\RedisServer\FdServer;
use App\Server\RedisServer\CountServer;
use App\Utility\Ws\{Result,Category,LogRequest,CheckRequest as checkRequest};

class PcMessage extends Base
{
    protected $commonKey = ['to_uid','syncstamp'];

    public function unReadAll()
    {
        $log = new LogRequest('unReadAll', Category::CLIENT_TYPE_PC);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $fdServer = new FdServer();
        $unReadObj = new CountServer();

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        $fd = $this->caller()->getClient()->getFd();
        $fUid = $fdServer->getSocketUid($fd);

        $msgErrorRet = checkRequest::checkEmpty('fUid',$fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $counterArr = $unReadObj->getUnreadMessageDetails($toUid);
        $result = Result::getUnReadAllResult(Category::CLIENT_TYPE_PC, $syncStamp, $counterArr);
        $this->messageBase($result);
        return true;
    }

    public function readFromComments()
    {
        $log = new LogRequest('readFromComments', Category::CLIENT_TYPE_PC);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $fdServer = new FdServer();
        $countServer = new CountServer();

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        $fd = $this->caller()->getClient()->getFd();
        $fUid = $fdServer->getSocketUid($fd);

        $msgErrorRet = checkRequest::checkEmpty('fUid',$fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $countServer->clearCommentUnread($toUid);

        $result = Result::getReadFromCommentsResult(Category::CLIENT_TYPE_PC, $syncStamp);
        $this->messageBase($result);
        return true;
    }

    public function readMessage()
    {
        $log = new LogRequest('readMessage', Category::CLIENT_TYPE_PC);
        $log->request($this->body);

        $msgErrorRet = checkRequest::requestData($this->commonKey, $this->body);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $fdServer = new FdServer();
        $countServer = new CountServer();

        $toUid = $this->request->getToUid($this->body['to_uid']);
        $syncStamp = $this->request->getSyncStamp($this->body['syncstamp']);

        $fd = $this->caller()->getClient()->getFd();
        $fUid = $fdServer->getSocketUid($fd);

        $msgErrorRet = checkRequest::checkEmpty('fUid',$fUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $msgErrorRet = checkRequest::checkEq('uid', $fUid, $toUid);
        if (!empty($msgErrorRet)) {
            $log->trackErrorLog($msgErrorRet);
            return $this->response()->setMessage(json_encode($msgErrorRet));
        }

        $countServer->clearMessageUnread($toUid);
        $result = Result::getReadMessageResult(Category::CLIENT_TYPE_PC, $syncStamp);
        $this->messageBase($result);
        return true;
    }
}
