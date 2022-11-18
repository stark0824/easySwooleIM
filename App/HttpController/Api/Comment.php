<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: Comment 与评论相关的Http接口服务
# +----------------------------------------------------------------------

namespace App\HttpController\Api;

use App\Utility\Http\ParamsCheck;
use EasySwoole\Http\Message\Status;
use App\Server\RedisServer\QueueServer;
use App\Server\MysqlServer\PushMsg;
use App\Server\RedisServer\CountServer;

class Comment extends Base
{
    /**
     *  根据类型，接收推送的消息
     */
    public function message(){

        \EasySwoole\EasySwoole\Logger::getInstance()->log('请求方法method:'.$this->method .', 接收到的参数:'.json_encode($this->params) ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'commentMessage');

        $msgErrorRet = ParamsCheck::checkRequestMethod($this->method);
        if(!empty($msgErrorRet))  return $this->writeJson($msgErrorRet['code'],$msgErrorRet['result'],$msgErrorRet['msg']);

        $msgErrorRet = ParamsCheck::checkRequestData(['uid','msg'],$this->params);
        if(!empty($msgErrorRet))  return $this->writeJson($msgErrorRet['code'],$msgErrorRet['result'],$msgErrorRet['msg']);

        if( !empty($this->params['uid']) && !empty($this->params['msg']) ){

            $this->params['msg'] = str_replace(array(PHP_EOL,' '),'' ,$this->params['msg']);
            $msgData = json_decode($this->params['msg'],true);
            $commentUid = empty($msgData['comment_uid']) ? 0 :  $msgData['comment_uid'];

            $uid = (int)$this->params['uid'];
            $noceAck = $this->getNoceAck();

            $pushMsgData = [
                'noce_ack' => $noceAck,
                'to_uid' => $uid,
                'create_time' => time(),
            ];

            $listObj = new QueueServer();
            $listObj->addPushCommentMessage($pushMsgData);

            $model = new PushMsg();
            $model->addComment($uid,$noceAck);

            //计算评论数
            $countServer = new CountServer();
            $countServer->commentsCounter($uid,$commentUid);
        }
        return $this->writeJson(Status::CODE_OK,[],Status::getReasonPhrase(Status::CODE_OK));
    }

}
