<?php
namespace app\common\logic;

use think\Model; 
use think\Db;

/**
 * 关系
 */
class GuanxiLogic extends Model
{
	/**
	 * 获取 两个 ID 之间的 级别关系
	 */
	public function get_shangxiaji_guanxi($shangji,$xiaji)
	{
		if($shangji == $xiaji){
			return '自购';
		}
		//自购返利

		$jibie = 1;
		
		/** 15级 就差不多了 （大于10级显示  十级以上 ）实际上大于4级就不返利了 */
		for($i=0;$i<=15;$i++){
			
			$shangji_temp = M('users')->where(['user_id'=>$xiaji])->value('first_leader');
			if($shangji_temp == $shangji){
			
				break;
			}else{
				$jibie +=1;
				$xiaji = $shangji_temp;
			}

		}

		switch ($jibie) {
			case 1:
				$jibie_name = '一类';
				break;
			case 2:
				$jibie_name = '二类';
				break;
			case 3:
				$jibie_name = '三类';
				break;
			case 4:
				$jibie_name = '四类';
				break;
			case 5:
				$jibie_name = '五类';
				break;
			case 6:
				$jibie_name = '六类';
				break;
			case 7:
				$jibie_name = '七类';
				break;
			case 8:
				$jibie_name = '八类';
				break;
			case 9:
				$jibie_name = '九类';
				break;
			case 10:
				$jibie_name = '十类';
				break;
		
			
			default:
				$jibie_name = '十类以上';
				break;
		}

		return $jibie_name;
	}
}