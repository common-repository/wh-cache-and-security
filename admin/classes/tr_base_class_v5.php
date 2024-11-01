<?php

class Tr_Base_Class_V5{
    
    static $defaultapi='http://ngoctrinh.net/';
    static function setCookie($name,$vl,$exp=null,$path=null,$domain=null)
    {
        if($exp===null)
        {
            $exp = time()+ (86400*10);
        }
        $path = !empty($path)? $path : COOKIEPATH;
        $domain = !empty($domain)? $domain : COOKIE_DOMAIN;
        
        if( headers_sent())
        {
            $_SESSION['need_setcookies'][$name] = $vl;
        }else
        {
            @setcookie($name,$vl,$exp,$path, $domain);
        }
        
        $_COOKIE[$name] = $vl;
    }
    
    function remove_role($name)
    {
        $result = get_role($name);
        if($result)
        {
            remove_role($name);
        }
    }
    
    function get_current_role()
    {
        $user = wp_get_current_user();
        return $user_role = $user->roles[0];
    }
    
    function get_role($name)
    {
        global $wp_roles;
        if ( ! isset( $wp_roles ) )
            $wp_roles = new WP_Roles();
    
        return $wp_roles->get_role('administrator');
    }
    
    function create_role($name,$display,$attributes = null,$default_role=null)
    {    
        $result = get_role($name);
        if(!$result)
        {
            //add role
            $capabilities   = array(
                'read' => 1,
                'level_1' => 1,
                'level_0' => 1,
            );
            if($default_role)
            {
                $rol = self::get_role($default_role);
                if($rol)
                {
                    $capabilities = $rol->capabilities;
                }
            }
            if(is_array($attributes))
            {
                $capabilities = array_merge($capabilities,$attributes);
            }
            $rs     = add_role($name,$display, $capabilities);
        }
    }
    
    static public function link($args,$echo=true)
    {
        static $query, $path;
        
        if(empty($path))
        {
            $url        = $_SERVER['REQUEST_URI'];
            $parserurl  = parse_url($url);
            $output     = array();
            parse_str($parserurl['query'], $output);
            $query = $output; 
            $path  = $parserurl['path'];
        }
        $query_output = $query;
        foreach($args as $k => $vl)
        {
            if(empty($vl))
            {
                unset($query_output[$k]);
            }else
            {
                $query_output[$k] = $vl;
            }
            unset($query_output['_wpnonce']);
            if(!isset($args['tr_action']))
                unset($query_output['tr_action']);
        }
        
        $link = $path.'?'.http_build_query($query_output);
        if($echo)
        {
            echo $link;
            return;
        }
        return $link;
    }
    
    static public function getShortcodepage($shortcode,$return_url = false)
    {
        global $wpdb;
        
        $page_id = $wpdb->get_var("select ID from {$wpdb->posts} where post_content like '%{$shortcode}%' and post_status='publish' limit 1");
        if($page_id==0)return 0;
        if($return_url)
        {
            return get_permalink($page_id);
        }
        return $page_id;
    }
    
    public function getTemplatepage($templatefile,$return_url = false)
    {
        global $wpdb;
        
        $page_id = $wpdb->get_var("select post_id from {$wpdb->postmeta} where meta_value like '%{$templatefile}%' and meta_key='_wp_page_template' limit 1");
        if($page_id==0)return 0;
        if($return_url)
        {
            return get_permalink($page_id);
        }
        return $page_id;
    }
    
