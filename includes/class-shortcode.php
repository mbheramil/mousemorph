<?php
/**
 * [mousemorph] shortcode — standalone caricature maker (works without WooCommerce).
 *
 * Usage:
 *   [mousemorph]
 *   [mousemorph title="Make Your Mouse!"]
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MM_Shortcode {

    public function __construct() {
        add_shortcode( 'mousemorph', [ $this, 'render' ] );
    }

    public function render( $atts ): string {
        $atts = shortcode_atts( [
            'title' => __( 'Create Your Mouse Caricature', 'mousemorph' ),
        ], $atts, 'mousemorph' );

        if ( ! MouseMorph::is_configured() ) {
            return '<p class="mmorph-notice">' . esc_html__( 'The caricature maker is currently unavailable.', 'mousemorph' ) . '</p>';
        }

        ob_start();
        $is_standalone  = true;
        $shortcode_atts = $atts;
        include MMORPH_DIR . 'public/views/caricature-form.php';
        return ob_get_clean();
    }
}
