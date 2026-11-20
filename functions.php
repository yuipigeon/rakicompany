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

//アイキャッチ画像クリックで外部リンクへ（既にaがある場合は包まず href だけ差し替え）
function custom_post_thumbnail_external_link( $html, $post_id ) {
    $external_link = get_post_meta( $post_id, 'external_link', true );
    if ( ! $external_link || $html === '' ) {
      return $html;
    }

    if ( strpos( $html, '<a ' ) !== false ) {
      return preg_replace(
        '/(<a\s[^>]*href=["\'])[^"\']*(["\'])/',
        '$1' . esc_url( $external_link ) . '$2 target="_blank" rel="noopener noreferrer"',
        $html,
        1
      );
    }

    return '<a href="' . esc_url( $external_link ) . '" target="_blank" rel="noopener noreferrer">' . $html . '</a>';
  }
  add_filter( 'post_thumbnail_html', 'custom_post_thumbnail_external_link', 10, 2 );

// 外部リンクがある投稿は、一覧のタイトル・アイキャッチのリンク先を外部記事にする
add_filter( 'post_link', function ( $permalink, $post ) {
  if ( is_admin() || wp_is_json_request() ) {
    return $permalink;
  }
  $external_link = get_post_meta( $post->ID, 'external_link', true );
  return $external_link ? $external_link : $permalink;
}, 10, 2 );

add_filter( 'post_type_link', function ( $permalink, $post ) {
  if ( $post->post_type !== 'advertisement' || is_admin() || wp_is_json_request() ) {
    return $permalink;
  }
  $external_link = get_post_meta( $post->ID, 'external_link', true );
  return $external_link ? $external_link : $permalink;
}, 10, 2 );

add_filter( 'render_block', function ( $html, $block ) {
  if ( ! in_array( $block['blockName'], array( 'core/post-title', 'core/post-featured-image', 'core/latest-posts' ), true ) ) {
    return $html;
  }
  $tags = new WP_HTML_Tag_Processor( $html );
  while ( $tags->next_tag( 'a' ) ) {
    $href = (string) $tags->get_attribute( 'href' );
    if ( preg_match( '#^https?://#', $href ) && strpos( $href, home_url() ) !== 0 ) {
      $tags->set_attribute( 'target', '_blank' );
      $tags->set_attribute( 'rel', 'noopener noreferrer' );
    }
  }
  return $tags->get_updated_html();
}, 10, 2 );

// Online contents All（著者アーカイブ）：1ページ20件、?oc=カテゴリスラッグで絞り込み
add_filter( 'query_vars', function ( $vars ) {
  $vars[] = 'oc';
  return $vars;
} );

add_action( 'pre_get_posts', function ( $query ) {
  if ( is_admin() || ! $query->is_main_query() || ! $query->is_author() ) {
    return;
  }
  $query->set( 'post_type', 'post' );
  $query->set( 'posts_per_page', 20 );
  $online_cat = sanitize_title( (string) $query->get( 'oc' ) );
  if ( $online_cat ) {
    $query->set( 'category_name', $online_cat );
  }
} );

// 広告記事一覧：1ページ20件
add_action( 'pre_get_posts', function ( $query ) {
  if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'advertisement' ) ) {
    return;
  }
  $query->set( 'posts_per_page', 20 );
} );

// 紙媒体記事：媒体名・号・PDF（PDF は後から追加できる）
add_action( 'acf/include_fields', function () {
  if ( ! function_exists( 'acf_add_local_field_group' ) ) {
    return;
  }
  acf_add_local_field_group( array(
    'key'      => 'group_raki_paper',
    'title'    => '紙媒体記事',
    'fields'   => array(
      array(
        'key'          => 'field_raki_paper_media',
        'label'        => '媒体名',
        'name'         => 'paper_media',
        'type'         => 'text',
        'instructions' => '例）婦人画報',
        'required'     => 1,
      ),
      array(
        'key'          => 'field_raki_paper_issue',
        'label'        => '号・発行年月',
        'name'         => 'paper_issue',
        'type'         => 'text',
        'instructions' => '例）2023年12月号',
      ),
      array(
        'key'           => 'field_raki_paper_pdf',
        'label'         => 'PDF',
        'name'          => 'paper_pdf',
        'type'          => 'file',
        'return_format' => 'id',
        'mime_types'    => 'pdf',
      ),
    ),
    'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'paper' ) ) ),
  ) );
} );

