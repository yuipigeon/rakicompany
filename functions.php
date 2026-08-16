<?php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('rakicompany-style', get_theme_file_uri('assets/css/style.css'), [], wp_get_theme()->get('Version'));
});


add_action('enqueue_block_editor_assets', function () {
    wp_dequeue_style('rakicompany-style');
});

add_action( 'wp_enqueue_scripts', function() {
  wp_enqueue_script(
      'fix-nav-animation',
      get_theme_file_uri( '/assets/js/main.js' ),
      array(),
      wp_get_theme()->get( 'Version' ),
      true
  );
} );

add_filter('body_class', function ($classes) {
    if (!is_admin()) {
        $classes[] = 'is-frontend';
    }
    return $classes;
});

// 固定ページでも抜粋欄を使えるようにする
add_post_type_support( 'page', 'excerpt' );

//アイキャッチ画像クリックで外部リンクへ
function custom_post_thumbnail_external_link( $html, $post_id ) {
    // 投稿のカスタムフィールドから外部リンクを取得
    $external_link = get_post_meta( $post_id, 'external_link', true );
  
    // 外部リンクが設定されている場合
    if ( $external_link ) {
      $html = '<a href="' . esc_url( $external_link ) . '" target="_blank" rel="noopener noreferrer">' . $html . '</a>';
    }
  
    return $html;
  }
  add_filter( 'post_thumbnail_html', 'custom_post_thumbnail_external_link', 10, 2 );
  function custom_post_title_external_link( $title, $post_id ) {
    // 管理画面では処理しない
    if ( is_admin() ) return $title;
  
    // 外部リンク取得
    $external_link = get_post_meta( $post_id, 'external_link', true );
  
    // 外部リンクがある場合、タイトルにリンクをつける
    if ( $external_link && in_the_loop() ) {
      $title = '<a href="' . esc_url( $external_link ) . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
    }
  
    return $title;
  }
  add_filter( 'the_title', 'custom_post_title_external_link', 10, 2 );

// 投稿ページデフォルト
add_filter('block_editor_post_type_template', function($template, $post_type) {
  if ($post_type !== 'post') {
      return $template; // 投稿タイプが post 以外なら何もしない
  }

  // 新規投稿作成時のみ初期ブロックを指定
  return [
      ['core/paragraph', ['content' => 'タイトルにブログタイトルを記載']],
      ['core/image', []],
      ['core/paragraph', ['content' => '本文や説明文を入力']],
  ];
}, 10, 2);
  //カスタム投稿

function create_custom_post_type() {
  register_post_type(
    'books', // ← 投稿タイプのスラッグ
    array(
      'labels' => array(
        'name'          => '著書', // 管理画面メニューに表示される名前
        'singular_name' => '著書',
      ),
      'public'        => true, // 管理画面とフロント両方に表示
      'has_archive'   => true, // アーカイブページ有効
      'menu_position' => 5, // 投稿の下あたりに表示
      'menu_icon'     => 'dashicons-book', // アイコン（WordPress公式アイコンから）
      'show_in_rest'  => true, // ← これがブロックエディタ（Gutenberg）対応のポイント！
      'supports'      => array('title', 'editor', 'thumbnail', 'excerpt'),
      // メタ（出版社・出版日・価格）は投稿本文の books-meta-fields で表示
      'template' => array(
        array( 'core/paragraph', array(
          'placeholder' => 'この本の説明文を入力...',
        )),
        array( 'core/group', array(
          'className' => 'books-meta-fields',
        ), array(
          array( 'core/heading', array(
            'level' => 4,
            'content' => '出版社',
          )),
          array( 'core/paragraph', array(
            'placeholder' => '出版社を入力',
            'metadata' => array(
              'bindings' => array(
                'content' => array(
                  'source' => 'core/post-meta',
                  'args' => array( 'key' => 'publisher' ),
                ),
              ),
            ),
          )),
          array( 'core/heading', array(
            'level' => 4,
            'content' => '出版日',
          )),
          array( 'core/paragraph', array(
            'placeholder' => '出版日を入力',
            'metadata' => array(
              'bindings' => array(
                'content' => array(
                  'source' => 'core/post-meta',
                  'args' => array( 'key' => 'published_date' ),
                ),
              ),
            ),
          )),
          array( 'core/heading', array(
            'level' => 4,
            'content' => '価格',
          )),
          array( 'core/paragraph', array(
            'placeholder' => '価格を入力',
            'metadata' => array(
              'bindings' => array(
                'content' => array(
                  'source' => 'core/post-meta',
                  'args' => array( 'key' => 'price' ),
                ),
              ),
            ),
          )),
        )),
      ),
    )
  );
  register_post_type(
    'news', // ← 投稿タイプのスラッグ
    array(
      'labels' => array(
        'name'          => 'お知らせ', // 管理画面メニューに表示される名前
        'singular_name' => 'お知らせ',
      ),
      'public'        => true, // 管理画面とフロント両方に表示
      'has_archive'   => true, // アーカイブページ有効
      'menu_position' => 5, // 投稿の下あたりに表示
      'menu_icon'     => 'dashicons-info-outline', // アイコン（WordPress公式アイコンから）
      'show_in_rest'  => true, // ← これがブロックエディタ（Gutenberg）対応のポイント！
      'supports'      => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
    )
  );
}
add_action('init', 'create_custom_post_type');

