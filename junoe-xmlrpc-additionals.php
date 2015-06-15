<?php
/*
Plugin Name: Junoe XMLRPC Additional Methods
Plugin URI: https://wordpress.org/plugins/junoe-xmlrpc-additional-methods/
Description: Add yet another XML-RPC methods
Version: 1.0.1
Author: ITOH Takashi
Author URI: http://d.hatena.ne.jp/tohokuaiki/
*/
/*  Copyright 2015 ITOH Takashi (itoh@tohokuaiki.jp)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
add_filter('xmlrpc_methods', 'add_junoe_xmlrpc_methods');
add_action('xmlrpc_call',    'junoe_generic_xmlrpc_call');

$junoe_additiona_xmlrpc_methods = array(
    'wp.JdeleteAllPage'     => 'junoe_wp_deleteAllPage',
    'wp.JcheckAdminAccount' => 'junoe_wp_checkAdminAccount',
    'wp.JpluginInfo'        => 'junoe_wp_getPluginInfo',
    'wp.JgetAllPageByJSON'  => 'junoe_wp_getAllPageContentsByJson',
    'wp.JaddNewBlog'        => 'junoe_wp_addNewBlog',
    'wp.JupdateBlog'        => 'junoe_wp_updateBlog',
    'wp.JdeleteBlog'        => 'junoe_wp_deleteBlog',
    'wp.JpluginActivate'    => 'junoe_wp_pluginActivate',
    'wp.JgetPageBySlug'     => 'junoe_wp_getPageBySlug',
    );
/**
 * @brief メソッドの追加
 * @param Array 定義済みメソッド
 * @retval Array 定義追加済みメソッド
 * WPLOG_ERR
 * WPLOG_WARNING
 * WPLOG_INFO
 * WPLOG_DEBUG
 */
function add_junoe_xmlrpc_methods($methods)
{
    global $junoe_additiona_xmlrpc_methods ;
    
    return array_merge($methods, $junoe_additiona_xmlrpc_methods);
}


/**
 * @brief このプラグインの情報を取得する
 * @param 
 * @retval
 */
function junoe_wp_getPluginInfo($args){
    foreach (file(__FILE__) as $line){
        if (preg_match('/^Version:\s*([\d\.]+)$/', trim($line), $m)){
            return $m[1];
        }
    }
    return new IXR_Error(-9900002, 'plugin not installed');
}

/**
 * @brief check account has administrator privilege or not.
 * @param 
 * @retval
 */
function junoe_wp_checkAdminAccount($args)
{
    global $wp_xmlrpc_server;
    
    $blog_ID     = (int) $args[0];
    $username  = $args[1];
    $password   = $args[2];
    
    $is_admin = false;
    
    $current_user = $wp_xmlrpc_server->login($username, $password);
    if ( is_object($current_user) ) {
        if ($current_user->has_cap("administrator")){
            $is_admin = true;
            return $is_admin;
        }
        else {
            return new IXR_Error(-9900001, "$username is not administrator cap.");
        }
    }
    else {
        return $wp_xmlrpc_server->error ;
    }
    
    return $is_admin;
}


/**
 * @brief 
 * @param 
 * @retval
 */
