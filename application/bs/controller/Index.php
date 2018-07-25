<?php
namespace app\bs\controller;

use app\common\model\User;
use think\Controller;
use think\Request;
use think\Validate;
use app\common\model\Menu;

class Index extends Controller{
    protected $useModel = "User";
    protected $choose = "default";

    /*
     * 不存在的路由的处理
     * */
    public function _empty(){
        cookie("currentUrl",null);
        return view("error/404");
    }

    /*
     * 权限页面错误跳转
     * */
    public function abort($type = 404){
        cookie("currentUrl",null);
        return $this->fetch('error/'.$type);
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
            $user = $model->where(['name'=>$request->name])->field('lid,name,passwd')->find();
            if(!empty($user)){
                if(!empty($user['passwd']) && $user['passwd'] == $request->passwd){
                    session('login_id',$user['lid']);
                    if(!empty($request->remember)){
                        cookie("login_id",$user['lid'],7*86400);
                    }
                    if(!empty(cookie("currentUrl"))){
                        return redirect(cookie("currentUrl"));
                    }else{
                        return redirect($this->loginJump());
                    }
                }else{
                    $this->error('账号或密码错误');
                }
            }else{
                $this->error('账号或密码错误');
            }
        }
        $menu = $this->getMenu($this->useModel,$this->choose);
        $this->assign('menus',$menu->{$this->choose});
        return $this->fetch('login');
    }

    /*
     * 退出
     * */
    public function logout(){
        session(null);
        cookie(null);
        return redirect('bs/index/index');
    }

    /*
     * 注册
     * */
    public function register(Request $request){
        if($request->isPost()){
            if(!isset($request->accept) || $request->accept != 1){
                $this->error("还没接受注册协议");
            }
            $result = $this->checkData($request,$this->useModel,$this->choose);
            if($result['status'] != 200){
                if(is_array($result['data'])){
                    $msg = implode(',',$result['data']);
                }else{
                    $msg = $result['data'];
                }
                $this->error($msg);
            }
            $modelname = "\\app\\common\\model\\".$this->useModel;
            $model = new $modelname;
            $model->save($result['data']);
            $this->success('新加成功','bs/index/index');
        }
        $menu = $this->getMenu($this->useModel,$this->choose);
        $this->assign('menus',$menu->{$this->choose});
        return $this->fetch('register');
    }

    public function getMenu($modelname,$choose="default"){
        $useModel = "\\app\\common\\model\\".$modelname;
        $model = new $useModel;
        $menus = $model->getCF($choose);
        foreach($menus[$choose] as $key=>$menu){
            if(empty($menu['name'])){
                $menus[$choose][$key]['name'] = $key;
            }
        }
        $result = array2obj($menus);
        return $result;
    }

    public function checkData(Request $request,$useModel,$choose="default"){
        $rule = [];
        $tipMsg = [];
        $data = [];
        $menus = $this->getMenu($useModel,$choose);
        foreach ($menus->{$choose} as $menu){
            if(isset($request->{$menu->name})){
                //查找验证规则
                if(!empty($menu->validate)){
                    $rule[$menu->name] = $menu->validate->rule;
                    $tips = explode('|',$menu->validate->rule);
                    foreach ($tips as $tip){
                        if(strrpos($tip,':')){
                            $tip = substr($tip,0,strrpos($tip,':'));
                        }
                        $tipMsg[$menu->name.".".$tip] = $menu->validate->{$menu->name.".".$tip};
                    }
                }
                if(is_array($request->{$menu->name})){
                    $request->{$menu->name} = implode(",",$request->{$menu->name});
                }
                $data[$menu->name] = filterChar($request->{$menu->name});
            }
        }
        $result = ['status'=>200,'data'=>$data];
        if(!empty($rule)){
            //$validate = Validate::make($rule,$tipMsg)->batch();//批量验证
            $validate = Validate::make($rule,$tipMsg);//单个验证
            if(!$validate->check($data)){
                $result = ['status' => 301,'data'=>$validate->getError()];
            }
        }
        return $result;
    }

    /*
     * 登录成功页面跳转
     * */
    public function loginJump(){
        $url = 'bs/index/abort';
        if(empty(cookie('currentUrl'))){
            $menus = Menu::getUserMenu("route");
            if(!empty($menus) && $menus->count() > 0){
                $url = $this->chooseUrl($menus);
            }
        }else{
            $url = cookie('currentUrl');
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
}