// 紙媒体記事を媒体名ごとにまとめる（並びは管理画面の「順序」→ 新しい順、媒体は最初に出てきた順）
function raki_paper_groups() {
  $groups = array();
  $items  = get_posts( array(
    'post_type'      => 'paper',
    'posts_per_page' => -1,
    'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
  ) );
  foreach ( $items as $item ) {
    $media = (string) get_post_meta( $item->ID, 'paper_media', true );
    $title = get_the_title( $item );
    $groups[ $media ][] = array(
      'issue' => (string) get_post_meta( $item->ID, 'paper_issue', true ),
      // 媒体名だけの記事は、タイトルに媒体名を入れて登録している
      'title' => $title !== $media ? $title : '',
      'pdf'   => wp_get_attachment_url( (int) get_post_meta( $item->ID, 'paper_pdf', true ) ),
    );
  }
  return $groups;
}

// [raki_paper_list limit="8"]：媒体ごとのカード（limit は媒体の数）
add_shortcode( 'raki_paper_list', function ( $atts ) {
  $atts   = shortcode_atts( array( 'limit' => -1 ), $atts );
  $groups = raki_paper_groups();
  if ( (int) $atts['limit'] > 0 ) {
    $groups = array_slice( $groups, 0, (int) $atts['limit'], true );
  }
  if ( ! $groups ) {
    return '';
  }

  $detail_cards = '';
  $name_cards   = '';
  foreach ( $groups as $media => $articles ) {
    $list = '';
    foreach ( $articles as $article ) {
      if ( $article['issue'] === '' && $article['title'] === '' && ! $article['pdf'] ) {
        continue;
      }
      $list .= '<li class="p-paper-card__item">';
      if ( $article['issue'] !== '' ) {
        $list .= '<span class="p-paper-card__issue">' . esc_html( $article['issue'] ) . '</span>';
      }
      if ( $article['title'] !== '' ) {
        $list .= '<span class="p-paper-card__title">' . esc_html( $article['title'] ) . '</span>';
      }
      if ( $article['pdf'] ) {
        $list .= '<a class="p-paper-card__pdf" href="' . esc_url( $article['pdf'] ) . '" target="_blank" rel="noopener noreferrer">PDF</a>';
      }
      $list .= '</li>';
    }
    $media_html = '<p class="p-paper-card__media">' . esc_html( $media ) . '</p>';
    if ( $list === '' ) {
      $name_cards .= '<div class="p-paper-card is-name-only">' . $media_html . '</div>';
    } else {
      $detail_cards .= '<div class="p-paper-card">' . $media_html . '<ul class="p-paper-card__list">' . $list . '</ul></div>';
    }
  }

  // 号・タイトルのあるカードを先に、媒体名だけのカードは後ろにまとめる（高さを別々にそろえるため）
  $html = '<div class="p-paper-cards">';
  if ( $detail_cards !== '' ) {
    $html .= '<div class="p-paper-cards__grid is-detail">' . $detail_cards . '</div>';
  }
  if ( $name_cards !== '' ) {
    $html .= '<div class="p-paper-cards__grid is-name-only">' . $name_cards . '</div>';
  }
  return $html . '</div>';
} );

// 著書：表紙が未登録か no-image.jpg のときは、タイトルだけの「文字の表紙」を出す
function raki_books_needs_text_cover( $post_id ) {
  if ( ! $post_id || get_post_type( $post_id ) !== 'books' ) {
    return false;
  }
  $thumbnail_id = (int) get_post_thumbnail_id( $post_id );
  return ! $thumbnail_id || basename( (string) get_attached_file( $thumbnail_id ) ) === 'no-image.jpg';
}

function raki_books_text_cover_title( $post_id ) {
  return '<span class="p-text-cover__title">' . esc_html( get_the_title( $post_id ) ) . '</span>';
}

add_filter( 'render_block_core/post-featured-image', function ( $html, $block, $instance ) {
  $post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();
  if ( ! raki_books_needs_text_cover( $post_id ) ) {
    return $html;
  }
  return '<figure class="wp-block-post-featured-image p-text-cover">' . raki_books_text_cover_title( $post_id ) . '</figure>';
}, 10, 3 );

// 著書の詳細ページは media-text の「アイキャッチ画像を使用」で表紙を出している
add_filter( 'render_block_core/media-text', function ( $html, $block ) {
  if ( empty( $block['attrs']['useFeaturedImage'] ) || ! is_singular( 'books' ) || ! raki_books_needs_text_cover( get_the_ID() ) ) {
    return $html;
  }
  return preg_replace(
    '#<figure\s+class="wp-block-media-text__media"[^>]*>.*?</figure>#s',
    '<figure class="wp-block-media-text__media"><div class="p-text-cover">' . raki_books_text_cover_title( get_the_ID() ) . '</div></figure>',
    $html,
    1
  );
}, 10, 2 );

