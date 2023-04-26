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
