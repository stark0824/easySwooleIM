<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-03
# +----------------------------------------------------------------------
# | Desc: ImModel model类
# +----------------------------------------------------------------------

namespace App\Models;
use EasySwoole\ORM\AbstractModel;


class ImModel extends AbstractModel
{
    //选择连接的数据库
    protected $connectionName = 'mysql-msg';

    protected $tableName = 'im_user_relation';

}
