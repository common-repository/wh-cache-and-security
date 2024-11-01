<?php
/**
 * Create meta boxes
 * 
    $meta_boxs = array();
    $meta_boxs[]=array(
    	'id'=>'page-meta-box-3',
    	'title'=>'Meta Data',
    	'page'=> 'cpt_showcase',
    	'context'=>'normal',
    	'priority'=>'high',
    	'fields'=>array(                
                    array(
                        'id'=>"_otherimages",
                        'label'=>"Images:",
                        'type'=>"imgbox",
                        'desc' => ''
    				),
    		)
    );
    
    foreach($meta_boxs as $meta_box)
        new TR_Meta_Box_V8($meta_box);
 */
 
class TR_Meta_Box_V9 {

    protected $_meta_box;
    var $has_date = false;
    var $has_time = false;

    // create meta box based on given data
    function __construct($meta_box) 
    {
        if (!is_admin()) return;

        $this->_meta_box = $meta_box;
		
        $this->add();

	add_action('save_post', array(&$this, 'save'));
        
        $this->folder_root = dirname(dirname(__FILE__)).'/';
       
        if (stripos(__FILE__,'wp-content/themes') !==false || stripos(__FILE__,'wp-content\themes') !==false){
            $this->SelfPath = get_stylesheet_directory_uri() . '/admin';
        }
        else{
            $this->SelfPath = plugins_url( 'admin', plugin_basename( $this->folder_root ) );
        }
    }
    
	/// Add meta box for multiple post types
    function add() 
    {
        $this->_meta_box['context'] = empty($this->_meta_box['context']) ? 'normal' : $this->_meta_box['context'];
        $this->_meta_box['priority'] = empty($this->_meta_box['priority']) ? 'high' : $this->_meta_box['priority'];
        if(!is_array($this->_meta_box['page']))
        {
            $this->_meta_box['page'] = array($this->_meta_box['page']);
        }
        foreach ($this->_meta_box['page'] as $page) {
                add_meta_box($this->_meta_box['id'], $this->_meta_box['title'], array(&$this, 'show'), $page, $this->_meta_box['context'], $this->_meta_box['priority']);
        }
    }

