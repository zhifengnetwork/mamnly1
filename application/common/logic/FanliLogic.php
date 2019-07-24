<?php
/**
 * DC环球直供网络
 * ============================================================================
 *   分销、代理
 */

namespace app\common\logic;

use app\common\logic\LevelLogic;
use think\Model;
use think\Db;
use think\Session;
/**
 * 返利类
 */
class FanliLogic extends Model
{

	private $userId;//用户id
	private $goodId;//商品id
	private $goodNum;//商品数量
	private $orderSn;//订单编号
	private $orderId;//订单id

	public function __construct($userId,  $goodId, $goodNum, $orderSn, $orderId)
	{	
		$this->userId = $userId;
		$this->goodId = $goodId;
		$this->goodNum = $goodNum;
		$this->orderSn = $orderSn;
		$this->orderId = $orderId;
		$this->tgoodsid = $this->catgoods();
	}
	//获取返利数据
	public function getconfing()
	{
         $goods_info = M('goods')->where(['goods_id'=>$this->goodId])->field('rebate')->find();
         return unserialize($goods_info['rebate']);
	}
		//获取返利数据
	public function getgoodsinfo()
	{
         $goods_info = M('goods')->where(['goods_id'=>$this->goodId])->field('cat_id,sign_free_receive,goods_name,level6_fanli')->find();
         return $goods_info;
	}
	//获取用户购买特殊产品数量
	public function getproductnum()
	{
        $num=M('order_goods')->alias('og')
        ->join('order o', 'og.order_id=o.order_id')
        ->where(['o.user_id'=>$this->userId,'og.goods_id'=>$this->tgoodsid,'o.pay_status'=>1])
        ->count();
        return $num;
	}
    //会员返利
	public function fanliModel()
	{

		$price = M('goods')->where(['goods_id'=>$this->goodId])->value('shop_price');
		//判断商品是否是活动商品
		$good = M('goods')
				->where('goods_id', $this->goodId)
				->field('is_distribut,is_agent')
                ->find();
           //获取每个产品返利数据
         $rebase = $this->getconfing();
        //查询会员当前等级
		$user_info = M('users')->where('user_id',$this->userId)->field('first_leader,level,user_id')->find();
		//查询上一级信息
		$parent_info = M('users')->where('user_id',$user_info['first_leader'])->field('level')->find();
        //判断是否特殊产品成为合伙人，则不走返利流程
        //用户购买后检查升级
      	$goods_info=$this->getgoodsinfo();
        if($goods_info['sign_free_receive']==0) //免费领取，签到产品不参与返利
        {
        	  
		 $this->checkuserlevel($this->userId,$this->orderId);
	    }

		$pro_num = $this->getproductnum();
		//echo $this->goodId.'-'.$this->tgoodsid.'-'.$user_info['level'];exit;
		$goods_info=$this->getgoodsinfo();

		if(($goods_info['cat_id'] == C('customize.level6_cid')) && $goods_info['level6_fanli'])
		{
			//查找上级中level=6的用户
			$UsersLogic = new \app\common\logic\UsersLogic();
			$leader = $UsersLogic->getUserLevTop($this->userId,6);	
			if($leader['user_id']){
				$desc = "创业包返利";
				$log = $this->writeLog($leader['user_id'],$goods_info['level6_fanli'],$desc,33); 				
			}		
		}

		if(($goods_info['sign_free_receive']==0) && ($goods_info['cat_id']!= C('customize.special_cid'))) //免费领取，签到产品不参与返利
		{
			if($user_info['level']>=3)//自购只返利给合伙人以上级别
				{
					$distribut_level = M('user_level')->where('level',$user_info['level'])->field('direct_rate')->find();
						//计算返利金额
			$goods = $this->goods();
			$commission = $goods['shop_price'] * ($distribut_level['direct_rate'] / 100) * $this->goodNum;
					//计算佣金
				//按上一级等级各自比例分享返利
			$bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);
				if ($bool !== false) {
					$desc = "自购返利";
					$log = $this->writeLog($user_info['user_id'],$commission,$desc,7); 
					//return true;
					} else {
					return false;
					}
				}		
		}

