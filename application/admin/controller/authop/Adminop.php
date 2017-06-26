<?php
namespace app\admin\controller\authop;
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
class Adminop extends Backend
{

    protected $model = null;
    //当前登录管理员所有子节点组别
    protected $childrenIds = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Admin');

        $groups = $this->auth->getGroups();
        // 取出所有分组
        // $uid = (int)json_decode( $_SESSION['think']['admin'])->id;
        // $res = Db::query('SELECT group_id FROM '.config('database.prefix').'auth_group_access WHERE uid = ' . $uid);
        // $groupId = $res[0]['group_id'];//组别id
        // $grouplist = Db::table(config('database.prefix').'auth_group')->whereOr(['id'=> $groupId,'pid'=>$groupId])->select();
        // $grouplist = model('AuthGroup')->all(['id'=> $groupId,'pid'=>$groupId]);
        $grouplist = Db::query('SELECT * FROM '.config('database.prefix').'auth_group a JOIN '.config('database.prefix').'auth_group_access b WHERE b.uid = '.$this->uid.' AND (a.id = b.group_id OR (a.pid = b.group_id AND a.type = 1) )');
        $objlist = [];
        foreach ($groups as $K => $v)
        {   
            // 取出包含自己的所有子节点
            $childrenlist = Tree::instance()->init($grouplist)->getChildren($v['id'], TRUE);
            $obj = Tree::instance()->init($childrenlist)->getTreeArray($v['pid']);
            $objlist = array_merge($objlist, Tree::instance()->getTreeList($obj));
        }
        $groupdata = [];
        foreach ($objlist as $k => $v)
        {
            $groupdata[$v['id']] = $v['name'];
        }
        $this->childrenIds = array_keys($groupdata);
        // var_dump($groupdata);
        $this->view->assign('groupdata', $groupdata);
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
                //校验是否越权
                // $uid = (int)json_decode( $_SESSION['think']['admin'])->id;
                $res = Db::query('SELECT group_id FROM fa_auth_group_access WHERE uid = ' . $this->uid);
                $groupId = $res[0]['group_id'];//当前用户组别id
                $group = $this->request->post("group/a");
                foreach ($group as $v) {
                    //添加的权限组ID必须大于当前的权限组ID
                   if ($groupId >= $v) {
                     $this->code = -1;
                     $this->msg = __('The groupId is not right');
                     return;
                   }
                }
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']) . $params['salt']);
                $admin = $this->model->create($params);
                AdminLog::record(__('Add'), $this->model->getLastInsID());

                //过滤不允许的组别,避免越权
                $group = array_intersect($this->childrenIds, $group);
                // var_dump($group);
                $dataset = [];
                foreach ($group as $value)
                {
                    $dataset[] = ['uid' => $admin->id, 'group_id' => $value, 'add_uid'=> $this->uid];
                }
                model('AuthGroupAccess')->saveAll($dataset);
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
                $row->save($params);
                AdminLog::record(__('Edit'), $ids);

                // 先移除所有权限
                model('AuthGroupAccess')->where('uid', $row->id)->delete();

                $group = $this->request->post("group/a");

                // 过滤不允许的组别,避免越权
                $group = array_intersect($this->childrenIds, $group);

                $dataset = [];
                foreach ($group as $value)
                {
                    $dataset[] = ['uid' => $row->id, 'group_id' => $value];
                }
                model('AuthGroupAccess')->saveAll($dataset);
                $this->code = 1;
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