// フロントの more ボタン：8件以下のセクションでは出さない
add_filter( 'render_block', function ( $html, $block ) {
  if ( $block['blockName'] !== 'core/buttons' || ! is_front_page() || strpos( $html, 'c-more' ) === false ) {
    return $html;
  }
  $types = array(
    '/author/'        => 'post',
    '/advertisement/' => 'advertisement',
    '/paper-media/'   => 'paper',
    '/books/'         => 'books',
  );
  foreach ( $types as $path => $post_type ) {
    if ( preg_match( '#href="[^"]*' . preg_quote( $path, '#' ) . '[^"]*"#', $html ) ) {
      $count = $post_type === 'paper' ? count( raki_paper_groups() ) : (int) wp_count_posts( $post_type )->publish;
      return $count > 8 ? $html : '';
    }
  }
  return $html;
}, 10, 2 );

add_shortcode( 'raki_online_filter', function () {
  $base    = get_author_posts_url( (int) get_queried_object_id() );
  $current = sanitize_title( (string) get_query_var( 'oc' ) );
  $items   = array( array( 'All', $base, $current === '' ) );
  $terms   = get_categories( array( 'hide_empty' => true, 'exclude' => array( (int) get_option( 'default_category' ) ) ) );
  foreach ( $terms as $term ) {
    $items[] = array( $term->name, add_query_arg( 'oc', $term->slug, $base ), $current === $term->slug );
  }

  $html = '<ul class="c-filter">';
  foreach ( $items as [ $label, $url, $active ] ) {
    $html .= sprintf(
      '<li><a href="%s"%s>%s</a></li>',
      esc_url( $url ),
      $active ? ' class="is-active" aria-current="page"' : '',
      esc_html( $label )
    );
  }
  return $html . '</ul>';
} );

add_shortcode( 'raki_pagenavi', function () {
  if ( ! function_exists( 'wp_pagenavi' ) ) {
    return '';
  }
  return (string) wp_pagenavi( array(
    'echo'    => false,
    'options' => array(
      'pages_text'    => '',
      'current_text'  => '%PAGE_NUMBER%',
      'page_text'     => '%PAGE_NUMBER%',
      'first_text'    => '',
      'last_text'     => '',
      'prev_text'     => '‹',
      'next_text'     => '›',
      'dotleft_text'  => '…',
      'dotright_text' => '…',
      'num_pages'     => 5,
      'always_show'   => false,
      'style'         => 1,
    ),
  ) );
} );

add_action( 'wp_enqueue_scripts', function () {
  wp_dequeue_style( 'wp-pagenavi' );
}, 20 );

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
  register_post_type(
    'advertisement',
    array(
      'labels' => array(
        'name'          => '広告記事',
        'singular_name' => '広告記事',
      ),
      'public'        => true,
      'has_archive'   => true,
      'menu_position' => 5,
      'menu_icon'     => 'dashicons-megaphone',
      'show_in_rest'  => true,
      'supports'      => array('title', 'editor', 'thumbnail'),
    )
  );
  register_post_type(
    'paper',
    array(
      'labels' => array(
        'name'          => '紙媒体記事',
        'singular_name' => '紙媒体記事',
      ),
      'public'             => true,
      'publicly_queryable' => true,
      'has_archive'        => 'paper-media',
      'rewrite'            => array( 'slug' => 'paper-media' ),
      'menu_position'      => 5,
      'menu_icon'          => 'dashicons-media-document',
      'show_in_rest'       => true,
      'supports'           => array('title', 'page-attributes'),
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

// Contact Form 7: 送信ボタンは常に押せる。未入力・未同意は送信後にエラー表示
add_filter( 'wpcf7_contact_form_properties', function ( $properties ) {
  $settings = isset( $properties['additional_settings'] ) ? $properties['additional_settings'] : '';
  if ( strpos( $settings, 'acceptance_as_validation' ) === false ) {
    $properties['additional_settings'] = trim( $settings . "\nacceptance_as_validation: on" );
  }

  if ( isset( $properties['messages'] ) && is_array( $properties['messages'] ) ) {
    if ( empty( $properties['messages']['validation_error'] ) ) {
      $properties['messages']['validation_error'] = '入力内容に不備があります。確認して再度送信してください。';
    }
  }

  return $properties;
} );
