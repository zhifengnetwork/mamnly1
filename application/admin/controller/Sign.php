<?php


namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\common\model\Config;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use think\Loader;

class Sign extends Base
{

    /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList()
    {

        $user_id = I('user_id/d');
        $create_time = urldecode(I('create_time'));
        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.sign_day'] = array(array('gt', $create_time3[0]), array('lt', $create_time3[1]));


        $user_id && $where['u.user_id'] = $user_id;

        $count = Db::name('sign_log')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('sign_log')->alias('w')->field('w.*,u.nickname,u.mobile')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($list as $key => $value) {
            $list[$key]['continue_sign'] = continue_sign($value['user_id']);
        }
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
       return $this->fetch();
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {

        // $list = M('sign_log')->group("user_id")->select();

        $list =  Db::query("select *,count(sign_day) as day_num from tp_sign_log as a,tp_users  as b where a.user_id = b.user_id group by a.user_id order by a.sign_day desc");
        $this->assign('list',$list);

        return $this->fetch();
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule()
    {
        // 等級設置
        $res = M('user_level')->order('level desc')->select();
        // dump($res );exit;
        $this->assign('res',$res);
    	
        // return $this->fetch();


        if(IS_POST){
            $post = I('post.');

            // ["sign_on_off"] => string(1) "1"
            // ["sign_integral"] => string(2) "10"
            // ["sign_signcount"] => string(2) "10"
            // ["sign_award"] => string(2) "10"

            
            $model = new Config();
            $model->where(['name'=>'sign_rule'])->save(['value'=>$post['sign_rule']]);
            $model->where(['name'=>'sign_on_off'])->save(['value'=>$post['sign_on_off']]);
            $model->where(['name'=>'sign_signcount'])->save(['value'=>$post['sign_signcount']]);
            $model->where(['name'=>'sign_agent_days'])->save(['value'=>$post['sign_agent_days']]);
            $model->where(['name'=>'sign_require_level'])->save(['value'=>$post['level_name']]);
            $model->where(['name'=>'sign_distribut_days'])->save(['value'=>$post['sign_distribut_days']]);

            

            $this->success('保存成功');
        }

        $model = new Config();

        $config['sign_on_off'] = $model->where(['name'=>'sign_on_off'])->value('value');
        
        $config['sign_signcount'] = $model->where(['name'=>'sign_signcount'])->value('value');
        $config['sign_rule'] = $model->where(['name'=>'sign_rule'])->value('value');
        $config['sign_agent_days'] = $model->where(['name'=>'sign_agent_days'])->value('value');
        $config['sign_require_level'] = $model->where(['name'=>'sign_require_level'])->value('value');
        $config['sign_distribut_days'] = $model->where(['name'=>'sign_distribut_days'])->value('value');

        $this->assign('config',$config);

        return $this->fetch();
    }
   
}