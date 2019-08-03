<?php

namespace app\common\logic;

use think\Exception;
use think\Model;
use think\Db;

/**
 * 活动逻辑类
 */
class BonusPoolLogic extends Model
{

	//判断商品是否为领取类目的产品
	public function is_receive($order)
	{
		//判断是否开启奖金池
		$pool_open = $this->getData('pool_open');
		if($pool_open == '0') return false;
		
		//订单中的所有产品
		$orderGoods = M('order_goods')
					->where('order_id', $order['order_id'])
					->field('goods_num,is_bonus')
					->select();
					
        //计算产品数量
        $nums = 0;
        foreach ($orderGoods as $key => $value) {
            //参加活动的产品
            if($value['is_bonus'] == 1){
                $nums = $nums + $value['goods_num'];
            }
        }
		if($nums <= 0) return false;

		//检查是否有邮费
		if($order['shipping_price'] <= 0) return false;

		//领取产品的用户
		$user = M('users')->where('user_id', $order['user_id'])
				->field('user_id, nickname, first_leader')
			  	->find();  	
		if(!$user || $user['first_leader'] == 0) return false;

		//找不到上级不抽取邮费
		$leader = M('users')->where('user_id', $user['first_leader'])->value('user_id');
		if(!$leader) return false;

		//开启事务
		Db::startTrans();
		try{
			//将邮费放进奖金池
			$money = $this->put_in($order['shipping_price']);
			
			//记录直属领取的日志
			$this->write_log($nums, $money, $user, $order['order_id']);
			
			//给上级记录排名
			$this->write_ranking($nums, $money, $user);
			
			// 提交事务
			Db::commit();  
	        return true;
		}catch(\Exception $e){
			// 回滚事务
            Db::rollback();
            return false;
		}
	}

	/*
     * 将邮费放进奖金池
     * shipping 运费
     */
	public function put_in($shipping)
	{
		//获每个产品抽取的邮费
        $bonus_pool = $this->getData('bonus_pool');
		$bonus_total = $this->getData('bonus_total');
        
		//抽取邮费的百分比
		$money = round($shipping * ((int)$bonus_pool/100), 2);

        // 增加奖金池
        $bonus_total  = $money + (real)$bonus_total;
        $data   = array('name' => 'bonus_total', 'inc_type' => 'bonus');
        $result = Db::name('config')->where($data)->update(['value' => $bonus_total]);
		if($result){
			return $money;
		}else{
			return false;	
		}
	}

	//记录领取日志
	public function write_log($nums, $money, $user, $order_id)
	{
		$data = array(
			'leader_id' => $user['first_leader'],
			'user_id'   => $user['user_id'],
			'user_name' => $user['nickname'],	
			'order_id'  => $order_id,
			'money' => $money,
			'nums'  => $nums,
			'desc'  => '领取'.$nums.'个商品',
			'create_time' => time(),
		);
		$result = Db::name('bonus_receive_log')->insert($data);
		return $result;	
	}

	//记录上级排名
	public function write_ranking($nums, $money, $user)
	{
		//结算排名的时间戳
		$arr       = ['bonus_time','day'];
		$config    = $this->getData($arr);
		$bonus_day = (int)$config['day'];

		//当月奖励时间
		$tb_time['bonus_now']  = strtotime(date('Y-m') . '-' . $bonus_day . ' 00:00:00');
		//上次奖励时间
		$tb_time['bonus_time'] = (int)$config['bonus_time'];
		
		$condition['user_id']  = $user['first_leader'];
		$condition['status']   = 0;
		if(time() > $tb_time['bonus_now']){
			$condition['create_time'] = ['>', $tb_time['bonus_now']];
		}else{
			$condition['create_time'] = [['<=', $tb_time['bonus_now']], ['>', $tb_time['bonus_time']]];
		}

		//查询用户是否已经存在排名
		$users = M('bonus_rank')->where($condition)->find();
		//用户已存在排名则更新数据, 否则插入一条新记录
		if($users){
			$data = array(
				'nums'  => $users['nums']  + $nums,
				'money' => $users['money'] + $money,
				'update_time' => time(), 
			);
			$result = Db::name('bonus_rank')->where('id', $users['id'])->update($data);
			return $result;
		}else{
			$data = array(
				'user_id' => $user['first_leader'],
				'nums'    => $nums,
				'money'   => $money,
				'create_time' => time(),
				'update_time' => time(),
			);
			$result = Db::name('bonus_rank')->insert($data);
			return $result;
		}
	}

