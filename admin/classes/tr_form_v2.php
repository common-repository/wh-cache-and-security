<?php

class Tr_Form_V2
{
     
    static $parentname;
    static $displaytype;
    static $validate;
    static $has_calendar;
    static $data;
    static $id;
    var $args = array();
    
    public function __construct()
    {
        add_action('admin_init',array(&$this,'admin_init_form'));
    }
    
    public function get_tbl($field)
    {
        global $wpdb;
        $table = stripos($field,'tbl')===0? str_replace('tbl','',$field): $wpdb->prefix . $field;
        return $table;
    }
    
    /**
     * Tr_Form::admin_init()
     * 
     * @return
     */
    public function admin_init_form()
    {
        global $wpdb;
        if(!isset($_POST['admin_action']))return;
       
        if(isset($this->pages[$_POST['admin_action']]) && @$_POST['auto_save_options']=='1')
        {
            $field = $_POST['admin_action'];
            $data = $_POST[$field];
            $data = stripslashes_deep($data);
            $data = apply_filters('save_admin_options',$data,$field);
            update_option($field,$data);
            Tr_Session_Class::add('Saved Settings successfully');
        }
        else if( @$_POST['auto_save_options']==='2')
        {
            $name  = $_POST['name_object'];
            $field = $_POST['admin_action'];
            $data  = $_POST[$field]; 
            $table = self::get_tbl($field);
            $rs = false;
            $insert_id = '';
            if(empty($data['ID']))
            {
                $rs = $wpdb->insert($table,$data);
                if($rs)
                {
                    Tr_Session_Class::add('Added '.$name.' successfully');
                    $insert_id = $wpdb->insert_id;
                }
                else
                {
                    Tr_Session_Class::add_error('Error when Insert new '.$name);
                }
            }else
            {
                $rs = $wpdb->update($table,$data,array('ID'=>$data['ID']));                
                if($rs!==false)
                {
                    Tr_Session_Class::add('Updated '.$name.' successfully');
                }else{
                    Tr_Session_Class::add_error('Error when Insert update '.$name);
                }
            }
           
            if($rs!==false)
            {
                $redirect = !empty($_POST['tr_redirect'])? $_POST['tr_redirect'] : wp_get_referer();
                if($insert_id > 0)
                {
                    $redirect = str_replace('insert_id',$insert_id,$redirect);
                }
                wp_redirect($redirect);
                exit;
            }
        }
    }
    
    /**
     * Tr_Form::form()
     * 
     * @param array $args
     * @param - method: post,get
     * @param - target: _blank, parent, self, ...
     * @param - enctype: text/plain, application/x-www-form-urlencoded, multipart/form-data
     * @param - class
     * @param - id
     * @param - validate : default true
     * @param - name : label of table
     * @param - save_option: 1: save option, 2: save table
     * @return
     */
    public function form($args=array(),$parentname='',$displaytype='table')
    {
        global $plugin_page,$wpdb;
        
        $args = wp_parse_args($args,array(
            'method'=>'post',
            'target'=>'',
            'enctype'=>'',
            'class'=>'',
            'id'=>time(),
            'autocomplete'=>'',
            'validate'=> true,
            'name' => '',
            'save_option' => 0,
            'tr_action' => '',
        ));
        $this->args = $args;
        
        self::$id           = 0;
        self::$data         = array();
        self::$parentname   = $parentname;
        self::$displaytype  = $displaytype;
        self::$validate     = $args['validate'];
        if(self::$validate)
        {
            $args['class'].=' need_validate';
        }
        
        
        $atts = '';
        foreach($args as $k => $vl)
        {
            if(empty($vl))continue;
            $atts.=$k.'="'.$vl.'" ';
        }
        if($args['alert'])Tr_Session_Class::show();
        echo '<form '.$atts.'>';
        if(!empty(self::$parentname))
        {
            self::$id = $args['edit'];
            if(self::$id > 0)
            {
                $table = self::get_tbl(self::$parentname);
                self::$data = $wpdb->get_row("select * from {$table} where ID = ".self::$id,ARRAY_A);
                echo '<input type="hidden" name="'.self::$parentname.'[ID]" value="'.self::$id.'"/>';
            }
        }
        if(!empty($args['name']))
        {
            echo '<input type="hidden" name="name_object" value="'.$args['name'].'"/>';
        }
        if(!empty($args['redirect']))
        {
            echo '<input type="hidden" name="tr_redirect" value="'.$args['redirect'].'"/>';
        }
        if(!empty($args['tr_action']))
        {
            echo '<input type="hidden" name="tr_action" value="'.$args['tr_action'].'" />';
        }
        echo '<input type="hidden" name="admin_action" value="'.(!empty(self::$parentname)? self::$parentname: $plugin_page).'" />';
        echo '<input type="hidden" name="auto_save_options" value="'.$args['save_option'].'" />';
        
        if(self::$displaytype=='table')
        {
           echo '<table class=" custom-meta-tbl" style="width:100%">';
        }
    }
    