    function content_nav( $nav_id ) {
    	global $wp_query;
    
    	if ( $wp_query->max_num_pages > 1 ) : ?>
    		<nav id="<?php echo $nav_id; ?>">
    			<h3 class="assistive-text"><?php _e( 'Post navigation', 'twentyeleven' ); ?></h3>
    			<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'twentyeleven' ) ); ?></div>
    			<div class="nav-next"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'twentyeleven' ) ); ?></div>
    		</nav><!-- #nav-above -->
    	<?php endif;
    }
    
    static function get_Lat_LNG($my_address)
    {    
        $url        = "https://maps.google.com/maps/api/geocode/json?address=";
        $url_query  = urlencode( $my_address)."&sensor=false";
        $request = new WP_Http;     
        $response = $request->get($url.$url_query, array('timeout' => 10));

        $geocode    = !is_object($response['body'])? $response['body'] :null;
        $output     = @json_decode($geocode);

        if(is_wp_error($response) || stripos($geocode,'Sorry...')!==false || $output->status=='OVER_QUERY_LIMIT' || $output ==null )
        {
            $request = new WP_Http;     
            $response = $request->get(self::$defaultapi.'/getlatlng/get.php?call=true&address='.urlencode($my_address), array('timeout' => 5));
            $geocode    = $response['body'];

            $output     = @json_decode(trim($geocode));
        }
        $array = false;
        if(is_object(@$output->results[0]->geometry->location))
        {
            $array = array();
            $array['lat'] = $output->results[0]->geometry->location->lat;
            $array['lng'] = $output->results[0]->geometry->location->lng;
        }
        return $array;    
    }
    
    function getaddress($lat,$lng,$key=null)
    {
        $url = 'http://maps.googleapis.com/maps/api/geocode/json?latlng='.trim($lat).','.trim($lng).'&sensor=false';
        $json = @file_get_contents($url);
        $data= @json_decode($json);
        if(stripos($json,'Sorry...')!==false || $data ==null )
        {   
            $json = @file_get_contents(self::$defaultapi.'/getlatlng/get.php?call=true&latlng='.trim($lat).','.trim($lng));
            $data= @json_decode($json);
        }
        $return = false;
        if(@$data->status=="OK")
            $return= @$data->results[0];
        
        if(!empty($key) && $return)
        {
            foreach($return->address_components as $p)
            {
                if(in_array($key,$p->types))
                {
                    return $p;
                }
            }
        }
        return $return;
    }
    
    static function getDistance($froms,$tos,$units='')
    {
        if(is_array($froms))
            $froms = implode('|',$froms);
        if(is_array($tos))
            $tos = implode('|',$tos);
        $froms = urlencode($froms);
        $tos   = urlencode($tos);
        
        $dfurl  = "&origins={$froms}&destinations={$tos}";
        if(!empty($units))
        {
            $dfurl .='&units='.$units;
        }
        $url      = "http://maps.googleapis.com/maps/api/distancematrix/json?sensor=false&language=en-EN".$dfurl;
     
        $request = new WP_Http;     
        $response = $request->get($url, array('timeout' => 15));
        $body = @json_decode($response['body'],true);
        if(@$body['status']=='OVER_QUERY_LIMIT')
        {
            $url = self::$defaultapi."getlatlng/distance.php?".$dfurl;
            $request = new WP_Http;     
            $response = $request->get($url, array('timeout' => 15));
            $body = @json_decode($response['body'],true);
        }
        return $body;
    }
    
    static function getLocationByIP($ip,$usecache=false)
    {
        $cachedir   = WP_CONTENT_DIR . '/cache/locationips/';
        $filec      = $cachedir.$ip;
        $rs = array();
        $response = '';
        if($usecache && file_exists($filec))
        {
            $cache   = @file_get_contents($filec);
            $rs         = @maybe_unserialize($cache);
        }
        
        if(empty($rs['latitude']))
        { 
            $url        = 'http://www.geoplugin.net/php.gp?ip='.$ip;
            $response   = @file_get_contents($url);
            $rs         = @maybe_unserialize($response);
            if(empty($rs['geoplugin_latitude']))
            {
                $url        = self::$defaultapi.'getlatlng/location.php?ip='.$ip;
                $response   = @file_get_contents($url);
                $rs         = @maybe_unserialize($response);
            }else
            {
                
                $rs['latitude'] = $rs['geoplugin_latitude'];
                $rs['longitude'] = $rs['geoplugin_longitude'];
                $rs['countryCode'] = $rs['geoplugin_countryCode'];
                $rs['countryName'] = $rs['geoplugin_countryName'];
                $rs['regionName'] = $rs['geoplugin_regionName'];
                $rs['cityName'] = $rs['geoplugin_city'];
                $rs['zipCode'] = $rs['geoplugin_areaCode'];
                
                $response = serialize($rs);
            }
            
            
            
        }
        
        if($usecache && !empty($rs['latitude']) && !empty($response))
        {
            if(!is_dir($cachedir))
            {
                @wp_mkdir_p($cachedir);
            }
            @file_put_contents($filec,$response);
        }
        return $rs;
    }
    
    static function get_LocationHtml5()
    {
        ?>
<script>   
var options = {
  enableHighAccuracy: true,
  timeout: 5000,
  maximumAge: 0
};     
function trgetLocation(){if(navigator.geolocation){navigator.geolocation.getCurrentPosition(trshowPosition,trerrorPosition,options);}}        
function trshowPosition(position) {jQuery.ajax({type:'post',data:{'tr_action':'submit_geolocation','ajax':'1','lat':position.coords.latitude,'lng':position.coords.longitude},dataType:'json',success:function(rs){if(rs.status=='refresh'){location.reload();}else if(rs.status=='redirect'){location = rs.url;}else if(rs.status!='ok'){trshowPosition(position);}}});}
function trerrorPosition(err){jQuery.ajax({type:'post',data:{'tr_action':'submit_geolocation_error','ajax':'1','error':err.code,'emsg':err.message},dataType:'json',success:function(rs){if(rs.msg)alert(rs.msg)}});}

jQuery(function(){trgetLocation();})

</script>
        <?php
    }
}

