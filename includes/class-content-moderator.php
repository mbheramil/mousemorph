<?php
/**
 * Content Moderator — enforces "nothing sexual, violent, or insulting to any religion."
 *
 * Three layers:
 *   1. Blocked-term word matching (admin-configurable)
 *   2. Regex pattern detection (sexual / violent / religious insults)
 *   3. PixelBin NSFW image scan
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Content_Moderator {

    /**
     * Check a user prompt against blocked terms and category patterns.
     *
     * @return true|WP_Error
     */
    public function check_prompt( string $prompt ) {
        $lower = strtolower( trim( $prompt ) );
        if ( empty( $lower ) ) {
            return true;
        }

        $blocked_raw = MouseMorph::get_option( 'blocked_terms', '' );
        $terms       = array_filter( array_map( 'trim', explode( "\n", strtolower( $blocked_raw ) ) ) );

        foreach ( $terms as $term ) {
            if ( empty( $term ) ) {
                continue;
            }
            if ( preg_match( '/\b' . preg_quote( $term, '/' ) . '\b/i', $lower ) ) {
                return new WP_Error(
                    'blocked_content',
                    __( 'Your description contains content that is not allowed. Please keep it fun and family-friendly!', 'mousemorph' )
                );
            }
        }

        return $this->check_categories( $lower );
    }

    /**
     * Check uploaded image for NSFW content via PixelBin.
     *
     * @return true|WP_Error
     */
    public function check_image( string $pixelbin_path ) {
        if ( MouseMorph::get_option( 'nsfw_check' ) !== 'yes' ) {
            return true;
        }
        return MouseMorph::instance()->api->check_nsfw( $pixelbin_path );
    }

    /**
     * Sanitise a user prompt before sending to the API.
     */
    public function sanitize_prompt( string $prompt ): string {
        $prompt = wp_strip_all_tags( $prompt );
        $prompt = mb_substr( $prompt, 0, 500 );
        $prompt = preg_replace( '/\s+/', ' ', $prompt );
        return trim( $prompt );
    }

    /* ── Category Pattern Checks ────────────────── */

    private function check_categories( string $text ) {
        $sexual = [
            '/\b(sex|sexy|seduc|erotic|lingerie|bikini|topless|underwear|strip)\b/i',
            '/\b(nsfw|xxx|adult\s+content|18\+|r[\-\s]?rated)\b/i',
        ];
        foreach ( $sexual as $p ) {
            if ( preg_match( $p, $text ) ) {
                return new WP_Error( 'blocked_sexual', __( 'Please keep your description family-friendly. Sexual or explicit content is not allowed.', 'mousemorph' ) );
            }
        }

        $violent = [
            '/\b(blood|gore|stab|shoot|murder|kill|dead|corpse|decapitat|dismember)\b/i',
            '/\b(assault|beat\s+up|attack|weapon|sword|gun|rifle|bomb)\b/i',
        ];
        foreach ( $violent as $p ) {
            if ( preg_match( $p, $text ) ) {
                return new WP_Error( 'blocked_violence', __( 'Violent content is not allowed. Please describe a fun, positive scene instead!', 'mousemorph' ) );
            }
        }

        $religion = [
            '/\b(insult|mock|ridicul|profan|blashem|desecrat)\w*\s+(religion|religious|god|allah|jesus|buddha|church|mosque|temple|synagogue|hindu|muslim|christian|jewish|sikh)\b/i',
            '/\b(religion|religious|god|allah|jesus|buddha|church|mosque|temple|synagogue)\w*\s+(stupid|dumb|evil|fake|scam|curse)\b/i',
        ];
        foreach ( $religion as $p ) {
            if ( preg_match( $p, $text ) ) {
                return new WP_Error( 'blocked_religion', __( 'Content that is disrespectful to any religion is not allowed.', 'mousemorph' ) );
            }
        }

        return true;
    }
}