        if($this->goodId==$this->tgoodsid )//是否特殊产品
        {
        	 
          $this->addhostmoney($user_info['user_id'],$parent_info);//合伙人推荐合伙人
             $this->upzdmoney($user_info['first_leader']);//执行，董事无限代
             //$this->pingji($user_info['first_leader']);//评级奖
          //$this->ppInvitation($user_info['first_leader']);//联合创始人下线推荐合伙人金额
          //$this->ccInvitation($user_info['first_leader']);//执行下线推荐合伙人金额
        }
        else
        {
        	//不是特产品按照佣金比例反给用户 ，自购返利
        	if($goods_info['sign_free_receive']==0) //免费领取，签到产品不参与返利
        	{
            // 购买商品返利给上一级
            if(empty($rebase)||$rebase[$parent_info['level']]<=0) //计算返利比列
		       {
                   $fanli = M('user_level')->where('level',$parent_info['level'])->field('rate')->find();
		       }else
		       {
		           $fanli['rate'] = $rebase[$parent_info['level']];
		       }
	          //查询会员等级返利数据
		       if($parent_info['level']!=1 && !empty($parent_info)){ //上一级是普通会员则不反钱
		         //计算返利金额
		          $goods = $this->goods();
		          $commission = $goods['shop_price'] * ($fanli['rate'] / 100) * $this->goodNum;
		           //计算佣金
		          //按上一级等级各自比例分享返利
		          $bool = M('users')->where('user_id',$user_info['first_leader'])->setInc('user_money',$commission);
			      if ($bool !== false) {
			        	$desc = "分享返利";
			        	$log = $this->writeLog($user_info['first_leader'],$commission,$desc,1); //写入日志
			            //检查返利管理津贴
			          $this->jintie($user_info['first_leader'],$commission);
			        	//return true;
			         } else {
			        	return false;
			         }
			     }else{
			     	//return false;
			     }

        	}

           
        	 

            /*
		    elseif($user_info['level']>=4) //是复购
		    {
	            if(empty($rebase)||$rebase[$user_info['level']]<=0) //
		         {
                    $fanli = M('user_level')->where('level',$user_info['level'])->field('rate')->find();
                    
		         }else
		         {
		          	 $fanli['rate'] = $rebase[$user_info['level']];
		          	 
		         }
	          if(!empty($fanli['rate']))
	          {


	          //计算返利金额
	          $goods = $this->goods();
	          $commission = $goods['shop_price'] * ($fanli['rate'] / 100) * $this->goodNum; //计算佣金
	          //按上一级等级各自比例分享返利
	          $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);

	         if ($bool !== false) {
	        	$desc = "复购返利";
	        	$log = $this->writeLog($user_info['user_id'],$commission,$desc,1); //写入日志
	          }
	      

	        	//return true;
	         } else {
	        	return false;
	         }
	        }*/
		
	    }

	}
	//会员升级
	public function checkuserlevel($user_id,$order_id)
	{
         //自动升级为vip，合伙人，联合创始人，执行董事自动申请
		
		 //扫码登陆

		$order = M('order')->where(['order_id'=>$this->orderId])->find();
		$user_info = M('users')->where('user_id',$user_id)->field('first_leader,level,is_code,user_id')->find();
        $goodid = $this->goodId;
        $tgoodsid =$this->tgoodsid;
		if( $order['pay_status']==1 && $user_info['level']==1 && $goodid!=$tgoodsid)//自动升级vip
		{
              $res = M('users')->where(['user_id'=>$user_id])->update(['level'=>2]);
              	$desc = "购买产品成为vip";
	        	$log = $this->writeLog_ug($user_info['user_id'],'',$desc,2); //写入日志
		}
		else if($this->goodId==$this->tgoodsid  && $order['pay_status']==1 && $user_info['level']<3)//自动升级合伙人
		{
			$res_s = M('users')->where(['user_id'=>$user_id])->update(['level'=>3,'count_time'=>'']);
			$desc = "购买指定产品获得合伙人";
	        $log = $this->writeLog_ug($user_info['user_id'],'398',$desc,2); //写入日志

	        if($res_s)
	        {
	        	//$this->addhostmoney2($user_info['user_id']);//产生合伙人获得金额和津贴
	          //自动升级联合创始人
			    $parent_info = M('users')->where('user_id',$user_info['first_leader'])->field('first_leader,level,is_code,user_id')->find();

				
				$fanli = M('user_level')->where('level',4)->field('con_name,con_level')->find();
				$num=M('users')->where(['first_leader'=>$user_info['first_leader'],'level'=>$fanli['con_level']])->count();
				if($num>=$fanli['con_name'] && !empty($fanli['con_name']))
				{
						$res = M('users')->where(['user_id'=>$user_info['first_leader']])->update(['level'=>4,'count_time'=>'']);
						$desc = "直推合伙人".$fanli['con_name']."个成为联合创始人";
						$log = $this->writeLog_ug($user_info['first_leader'],'',$desc,2); //写入日志

						$fanli = M('user_level')->where('level',5)->field('con_name,con_level')->find();
						$first_leader = M('Users')->where(['user_id'=>$user_info['first_leader']])->column('first_leader');
						if(!$first_leader)return;
						$num=M('users')->where(['first_leader'=>$first_leader,'level'=>$fanli['con_level']])->count();
						if($num>=$fanli['con_name'] && !empty($fanli['con_name'])){
							$res = M('users')->where(['user_id'=>$first_leader])->update(['level'=>5,'count_time'=>'']);
							$desc = "直推联合创始人".$fanli['con_name']."个成为执行董事";
							$log = $this->writeLog_ug($first_leader,'',$desc,2); //写入日志	
						}
				}
	        }
		}

	}
    //推荐合伙人获得金额
	public function addhostmoney($user_id,$parent_info)
	{
		$user_info = M('users')->where('user_id',$user_id)->field('first_leader,level')->find();
       //只有合伙人，联合创始人，执行董事推荐合伙人才能的到合伙人推荐金额
		 if($parent_info['level']==3 || $parent_info['level']==4 || $parent_info['level']==5)//
		 {
		//计算返利金额
		  $fanli = M('user_level')->where('level',$parent_info['level'])->field('rate','reward')->find();
          $goods = $this->goods();
          $commission = $fanli['reward']; //计算金额
          
         // print_R($goods['shop_price'].'-'.$this->goodNum.'-'.$fanli['rate']);exit;
          //按上一级等级各自比例分享返利
          if($commission>0){
          	 $bool = M('users')->where('user_id',$user_info['first_leader'])->setInc('user_money',$commission);
	         if ($bool !== false) {
	        	$desc = "推荐合伙人获得金额";
	        	$log = $this->writeLog($user_info['first_leader'],$commission,$desc,3); //写入日志

	        	return true;
	         } else {
	        	return false;
	         }

          }
      
	     }
	     else
	     {
            return false;
	     }

	}
	//获得管理津贴
	public  function jintie($user_leader,$fanli_money)
	{
      //只有联合创始人和执行获得管理津贴
		//查询上上级信息
		$parent_info = M('users')->where('first_leader',$user_leader)->field('level,user_id')->find();
		if($parent_info['level']==4 || $parent_info['level']==5)
		{
			$fanli = M('user_level')->where('level',$parent_info['level'])->field('jintie')->find();
			 $commission = $fanli_money * ($fanli['jintie'] / 100);

	          //按上一级等级各自比例分享返利
	       $bool = M('users')->where('user_id',$parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "获得管理津贴";
	        $log = $this->writeLog($parent_info['user_id'],$commission,$desc,5); //写入日志
		}

	}
		//获得管理津贴
	public  function jintienew($user_id,$fanli_money)
	{
      //只有联合创始人和执行获得管理津贴
		//查询上上级信息
		$user_info = M('users')->where('user_id',$user_id)->field('level,user_id')->find();

			$fanli = M('user_level')->where('level',$user_info['level'])->field('lead_reward')->find();
			if($fanli['lead_reward']>0)
			{
			 $commission = $fanli_money * ($fanli['lead_reward'] / 100);

	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "平级领导奖";
	        $log = $this->writeLog($user_info['user_id'],$commission,$desc,5); //写入日志

			}
			
		

	}
	 //联合创始人，执行董事产生一个合伙人的金额和管理津贴
	public function addhostmoney2($user_id)
	{
		//查询会员当前等级
		$user_info = M('users')->where('user_id',$this->userId)->field('first_leader,level')->find();
		//查询上一级信息
		$parent_info = M('users')->where('user_id',$user_info['first_leader'])->field('level')->find();
		if($parent_info['level']==4 || $parent_info['level']==5)
		{
           $fanli = M('user_level')->where('level',$parent_info['level'])->field('chan')->find();
	         //计算返利金额
	       $commission = $fanli['chan']; //计算佣金
	          //按上一级等级各自比例分享返利
	       $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "团队产生合伙人获得金额";
	        $log = $this->writeLog($user_info['first_leader'],$commission,$desc,4); //写入日志
	        return true;
		}

	}

	//记录日志
	public function writeLog($userId,$money,$desc,$states)
	{
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$this->orderSn,
			'order_id'=>$this->orderId,
			'log_type'=>$states
		);

		$bool = M('account_log')->insert($data);


         if(empty($money))
         {
         	$money =0; 
         }
         //分钱后成功收到微信推送信息
         $goods_name =$this->getgoodsinfo();
         $user_info = M('users')->where('user_id',$userId)->field('openid')->find();
         if($bool && !empty($user_info['openid'])){
			$this->sent_weiassage($user_info['openid'],$goods_name['goods_name'],$money,$this->orderSn);
		}
		if($bool){

			//分钱记录
			$data = array(
				'order_id'=>$this->orderId,
				'user_id'=>$userId,
				'status'=>1,
				'goods_id'=>$this->goodId,
				'money'=>$money
			);
			M('order_divide')->add($data);
		
		}

		
		return $bool;
	}
		//升级记录
	public function writeLog_ug($userId,$money,$desc,$states)
	{
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$this->orderSn,
			'order_id'=>$this->orderId,
			'log_type'=>$states
		);

		$bool = M('upgrade_log')->insert($data);
		
		return $bool;
	}
	public function goods(){
		$goods = M('goods')->field("shop_price,cat_id")->where(['goods_id'=>$this->goodId])->find();
		return $goods;
	}
	//特殊产品
	public function catgoods()
	{
		$goods = M('goods')->field("goods_id")->where(['cat_id'=>8,'goods_id'=>$this->goodId])->find();
		if(!$goods) return 0;
		return $goods['goods_id'];
	}
		//联合创始人，执行无限代返利
	public function upzdmoney($user_id)
	{
		$three =0; //记录联合创始人返利几个
	    $zongjing =0;//记录这条线是否有联合创始人
	    $four = 0;//记录执行返利几个
	    $pingji_4 =0;//记录联合创始人平级奖返利几个
	    $pingji_5 =0;//记录执行平级奖返利几个
	    $daqu=0;
	    $teshu =0;//特殊情况
		//查询上级信息
		$parent_info = M('users')->where('user_id',$user_id)->field('level,user_id,first_leader')->find();
		//查询上上级信息
		//$p_parent_info = M('users')->where('user_id',$parent_info['first_leader'])->field('level,user_id,first_leader')->find();
		 
           //循环无限代
			if(!empty($user_id))
			{
				$first_leader = $this->newgetAllUps($user_id);
			  foreach($first_leader as $ke=>$ye)
			 {
			 //	if($ke>=1) //从上上级开始
			 	//{
			 $next_k =$ke+1;
			 //特殊情况 上级就是联合创始人，跳到到联合创始人程序
			 /*
			 if($parent_info['level']==4)
			 {
			 	$three =1;
			 } */

			  if($ye['level']==4 && $three<2 && $error!=1)   //处理联合创始人返利
			  {
	
				    if($parent_info['level']==4 && $pingji_4!=1)//联合创始人直邀合伙人平级奖
				    {
				     	if($ke>=1)
				     	{
						    $fanli = M('user_level')->where('level',4)->field('pin_reward')->find();
					     	
					        $commission = $fanli['pin_reward']; //计算金额
					        if($commission>0){
					        	          //按上一级等级各自比例分享返利
					        $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
					       	$desc = "联合创始人平级奖";
					        $log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
					        $pingji_4 =1;
					        $three = $three+1;

					        }
				
				        }
		
				     }
				     elseif($parent_info['level']!=4 && $pingji_4!=1)
				     {
				     	if($three==1) //联合创始人平级奖
						 {
	                  	    if($ke>=1)
					     	{
						        $fanli = M('user_level')->where('level',4)->field('pin_reward2')->find();
					     	
						        $commission = $fanli['pin_reward2']; //计算金额
						        if($commission>0){
							          //按上一级等级各自比例分享返利
							        $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
							       	$desc = "联合创始人平级奖";
							        $log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
							        $pingji_4 =1;
							        $three = $three+1;
						        }
					        }
						 }
						 if($three<1)
						 {
						 	if($ke>=1)
					     	{
							     $fanli = M('user_level')->where('level',$ye['level'])->field('y_reward')->find();
							 	$commission = $fanli['y_reward']; //计算金额
							 	if($commission>0){
							 		 //按上一级等级各自比例分享返利
					        	$bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
					       		$desc = "联合创始人直属合伙人邀合伙人获得金额";
					        	$log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
					        	$three =$three+1;

							 	}
				         
				            }

						 }
				     }
				     if($parent_info['level']==4 && $three==1)
				     {
				     	$teshu =1;
				     }
				   // if($ke>=1)
				    //{
				   	    $zongjing =1;
						//$three =$three+1;
				    //}
				
				}
				if($ye['level']==5 && $four<2)  //处理执行返利
				{
					/*
					 if($parent_info['level']==5)
					 {
	
					 	$four =1;
					 } */
					$f_1 =$first_leader[$ke]['level'];
					$f_2 =$first_leader[$next_k]['level'];
					if($f_1>$f_2)
					{
                         $error =1; //判断上级比下级等级少，记录不循环
					}
		
				   if($parent_info['level']==5 && $pingji_5!=1)//执行平级奖
				   {
				      if($ke>=1)
				      {
					     $fanli = M('user_level')->where('level',5)->field('pin_reward')->find();
						 $commission = $fanli['pin_reward']; //计算金额
						 if($commission>0)
						 {
						 	//按上一级等级各自比例分享返利
				         $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
				       	 $desc = "执行平级奖";
				         $log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志

						 }
				          
				         /*
				         if(!empty($commission_z))
		             	{
		             	  //平级领导奖
			 			  $this->jintienew($ye['user_id'],$commission_z);//平级领导奖

		             	}*/

	
		             	//平级领导奖
				        $fanli = M('user_level')->where('level',$parent_info['level'])->field('reward')->find();
			 			$commission = $fanli['reward']; //计算金额
			 			$this->jintienew($ye['user_id'],$commission);//平级领导奖

		             	
				         $pingji_5 =1;
				         $four =$four+1;
				      }
				
				   }
				   elseif($parent_info['level']!=5 && $pingji_5!=1)
				   {
				   	
					if($four==1)  
					 {
					 	//特殊情况返利两个联合创始人时候没有平级奖
					 	if($teshu==0){
					 		 //执行直属联合创始人邀合伙人的平级奖
						 	if($parent_info['level']==4)
						 	{
						 	  $fanli = M('user_level')->where('level',5)->field('pin_reward4')->find();
							  $commission = $fanli['pin_reward4']; //计算金额
						 	}
						 	//执行直属联合创始人的合伙人邀合伙人的平级奖
						 	elseif($zongjing==1)//证明这条线有联合创始人和执行
							{
						 	  $fanli = M('user_level')->where('level',5)->field('pin_reward2')->find();
							  $commission = $fanli['pin_reward2']; //计算金额
					         
					         }
					         //执行直属合伙人邀合伙人的平级奖
					         else
					         {
					         	 $fanli = M('user_level')->where('level',5)->field('pin_reward3')->find();
							     $commission = $fanli['pin_reward3']; //计算金额
					         }
					         if($commission>0)
						    {
					         $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
					       	 $desc = "执行平级奖";
					         $log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
					        }

					 	}

                  	    if(!empty($commission_z))
		             	{		             	 
		             	   //平级领导奖
			 			   $this->jintienew($ye['user_id'],$commission_z);//平级领导奖
			 			   $four =$four+1;
			 			   $pingji_5 =1;
		             	}
					 }
                    if($four<1)
                    {
                    	if($teshu==1) //执行直属联合创始人邀合伙人(有平级联合创始人时候)
					 	{
					 		if($ke>=1)
					      {

						 	$fanli = M('user_level')->where('level',$ye['level'])->field('h_reward')->find();
						 	$commission_z = $fanli['h_reward']; //计算金额
				          //按上一级等级各自比例分享返利
						 	if($commission_z>0){
						 	 $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
				       		 $desc = "执行直属联合创始人邀合伙人获得金额";
				       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
				       		 $four =$four+1;

						 	}
				        	
				       	   }

					 	}
                    	elseif($parent_info['level']==4) //执行直属联合创始人邀合伙人
					 	{
					 		if($ke>=1)
					      {

						 	$fanli = M('user_level')->where('level',$ye['level'])->field('s_reward')->find();
						 	$commission_z = $fanli['s_reward']; //计算金额
						 	if($commission_z>0)
						 	{
						 		//按上一级等级各自比例分享返利
				        	$bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
				       		$desc = "执行直属联合创始人邀合伙人获得金额";
				       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
				       		 $four =$four+1;

						 	}
				          
				       	   }

					 	}
	                    elseif($zongjing==1)//证明这条线有联合创始人和执行
						{
						  if($ke>=1)
					      {

						 	$fanli = M('user_level')->where('level',$ye['level'])->field('k_reward')->find();
						 	$commission_z = $fanli['k_reward']; //计算金额
				          //按上一级等级各自比例分享返利
						 	if($commission_z>0)
						 	{
				        	$bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
				       		$desc = "执行直属联合创始人的合伙人邀合伙人获得金额";
				       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
				       		 $four =$four+1;
				       		}
				       	   }

						}else //只有执行
						{
						    if($ke>=1)
					        {
								 $fanli = M('user_level')->where('level',$ye['level'])->field('y_reward')->find();
							 	 $commission_z = $fanli['y_reward']; //计算金额
					             //按上一级等级各自比例分享返利
					             if($commission_z>0)
							 	{
					       		 $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
					       		 $desc = "执行直属合伙人邀合伙人获得金额";
					       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
					       		 $four =$four+1;
					       		}
				       		}

						}

                    }

				   }



				 }

			 	//}

			  }
			}
		

	}

	

 /*
 * 获取所有上级
 */
   public function newgetAllUps($invite_id,&$userList=array())
  {           
      $field  = "user_id,first_leader,mobile,level";
      $UpInfo = M('users')->field($field)->where(['user_id'=>$invite_id])->find();
      if($UpInfo)  //有上级
      {
          $userList[] = $UpInfo;
          $this->newgetAllUps($UpInfo['first_leader'],$userList);

      }
      
      return $userList;     
      
  }
  public function sent_weiassage($openid,$goods_name,$yongjin,$order_sn)
  {
  	   $logic = new \app\common\logic\TemplateMessage();
        //$openid = 'okGVu1Z1J_m0n6YhDvqBFziqdTrQ';
        //$goods_name = '一坨屎';
        //$yongjin = '2.50';
        //$order_sn = '12345678910';
        $res = $logic->yongjin($openid,$goods_name,$yongjin,$order_sn);
  }

	
}