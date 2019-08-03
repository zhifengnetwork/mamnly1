<?php
/**
 * 签到API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\LevelLogic;
use app\common\logic\UsersLogic;

use think\Db;
use think\Controller;


class Distribut extends Controller
{

    /**
     * api调用 升级
     */
    public function upgrade(){
        $user_id = I('user_id');
        if(!$user_id){
            return 'user_id不存在';
        }

        $top_level = new LevelLogic();
        $top_level->user_in($user_id);
        
    }

    /**
     * 获取团队总人数
     */
    public function aaaaaaaa()
    {
        ini_set('max_execution_time', '0');

        $user_id = I('user_id');
        $all = M('users')->field('user_id,first_leader')->select();
        $res = count($this->get_downline($all,$user_id,0));
        M('users')->where(['user_id'=>$user_id])->update(['underling_number'=>$res]);
        echo $res;
    }


    //获取用户的所有直属ID
    function get_downline($data,$mid,$level=0){
        $arr=array();
        foreach ($data as $key => $v) {
            if($v['first_leader']==$mid){  //pid为0的是顶级分类
                $v['level'] = $level+1;
                $arr[]=$v;
                $arr = array_merge($arr,$this->get_downline($data,$v['user_id'],$level+1));
            }
        }
        return $arr;
    }


    public function aaa(){
        ini_set('max_execution_time', '0');

        $user_id = I('user_id');
        $res = $this->login_service_volume($user_id);

        dump($res);
    }



    function login_service_volume($memberid){
        //echo $uniacid."\\\\<p>";
        $count = 0;
    
        $memberids = M('users')->field('user_id')->where(['first_leader'=>$memberid])->select();

        //echo "<pre>";
        //print_r($memberids);
        //echo "</pre>";
        $total = count($memberids);
        //echo $total."---".$memberid."<p>";
        if(empty($memberids)){
            return $total;
        }else{
            $mun = 0;
            foreach ($memberids as $key => $value){
                $mun += $this->login_service_volume($value['user_id']);
                //echo "===".$i.";;;".$value['id']."<p>";
                /*$id = pdo_fetchcolumn('select count(id) from ' . tablename('sz_yi_member') . ' where agentid=:agentid  and uniacid=:uniacid ', array(':uniacid' => $uniacid, ':agentid' => $value['id']));
                $total += intval($id);*/
                //$mun += $i;
            }
            $total += $count;
        }
        return $total + $mun;
    }

    /**
     * 通过 user_id  查  所有
     */
    public function get_team_num(){
        ini_set('max_execution_time', '0');
        $user_id = I('user_id');
        if(!$user_id){
            echo 0;
            exit;
        }
        $logic = new UsersLogic();
        $num = $logic->get_team_num($user_id);
        echo $num;
    }

}
