<?php

namespace app\admin\controller\appstore;

use app\admin\model\AdminLog;
use app\admin\model\App_store;
use app\common\controller\Backend;
use fast\Tree;
use \Think\Db;
/**
 * 角色组
 *
 * @icon fa fa-group
 * @remark 角色组可以有多个,角色有上下级层级关系,如果子角色有角色组和管理员的权限则可以派生属于自己组别下级的角色组或管理员
 */
class Applist extends Backend
{

    protected $model = null;
    //当前登录管理员所有子节点组别
    protected $childrenIds = [];
    //当前组别列表数据
    protected $groupdata = [];

    public function _initialize()
    {
        parent::_initialize();
        //初始化用户级别
        parent::initLevel();
        $this->view->assign('groupdata', '添加应用');
    }

    /**
     * 查看应用
     */
    public function index()
    {
        if ($this->request->isAjax() )
        {
            $applist= $this->test();
            $total = count($applist);
            $result = array("total" => $total, "rows" => $applist, "test"=>'test');
            return json($result);
        }
        $this->view->assign('allapps',$this->test());
        return $this->view->fetch();
    }
    public function test($no_default='')
    {
       $params = $this->request->post("row/a");
       if ( $params['userid'] && is_int($params['userid']) ) {
          $uid = $params['userid'];
       }else{
          $uid = $this->uid;
       }
       $where = '';
       //只查询非默认应用
       if ($no_default) {
           $where = $where . ' and a.is_default = 1';
       }
       $applist = Db::query('SELECT a.* FROM  '.config('database.prefix').'app_store a JOIN '.config('database.prefix').'app_admin_access b ON b.uid = '.$uid.' AND b.app_id = a.id '.$where);
       //应用包括非默认应用和默认应用
       if (!$no_default) {
          $applist2 =  Db::query('SELECT * FROM fa_app_store WHERE is_default = 2 and state = 1');
          $applist = array_merge($applist,$applist2);
       }
       return $applist;
    }
    //更改是否默认APP
    public function defaultApps(){
        $appIds = $this->request->post("appid/a");
        //取消默认
        $cancel = $this->request->post("cancel/a");
        if (!$appIds) {
          $this->code = -1;
          $this->msg = __('empty data is not allowed');
          return;
        }
        //2表示默认应用，1表示非默认应用
        if ($cancel) {
            $default = 1;
        }else{
            $default = 2;
        }
        $list = [];
        foreach ($appIds as $key => $value) {
            //写入前查出应用默认进行判断
            $res = app_store::get((int)$value);
            if (!$cancel && $res->is_default == 2 ) {
               $this->code = -1;
               $this->msg = __('设置默认应用有重复');
               return;
            }
            if ($cancel && ($res->add_user_level > 0) && ( $res->add_user_level < $this->level) ) {
               $this->code = -1;
               $this->msg = __('不能取消上级设置的默认应用' );
               return;
            }

            $list[$key]['id'] = (int)$value;
            $list[$key]['is_default'] = $default; 
            $list[$key]['add_user_level'] = $this->level; 

        }

        $store = new app_store;
        $res = $store->saveAll($list);

        if ($res ) {
           $this->code = 1;
          $this->msg = __('succsess');
          return;
        }else{
            $this->code = -1;
            $this->msg = __('failed');
            return;
        }
    }
    /**
     * 平台添加应用
     */
    public function add()
    {
        //平台才能添加应用
        if ($this->uid != config('levels')['pt'] ) {
            $this->code = -1;
            $this->msg = __('you have no auth');
            return;
        }
        if ($this->request->isPost())
        {
            $params = $this->request->post("row/a");

            //1正常，2关闭
            if ($params['appstatus'] != 2) {
                $params['appstatus'] = 1;
            }
            //添加下级信息
            $data = ['name' => $params['appname'], 'logo'=>$params['applogo'], 'url'=>$params['appurl'], 'state'=>$params['appstatus'], 'is_default'=>$params['apptype'] ];
            $appId = Db::name('app_store')->insertGetId($data);
            if ($appId)
            {
                $data = ['uid' => $this->uid, 'app_id'=>$appId];
                $appId = Db::name('app_admin_access')->insertGetId($data);
                $this->code = 1;
            }else {
                $this->code = -1;
            }
            return;
        }
        return $this->view->fetch();
    }

