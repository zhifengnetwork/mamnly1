<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/24
 * Time: 10:23
 */

namespace app\admin\controller;

use app\common\logic\wechat\ErrorCode;
use think\Db;
class AlipayTransfer extends Base
{
    private $appId;    //appid
    private $rsaPrivateKey;    //私钥
    private $alipayrsaPublicKey;   //支付宝公钥
    public function __construct()
    {
        $this->configPay();
        //引入单笔转账sdk
    }

    public function test () {
        $str = 'check sign Fail! [sign=kSB3Vbj58TfD+zTrZyHtmPpWlYnallMGygzFtK/IyzDBr1F9Jq/y3FEuRDcPj8l5XEuD3u8AonSGrr7BrmjtyhpAlRkbnuqwwmfSPMirgsY0zsABq1WemJFxA340FxqQRkpzf5T77Rql5sgKc+jY0hXPq5I/OyM4/V6Bphjgn215lKoR/A2CLowNBurzPYzXteRd0iqYk1sqtP1U7V2XyX6zXMwzGlRi2RNfMRMG6hoBZeRan7b9Ei7ILZfZJm5s/PSYe71A5+SFSi6PC3WjjSIAlflLjftBPVZXLtv6ccAM+6P6P4fbl7JWU09HjpWcplyFSjgGhAYKvCZNvlrdKw==, signSourceData={"code":"40006","msg":"Insufficient Permissions","sub_code":"isv.insufficient-isv-permissions","sub_msg":"ISV权限不足，建议在开发者中心检查应用是否上线"}]';
        $arr = explode('signSourceData=',$str);
        if (isset($arr[1])){
            $json = explode(']',$arr[1]);
            $res = json_decode($json[0]);
            dump($res);
        }
    }

    private function configPay () {
        $this->appId = '2019041663926129';
        $this->rsaPrivateKey = 'MIIEpQIBAAKCAQEA6nlSMja77DHkO3YMRY43ySw0KkPWY5/LUyvDSI2AH3lkJfsCMcEYcUpyJnczce6EPAVSgS+vZ9s6IWy3dzhphO7fw7DLhGjrJp+EV8ZKzA9g3VT2CsuyqJHSlgcSwGaZ5f8WWzFHfCnszgs5w0qIu+keX2Fz4qGybN8dFxAvCY+LPcLCahOFOmRmp1uw7bgn6UWKYBOJPkmVBOraun2hr3CLWu2PqKpIrKx5hWgZNX3LIYifUENz0VFAwDl4Flg3mLncy6Jsa4R0kSP6ehwAbYWk0ESSv5FiiA5SCJEHNmFgLWczEMGm48efs1t0YbMrwJWI0lun8a7PZ/AHLwm85wIDAQABAoIBAHBWVfo23QxJzwZqBXEhtTqOEiQZwlKS0ZB0jChrmvH5b/D+dMuvru1AdLZXL++rDfHPvvqkBQ7mKtCuzKuy/GMzK0QPpUI4Hkmv7XE8UMO5rnf8Z7E+bMd0rgcxNlu2DI/0ChsA3jXvxEPnfvJA+IfHJcUe5K21OM4Oi1psZ4zVLVl34mthLg/4oYkbxexg9INL0HAxEhC0I51PrIupnKCn5NJlcco591sxQEfv9yPWuexrgE41iJk5NNFWy9Y/mmrKvwYyqSmNplTYZtHGbf9Vm1uDkX2vBVbDg6Mz1qTvnds2cLJPYWXMG5ECx2QjPl2l9Cofg9qguOLM/NROlCkCgYEA+hymTabBpKbLvHatv3+Pbjhrg/qnW7ieFHctIoWf0t1ay4w179fREr4T4k4llmjiEvHNaOCzBrF81IrY640h+H2rkZ91+df1N03my72IWpIfkz+ezaDO0/DZxJFIrA14fA3BkxhFiEzC7Ghx+jeRNFCcKasvh/ex6l7BMazwYM0CgYEA7/5tBJ9qrwEafbV1cRD2MXM2G8o5QmuipTCt0GC2cTZhZfD9qHokWFpsoiGgp4nw9lxqRUj13niYLczO7K94jwDLFK1xqieOfXsU+V1lJwdng6SAQebVYeOJOkpB185/YhlVg1Sr7orGwhLCNV6C5zJ/NwXP+GZrdHxhJmSZBIMCgYEAsPPaGTA06qfzlvgkP0shkCqsrqiFBZidhv82WKlPhSGE3mPpuTHowqjmaoM9hqfX4u1elaf8IW0rUziU9jpY4XUQEKxQDJ7k5+betiD3OpUNb+FgGj1+d2Z8u9zKHKg/KQ2Wedp/P0qH0jinAw+TVP7/LV/m9fyhzJ6TcvDW9LUCgYEAtfK4iCasZR17Dg9CiIQJgpgMT6lTG+4qkv6C6FZKOy61TOoWBWMEpw93CLxh5mMIEl8iGoEkFpRrG14JCxxFVHWPgY+1ewEeYDeuQRfzllFgw0c2DcCJyfsNkOm3XXuqy57VXAoXh3QjGAPMxVVv/QQluntnnrVXhiq+JLNj5y0CgYEAtJe/n+mZBITtgCGyO0yNdaE3fI0I+cp7A/aD7FG8V81t5gPxKRNx5+nRySLR/MDZ4WeoIzNyuUckdWV51xPRtDa5QqLo7hgiyzlvdohywzozj6iY7lZimEMJbwK7c5yP64Gj9j3Qapvq6KLzh1q2pc5ILs0gkWEGnUvIhjV5G/8=';
        $this->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA6nlSMja77DHkO3YMRY43ySw0KkPWY5/LUyvDSI2AH3lkJfsCMcEYcUpyJnczce6EPAVSgS+vZ9s6IWy3dzhphO7fw7DLhGjrJp+EV8ZKzA9g3VT2CsuyqJHSlgcSwGaZ5f8WWzFHfCnszgs5w0qIu+keX2Fz4qGybN8dFxAvCY+LPcLCahOFOmRmp1uw7bgn6UWKYBOJPkmVBOraun2hr3CLWu2PqKpIrKx5hWgZNX3LIYifUENz0VFAwDl4Flg3mLncy6Jsa4R0kSP6ehwAbYWk0ESSv5FiiA5SCJEHNmFgLWczEMGm48efs1t0YbMrwJWI0lun8a7PZ/AHLwm85wIDAQAB';
    }

