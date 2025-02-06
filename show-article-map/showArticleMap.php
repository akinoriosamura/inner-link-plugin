<?php
/*
Plugin Name: Show Article Map
Plugin URI: https://www.naenote.net/entry/show-article-map
Description: 投稿・固定ページ間の内部リンクを可視化するプラグインです。
Author: NAE
Version: 0.8
Author URI: https://www.naenote.net/entry/show-article-map
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // 直接アクセスを防止

// 指定の位置に文字列を挿入する関数
if ( ! function_exists( 'nae_insert_str' ) ) {
    function nae_insert_str( $text, $insert, $num ) {
        $returnText = $text;
        $text_len   = mb_strlen( $text, 'utf-8' );
        $insert_len = mb_strlen( $insert, 'utf-8' );
        for ( $i = 0; ($i + 1) * $num < $text_len; ++$i ) {
            $current_num = $num + $i * ($insert_len + $num);
            $returnText = preg_replace("/^.{0,$current_num}+\K/us", $insert, $returnText);
        }
        return $returnText;
    }
}

// 内部リンクデータ（ノード、エッジ）の取得
if ( ! function_exists( 'nae_get_dataset' ) ) {
    function nae_get_dataset() {
        $args_post = array(
            'posts_per_page' => -1,
            'post_type'      => 'post',
            'post_status'    => 'publish',
        );
        $args_page = array(
            'posts_per_page' => -1,
            'post_type'      => 'page',
            'post_status'    => 'publish',
        );
        $post_array = get_posts( $args_post );
        $page_array = get_pages( $args_page );
        $articles   = array_merge( $post_array, $page_array );
        $nodes      = array();
        $edges      = array();

        foreach ( $articles as $post ) {
            $category = get_the_category( $post->ID );
            $ancestors_cat_IDs = isset( $category[0] ) ? get_ancestors( $category[0]->cat_ID, 'category' ) : array();
            if ( empty( $ancestors_cat_IDs ) && ! empty( $category ) ) {
                $root_category_ID = $category[0]->cat_ID;
            } elseif ( ! empty( $ancestors_cat_IDs ) ) {
                $root_category_ID = array_pop( $ancestors_cat_IDs );
            } else {
                $root_category_ID = 0;
            }
            $root_category = ($root_category_ID > 0) ? get_category( $root_category_ID ) : null;
            $group_name = ! empty( $category ) && $root_category ? $root_category->slug : '固定ページ';

            $nodes[] = array(
                'id'    => $post->ID,
                'label' => nae_insert_str( urldecode( $post->ID . ':' . $post->post_name ), "\n", 20 ),
                'group' => urldecode( $group_name ),
                'title' => '<a href="' . get_permalink( $post ) . '" target="_blank">' . esc_html( $post->post_title ) . '</a>',
            );

            // [show_article_map] ショートコードを除去した本文を取得
            $post_content = str_replace( '[show_article_map]', '', $post->post_content );
            $html         = apply_filters( 'the_content', $post_content );

            // 本文が空の場合は DOM解析をスキップ
            if ( trim( $html ) === '' ) {
                continue;
            }

            $dom = new DOMDocument();
            // エラーを抑制しつつ、HTMLを読み込む
            @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
            $xpath = new DOMXPath( $dom );
            $query = "//a[
                        @href != ''
                        and not(starts-with(@href, '#'))
                        and normalize-space() != ''
                      ]";
            foreach ( $xpath->query( $query ) as $node ) {
                $href = $xpath->evaluate( 'string(@href)', $node );
                $linked_post_id = url_to_postid( $href );
                if ( 0 != $linked_post_id && ! in_array( array( 'from' => $post->ID, 'to' => $linked_post_id ), $edges ) ) {
                    $edges[] = array(
                        'from' => $post->ID,
                        'to'   => $linked_post_id,
                    );
                }
            }
        }
        return json_encode( array( $nodes, $edges ) );
    }
}

// ショートコードで出力する地図のHTML
if ( ! function_exists( 'nae_echo_article_map' ) ) {
    function nae_echo_article_map() {
        $dataset = nae_get_dataset();
        // JSONデータは非表示のdivに出力
        $dataset_html = '<div id="show-article-map-dataset" style="display:none;">' . esc_html( $dataset ) . '</div>';
        $html  = '<div>';
        $html .= '<div id="manipulationspace">
                    <div>
                        <label for="searchnodequery">Search by node name : </label>
                        <input id="searchnodequery" name="searchnodequery" size="30" style="display:inline;width:50% !important;" type="text">
                        <button id="searchnodebutton" type="submit">Search</button>
                    </div>
                    <div>
                        <label for="groupList">Toggle category / pages : </label>
                        <span id="groupList"></span>
                    </div>
                    <div>
                        <label for="toggleBlur">Toggle Blur : </label>
                        <button id="togglepBlur" type="submit">Stop</button>
                    </div>
                  </div>';
        $html .= '<div id="mynetwork" style="width: 100%; height: 800px; border: 1px solid lightgray;"></div>';
        $html .= '<div><a id="downloadCSV" href="#" download="ShowArticleMap.csv">Download CSV</a></div>';
        $html .= $dataset_html;
        $html .= '</div>';
        return $html;
    }
}

// フロントエンドで必要なCSS/JSをエンキュー
function nae_enqueue_article_map_scripts() {
    wp_enqueue_style( 'vis-css', 'https://cdnjs.cloudflare.com/ajax/libs/vis/4.20.0/vis.min.css', array(), '4.20.0' );
    wp_enqueue_script( 'vis-js', 'https://cdnjs.cloudflare.com/ajax/libs/vis/4.20.0/vis.min.js', array(), '4.20.0', true );
    wp_enqueue_script( 'showArticleMap', plugins_url( 'showArticleMap.js', __FILE__ ), array( 'jquery', 'vis-js' ), '0.8', true );
}
add_action( 'wp_enqueue_scripts', 'nae_enqueue_article_map_scripts' );

// ショートコード登録（フロントエンド用）
add_shortcode( 'show_article_map', 'nae_echo_article_map' );


// ---------- 管理画面にメニューを追加する処理 ----------

function nae_add_menu_page() {
    add_menu_page(
        '記事マップ',                  // ページタイトル
        '記事マップ',                  // メニュータイトル
        'manage_options',             // 必要な権限
        'show-article-map',           // メニュースラッグ
        'nae_article_map_admin_page', // 表示するコールバック関数
        'dashicons-networking',       // アイコン（WordPress Dashicons）
        6                             // 表示位置
    );
}
add_action( 'admin_menu', 'nae_add_menu_page' );

function nae_article_map_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( '記事マップ', 'show-article-map' ); ?></h1>
        <?php echo nae_echo_article_map(); ?>
    </div>
    <?php
}

// 管理画面用のCSS/JSをエンキュー
function nae_enqueue_admin_article_map_scripts( $hook ) {
    if ( $hook != 'toplevel_page_show-article-map' ) {
        return;
    }
    wp_enqueue_style( 'vis-css', 'https://cdnjs.cloudflare.com/ajax/libs/vis/4.20.0/vis.min.css', array(), '4.20.0' );
    wp_enqueue_script( 'vis-js', 'https://cdnjs.cloudflare.com/ajax/libs/vis/4.20.0/vis.min.js', array(), '4.20.0', true );
    wp_enqueue_script( 'showArticleMap', plugins_url( 'showArticleMap.js', __FILE__ ), array( 'jquery', 'vis-js' ), '0.8', true );
}
add_action( 'admin_enqueue_scripts', 'nae_enqueue_admin_article_map_scripts' );
?>
