<?php
//获取 支付编号 支付信息
function alipay_get_pay_info($trade_no){
	include('aop/AopClient.php');
	include('aop/SignData.php');
	include('aop/request/AlipayTradeQueryRequest.php');
	$aop = new AopClient;
	$request = new AlipayTradeQueryRequest();
	$bizcontent = json_encode(['trade_no'=>$trade_no,]);
	$request->setBizContent($bizcontent);
	$result = $aop->execute($request); 
	var_dump($result);
}
 
//订单 退款
function alipay_refund($trade_no,$order_amount){
	include('aop/AopClient.php');
	include('aop/SignData.php');
	include('aop/request/AlipayTradeRefundRequest.php');
	$aop = new AopClient;
	$request = new AlipayTradeRefundRequest();
	$bizcontent = json_encode(['trade_no'=>$trade_no,'refund_amount'=>$order_amount]);
	$request->setBizContent($bizcontent);
	$result = $aop->execute($request); 
	//var_dump($result);
}
