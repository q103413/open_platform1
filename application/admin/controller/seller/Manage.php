<?php
namespace app\admin\controller\seller;
use app\admin\model\AdminLog;
use app\common\controller\Backend;
use fast\Random;
use fast\Tree;
use \Think\Db ;

/**
 * 管理员管理
 *
 * @icon fa fa-users
 * @remark 一个管理员可以有多个角色组,左侧的菜单根据管理员所拥有的权限进行生成
 */
class Manage extends Backend
{

    protected $model = null;
    //当前登录管理员所有子节点组别
    protected $childrenIds = [];
     //有查看商家权限的level,包括OEM和三级代理
     // protected $checkSellerLevel;
     //有添加权限的level
     protected $addSellerLevel;

    public function _initialize()
    {
        parent::_initialize();
        //初始化用户级别
        parent::initLevel();

        // $this->checkSellerLevel = array_slice(config('levels') ,2,4 );
        $this->model = model('Admin');

        $this->addSellerLevel = array_slice(config('levels') ,3,3 );
        //禁止商家查看下级
        if ( $this->level >= config('levels')['seller'] ) {
            // var_dump('no auth to manage sellers');
            $this->code = -1;
            $this->msg = 'no auth';
            return;
        }
        // var_dump($groupdata);
        $this->view->assign('groupdata', '下级商户');
    }