    public function index () {
        $out_biz_no = time().range(1000,9999);;
        $payee_account = '13226785330';
        $amount = '1.0';
        $payer_show_name = '上海交通卡退款';
        $payee_real_name = '莫敏宏';
        $remark = '沙箱环境';
        $this->pay($out_biz_no,$payee_account,$amount,$payer_show_name,$payee_real_name,$remark);
    }

    public function pay ($out_biz_no,$payee_account,$amount,$payer_show_name,$payee_real_name,$remark) {
        vendor('alipayaop.AopSdk');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->method = 'alipay.fund.trans.toaccount.transfer';
        $aop->appId = $this->appId;
        $aop->rsaPrivateKey = $this->rsaPrivateKey;
        $aop->alipayrsaPublicKey = $this->alipayrsaPublicKey;
        $aop->signType = 'RSA2';
        $aop->charset = 'UTF-8';
        $aop->timestamp = date("Y/m/d H:i:s",time());
        $aop->version = 1.0;
        $request = new \AlipayFundTransToaccountTransferRequest ();
        $request ->setBizContent("{" .
            "\"out_biz_no\":\"'".$out_biz_no."'\"," .
            "\"payee_type\":\"ALIPAY_LOGONID\"," .
            "\"payee_account\":\"'".$payee_account."'\"," .
            "\"amount\":\"'".$amount."'\"," .
            "\"payer_show_name\":\"墨家互娱\"," .
            "\"payee_real_name\":\"'".$payee_real_name."'\"," .
            "\"remark\":\"'".$remark."'\"" .
            "}");
        try {
            $result = $aop->execute($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                echo "成功";
            } else {
                echo "失败";
            }
        } catch (\Exception $e) {
            dump($e->getMessage());
            exit();
        }

    }
}