<?php
/**
 * The Upload class
 * @author Alex Lee <iuyes@qq.com>
 * @license http://opensource.org/licenses/BSD-3-Clause New BSD License
 * @version svn:$Id: Upload.php 2013-11-20 22:12:32Z iuyes@qq.com $
 */

/**
 * @category runtime
 * @package   Lotusphp\Upload
 */
 
error_reporting(0); //屏蔽所有错误信息

class LtUpload
{
	/** @var LtConfig config handle */
	public $configHandle;
	
	/** @var array config */
	public $conf;
	public $confgroup;
	//getimagesize + $_FILES值
	public  $getData = '';
	private $dir_sep = DIRECTORY_SEPARATOR;
	private $err =  array('fileEmpty'=>'上传的文件为空','fileIllegal'=>'上传文件非法','filePostfixIllegal'=>'上传文件格式非法','fileSizeIllegal'=>'上传文件大小非法','fileFailure'=>'上传文件失败','fileSizeToosmall'=>'图片像素太小','fileLayer'=>'分割文件名宽度超出范围','fileCreateDir'=>'创建目录没有权限');

	/**
	 * construct
	 */
	public function __construct()
	{
		if (! $this->configHandle instanceof LtConfig)
		{
			if (class_exists("LtObjectUtil", false))
			{
				$this->configHandle = LtObjectUtil::singleton("LtConfig");
			}
			else
			{
				$this->configHandle = new LtConfig;
			}
		}
	}

	/**
	 * init
	 */
	public function init()
	{
		$this->confgroup = $this->configHandle->get("upload");
		if (empty($this->confgroup['default']))
		{
			//详细配置信息(默认配置)
			//默认只允许上传gif、jpeg、png、bmp
			$this->confgroup['default']['fileType'] = 'image'; //类型为图片,参数为file则为只上传 
			$this->confgroup['default']['cutting'] = FALSE;    //是否需要切图
			$this->confgroup['default']['cutType'] = 1;    //切图方式，1：等比缩放，2：
			$this->confgroup['default']['cutSize'] = array(array('width'=>0,'height'=>0));       //默认等比例缩微切割宽度
			$this->confgroup['default']['allowType'] = array('gif','jpeg','jpg','png');  //可以上传的图片类型
			$this->confgroup['default']['checkType'] = TRUE;   //是否检查图片格式
			$this->confgroup['default']['checkSize'] = TRUE;   //是否检查图片大小
			$this->confgroup['default']['maxSize'] = 2097152;  //上传最大值, 单位字节
			$this->confgroup['default']['pathFormat'] ='/upload/image/{yyyy}{mm}/{dd}/{time}{rand:6}';
			
			$this->conf = $this->confgroup['default'];
		}
		else
		{
			$this->conf = $this->confgroup['default'];
		}
	}

	public function setUpload($configName)
	{
		$this->confgroup[$configName]? $this->conf = $this->confgroup[$configName] : $this->conf = $this->confgroup['default'];
	}
	
	//文件原图上传
	public function put($arr_files = array()) {
		$this->getData = & $arr_files;
		
		//判断是否为空
		if(!isset($this->getData['tmp_name']) || !isset($this->getData['name']) || empty($this->getData['tmp_name']) || empty($this->getData['name'])) {
			$this->halt($this->err['fileEmpty']);
		}
		
		//判断是否上传失败
		if(isset($this->getData['error']) && $this->getData['error']) {
			$this->halt($this->err['fileFailure']);
		}

		if(is_uploaded_file($this->getData['tmp_name'])) {
			$this->checkSafe();
			$this->createNewPath();
			$this->createDir();

			//图片或者文件都可以上传
			return $this->doUpload() ? $this->getData : $this->err['fileFailure'];
		}
	}

