<?php

namespace app\index\controller;

use \Think\Db ;

use app\common\controller\Frontend;

class Seller extends Frontend
{

    protected $layout = 'bootstrap';

    public function _initialize()
    {
        parent::_initialize();
    }

    public function index()
    {
        return $this->view->fetch();
    }
    public function seller($uid='')
    {
        // $row = $this->model->get(['id' => $ids]);
        // $res = new \app\admin\controller\appstore\Applist();
        // // var_dump($this->testapp()['rows']);
        // $this->view->assign("res", $res->test());
         // $params = $this->request->post("row/a");
         if (!$uid) {
            $uid = 1;   
         }
       // if ( $params['userid'] && is_int($params['userid']) ) {
       //    $uid = $params['userid'];
       // }else{
       //    $uid = (int)json_decode( $_SESSION['think']['admin'])->id;
       // }
       $applist = Db::query('SELECT a.* FROM  '.config('database.prefix').'app_store a JOIN '.config('database.prefix').'app_admin_access b ON b.uid = '.(int)$uid.' AND b.app_id = a.id and a.state = 1');
       $this->view->assign("res", $applist);

        // var_dump($applist);
    	 return $this->view->fetch();
    }

}
