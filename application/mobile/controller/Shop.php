<?php

namespace app\mobile\controller;

use think\Db;
use think\db\Query;

class Shop extends MobileBase
{
    /**
     * 用户个人自提过商品的门店列表
     */
    public function shop_list()
    {
        /*$orderList = Db::query("select distinct o.shop_id,s.shop_name from tp_order as o
                      inner join tp_shop as s on s.shop_id = o.shop_id  where o.user_id = ".cookie('user_id'));*/
        $shopList = Db::name('order')->alias('o')
            ->join('tp_shop s','s.shop_id = o.shop_id','inner')
            ->field('distinct o.shop_id,s.shop_name')
            ->where(['o.user_id'=>cookie('user_id')])
            ->select();
        if(!empty($shopList)){
            foreach ($shopList as $key => $value){

                /*echo "<pre>";
                print_r($value);
                echo "</pre>";
                continue;*/
                $shopGoodsList = Db::name('shop_goods')->alias('sg')
                    ->join('tp_goods g','g.goods_id = sg.goods_id','inner')
                    //->field('distinct o.shop_id,s.shop_name')
                    ->where(['sg.shop_id'=>$value['shop_id']])
                    ->select();
                $shopList[$key]['item'] = $shopGoodsList;
            }
        }

        /*echo "<pre>";
        print_r($shopList);
        echo "</pre>";
        exit;*/
        $this->assign('shop_title', '门店管理');
        $this->assign('shopList', $shopList);
        return $this->fetch();
    }

    /**
     * 用户个人成为核销员后自提核销功能
     */
    public function shop_order()
    {
        /*$topic_id = I('topic_id/d', 1);
        $topic = Db::name('topic')->where("topic_id", $topic_id)->find();
        $this->assign('topic', $topic);*/
        return $this->fetch();
    }

    public function getShopData () {
        $res = model('DiyEweiShop')->getShopData();
        if (!empty($res)){
            return json(['code'=>1,'msg'=>'','data'=>$res]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据，请添加','data'=>'']);
        }
        
    }

    public function gooodsList () {
        $keyword  = request()->param('keyword','');
        $cat_id   = request()->param('cat_id',0,'intval');
        $page     = request()->param('page',0,'intval');
        $goods    = new Goods();
        $list     = $goods->getGoodsList($keyword,$cat_id,$page);
        if (!empty($list)){
            return json(['code'=>1,'msg'=>'','data'=>$list]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据哦','data'=>$list]);
        }
    }

    public function getGoodsData () {
        $goods_id = request()->param('goods_id',0,'intval');
        $data = model('Goods')->where('goods_id',$goods_id)->find();
        $sku =  Db::table('goods_sku')->where('goods_id',$data['goods_id'])->select();
        $data['sku'] = $sku;
        return json(['code'=>1,'msg'=>'','data'=>$data]);
    }


    public function shop_order_info()
    {
        $shopOrderList = Db::name('shop_order')->alias('so')
            ->join('tp_order o','o.order_id = so.order_id','inner')
            //->field('o.shop_id,s.shop_name')
            ->where(['so.shop_order_id'=>I('post.write_off_code/d')])
            ->select();
        if ($shopOrderList) {
            $this->assign('shopList', $shopOrderList);
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        } else {
            $this->ajaxReturn(['status' => -1, 'msg' => '您好,您的提货码错误,请重新输入']);
        }
    }

    /**
     * 用户个人成为核销员后自提核销功能
     */
    public function search_order()
    {
        print_r(4444);
        /*$topic_id = I('topic_id/d', 1);
        $topic = Db::name('topic')->where("topic_id", $topic_id)->find();
        $this->assign('topic', $topic);*/
        return $this->fetch();
    }
}