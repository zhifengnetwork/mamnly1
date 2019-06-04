<?php

namespace app\shop\controller;
use app\common\logic\BonusPoolLogic;

use think\Db;
use think\Page;

class Pool extends MobileBase {
	public $user_id = 0;

	//初始化操作
    public function _initialize()
    {
        parent::_initialize();
        if (!session('user')) {
            header("location:" . U('Mobile/User/login'));
            exit;
        }
        
        $user = session('user');
        session('user', $user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
    }

    //排名
	public function index()
	{
		$arr = array('name' => 'bonus_open', 'inc_type' => 'bonus');
		$bonus_open = M('config')->where($arr)->value('value');
		if($bonus_open){
			//奖金池奖励
			// $BonusPoolLogic = new BonusPoolLogic();
			// $BonusPoolLogic->bonus_reward(); 
			
			$user_id = $this->user_id;
			$data = array('bonus_time', 'bonus_total', 'day');
			$data = M('config')->where('name', ['in', $data])
							->column('name, value');

			$bonus_time = (int)$data['bonus_time'];
			$bonus_day  = (int)$data['day'];
			$bonus_now  = strtotime(date('Y-m') . '-' . $bonus_day . ' 00:00:00');
			$condition  = array(
				'rank.status' => 0,
				'rank.create_time' => [['>', $bonus_time], ['<=', $bonus_now]],
			);

			$my_rank = M('bonus_rank')->alias('rank')
					->join('users','users.user_id = rank.user_id' )
					->where($condition)->where(['rank.user_id' => $user_id])
					->field('rank.*,users.nickname')
					->order('id DESC')->find();
			if($my_rank){
				$rank_sort = M('bonus_rank')
						->alias('rank')
						->where($condition)
						->order('money DESC, nums DESC, create_time ASC')
						->column('user_id');
				$rank_sort = array_flip($rank_sort);
				$my_rank['ranking'] = $rank_sort[$my_rank['user_id']]+1; 
			}

			$rank = M('bonus_rank')->alias('rank')
					->join('users','users.user_id = rank.user_id' )
					->where($condition)->field('rank.*,users.nickname')
					->order('rank.nums DESC, rank.money DESC, rank.create_time ASC, rank.id ASC')
					->limit(4)->select();
		}else{
			$data = array();
			$rank = array();
			$my_rank = array();
		}
		
		$this->assign('data', $data);
		$this->assign('my_rank', $my_rank);
		$this->assign('rank', $rank);
		return $this->fetch();
	}

	//往期奖励记录
	public function past_period()
	{
		$user_id = $this->user_id;
		
		//这一年我的奖励记录
		$my_rank = M('bonus_log')->alias('log')->join('users', 'users.user_id = log.user_id' )
				 ->where(['log.user_id' => $user_id])->whereTime('create_time', 'year')
				 ->field('log.*, users.nickname')->order('id DESC')->select();

		//上一期前三名的奖励记录
		$rank = M('bonus_log')->alias('log')->join('users', 'users.user_id = log.user_id' )
			  ->where('log.status', 1)->field('log.*, users.nickname')->order('id DESC')
			  ->limit(3)->select();
		foreach ($rank as $key => $value) {
			if($key != 0){
				if($value['create_time'] != $rank[0]['create_time']){
					unset($rank[$key]);
				}
			}
		}
		//重新排序	
		array_multisort(array_column($rank, 'ranking'),SORT_ASC,$rank);
		$this->assign('bonus_total', $bonus_total);
		$this->assign('my_rank', $my_rank);
		$this->assign('rank', $rank);
		return $this->fetch();
	}
}