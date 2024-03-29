<?php


namespace app\common\logic;

use think\Db;
use think\Page;
use think\Session;
use think\Cache;

class DistributLogic
{

    /**
     * 用户充值记录
     * $author lxl 2017-4-26
     * @param $user_id 用户ID
     * @param int $pay_status 充值状态0:待支付 1:充值成功 2:交易关闭
     *  @param $table 指定查询那张表
     * @return mixed
     */
    public function get_recharge_log($user_id,$pay_status=0,$table='recharge'){
        $recharge_log_where = ['user_id'=>$user_id];
        if($pay_status){
            $pay_status['status']=$pay_status;
        }
        if($table='agent_performance_log'){
            $count = M('agent_performance_log')->where($recharge_log_where)
            ->group('order_id')
            ->count();
            $Page = new Page($count, 15);
            $recharge_log = M('agent_performance_log')->field('sum(money) as money,performance_id,user_id,create_time,note,order_id')->where($recharge_log_where)
                ->group('order_id')
                ->order('create_time desc')
                //->group('order_id')
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->select(); 
        }else{
            $count = M('recharge')->where($recharge_log_where)->count();
            $Page = new Page($count, 15);
            $recharge_log = M('recharge')->where($recharge_log_where)
                ->order('order_id desc')
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->select(); 
        }

        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$recharge_log,
            'show'      =>$Page->show()
        ];
        return $return;
    }    
     /*
     * 获取佣金明细
     */
    public function get_commision_log($user_id,$pay_status=0){
        //$recharge_log_where ="user_id='".$user_id."' and states =101 or states=102";
        $recharge_log_where ="user_id='".$user_id."' and log_type>=1";
        if($pay_status){
            $pay_status['status']=$pay_status;
        }
        $count = M('account_log')->where($recharge_log_where)->count();
        $Page = new Page($count, 15);
        $recharge_log = M('account_log')->where($recharge_log_where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('log_id desc')
            ->select(); 
            // dump($recharge_log);
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$recharge_log,
            'show'      =>$Page->show()
        ];
        return $return;
    }

    /*
     * 获取团队列表
     */
    public function get_team_list($user_id){
        global $result;
        $id_list = M('users')->field('user_id,first_leader')->select();
        // $series = $this->get_series($user_id,$id_list);
        $this->get_next($user_id);  //获取直属信息

        $page = new Page(count($result),15);
        
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$result,
            'show'      =>$page
        ];
        
        Cache::set('team_list', $return, 3600);

        return $return;
    }

    // public function get_series($user_id,$id_list)
    // {
    //     global $result;

    //     if (array_key_exists($user_id, $id_list)) {
    //         $result[] = $id_list[$user_id]
    //     }
    // }

    //获取直属信息
    public function get_next($user_id)
    {
        global $result;
        // 直属
        $next = M('users')->where('first_leader',$user_id)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->field('user_id,nickname,mobile,first_leader')
            ->select();

        if($next){
            $result[] = $next;
            $k += 1;
            
            foreach ($next as $key => $value) {
                if ($value) {
                    $this->get_next($value['user_id']);
                }
            }
        }
    }

    public function auto_confirm(){
        return null;
    }
}