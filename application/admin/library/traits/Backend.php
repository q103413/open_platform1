<?php

namespace app\admin\library\traits;

use app\admin\model\AdminLog;
use \Think\Db ;
// use app\admin\controller\appstore;

trait Backend
{

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax() )
        {
           $result = $this->testapp();
           return json($result);
        }
        $res = new \app\admin\controller\appstore\Applist();
        // 只显示非默认的应用
        $this->view->assign("allapps", $res->test('no_default'));
        $this->view->assign("list",  $this->testapp()['rows'] );

        return $this->view->fetch();
    }
    public function initLevel($value='')
    {
       $level = Db::query('SELECT a.id FROM '.config('database.prefix').'auth_group a JOIN '.config('database.prefix').'auth_group_access b WHERE b.uid = '.$this->uid.' AND a.id = b.group_id AND a.type = 2');
       // var_dump(Db::getLastSql());
       $this->level = $level[0]['id'];
    }
    public function testapp($value='')
    {
        $total =   Db::query('SELECT COUNT(*) as total FROM '.config('database.prefix').'admin a  JOIN ' . config('database.prefix') . 'auth_group_access b ON b.group_id = 6 AND a.id = b.uid ');
        $total = $total[0]['total'];
        // $list = $this->testapp();
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        
         // 只能查看直接下级    
        // $uid = (int)json_decode( $_SESSION['think']['admin'])->id;//userid
        $res = Db::query('SELECT group_id FROM '.config('database.prefix').'auth_group_access WHERE uid = ' . $this->uid);
        $groupId = $res[0]['group_id'];//组别id

        //搜索条件
        $search = $this->request->get("search", '');
        $where = '';
        if ($search) {
            $where = " AND a.username LIKE '%".$search."%' ";
        }
        // 包含op的是内部管理员操作
        if (strpos($_SERVER['PHP_SELF'], 'op') !== false) {
            //内部管理操作
            $gid = 'AND b.pid = '.$groupId;
            $type = 1;
        }else{
            //外部下级
             $gid = '';
             $type = 2;
        }

        $list = Db::query('SELECT a.* from '.config('database.prefix').'admin a JOIN '.config('database.prefix').'auth_group b JOIN '.config('database.prefix').'auth_group_access c ON a.id = c.uid '.$gid.' AND c.group_id = b.id AND c.group_id != '.config('levels')['seller'].' AND c.add_uid = '.$this->uid.' AND b.type = '. $type . $where . ' ORDER BY a.'."$sort DESC limit $offset,$limit ");
        $this->view->assign("list", $list);
        // var_dump( Db::getLastSql() );
        return $result = array("total" => $total, "rows" => $list);

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
     */
    public function multi($ids = "")
    {
        $this->code = -1;
        $ids = $ids ? $ids : $this->request->param("ids");
        if ($ids)
        {
            if ($this->request->has('params'))
            {
                parse_str($this->request->post("params"), $values);
                $values = array_intersect_key($values, array_flip(array('status')));
                if ($values)
                {
                    $count = $this->model->where('id', 'in', $ids)->update($values);
                    if ($count)
                    {
                        AdminLog::record(__('Multi'), $ids);
                        $this->code = 1;
                    }
                }
            }
            else
            {
                $this->code = 1;
            }
        }

        return;
    }

}