    public function index()
    {
        if ($this->request->isAjax() )
        {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            //搜索条件
            $search = $this->request->get("search", '');
            $where = '';
            if ($search) {
                $where = " AND a.username LIKE '%".$search."%' ";
            }
            //平台有查看所有商家的权限
            if ( config('levels')['pt'] == $this->level ) {
                //SELECT * FROM  fa_admin as a WHERE (a.id IN ( SELECT id from fa_auth_group as b WHERE b.id in (60,61,62,63,64) ) )
                $sql = 'SELECT * FROM fa_admin a JOIN fa_auth_group_access b ON a.id = b.uid AND b.group_id = ' . config('levels')['seller'];
            //OEM和渠道和代理有查看该下的商家的权限
            } else  {
                $sql = 'SELECT * FROM fa_admin a JOIN fa_auth_group_access b ON a.id = b.uid AND b.group_id =' . config('levels')['seller'] .' AND b.all_add_uid LIKE "%'.$this->uid.'%" ';
            }
            // var_dump($where);return;

           $res =   Db::query( $sql . ' ORDER BY a.'."$sort DESC limit $offset,$limit " );
           $res = array("total" => count($res), "rows" => $res);

           return json($res);
        }
          if ( config('levels')['pt'] == $this->level ) {
              //SELECT * FROM  fa_admin as a WHERE (a.id IN ( SELECT id from fa_auth_group as b WHERE b.id in (60,61,62,63,64) ) )
              $sql = 'SELECT * FROM fa_admin a JOIN fa_auth_group_access b ON a.id = b.uid AND b.group_id = ' . config('levels')['seller'];
          //OEM和渠道和代理有查看该下的商家的权限
          } else  {
              $sql = 'SELECT * FROM fa_admin a JOIN fa_auth_group_access b ON a.id = b.uid AND b.group_id =' . config('levels')['seller'] .' AND b.all_add_uid LIKE "%'.$this->uid.'%" ';
          }

        $sellers =   Db::query( $sql );
        $res = new \app\admin\controller\appstore\Applist();
        // var_dump($res->test());
        $this->view->assign("sellers",$sellers );
        $this->view->assign("apps",$res->test('no_default'));
        return $this->view->fetch();
    }
    /**
     * 添加
     */
    public function add()
    {
        if ( !in_array( $this->level,  $this->addSellerLevel) ) {
            // var_dump('no auth to manage sellers');
            $this->code = -1;
            $this->msg = 'no auth';
            return;
        }
        if ($this->request->isPost())
        {
            $this->code = -1;
            $params = $this->request->post("row/a");
            if ($params)
            {
               //商户等级配置
               $level = config('levels')['seller'];
               //记录所有上级uid，方便查询
               $res = Db::name('auth_group_access')->where('uid', $this->uid)->field('all_add_uid')->find();
               $allAddUid = $res['all_add_uid'] . $this->uid . ',';
               //用户信息
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']) . $params['salt']);
                $admin = $this->model->create($params);
                AdminLog::record(__('Add'), $this->model->getLastInsID());
                //写入映射表
                $dataset = ['uid' => $admin->id, 'group_id' => $level, 'add_uid'=> $this->uid, 'all_add_uid'=>$allAddUid];

                model('AuthGroupAccess')->save($dataset);
                $this->code = 1;
            }

            return;
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = NULL)
    {
        if ( !in_array( $this->level,  $this->addSellerLevel) ) {
            // var_dump('no auth to manage sellers');
            $this->code = -1;
            $this->msg = 'no auth';
            return;
        }
        $row = $this->model->get(['id' => $ids]);
        if (!$row)
            $this->error(__('No Results were found'));
        if ($this->request->isPost())
        {
            $this->code = -1;
            $params = $this->request->post("row/a");
            if ($params)
            {
                if ($params['password'])
                {
                    $params['salt'] = Random::alnum();
                    $params['password'] = md5(md5($params['password']) . $params['salt']);
                }
                // db('admin')->where('id',$ids)->update(['name' => 'thinkphp']);
                $res = $row->save($params);
                AdminLog::record(__('Edit'), $ids);
                if ($res>0) {
                   $this->code = 1;
                   $this->msg = '修改成功';
                   return;
                }
               // var_dump( $row->getLastSql() );

                // 先移除所有权限
                // model('AuthGroupAccess')->where('uid', $row->id)->delete();

                // $group = $this->request->post("group/a");

                // // 过滤不允许的组别,避免越权
                // $group = array_intersect($this->childrenIds, $group);

                // $dataset = [];
                // $uid = (int)json_decode( $_SESSION['think']['admin'])->id;
                // foreach ($group as $value)
                // {
                //     $dataset[] = ['uid' => $row->id, 'group_id' => $value,'add_uid'=>$uid];
                // }
                // model('AuthGroupAccess')->saveAll($dataset);
                // $this->code = 1;
            }

            return;
        }
        $grouplist = $this->auth->getGroups($row['id']);
        $groupids = [];
        foreach ($grouplist as $k => $v)
        {
            $groupids[] = $v['id'];
        }
        $this->view->assign("row", $row);
        $this->view->assign("groupids", $groupids);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        // if ( !in_array( $this->level,  $this->addSellerLevel) ) {
            // var_dump('no auth to manage sellers');
            $this->code = -1;
            $this->msg = 'no auth';
            return;
        // }

        $this->code = -1;
        if ($ids)
        {
            // 避免越权删除管理员
            $childrenGroupIds = $this->childrenIds;
            $adminList = $this->model->where('id', 'in', $ids)->where('id', 'in', function($query) use($childrenGroupIds)
                    {
                        $query->name('auth_group_access')->where('group_id', 'in', $childrenGroupIds)->field('uid');
                    })->select();
            if ($adminList)
            {
                $deleteIds = [];
                foreach ($adminList as $k => $v)
                {
                    $deleteIds[] = $v->id;
                }
                $deleteIds = array_diff($deleteIds, [$this->auth->id]);
                if ($deleteIds)
                {
                    AdminLog::record(__('Del'), $deleteIds);
                    $this->model->destroy($deleteIds);
                    model('AuthGroupAccess')->where('uid', 'in', $deleteIds)->delete();
                    $this->code = 1;
                }
            }
        }

        return;
    }

    /**
     * 批量更新
     * @internal
     */
    public function multi($ids = "")
    {
        // 管理员禁止批量操作
        $this->code = -1;
    }

}