	// Callback function to show fields in meta box
    function show() {
        global $post;
        
        //add_css
        wp_enqueue_style( 'Admin_Page_Class', $this->SelfPath . '/css/admin.css',array(),'5.0');
        wp_enqueue_script( 'admin_post_meta', $this->SelfPath . '/js/post.js',array(),'5.0' );
        if (! did_action( 'wp_enqueue_media' ) )
        {            
            add_thickbox();
            wp_enqueue_media(  array('post' => $post->ID) );
        }

        // Use nonce for verification
        echo '<input type="hidden" name="wp_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';

        echo '<table class=" custom-meta-tbl" style="width:100%">';

        foreach ($this->_meta_box['fields'] as $field) {
                // get current post meta data
            if(isset($field['disabled']) && $field['disabled']===true)continue;
            
                $meta = get_post_meta($post->ID, $field['id'], true);
            if(empty($field['name']))$field['name'] = $field['id'];
            
            if(empty($field['label']) || $field['type']=='wysiwyg')
            {
                echo '<tr><td colspan="2">';
            }else
            {
                echo '<tr>',
                '<th style="width:20%"><label for="', $field['id'], '">', $field['label'], '</label></th>',
                '<td>';
            }
            
            $field['field_data'] = '';
            if($field['filetype'])
            {
                if($field['filetype']=='pdf')$field['filetype']='application/pdf';
                $field['field_data'].=' data-filetype="'.$field['filetype'].'" ';
            }else if(isset($field['options']) && is_string($field['options']) && function_exists($field['options']))
            {
                $field['options'] = call_user_func($field['options']);
            }
		
            switch ($field['type']) {
                case 'text':
                        echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="',  $meta ? esc_attr($meta) : esc_attr($field['std']), '" size="30" style="width:97%" />',
                                '<br />', $field['desc'];
                        break;
                case 'date':
                    $this->has_date = true;
                    if(empty($field['format']))$field['format'] = 'dd/mm/yy';
                    echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="',  $meta ? esc_attr($meta) : esc_attr($field['std']), '" size="30" style="width:200px" class="at-date" rel="',$field['format'],'"/>',
                    '<br />', $field['desc'];
                    break;
                case 'excludedate':
                    if(empty($field['format']))$field['format'] = 'dd/mm/yy';
                    if(isset($field['post_parent']))
                    {
                        global $post;
                        if($post->post_parent != $field['post_parent'])break;
                    }
                    ?>
                    <ul id="list_exclude_date">
                        <?php
                        if(!is_array($meta))$meta = array();
                        foreach($meta as $ed)
                        {
                            $ed = trim($ed);
                            $fromto = explode('-',$ed,2);
                            if(count($fromto)==2 && !empty($fromto[0]))
                            {
                                $from = $fromto[0];
                                $to   = $fromto[1];
                                $display = $from .' - '.$to;
                            }else
                            {
                                $display = $ed;
                            }
                            ?>

                            <li class="delem">
                                <?php echo $display?>
                                <input type="hidden" name="<?php echo $field['id']?>[]" value="<?php echo $ed?>" />
                                <a class="removeel"></a>
                            </li>


                        <?php
                        }
                        ?>
                    </ul>
                    <div class="formadd">
                        From: <input type="text" id="fromdate" class="at-date" rel="<?php echo $field['format']?>" />
                        - To: <input type="text" id="todate" class="at-date" rel="<?php echo $field['format']?>" />
                        <input type="button" class="button" value="Add" id="add_date_button" />
                        <script>
                            (function($){
                                $('#add_date_button').click(function(){
                                    var fr = $('#fromdate').val();
                                    var to = $('#todate').val();
                                    if(fr=='')
                                    {
                                        return false;
                                    }
                                    if(to!='')fr+=' - '+to;
                                    var ne = $('<li class="delem">'+fr+'<input type="hidden" name="<?php echo $field['id']?>[]" value="'+fr+'" /><a class="removeel"></a></li>');
                                    $('#list_exclude_date').append(ne);
                                    $('#fromdate').val('');
                                    $('#todate').val('');
                                });
                                $('a.removeel').live('click',function(){
                                    $(this).parents('li').remove();
                                })
                            })(jQuery)
                        </script>
                    </div>
                    <?php
                    break;
                case 'time':
                    $this->has_time = true;
                    if(empty($field['format']))$field['format'] = 'hh:mm';
                    echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="',  $meta ? esc_attr($meta) : esc_attr($field['std']), '" size="30" class="at-time" rel="',$field['format'],'" data-amp="',$field['amp'],'" />',
                    '<br />', $field['desc'];
                    break;
                case 'texts':
                    echo '<table class="subtbl">';
                    foreach($field['options'] as $title => $vl):
                        echo '<tr><td>'.$title.': </td>';
                        echo '<td><input type="text" name="', $field['id'], '[',$title,']" id="', $field['id'], '" value="',  $meta[$title] ? esc_attr($meta[$title]) : esc_attr($vl), '" size="30" style="width:97%" />';
                        echo '</td>';
                    endforeach;
                    echo '</table>';
                    echo '<br />', $field['desc'];
                    break;
                    
                case 'function':
                    call_user_func($field['function'],$post,$field);
                    break;
                case 'textarea':
                        echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="6" style="width:97%;'.(!empty($field['height'])?'height:'.$field['height']:'').'">', $meta ? $meta : $field['std'], '</textarea>',
                                '<br />', $field['desc'];
                        break;
                case 'select':
                        echo '<select name="', $field['id'], '" id="', $field['id'], '">';

                        foreach ($field['options'] as $id => $opt) {

                            $option['value'] = is_array($opt) && isset($opt['value'])? $opt['value'] : $id;
                            $option['name'] = is_array($opt) && isset($opt['name'])? $opt['name'] : $opt;

                            echo '<option value="', $option['value'], '"', $meta == $option['value'] ? ' selected="selected"' : '', '>', $option['name'], '</option>';
                        }
                        echo '</select>';
                        break;
                case 'radio':
                        foreach ($field['options'] as $option) {
                                echo '<input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $meta == $option['value'] ? ' checked="checked"' : '', ' />', $option['name'];
                        }
                        break;
                case 'checkbox':
                        echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />';
                        break;
                case 'checkboxes':
                    $meta = is_array($meta)? $meta : array();
                    foreach($field['options'] as $id => $opt)
                    {
                        $option['value'] = is_array($opt) && isset($opt['value'])? $opt['value'] : $id;
                        $option['name'] = is_array($opt) &&  isset($opt['name'])? $opt['name'] : $opt;
                        
                        if($field['first'])
                        {
                            echo '<label '.$field['labelcss'].'>';
                            echo ' <input type="checkbox" name="', $field['id'], '[]" id="', $field['id'], '"', (in_array($option['value'],$meta)) ? ' checked="checked"' : '', ' value="',$option['value'],'" />';
                            echo ' '.$option['name'];
                            echo '</label>';
                            if($field['br'])echo '<br>';
                        }else
                        {
                            echo '<label '.$field['labelcss'].'>',$option['name'];
                            if($field['br'])echo '<br>';
                            echo ' <input type="checkbox" name="', $field['id'], '[]" id="', $field['id'], '"', (in_array($option['value'],$meta)) ? ' checked="checked"' : '', ' value="',$option['value'],'" />';
                            echo '</label>';
                        }
                        
                    }
                    break;
				case 'wysiwyg':
                    //echo $field['std'];
                    echo '<label for="', $field['id'], '">', $field['label'], '</label>';
                    echo '<div class="postarea">';
                    $text = ($meta ? $meta : $field['std']);
                    wp_editor($text,$field['id']);
					echo '</div>';
                    echo $field['desc'];
					break;
                case 'imageurl':
                    $title = $field['title']? $field['title'] : 'Upload Image';
                                         
                    ?>
                    <input type="text" name="<?php echo $field['id']?>" value="<?php echo $meta?>" style="width: 80%;" />                   
                    <a rel="<?php echo $field['id']?>" class="upload_image_button button" <?php echo $field['field_data']?>  data-title="<?php echo $field['label']? $field['label'] : 'Insert Media'?>" href="#upload"><?php echo $title?></a>
                    
                    <?php
					echo '<br>', $field['desc'];
                    break;
                case 'file':
                case 'img':
				case 'image':
                    if(!empty($meta))
                    {
                        $img_link = wp_get_attachment_image_src($meta);
                        $img_link = $img_link[0];
                        
                        if(empty($img_link))
                         {
                            $file = wp_get_attachment_url($meta);
                            $name = get_post($meta);
                            $name = $name->post_title;
                         }
                    }
                    $title = $field['title']? $field['title'] : 'Upload Image';
                     
                    echo '<span id="imagelist_'.$field['id'].'">';
                    if(!empty($meta))
                    {
                        if(intval($meta)==0)continue;
                        
                         $gThumb = wp_get_attachment_image_src($meta);
                         if(empty($gThumb[0]))
                         {
                            $file = wp_get_attachment_url($meta);
                            $name = get_post($meta);
                            $name = $name->post_title;
                         }
                    ?>
                    <span class="imagelist listimg">
                        <input type="hidden" name="<?php echo $field['id']?>" value="<?php echo $meta?>" />
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
                    <a rel="<?php echo $field['id']?>" class="upload_image_button button" <?php echo $field['field_data']?>  data-title="<?php echo $field['label']? $field['label'] : 'Insert Media'?>" href="#upload"><?php echo $title?></a>
                    
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
                
               
			}
			echo 	'<td>',
				'</tr>';
		}

		echo '</table>';
        if($this->_meta_box['richbox'] == 'hide')echo '<style type="text/css">#postdivrich {display:none;}</style>';
        if($this->has_date || $this->has_time)
        {
            wp_enqueue_style('rw-jquery-ui-css', $this->SelfPath . '/css/jquery-ui.css');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-slider');
            wp_enqueue_script('jquery-ui-datepicker');
        }
        if($this->has_time)
        {
            wp_enqueue_script( 'at-timepicker', $this->SelfPath . '/js/time-and-date/jquery-ui-timepicker-addon.js', array( 'jquery' ), null, true );
        }
    }
    
    function creatImages($meta,$field)
    {
        global $post;
        if(!is_array($meta))$meta = array();
        $meta = (array)$meta;
        $title = $field['title']? $field['title'] : 'Upload Image';
        echo '<div id="imagelist_'.$field['id'].'" class="ui-sortable">';
        foreach($meta as $att_id)
        {
            if(intval($att_id)==0)continue;
            
             $gThumb = wp_get_attachment_image_src($att_id);
             if(empty($gThumb[0]))
             {
                $file = wp_get_attachment_url($att_id);
                $name = get_post($att_id);
                $name = $name->post_title;
             }
        ?>
        <span class="imagelist listimg">
            <input type="hidden" name="<?php echo $field['id']?>[]" value="<?php echo $att_id?>" />
            <?php if($gThumb[0]):?>
                <img src="<?php echo $gThumb[0]?>" width="50" height="50"/>
            <?php else:?>
                <span class="filename"><a href="<?php echo $file?>" target="_blank"><?php echo $name?></a></span>
            <?php endif;?>
            <a rel="<?php echo $att_id?>" pid="<?php echo $post->ID ?>" class="removeimg">Remove</a>
        </span>
        <?php
        }
        echo '</div><div class="clear">&nbsp;</div>';
        ?>
        <a rel="<?php echo $field['id']?>" class="upload_image_button button" <?php echo $field['field_data']?> data-multiple="1"  data-title="<?php echo $field['label']? $field['label'] : 'Insert Media'?>" href="#upload"><?php echo $title?></a>
        <script>
        jQuery(function(){
            jQuery("#<?php echo 'imagelist_'.$field['id']?>").sortable({
                cursor: 'move'
            });
        })
        </script>
        <?php
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
                    <label><input type="checkbox" <?php if($meta[$id]['id'])echo 'checked'?> name="<?php echo $field['id']?>[<?php echo $id?>][id]" value="<?php echo $id?>" /> <?php echo $text?></label>
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

    // Save data from meta box
    function save($post_id) {
            // verify nonce
            if (!wp_verify_nonce($_POST['wp_meta_box_nonce'], basename(__FILE__))) {
                return $post_id;
            }

            // check autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return $post_id;
            }

            // check permissions
            if ('page' == $_POST['post_type']) {
                if (!current_user_can('edit_page', $post_id)) {
                        return $post_id;
                }
            } elseif (!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }

        foreach ($this->_meta_box['fields'] as $field) {
            $name = $field['id'];

            $old = get_post_meta($post_id, $name, true);
            $new = $_POST[$field['id']];

            if ($field['type'] == 'wysiwyg') {
                    //$new = wpautop($new);
            }
            else if ($field['type'] == 'textarea') {
                    $new = htmlspecialchars($new);
            }
            else if($field['type']=='function' && !empty($field['function_filter']))
            {
                $ok = apply_filters($field['function_filter'],true,$post_id,$field);
                if($ok===false)continue;
            }



            // validate meta value
            if (isset($field['validate_func'])) {
                    $ok = call_user_func(array('Ant_Meta_Box_Validate', $field['validate_func']), $new);
                    if ($ok === false) { // pass away when meta value is invalid
                            continue;
                    }
            }

            if(isset($field['filter']))
            {
                $new = apply_filters($field['filter'],$new,$old,$post_id);
            }

            if ($new!='' && $new != $old) {
                    update_post_meta($post_id, $name, $new);
            } elseif ('' == $new && $old && $field['type'] != 'file' && $field['type'] != 'image') {
                    delete_post_meta($post_id, $name, $old);
            }
        }
    }
	
}




