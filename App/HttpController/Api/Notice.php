<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-03
# +----------------------------------------------------------------------
# | Desc: Notice 与通知相关的Http接口服务
# +----------------------------------------------------------------------

namespace App\HttpController\Api;

use App\Server\MysqlServer\PushMsg;
use EasySwoole\Http\Message\Status;
use App\Utility\Http\ParamsCheck;
use App\Server\RedisServer\CountServer;
use App\Server\RedisServer\QueueServer;

class Notice extends Base
{
    /**
     *  接收个人通知的推送的消息
     */
    public function userMessage()
    {
        //{"send_id":"1","title":"\u8fd9\u4e2a\u662f\u4ec5\u6587\u5b57\u6a21\u7248\u6807\u9898","content":"\u8fd9\u4e2a\u662f\u4ec5\u6587\u5b57\u5185\u5bb9\u6a21\u7248\u6807\u9898","extras":{"model_type":1}}
        //{"send_id":"1","title":"\u6587\u5b57\u52a0\u94fe\u63a5\u6807\u9898","content":"\u6587\u5b57\u52a0\u94fe\u63a5\u5185\u5bb9","extras":{"share_url":"https:\/\/gongzicp.com\/novel-888714.html","model_type":2}}
        //{"send_id":"1","title":"\u8fd9\u91cc\u662f\u94fe\u63a5\u6807\u9898","content":"\u8fd9\u91cc\u662f\u94fe\u63a5\u5185\u5bb9","extras":{"share_title":"\u8fd9\u91cc\u662f\u94fe\u63a5\u5206\u4eab\u6807\u9898","share_url":"https:\/\/gongzicp.com\/novel-888714.html","model_type":3}}
        //{"send_id":"1","title":"\u8fd9\u91cc\u662f\u6587\u5b57\u52a0\u56fe\u7247\u6807\u9898","content":"\u8fd9\u91cc\u662f\u6587\u5b57\u52a0\u56fe\u7247\u5185\u5bb9","extras":{"cover":"https:\/\/resourcecp.oss-cn-beijing.aliyuncs.com\/uploads\/20220818\/64075d3fc665445e95996050aa33787c.png","model_type":4}}
        \EasySwoole\EasySwoole\Logger::getInstance()->log('请求方法method:'.$this->method ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'userMessage');

        $msgErrorRet = ParamsCheck::checkRequestMethod($this->method);
        if(!empty($msgErrorRet))  return $this->writeJson($msgErrorRet['code'],$msgErrorRet['result'],$msgErrorRet['msg']);

        $msgErrorRet = ParamsCheck::checkRequestData(['uid','msg'],$this->params);
        if(!empty($msgErrorRet))  return $this->writeJson($msgErrorRet['code'],$msgErrorRet['result'],$msgErrorRet['msg']);

        if ( !empty($this->params['msg']) && !empty($this->params['uid']) ) {
            $noceAck = $this->getNoceAck();
            $msg = $this->params['msg'];

            $pushMsgArray = [
                'noce_ack' => $noceAck ,
                'to_uid' => $this->params['uid'] ,
                'create_time' => time(),
                'msg' => $msg
            ];

            \EasySwoole\EasySwoole\Logger::getInstance()->log('msg:'.$this->params['msg'] ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'userMessage');
            \EasySwoole\EasySwoole\Logger::getInstance()->log('uid:'.$this->params['uid'] ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'userMessage');

            $listObj = new QueueServer();
            $listObj->addPushUserNoticeMessage($pushMsgArray);

            $countServer = new CountServer();
            $countServer->noticeCounter($pushMsgArray);

            $model = new PushMsg();
            $model->addNotice($this->params['uid'],$noceAck,'');

            return $this->writeJson(Status::CODE_OK,[],Status::getReasonPhrase(Status::CODE_OK));
        } else {
            return $this->writeJson(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE,[],Status::getReasonPhrase(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE));
        }
    }

    /**
     * @return bool 15分钟之内只接收一次全站消息
     */
    public function systemMessage(){

        \EasySwoole\EasySwoole\Logger::getInstance()->log('请求方法method:'.$this->method ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'systemMessage');
        $msgErrorRet = ParamsCheck::checkRequestMethod($this->method);
        $lock = \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis){
            return  $redis->get('systemMessageLock');
        },self::REDIS_CONN_NAME );

        if($lock){
            return $this->writeJson(Status::CODE_OK,[],Status::getReasonPhrase(Status::CODE_OK));
        }

        if(!empty($msgErrorRet))  return $this->writeJson($msgErrorRet['code'],$msgErrorRet['result'],$msgErrorRet['msg']);

        if ( isset($this->params['msg']) && !empty($this->params['msg']) &&  ( $this->params['uid'] == 0 ) ){

            \EasySwoole\EasySwoole\Logger::getInstance()->log('msg:'.$this->params['msg'] ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_INFO,'systemMessage');
            $msg =  (string)$this->params['msg'];

            $msgRequest = json_decode($msg,true);
            // $msgRequest['tag_type'] 2 全部用户  3 全部作者  4 签约作者

            if(!isset($msgRequest['tag_type']) || !in_array($msgRequest['tag_type'],[2,3,4])){
                return $this->writeJson(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE,[],Status::getReasonPhrase(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE));
            }
            //全站消息只发给站内在线用户，Mysql不做存储
            $listObj = new QueueServer();
            if(2 == $msgRequest['tag_type']){
                $listObj->addPushSystemNoticeMessage($msg);
            }else if(3 == $msgRequest['tag_type']){
                $listObj->addPushAllAuthorNoticeMessage($msg);
            }else if(4 == $msgRequest['tag_type']){
                $listObj->addPushSignAuthorNoticeMessage($msg);
            }
            return $this->writeJson(Status::CODE_OK,[],Status::getReasonPhrase(Status::CODE_OK));
        }else{
            return $this->writeJson(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE,[],Status::getReasonPhrase(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE));
        }
    }
}
