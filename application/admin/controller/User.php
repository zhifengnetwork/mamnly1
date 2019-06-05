<?php


namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use app\common\model\AgentInfo;
use think\Loader;

class User extends Base
{

    public function changelevel(){
        
        if(IS_POST){
            $post = I('post.');

            $cunzai = M('users')->where(['user_id'=>$post['user_id'],'level'=>$post['level']])->find();
            if($cunzai){
                $this->error('无需修改');
            }

            $res = M('users')->where(['user_id'=>$post['user_id']])->update(['level'=>$post['level']]);
            if($res){
              
                //修改  tp_agent_info
                $is_cun = M('agent_info')->where(['uid'=>$post['user_id']])->find();
                $head_id = M('users')->where(['user_id'=>$post['user_id']])->value('first_leader');

                if($is_cun){
                    
                    M('agent_info')->where(['uid'=>$post['user_id']])->update(['head_id'=>$head_id,'level'=>$post['level'],'update_time'=>time()]);

                }else{

                    $model = new AgentInfo();
                    $model->uid = $post['user_id'];
                    $model->head_id = $head_id;
                    $model->level = $post['level'];
                    $model->create_time = time();
                    $model->update_time = time();
                    $model->save();

                }


                $this->success('修改成功');
            }else{
                $this->error('修改失败');
            }
            exit;
        }

        return $this->fetch();
    }

    public function index()
    {
        return $this->fetch();
    }

    public function yejiList()
    {
        return $this->fetch();
    }    