    /**
     * Tr_Form::endform()
     * 
     * @param string $label
     * @return
     */
    public function endform($label='Save',$cancel='',$buttons='')
    {        
        if($label)
        {
            echo self::$displaytype=='table'? '<tr><th></th><td>': '<div class="submit">';
            echo '<input type="submit" class="button-primary" value="'.$label.'" /> ';
            if(!empty($cancel))
            {
                echo ' <a class="button" href="'.$cancel.'">Cancel</a> ';
            }
            echo $buttons;
            echo self::$displaytype=='table'? '</td></tr>': '</div>';
        }
        
        if(self::$displaytype=='table')
        {
           echo '</table>';
        }        
        echo '</form>';
        
        static $showed_validate;
        if(self::$validate && $showed_validate==false)
        {            
            wp_enqueue_script( 'jquery-validate', Tr_Admin_Page_V4::get_url() . '/js/jquery.validate.min.js',array('jquery'),true );
            $showed_validate = true;
            ?>
            <script>
            jQuery(function($){
                $("form.need_validate").validate();
            })
            </script>
            <?php
        }
        static $show_calendar;
        if(self::$has_calendar && $show_calendar==false)
        {
            $show_calendar = true;
            wp_enqueue_script('jquery-ui-datepicker');            
            wp_enqueue_style( 'jquery-ui', Tr_Admin_Page_V4::get_url() . '/css/jquery-ui.css',array(),'1.1.9' );
        }
        wp_enqueue_script( 'admin_post_meta', Tr_Admin_Page_V4::get_url() . '/js/post.js',array(),'3.1' );
    }
    function _getInput($field)
    {
        global $plugin_page;
        
        if(empty($field['type']))$field['type'] = 'text';
        $field['id'] = $field['name'];
        $postdata = @$_POST;
        if(!empty(self::$parentname))
        {
            $field['name'] = self::$parentname.'['.$field['name'].']';
            $postdata = @$_POST[self::$parentname];
        }else if($this->args['save_option']=='1')
        {
            $postdata = get_option($plugin_page,array());
            $field['name'] = $plugin_page.'['.$field['name'].']';
            $postdata = isset($_POST[$plugin_page])? $_POST[$plugin_page] : $postdata;
        }
        
        $meta = isset(self::$data[$field['id']])? self::$data[$field['id']]: '';
        $meta = isset($postdata[$field['id']])? $postdata[$field['id']]: $meta;
        

        $other_atts = isset($field['attrs'])? $field['attrs'] :'';
        $style = '';
        if($field['required'])
        {
            $other_atts.=' data-rule-required="true" ';
        }
        if(!empty($field['height']))
        {
            $field['height'] .= strpos($field['height'],'%')===false? 'px':'';
            $style.='height: '.$field['height'];
        }
        if(!empty($field['width']))
        {
            $field['width'] .= strpos($field['width'],'%')===false? 'px':'';
            $style.='width: '.$field['width'];
        }
        
        if(!empty($style))
        {
            $other_atts.= ' style="'.$style.'" '; 
        }
        
        if(!empty($field['class']))
        {
            $other_atts.= ' class="'.$field['class'].'" ';
        }
        
        
       
        echo self::$displaytype=='table'? '<tr><th style="width:20%">'.$field['label'].'</th><td>': '<div class="row">'.$field['label'];
        
        switch ($field['type']) {
                
            case 'texts':
                echo '<table class="subtbl">';
                foreach($field['options'] as $title => $vl):
                    echo '<tr><td>'.$title.': </td>';
                    echo '<td><input type="text" name="', $field['name'], '[',$title,']" id="', $field['id'], '" value="',  $meta[$title] ? esc_attr($meta[$title]) : esc_attr($vl), '" size="30" ', $other_atts,'/>';
                    echo '</td>';
                endforeach;
                echo '</table>';
				echo	'<br />', $field['desc'];
                break;
                
            case 'function':
                call_user_func($field['function'],$post,$field);
                break;
			case 'textarea':
				echo '<textarea name="', $field['name'], '" id="', $field['id'], '" cols="60" rows="6" ', $other_atts,'>', $meta ? stripslashes($meta) : $field['std'], '</textarea>',
					'<br />', $field['desc'];
				break;
			case 'select':
				echo '<select name="', $field['name'], '" id="', $field['id'], '" ', $other_atts,'>';
                
				foreach ($field['options'] as $id => $opt) {
				   
				    $option['value'] = is_array($opt) && isset($opt['value'])? $opt['value'] : $id;
                    $option['name'] = is_array($opt) && isset($opt['name'])? $opt['name'] : $opt;
                    
                    if(is_array($option['name']))
                    {
                        $group = $option['name'];
                        echo '<optgroup label="'.$group['group_name'].'">';
                        foreach($group as $ic => $vl)
                        {
                            if($ic =='group_name')continue;
                            echo '<option value="', $ic, '"', $meta == $ic ? ' selected="selected"' : '', '>', $vl, '</option>';
                        }
                        echo '</optgroup>';
                    }
                    else
                    {
                        echo '<option value="', $option['value'], '"', $meta == $option['value'] ? ' selected="selected"' : '', '>', $option['name'], '</option>';
                    }
					   
				}
				echo '</select>';
				break;
			case 'radio':
				foreach ($field['options'] as  $id => $opt) {
				    $option['value'] = is_array($opt) && isset($opt['value'])? $opt['value'] : $id;
                    $option['name'] = is_array($opt) && isset($opt['name'])? $opt['name'] : $opt;
                    
					echo '<label class="inline"><input type="radio" name="', $field['name'], '" value="', $option['value'], '"', $meta == $option['value'] ? ' checked="checked"' : '', $other_atts, ' />', $option['name'],'</label> ';
                    if(($field['newline']))echo '<br>';
				}
				break;
			case 'checkbox':
				echo '<input type="checkbox" value="1" name="', $field['name'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />';
				break;
            case 'checkboxes':
                $meta = is_array($meta)? $meta : array();
                foreach($field['options'] as $id => $opt)
                {
                    $option['value'] = is_array($opt) && isset($opt['value'])? $opt['value'] : $id;
                    $option['name'] = is_array($opt) &&  isset($opt['name'])? $opt['name'] : $opt;
                    
                    echo '<label '.$field['labelcss'].'>',$option['name'];
                    if($field['br'])echo '<br>';
                    echo ' <input type="checkbox" name="', $field['name'], '[]" id="', $field['id'], '"', (in_array($option['value'],$meta)) ? ' checked="checked"' : '', ' value="',$option['value'],'" />';
                    echo '</label>';
                }
                break;
			case 'wysiwyg':
                //echo $field['std'];
                echo '<label for="', $field['id'], '">', $field['label'], '</label>';
                echo '<div class="postarea">';
                $text = ($meta ? $meta : $field['std']);
                wp_editor(stripslashes($text),$field['id']);
				echo '</div>';
                echo $field['desc'];
				break;

            case 'img':
			case 'image':
                $title = $field['title']? $field['title'] : 'Upload Image';
                 
                echo '<span id="imagelist_'.$field['id'].'">';
                if(!empty($meta))
                {
                     $gThumb = wp_get_attachment_image_src($meta);
                     if(empty($gThumb[0]))
                     {
                        $file = wp_get_attachment_url($meta);
                        $name = get_post($meta);
                        $name = $name->post_title;
                     }
                ?>
                <span class="imagelist listimg">
                    <input type="hidden" name="<?php echo $field['name']?>" value="<?php echo $meta?>" />
                    <?php if($gThumb[0]):?>
                        <img src="<?php echo $gThumb[0]?>" width="50" height="50"/>
                    <?php else:?>
                        <span class="filename"><a href="<?php echo $file?>" target="_blank"><?php echo $name?></a></span>
                    <?php endif;?>
                    <a rel="<?php echo $meta?>" pid="<?php echo $post->ID ?>" class="removeimg">Remove</a>
                </span>
                <?php
                }
                echo '</span>';
                ?>
                <a rel="<?php echo $field['id']?>" data-fname="<?php echo $field['name']?>" class="upload_image_button button" <?php echo $field['field_data']?>  data-title="<?php echo $field['label']? $field['label'] : 'Insert Media'?>" href="#upload"><?php echo $title?></a>
                
                <?php
				echo '<br>', $field['desc'];
                break;
            //boxes
            case 'box':
                $this->creatboxes($meta,$field);
                break;
                
            case 'images':
                $this->creatImages($meta,$field);
                break;  
            
            case 'optionbox':
                $this->creatOptionboxs($meta,$field);
                break;
                
            default:
				echo '<input type="',$field['type'],'" name="', $field['name'], '" id="', $field['id'], '" value="',  $meta ? esc_attr($meta) : esc_attr($field['std']), '" size="30" ',$other_atts,'  />',
					'<br />', $field['desc'];
				break;
           
		}
		echo self::$displaytype=='table'? '</td></tr>': '</div>';
    }
    
    function creatOptionboxs($meta,$field)
    {
        global $post;
        ?>
        <div class="list_option" id="options_<?php echo $field['id']?>">
            <?php 
            $listoptions = get_option('tr_option_'.$field['id'],array());
            if(!is_array($meta))$meta = array();
            foreach($listoptions as $id=>$text)
            {
                $value = @$meta[$id]['vl'];
                ?>
                <div class="option">
                    <label><input type="checkbox" <?php if($meta[$id]['id'])echo 'checked'?> name="<?php echo $field['name']?>[<?php echo $id?>][id]" value="<?php echo $id?>" /> <?php echo $text?></label>
                    <?php tr_get_value_options_type($field,$id,$value)?>                    
                    <a class="removeoption" oid="<?php echo $id?>" fid="<?php echo $field['id']?>">Remove</a>
                </div>
                <?php
            }
            ?>
        </div>
        <div class="boxaddnewoption">
            <input type="text" id="createnewoption" />
            <a class="button createnewoptionbutton" opt="<?php echo base64_encode(json_encode($field['valueoptions']))?>" rel="<?php echo $field['id']?>" pid="<?php echo $post->ID ?>">Add New</a>
        </div>
                
        <?php
        global $show_script_for_option_abc;
        if(!$show_script_for_option_abc)
        {
            $show_script_for_option_abc = true;
        ?>
        <script>
        (function($){
            $('a.createnewoptionbutton').live('click',function(){
               var prnew= $(this).parents('.boxaddnewoption');
               var fid = $(this).attr('rel');
               var pid = $(this).attr('pid');
               var opt= $(this).attr('opt');
               var vl = prnew.find('input#createnewoption').val();
               if(vl==''){alert('Please enter text');return false};
               $.ajax({
                    url: ajaxurl,
                    type: 'post',
                    data:{'action':'trcreatenewoption','fid':fid,'pid':pid,'text':vl,'opt':opt},
                    success:function(rs){
                        $('#options_'+fid).append(rs);
                        prnew.find('input#createnewoption').val('')
                    }
               })
            });
            $('input#createnewoption').live('keypress',function(e){
                if(e.keyCode==13){
                    $(this).parents('.boxaddnewoption').find('a.button').click();
                    return false;
                };
            })
            $('.list_option .option a.removeoption').live('click',function(){
                if(confirm('Do you want remove?')==false)return false;
                var fth =$(this);
                $.ajax({
                    url: ajaxurl,
                    type: 'post',
                    data:{'action':'trremoveoption','fid':fth.attr('fid'),'id':fth.attr('oid')},
                    success:function(rs){
                        if(rs=='ok')
                        {
                            fth.parents('.option').remove();
                        }
                    }
               })
            })
        })(jQuery)
        </script>
        <?php
        }
    }
    
    function creatboxes($meta,$field)
    {
        global $showed_script_for_boxs;
        if(!$showed_script_for_boxs)
        {
        
        $showed_script_for_boxs = true;
        ?>
        <div class="box_bank" style="display: none;">
                <div class="row">
                    <label><span class="tt">Title</span> <span class="idrow"></span></label>
                    <input type="text" id="title" />
                </div>
                <div class="row">
                    <label class="vl">Value</label>
                    <input type="text" id="value" />
                </div>
                <div class="remove"><a class="removebox">Remove</a></div>
        </div>
        <script>
        var $ = jQuery;
        function tradd_box(title,value,fieldname)
        {        
            var prdiv =$('#boxes'+fieldname);            
            var countbox = prdiv.attr('countbox');
            if(countbox==undefined )countbox = 0;
            countbox = parseInt(countbox);
            var fname = fieldname+'['+countbox+']';
            var newrow= $('<div class="box"></div>');
            newrow.append($('.box_bank').html());
            prdiv.append(newrow);
            newrow.find('#title').attr('name',fname+'[title]').val(title);
            newrow.find('#value').attr('name',fname+'[value]').val(value);
            newrow.find('.idrow').html(countbox+1);
            countbox ++;
            prdiv.attr('countbox',countbox);
        }
        
        (function($){
            $('a.removebox').live('click',function(){
                $(this).parents('.box').remove();
            });            
        })(jQuery)
        </script>
        <?php
        }
        $meta = (array)$meta;
        
        $script_addbox='';
        //print_r($meta);
        if(is_array($meta) && count($meta)>0)
        {
            foreach($meta as $data)
            {
                foreach((array)$data as $k => $vl)
                {
                    $vl       = trim($vl);
                    $vl       = str_replace(array("\n","\r"),array('[br]',' '),$vl);
                    $data[$k] = addslashes ($vl);                
                }
                if(empty($data['title']))continue;
                
                $script_addbox.='tradd_box("'.$data['title'].'","'.$data['value'].'","'.$field['id'].'");'."\n";
            }
        }
        ?>
        <div class="boxes" id="boxes<?php echo $field['id']?>"></div>        
        <a class="button" id="addbox<?php echo $field['id']?>" >Add Row</a>        
        <script>
        (function($){
            $('a#addbox<?php echo $field['id']?>').live('click',function(){
                tradd_box('','','<?php echo $field['id']?>');
                return false;
            });
            <?php echo $script_addbox?>
        })(jQuery)
        </script>
        
        <?php
        
    }
    
    public function input($name,$args)
    {
        $args['name']   = $name;
        self::_getInput($args);
    }
    
    public function hidden($name,$value)
    {
        $args['name']   = $name;
        $args['type']   = 'hidden';
        $args['std']    = $value;
        self::_getInput($args);
    }
    
    public function select($name,$options,$args)
    {        
        $args['name']   = $name;
        $args['type'] ='select';
        $args['options'] = $options;
        self::_getInput($args);
    }
    
    public function radio($name,$options,$args)
    {
        $args['name']   = $name;
        $args['type'] ='radio';
        $args['options'] = $options;
        self::_getInput($args);
    }
    
    public function textarea($name,$args)
    {
        $args['name']   = $name;
        $args['type'] ='textarea';
        self::_getInput($args);
    }
    public function checkbox($name,$args)
    {
        $args['name']   = $name;
        $args['type'] ='checkbox';
        self::_getInput($args);
    }
    
    public function date($name,$args)
    {
        $args['name']   = $name;
        $args['class'] .= ' at-date';
        $args['attrs']  = ' rel="'.($args['format']? $args['format']: 'yy-mm-dd').'" ';
        self::_getInput($args);
        self::$has_calendar = true;
    }
    
    public function box($label,$args=array())
    {
        $args = wp_parse_args($args,array('class'=>'','border'=>0,'postbox'=>0));
        if($args['border'])$args['class'].=' border';
        if($args['postbox'])$args['class'].= ' postbox';        
        if(self::$displaytype=='table')echo '<tr><td colspan="2">' ; 
        
        echo '<div class="box_form needpostboxes  meta-box-sortables">';        
        echo '<div class=" '.@$args['class'].'">';
            if($args['postbox'])echo '<div title="Click to toggle" class="handlediv"><br></div>';
            echo '<h3 class="hndle"><span>'.$label.'</span></h3>';
            echo '<div class="inside">';
                if(self::$displaytype=='table')echo '<table class=" custom-meta-tbl" style="width:100%">';
    }
    public function endbox()
    {
            if(self::$displaytype=='table')echo '</table>';
            echo '</div></div>';
        echo '</div>';
        if(self::$displaytype=='table')echo '</td></tr>';        
    }
}


