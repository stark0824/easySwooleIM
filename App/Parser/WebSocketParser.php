<?php
/**
 * @author zhangyu
 */

namespace App\Parser;

use EasySwoole\Socket\AbstractInterface\ParserInterface;
use EasySwoole\Socket\Bean\Caller;
use EasySwoole\Socket\Bean\Response;
use App\WebSocketRoute\ForwardRoute;

class WebSocketParser implements ParserInterface
{
    public $data = [];
    public $action = '';
    public $body = [];

    /**
     * 解析器避免高耦合，从解析器开始分发请求，使控制器分离
     */
    public function decode($raw, $client): ?Caller
    {
        $caller = new Caller();
        $this->data = json_decode($raw, true);

        $toolRoute = new ForwardRoute($this->data);
        $controllerRoute = $toolRoute->_getRouter();
        $this->action = $toolRoute->_getAction();
        $this->body = $toolRoute->_formatBody();

        $caller->setControllerClass($controllerRoute);
        $caller->setAction($this->action);
        $caller->setArgs($this->body);
        return $caller;
    }

    public function encode(Response $response, $client): ?string
    {
        return $response->getMessage();
    }

}
