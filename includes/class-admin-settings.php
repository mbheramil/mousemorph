<?php
/**
 * Admin Settings — menu page, settings registration, field renderers.
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    const GROUP  = 'mmorph_settings_group';
    const OPTION = 'mmorph_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            __( 'MouseMorph', 'mousemorph' ),
            __( 'MouseMorph', 'mousemorph' ),
            'manage_options',
            'mousemorph',
            [ $this, 'render_page' ],
            'dashicons-smiley',
            58
        );
    }

    /* ── Register ───────────────────────────────── */

    public function register(): void {
        register_setting( self::GROUP, self::OPTION, [ 'sanitize_callback' => [ $this, 'sanitize' ] ] );

        /* Connection */
        add_settings_section( 'mmorph_connection', '', '__return_false', 'mousemorph' );
        $this->field( 'cloud_name', __( 'Cloud Name', 'mousemorph' ), 'text', 'mmorph_connection', [
            'description' => __( 'Found at console.pixelbin.io → Settings → Details.', 'mousemorph' ),
            'required'    => true,
        ] );
        $this->field( 'api_token', __( 'API Token', 'mousemorph' ), 'password', 'mmorph_connection', [
            'description' => __( 'Create at console.pixelbin.io → Settings → API Tokens. Copy the "API Token" value.', 'mousemorph' ),
            'required'    => true,
        ] );
        $this->field( 'zone_slug', __( 'Zone Slug', 'mousemorph' ), 'text', 'mmorph_connection', [
            'description' => __( 'Optional. Only needed if you use external storage zones.', 'mousemorph' ),
        ] );
        $this->field( 'custom_domain', __( 'Custom CDN Domain', 'mousemorph' ), 'text', 'mmorph_connection', [
            'description' => __( 'Optional. Leave blank to use cdn.pixelbin.io.', 'mousemorph' ),
        ] );

        /* Caricature AI */
        add_settings_section( 'mmorph_ai', '', '__return_false', 'mousemorph' );
        $this->field( 'transform_method', __( 'Transformation Method', 'mousemorph' ), 'select', 'mmorph_ai', [
            'options'     => [
                'portrait' => __( 'AI Portrait Generator (portrait.generate) — recommended, 3D caricature style', 'mousemorph' ),
                'img'      => __( 'AI Image Editor (img.edit) — prompt-driven, generic style', 'mousemorph' ),
                'vg'       => __( 'Design Variation Generator (vg.generate) — subtle variations only', 'mousemorph' ),
            ],
            'description' => __( '"AI Portrait Generator" produces the best 3D cartoon caricatures (same engine as the PixelBin caricature maker). "AI Image Editor" uses text prompts for more control but produces a flatter cartoon style. "Design Variation Generator" creates subtle image variations — NOT recommended for caricatures.', 'mousemorph' ),
        ] );
        $this->field( 'enable_prompts', __( 'Enable Custom Prompts', 'mousemorph' ), 'toggle', 'mmorph_ai', [
            'description' => __( 'OFF = one-click caricature (no prompts). ON = users can describe a custom scene. Start with OFF to verify the connection, then enable later.', 'mousemorph' ),
        ] );
        $this->field( 'system_prompt', __( 'System Prompt', 'mousemorph' ), 'textarea', 'mmorph_ai', [
            'description' => __( 'Base AI instruction prepended to every generation. The mouse theme suffix is always appended automatically.', 'mousemorph' ),
            'rows'        => 3,
            'default'     => 'Transform this photo into a 3D cartoon mouse caricature character with big round mouse ears, pink nose, and whiskers. Exaggerated facial features, big expressive eyes, smooth cartoon skin, fun playful expression. Pixar Disney animation style, colorful, high quality 3D render. Keep the person recognizable as a mouse character.',
        ] );

        /* Usage Limits */
        add_settings_section( 'mmorph_limits', '', '__return_false', 'mousemorph' );
        $this->field( 'daily_limit_guest', __( 'Daily Limit — Guests', 'mousemorph' ), 'number', 'mmorph_limits', [
            'description' => __( 'Max generations per day for non-logged-in visitors.', 'mousemorph' ),
            'min' => 1, 'max' => 50, 'default' => 3,
        ] );
        $this->field( 'daily_limit_user', __( 'Daily Limit — Logged-in Users', 'mousemorph' ), 'number', 'mmorph_limits', [
            'description' => __( 'Max generations per day for logged-in users. Set higher to incentivize registration.', 'mousemorph' ),
            'min' => 1, 'max' => 100, 'default' => 5,
        ] );
        $this->field( 'require_login', __( 'Require Login to Generate', 'mousemorph' ), 'toggle', 'mmorph_limits', [
            'description' => __( 'If ON, guests see the UI but cannot generate. Strongest credit protection.', 'mousemorph' ),
        ] );

        /* WooCommerce */
        add_settings_section( 'mmorph_woo', '', '__return_false', 'mousemorph' );
        $this->field( 'enable_on_all', __( 'Enable on All Products', 'mousemorph' ), 'toggle', 'mmorph_woo', [
            'description' => __( 'Show the caricature maker on every product page. Otherwise enable per-product.', 'mousemorph' ),
        ] );

        /* Moderation */
        add_settings_section( 'mmorph_moderation', '', '__return_false', 'mousemorph' );
        $this->field( 'blocked_terms', __( 'Blocked Terms', 'mousemorph' ), 'textarea', 'mmorph_moderation', [
            'description' => __( 'Words/phrases rejected from user prompts. One per line.', 'mousemorph' ),
            'rows'        => 8,
        ] );
        $this->field( 'nsfw_check', __( 'NSFW Image Detection', 'mousemorph' ), 'toggle', 'mmorph_moderation', [
            'description' => __( 'Scan uploaded photos with PixelBin NSFW AI before processing.', 'mousemorph' ),
            'default'     => 'yes',
        ] );
    }

    /* ── Sanitize ───────────────────────────────── */

    public function sanitize( $in ): array {
        return [
            'cloud_name'        => sanitize_text_field( $in['cloud_name'] ?? '' ),
            'api_token'         => sanitize_text_field( $in['api_token'] ?? '' ),
            'zone_slug'         => sanitize_text_field( $in['zone_slug'] ?? '' ),
            'custom_domain'     => esc_url_raw( $in['custom_domain'] ?? '' ),
            'transform_method'  => in_array( ( $in['transform_method'] ?? '' ), [ 'portrait', 'img', 'vg' ], true ) ? $in['transform_method'] : 'portrait',
            'enable_prompts'    => ( $in['enable_prompts'] ?? 'no' ) === 'yes' ? 'yes' : 'no',
            'system_prompt'     => sanitize_textarea_field( $in['system_prompt'] ?? '' ),
            'daily_limit_guest' => max( 1, min( 50, absint( $in['daily_limit_guest'] ?? 3 ) ) ),
            'daily_limit_user'  => max( 1, min( 100, absint( $in['daily_limit_user'] ?? 5 ) ) ),
            'require_login'     => ( $in['require_login'] ?? 'no' ) === 'yes' ? 'yes' : 'no',
            'enable_on_all'     => ( $in['enable_on_all'] ?? 'no' ) === 'yes' ? 'yes' : 'no',
            'blocked_terms'     => sanitize_textarea_field( $in['blocked_terms'] ?? '' ),
            'nsfw_check'        => ( $in['nsfw_check'] ?? 'no' ) === 'yes' ? 'yes' : 'no',
        ];
    }

    /* ── Render Page ────────────────────────────── */

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once MMORPH_DIR . 'admin/views/settings-page.php';
    }

    /* ── Field Renderers ────────────────────────── */

    public function render_text( array $a ): void {
        printf(
            '<input type="text" id="mmorph_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" %4$s/>',
            esc_attr( $a['id'] ), esc_attr( self::OPTION ), esc_attr( $this->val( $a ) ),
            ! empty( $a['required'] ) ? 'required ' : ''
        );
        $this->desc( $a );
    }

    public function render_password( array $a ): void {
        printf(
            '<input type="password" id="mmorph_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" autocomplete="off" %4$s/>',
            esc_attr( $a['id'] ), esc_attr( self::OPTION ), esc_attr( $this->val( $a ) ),
            ! empty( $a['required'] ) ? 'required ' : ''
        );
        $this->desc( $a );
    }

    public function render_number( array $a ): void {
        printf(
            '<input type="number" id="mmorph_%1$s" name="%2$s[%1$s]" value="%3$s" class="small-text" min="%4$s" max="%5$s"/>',
            esc_attr( $a['id'] ), esc_attr( self::OPTION ), esc_attr( $this->val( $a ) ),
            esc_attr( $a['min'] ?? 0 ), esc_attr( $a['max'] ?? 99999 )
        );
        $this->desc( $a );
    }

    public function render_toggle( array $a ): void {
        printf(
            '<label class="mmorph-toggle">
                <input type="hidden" name="%2$s[%1$s]" value="no"/>
                <input type="checkbox" id="mmorph_%1$s" name="%2$s[%1$s]" value="yes" %3$s/>
                <span class="mmorph-toggle-slider"></span>
            </label>',
            esc_attr( $a['id'] ), esc_attr( self::OPTION ), checked( $this->val( $a ), 'yes', false )
        );
        $this->desc( $a );
    }

    public function render_textarea( array $a ): void {
        printf(
            '<textarea id="mmorph_%1$s" name="%2$s[%1$s]" rows="%4$s" class="large-text">%3$s</textarea>',
            esc_attr( $a['id'] ), esc_attr( self::OPTION ), esc_textarea( $this->val( $a ) ),
            esc_attr( $a['rows'] ?? 4 )
        );
        $this->desc( $a );
    }

    public function render_select( array $a ): void {
        $current = $this->val( $a );
        printf( '<select id="mmorph_%1$s" name="%2$s[%1$s]">', esc_attr( $a['id'] ), esc_attr( self::OPTION ) );
        foreach ( ( $a['options'] ?? [] ) as $value => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
        }
        echo '</select>';
        $this->desc( $a );
    }

    /* ── Helpers ─────────────────────────────────── */

    private function field( string $id, string $title, string $type, string $section, array $extra = [] ): void {
        add_settings_field(
            'mmorph_' . $id, $title,
            [ $this, 'render_' . $type ],
            'mousemorph', $section,
            array_merge( [ 'id' => $id ], $extra )
        );
    }

    private function val( array $a ) {
        $opts = get_option( self::OPTION, [] );
        return $opts[ $a['id'] ] ?? ( $a['default'] ?? '' );
    }

    private function desc( array $a ): void {
        if ( ! empty( $a['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $a['description'] ) );
        }
    }
}
