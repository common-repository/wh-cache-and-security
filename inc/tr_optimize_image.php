<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Optimize_Image
{
    var $total = 0;
    var $file_list = '';
    var $max_width = 1920;
    var $max_height = 1100;
    var $max_size = 500000;
    var $data_list;
    var $rootpath;
    var $blogurl;

    function __construct()
    {
        $this->blogurl = get_bloginfo('url');
    }

    function is_image($filepath)
    {
        $info = pathinfo($filepath);
        $image_types = array('gif', 'jpg', 'jpeg', 'png', 'jpe');

        $ext = $info['extension'];
        if (in_array($ext, $image_types))
        {
            return true;
        }
        return false;
    }

    function load_image($filename) {

        $image_info = getimagesize($filename);
        $image_type = $image_info[2];
        if( $image_type == IMAGETYPE_JPEG ) {

            $image= imagecreatefromjpeg($filename);
        } elseif( $image_type == IMAGETYPE_GIF ) {

            $image= imagecreatefromgif($filename);
        } elseif( $image_type == IMAGETYPE_PNG ) {

            $image= imagecreatefrompng($filename);
        }
        
        return array('image_type'=>$image_type,'image'=>$image);
    }



    function save($filename, $image, $image_type, $compression=75) {
        if( $image_type == IMAGETYPE_JPEG ) {

            return imagejpeg($image,$filename,$compression);
        } elseif( $image_type == IMAGETYPE_GIF ) {

            return imagegif($image,$filename);
        } elseif( $image_type == IMAGETYPE_PNG ) {

            return imagepng($image,$filename);
        }
        return false;
    }

    function resize($file,$w,$h,$compression=75,$crop=false)
    {
        $bkfile = $file.'_bk.jpg';
        @copy($file,$bkfile);

        $image = wp_get_image_editor( $file);
        if ( ! is_wp_error( $image ) ) {
            $image->resize( $w, $h, false );
            $image->save( $file );
            return true;
        }

        return -3;
        /*
        list($width, $height) = getimagesize($file);
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil($width-($width*abs($r-$w/$h)));
            } else {
                $height = ceil($height-($height*abs($r-$w/$h)));
            }
            $newwidth = $w;
            $newheight = $h;
        } else {
            if ($w/$h > $r) {
                $newwidth = $h*$r;
                $newheight = $h;
            } else {
                $newheight = $w/$r;
                $newwidth = $w;
            }
        }




        $image = wp_get_image_editor( $file);
        if ( ! is_wp_error( $image ) ) {
            $image->resize( $newwidth, $newheight, false );
            return $image->save( $file );
        }

        /*

        $imageinfo = $this->load_image($file);

        if(empty($imageinfo['image']))return -2;

        $new_image = imagecreatetruecolor($newwidth, $newheight);
        $rs  = imagecopyresampled($new_image, $imageinfo['image'], 0, 0, 0, 0,  $newwidth, $newheight, $width, $height);
        if($rs)
        {
            //$file .= 'new.jpg';
            //make backup file

            $old_size = filesize($file);

            $rs =  $this->save($file,$new_image, $imageinfo['image_type'],$compression);

            $new_size = filesize($file);
            if($old_size/$new_size>=1)
            {
                unlink($file);
                rename($bkfile,$file);
            }else{
                @unlink($bkfile);
            }

        }
        */

        return -3;
    }

    function optimize_images($dir,$maxwidth,$maxheight,$maxsize=500000, $compression=75,$limit = 5)
    {
        $count = 0;
        $files = scandir($dir);
        foreach ($files as $file)
        {
            $filepath = $dir.'/'.$file;
            if($file == '..' || $file == '.' || !$this->is_image($filepath) || strpos($file,'.bak.')>0 || strpos($file,'_bk.')>0)continue;
            $filesize = filesize($filepath);
            if($filesize < $maxsize)continue;
            list($width, $height) = getimagesize($filepath);

            if($width <= $maxwidth && $height <= $maxheight)continue;


            if($count<$limit)
            {
                echo $filepath.'=>size:'.$filesize.'=>w:'.$width.'x'.$height;
                $rs = $this->resize($filepath,$maxwidth,$maxheight,$compression,false);
                
                if($rs)
                {
                    echo '=>ok';
                }else{
                    echo '=>error';
                }
                echo '<br>';
            }

            $count++;
        }

        return $count;

    }

    function optimize($position)
    {
        $position = intval($position);
        $file = $this->data_list[$position];
        $filepath = $this->rootpath.'/'.$file;
        $maxwidth = $this->max_width;
        $maxheight = $this->max_height;
        $rs = $this->resize($filepath,$maxwidth,$maxheight,$compression,false);
        list($d,$url) = explode('/wp-content/',$filepath);
        $url = $this->blogurl.'/wp-content/'.$url;
        return array('file'=>$file,'status'=>$rs,'url'=>$url);
    }

    function get_file_list($key)
    {
        return WP_CONTENT_DIR.'/cache/'.md5($key).'.json';
    }

    function set_size($maxwidth,$maxheight,$maxsize)
    {
        $this->max_width = $maxwidth;
        $this->max_height = $maxheight;
        $this->max_size = $maxsize * 1000;
    }

    function make_list($dir,$key)
    {
        $this->data_list = array();
        $this->file_list = $this->get_file_list($key);
        $this->scan_dir($dir,'');
        $this->total = count($this->data_list);
        $this->rootpath = $dir;

        $data = array(
            'list' => $this->data_list,
            'total' => $this->total,
            'rootpath' => $this->rootpath,
            'max_width' =>$this->max_width,
            'max_height' => $this->max_height
        );
        $data = json_encode($data);
        file_put_contents($this->file_list,$data);
    }

    function loaddata($key)
    {
        $this->file_list = $this->get_file_list($key);
        $data = json_decode(file_get_contents($this->file_list),true);
        $this->data_list = $data['list'];
        $this->total = $data['total'];
        $this->rootpath = $data['rootpath'];
        $this->max_width = $data['max_width'];
        $this->max_height = $data['max_height'];

        unset($data);
    }

    function scan_dir($root,$dir='')
    {
        if (is_dir($root.$dir)) {
            $handle = @opendir($root . $dir);
            if ($handle) {
                while (false !== ($entry = @readdir($handle))) {
                    if ($entry != "." && $entry != ".." && $entry != '') {
                        $path = trim($dir . '/' . $entry,'/');
                        if (is_dir($root . $path)) {
                            $this->scan_dir($root, $path);
                        }else if($this->is_image($root.'/'.$path)){
                            $filepath = $root.$path;


                            $filesize = filesize($filepath);
                            if($filesize < $this->max_size)continue;
                            list($width, $height) = getimagesize($filepath);
                            if($width < $this->max_width && $height < $this->max_height)continue;
                            if(strpos($filepath,'_bk.')>0 || strpos($filepath,'.bak.')>0)continue;
                            $this->data_list[] = $path;
                        }
                    }
                }
            }
        }
    }
}