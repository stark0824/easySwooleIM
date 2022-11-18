<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-03
# +----------------------------------------------------------------------
# | Desc: PushMsgModel model类
# +----------------------------------------------------------------------

namespace App\Models;
use EasySwoole\ORM\AbstractModel;


class PushMsgModel extends AbstractModel
{
    //选择连接的数据库
    protected $connectionName = 'mysql-msg';

    protected $tableName = 'user_push_msg_0';

}