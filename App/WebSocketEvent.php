<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: WebSocketEvent 监听事件
# +----------------------------------------------------------------------
namespace App;

use \swoole_server;
use \swoole_websocket_server;
use \swoole_http_request;
use EasySwoole\EasySwoole\Task\TaskManager;
use App\Models\ImModel;
use EasySwoole\ORM\DbManager;
use EasySwoole\EasySwoole\Config;
use App\Utility\Ws\Category;

class WebSocketEvent
{
    const MYSQL_CONN_NAME = 'mysql-msg';
    /**
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    public function onHandShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        /** 此处自定义握手规则 返回 false 时中止握手 */
        if (!$this->customHandShake($request, $response)) {
            $response->end();
            return false;
        }

        /** 此处是  RFC规范中的WebSocket握手验证过程 必须执行 否则无法正确握手 */
        if ($this->secWebsocketAccept($request, $response)) {
            $response->end();
            return true;
        }

        $response->end();
        return false;
    }

    /**
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    protected function customHandShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        /**
         * 这里可以通过 http request 获取到相应的数据
         * 进行自定义验证后即可
         * (注) 浏览器中 JavaScript 并不支持自定义握手请求头 只能选择别的方式 如get参数
         */
        $headers = $request->header;
        $cookie = $request->cookie;

        // if (如果不满足我某些自定义的需求条件，返回false，握手失败) {
        //    return false;
        // }
        return true;
    }

    /**
     * RFC规范中的WebSocket握手验证过程
     * 以下内容必须强制使用
     *
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    protected function secWebsocketAccept(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        // ws rfc 规范中约定的验证过程
        if (!isset($request->header['sec-websocket-key'])) {
            // 需要 Sec-WebSocket-Key 如果没有拒绝握手
            var_dump('shake fai1 3');
            return false;
        }
        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $request->header['sec-websocket-key'])
            || 16 !== strlen(base64_decode($request->header['sec-websocket-key']))
        ) {
            //不接受握手
            var_dump('shake fai1 4');
            return false;
        }

        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $headers = array(
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
            'KeepAlive' => 'off',
        );

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        // 发送验证后的header
        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        // 接受握手 还需要101状态码以切换状态
        $response->status(101);
        var_dump('shake success at fd :' . $request->fd);
        return true;
    }



    /**
     * 打开了一个链接
     * @param swoole_websocket_server $server
     * @param swoole_http_request $request
     */
    static function onOpen(\swoole_websocket_server $server, \swoole_http_request $request)
    {

             //var_dump($request);

    }

    /**
     * 链接被关闭时
     * @param swoole_server $server
     * @param int $fd
     * @param int $reactorId
     * @throws Exception
     */
    static function onClose(\swoole_server $server, int $fd, int $reactorId)
    {
        /** @var array $info */
        $info = $server->getClientInfo($fd);

        /**
         * 判断此fd 是否是一个有效的 websocket 连接
         * 参见 https://wiki.swoole.com/wiki/page/490.html
         */
        if ($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME)
        {
            /**
             * 判断连接是否是 server 主动关闭
             * 参见 https://wiki.swoole.com/wiki/page/p-event/onClose.html
             */
            TaskManager::getInstance()->async(function () use ( $fd ) {

                \EasySwoole\RedisPool\RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($fd) {
                    //回收用户
                    $fUid = $redis->hGet('PUSH_MSG_SOCKET_FD', $fd);
                    if (isset($fUid) && !empty($fUid) && is_numeric($fUid)) {
                        $redis->zRem('PUSH_MSG_SSET_USER_LOGIN', $fd);
                        //检测是否有客服关系未断开
                        $redis->del(Category::$imUserRelationName.$fUid);
                        $redis->hDel('PUSH_MSG_SOCKET_FD', $fd);

                        DbManager::getInstance()->invoke(function ($client) use ($fUid) {
                            $model = ImModel::invoke($client);
                            $model->where('to_uid', (int)$fUid )->where('im_status',1)->update(['im_status' => 2]);
                        }, self::MYSQL_CONN_NAME);

                    }

                    //回收cpAdmin客服管理用户
                    $cUid = $redis->hGet('PUSH_CUSTOMER_MSG_SOCKET_FD', $fd);
                    if (isset($cUid) && !empty($cUid)) {
                        $redis->zRem('PUSH_CUSTOMER_MSG_SSET_USER_LOGIN', intval($fd));
                        $redis->hDel('PUSH_CUSTOMER_MSG_SOCKET_FD', intval($fd));

                        DbManager::getInstance()->invoke(function ($client) use ($cUid) {
                            $model = ImModel::invoke($client);
                            $model->where('virtual_uid', (int)$cUid )->where('im_status',1)->update(['im_status' => 2]);
                        }, self::MYSQL_CONN_NAME);
                    }

                }, 'redis');
            });
        }

    }

    /**
     * @param swoole_server $server
     * 停止服务时，回收客服管理员的列表
     */
    static function onShutdown(\swoole_server $server){

        go(function (){
            $redisConf = Config::getInstance()->getConf('redis');
            $redis = new \EasySwoole\Redis\Redis(new \EasySwoole\Redis\Config\RedisConfig($redisConf));
            $redis->del('PUSH_CUSTOMER_MSG_SOCKET_FD','PUSH_CUSTOMER_MSG_SSET_USER_LOGIN','PUSH_MSG_SOCKET_FD','PUSH_MSG_SSET_USER_LOGIN');
        });
    }
}
