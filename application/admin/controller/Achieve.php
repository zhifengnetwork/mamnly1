<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use app\common\logic\ModuleLogic;
use think\db;
use think\Cache;
use think\Page;

class Achieve extends Base
{
	public function config()
    {
        
        $config = tpCache('achieve');
        $config['averanking1'] = unserialize($config['averanking1']);
        $config['averanking2'] = unserialize($config['averanking2']);
        $sale_money = $this->count_todayorder();//统计当天订单金额
        //print_R($config);exit;
        $this->assign('config',$config);//当前配置项
        $this->assign('sale_money',$sale_money);//当前配置项
        return $this->fetch();
    }
    public function config_set()
    {
        if($_POST){

        $pool_open  = input('pool/s');
        $pool_opens  = input('pool_s/s');
        //$averanking1 = input('averanking1');
        //$averanking2 = input('averanking2');
        $avetime1 = input('avetime1');
        $avetime2 = input('avetime2');
        $averanking = !empty($_POST['averanking'])?$_POST['averanking']:'0';
        $averanking_s = !empty($_POST['averanking_s'])?$_POST['averanking_s']:'0';
        $averanking1 = !empty($_POST['averanking1'])?serialize($_POST['averanking1']):'0';
        $averanking2 = !empty($_POST['averanking2'])?serialize($_POST['averanking2']):'0';

            $data = array(
                'achieve_pool' => $pool_open,
                'achieve_pool_s' => $pool_opens,
               // 'averanking1' =>$averanking1,
               // 'averanking' =>$averanking,
                //'averanking2' =>$averanking2,
                'avetime1' =>$avetime1,
                'avetime2' =>$avetime2,
                // 'day' =>$day,
                );
            foreach($data as $k =>$v){
                $updata = Db::query("update tp_config set value='$v' where inc_type='achieve' and name='$k'");
            }
            //delFile(RUNTIME_PATH);
            clearCache();
            $this->success("操作成功");

        }

    }
    public function slist(){

       

        $config = tpCache('achieve');
        $config['averanking1'] = unserialize($config['averanking1']);
        $config['averanking2'] = unserialize($config['averanking2']);

        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//当天结算时间
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));//当天开始时间
        //exit('数据正在调整');
        $y_day =  date("Y-m-d",strtotime("-1 day")); //昨天
        $day = date("Y-m-d");
        $y_daytime = strtotime($y_day.$config['avetime1'].':00:00');
        $daytime =   strtotime($day.$config['avetime2'].':00:00');
        $sale_money = $this->count_todayorder();//统计当天订单金额

        $where = " UNIX_TIMESTAMP(create_time)>=".$y_daytime.' and UNIX_TIMESTAMP(create_time)<='.$daytime;

        if ($_POST) {

            $start_time = strtotime(I('start_time'));
            $end_time = strtotime(I('end_time'));
            $user_id = input('user_id/s');
            if(!empty($start_time) && !empty($end_time))
            {
                $where = " UNIX_TIMESTAMP(create_time)>=".$start_time.' and UNIX_TIMESTAMP(create_time)<='.$end_time;
            }
            if(!empty($user_id))
            {
                $where.=" and u.user_id=".$user_id;
            }
            //$where.= " and  b.user_id like '%$user_id%' ";
            //$cwhere. = "tp_users.user_id like '%$user_id%' ";
        }
        $start_time = $y_day.$config['avetime1'].':00:00' ;
        $end_time = $day.$config['avetime2'].':00:00';
       
        $count = M('agent_performance_log')->alias('a')
        ->field("create_time,sum(a.money) as new_money")
        ->join('tp_users u','a.user_id=u.user_id')
        ->where($where)
        ->having('new_money>='.$config['averanking'])
        ->group('a.user_id')
        
        ->count();

        $Page = new Page($count,10);

        $Pickup = M('agent_performance_log')->alias('a')
         ->field("create_time,sum(a.money) as new_money,a.order_id,u.nickname,u.level,u.user_id,ach_pool,ach_rate")
        ->join('tp_users u','a.user_id=u.user_id')
        //->join('tp_account_log ac','ac.user_id=u.user_id')
        ->where($where)
        ->having('new_money>='.$config['averanking'])
        ->group('a.user_id')
        ->limit($Page->firstRow,$Page->listRows)
        ->order('ach_pool desc,new_money desc,user_id desc')
        ->select();
        foreach ($Pickup as $key=>$val)
        {  $log_where = " change_time>=".$beginToday.' and change_time<='.$endToday.' and log_type=-1 and user_id='.$val['user_id'];
            $log = M('account_log')->where($log_where)->find();
            $Pickup[$key]['p_money']= $log['user_money'];
            $Pickup[$key]['change_time']= !empty($log['change_time'])?date("Y-m-d H:i:s",$log['change_time']):'';
            $Pickup[$key]['log_desc']= $log['desc'];

        }
        $this->assign('page',$Page);
        //$this->assign('page',$sta);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
         //$this->assign('page',$user_id);
        $this->assign('list',$Pickup);
        $this->assign('level', M('user_level')->getField('level,level_name'));
        $this->assign('sale_money',$sale_money);//当天订单总金额

        return $this->fetch();
    }
    //设置会员分红
    public function info()
    {
        $id = I('id');
        $new_money=I('m');
        $info  = M('users')->where('user_id',$id)->find();
        $info['new_money'] = $new_money;
        //print_r($info);exit;
        if($_POST){
              $ach_pool  = input('ach_pool/s');
              $ach_rate = input('ach_rate');

             $data = array(
                'ach_pool' => $ach_pool,
                'ach_rate' =>$ach_rate,
                // 'day' =>$day,
                );
             $res = M('users')->where('user_id',$id)->update($data);
            if($res){
                $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
            }else{
                $this->ajaxReturn(['status' => 0, 'msg' => '参数失败']);
            }

        }
        $level =M('user_level')->getField('level,level_name');
         $this->assign('info',$info);
        $this->assign('level', M('user_level')->getField('level,level_name'));
        return $this->fetch();
    }
    //查看当天订单
    public function orderlist()
    {
        $id = I('id');
       //  $y_day =  date("Y-m-d",strtotime("-1 day")); //昨天
        //$day = date("Y-m-d");
        //$y_daytime = strtotime($y_day.$config['avetime1'].':00:00');
        //$daytime =   strtotime($day.$config['avetime2'].':00:00');


        $where = " UNIX_TIMESTAMP(create_time)>=".$y_daytime.' and UNIX_TIMESTAMP(create_time)<='.$daytime;
          $orderlist = M('agent_performance_log')->alias('a')
         ->field("create_time,sum(a.money) as new_money,a.order_id,u.nickname,u.level,u.user_id,ach_pool")
        ->join('tp_users u','a.user_id=u.user_id')
        //->join('tp_account_log ac','ac.user_id=u.user_id')
        ->where($where)
        ->order('new_money desc')
        ->select();
         $this->assign('list',$orderlist);
        return $this->fetch();
    } 
    //自动发放业绩分红
    public function count_sent()
    {
        $config = tpCache('achieve'); //读取分红设置
        $config['averanking1'] = unserialize($config['averanking1']);
        $config['averanking2'] = unserialize($config['averanking2']);
        if($config['achieve_pool']==0)
        {
            exit("已关闭分红");
        }
        if(!$config)
        {
            exit("参数错误");
        }
        //$config['averanking1'] = unserialize($config['averanking1']);
       // $config['averanking2'] = unserialize($config['averanking2']);
        

        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        $y_day =  date("Y-m-d",strtotime("-1 day")); //昨天
        $day = date("Y-m-d");
        $y_daytime = strtotime($y_day.$config['avetime1'].':00:00');
        $daytime =   strtotime($day.$config['avetime2'].':00:00');
        $where = " UNIX_TIMESTAMP(create_time)>=".$y_daytime.' and UNIX_TIMESTAMP(create_time)<='.$daytime." and u.level in(4,5)";

        //检查是否当天已发放
        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//当天结算时间
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));//当天开始时间
         $log_where = " change_time>=".$beginToday.' and change_time<='.$endToday.' and log_type=10';
         $log = M('account_log')->where($log_where)->find();
        // print_r(M('account_log')->getlastsql());exit;
         if($log)
         {
            exit("当天已经分红了");
         }

         $Pickup = M('agent_performance_log')->alias('a')
         ->field("create_time,sum(a.money) as new_money,a.order_id,u.nickname,u.level,u.user_id")
        ->join('tp_users u','a.user_id=u.user_id')
        //->join('tp_account_log ac','ac.user_id=u.user_id')
        ->where($where)
        ->having('new_money>='.$config['averanking'])
        ->group('a.user_id')
        ->order('new_money desc')
        ->select();  //统计当日业绩符合分红的会员

        foreach($Pickup as $key=>$val)
        {
            //开始分红
           if($val['level']==4)
           {
              $commission = $val['new_money']* ($config['averanking1']['b'][0] / 100);//计算业绩
          }elseif ($val['level']==5) {
             $commission = $val['new_money']* ($config['averanking2']['b'][0] / 100);//计算业绩
          }
           
                   
           
             //发放业绩分红，记录
              if(!empty($commission))
              {
               // $bool = M('users')->where('user_id',$val['user_id'])->setInc('user_money',$commission);
                  if ($bool !== false) {
                    $desc = "业绩分红";
                   
                    $log = $this->writeLog_user($val['user_id'],$commission,$desc,-1); //写入日志
                   } 

              }
        }
        exit('分红成功');
    }
