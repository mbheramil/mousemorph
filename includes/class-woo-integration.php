<?php
/**
 * WooCommerce Integration — product page injection, cart data, order meta,
 * admin order view, and per-product enable/disable.
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Woo_Integration {

    private $rendered = false;

    public function __construct() {
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_maker' ], 15 );
        add_action( 'woocommerce_single_product_summary', [ $this, 'render_maker_fallback' ], 35 );

        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_cart' ], 10, 3 );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_data' ], 10, 3 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_data' ], 10, 2 );

        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_meta' ], 10, 4 );
        add_action( 'woocommerce_after_order_itemmeta', [ $this, 'display_order_meta' ], 10, 3 );

        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'product_option' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_option' ] );
    }

    /* ── Render on Product Page ─────────────────── */

    public function render_maker(): void {
        if ( $this->rendered ) {
            return;
        }
        global $product;
        if ( ! $product || ! $this->is_enabled( $product->get_id() ) || ! MouseMorph::is_configured() ) {
            return;
        }
        $this->rendered = true;
        $is_standalone  = false;
        $shortcode_atts = [ 'title' => __( 'Create Your Mouse Caricature', 'mousemorph' ) ];
        include MMORPH_DIR . 'public/views/caricature-form.php';
    }

    public function render_maker_fallback(): void {
        if ( $this->rendered ) {
            return;
        }
        global $product;
        if ( ! $product || ! $this->is_enabled( $product->get_id() ) || ! MouseMorph::is_configured() ) {
            return;
        }
        $this->rendered = true;
        $is_standalone  = true;
        $shortcode_atts = [ 'title' => __( 'Create Your Mouse Caricature', 'mousemorph' ) ];
        include MMORPH_DIR . 'public/views/caricature-form.php';
    }

    /* ── Cart Validation ────────────────────────── */

    public function validate_cart( bool $passed, int $product_id, int $qty ): bool {
        if ( ! $this->is_enabled( $product_id ) ) {
            return $passed;
        }
        $uid = sanitize_text_field( $_POST['mmorph_upload_id'] ?? '' );
        if ( empty( $uid ) ) {
            wc_add_notice( __( 'Please create your mouse caricature before adding to cart.', 'mousemorph' ), 'error' );
            return false;
        }
        $result = get_transient( 'mmorph_result_' . $uid );
        if ( ! $result || empty( $result['caricature_url'] ) ) {
            wc_add_notice( __( 'Please generate your caricature before adding to cart.', 'mousemorph' ), 'error' );
            return false;
        }
        return $passed;
    }

    /* ── Cart Item Data ─────────────────────────── */

    public function add_cart_data( array $data, int $product_id, int $variation_id ): array {
        if ( ! $this->is_enabled( $product_id ) ) {
            return $data;
        }
        $uid = sanitize_text_field( $_POST['mmorph_upload_id'] ?? '' );
        if ( empty( $uid ) ) {
            return $data;
        }

        $result = get_transient( 'mmorph_result_' . $uid );
        $upload = get_transient( 'mmorph_upload_' . $uid );

        if ( $result && ! empty( $result['caricature_url'] ) ) {
            $data['mmorph_caricature'] = [
                'upload_id'      => $uid,
                'caricature_url' => $result['caricature_url'],
                'scene'          => $result['scene'] ?? '',
                'generated_at'   => $result['generated_at'] ?? '',
                'original_photo' => $upload['pixelbin_path'] ?? '',
            ];
            $data['unique_key'] = md5( $uid . microtime() );
        }
        return $data;
    }

    public function display_cart_data( array $item_data, array $cart_item ): array {
        if ( ! isset( $cart_item['mmorph_caricature'] ) ) {
            return $item_data;
        }
        $d = $cart_item['mmorph_caricature'];

        $item_data[] = [
            'key'   => __( 'Mouse Caricature', 'mousemorph' ),
            'value' => '<img src="' . esc_url( $d['caricature_url'] ) . '" alt="' . esc_attr__( 'Your mouse caricature', 'mousemorph' ) . '" style="max-width:80px;max-height:80px;border-radius:8px;"/>',
        ];
        if ( ! empty( $d['scene'] ) ) {
            $item_data[] = [
                'key'   => __( 'Scene', 'mousemorph' ),
                'value' => esc_html( mb_substr( $d['scene'], 0, 100 ) ),
            ];
        }
        return $item_data;
    }

    /* ── Order Meta ─────────────────────────────── */

    public function save_order_meta( $item, $cart_key, array $values, $order ): void {
        if ( ! isset( $values['mmorph_caricature'] ) ) {
            return;
        }
        $d = $values['mmorph_caricature'];
        $item->add_meta_data( '_mmorph_caricature_url', $d['caricature_url'] );
        $item->add_meta_data( '_mmorph_scene', $d['scene'] );
        $item->add_meta_data( '_mmorph_original_photo', $d['original_photo'] );
        $item->add_meta_data( '_mmorph_generated_at', $d['generated_at'] );
    }

    public function display_order_meta( int $item_id, $item, $product ): void {
        $url = $item->get_meta( '_mmorph_caricature_url' );
        if ( empty( $url ) ) {
            return;
        }
        $scene = $item->get_meta( '_mmorph_scene' );
        ?>
        <div class="mmorph-order-meta" style="margin-top:12px;padding:12px;background:#f8f9fa;border-radius:8px;border:1px solid #e1e5eb;">
            <strong style="display:block;margin-bottom:8px;"><?php esc_html_e( 'Mouse Caricature', 'mousemorph' ); ?></strong>
            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
                <img src="<?php echo esc_url( $url ); ?>" alt="<?php esc_attr_e( 'Caricature', 'mousemorph' ); ?>" style="max-width:200px;border-radius:8px;display:block;margin-bottom:8px;"/>
            </a>
            <?php if ( ! empty( $scene ) ) : ?>
                <small style="color:#636e72;"><strong><?php esc_html_e( 'Scene:', 'mousemorph' ); ?></strong> <?php echo esc_html( $scene ); ?></small>
            <?php endif; ?>
            <br>
            <a href="<?php echo esc_url( $url ); ?>" class="button button-small" download style="margin-top:6px;">
                <?php esc_html_e( 'Download for Print', 'mousemorph' ); ?>
            </a>
        </div>
        <?php
    }

    /* ── Per-Product Setting ─────────────────────── */

    public function product_option(): void {
        woocommerce_wp_checkbox( [
            'id'          => '_mmorph_enabled',
            'label'       => __( 'MouseMorph Caricature', 'mousemorph' ),
            'description' => __( 'Enable the mouse caricature maker on this product page.', 'mousemorph' ),
            'desc_tip'    => true,
        ] );
    }

    public function save_product_option( int $post_id ): void {
        update_post_meta( $post_id, '_mmorph_enabled', isset( $_POST['_mmorph_enabled'] ) ? 'yes' : 'no' );
    }

    /* ── Helpers ─────────────────────────────────── */

    private function is_enabled( int $product_id ): bool {
        if ( MouseMorph::get_option( 'enable_on_all' ) === 'yes' ) {
            return true;
        }
        return get_post_meta( $product_id, '_mmorph_enabled', true ) === 'yes';
    }
}
