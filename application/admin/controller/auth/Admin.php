<?php
namespace app\admin\controller\auth;
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
class Admin extends Backend
{

    protected $model = null;
    //当前登录管理员所有子节点组别
    protected $childrenIds = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Admin');
        //初始化用户级别
        parent::initLevel();
        //显示下级名称
         $map['id'] = $this->level + 1;
         $groupName = Db::table(config('database.prefix').'auth_group')->where($map)->find();
 
        $this->view->assign('groupdata', $groupName['name']);
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost())
        {
            $this->code = -1;
            $params = $this->request->post("row/a");
            if ($params)
            {
                //读取上级权限id
                // $uid = (int)json_decode( $_SESSION['think']['admin'])->id;
                // $res = Db::query('SELECT a.pid FROM '.config('database.prefix').'auth_group a JOIN '.config('database.prefix').'auth_group_access b ON b.uid = '.$this->uid.' AND b.group_id = a.id AND a.type = 2');
                // if (!$res) {
                //    $this->code = -1;
                //    $this->msg = 'no pid'.$this->uid;
                //    return ;
                // }
                // $pid = $res[0]['pid'];
                //记录所有上级uid，方便查询
                $level =  $this->level +1;
                $res = Db::name('auth_group_access')->where('uid', $this->uid)->field('all_add_uid')->find();
              
                $allAddUid = $res['all_add_uid'] . $this->uid . ',';

                //查出下级权限
                // $res = Db::query('SELECT * FROM '.config('database.prefix').'auth_group WHERE pid = ' . $pid );

                //添加下级信息
                // $data = ['pid' => $pid, 'name' => $res[0]['name'], 'rules'=>$res[0]['rules'],'type'=>2,'createtime'=>time(),'updatetime'=>time(),'status'=>'normal'];
                // $groupId = Db::name('auth_group')->insertGetId($data);
                // var_dump($res);
                // return;
                // $group = $this->request->post("group/a");
                // foreach ($group as $v) {
                //     //添加的权限组ID必须大于当前的权限组ID
                //    if ($groupId >= $v) {
                //      $this->code = -1;
                //      $this->msg = __('The groupId is not right');
                //      return;
                //    }
                // }
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']) . $params['salt']);
                $admin = $this->model->create($params);
                AdminLog::record(__('Add'), $this->model->getLastInsID());

                //过滤不允许的组别,避免越权
                // $group = array_intersect($this->childrenIds, $group);
                // $dataset = [];
                // foreach ($group as $value)
                // {
                $dataset = ['uid' => $admin->id, 'group_id' => $level, 'add_uid'=> $this->uid, 'all_add_uid'=>$allAddUid];
                // }
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
        $this->code = -1;
        $this->msg = 'no finish';
        return;
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
