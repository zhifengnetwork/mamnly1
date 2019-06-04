<?php

namespace app\shop\controller;

use app\admin\logic\GoodsLogic;
use app\common\logic\ModuleLogic;
use think\db;
use think\Cache;
use think\Page;

class Achieve extends MobileBase
{
	

   
    //自动发放业绩分红
    public function count_sent()
    {
        header("Content-type: text/html; charset=utf-8");
        $config = tpCache('achieve'); //读取分红设置
       // $config['averanking1'] = unserialize($config['averanking1']);
        //$config['averanking2'] = unserialize($config['averanking2']);
        if($config['achieve_pool']==0 && $config['achieve_pool_s']==0)
        {
            exit("已关闭分红");
        }
        if(!$config)
        {
            exit("参数错误");
        }
        //$config['averanking1'] = unserialize($config['averanking1']);
       // $config['averanking2'] = unserialize($config['averanking2']);
        
         
        //检查是否当天已发放
        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//当天结算时间
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));//当天开始时间
         $log_where = " change_time>=".$beginToday.' and change_time<='.$endToday.' and log_type=-1';
         $log = M('account_log')->where($log_where)->find();
        // print_r(M('account_log')->getlastsql());exit;
         if($log)
         {
            exit("当天已经分红了");
         }

         $Pickup = M('users')->alias('u')
         ->field("u.level,u.user_id,ach_rate,ach_pool")
        //->join('tp_users u','a.user_id=u.user_id')
        //->join('tp_account_log ac','ac.user_id=u.user_id')
        ->where('ach_pool=1')
        ->select();  //统计当日业绩符合分红的会员
        //print_R($Pickup);exit;
        $sale_money = $this->count_todayorder();
        foreach($Pickup as $key=>$val)
        {
            //开始分红
           if ($val['level']==5) 
           {
                if($config['achieve_pool']==1)
                {
                 $commission = $sale_money* ($val['ach_rate'] / 100);//计算业绩
                }else
                {
                    $commission ='';
                }

          }else
          {
              if($config['achieve_pool_s']==1)
              {
                $commission = $sale_money* ($val['ach_rate'] / 100);//计算业绩
              }
              
              else
              {
                $commission ='';
              }

          }
           
                   
           
             //发放业绩分红，记录
              if(!empty($commission))
              {
               // $bool = M('users')->where('user_id',$val['user_id'])->setInc('user_money',$commission);
                  if ($bool !== false) {
                    $desc = "业绩分红";
                   echo "ID:".$val['user_id']."分红成功<br/>";
                    $log = $this->writeLog_user($val['user_id'],$commission,$desc,-1); //写入日志
                   } 

              }else
              {
                echo "已关闭分红或者会员比例未设置<br/>";
              }
        }
        exit('流程结束');
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