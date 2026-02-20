<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap mmorph-wrap">

    <div class="mmorph-admin-header">
        <div class="mmorph-admin-header-inner">
            <div class="mmorph-admin-logo">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect width="40" height="40" rx="10" fill="#6C5CE7"/>
                    <circle cx="13" cy="12" r="5" fill="#fff" opacity="0.6"/>
                    <circle cx="27" cy="12" r="5" fill="#fff" opacity="0.6"/>
                    <circle cx="20" cy="24" r="9" fill="#fff"/>
                    <circle cx="17" cy="22" r="1.5" fill="#6C5CE7"/>
                    <circle cx="23" cy="22" r="1.5" fill="#6C5CE7"/>
                    <ellipse cx="20" cy="26" rx="2.5" ry="1.2" fill="#FFB8C6"/>
                </svg>
                <div>
                    <h1><?php esc_html_e( 'MouseMorph', 'mousemorph' ); ?></h1>
                    <span class="mmorph-admin-version">v<?php echo esc_html( MMORPH_VERSION ); ?></span>
                </div>
            </div>
            <div class="mmorph-admin-actions">
                <button type="button" id="mmorph-test-btn" class="button button-secondary">
                    <span class="dashicons dashicons-networking"></span>
                    <?php esc_html_e( 'Test Connection', 'mousemorph' ); ?>
                </button>
                <a href="https://console.pixelbin.io" target="_blank" rel="noopener" class="button button-secondary">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( 'PixelBin Dashboard', 'mousemorph' ); ?>
                </a>
            </div>
        </div>
        <div id="mmorph-test-status" class="mmorph-status hidden"></div>
    </div>

    <nav class="mmorph-admin-tabs">
        <a href="#connection" class="mmorph-admin-tab active" data-tab="connection">
            <span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'Connection', 'mousemorph' ); ?>
        </a>
        <a href="#caricature" class="mmorph-admin-tab" data-tab="caricature">
            <span class="dashicons dashicons-smiley"></span> <?php esc_html_e( 'Caricature AI', 'mousemorph' ); ?>
        </a>
        <a href="#limits" class="mmorph-admin-tab" data-tab="limits">
            <span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Usage Limits', 'mousemorph' ); ?>
        </a>
        <a href="#woocommerce" class="mmorph-admin-tab" data-tab="woocommerce">
            <span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'WooCommerce', 'mousemorph' ); ?>
        </a>
        <a href="#moderation" class="mmorph-admin-tab" data-tab="moderation">
            <span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Content Safety', 'mousemorph' ); ?>
        </a>
        <a href="#shortcode" class="mmorph-admin-tab" data-tab="shortcode">
            <span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Shortcode', 'mousemorph' ); ?>
        </a>
    </nav>

    <form method="post" action="options.php" class="mmorph-admin-form">
        <?php settings_fields( Admin_Settings::GROUP ); ?>

        <!-- Connection -->
        <div class="mmorph-tab-panel active" id="tab-connection">
            <div class="mmorph-card">
                <div class="mmorph-card-header">
                    <h2><?php esc_html_e( 'PixelBin API Connection', 'mousemorph' ); ?></h2>
                    <p><?php esc_html_e( 'Connect your PixelBin account to enable AI caricature generation.', 'mousemorph' ); ?></p>
                </div>
                <div class="mmorph-card-body">
                    <table class="form-table"><?php do_settings_fields( 'mousemorph', 'mmorph_connection' ); ?></table>
                </div>
            </div>
            <div class="mmorph-card mmorph-card-info">
                <div class="mmorph-card-header"><h2><?php esc_html_e( 'Quick Setup', 'mousemorph' ); ?></h2></div>
                <div class="mmorph-card-body">
                    <ol class="mmorph-steps-list">
                        <li><?php esc_html_e( 'Create a free account at pixelbin.io', 'mousemorph' ); ?></li>
                        <li><?php esc_html_e( 'Find your Cloud Name: console.pixelbin.io → Settings → Details', 'mousemorph' ); ?></li>
                        <li><?php esc_html_e( 'Create an API Token: console.pixelbin.io → Settings → API Tokens → Create Token', 'mousemorph' ); ?></li>
                        <li><?php esc_html_e( 'Paste both values above and click "Test Connection"', 'mousemorph' ); ?></li>
                        <li><?php esc_html_e( 'Enable on your products (WooCommerce tab) or use [mousemorph] shortcode', 'mousemorph' ); ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Caricature AI -->
        <div class="mmorph-tab-panel" id="tab-caricature">
            <div class="mmorph-card">
                <div class="mmorph-card-header">
                    <h2><?php esc_html_e( 'Caricature AI Settings', 'mousemorph' ); ?></h2>
                    <p><?php esc_html_e( 'Choose between simple mode (one-click, no prompts) or custom mode (users describe a scene).', 'mousemorph' ); ?></p>
                </div>
                <div class="mmorph-card-body">
                    <table class="form-table"><?php do_settings_fields( 'mousemorph', 'mmorph_ai' ); ?></table>
                </div>
            </div>
        </div>

        <!-- Usage Limits -->
        <div class="mmorph-tab-panel" id="tab-limits">
            <div class="mmorph-card">
                <div class="mmorph-card-header">
                    <h2><?php esc_html_e( 'Credit Protection & Usage Limits', 'mousemorph' ); ?></h2>
                    <p><?php esc_html_e( 'Each generation uses PixelBin API credits. These limits prevent abuse.', 'mousemorph' ); ?></p>
                </div>
                <div class="mmorph-card-body">
                    <table class="form-table"><?php do_settings_fields( 'mousemorph', 'mmorph_limits' ); ?></table>
                </div>
            </div>
        </div>

        <!-- WooCommerce -->
        <div class="mmorph-tab-panel" id="tab-woocommerce">
            <div class="mmorph-card">
                <div class="mmorph-card-header">
                    <h2><?php esc_html_e( 'WooCommerce Integration', 'mousemorph' ); ?></h2>
                    <p><?php esc_html_e( 'Control where the caricature maker appears on your store.', 'mousemorph' ); ?></p>
                </div>
                <div class="mmorph-card-body">
                    <table class="form-table"><?php do_settings_fields( 'mousemorph', 'mmorph_woo' ); ?></table>
                </div>
            </div>
            <div class="mmorph-card mmorph-card-info">
                <div class="mmorph-card-header"><h2><?php esc_html_e( 'Per-Product Setup', 'mousemorph' ); ?></h2></div>
                <div class="mmorph-card-body">
                    <ol class="mmorph-steps-list">
                        <li><?php esc_html_e( 'Edit a product in WooCommerce', 'mousemorph' ); ?></li>
                        <li><?php esc_html_e( 'In Product Data → General, check "MouseMorph Caricature"', 'mousemorph' ); ?></li>
                        <li><?php esc_html_e( 'Save — the caricature maker will appear on that product page', 'mousemorph' ); ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Content Safety -->
        <div class="mmorph-tab-panel" id="tab-moderation">
            <div class="mmorph-card">
                <div class="mmorph-card-header">
                    <h2><?php esc_html_e( 'Content Safety & Moderation', 'mousemorph' ); ?></h2>
                    <p><?php esc_html_e( 'Block inappropriate content from user prompts and uploaded images.', 'mousemorph' ); ?></p>
                </div>
                <div class="mmorph-card-body">
                    <table class="form-table"><?php do_settings_fields( 'mousemorph', 'mmorph_moderation' ); ?></table>
                </div>
            </div>
        </div>

        <!-- Shortcode -->
        <div class="mmorph-tab-panel" id="tab-shortcode">
            <div class="mmorph-card">
                <div class="mmorph-card-header">
                    <h2><?php esc_html_e( 'Shortcode Usage', 'mousemorph' ); ?></h2>
                    <p><?php esc_html_e( 'Embed the caricature maker anywhere, even without WooCommerce.', 'mousemorph' ); ?></p>
                </div>
                <div class="mmorph-card-body">
                    <h3><?php esc_html_e( 'Basic', 'mousemorph' ); ?></h3>
                    <pre><code>[mousemorph]</code></pre>
                    <h3><?php esc_html_e( 'Custom Title', 'mousemorph' ); ?></h3>
                    <pre><code>[mousemorph title="Turn Yourself Into a Mouse!"]</code></pre>
                </div>
            </div>
        </div>

        <?php submit_button( __( 'Save Settings', 'mousemorph' ) ); ?>
    </form>
</div>
