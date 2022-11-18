<?php

return [
    //mysql数据库配置
    'mysql-msg' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'swoole_msg',
        'username' => 'demo',
        'password' => '123456',
        'timeout' => 300,
        'charset'  => 'utf8mb4'
    ],

    //Mysql连接池配置
    'conn_pool' => [
        'timeOut'             =>  '3.0',    //设置获取连接池对象超时时间
        'checkOut'            =>  30*1000,  //设置检测连接存活执行回收和创建的周期
        'maxidleTime'         =>  15,       //连接池对象最大闲置时间(秒)
        'maxObjectNumber'     =>  100,       //设置最大连接池存在连接对象数量
        'minObjectNumber'     =>  5,        //设置最小连接池存在连接对象数量
        'autoPing'            =>  5,        //设置自动ping客户端链接的间隔
    ],

];
