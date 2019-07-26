<?php


namespace app\cron\controller;

use think\Controller;
use think\Db;
use app\common\util\Exception;
use app\common\logic\UsersLogic;

class Team extends Controller{

    //季度分红，每3月一次 ，2,5,8,11
    public function quarter_bonus(){
        $AgentPerformance = M('Agent_performance');
        $AgentPerformance->field('performance_id,user_id,team_per')->chunk(100,function($list){

            $Share = M('Share');
            $AgentPerformance = M('Agent_performance');
            $QuarterBonus = M('Quarter_bonus');
            $Users = M('Users');
            foreach($list as $v){
                $ach_pool = $Users->where(['user_id'=>$v['user_id']])->value('ach_pool');
                if(!$ach_pool){
                    $info = $AgentPerformance->field('performance_id',true)->find($v['performance_id']);
                    $info['note'] = $info['note'] ? $info['note'] : '';
                    $info['msg'] = date('Y-m').'未执行季度分红--0';
                    M('Agent_performance')->delete($v['performance_id']);
                    $info['year_m'] = date('Y-m');
                    M('quarter_bonus')->insert($info);
                    continue;
                }
                $n = $QuarterBonus->where(['user_id'=>$v['user_id'],'year_m'=>date('Y-m')])->count();
                if($n)continue;
                $grade = $Share->where(['lower'=>['egt',$v['team_per']]])->order('lower desc')->value('grade');
                if(!$grade)continue;
    
                $price = floor($v['team_per'] * $grade)/100;
    
                //找出用户的下级
                $childlist = $AgentPerformance->alias('ap')->join('users u','ap.user_id=u.user_id','left')->field('ap.performance_id,ap.user_id,ap.team_per')->where('u.first_leader='.$v['user_id'])->select();
                foreach($childlist as $v1){
                    $grade = $Share->where(['lower'=>['egt',$v1['team_per']]])->order('lower desc')->value('grade');
                    if(!$grade)continue;  
                    $price1 = floor($v1['team_per'] * $grade)/100;  
                    $price -= $price1;
                }
    
                $this->writeLog($v['user_id'],$price,date('Y-m').'季度分红',72,$v['performance_id']);
            }  
            //M()->query('TRUNCATE tp_agent_performance');
        });
    }

	//记录日志
	private function writeLog($userId,$money,$desc,$states,$id)
	{
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'states'=>$states
        );
        
        Db::startTrans();
		$bool = $money ? M('account_log')->insert($data) : 1;


		if($bool){
            M('Users')->where('user_id',$userId)->setInc('user_money',$money);
            $info = M('Agent_performance')->field('performance_id',true)->find($id);
            $info['note'] = $info['note'] ? $info['note'] : '';
            $info['msg'] = date('Y-m').'已执行季度分红';
            M('Agent_performance')->delete($id);
            $info['year_m'] = date('Y-m');
            M('quarter_bonus')->insert($info);
            Db::commit(); 
		}else{
            Db::rollback();
        }
		
	}    

}