    /*
    平台关闭应用
     */
    public function delApps($ids = "")
    {
        //平台才能关闭应用
        if ($this->uid != config('levels')['pt'] ) {
            $this->code = -1;
            $this->msg = __('you have no auth');
            return;
        }
        // if ($this->request->isPost())
        // {
            // $params = $this->request->post("row/a");
            $res = db('app_store')->where('id',(int)$ids)->setField('state',2);
            if ($res > 0) {
                  $this->code = 1;
                  $this->msg = __('succsess');
            }else{
                  $this->msg = __('failed');
                  $this->code = -1;
            }
            return;
        // }
    }
    /*
    下级收回应用
     */
    public function closeApps($params='')
    {
        $appid = $this->request->post("appid/a");
        $userid = $this->request->post("userid/a");

var_dump($userid);return;
        //  if ($this->request->isPost())
        // {
        $res = db('app_admin_access')->where('id',11)->delete();
        if ($res > 0) {
           $this->code = -1;
        }
        var_dump($res);
        // }
    }
    /*
    平台的下级管理分配应用
     */
    public function addApps($params=''){
        //接收到的应用id数组
        $appIds = $this->request->post("appid/a");
        //接收到的下级管理员ID
        $childId = $this->request->post("userid/a");
        if (count($appIds) == 0 || count($childId) ==0 ) {
           $this->code = -1;  
            $this->msg = '不能为空';
            return;
        }
        // if ($this->request->isPost())
        // {
            //添加下级信息
            $data = [];
            for ($i=0; $i < count($appIds); $i++) { 
                $appId = (int)$appIds[$i];
                for ($j=0; $j < count($childId); $j++) { 
                    $res = Db::name('app_admin_access')->where(['uid' => (int)$childId[$j], 'app_id' => $appId ])->find();
                    if ( !$res) {
                       $data[] = ['uid' => (int)$childId[$j], 'app_id' => $appId ];
                    }
                }
            }
            if ($data == []) {
               $this->code = -1;  
               $this->msg = '没有新分配的应用';
               return;
            }
            $res = Db::name('app_admin_access')->insertAll($data);
            // var_dump(Db::getfa);
            if (!$res || $res<1) {
                $this->code = -1;
                return;
            }
            $this->code = 1;  
            $this->msg = '分配应用成功';
            return;
        // }
        // return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = NULL)
    {
        //平台才能编辑应用
        if ($this->uid != config('levels')['pt'] ) {
            $this->code = -1;
            $this->msg = __('you have no auth');
            return;
        }
        if ($this->request->isPost())
        {
            $params = $this->request->post("row/a");
            //默认可用
            if (array_key_exists('state',$params)  && $params['state'] !=2 ) {
                $params['state'] = 1;
            }else{
                $params['state'] = 2;
            }
            $res = db('app_store')->where('id',(int)$params['id'])->update(['name' => $params['name'],'logo'=>$params['logo'],'url'=>$params['url'],'state'=>$params['state'],'is_default'=> $params['appType']]);
            if ($res > 0) {
                  $this->code = 1;
                  $this->msg = __('succsess');
            }else{
                  $this->msg = __('failed');
                  $this->code = -1;
            }
            return;
        }
        $row = db('app_store')->where('id', $ids)->find();
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    // public function del($ids = "")
    // {
    //     $this->code = -1;
    //     if ($ids)
    //     {
    //         $ids = explode(',', $ids);
    //         $grouplist = $this->auth->getGroups();
    //         $group_ids = array_map(function($group)
    //         {
    //             return $group['id'];
    //         }, $grouplist);
    //         // 移除掉当前管理员所在组别
    //         $ids = array_diff($ids, $group_ids);

    //         // 循环判断每一个组别是否可删除
    //         $grouplist = $this->model->where('id', 'in', $ids)->select();
    //         $groupaccessmodel = model('AuthGroupAccess');
    //         foreach ($grouplist as $k => $v)
    //         {
    //             // 当前组别下有管理员
    //             $groupone = $groupaccessmodel->get(['group_id' => $v['id']]);
    //             if ($groupone)
    //             {
    //                 $ids = array_diff($ids, [$v['id']]);
    //                 continue;
    //             }
    //             // 当前组别下有子组别
    //             $groupone = $this->model->get(['pid' => $v['id']]);
    //             if ($groupone)
    //             {
    //                 $ids = array_diff($ids, [$v['id']]);
    //                 continue;
    //             }
    //         }
    //         if (!$ids)
    //         {
    //             $this->msg = __('You can not delete group that contain child group and administrators');
    //             return;
    //         }
    //         $count = $this->model->where('id', 'in', $ids)->delete();
    //         if ($count)
    //         {
    //             AdminLog::record(__('Del'), $ids);
    //             $this->code = 1;
    //         }
    //     }
    //     return;
    // }

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
