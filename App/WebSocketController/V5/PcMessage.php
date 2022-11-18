<?php

namespace App\WebSocketController\V5;

use Swoole\Websocket\Server;
use App\WebSocketController\Base;
use App\Server\RedisServer\CountServer;
use App\Utility\Ws\{Result,Category,LogRequest,CheckRequest as checkRequest};

class PcMessage extends Base
{
    public function clearReadNumber()
    {
        $log = new LogRequest('clearReadNumber', Category::CLIENT_TYPE_PC);
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

        $result = Result::unReadMessageResult(Category::CLIENT_TYPE_PC, $syncStamp);
        $this->messageBase($result);
        return true;
    }
}
