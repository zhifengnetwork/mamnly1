<?php


namespace app\common\behavior;
use app\common\logic\wechat\WechatUtil;
use think\Db;
class Order
{
    public function userAddOrder(&$order)
    {

        $time = date('Y-m-d H:i:s',time());
        // 记录订单操作日志
        $action_info = array(
            'order_id'        =>$order['order_id'],
            'action_user'     =>0,
            'action_note'     => '您提交了订单，请等待系统确认',
            'status_desc'     =>'提交订单', //''
            'log_time'        =>time(),
        );
        Db::name('order_action')->add($action_info);

        //分销开关全局
        // $distribut_switch = tpCache('distribut.switch');
        // if ($distribut_switch == 1 && file_exists(APP_PATH . 'common/logic/DistributLogic.php')) {
        //     $distributLogic = new \app\common\logic\DistributLogic();
        //     $distributLogic->rebateLog($order); // 生成分成记录
        // }

        // 如果有微信公众号 则推送一条消息到微信.微信浏览器才发消息，否则下单超时。by清华
        if(is_weixin()){

            $user = Db::name('users')->where(['user_id'=>$order['user_id']])->field('openid,first_leader')->find();
            
            if($user['openid']){
                $goods = Db::name('OrderGoods')->where(['order_id'=>$order['order_id']])->select();
                $text = '';
                foreach ($goods as $key => $value) {
                    $text .= $value['goods_name'].'(规格：'.$value['spec_key_name'].',数量：'.$value['goods_num'].',价格：'.$value['final_price'].');';
                }
                // $wx_content = "您的订单已提交成功！\n\n店铺：丝蒂芬妮娅\n下单时间：{$time}\n商品：{$text}\n金额：{$order['total_amount']}\n\n您的订单我们已经收到，支付后我们将尽快配送~~";
                
                // $wechat = new WechatUtil();
                // $wechat->sendMsg($user['openid'], 'text', $wx_content);
                
                // if($order['total_amount'] > 10){
                //     $fanli = '支付后可获得返利~~';
                // }

                // $first_leader_openid = Db::name('users')->where(['user_id' => $user['first_leader']])->value('openid');
                // if($first_leader_openid){
                //     $nickname = Db::name('users')->where(['openid'=>$user['openid']])->value('nickname');
                //     $first_leader_wx_content = "您的直属【{$nickname}】已提交订单成功！\n\n店铺：丝蒂芬妮娅\n下单时间：{$time}\n商品：{$text}\n\n".$fanli;
                //     $wechat->sendMsg($first_leader_openid, 'text', $first_leader_wx_content);
                // }
            }
        }

        //用户下单, 发送短信给商家
        $res = checkEnableSendSms("3");
        if($res && $res['status'] ==1){
            $sender = tpCache("shop_info.mobile");
            $params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
            sendSms("3", $sender, $params);
        }
    }

}