// カスタム投稿「著書」にカテゴリー的なタクソノミーを作る
function create_books_taxonomies() {
    // 階層あり（カテゴリータイプ）
    register_taxonomy(
        'books_category', // タクソノミーのスラッグ
        'books',          // 関連付ける投稿タイプ
        array(
            'labels' => array(
                'name'          => '著書カテゴリー',
                'singular_name' => '著書カテゴリー',
                'all_items'     => 'すべてのカテゴリー',
                'edit_item'     => 'カテゴリーを編集',
                'add_new_item'  => '新規カテゴリーを追加',
            ),
            'public'       => true,
            'hierarchical' => true, // ← trueでカテゴリータイプ
            'show_in_rest' => true, // ← Gutenberg対応
        )
    );

    // 階層なし（タグタイプ）
    register_taxonomy(
        'books_tag',
        'books',
        array(
            'labels' => array(
                'name'          => '著書タグ',
                'singular_name' => '著書タグ',
                'all_items'     => 'すべてのタグ',
                'edit_item'     => 'タグを編集',
                'add_new_item'  => '新規タグを追加',
            ),
            'public'       => true,
            'hierarchical' => false, // ← falseでタグタイプ
            'show_in_rest' => true,
        )
    );
}
add_action('init', 'create_books_taxonomies');

// カスタム投稿「お知らせ」にカテゴリー的なタクソノミーを作る
function create_news_taxonomies() {
    // 階層あり（カテゴリータイプ）
    register_taxonomy(
        'news_category', // タクソノミーのスラッグ
        'news',          // 関連付ける投稿タイプ
        array(
            'labels' => array(
                'name'          => 'お知らせカテゴリー',
                'singular_name' => 'お知らせカテゴリー',
                'all_items'     => 'すべてのカテゴリー',
                'edit_item'     => 'カテゴリーを編集',
                'add_new_item'  => '新規カテゴリーを追加',
            ),
            'public'       => true,
            'hierarchical' => true, // ← trueでカテゴリータイプ
            'show_in_rest' => true, // ← Gutenberg対応
        )
    );

    // 階層なし（タグタイプ）
    register_taxonomy(
        'news_tag',
        'news',
        array(
            'labels' => array(
                'name'          => 'お知らせタグ',
                'singular_name' => 'お知らせタグ',
                'all_items'     => 'すべてのタグ',
                'edit_item'     => 'タグを編集',
                'add_new_item'  => '新規タグを追加',
            ),
            'public'       => true,
            'hierarchical' => false, // ← falseでタグタイプ
            'show_in_rest' => true,
        )
    );
}
add_action('init', 'create_news_taxonomies');

/**
 * books用ACFカスタムフィールド登録　ACF入力値が本文へ反映される
 */
add_action( 'init', function() {

  $books_meta_keys = array( 'publisher', 'price', 'published_date' );

  foreach ( $books_meta_keys as $meta_key ) {
      register_post_meta( 'books', $meta_key, array(
          'show_in_rest'  => true,
          'single'        => true,
          'type'          => 'string',
          'auth_callback' => '__return_true',
      ) );
  }

} );

//css追加　ブロックごとのスタイリング
//独自スタイルの追加
function custom_block_styles() {
    // 独自のブロックスタイルを登録する
    register_block_style(
        'core/group', // ブロック名
        array(
            'name'         => 'width__contact', // スタイル名
            'label'        => 'コンタクトフォーム幅', // スタイルの表示名
        )
    );
    register_block_style(
        'core/spacer', // ブロック名
        array(
            'name'         => 'sp-none', // スタイル名
            'label'        => 'スマホ時に余白を見せない', // スタイルの表示名
        )
    );
}
add_action( 'init', 'custom_block_styles' );
