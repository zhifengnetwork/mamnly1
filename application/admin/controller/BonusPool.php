<?php

namespace app\admin\controller;

use think\Page;
use think\Db;

class BonusPool extends Base {
    /**
     * 奖金池排名
     */
    public function ranking()
    {
        $arr = array(
            'name' => ['in', ['day', 'bonus_time']],
            'inc_type' => 'bonus',
        );
        $config = M('config')->where($arr)->column('name, value');
        $bonus_day = (int)$config['day'];
		//当月奖励时间
		$tb_time['bonus_now']  = strtotime(date('Y-m') . '-' . $bonus_day . ' 00:00:00');
		//上次奖励时间
        $tb_time['bonus_time'] = (int)$config['bonus_time'];
        $condition['create_time'] = [['<=', $tb_time['bonus_now']], ['>', $tb_time['bonus_time']]];
        $condition['rank.status'] = 0;
        if($bonus_time){
            $condition['rank.create_time'] = ['>', $bonus_time];
        }

        $count = M('bonus_rank')->alias('rank')
                ->join('users', 'users.user_id = rank.user_id')
                ->where($condition)
                ->count();
        $page = new Page($count, 20);
        $rank_list = M('bonus_rank')->alias('rank')
                ->join('users', 'users.user_id = rank.user_id')
                ->field('rank.*, users.nickname')->where($condition)
                ->limit($page->firstRow, $page->listRows)
                ->order('rank.nums DESC, rank.money DESC, rank.create_time ASC, rank.id ASC')
                ->select();

        $this->assign('page', $page->firstRow);
        $this->assign('pager', $page);
        $this->assign('rank_list', $rank_list);
        return $this->fetch();
    }

    /**
     * 领取产品日志
     */
    public function receive_log()
    {
        $map = $this->search();
        $count = M('bonus_receive_log')->alias('receive')
               ->join('users','users.user_id = receive.leader_id', 'LEFT')
               ->where($map)->count(); 
               
        $page = new Page($count, 20);
        $receive_list = M('bonus_receive_log')->alias('receive')
                ->join('users','users.user_id = receive.leader_id', 'LEFT')
                ->where($map)->field('users.nickname as leader, receive.*')->order('id DESC')
                ->limit($page->firstRow, $page->listRows)->select();

        $this->assign('receive_list', $receive_list);
        $this->assign('pager', $page);
        return $this->fetch();
    }

    /**
     * 奖金池奖励明细日志
     */
    public function bonus_log()
    {
        $map = $this->search();
        $count = M('bonus_log')->alias('bonus')->join('users','users.user_id = bonus.user_id')
               ->where($map)->count(); 
        $page = new Page($count, 20);
        $bonus_list = M('bonus_log')->alias('bonus')->join('users','users.user_id = bonus.user_id')->where($map)
                ->field('users.nickname, bonus.*')->order('id DESC')->limit($page->firstRow, $page->listRows)
                ->select();
        $this->assign('bonus_list', $bonus_list);
        $this->assign('pager', $page);
        return $this->fetch();
    }

    //搜索条件
    public function search()
    {
        $timegap = urldecode(I('timegap'));
        $search_type = I('search_type');
        $search_value = I('search_value');
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['create_time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($search_value) {
            if($search_type == 'account'){
                $map['users.mobile|users.email'] = array('like', "%$search_value%");
            }else{
                $map['users.'.$search_type] = array('like', "%$search_value%");
            }
            
            $this->assign('search_type', $search_type);
            $this->assign('search_value', $search_value);
        }

        return $map;
    }
}