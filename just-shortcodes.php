<?php
/**
 * Plugin Name: Just Shortcodes
 * Plugin URI: https://dngstudio.co
 * Description: Generates a shortcode whenever a new post is created, and allows users to edit the shortcode title and content.
 * Version: 1.0.0
 * Author: DNG Studio
 * Author URI: https://dngstudio.co
 */

function shortcode_post_type() {
    register_post_type( 'shortcode',
        array(
            'labels' => array(
                'name' => __( 'Shortcodes' ),
                'singular_name' => __( 'Shortcode' )
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'shortcode'),
            'supports' => array('title', 'editor')
        )
    );
}
add_action( 'init', 'shortcode_post_type' );

function generate_shortcode( $post_id ) {
    $post = get_post( $post_id );
    $title = strtolower( str_replace( ' ', '_', $post->post_title ) );
    $shortcode = '[' . $title . ']';
    
    add_shortcode( $title, function( $atts, $content = null ) use ( $post ) {
        return apply_filters( 'the_content', $post->post_content );
    } );
    
    update_post_meta( $post_id, 'generated_shortcode', $shortcode );
    return $shortcode;
}

function register_shortcodes() {
    $shortcodes = get_posts( array(
        'post_type' => 'shortcode',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ) );

    foreach ( $shortcodes as $shortcode ) {
        $title = strtolower( str_replace( ' ', '_', $shortcode->post_title ) );
        add_shortcode( $title, function( $atts, $content = null ) use ( $shortcode ) {
            return apply_filters( 'the_content', $shortcode->post_content );
        } );
    }
}
add_action( 'init', 'register_shortcodes' );

add_action( 'save_post_shortcode', 'generate_shortcode' );

/* function shortcode_content( $atts ) {
    $title = strtolower( str_replace( ' ', '_', $atts['title'] ) );
    $shortcode = '[' . $title . ']';
    return do_shortcode( $shortcode );
}
add_shortcode( 'moje_ime', 'shortcode_content' ); */

function limit_shortcode_options( $post_type, $post ) {
    if ( $post_type == 'shortcode' ) {
        remove_post_type_support( 'shortcode', 'author' );
        remove_post_type_support( 'shortcode', 'comments' );
        remove_post_type_support( 'shortcode', 'excerpt' );
        remove_post_type_support( 'shortcode', 'thumbnail' );
        remove_post_type_support( 'shortcode', 'trackbacks' );
        remove_meta_box( 'slugdiv', 'shortcode', 'normal' );
    }
}
add_action( 'add_meta_boxes', 'limit_shortcode_options', 10, 2 );

function shortcode_taxonomy() {
    $labels = array(
        'name' => __( 'Shortcode Categories' ),
        'singular_name' => __( 'Shortcode Category' ),
        'menu_name' => __( 'Categories' ),
    );
    $args = array(
        'labels' => $labels,
        'public' => true,
        'hierarchical' => true,
        'rewrite' => array( 'slug' => 'shortcode_category' ),
    );
    register_taxonomy( 'shortcode_category', 'shortcode', $args );
}
add_action( 'init', 'shortcode_taxonomy' );

function shortcode_columns( $columns ) {
    $columns['generated_shortcode'] = 'Generated Shortcode';
    return $columns;
}
add_filter( 'manage_shortcode_posts_columns', 'shortcode_columns' );

function shortcode_column_data( $column, $post_id ) {
    if ( $column == 'generated_shortcode' ) {
        $shortcode = get_post_meta( $post_id, 'generated_shortcode', true );
        echo $shortcode;
    }
}
add_action( 'manage_shortcode_posts_custom_column', 'shortcode_column_data', 10, 2 );

function update_shortcode_content( $post_id ) {
    $shortcode = get_post_meta( $post_id, 'generated_shortcode', true );
    $post_data = array(
        'ID'           => $shortcode,
        'post_content' => get_post_field( 'post_content', $post_id )
    );
    wp_update_post( $post_data );
}
add_action( 'save_post', 'update_shortcode_content' ); 