	//奖金池奖励
	public function bonus_reward()
	{
		//判断是否满足奖励的条件
		$satisfy = $this->is_satisfy();
		if(!$satisfy) return false;

		//获取后台设置的奖励信息
		$data = array('bonus_total', 'day', 'bonus_time', 'ranking1', 'ranking2', 'ranking3');
		$data = $this->getData($data);
	
		//截止时间,用于修改排名表时不更新后面新增的数据
		$bonus_day = (int)$data['day'];
		//当月奖励时间
		$tb_time['bonus_now']  = strtotime(date('Y-m') . '-' . $bonus_day . ' 00:00:00');
		//上次奖励时间
		$tb_time['bonus_time'] = (int)$data['bonus_time'];
		
		$condition = array();
		$condition['create_time'] = [['>', $tb_time['bonus_time']], ['<=', $tb_time['bonus_now']]];

		$result = $this->getUser($tb_time, $condition);
		if(!$result) return false;
		
		//按数量取最大的前3条记录
		Db::startTrans();
		try{
			//记录奖励的总额
			$count = 0;
			$log = array();
			$now = time();
			foreach ($result as $key => $value) {
				//奖励总金额
				$num = $key + 1;
				$money = round(($data['ranking' . $num] / 100) * $data['bonus_total'], 2);
				$count = $count + $money;
				accountLog($value['user_id'], $money, 0,  '奖金池排名奖励');
				
				$log[$key]['user_id'] = $value['user_id'];
				$log[$key]['money'] = $money;
				$log[$key]['ranking'] = $num;
				$log[$key]['bonus_total'] = $data['bonus_total'];
				$log[$key]['create_time'] = $tb_time['bonus_now'];
				$log[$key]['status'] = 1;
			}

			//记录奖励日志,修改排名记录为过期
			Db::name('bonus_log')->insertAll($log);
			Db::name('bonus_rank')->where($condition)->where('status', 0)
					->update(['status'=>1]);
			//剩余金额
			$remanent = round(($data['bonus_total'] - $count), 2);
			$arr = array('name' => 'bonus_total', 'inc_type' => 'bonus');
			Db::name('config')->where($arr)->update(['value'=>$remanent]);
			Db::commit();
			return true;
		}catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    // $this->fail_reward($now);
		    return false;
		}
	}

	//获取奖励用户
	public function getUser($tb_time, $condition)
	{
		$data = array('name' => 'bonus_time', 'inc_type' => 'bonus');
		M('config')->where($data)->update(['value'=>$tb_time['bonus_now']]);
		
		$condition['rank.status'] = 0;
		$result = Db::name('bonus_rank')->alias('rank')
				->join('users', 'rank.user_id = users.user_id')
				->field('rank.*, users.user_money')->where($condition)
				->order('rank.nums DESC, rank.money DESC, rank.create_time ASC, rank.id ASC')
				->limit(3)->select();
				
		return $result;
	}

	// //奖励失败时插入记录
	// public function fail_reward($data)
	// {
	// 	$data = array(
	//     	$log['user_id'] = 0,
	// 		$log['money'] = 0,
	// 		$log['ranking'] = 0,
	// 		$log['bonus_total'] = 0,
	// 		$log['create_time'] = $data,
	// 		$log['status'] = 0,
	//     );
	//     M('bonus_log')->insert($data);
	// }

    //获取奖金池设置信息
    public function getData($data = '')
    {
    	$condition['inc_type'] = 'bonus';

        if(is_array($data)){

            $condition['name'] = ['in', $data];
            $result = M('config')->where($condition)->column('name, value');

        }else if($data != ''){

            $condition['name'] = $data;
            $result = M('config')->where($condition)->value('value');

        }else{

            $condition = array();
            $result = M('config')->where($condition)->column('name, value');

        }

        return $result;
    }

	//判断是否满足条件
	public function is_satisfy()
	{
		//判断奖金池是否开启
		$bonus_open = $this->getData('bonus_open');
		if(!$bonus_open) return false;
		
		//判断是否达到后台设定的奖励日期
		//默认晚上12点结算奖励
		$time     = 0;
		$pre_day  = date('d');
		$pre_time = date('H');
		$day = $this->getData('day');
		if(($pre_day < $day) or ($pre_time < $time)) return false;

		//查询排名奖励表当月是否已经奖励
		$is_reward = M('bonus_log')->whereTime('create_time','month')->find();
		if($is_reward) return false;

		return true;
	}


}