    /**
     * 会员列表
     */
    public function ajaxindex()
    {
        // 搜索条件
        $condition = array();
        $nickname = I('nickname');
        $user_id = input('user_id');
        $account = I('account');
        // dump($user_id);exit;
        $account ? $condition['email|mobile'] = ['like', "%$account%"] : false;
        $nickname ? $condition['nickname'] = ['like', "%$nickname%"] : false;
        $user_id ? $condition['user_id'] = ['like', "%$user_id%"] : 
            false;

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        // I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        // I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');

        $usersModel = new Users();
        $count = $usersModel->where($condition)->count();
        $Page = new AjaxPage($count, 10);

        if(trim($sort_order) == ''){
            $userList = $usersModel->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }else{
            $userList = $usersModel->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }

        $user_id_arr = get_arr_column($userList, 'user_id');
        if (!empty($user_id_arr)) {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(" . implode(',', $user_id_arr) . ")  group by first_leader");
            $first_leader = convert_arr_key($first_leader, 'first_leader');

            // $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(" . implode(',', $user_id_arr) . ")  group by second_leader");
            // $second_leader = convert_arr_key($second_leader, 'second_leader');

            // $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(" . implode(',', $user_id_arr) . ")  group by third_leader");
            // $third_leader = convert_arr_key($third_leader, 'third_leader');
        }
        $this->assign('first_leader', $first_leader);
        // $this->assign('second_leader', $second_leader);
        // $this->assign('third_leader', $third_leader);
        $show = $Page->show();

        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 业绩列表
     */
    public function ajaxyejiindex()
    {
        // 搜索条件
        $condition = array();
        $nickname = I('nickname');
        $user_id = input('user_id');
        $account = I('account');
        // dump($user_id);exit;
        $account ? $condition['email|mobile'] = ['like', "%$account%"] : false;
        $nickname ? $condition['nickname'] = ['like', "%$nickname%"] : false;
        $user_id ? $condition['user_id'] = ['like', "%$user_id%"] : 
            false;
        $condition['level'] = ['egt',4];
        $start_time = I('post.start_time/s','');
        $end_time = I('post.end_time/s','');
        if(!$start_time && $end_time)$this->error('请选择开始时间');
        if($start_time && !$end_time)$this->error('请选择截止时间');
        $start_time && ($start_time = strtotime($start_time));
        $end_time && ($end_time = strtotime($end_time));
        if($start_time > time())$this->error('开始时间不能大于当前时间');
        if($start_time > $end_time)$this->error('开始时间不能大于截止时间');        

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        // I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        // I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');

        $usersModel = new Users();
        $count = $usersModel->where($condition)->count();
        $Page = new AjaxPage($count, 10);

        if(trim($sort_order) == ''){
            $userList = $usersModel->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }else{
            $userList = $usersModel->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }

        $user_id_arr = get_arr_column($userList, 'user_id');
        if (!empty($user_id_arr)) {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(" . implode(',', $user_id_arr) . ")  group by first_leader");
            $first_leader = convert_arr_key($first_leader, 'first_leader');

            // $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(" . implode(',', $user_id_arr) . ")  group by second_leader");
            // $second_leader = convert_arr_key($second_leader, 'second_leader');

            // $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(" . implode(',', $user_id_arr) . ")  group by third_leader");
            // $third_leader = convert_arr_key($third_leader, 'third_leader');
        }
        $this->assign('first_leader', $first_leader);
        // $this->assign('second_leader', $second_leader);
        // $this->assign('third_leader', $third_leader);
        $show = $Page->show();

        
        if($start_time && $end_time){
            $where['addtime']    = ['between',[$start_time,$end_time]];
        }
        $Yeji = M('Yeji');
        foreach($userList as $k=>$v){
            $where['uid'] = $v['user_id'];
            $userList[$k]['yeji'] =  $Yeji->where($where)->sum('money');  
        }

        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }    

    /**
     * 会员详细信息查看
     */
     public function detail()
     {

         $uid = I('get.id');
         $user = D('users')->where(array('user_id' => $uid))->find();
         if (!$user)
             exit($this->error('会员不存在'));
         if (IS_POST) {
             //  会员信息编辑
             $password = I('post.password');
             $password2 = I('post.password2');
             if ($password != '' && $password != $password2) {
                 exit($this->error('两次输入密码不同'));
             }
             if ($password == '' && $password2 == '') {
                 unset($_POST['password']);
             } else {
                 $_POST['password'] = encrypt($_POST['password']);
             }
 
             if (!empty($_POST['email'])) {
                 $email = trim($_POST['email']);
                 $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                 $c && exit($this->error('邮箱不得和已有用户重复'));
             }
             
             if (!empty($_POST['first_leader']) && $_POST['first_leader']>0) {
                 $first_leader = trim($_POST['first_leader']);
                 $c = M('users')->where("user_id=$first_leader")->find();
                 //print_r(M('users')->getLastSql());exit;
                 if(empty($c))
                 {
                    exit($this->error('绑定上级ID不存在！'));
                 }
                // $c && exit($this->error('邮箱不得和已有用户重复'));
             }
 
             if (!empty($_POST['mobile'])) {
                 $mobile = trim($_POST['mobile']);
                 $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                 $c && exit($this->error('手机号不得和已有用户重复'));
             }
             if(!empty($_POST['level'])){
                 $userLevel = D('user_level')->where('level=' . $_POST['level'])->value('level');
                 $_POST['agent_user'] = $userLevel;
                 $_POST['level'] = $userLevel;
             }
             // dump($_POST);die;
             $agent = M('agent_info')->where(['uid'=>$uid])->find();
             if ($agent) {
                 $data = array('level'=>$userLevel);
                 M('agent_info')->where(['uid'=>$uid])->save($data);
             }else{
                 $this->agent_add($user['user_id'],$user['first_leader'],$userLevel);
                 $_POST['is_agent'] = 1;
             }
             $row = M('users')->where(array('user_id' => $uid))->save($_POST);
             if ($row)
 
                 exit($this->success('修改成功'));
             exit($this->error('未作内容修改或修改失败'));
         }
 
         $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
       
         $this->assign('user', $user);
         return $this->fetch();
     }
     
    /**
     * ajax查找会员详细信息
     */
     public function details()
     {

         $uid = I('get.id');
         $user = D('users')->where(array('user_id' => $uid))->find();
         if (!$user)
            $this->ajaxReturn(['status'=>0,'msg'=>'会员不存在','result'=>'']);
 
         $user['level_name'] = M('user_level')->where("level = {$user['level']}")->value('level_name');
 
        $this->ajaxReturn(['status'=>1,'msg'=>'查询成功','result'=>$user]);
     }

     private function agent_add($user_id,$head_id,$level)
     {
         $data = array(
             'uid'=>$user_id,
             'head_id'=>$head_id,
             'level'=>$level,
             'create_time'=>time(),
             'update_time'=>time(),
             'note'=>"后台增加等级"
         );
         M('agent_info')->add($data);
     }

    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if ($res['status'] == 1) {
                $this->success('添加成功', U('User/index'));
                exit;
            } else {
                $this->error('添加失败,' . $res['msg'], U('User/index'));
            }
        }
        return $this->fetch();
    }

    public function export_user()
    {
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '</tr>';
        $user_ids = I('user_ids');
        if ($user_ids) {
            $condition['user_id'] = ['in', $user_ids];
        } else {
            $mobile = I('mobile');
            $email = I('email');
            $mobile ? $condition['mobile'] = $mobile : false;
            $email ? $condition['email'] = $email : false;
        };
        $count = DB::name('users')->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList = M('users')->where($condition)->order('user_id')->limit($start,5000)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['level'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['email'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id' => $uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete()
    {
        $uid = I('get.id');

        //先删除ouath_users表的关联数据
        M('OuathUsers')->where(array('user_id' => $uid))->delete();
        $row = M('users')->where(array('user_id' => $uid))->delete();
        if ($row) {
            $this->success('成功删除会员');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 删除会员
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(array('user_id' => $uid))->delete();
            if ($row !== false) {
                //把关联的第三方账号删除
                M('OauthUsers')->where(array('user_id' => $uid))->delete();
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(array('user_id' => $user_id))->count();
        $page = new Page($count);
        $lists = M('account_log')->where(array('user_id' => $user_id))->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $desc = I('post.desc');
            if (!$desc)
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money = $m_op_type ? $user_money : 0 - $user_money;
            if (($user['user_money'] + $user_money) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
            }
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            if (($pay_points + $user['pay_points']) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户剩余积分不足！！']);
            }
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if ($revision_frozen_money != 0) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if ($f_op_type == 1 && $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
                }
                if ($f_op_type == 0 && $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }
            if (accountLog($user_id, $user_money, $pay_points, $desc, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/account_log", array('id' => $user_id))]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => "操作失败"]);
            }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function recharge()
    {
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($nickname) {
            $map['nickname'] = array('like', "%$nickname%");
            $this->assign('nickname', $nickname);
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function level($id="")
    {
		$user_level = M('user_level')->select();
		$this->assign('level',$user_level);
		
		
		// exit;
		/* $goods = M('goods')->select();
		$this->assign('goods',$goods);
		
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level = I('get.level');
        if ($level) {
            $level_info = D('user_level')->where('level=' . $level)->find();
            $this->assign('info', $level_info);
        } */
		if($id>0){
			$res = M('user_level')->where('id',$id)->select();
			$this->assign('info', $res['0']);
		}
        return $this->fetch();
    }

    public function levelList()
    {
		$benefits = Db::query("select * from tp_config where name='benefits' and inc_type='user_level'");
		$this->assign('benefits',$benefits['0']);
        $Ad = M('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }
	
	public function benefits($id=""){//平级管理津贴
		$info = M('config')->where('id',$id)->select();
		$this->assign('info',$info[0]);
		if($_POST){
			$id = I('id');
			$value = I('value');
			$res = M('config')->update(['value'=>$value,'id'=>$id]);
			if($res){
				$this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
			}else{
				$this->ajaxReturn(['status' => 0, 'msg' => '参数失败']);
			}
		}
		return $this->fetch();
	}

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        /* $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level=' . $data['level'])->save($data);
                if ($r !== false) {
                    $discount = $data['discount'] / 100;
                    D('users')->where(['level' => $data['level']])->save(['discount' => $discount]);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level=' . $data['level'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return); */
		if($_POST){
			$id = I('id');
			
			$level = I('level');
			$level_name = I('level_name');
			$type = I('type');
			$con_name = I('con_name');
			$rebate_id = I('rebate');
			$rebate = I('rebate');
			$rate = I('rate');
			$reward_id = I('reward_id');
			$reward = I('reward');
			$get_num = I('get_num');
            $receive_num = I('receive_num');
			$y_reward = I('y_reward');
            $s_reward = I('s_reward');
             $k_reward = I('k_reward');
             $h_reward = I('h_reward');
             $direct_rate = I('direct_rate');
             $pin_reward = I('pin_reward');
             $pin_reward2 = I('pin_reward2');
             $pin_reward3 = I('pin_reward3');
             $pin_reward4 = I('pin_reward4');
              $lead_reward = I('lead_reward');
            $jintie = I('jintie');
			if($level==""){
				$this->ajaxReturn(['status' => 0, 'msg' => '等级不可为空']);
			}
			$verify = Db::query("select * from tp_user_level");
			foreach($verify as $k =>$v){
				if($v['id']!=$id&&$v['level']==$level){
					$this->ajaxReturn(['status' => 0, 'msg' => '等级:已存在相同等级']);
				}
			}
			if($level_name=="")$this->ajaxReturn(['status' => 0, 'msg' => '等级名称不可为空']);
			if($type==0&&$con_name!="")$this->ajaxReturn(['status' => 0, 'msg' => '请选择等级所需条件']);
			if($rebate_id==0&&$rebate!="")$this->ajaxReturn(['status' => 0, 'msg' => '请选择直推返利等级']);
			if($reward_id==0&&$reward!="")$this->ajaxReturn(['status' => 0, 'msg' => '请选择直推奖励等级']);
			$data = array(
				'level'=>$level,
				'level_name'=>$level_name,
				'type'=>$type,
				'con_name'=>$con_name,
				'rebate'=>$rebate_id,
				'rate'=>$rate,
				//'rebate'=>$rebate,
				'reward_id'=>$reward_id,
				'reward'=>$reward,
                'get_num'=>$get_num,
				'receive_num'=>$receive_num,
				'describe'=>$describe,
                'y_reward'=>$y_reward,
                's_reward'=>$s_reward,
                'k_reward'=>$k_reward,
                'h_reward'=>$h_reward,
                'jintie'=>$jintie,
                'pin_reward'=>$pin_reward,
                'pin_reward2'=>$pin_reward2,
                'pin_reward3'=>$pin_reward3,
                'pin_reward4'=>$pin_reward4,
                'direct_rate'=>$direct_rate,
                'lead_reward'=>$lead_reward,
			);
			if($id>0){
				$res = M('user_level')->where('id',$id)->data($data)->save();
			}else{
				$data['create_time'] = time();
				$res = M('user_level')->insert($data);
			}
          //echo Db::table('user_level')->getLastSql();
			if($res){
				$this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
			}else{
				$this->ajaxReturn(['status' => 0, 'msg' => '参数失败']);
			}
		}
    }
	
	public function leveldel(){
		if($_POST){
			$id = I('id');
			$res = M('user_level')->where('id',$id)->delete();
			if($res){
				$this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
			}else{
				$this->ajaxReturn(['status' => 0, 'msg' => '参数失败']);
			}
		}
	}

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if ($search_key == '') $this->ajaxReturn(['status' => -1, 'msg' => '请按要求输入！！']);
        $list = M('users')->where(['nickname' => ['like', "%$search_key%"]])->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '未查询到相应数据！！']);
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送系统通知消息
     * @author yhj
     * @time  2018/07/10
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $message_content = I('post.text', '');//内容
        $message_title = I('post.title', '');//标题
        $message_type = I('post.type', 0);//个体or全体
        $users = I('post.user/a');//个体id
        $message_val = ['name' => ''];
        $send_data = array(
            'message_title' => $message_title,
            'message_content' => $message_content,
            'message_type' => $message_type,
            'users' => $users,
            'type' => 0, //0系统消息
            'message_val' => $message_val,
            'category' => 0,
            'mmt_code' => 'message_notice'
        );

        $messageFactory = new MessageFactory();
        $messageLogic = $messageFactory->makeModule($send_data);
        $messageLogic->sendMessage();

        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $status = I('status', 1);
        $realname = I('realname');
        $bank_card = I('bank_card');
        $where['status'] = $status;
        $realname && $where['realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['bank_card'] = array('like', '%' . $bank_card . '%');

        $create_time = urldecode(I('create_time'));
        // echo urldecode($create_time);
        // echo $create_time;exit;
        // $create_time = str_replace('+', '', $create_time);

        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        if ($status == 2) {
            $time_name = 'pay_time';
            $export_time_name = '转账时间';
            $export_status = '已转账';
        } else {
            $time_name = 'check_time';
            $export_time_name = '审核时间';
            $export_status = '待转账';
        }
        $where[$time_name] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));
        $withdrawalsModel = new Withdrawals();
        $count = $withdrawalsModel->where($where)->count();
        $Page = new page($count, C('PAGESIZE'));
        $list = $withdrawalsModel->where($where)->limit($Page->firstRow, $Page->listRows)->order("id desc")->select();
        if (I('export') == 1) {
            # code...导出记录
            $selected = I('selected');
            if (!empty($selected)) {
                $selected_arr = explode(',', $selected);
                $where['id'] = array('in', $selected_arr);
            }
            $list = $withdrawalsModel->where($where)->order("id desc")->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户昵称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">银行机构名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户号码</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户开户名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">' . $export_time_name . '</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';
            $strTable .= '</tr>';
            if (is_array($list)) {
                foreach ($list as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['users']['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $export_status . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val[$time_name]) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }

        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('status', $status);
        $this->assign('Page', $Page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        $this->assign('withdraw_status', C('WITHDRAW_STATUS'));
        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $id = I('selected/a');
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = urldecode(I('create_time'));
        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.create_time'] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));

        $status = empty($status) ? I('status') : $status;
        if ($status !== '') {
            $where['w.status'] = $status;
        } else {
            $where['w.status'] = ['lt', 2];
        }
        if ($id) {
            $where['w.id'] = ['in', $id];
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        $export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //$this->assign('create_time',$create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $id = I('del_id/d');
        $res = Db::name("withdrawals")->where(['id' => $id])->delete();
        if ($res !== false) {
            $return_arr = ['status' => 1, 'msg' => '操作成功', 'data' => '',];
        } else {
            $return_arr = ['status' => -1, 'msg' => '删除失败', 'data' => '',];
        }
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $withdrawals = Db::name("withdrawals")->find($id);
        $user = M('users')->where(['user_id' => $withdrawals['user_id']])->find();
        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];
        $status = $withdrawals['status'];
        $withdrawals['status_code'] = C('WITHDRAW_STATUS')["$status"];
        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update()
    {
        // $id_arr = I('id/a');
        // $data['status'] = $status = I('status');
        // $data['remark'] = I('remark');
        // if ($status == 1) $data['check_time'] = time();
        // if ($status != 1) $data['refuse_time'] = time();
        // $ids = implode(',', $id_arr);
        // $falg = M('withdrawals')->where(['id'=>$ids])->find();
        // $user_find = M('users')->where(['user_id'=>$falg['user_id']])->find();
        // if($user_find['user_money'] < $falg['money'])
        // {
        //     $this->ajaxReturn(array('status' => 0, 'msg' => "当前用户余额不足"), 'JSON');
        // }
        // $user_arr = array(
        //     'user_money' => $user_find['user_money'] - $falg['money']
        // );
        // $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
        // if ($r !== false) {
        //     Db::name('users')->whereIn('user_id', $falg['user_id'])->update($user_arr);
        //     $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
        // } else {
        //     $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        // }

        $id_arr = I('id/a');
        $data['status'] = $status = I('status');
        $data['remark'] = I('remark');
        $ids = implode(',', $id_arr);
        $falg = M('withdrawals')->where(['id'=>$ids])->find();

        if ($status == 1){
            $data['check_time']  = time();

            if($falg['id'] <= 217){
                //未改流程前的提现流程
                $user_find = M('users')->where(['user_id'=>$falg['user_id']])->find();
                if($user_find['user_money'] < $falg['money'])
                {
                    $this->ajaxReturn(array('status' => 0, 'msg' => "当前用户余额不足"), 'JSON');
                    exit;
                }
                $user_arr = array(
                    'user_money' => $user_find['user_money'] - $falg['money']
                );
                $result = Db::name('users')->whereIn('user_id', $falg['user_id'])->update($user_arr);
                if($result){
                    $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
                }else{
                    $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
                    exit;
                }
                if ($r !== false) { 
                    $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
                } else {
                    $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
                }
            }else{
                //修改流程后的提现流程
                $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
                if ($r !== false) { 
                    $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
                } else {
                    $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
                }
            }
        } else{
            $data['refuse_time'] = time();

            if($falg['id'] > 217){
                //修改流程后,拒绝提现需退还金额
                //审核未通过退还金额
                accountLog($falg['user_id'], $falg['money'] , 0, '提现未通过退款',  0, 0, '');
            }
            $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
            if ($r !== false) { 
                $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
            }
        }   
    }


    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) $this->error('请至少选择一条记录');
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(array('id' => $id))->select();
        }


        $messageFactory = new \app\common\logic\MessageFactory();
        $messageLogic = $messageFactory->makeModule(['category' => 0]);

        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(array('user_id' => $val['user_id']))->find();
            //$oauthUsers = M("OauthUsers")->where(['user_id'=>$user['user_id'] , 'oauth_child'=>'mp'])->find();
            $oauthUsers = M("OauthUsers")->where(['user_id' => $user['user_id'], 'oauth' => 'weixin'])->find();
            //获取用户绑定openId
            $user['openid'] = $oauthUsers['openid'];
            if ($user['user_money'] < $val['money']) {
                $data = array('status' => -2, 'remark' => '账户余额不足');
                M('withdrawals')->where(array('id' => $val['id']))->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = array('type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']);
                if ($atype == 'online') {
                    header("Content-type: text/html; charset=utf-8");
exit("请联系DC环球直供网络客服购买高级版支持此功能");
                } else {
                    accountLog($val['user_id'], ($val['money'] * -1), 0, "管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id' => $val['id']))->save(array('status' => 2, 'pay_time' => time()));
                    expenseLog($rdata);//支出记录日志
                    // 提现通知
                    $messageLogic->withdrawalsNotice($val['id'], $val['user_id'], $val['money'] - $val['taxfee']);

                }
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!", U('remittance'), 3);
    }



    /**
     * 会员标签列表
     */
    public function labels()
    {
        $p = input('p/d');
        $Label = new UserLabel();
        $label_list = $Label->order('label_order')->page($p, 10)->select();
        $this->assign('label_list', $label_list);
        $Page = new Page($Label->count(), 10);
        $this->assign('page', $Page);
        return $this->fetch();
    }

    /**
     * 添加、编辑页面
     */
    public function labelEdit()
    {
        $label_id = input('id/d');
        if ($label_id) {
            $Label = new UserLabel();
            $label = $Label->where('id', $label_id)->find();
            $this->assign('label', $label);
        }
        return $this->fetch();
    }

    /**
     * 会员标签添加编辑删除
     */
    public function label()
    {
        $label_info = input();
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        $userLabelValidate = Loader::validate('UserLabel');
        $UserLabel = new UserLabel();
        if (request()->isPost()) {
            if ($label_info['label_id']) {
                if (!$userLabelValidate->scene('edit')->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLabelValidate->getError()];
                }else {
                    $UserLabel->where('id', $label_info['label_id'])->save($label_info);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => ''];
                }
            }else{
                if (!$userLabelValidate->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLabelValidate->getError()];
                } else {
                    $UserLabel->insert($label_info);
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
                }
            }
        }
        if (request()->isDelete()) {
            $UserLabel->where('id', $label_info['label_id'])->delete();
            $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }
	public function teamRank(){
        $list = Db::query("SELECT *,sum(total_amount) AS num FROM tp_users WHERE first_leader!=0 GROUP BY first_leader ORDER BY num desc");
        
        $this->assign('list',$list);
		return $this->fetch();
	}
	public function bonusSystem(){//分红列表
		$settle = M('config')->where(['name'=>'settlement','inc_type'=>'settle'])->select();
		$this->assign('settle',$settle[0]);
		
        $list = Db::name('share')->order('grade','desc')->select();
        $UserLevel = M('User_level');
        foreach($list as $k=>$v){
            $list[$k]['level_name'] = $v['level'] ? $UserLevel->where(['level'=>$v['level']])->value('level_name') : '';
        }
		
		$this->assign('list',$list);
		return $this->fetch();
	}
	public function bonusSystem_add($rate_id=""){//分红列表操作
		if($rate_id>0){
			$info = Db::name('share')->where('rate_id',$rate_id)->select();
			$this->assign('info',$info['0']);
        }
        $levellist = M('User_level')->field('level,level_name')->select();
        $this->assign('levellist',$levellist);
		if($_POST){
			$rate_id = I('rate_id');
			
			$grade = I('grade');
			$lower = I('lower');
			$upper = I('upper');
            $rate = I('rate');
            $level = I('level');
			$describe = I('describe');
			$zz = preg_match("/^\d*$/",$grade);
			$zz1 = preg_match("/^\d*$/",$lower);
			$zz2 = preg_match("/^\d*$/",$upper);
			$zz3 = preg_match("/^\d*$/",$rate);
			if($grade==""||$zz==false){
				$this->ajaxReturn([status=>'0',msg=>'请填写等级(数字格式)！',name=>'grade']);
			}
			if($lower==""&&$upper==""){
				$this->ajaxReturn([status=>'0',msg=>'上限和下限不能都为空且数字格式',name=>'lower']);
            }
            if(!$level){
				$this->ajaxReturn([status=>'0',msg=>'请选择分红等级！',name=>'level']);
			}
			if($rate==''){
				$rate=0;
			}elseif($zz3==false){
				$this->ajaxReturn([status=>'0',msg=>'分红比例为数字格式',name=>'lower']);
			}
			$data = array(
				'grade' =>$grade,
				'lower' =>$lower,
				'upper' =>$upper,
				'rate' =>$rate,
                'describe' =>$describe,
                'level' => $level
			);  
			if($rate_id>0){
				$data['update_time'] = time();
                $res = Db::name('share')->where('rate_id',$rate_id)->update($data);
			}else{
				$data['create_time'] = time();
				$res = Db::name('share')->insert($data);
			}
			if($res){
				$this->ajaxReturn([status=>'1',msg=>'操作成功']);
			}else{
				$this->ajaxReturn([status=>'0',msg=>'参数失败']);
			}
			
		}
		return $this->fetch();
	}
	public function dels(){
		$rate_id = I('rate_id');
		if($rate_id>0){
			$del = Db::name('share')->where('rate_id',$rate_id)->delete();
			if($del){
				$this->ajaxReturn([status=>1,msg=>'操作成功']);
			}else{
				$this->ajaxReturn([status=>0,msg=>'参数失败']);
			}
		}
	}
	
	public function settle($id=""){
		$info = M('config')->where('id',$id)->select();
		$this->assign('info',$info['0']);
		if($_POST){
			$id=I('id');
			$value = I('value');
			$zz = preg_match("/^\d*$/",$value);
			if($zz==false){
				$this->ajaxReturn([status=>0,msg=>'请输入数字格式！']);
			}else{
				$res = Db::name('config')->update(['value'=>$value,'id'=>$id]);
				$this->ajaxReturn([status=>1,msg=>'操作成功']);
			}
		}
		return $this->fetch();
	}
}