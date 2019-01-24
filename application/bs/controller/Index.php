<?php
namespace app\bs\controller;

use app\common\model\User;
use think\Controller;
use think\Request;
use app\common\model\Menu;

class Index extends Controller{
    /*
     * 不存在的路由的处理
     * */
    public function _empty(){
        cookie("ms_currentUrl",null);
        return view("error/404");
    }

    /*
     * 权限页面错误跳转
     * */
    public function abort($type = 404){
        cookie("ms_currentUrl",null);
        return $this->fetch('/'.$type);
    }

    /*
     * 登录
     * */
    public function index(Request $request){
        $model = new User;
        if($model->checkLogin()){
            return redirect($this->loginJump());
        }
        if($request->isPost()){
            if(empty($request->name) || empty($request->passwd)){
                $this->error("信息填写不完整");
            }
            $request->name = filterChar($request->name);
            $user = $model->where(['name'=>$request->name])->field('id,name,passwd')->find();
            if(!empty($user)){
                if($user->passwd['change'] != $request->passwd){
                    $this->error('密码错误!');
                }
                session('login_id',$user->id);
                session('login_pwd',$user->passwd['original']);
                if(!empty($request->remember)){
                    cookie("ms_login_id",$user->id,7*86400);
                    cookie("ms_login_pwd",$user->passwd['original'],7*86400);
                }
                if(!empty(cookie("ms_currentUrl"))){
                    return redirect(cookie("ms_currentUrl"));
                }else{
                    return redirect($this->loginJump());
                }
            }else{
                $this->error('用户不存在!');
            }
        }
        return $this->fetch('login');
    }

    /*
     * 退出
     * */
    public function logout(){
        session(null);
        cookie(null,"ms_");
        return redirect('bs/index/index');
    }

    /*
     * 登录成功页面跳转
     * */
    public function loginJump(){
        $url = 'bs/index/abort';
        if(empty(cookie('ms_currentUrl'))){
            $menus = Menu::getUserMenu("route");
            if(!empty($menus) && $menus->count() > 0){
                $url = $this->chooseUrl($menus);
            }
        }else{
            $url = cookie('ms_currentUrl');
        }
        return $url;
    }

    public function chooseUrl($route){
        if(!empty($route) && $route->count() > 0){
            foreach($route as $r){
                if(!empty($r->route)){
                    return $r->route;
                }
            }
        }
        return '';
    }

    public function setPageRow(Request $request){
        if($request->isAjax()){
            $num = $request->param('row');
            $num = intval($num);
            if(!in_array($num,Config('setting.page'))){
                $num = 10;
            }
            cookie('pr_pagerow',$num);
        }
    }
}
