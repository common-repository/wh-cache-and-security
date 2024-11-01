<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Cache_Query{


    function _get_cache($key,$limit=200,$json=true)
    {
        $data = false;
        $filepath = TRSCSC_CACHE_QUERIES.'/'.$key;
        $timeout = time() - $limit;
        if(file_exists($filepath) && filemtime($filepath)> $timeout)
        {
            $data = file_get_contents($filepath);
            if($json)
                $data = json_decode($data,true);
        }
        return $data;
    }

    function _set_cache($key,$data,$json=true)
    {
        if(!file_exists(TRSCSC_CACHE_QUERIES))
        {
            wp_mkdir_p(TRSCSC_CACHE_QUERIES);
        }
        $filepath = TRSCSC_CACHE_QUERIES.'/'.$key;
        if($data===false || (is_array($data) && count($data) == 0))
        {
            @unlink($filepath);
            return false;
        }
        if($json)
            $data = json_encode($data);
        return file_put_contents($filepath,$data);
    }

    public function get_results($sql,$output = OBJECT,$limit=200)
    {
        global $wpdb;
        $results = false;
        $query_key = md5($sql.$output).'.get_results';

        //get cache
        $results = $this->_get_cache($query_key,$limit);

        if(!$results){
            $results = $wpdb->get_results($sql,$output);

            //cache
            $this->_set_cache($query_key,$results);
        }

        return $results;
    }

    public function get_row($sql,$output = OBJECT,$y=0,$limit=200)
    {
        global $wpdb;
        $results = false;
        $query_key = md5($sql.$output).'.get_row';

        //get cache
        $results = $this->_get_cache($query_key,$limit);

        if(!$results){
            $results = $wpdb->get_row($sql,$output,$y);

            //cache
            $this->_set_cache($query_key,$results);
        }

        return $results;
    }

    public function get_var($sql,$x=0,$y=0,$limit=200)
    {
        global $wpdb;
        $results = false;
        $query_key = md5($sql.$output).'.get_var'.$x.$y;

        //get cache
        $results = $this->_get_cache($query_key,$limit,false);

        if($results ===false){
            $results = $wpdb->get_var($sql,$x,$y);

            //cache
            $this->_set_cache($query_key,$results,false);
        }

        return $results;
    }

    public function get_col($sql,$x=0,$limit=200)
    {
        global $wpdb;
        $results = false;
        $query_key = md5($sql.$output).'.get_col'.$x;

        //get cache
        $results = $this->_get_cache($query_key,$limit);

        if(!$results){
            $results = $wpdb->get_col($sql,$x);

            //cache
            $this->_set_cache($query_key,$results);
        }

        return $results;
    }
}