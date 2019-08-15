<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Users extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function oauthUsers()
    {
        return $this->hasMany('OauthUsers', 'user_id', 'user_id');
    }

    public function userLevel()
    {
        return $this->hasOne('UserLevel', 'level', 'level');
    }

    /**
     * 用户下线分销金额
     * @param $value
     * @param $data
     * @return float|int
     */
    public function getRebateMoneyAttr($value, $data){
        $sum_money = DB::name('rebate_log')->where(['status' => 3,'user_id'=>$data['user_id']])->sum('money');
        $rebate_money = empty($sum_money) ? (float)0 : $sum_money;
        return  $rebate_money;
    }

    /**
     * 用户直属下线数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFisrtLeaderNumAttr($value, $data){
        $fisrt_leader = Users::where(['first_leader'=>$data['user_id']])->count();
        return  $fisrt_leader;
    }

    /**
     * 用户二级下线数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getSecondLeaderNumAttr($value, $data){
        $second_leader = Users::where(['second_leader'=>$data['user_id']])->count();
        return  $second_leader;
    }

    /**
     * 用户二级下线数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getThirdLeaderNumAttr($value, $data){
        $third_leader = Users::where(['third_leader'=>$data['user_id']])->count();
        return  $third_leader;
    }

    // 水光面膜
    public function getSignFreeAttr($value, $data){
        $sign_free_data = Db::name('goods')->where(['goods_id' => 59])->value('sign_free_data');
        $sign_free = json_decode($sign_free_data,true);
        $count = 0;
        if(isset($sign_free['month_max'][$data['level']])){
            $month_max = $sign_free['month_max'][$data['level']];
            $free_count = Db::name('sign_receive_log')->where([
                'user_id' => $data['user_id'],
                'goods_id' => 59,
                'create_time' => ['like', date('Y-m', time()) . '%']
            ])->value('sum(num) as sum')?:0;
            $count = ($month_max-$free_count>-1)?$month_max-$free_count:0;
        }
        return $count;
    }

    // 修复面膜
    public function getSignHehuoFreeAttr($value, $data){
        $sign_free_data = Db::name('goods')->where(['goods_id' => 58])->value('sign_free_data');
        $sign_free = json_decode($sign_free_data,true);
        $count = 0;
        if(isset($sign_free['month_max'][$data['level']])){
            $month_max = $sign_free['month_max'][$data['level']];
            $free_count = Db::name('sign_receive_log')->where([
                'user_id' => $data['user_id'],
                'goods_id' => 58,
                'create_time' => ['like', date('Y-m', time()) . '%']
            ])->value('sum(num) as sum')?:0;
            $count = ($month_max-$free_count>-1)?$month_max-$free_count:0;
        }
        return $count;
    }


}