function junoe_wp_deleteAllPage($args)
{
    global $wp_xmlrpc_server, $current_user, $wp, $wp_query, $wp_the_query, $wpdb;

    $wp_xmlrpc_server->escape($args);

    $blog_ID     = (int) $args[0];
    $username  = $args[1];
    $password   = $args[2];


    if ( !$current_user = $wp_xmlrpc_server->login($username, $password) ) {
        return $wp_xmlrpc_server->error;
    }

    
    $wp->parse_request(array(
        'post_type' => 'page',
        'posts_per_page' => 100000,
        'posts_per_archive_page' => 100000,
        'order' => 'asc',
        ));
    $wp->query_posts();
    
    $page_id = array();
    $deleted = 0;
    $msg = "";
    
    if ($current_user->has_cap("administrator")){
        // 遅すぎるので一括Query
        foreach ((array) $wp_query->posts as $post){
            $page_id[] = intval($post->ID);
        }
//        $sql = sprintf("DELETE FROM %s WHERE post_type='page' AND `ID` IN (%s)", $wpdb->posts, implode(",", $page_id));
        $sql = sprintf("DELETE FROM %s WHERE 1", $wpdb->posts);
        
        $wpdb->query($sql);
        $deleted = $wpdb->rows_affected;
        if (function_exists('junoe_clear_page_rewrite_cache_file')){
            junoe_clear_page_rewrite_cache_file();
        }
/* 
        foreach ((array) $wp_query->posts as $post){
            
            $post_id = $post->ID;
            $post_del = & get_post($post_id);
            logIO("O", $post_id);
            logIO("O", get_class($post_del));

            if ( !current_user_can('delete_page', $post_id) )
              wp_die( __('You are not allowed to delete this page.') );
            
            if ( $post_del->post_type == 'attachment' ) {
                if ( ! wp_delete_attachment($post_id, true) )
                  wp_die( __('Error in deleting...') );
            } else {
            if ( !wp_delete_post($post_id, true) ){
              wp_die( __('Error in deleting...') );
            }
            $deleted++;
        }
 */
    }
    
    return $deleted;
}



/**
 * @brief create new blog
 * @param 
 * @retval
 */
function junoe_wp_addNewBlog($args)
{
    global $wp_xmlrpc_server, $current_site;
    
    $username   = $args[0];
    $password   = $args[1];
    $newdomain  = $args[2]; // wp-samle.example.com
    $path       = $args[3]; // /wp3/example-wp3/7/
    $title      = $args[4];
    
    $path = rtrim($path, '/'). '/';
    
    $ret = array("result" => false, "error" => "", "blog_id" => 0);
    
    $current_user = $wp_xmlrpc_server->login($username, $password);
    $user_id      = $current_user->ID;
    
    if ( is_object($current_user) ) {
        if ($current_user->has_cap("administrator")) 
        {
            $id = wpmu_create_blog( $newdomain, $path, $title, $user_id , array( 'public' => 1 ));
            if ( !is_wp_error( $id ) ) {
                if ( !is_super_admin( $user_id ) && !get_user_option( 'primary_blog', $user_id ) )
                  update_user_option( $user_id, 'primary_blog', $id, true );
                $content_mail = sprintf( __( "New site created by %1s\n\nAddress: http://%2s\nName: %3s"), $current_user->user_login , $newdomain . $path, stripslashes( $title ) );
                wp_mail( get_site_option('admin_email'), sprintf( __( '[%s] New Site Created' ), $current_site->site_name ), $content_mail, 'From: "Site Admin" <' . get_site_option( 'admin_email' ) . '>' );
                wpmu_welcome_notification( $id, $user_id, $password, $title, array( 'public' => 1 ) );
                $ret['result']  = true;
                $ret['blog_id'] = $id;
            } else {
                $ret['error']  = $id->get_error_message();
            }
        }
    }
    
    return $ret;
}


/**
 * @brief update blog information
 * @param 
 * @retval
 */
