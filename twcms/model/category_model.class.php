<?php
/**
 * (C)2012-2013 twcms.cn TongWang Inc.
 * Author: wuzhaohuan <kongphp@gmail.com>
 */

defined('TWCMS_PATH') or exit;

class category extends model {
	private $data = array();		// 防止重复查询

	function __construct() {
		$this->table = 'category';	// 表名
		$this->pri = array('cid');	// 主键
		$this->maxid = 'cid';		// 自增字段
	}

	// 检查基本参数是否填写
	public function check_base(&$post) {
		if(empty($post['mid'])) {
			$name = 'mid';
			$msg = '请选择分类模型';
		}elseif(!isset($post['type'])) {
			$name = 'type';
			$msg = '请选择分类属性';
		}elseif(!isset($post['upid'])) {
			$name = 'upid';
			$msg = '请选择所属频道';
		}elseif(empty($post['name'])) {
			$name = 'name';
			$msg = '请填写分类名称';
		}elseif(empty($post['alias'])) {
			$name = 'alias';
			$msg = '请填写分类别名';
		}elseif(strlen($post['alias']) > 50) {
			$name = 'alias';
			$msg = '分类别名不能超过50个字符';
		}elseif(!preg_match('/^\w+$/', $post['alias'])) {
			$name = 'alias';
			$msg = '分类别名只能是 英文 数字 _';
		}elseif(empty($post['cate_tpl'])) {
			$name = 'cate_tpl';
			$msg = '请填写分类页模板';
		}elseif($post['mid'] > 1 && empty($post['show_tpl'])) {
			$name = 'show_tpl';
			$msg = '请填写内容页模板';
		}
		return empty($msg) ? FALSE : array('name' => $name, 'msg' => $msg);
	}

	// 检查别名是否被使用
	public function check_alias($alias) {
		if($this->find_fetch_key(array('alias'=> $alias))) {
			$msg = '分类别名已经被使用';
		}elseif($err_msg = $this->only_alias->check_alias($alias)) {
			$msg = $err_msg;
		}
		return empty($msg) ? FALSE : array('name' => 'alias', 'msg' => $msg);
	}

	// 检查是否符合修改条件
	public function check_is_edit($post, $data) {
		if($post['cid'] == $post['upid']) {
			$name = 'upid';
			$msg = '所属频道不能修改为自己';	// 暂时不考虑 upid 不能为自己的下级分类或非频道分类
		}elseif($post['mid'] != $data['mid'] && $data['count'] > 0) {
			$name = 'mid';
			$msg = '分类中有内容，不允许修改分类模型，请先清空分类内容';
		}elseif($post['type'] != $data['type'] && $data['count'] > 0) {
			$name = 'type';
			$msg = '分类中有内容，不允许修改分类属性，请先清空分类内容';
		}
		return empty($msg) ? FALSE : array('name' => $name, 'msg' => $msg);
	}

	// 检查是否符合删除条件
	public function check_is_del($data) {
		if($data['type'] == 1 && $this->find_fetch_key(array('upid' => $data['cid']), array(), 0, 1)) {
			return '分类有下级分类，请先删除下级分类';
		}elseif($data['count'] > 0) {
			return '分类中有内容，请先删除内容';
		}
		return FALSE;
	}

	// 获取分类 (树状结构)
	public function get_category_tree() {
		if(isset($this->data['category_tree'])) {
			return $this->data['category_tree'];
		}

		$tmp = $this->find_fetch(array(), array('orderby'=>1));

		// 重建数组
		$data = array();
		foreach($tmp as $v) {
			$data[$v['cid']] = $v;
		}

		// 格式化为树状结构 (会舍弃不合格的结构)
		// foreach($data as $v) {
		// 	$data[$v['upid']]['son'][$v['cid']] = &$data[$v['cid']];
		// }
		// $this->data['category_tree'] = $data[0]['son'];

		// 格式化为树状结构 (不会舍弃不合格的结构)
		$this->data['category_tree'] = array();
		foreach($data as $v) {
			if(isset($data[$v['upid']])) $data[$v['upid']]['son'][] = &$data[$v['cid']];
			else $this->data['category_tree'][] = &$data[$v['cid']];
		}

		return $this->data['category_tree'];
	}

	// 获取分类 (二维数组)
	public function get_category() {
		if(isset($this->data['category_array'])) {
			return $this->data['category_array'];
		}

		$arr = $this->get_category_tree();
		return $this->data['category_array'] = $this->to_array($arr);
	}

	// 递归转换为二维数组
	function to_array($arr, $pre = 1) {
		static $arr2 = array();

		foreach($arr as $k => $v) {
			$v['pre'] = $pre;
			if(isset($v['son'])) {
				$son_arr = $v['son'];
				unset($v['son']);
				$arr2[$v['mid']][] = $v;
				self::to_array($son_arr, $pre+1);
			}else{
				$arr2[$v['mid']][] = $v;
			}
		}

		return $arr2;
	}

	// 获取所有分类 (内容发布时使用)
	public function get_category_cid($cid = 0) {
		$category_arr = $this->get_category();
		$mod_arr = $this->models->get_mod_arr();

		$s = '<select name="cid" id="cid">';
		$s .= '<option value="0">全部内容</option>';
		foreach($category_arr as $mid => $arr) {
			// 不显示单页
			if($mid == 1) continue;

			$s .= '<option disabled="disabled" style="background:#999;color:#fff">'.$mod_arr[$mid].'分类</option>';
			foreach ($arr as $v) {
				$disabled = $v['type'] == 1 ? ' disabled="disabled"' : '';
				$s .= '<option value="'.$v['cid'].'"'.($v['type'] == 0 && $v['cid'] == $cid ? ' selected="selected"' : '').$disabled.'>';
				$s .= str_repeat("　", $v['pre']);
				$s .= '|─'.$v['name'].($v['type'] == 1 ? '[频道]' : '').'</option>';
			}
		}
		$s .= '</select>';
		return $s;
	}

	// 获取上级分类的 HTML 代码 (只显示频道分类)
	public function get_category_upid($mid, $upid = 0, $noid = 0) {
		$category_arr = $this->get_category();

		$s = '<option value="0">顶级分类</option>';
		if(isset($category_arr[$mid])) {
			foreach($category_arr[$mid] as $v) {
				// 不显示列表的分类
				if($mid> 1 && $v['type'] == 0) continue;

				// 当 $noid 有值时，排除等于它和它的下级分类
				if($noid) {
					if(isset($pre)) {
						if($v['pre'] > $pre) continue;
						else unset($pre);
					}
					if($v['cid'] == $noid) {
						$pre = $v['pre'];
						continue;
					}
				}

				$s .= '<option value="'.$v['cid'].'"'.($v['cid'] == $upid ? ' selected="selected"' : '').'>';
				$s .= str_repeat("　", $v['pre']-1);
				$s .= '|─'.$v['name'].'</option>';
			}
		}

		return $s;
	}
}