	/**
	 * 文件上传开始
	 *
	 * @param  <string> tmp_name
	 * @param  <string> path, new_path
	 * @return <boolean>
	 */
	private function doUpload() {
		if(move_uploaded_file($this->getData['tmp_name'], $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$this->getData['full_path'])) {
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * 判断上传文件大小是否合法
	 *
	 * @param  <number> size文件大小值
	 * @return <boolean>
	 */
	private function checkSize() {
		if($this->conf['checkSize'] && $this->getData['size'] > $this->conf['maxSize']) {
			return FALSE;
		}
		return TRUE;
	}
	
	/**
	 * 取得文件名后缀
	 *
	 * @param  <string>  name
	 * @return <string>	返回后缀名称
	 */
	private function getPostfix() {
		return strtolower(trim(substr(strrchr($this->getData['name'], '.'), 1, 10)));
	}
	
	/**
	 * 检查文件是否为规定上传类型
	 *
	 * @param  <string> name
	 * @return <boolean>
	 */
	private function checkFormat() {
		if($this->conf['checkType'] && !in_array($this->getPostfix(), $this->conf['allowType'])) {
			return FALSE;
		}
		return TRUE;
	} 
	
	/**
	 * 创建分隔目录
	 *
	 * @param  <string> path配置目录, new_path完整目录
	 */
	private function createDir() {
		$realpath=$_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$this->getData['full_path'];
		if(!file_exists($path = dirname($realpath))) {
			if(!@mkdir($path, 0777, TRUE)) {
				return $this->err['fileCreateDir'];
			}
		}
	}
	
	/**
	 * $autoDir 如果为假, 目录分割功能关闭
	 *
	 * @param  <string> new_name, new_dir, new_path
	 * @return <string> new_dir, new_path
	 */
	private function createNewPath() {
		
		$t = time();
        $d = explode('-', date("Y-y-m-d-H-i-s"));
        $format = $this->conf["pathFormat"];
        $format = str_replace("{yyyy}", $d[0], $format);
        $format = str_replace("{yy}", $d[1], $format);
        $format = str_replace("{mm}", $d[2], $format);
        $format = str_replace("{dd}", $d[3], $format);
        $format = str_replace("{hh}", $d[4], $format);
        $format = str_replace("{ii}", $d[5], $format);
        $format = str_replace("{ss}", $d[6], $format);
        $format = str_replace("{time}", $t, $format);

        //替换随机字符串
        $randNum = rand(1, 10000000000) . rand(1, 10000000000);
        if (preg_match("/\{rand\:([\d]*)\}/i", $format, $matches)) {
            $format = preg_replace("/\{rand\:[\d]*\}/i", substr($randNum, 0, $matches[1]), $format);
        }
        $this->getData['full_path']= $format.'.'.$this->getPostfix();
	}

	/**
	 * 图片有效性检测
	 *
	 * @param   <string> fullPath 完整全路径地址
	 * @return  <string> width图片宽度, height图片高度, img_type_number图片数字类型
	 */
	private function checkSafe() {
		if(!$this->checkFormat()) {
			$this->halt($this->err['filePostfixIllegal']);
		}

		if(!$this->checkSize()) {
			$this->halt($this->err['fileSizeIllegal']);
		}

		$this->getImage();
	}
	
	/**
	 * 取得图片的长、宽、类型
	 *
	 * @param   <string> fullPath 完整全路径地址
	 */
	private function getImage($fullPath = '') {
		if ($this->conf['fileType'] == 'image') {
			list($this->getData['width'], $this->getData['height'], $this->getData['img_type_number']) = getimagesize($fullPath ? $fullPath : $this->getData['tmp_name']);
			if(!in_array($this->getData['img_type_number'], array(1,2,3))) {
				$this->halt($this->err['filePostfixIllegal']);
			}
		}
	}
		
	/**
     * 生成等比例缩微图thumb
	 *
	 * @return <boolean>   TRUE 成功, FALSE 失败
     */
	public function putThumb() {
		$filePath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$this->getData['full_path'];

		if($filePath && $this->conf['fileType'] == 'image' && $this->conf['cutting'] && @file_exists($filePath)) {

			if(!isset($this->getData['width']) || !isset($this->getData['height'])) {
				$this->getImage($filePath);
			}
			
			if(!in_array($this->getData['img_type_number'], array(1,2,3))) {
				return $this->err['filePostfixIllegal'];
			}

			switch($this->getData['img_type_number']) {
				case 1:
					$im = imagecreatefromgif($filePath);
				break;
				case 2:
					$im = imagecreatefromjpeg($filePath);
				break;
				case 3:
					$im = imagecreatefrompng($filePath);
				break;
			}

			if(!$im) {
				return FALSE;
			}
			
			
			foreach($this->conf['cutSize'] as $img){
				$imgWidth=$img['width'];
				$imgHeight=$img['height'];
				$filename=$img['width'].'-'.$img['height'];
				
				if($img['width'] > $this->getData['width'] && $img['width']>0){$img['width'] =  $this->getData['width'];}
				if($img['height'] > $this->getData['height'] && $img['height']>0){ $img['height']= $this->getData['height'];}
				
				$width = $img['width']; 
				$height = $img['height'];

				
				if ($img['width']>0 && ($this->getData['width'] < $this->getData['height'])) {
					$width = ($img['height'] / $this->getData['height']) * $this->getData['width'];
				} else {
					$height = ($img['width'] / $this->getData['width']) * $this->getData['height'];
				}
	
				if (function_exists("imagecreatetruecolor")) {
					
					if($this->conf['cutType'] ==1 ){
						$new = imagecreatetruecolor($width, $height);
						
						$this->getData['img_type_number'] == 3 && $this->transparent($new);
						imagecopyresampled($new, $im, 0, 0, 0, 0, $width, $height, $this->getData['width'], $this->getData['height']);
					}else{
						$dst_x = 0;
						$dst_y = 0;
						if ( ($imgWidth/$imgHeight - $width/$height) > 0 ) {
							$dst_x = ( $imgWidth - $width ) / 2;
						} elseif ( ($imgWidth/$imgHeight - $width/$height) < 0 ) {
							$dst_y = ( $imgHeight - $height ) / 2;
						}
						
						$new = imagecreatetruecolor($imgWidth, $imgHeight);
						$color = imagecolorallocate($new, hexdec(substr($this->conf['bgcolor'], 1, 2)), hexdec(substr($this->conf['bgcolor'], 3, 2)), hexdec(substr($this->conf['bgcolor'], 5, 2)));
						imagefill($new, 0, 0, $color);
						imagecopyresampled($new, $im, $dst_x, $dst_y, 0, 0, $width, $height, $this->getData['width'], $this->getData['height']);
					}
					
					
				} else {
					$new = imagecreate($width, $height);
					if($this->conf['cutType'] ==1 ){
						$new = imagecreatetruecolor($width, $height);
						$this->getData['img_type_number'] == 3 && $this->transparent($new);
						imagecopyresampled($new, $im, 0, 0, 0, 0, $width, $height, $this->getData['width'], $this->getData['height']);
					}else{
						$dst_x = 0;
						$dst_y = 0;
						if ( ($imgWidth/$imgHeight - $width/$height) > 0 ) {
							$dst_x = ( $imgWidth - $width ) / 2;
						} elseif ( ($imgWidth/$imgHeight - $width/$height) < 0 ) {
							$dst_y = ( $imgHeight - $height ) / 2;
						}
						$new = imagecreatetruecolor($imgWidth, $imgHeight);
						$color = imagecolorallocate($new, hexdec(substr($this->conf['bgcolor'], 1, 2)), hexdec(substr($this->conf['bgcolor'], 3, 2)), hexdec(substr($this->conf['bgcolor'], 5, 2)));
						imagefill($new, 0, 0, $color);
						imagecopyresampled($new, $im, $dst_x, $dst_y, 0, 0, $width, $height, $this->getData['width'], $this->getData['height']);
					}
				}
	
				$newFilePath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$this->getData['full_path']. '.' . $filename;
	
				switch ($this->getData['img_type_number']) {
					case 1:
						imagegif($new, $newFilePath. '.gif');
					break;
					case 2:
						imagejpeg($new, $newFilePath. '.jpg', 100);
					break;
					case 3:
						imagepng($new, $newFilePath. '.png');
					break;
				}
				unset($filePath,$filename, $newFilePath, $width, $height);
				
			}
			imagedestroy($new); imagedestroy($im);
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * PNG透明背景图片处理
	 *
	 * @param  <resource> new 资源
	 * @param  <int> transparent 如果分配失败则返回 FALSE
	 */	
	private function transparent($new) {
		$transparent = imagecolorallocatealpha($new , 0 , 0 , 0 , 127);
		imagealphablending($new , false);
		imagefill($new , 0 , 0 , $transparent);
		imagesavealpha($new , true);
	}

	//格式化错误输出
	private function halt($message) {
		echo $message;
		exit();
	}
}
