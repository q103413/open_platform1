<?php

namespace app\admin\controller\authop;

use app\admin\model\AdminLog;
use app\common\controller\Backend;
use fast\Tree;
use \Think\Db ;
/**
 * 角色组
 *
 * @icon fa fa-group
 * @remark 角色组可以有多个,角色有上下级层级关系,如果子角色有角色组和管理员的权限则可以派生属于自己组别下级的角色组或管理员
 */
class Groupop extends Backend
{

    protected $model = null;
    //当前登录管理员所有子节点组别
    protected $childrenIds = [];
    //当前组别列表数据
    protected $groupdata = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AuthGroup');

        $groups = $this->auth->getGroups();

        // 取出所有分组
        // $grouplist = model('AuthGroup')->all(['status' => 'normal', 'pid'=>7]);
        // $uid = (int)json_decode( $_SESSION['think']['admin'])->id;
        // $res = Db::query('SELECT group_id FROM '.config('database.prefix').'auth_group_access WHERE uid = ' . $uid);
        // $groupId = $res[0]['group_id'];//组别id
        // $grouplist = Db::table(config('database.prefix').'auth_group')->whereOr(['id'=> $groupId,'pid'=>$groupId])->select();
        $grouplist = Db::query( 'SELECT * FROM '.config('database.prefix').'auth_group a JOIN '.config('database.prefix').'auth_group_access b on b.uid = '.$this->uid.' and ( a.id = b.group_id OR ( a.pid = b.group_id AND a.type = 1) )');
        // var_dump( $this->model->getLastSql() );
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
        $this->groupdata = $groupdata;
        $this->childrenIds = array_keys($groupdata);
        $this->view->assign('groupdata', $groupdata);
    }

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax())
        {
            $list = [];
            foreach ($this->groupdata as $k => $v)
            {
                $data = $this->model->get($k);
                $data->name = $v;
                $list[] = $data;
            }
            $total = count($list);
            $result = array("total" => $total, "rows" => $list, "test"=>'test');
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        $res = Db::query('SELECT b.type FROM fa_auth_group_access a JOIN fa_auth_group b ON a.uid = 1 AND a.group_id = b.id');
        if ($res[0]['type'] == 1) {
            $this->code = -1;
            $this->msg = '操作员没有权限增加下级角色组';
            return;
        }
        if ($this->request->isPost())
        {
            // $this->code = -1;
            $params = $this->request->post("row/a");
            $params['rules'] = explode(',', $params['rules']);
            $res = Db::query('SELECT group_id FROM fa_auth_group_access WHERE uid = ' . $this->uid);
            
            $groupId = $res[0]['group_id'];//组别id
            if ($params['pid'] != $groupId) {
               $this->code = -1;
               $this->msg = __('The parent group is not right');
               return;
            }
            if (!in_array($params['pid'], $this->childrenIds))
            {
                $this->code = -1;
                $this->msg = __('');
                return;
            }
            $parentmodel = model("AuthGroup")->get($params['pid']);
            if (!$parentmodel)
            {
                $this->msg = __('The parent group can not found');
                return;
            }
            // 父级别的规则节点
            $parentrules = explode(',', $parentmodel->rules);
            // 当前组别的规则节点
            $currentrules = $this->auth->getRuleIds();
            $rules = $params['rules'];
            // 如果父组不是超级管理员则需要过滤规则节点,不能超过父组别的权限
            $rules = in_array('*', $parentrules) ? $rules : array_intersect($parentrules, $rules);
            // 如果当前组别不是超级管理员则需要过滤规则节点,不能超当前组别的权限
            $rules = in_array('*', $currentrules) ? $rules : array_intersect($currentrules, $rules);
            $params['rules'] = implode(',', $rules);
            //操作员权限1，下级用户权限2
            $params['type'] = 1;
            if ($params)
            {
                $this->model->create($params);
                AdminLog::record(__('Add'), $this->model->getLastInsID());
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
            // 父节点不能是它自身的子节点
            if (!in_array($params['pid'], $this->childrenIds))
            {
                $this->msg = __('The parent group can not be its own child');
                return;
            }
            $params['rules'] = explode(',', $params['rules']);

            $parentmodel = model("AuthGroup")->get($params['pid']);
            if (!$parentmodel)
            {
                $this->msg = __('The parent group can not found');
                return;
            }
            // 父级别的规则节点
            $parentrules = explode(',', $parentmodel->rules);
            // 当前组别的规则节点
            $currentrules = $this->auth->getRuleIds();
            $rules = $params['rules'];
            // 如果父组不是超级管理员则需要过滤规则节点,不能超过父组别的权限
            $rules = in_array('*', $parentrules) ? $rules : array_intersect($parentrules, $rules);
            // 如果当前组别不是超级管理员则需要过滤规则节点,不能超当前组别的权限
            $rules = in_array('*', $currentrules) ? $rules : array_intersect($currentrules, $rules);
            $params['rules'] = implode(',', $rules);
            if ($params)
            {
                $row->save($params);
                AdminLog::record(__('Edit'), $ids);
                $this->code = 1;
            }

            return;
        }
        $this->view->assign("row", $row);
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
            $ids = explode(',', $ids);
            $grouplist = $this->auth->getGroups();
            $group_ids = array_map(function($group)
            {
                return $group['id'];
            }, $grouplist);
            // 移除掉当前管理员所在组别
            $ids = array_diff($ids, $group_ids);

            // 循环判断每一个组别是否可删除
            $grouplist = $this->model->where('id', 'in', $ids)->select();
            $groupaccessmodel = model('AuthGroupAccess');
            foreach ($grouplist as $k => $v)
            {
                // 当前组别下有管理员
                $groupone = $groupaccessmodel->get(['group_id' => $v['id']]);
                if ($groupone)
                {
                    $ids = array_diff($ids, [$v['id']]);
                    continue;
                }
                // 当前组别下有子组别
                $groupone = $this->model->get(['pid' => $v['id']]);
                if ($groupone)
                {
                    $ids = array_diff($ids, [$v['id']]);
                    continue;
                }
            }
            if (!$ids)
            {
                $this->msg = __('You can not delete group that contain child group and administrators');
                return;
            }
            $count = $this->model->where('id', 'in', $ids)->delete();
            if ($count)
            {
                AdminLog::record(__('Del'), $ids);
                $this->code = 1;
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
        // 组别禁止批量操作
        $this->code = -1;
        return;
    }

}
