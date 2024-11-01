<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Cache_Class
{
	static private $instance = null;

	static $scripts = array();
	static $styles  = array();
	static $script_file = 'cache/site_script.js';
	static $style_file  = 'cache/site_css.css';
	static $option = array();
	static $needupdate = false;
	static $has_run = false;


	public function __construct()
	{
        global $tr_cache_options;
		if (function_exists('domain_mapping_siteurl')) {
			define('TR_WP_SITE_URL',domain_mapping_siteurl());
			define('TR_WP_CONTENT_URL',str_replace(get_original_url(TR_WP_SITE_URL),TR_WP_SITE_URL,content_url()));
		} else {
			define('TR_WP_SITE_URL',site_url());
			define('TR_WP_CONTENT_URL',content_url());
		}
		self::$option = get_option('tr_cache_optimize_files',array());
        if(!is_array($tr_cache_options))
        {
            $tr_cache_options = get_option('trcs_cache',array());
        }
	}

	public static function instance()
	{
		if(self::$instance == null)
		{
			self::$instance = new Tr_Cache_Class();
		}
		return self::$instance;
	}

	public function template_redirect()
	{
		ob_start(array(&$this,'autoptimize_end'));
	}

	public function script_loader_tag($tag, $handle, $src)
	{
        if(strpos($src,'/jquery.js')===false)
        {
            $tag = str_replace('<script','<script async ',$tag);
        }

		return $tag;
	}

	public function autoptimize_end($content)
	{
		global $tr_cache_options;
		if(self::$has_run)return $content;
		$starttime = microtime(true);
		if($tr_cache_options['optimize_js'])
		{
			$content = $this->_optimize_js($content);
		}
		if($tr_cache_options['optimize_css'])
		{
			$content = $this->_optimize_css($content);
		}
		self::$has_run= true;
		$content .= '<!-- optimize in ' . (microtime(true) - $starttime) .' -->';
		return $content;
	}

	function _optimize_js($content)
	{
		global $tr_cache_options;
        $exclude = !empty($tr_cache_options['exclude_js'])? $tr_cache_options['exclude_js'] : false;
        $include = !empty($tr_cache_options['include_js'])? $tr_cache_options['include_js'] : false;

		if(preg_match_all('/\<script[^\>]*src\=[\'|\"]+([^\'|^\"]*\.js)[^\'|^\"]*[\'|\"]+[^\<]*\<\/script\>[\n]?/',$content,$matches))
		{
			$time_string = '';
			foreach($matches[0] as $i => $m)
			{
				$url     = $matches[1][$i];

				$filename = basename($url);
                if($exclude)
                {
                    if(strpos($exclude,$filename)!==false){
                        continue;
                    }
                }else if($include){
                    if(strpos($include,$filename)===false){
                        continue;
                    }
                }
                if(!$tr_cache_options['add_script_sync']) {
                    if (strpos($url, '/jquery.js') !== false || strpos($url, '/admin-bar.min.js') !== false || strpos($url, 'comment-reply.min.js') !== false) continue;
                }
				$path    = $this->getpath($url);
				if(!$path)continue;


				if(isset($_GET['debugjs']))
				{
					if($i==$_GET['debugjs']){
						file_put_contents(WP_CONTENT_DIR."/log.txt",$url);
					}
					if($i>$_GET['debugjs'])continue;
				}


				$content = str_replace($m,'',$content);

				self::$scripts[] = $path;
				$time_string         .= filemtime($path).'|';
			}


			$file_md5    = TRSCSC_CACHE_JS.'/'. md5($time_string) . '.js';
			$file_path   = WP_CONTENT_DIR.'/'.$file_md5;
			if(!empty($time_string))
			{
				if(!file_exists($file_path))
				{
					$data     = '';
					$only_minify = $tr_cache_options['optimize_js_minify'];
					$compression = !$only_minify && $tr_cache_options['optimize_js_compression'];
					foreach(self::$scripts as $handle => $path)
					{
						if(!$compression)
						{
							$data.='try{'."\n";
							$data.= @file_get_contents($path)."\n";
							$data.= ' }catch(e){console.log(e)} '."\n";
						}else
						{
							$data.= @file_get_contents($path).";\n";
						}
					}


					if(!empty($data))
					{
						if($only_minify)
						{
							try{
                                $data =  Tr_Minify_Js::minify($data);

							}catch (Exception $e){

							}
						}
						else if($compression)
						{
							try{
								$script = $data;
								$encoding = 0;
								$fast_decode = false;
								$special_char = false;
								$packer = new JavaScriptPacker($script, $encoding, $fast_decode, $special_char);
								$script = $packer->pack();
								$data = $script;
							}catch(Exception $e)
							{

							}
						}
						@file_put_contents($file_path,$data);
					}
				}else{
                    @touch($file_path);
                }

				//put script
				$search = ($tr_cache_options['optimize_js_footer'])? '</body>':'</head>';
				$script = '<script type="text/javascript" src="'.content_url($file_md5).'"></script>';
				$content= str_replace($search,$script.$search,$content);
			}
		}
        if($tr_cache_options['add_script_sync']){
            $content = str_replace('<script type','<script async type',$content);
        }
		return $content;
	}

	function _optimize_css($content)
	{
		global $tr_cache_options;
        $exclude = !empty($tr_cache_options['exclude_css'])? $tr_cache_options['exclude_css'] : false;
        $include = !empty($tr_cache_options['include_css'])? $tr_cache_options['include_css'] : false;

		if(preg_match_all('/\<link[^\>]*href\=[\'|\"]+([^\'|^\"]*\.css)[^\'|^\"]*[\'|\"]+[^\>^\)]*\>[\n]?/',$content,$matches))
		{
			$time_string = '';
			foreach($matches[0] as $i => $m)
			{
				$url     = $matches[1][$i];
                $filename = basename($url);
                if($exclude)
                {
                    if(strpos($exclude,$filename)!==false){
                        continue;
                    }
                }else if($include){
                    if(strpos($include,$filename)===false){
                        continue;
                    }
                }
				if(strpos($url,'ie')!==false|| strpos($url,'/admin-bar.min.css')!==false)continue;

				$path    = $this->getpath($url);
				if(!$path)continue;

				$content = str_replace($m,'',$content);

				self::$styles[] = array($path,$url);
				$time_string         .= filemtime($path).'|';
			}

			$file_md5    = TRSCSC_CACHE_CSS.'/'. md5($time_string) . '.css';
			$file_path   = WP_CONTENT_DIR.'/'.$file_md5;
			if(!empty($time_string))
			{
				if(!file_exists($file_path))
				{
					$data     = '';
					foreach(self::$styles as $handle => $css)
					{
						$csscode = $this->_fix_css(@file_get_contents($css[0]),$css[0],$css[1]);
						$data.= $csscode."\n";
					}
					// return $data;
					if(!empty($data))
					{
						// Remove comments
						$data = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $data);
						// Remove space after colons
						$data = str_replace(': ', ':', $data);
						// Remove whitespace

                        $data = str_replace(array("\r","\n","\t"),'',$data);
						if($tr_cache_options['optimize_ws_css'])
						{
                            $sp = array(
                                '  ',
                                ' {',
                                '}@'
                            );
                            $rp = array(
                                ' ',
                                '{',
                                "} @"
                            );
                            $data = str_replace($sp,$rp,$data);
						}

						@file_put_contents($file_path,$data);
					}
				}else{
					@touch($file_path);
				}

				//put script
				$search = '<title>';

                if($tr_cache_options['add_style_bottom'] || tr_cache_is_mobile())
                {
                    $search = '</body>';
                }
				$style  = '<link rel="stylesheet" type="text/css"  href="'.content_url($file_md5).'" />';
				$content= str_replace($search,$style.$search,$content);
			}
		}
		return $content;
	}

	function _fix_css($css,$path,$url)
	{
		//fix url()
		if(preg_match_all('/url\([\s]*[\'|\"]?([^\'\"\)]+)[\'|\"]?[^\)]*\)/i',$css,$matches))
		{
			foreach($matches[0] as $i => $bg)
			{
				$s_url = $matches[1][$i];
				if(preg_match('/^http/',$s_url) || strpos($s_url,'data:')===0)continue;

				$img_url = dirname($url).'/'.$s_url;
				$bg_new  = str_replace($s_url,$img_url,$bg);
				$css     = str_replace($bg,$bg_new,$css);
				$css     = str_replace('http://','//',$css);
				//return $bg_new;

			}

		}


		//fix import
		if(preg_match_all('/@import[^\'^\"]+[\'|\"]+([^\'^\"]+)[\'|\"]+/',$css,$matches))
		{
			foreach($matches[0] as $i=> $import)
			{
				$import_url = dirname($url) .'/'. $matches[1][$i];
				$import_path = $this->getpath($import_url);
				if($import_path && file_exists($import_path) && is_readable($import_path))
				{
					$csscode = $this->_fix_css(@file_get_contents($import_path),$import_path,$import_url);
					$css = str_replace($import,'',$css);
					$css .= "\n" . $csscode;
				}
			}
		}


		return $css;
	}

	public function getpath($url)
	{
        if(strpos($url,'//')===0)
        {
            $url = 'http:'.$url;
        }
		$url = current(explode('?',$url,2));
		$path = str_replace(TR_WP_SITE_URL,'',$url);
		if(preg_match('#^((https?|ftp):)?//#i',$path))
		{
			/** External script/css (adsense, etc) */
			return false;
		}
		$path = str_replace('//','/',ABSPATH.$path);
		return $path;
	}

}