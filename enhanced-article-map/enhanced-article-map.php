<?php
/**
 * Plugin Name: Enhanced Article Map
 * Description: ShowArticleMap を拡張し、フィルタリング、カテゴリごとの色分け、タイトルのみの表示、内部リンク候補の提案機能を追加します。
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 直接アクセスを防止
}

/**
 * フロントエンド用のスクリプトとスタイルを読み込み
 */
function eam_enqueue_scripts() {
    wp_enqueue_script( 'eam-script', plugin_dir_url( __FILE__ ) . 'js/eam-script.js', array('jquery'), '1.0', true );
    wp_enqueue_style( 'eam-style', plugin_dir_url( __FILE__ ) . 'css/eam-style.css' );
}
add_action( 'wp_enqueue_scripts', 'eam_enqueue_scripts' );

/**
 * ショートコード [enhanced_article_map] で記事マップを表示（タイトルのみ、カテゴリごとの色分け）
 */
function eam_display_article_map() {
    // 公開済み記事を全件取得
    $args = array(
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    );
    $posts = get_posts( $args );

    $output = '<div id="article-map">';
    foreach ( $posts as $post ) {
        // 各記事のカテゴリ情報取得（ここでは最初のカテゴリのみ使用）
        $categories = get_the_category( $post->ID );
        $color = '#000000'; // デフォルトの文字色
        if ( ! empty( $categories ) ) {
            $cat_id = $categories[0]->term_id;
            // タームメタ "category_color" に設定したカラーコードを取得（なければデフォルト）
            $cat_color = get_term_meta( $cat_id, 'category_color', true );
            if ( $cat_color ) {
                $color = $cat_color;
            }
        }
        // 複数のカテゴリがある場合、フィルタリング用にスラッグをカンマ区切りで保持
        $cat_slugs = array();
        foreach ( $categories as $cat ) {
            $cat_slugs[] = $cat->slug;
        }
        $output .= '<div class="article-item" data-categories="' . esc_attr( implode( ',', $cat_slugs ) ) . '" style="color:' . esc_attr( $color ) . ';">';
        $output .= '<span class="article-title">' . esc_html( get_the_title( $post->ID ) ) . '</span>';
        $output .= '</div>';
    }
    $output .= '</div>';
    return $output;
}
add_shortcode( 'enhanced_article_map', 'eam_display_article_map' );

/**
 * 投稿編集画面に内部リンク候補のメタボックスを追加
 */
function eam_add_meta_box() {
    add_meta_box( 'eam_internal_links', '内部リンク候補', 'eam_meta_box_callback', 'post', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'eam_add_meta_box' );

function eam_meta_box_callback( $post ) {
    // 管理画面内の投稿編集画面に表示。AJAXで候補を取得するためのエリアと更新ボタンを配置
    echo '<div id="eam-internal-links-suggestions">';
    echo '<p>候補を読み込み中...</p>';
    echo '</div>';
    echo '<button type="button" id="eam-refresh-links">候補を更新</button>';
}

/**
 * AJAX処理：投稿内容に基づく内部リンク候補の取得
 */
function eam_fetch_internal_links() {
    if ( ! isset( $_POST['post_id'] ) ) {
        wp_send_json_error( 'Missing post ID' );
    }
    $post_id = intval( $_POST['post_id'] );
    $current_post = get_post( $post_id );
    if ( ! $current_post ) {
        wp_send_json_error( 'Invalid post ID' );
    }

    // 現在の投稿以外の投稿を10件取得
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'post__not_in'   => array( $post_id )
    );
    $posts = get_posts( $args );
    $suggestions = array();

    // 現在の投稿の内容をテキストとして取得（小文字化・タグ除去）
    $current_content = strtolower( strip_tags( $current_post->post_content ) );
    foreach ( $posts as $post ) {
        $score = 0;
        $other_content = strtolower( strip_tags( $post->post_content ) );
        // 簡易的な類似度計算（単語ごとの出現回数による一致数）
        $current_words = array_count_values( preg_split( '/\s+/', $current_content ) );
        $other_words   = array_count_values( preg_split( '/\s+/', $other_content ) );
        foreach ( $current_words as $word => $count ) {
            if ( isset( $other_words[ $word ] ) ) {
                $score += min( $count, $other_words[ $word ] );
            }
        }
        // 任意の閾値（ここでは10以上）で内部リンク候補とする
        if ( $score > 10 ) {
            $suggestions[] = array(
                'ID'    => $post->ID,
                'title' => get_the_title( $post->ID ),
                'score' => $score,
            );
        }
    }
    wp_send_json_success( $suggestions );
}
add_action( 'wp_ajax_eam_fetch_internal_links', 'eam_fetch_internal_links' );
add_action( 'wp_ajax_nopriv_eam_fetch_internal_links', 'eam_fetch_internal_links' );

/**
 * 管理画面にプラグイン専用メニューを追加
 */
function eam_add_admin_menu() {
    add_menu_page(
        'Enhanced Article Map 設定',  // ページタイトル
        '記事マップ設定',               // メニュータイトル
        'manage_options',              // 権限
        'eam_settings',                // メニュースラッグ
        'eam_settings_page_callback',  // 表示用コールバック関数
        'dashicons-admin-generic',     // アイコン
        81                             // 表示位置（任意）
    );
}
add_action( 'admin_menu', 'eam_add_admin_menu' );

function eam_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1>Enhanced Article Map 設定</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'eam_settings_group' );
            do_settings_sections( 'eam_settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * オプションの登録（必要に応じて設定項目を追加）
 */
function eam_register_settings() {
    register_setting( 'eam_settings_group', 'eam_options' );
    add_settings_section( 'eam_main_section', '基本設定', null, 'eam_settings' );
    // 例: カテゴリカラーのデフォルト設定などをここで登録可能
}
add_action( 'admin_init', 'eam_register_settings' );