// Add export option to bulk actions dropdown
function shortcode_bulk_actions( $bulk_actions ) {
    $bulk_actions['export'] = __( 'Export', 'textdomain' );
    return $bulk_actions;
}
add_filter( 'bulk_actions-edit-shortcode', 'shortcode_bulk_actions' );

// Handle export action
function shortcode_handle_export() {
    $args = array(
        'post_type' => 'shortcode',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $shortcodes = get_posts( $args );
    
    $data = array();
    foreach ( $shortcodes as $shortcode ) {
        $title = $shortcode->post_title;
        $content = $shortcode->post_content;
        $data[] = array(
            'title' => $title,
            'content' => $content,
        );
    }
    
    header( 'Content-Type: application/json' );
    header( 'Content-Disposition: attachment; filename="shortcodes.json"' );
    echo json_encode( $data );
    exit();
}
add_action( 'admin_action_export', 'shortcode_handle_export' );

function import_shortcodes() {
    if ( isset( $_POST['import_shortcodes'] ) && ! empty( $_FILES['import_file']['tmp_name'] ) ) {
        $file = $_FILES['import_file']['tmp_name'];
        $contents = file_get_contents( $file );
        $shortcodes = json_decode( $contents );

        if ( ! empty( $shortcodes ) && is_array( $shortcodes ) ) {
            foreach ( $shortcodes as $shortcode ) {
                if ( empty( $shortcode->title ) || empty( $shortcode->content ) ) {
                    continue;
                }

                $post_id = wp_insert_post( array(
                    'post_title' => $shortcode->title,
                    'post_content' => $shortcode->content,
                    'post_status' => 'publish',
                    'post_type' => 'shortcode'
                ) );

                if ( ! is_wp_error( $post_id ) ) {
                    generate_shortcode( $post_id );
                    $imported_shortcodes[] = $shortcode->title;
                }
            }

            if ( ! empty( $imported_shortcodes ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>The following shortcodes have been imported:</p><ul>';
                foreach ( $imported_shortcodes as $shortcode ) {
                    echo '<li>' . $shortcode . '</li>';
                }
                echo '</ul></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>No shortcodes were imported. Please check your import file.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>No shortcodes were imported. Please check your import file.</p></div>';
        }
    }
}

add_action( 'admin_notices', 'import_shortcodes' );

function shortcode_import_page() {
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <form method="post" enctype="multipart/form-data">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="import_file">Import File:</label></th>
                    <td>
                        <input type="file" name="import_file" id="import_file" required>
                        <p class="description">Import file should be in JSON format and should have an array of shortcodes. Each shortcode should have a title and content field.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php wp_nonce_field( 'import_shortcodes', 'import_shortcodes_nonce' ); ?>
        <p class="submit"><input type="submit" name="import_shortcodes" class="button button-primary" value="Import Shortcodes"></p>
    </form>
</div>
<?php
}

function add_shortcode_import_page() {
    add_submenu_page(
        'edit.php?post_type=shortcode',
        __( 'Import Shortcodes', 'just-shortcodes' ),
        __( 'Import Shortcodes', 'just-shortcodes' ),
        'manage_options',
        'shortcode-import',
        'shortcode_import_page'
    );
}

add_action( 'admin_menu', 'add_shortcode_import_page' );

/* function shortcode_import_scripts( $hook ) {
    if ( 'edit.php' != $hook ) {
        return;
    }
    wp_enqueue_script( 'jquery' );
    wp_enqueue_media();
}

add_action( 'admin_enqueue_scripts', 'shortcode_import_scripts' );

add_action( 'admin_enqueue_scripts', 'enqueue_shortcode_import_script' );

function enqueue_shortcode_import_script() {
    wp_enqueue_script(
        'shortcode-import',
        plugin_dir_url( __FILE__ ) . 'js/import.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );
} */