function junoe_wp_updateBlog($args)
{
    global $wp_xmlrpc_server, $current_site;

    $username   = $args[0];
    $password   = $args[1];
    $newdomain  = $args[2]; // wp-samle.example.com
    $path       = $args[3]; // /wp3/example-wp3/7/
    $title      = $args[4];
    $status     = $args[5]; // true or false
    $blog_id    = $args[6]; // 1,2,....
    
    $path = rtrim($path, '/'). '/';
    
    $ret = array("result" => false, "error" => "", "blog_id" => $blog_id);
    
    $current_user = $wp_xmlrpc_server->login($username, $password);
    
    if ( is_object($current_user) ) {
        if ($current_user->has_cap("administrator")) 
        {
            $id = $blog_id;
            switch_to_blog( $id );

//            if ( isset( $_POST['update_home_url'] ) && $_POST['update_home_url'] == 'update' ) {
            if (true){
                $blog_address = get_blogaddress_by_domain( $newdomain, $path );
                if ( get_option( 'siteurl' ) != $blog_address )
                  update_option( 'siteurl', $blog_address );

                if ( get_option( 'home' ) != $blog_address )
                  update_option( 'home', $blog_address );
            }

            // rewrite rules can't be flushed during switch to blog
            delete_option( 'rewrite_rules' );

            // update blogs table
            $blog_detail = array(
                'domain' => $newdomain,
                'path'   => $path,
                'last_updated' => date('Y-m-d H:i:s'),
                'public' => true,
                );
            
            $blog_data = stripslashes_deep( $blog_detail );
            $existing_details = get_blog_details( $id, false );
            $blog_data_checkboxes = array( 'public', 'archived', 'spam', 'mature', 'deleted' );
            foreach ( $blog_data_checkboxes as $c ) {
                if ( ! in_array( $existing_details->$c, array( 0, 1 ) ) )
                  $blog_data[ $c ] = $existing_details->$c;
                else
                  $blog_data[ $c ] = isset( $blog_detail[ $c ] ) ? 1 : 0;
            }
            update_blog_details( $id, $blog_data );
            
            update_option( 'blogname', $title);
            
            // status
            if ($status == false){
                do_action( 'deactivate_blog', $blog_id );
                update_blog_status( $blog_id, 'deleted', '1' );
            }
            else {
                update_blog_status( $blog_id, 'deleted', '0' );
                do_action( 'activate_blog', $blog_id );
            }
            
            restore_current_blog();
            $ret['result']  = true;
            
            // require_once dirname(__FILE__).'/junoe-ms-addhooks.php';
            // junoe_site_onchange_hook($blog_id);
        }
    }
    
    return $ret;
}




/**
 * @brief delete blog
 * @param 
 * @retval
 */
function junoe_wp_deleteBlog($args)
{
    global $wp_xmlrpc_server, $current_site;
    
    $username   = $args[0];
    $password   = $args[1];
    $blog_id    = $args[2];
    
    $ret = array("result" => false, "error" => "", "blog_id" => $blog_id);
    
    $current_user = $wp_xmlrpc_server->login($username, $password);
    
    $id = $blog_id ;
    $blog_details = get_blog_details($id);
    $dirs  = array();
    $files  = array();
    if (is_object($blog_details)) {
        $blog_path = ABSPATH. basename($blog_details->path, '/');
        foreach (list_files($blog_path) as $f){
            $f = str_replace(ABSPATH, '', $f);
            $files[] = $f;
            $dir = dirname($f);
            $dirs[$dir] = true;
            $dirs[$dir] = true;
            $j = count(explode("/", $dir));
            for ($i=0; $i<$j-1; $i++){
                $dir = dirname($dir);
                $dirs[$dir] = true;
            }
        }
        $dirs = array_keys($dirs);
        rsort($dirs);
    }
    else {
        $ret['result'] = true; // already removed ?
    }
    
    if ( $id != '0' && current_user_can( 'delete_site', $id ) ) {
        wpmu_delete_blog( $id, true );
        $ret['result'] = true;
        $ret['files'] = $files;
        $ret['dirs']  = $dirs;
    }
    
    return $ret;
}


/**
 * @brief get all page contents by json
 * @param $args     0=> $blog_ID , 1=> $username, 2=> $password
 * @retval
 */
function junoe_wp_getAllPageContentsByJson($args)
{
    if (!function_exists('json_encode') && !extension_loaded('json') && !dl('json.so')){
        return new IXR_Error(-9900002, "This WordPress Server does not support PHP-Json module.");
    }
    
    
    $numberposts = 1000; // max 1000pages
    $qurey = sprintf('numberposts=%d&orderby=post_name',
                     $numberposts);
    
    $defaults = array(
        'depth' => 0, 'show_date' => '',
        'date_format' => get_option('date_format'),
        'child_of' => 0, 'exclude' => '',
        'title_li' => __('Pages'), 'echo' => 1,
        'authors' => '', 'sort_column' => 'menu_order, post_title',
        'link_before' => '', 'link_after' => '', 'walker' => '',
        'hierarchical' => 0,
        );
        
    //$defaults['hierarchical'] = 1;
    $defaults['sort_column'] = 'post_title';
    $defaults['sort_order'] = 'ASC';
    
    
    // Query pages.
    $pages = array();
    $ps = get_pages($defaults);
    $pages_key = array();
    foreach ($ps as $k=>$p){
        $pages_key[$p->ID] = array(
            'parent' => $p->post_parent,
            'slug'   => $p->post_name,
            );
    }
    
    foreach ($ps as $k=>$p){
        $slug = implode('/', array_reverse(_getWordpressPagePath($pages_key, $p->ID)));
        $pages[$k] = array(
            'id'    => $p->ID,
            'title' => $p->post_title,
            'text'  => isset($p->post_search_field) ? $p->post_search_field : strip_tags($p->post_content),
            'slug'  => $slug,
            'parent'=> $p->post_parent,
            );
    }
    
    return json_encode($pages);
}


