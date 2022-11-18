<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: 统一路由入口
# +----------------------------------------------------------------------

namespace App\WebSocketRoute;

use App\Utility\Ws\Category;

class ForwardRoute {

    public $msgType = 0;
    public $clientType = 0;
    private $controller = 'ErrorMessage';
    private $action = 'errorBody';
    public $data = [];
    public $pathRoute = '';

    public function __construct($data = []){

        $this->clientType = $data['client_type'];
        $this->msgType =  $data['msg_type'];
        $this->data = $data;
    }

    public function _getRequest(){
       return [$this->clientType , $this->msgType ];
    }

    public function _getRouter() {

        $flag = $this->_check();
        if($flag){
            return $this->pathRoute;
        }

        $this->_setController();
        $this->_setAction();
        $versionNumber = $this->_getVersionNumber();
        $this->pathRoute = "App\\WebSocketController\\{$versionNumber}\\{$this->controller}";
        return $this->pathRoute;
    }

    private function _setController(){
        $this->controller = Category::getClientControllerName($this->clientType);
    }

    private function _setAction(){
        $this->action = Category::getMsgTypeName($this->msgType);
    }

    public function _getAction(){
        return $this->action;
    }

    protected function _getVersionNumber(){

        $versionNumber = 'V1';
        if(in_array($this->msgType,Category::$msgTypeV1)){
            $versionNumber = 'V1';
        } else if(in_array($this->msgType,Category::$msgTypeV2)){
            $versionNumber = 'V2';
        }  else if(in_array($this->msgType,Category::$msgTypeV3)){
            $versionNumber = 'V3';
        }else if(in_array($this->msgType,Category::$msgTypeV4)){
            $versionNumber = 'V4';
        }else if(in_array($this->msgType,Category::$msgTypeV5)) {
            $versionNumber = 'V5';
        }
        return $versionNumber;
    }

    public function _formatBody(){
        $data['body'] = empty($this->data['body']) ? [] : $this->data['body'];
        $data['body']['syncstamp']  = empty($this->data['syncstamp']) ? 0 : (int)$this->data['syncstamp'];
        return $data['body'];
    }

    public function _check() {

        $flag = false;
        if(empty($this->msgType) || empty($this->clientType)) {
            $flag = true;
        }

        if( !in_array($this->msgType , Category::getMsgTypeDict() ) ) {
            $flag = true;
        }

        if( !in_array($this->clientType ,Category::getClientType()) ) {
            $flag = true;
        }

        if($flag === true){
            $this->controller = 'ErrorMessage';
            $this->pathRoute = "App\\WebSocketController\\Error\\{$this->controller}";
        }
        return $flag;
    }

}