// 分钱全部记录
        public function flist(){

       


        $where = "log_type=-1";

       
        $count = M('account_log_new')->alias('a')
        //->field("create_time,sum(a.money) as new_money")
        ->join('tp_users u','a.user_id=u.user_id')
        ->where($where)
        //->having('new_money>='.$config['averanking'])
       // ->group('a.user_id')
        
        ->count();

        $Page = new Page($count,10);

        $Pickup = M('account_log_new')->alias('a')
         ->field("a.user_money,a.order_id,u.nickname,u.level,u.user_id,ach_pool,a.change_time,a.desc")
        ->join('tp_users u','a.user_id=u.user_id')
        //->join('tp_account_log ac','ac.user_id=u.user_id')
        ->where($where)
        //->group('a.user_id')
        ->limit($Page->firstRow,$Page->listRows)
        ->order('log_id desc')
        ->select();
        $this->assign('page',$Page);
        //$this->assign('page',$sta);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
         //$this->assign('page',$user_id);
        $this->assign('list',$Pickup);
        $this->assign('level', M('user_level')->getField('level,level_name'));
        //$this->assign('sale_money',$sale_money);//当天订单总金额

        return $this->fetch();
    }
  //统计今天的订单
  public function count_todayorder($start_time='',$end_time='')
  {
        if(!$start_time && !$end_time)
        {
             $config = tpCache('achieve');
             $y_day =  date("Y-m-d",strtotime("-1 day")); //昨天
             $day = date("Y-m-d");
             $start_time = strtotime($y_day.$config['avetime1'].':00:00');
             $end_time =   strtotime($day.$config['avetime2'].':00:00');
        }
        $where_goods = [
           // 'og.is_send'    => 1,
            'og.prom_type' =>0,//只有普通订单才算业绩
            //'u.first_leader'=>$v['user_id'],
            //"og.goods_num" =>'>1',
            'o.pay_status'=>1,
            'o.pay_time'    => ['Between',$start_time.','.$end_time],

            'gs.sign_free_receive'=>0,
            
          ];
        $order_goods = Db::name('order_goods')->alias('og')
             ->field('sum(og.goods_num*og.goods_price) as sale_amount')
             ->where($where_goods)
             ->join('goods gs','gs.goods_id=og.goods_id','LEFT')
             ->join('order o','og.order_id=o.order_id','LEFT')
             ->order('og.order_id desc')
             //->limit($pe,50)
             ->find();
        //echo Db::name('order_goods')->getlastsql();exit;
     return $order_goods['sale_amount'];
  }


        //记录日志
  public function writeLog_user($userId,$money,$desc,$states)
  {
    $data = array(
      'user_id'=>$userId,
      'user_money'=>$money,
      'change_time'=>time(),
      'desc'=>$desc,
      //'order_sn'=>$this->orderSn,
      //'order_id'=>$this->orderId,
      'log_type'=>$states
    );

    $bool = M('account_log_new')->insert($data);



    
    return $bool;
  }
}