/**
 * @brief 
 * @param array ID=>$postobject な配列
 * @param array 
 * @param int 探求したいID
 * @retval
 */
function _getWordpressPagePath(&$page_info, $page_ID, $nice_path = array())
{
    if (isset($page_info[$page_ID])){
        $nice_path[] = $page_info[$page_ID]['slug'];
        if ($page_info[$page_ID]['parent']){
            return _getWordpressPagePath($page_info, $page_info[$page_ID]['parent'], $nice_path);
        }
    }
    
    return $nice_path;
}

// $GLOBALS['xmlrpc_logging'] = 1;
// logIO('I', sprintf('blog_id => %s, path => %s, title => %s', $blog_id, $path, $title ));



/**
 * @brief get page_id by slug
 * @param $args     0=> $blog_ID , 1=> $username, 2=> $password, 3 => $slug
 * @retval
 */
function junoe_wp_getPageBySlug($args)
{
    global $wp_xmlrpc_server;
    
    $blog_id = $args[0];
    switch_to_blog( $blog_id );
    
    $param = array(
        'name'        => $args[3],
        'post_type'   => 'page',
        'post_status' => 'publish',
        'showposts'   => 1,
        );
    
    $pages = get_posts($param);
    if (count($pages) == 0){
        return array();
    }
    return $pages[0];
}




/**
 * @brief プラグインをActivate/Deactivateする
 * @param 
 * @retval
 */
function junoe_wp_pluginActivate($args)
{
    global $wp_xmlrpc_server;
    
    $blog_ID     = (int) $args[0];
    $username    = $args[1];
    $password    = $args[2];
    $plugin      = $args[3];
    $activate    = $args[4];
    
    switch_to_blog( $blog_ID );
    
    if ( !$current_user = $wp_xmlrpc_server->login($username, $password) ) {
        return $wp_xmlrpc_server->error;
    }
    if ($current_user->has_cap("administrator"))
    {
//        $r = chdir(dirname(dirname(dirname(__FILE__))).'/wp-admin');
//        require_once './plugins.php'; 
//        return array('acti' => $activate, 'chdir' => $r);
        
        $ret = array('result' => false);
        if ($activate) {
            $result = activate_plugin($plugin);
            if (! is_wp_error( $result ) ) {
                $ret['result'] = true;
                $recent = (array)get_option('recently_activated');
                if ( isset($recent[ $plugin ]) ) {
                    unset($recent[ $plugin ]);
                    update_option('recently_activated', $recent);
                }
            }
        }
        else {
            deactivate_plugins($plugin);
            update_option('recently_activated',
                          array($plugin => time()) + (array)get_option('recently_activated'));
        }
        return $ret;
    }
    else {
        return new IXR_Error(-9900001, "$username is not administrator cap.");    
    }
}



/**
 * @brief XML-RPCをcallする前のAction
 * @param 
 * @retval
 */
function junoe_generic_xmlrpc_call($method)
{
    global $HTTP_RAW_POST_DATA;
    if (empty($HTTP_RAW_POST_DATA)) {
        // workaround for a bug in PHP 5.2.2 - http://bugs.php.net/bug.php?id=41293
        $data = file_get_contents('php://input');
    } else {
        $data =& $HTTP_RAW_POST_DATA;
    }
    $message = new IXR_Message($data);
    $message->parse();
    $params = $message->params;

    switch ($method){
      case "wp.newCategory":
        if (is_multisite()) {
            $blog_id = $params[0];
            switch_to_blog($blog_id);
            break;
        }
    }
}
