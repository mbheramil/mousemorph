<?php
/**
 * Plugin Name: MouseMorph — AI Caricature Maker for WooCommerce
 * Plugin URI:  https://pixelbin.io
 * Description: Let customers turn their photos into fun mouse caricatures powered by PixelBin AI, then print them on your products.
 * Version:     1.2.2
 * Author:      PixelBin
 * Author URI:  https://pixelbin.io
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mousemorph
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MMORPH_VERSION', '1.2.2' );
define( 'MMORPH_FILE', __FILE__ );
define( 'MMORPH_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMORPH_URL', plugin_dir_url( __FILE__ ) );
define( 'MMORPH_BASENAME', plugin_basename( __FILE__ ) );

require_once MMORPH_DIR . 'includes/class-pixelbin-api.php';
require_once MMORPH_DIR . 'includes/class-content-moderator.php';
require_once MMORPH_DIR . 'includes/class-caricature-engine.php';
require_once MMORPH_DIR . 'includes/class-admin-settings.php';
require_once MMORPH_DIR . 'includes/class-woo-integration.php';
require_once MMORPH_DIR . 'includes/class-shortcode.php';
require_once MMORPH_DIR . 'includes/class-github-updater.php';

final class MouseMorph {

    private static $instance = null;

    public $api;
    public $moderator;
    public $engine;
    public $settings;
    public $woo;
    public $shortcode;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->boot();
    }

    private function boot(): void {
        add_action( 'admin_notices', [ $this, 'maybe_warn_woocommerce' ] );

        $this->api       = new PixelBin_API();
        $this->moderator = new Content_Moderator();
        $this->engine    = new Caricature_Engine();
        $this->settings  = new Admin_Settings();
        $this->shortcode = new MM_Shortcode();

        if ( class_exists( 'WooCommerce' ) ) {
            $this->woo = new Woo_Integration();
        }

        new GitHub_Updater( MMORPH_FILE, 'mbheramil/mousemorph', MMORPH_VERSION );

        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
        add_filter( 'plugin_action_links_' . MMORPH_BASENAME, [ $this, 'settings_link' ] );
    }

    public function maybe_warn_woocommerce(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e( 'MouseMorph: WooCommerce is not active. Product integration is disabled, but the [mousemorph] shortcode still works.', 'mousemorph' );
            echo '</p></div>';
        }
    }

    public function admin_assets( string $hook ): void {
        if ( strpos( $hook, 'mousemorph' ) === false ) {
            return;
        }
        wp_enqueue_style( 'mmorph-admin', MMORPH_URL . 'admin/css/admin.css', [], MMORPH_VERSION );
        wp_enqueue_script( 'mmorph-admin', MMORPH_URL . 'admin/js/admin.js', [ 'jquery' ], MMORPH_VERSION, true );
        wp_localize_script( 'mmorph-admin', 'mmorph_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mmorph_admin_nonce' ),
        ] );
    }

    public function frontend_assets(): void {
        if ( ! $this->should_load_frontend() ) {
            return;
        }
        wp_enqueue_style( 'mmorph-front', MMORPH_URL . 'public/css/caricature.css', [], MMORPH_VERSION );
        wp_enqueue_script( 'mmorph-front', MMORPH_URL . 'public/js/caricature.js', [ 'jquery' ], MMORPH_VERSION, true );
        wp_localize_script( 'mmorph-front', 'mmorph', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'mmorph_nonce' ),
            'max_size'       => 10 * 1024 * 1024,
            'allowed'        => [ 'image/jpeg', 'image/png', 'image/webp' ],
            'enable_prompts' => self::get_option( 'enable_prompts', 'no' ) === 'yes',
            'custom_domain'  => self::get_option( 'custom_domain', '' ),
            'i18n'           => [
                'optimizing'     => __( 'Optimizing your photo…', 'mousemorph' ),
                'uploading'      => __( 'Uploading your photo…', 'mousemorph' ),
                'generating'     => __( 'Creating your mouse caricature…', 'mousemorph' ),
                'almost'         => __( 'Almost there — adding finishing touches…', 'mousemorph' ),
                'done'           => __( 'Your caricature is ready!', 'mousemorph' ),
                'error'          => __( 'Something went wrong. Please try again.', 'mousemorph' ),
                'moderation'     => __( 'Your description contains content that is not allowed. Please keep it fun and family-friendly!', 'mousemorph' ),
                'file_too_large' => __( 'File is too large. Maximum size is 10 MB.', 'mousemorph' ),
                'invalid_type'   => __( 'Please upload a JPG, PNG, or WebP image.', 'mousemorph' ),
                'no_file'        => __( 'Please upload a photo first.', 'mousemorph' ),
                'timeout'        => __( 'Generation timed out. Please try again.', 'mousemorph' ),
                'limit_reached'  => __( 'Daily limit reached. Please come back tomorrow!', 'mousemorph' ),
            ],
        ] );
    }

    public function settings_link( array $links ): array {
        $url  = admin_url( 'admin.php?page=mousemorph' );
        $link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'mousemorph' ) . '</a>';
        array_unshift( $links, $link );
        return $links;
    }

    private function should_load_frontend(): bool {
        global $post;
        if ( function_exists( 'is_product' ) && is_product() ) {
            return true;
        }
        if ( $post && has_shortcode( $post->post_content, 'mousemorph' ) ) {
            return true;
        }
        return false;
    }

    public static function get_option( string $key, $default = '' ) {
        $opts = get_option( 'mmorph_settings', [] );
        return $opts[ $key ] ?? $default;
    }

    public static function is_configured(): bool {
        return ! empty( self::get_option( 'cloud_name' ) )
            && ! empty( self::get_option( 'api_token' ) );
    }
}

register_activation_hook( __FILE__, function () {
    $defaults = [
        'cloud_name'        => '',
        'zone_slug'         => '',
        'api_token'         => '',
        'custom_domain'     => '',
        'transform_method'  => 'portrait',
        'enable_prompts'    => 'no',
        'system_prompt'     => 'Transform this photo into a cute, fun cartoon mouse caricature. The person should be reimagined as an adorable mouse character with big round mouse ears, a small pink nose, whiskers, and expressive eyes that capture their likeness. Keep it family-friendly, whimsical, Pixar Disney animation style, high quality 3D render, suitable for printing on merchandise.',
        'daily_limit_guest' => 3,
        'daily_limit_user'  => 5,
        'require_login'     => 'no',
        'enable_on_all'     => 'no',
        'blocked_terms'     => "sexual\nnude\nnaked\nviolent\nviolence\nkill\nmurder\nblood\ngore\nweapon\ngun\nknife\nporn\nexplicit\noffensive\nabuse\nracist\nracism\nhate\nslur",
        'nsfw_check'        => 'yes',
    ];
    if ( ! get_option( 'mmorph_settings' ) ) {
        add_option( 'mmorph_settings', $defaults );
    }
    $upload_dir = wp_upload_dir();
    wp_mkdir_p( $upload_dir['basedir'] . '/mousemorph' );
} );

register_deactivation_hook( __FILE__, function () {
    // Nothing destructive on deactivation.
} );

add_action( 'plugins_loaded', function () {
    MouseMorph::instance();
} );
