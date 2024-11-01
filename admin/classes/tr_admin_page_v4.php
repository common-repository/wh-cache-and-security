<?php
class Tr_Admin_Page_V4 extends Tr_Form_V2
{
    
    public $pages = array();
    public $removes = array();
    public $not_removes = array();
    public $removesubs = array();
    public $parents = array();
    public $dir;
    
    /**
     * Tr_Admin_Page::__construct()
     * 
     * @return
     */
    public function __construct($dir)
    {     
        $this->dir = $dir;
        $menu_action = WP_NETWORK_ADMIN===true? 'network_admin_menu': 'admin_menu';

        add_action($menu_action,array(&$this,'admin_menu'),999);
        add_action('admin_init',array(&$this, 'admin_init'),999);
        parent::__construct();
    }
    
    
    /**
     * Tr_Admin_Page::add_page()
     * 
     * @param string $parent
     * @param string $slug
     * @param string $title
     * @param string $cap
     * @param string $callback
     * @param array $args : hide,
     * @return
     */
    public function add_page($slug,$title,$cap='manage_options',$callback='default',$args=array())
    {
        if(WP_NETWORK_ADMIN && $args['network']!==true)
        {
            return false;
        }
        $this->pages[$slug]= compact('title','cap','callback','args');
    }
    
    public function add_subpage($parent,$slug,$title,$cap='manage_options',$callback='default',$args=array())
    {
        if(WP_NETWORK_ADMIN && $args['network']!==true)
        {
            return false;
        }
        $this->pages[$slug]= compact('parent','title','cap','callback','args');
    }
    
    /**
     * Tr_Admin_Page::remove_page()
     * 
     * @param mixed $slug
     * @return
     */
    public function remove_page($slug,$role=null)
    {
        $this->removes[$slug]= $role;
    }
    
    public function remove_not_page($slug,$role=null,$changed = array())
    {
        $this->not_removes[$slug]= $changed + array('role'=>$role);
    }
    
    public function remove_subpage($menu,$slug,$role=null)
    {
        $this->removesubs[$slug] = compact('menu','role');
    }
        
    /**
     * Tr_Admin_Page::admin_menu()
     * 
     * @return
     */
    public function admin_menu()
    {
        global $submenu,$menu, $_registered_pages,$_parent_pages;
        
        foreach($this->removes as $slug =>$role)
        {
            if($slug=='all')
            {
                if(count($this->not_removes)>0)
                {
                    $new_menu = array();
                    foreach($menu as $i =>$m)
                    {
                        $sl = $m[2];
                        if(isset($this->not_removes[$sl]))
                        {
                            $n = $this->not_removes[$sl];
                            if(!empty($n['name']))
                            {
                                $menu[$i][0] = $n['name'];
                            }
                            if(!empty($n['position']))
                            {
                                $new_menu[$n['position']] = $menu[$i];
                            }else
                            {
                                $new_menu[$i] = $menu[$i];
                            }
                        }
                    }
                    $menu = $new_menu;
                }
                else if($role)
                {
                    $user = wp_get_current_user();
                    $user_role = $user->roles[0];
                    if($role == $user_role)
                    {                        
                        $menu = array();
                    }                    
                }else
                {
                    $menu = array();
                }           
            }else
                remove_menu_page($slug);
                
            //var_dump($menu);
        }
        
        foreach($this->removesubs as $slug =>$ar)
        {
            remove_submenu_page($ar['menu'],$slug);
        }
        
        
        
        foreach($this->pages as $slug => $page)
        {
            $callback  = $page['callback']=='default'? array($this,'callback') : $callback;
            if($page['args']['hide'])
            {                
                if(current_user_can( $page['cap'] ))
                {                    
                    $slug = plugin_basename( $slug );
                    $hookname = get_plugin_page_hookname( $slug, '' );
                  
                    add_action( $hookname, $callback );
                    $_registered_pages[$hookname] = true;
                    $_parent_pages[$slug] = false;
                }                    
            }
            else if($page['parent'])
            {
                if($page['callback']===1)
                {
                    $submenu[$page['parent']][] = array ( $page['title'], $page['cap'], $slug, $page['title'] );
                }
                else add_submenu_page($page['parent'],$page['title'],$page['title'],$page['cap'],$slug,$callback);
            }else
            {
                add_menu_page($page['title'],$page['title'],$page['cap'],$slug,$callback,$page['args']['icon'],$page['args']['position']);
                $this->parents[] = $slug;
            }
        }
    }
    
    public function admin_init()
    {
        global $submenu;
        foreach($this->parents as $slug)
        {
            if($submenu[$slug][0][2]==$slug)
            {
                unset($submenu[$slug][0]);
            }
        }
    }
    
    /**
     * Tr_Admin_Page::callback()
     * 
     * @return
     */
    public function callback()
    {
        global $plugin_page,$wpdb;
        $current_dir    = self::get_dir();
        $action_admin   = $current_dir.'/pages/admin_'.$plugin_page.'.php';
        $field          = $plugin_page;
        $values         = get_option($field);
        $act            = isset($_REQUEST['act'])? $_REQUEST['act'] : '';
        
        
        wp_enqueue_style( 'Admin_Page_Class', self::get_url() . '/css/admin.css',array(),'1.2.2' );
        if (! did_action( 'wp_enqueue_media' ) )
        {            
            add_thickbox();
            wp_enqueue_media(   );
        }
        if(is_file($action_admin))
        {
            include_once($action_admin);
        }
        do_action('show_admin_page_'.$plugin_page);
    }
    
    public function get_dir()
    {
        return $this->dir;
    }
    
    public function get_url()
    {
        static $path_url;
        if(!empty($path_url))return $path_url;
        
        $current_dir    = self::get_dir();
        if(stripos($current_dir,'plugins/')!==false)
        {
            $path_url = plugins_url( 'admin',  $current_dir .'/plug.php' );
        }else
        {
            $path_url = get_stylesheet_directory_uri() . '/admin';
        }
        return $path_url;
    }
    
}