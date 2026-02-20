<?php
/**
 * Frontend Caricature Maker Form.
 *
 * Used on WooCommerce product pages and via [mousemorph] shortcode.
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title         = $shortcode_atts['title'] ?? __( 'Create Your Mouse Caricature', 'mousemorph' );
$is_standalone = $is_standalone ?? false;
$prompts_on    = MouseMorph::get_option( 'enable_prompts', 'no' ) === 'yes';
?>

<div class="mmorph-maker" id="mmorph-maker" data-standalone="<?php echo $is_standalone ? '1' : '0'; ?>">

    <!-- Header -->
    <div class="mmorph-header">
        <div class="mmorph-icon">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="18" cy="18" r="18" fill="#6C5CE7" opacity="0.1"/>
                <circle cx="11" cy="10" r="5" fill="#6C5CE7" opacity="0.6"/>
                <circle cx="25" cy="10" r="5" fill="#6C5CE7" opacity="0.6"/>
                <circle cx="18" cy="20" r="8" fill="#6C5CE7"/>
                <circle cx="15" cy="18" r="1.5" fill="#fff"/>
                <circle cx="21" cy="18" r="1.5" fill="#fff"/>
                <ellipse cx="18" cy="22" rx="2" ry="1" fill="#FFB8C6"/>
            </svg>
        </div>
        <div>
            <h3 class="mmorph-title"><?php echo esc_html( $title ); ?></h3>
            <p class="mmorph-subtitle">
                <?php if ( $prompts_on ) : ?>
                    <?php esc_html_e( 'Upload your photo, describe your scene, and we\'ll turn you into a fun mouse character!', 'mousemorph' ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Upload your photo and we\'ll turn you into a fun mouse caricature in one click!', 'mousemorph' ); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="mmorph-steps-bar">
        <div class="mmorph-step active" data-step="1">
            <span class="mmorph-step-num">1</span>
            <span class="mmorph-step-label"><?php esc_html_e( 'Upload Photo', 'mousemorph' ); ?></span>
        </div>
        <?php if ( $prompts_on ) : ?>
        <div class="mmorph-step-line"></div>
        <div class="mmorph-step" data-step="2">
            <span class="mmorph-step-num">2</span>
            <span class="mmorph-step-label"><?php esc_html_e( 'Describe Scene', 'mousemorph' ); ?></span>
        </div>
        <?php endif; ?>
        <div class="mmorph-step-line"></div>
        <div class="mmorph-step" data-step="<?php echo $prompts_on ? '3' : '2'; ?>">
            <span class="mmorph-step-num"><?php echo $prompts_on ? '3' : '2'; ?></span>
            <span class="mmorph-step-label"><?php esc_html_e( 'Your Caricature', 'mousemorph' ); ?></span>
        </div>
    </div>

    <!-- Step 1: Upload -->
    <div class="mmorph-panel active" id="mmorph-step-1">
        <input type="file" id="mmorph-file-input" accept="image/jpeg,image/png,image/webp" class="mmorph-file-input"/>
        <label for="mmorph-file-input" class="mmorph-upload-zone" id="mmorph-dropzone">
            <div class="mmorph-upload-icon">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                    <rect width="48" height="48" rx="12" fill="#6C5CE7" opacity="0.08"/>
                    <path d="M24 16v16M16 24h16" stroke="#6C5CE7" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </div>
            <p class="mmorph-upload-text"><?php esc_html_e( 'Drag & drop your photo here', 'mousemorph' ); ?></p>
            <p class="mmorph-upload-hint"><?php esc_html_e( 'or click to browse — JPG, PNG, WebP up to 10 MB', 'mousemorph' ); ?></p>
        </label>

        <div class="mmorph-upload-preview hidden" id="mmorph-upload-preview">
            <img id="mmorph-preview-img" src="" alt="<?php esc_attr_e( 'Your uploaded photo', 'mousemorph' ); ?>"/>
            <button type="button" class="mmorph-btn-change" id="mmorph-change-photo">
                <?php esc_html_e( 'Change Photo', 'mousemorph' ); ?>
            </button>
        </div>

        <p class="mmorph-tip">
            <strong><?php esc_html_e( 'Tip:', 'mousemorph' ); ?></strong>
            <?php esc_html_e( 'Use a clear, well-lit photo where your face is clearly visible for the best results!', 'mousemorph' ); ?>
        </p>

        <?php if ( ! $prompts_on ) : ?>
        <div class="mmorph-rate-info hidden" id="mmorph-rate-info">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:-3px">
                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                <path d="M8 4v4l2.5 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span id="mmorph-rate-text"></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( $prompts_on ) : ?>
    <!-- Step 2: Describe Scene -->
    <div class="mmorph-panel" id="mmorph-step-2">
        <button type="button" class="mmorph-btn-back" id="mmorph-back-to-upload">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:-3px">
                <path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php esc_html_e( 'Back — change photo', 'mousemorph' ); ?>
        </button>

        <label for="mmorph-scene" class="mmorph-label">
            <?php esc_html_e( 'Describe your scene', 'mousemorph' ); ?>
            <span class="mmorph-label-opt"><?php esc_html_e( '(optional but fun!)', 'mousemorph' ); ?></span>
        </label>
        <textarea id="mmorph-scene" class="mmorph-textarea" rows="4" maxlength="500"
            placeholder="<?php esc_attr_e( "Example: I'm a mouse chef cooking a giant pizza in a cozy Italian kitchen, wearing a tiny chef hat, with a surprised cat peeking through the window…", 'mousemorph' ); ?>"></textarea>
        <div class="mmorph-char-count"><span id="mmorph-char-count">0</span>/500</div>

        <div class="mmorph-ideas">
            <p class="mmorph-ideas-label"><?php esc_html_e( 'Need inspiration? Try one:', 'mousemorph' ); ?></p>
            <div class="mmorph-idea-chips">
                <button type="button" class="mmorph-chip" data-prompt="<?php esc_attr_e( 'A mouse detective with a magnifying glass investigating a cheese mystery in a cozy library', 'mousemorph' ); ?>">
                    <?php esc_html_e( 'Mouse Detective', 'mousemorph' ); ?>
                </button>
                <button type="button" class="mmorph-chip" data-prompt="<?php esc_attr_e( 'A mouse astronaut floating in space with Earth in the background, wearing a tiny space suit', 'mousemorph' ); ?>">
                    <?php esc_html_e( 'Space Mouse', 'mousemorph' ); ?>
                </button>
                <button type="button" class="mmorph-chip" data-prompt="<?php esc_attr_e( 'A mouse musician playing a tiny guitar on stage with colorful spotlights and a cheering crowd', 'mousemorph' ); ?>">
                    <?php esc_html_e( 'Rock Star Mouse', 'mousemorph' ); ?>
                </button>
                <button type="button" class="mmorph-chip" data-prompt="<?php esc_attr_e( 'A mouse superhero with a tiny cape flying over a miniature city, looking heroic', 'mousemorph' ); ?>">
                    <?php esc_html_e( 'Superhero Mouse', 'mousemorph' ); ?>
                </button>
                <button type="button" class="mmorph-chip" data-prompt="<?php esc_attr_e( 'A mouse pirate on a tiny ship sailing through a bathtub ocean with a parrot on their shoulder', 'mousemorph' ); ?>">
                    <?php esc_html_e( 'Pirate Mouse', 'mousemorph' ); ?>
                </button>
                <button type="button" class="mmorph-chip" data-prompt="<?php esc_attr_e( 'A family of mice having a picnic in a garden with flowers, tiny sandwiches, and a cat hiding behind a bush', 'mousemorph' ); ?>">
                    <?php esc_html_e( 'Family Picnic', 'mousemorph' ); ?>
                </button>
            </div>
        </div>

        <div class="mmorph-rate-info hidden" id="mmorph-rate-info">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:-3px">
                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                <path d="M8 4v4l2.5 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span id="mmorph-rate-text"></span>
        </div>

        <button type="button" class="mmorph-btn mmorph-btn-primary" id="mmorph-generate-btn">
            <span class="mmorph-btn-icon">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M10 2l2.5 5 5.5.8-4 3.9.9 5.3L10 14.5 5.1 17l.9-5.3-4-3.9 5.5-.8L10 2z" fill="currentColor"/>
                </svg>
            </span>
            <?php esc_html_e( 'Generate My Mouse Caricature!', 'mousemorph' ); ?>
        </button>
    </div>
    <?php endif; ?>

    <!-- Result Step -->
    <div class="mmorph-panel" id="mmorph-step-<?php echo $prompts_on ? '3' : '2'; ?>">
        <div class="mmorph-result-container">
            <div class="mmorph-result-card">
                <img id="mmorph-result-img" src="" alt="<?php esc_attr_e( 'Your mouse caricature', 'mousemorph' ); ?>"/>
            </div>
            <div class="mmorph-result-actions">
                <a href="#" id="mmorph-download-btn" class="mmorph-btn mmorph-btn-outline" download>
                    <?php esc_html_e( 'Download', 'mousemorph' ); ?>
                </a>
                <button type="button" class="mmorph-btn mmorph-btn-outline" id="mmorph-regenerate-btn">
                    <?php esc_html_e( 'Try Again', 'mousemorph' ); ?>
                </button>
                <button type="button" class="mmorph-btn mmorph-btn-outline" id="mmorph-new-photo-btn">
                    <?php esc_html_e( 'New Photo', 'mousemorph' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="mmorph-loading hidden" id="mmorph-loading">
        <div class="mmorph-loading-inner">
            <div class="mmorph-spinner"></div>
            <p class="mmorph-loading-text" id="mmorph-loading-text"><?php esc_html_e( 'Creating your caricature…', 'mousemorph' ); ?></p>
            <div class="mmorph-progress-bar">
                <div class="mmorph-progress-fill" id="mmorph-progress-fill"></div>
            </div>
        </div>
    </div>

    <!-- Error -->
    <div class="mmorph-error hidden" id="mmorph-error">
        <p id="mmorph-error-text"></p>
        <button type="button" class="mmorph-btn mmorph-btn-outline mmorph-btn-sm" id="mmorph-error-dismiss">
            <?php esc_html_e( 'Dismiss', 'mousemorph' ); ?>
        </button>
    </div>

    <!-- Hidden fields for WooCommerce form -->
    <input type="hidden" name="mmorph_upload_id" id="mmorph-upload-id" value=""/>
    <input type="hidden" name="mmorph_caricature_url" id="mmorph-caricature-url" value=""/>
</div>
