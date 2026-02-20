<?php
/**
 * Caricature Engine — upload handling, prompt building, generation AJAX,
 * rate limiting, and status polling.
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Caricature_Engine {

    const DEFAULT_CARICATURE_PROMPT = 'Transform this photo into a 3D cartoon caricature character. Exaggerated facial features, big expressive eyes, smooth cartoon skin, fun playful expression. Pixar Disney animation style, colorful, high quality 3D render. Keep the person recognizable.';

    const DEFAULT_MOUSE_PROMPT = 'Transform this photo into a 3D cartoon mouse caricature character with big round mouse ears, pink nose, and whiskers. Exaggerated facial features, big expressive eyes, smooth cartoon skin, fun playful expression. Pixar Disney animation style, colorful, high quality 3D render. Keep the person recognizable as a mouse character.';

    public function __construct() {
        add_action( 'wp_ajax_mmorph_upload_photo',  [ $this, 'ajax_upload' ] );
        add_action( 'wp_ajax_nopriv_mmorph_upload_photo', [ $this, 'ajax_upload' ] );

        add_action( 'wp_ajax_mmorph_generate',      [ $this, 'ajax_generate' ] );
        add_action( 'wp_ajax_nopriv_mmorph_generate', [ $this, 'ajax_generate' ] );

        add_action( 'wp_ajax_mmorph_poll_status',    [ $this, 'ajax_poll' ] );
        add_action( 'wp_ajax_nopriv_mmorph_poll_status', [ $this, 'ajax_poll' ] );

        add_action( 'wp_ajax_mmorph_rate_info',      [ $this, 'ajax_rate_info' ] );
        add_action( 'wp_ajax_nopriv_mmorph_rate_info', [ $this, 'ajax_rate_info' ] );
    }

    /* ── Prompt Builder ─────────────────────────── */

    public function build_prompt( string $user_scene = '' ): string {
        $method = MouseMorph::get_option( 'transform_method', 'portrait' );

        $system = MouseMorph::get_option( 'system_prompt', '' );

        if ( empty( $system ) ) {
            $system = self::DEFAULT_MOUSE_PROMPT;
        }

        if ( $method === 'portrait' && empty( $user_scene ) ) {
            return '';
        }

        $prompt = trim( $system );

        if ( ! empty( $user_scene ) ) {
            $prompt .= ' Scene/details: ' . trim( $user_scene ) . '.';
        }

        $prompt = preg_replace( '/[\r\n\t]+/', ' ', $prompt );
        $prompt = preg_replace( '/\s{2,}/', ' ', $prompt );

        return trim( $prompt );
    }

    /* ── Rate Limiting ──────────────────────────── */

    private function visitor_id(): string {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
        }
        return 'ip_' . md5( $ip . wp_salt( 'auth' ) );
    }

    private function daily_limit(): int {
        if ( is_user_logged_in() ) {
            return absint( MouseMorph::get_option( 'daily_limit_user', 5 ) );
        }
        return absint( MouseMorph::get_option( 'daily_limit_guest', 3 ) );
    }

    private function today_count( string $vid ): int {
        return absint( get_transient( 'mmorph_daily_' . $vid . '_' . gmdate( 'Ymd' ) ) );
    }

    private function increment( string $vid ): void {
        $key = 'mmorph_daily_' . $vid . '_' . gmdate( 'Ymd' );
        set_transient( $key, $this->today_count( $vid ) + 1, DAY_IN_SECONDS );
    }

    public function rate_info(): array {
        $vid       = $this->visitor_id();
        $limit     = $this->daily_limit();
        $used      = $this->today_count( $vid );
        $remaining = max( 0, $limit - $used );

        return compact( 'limit', 'used', 'remaining' ) + [ 'logged_in' => is_user_logged_in() ];
    }

    /* ── AJAX: Rate Info ────────────────────────── */

    public function ajax_rate_info(): void {
        check_ajax_referer( 'mmorph_nonce', 'nonce' );
        wp_send_json_success( $this->rate_info() );
    }

    /* ── AJAX: Upload Photo ─────────────────────── */

    public function ajax_upload(): void {
        check_ajax_referer( 'mmorph_nonce', 'nonce' );

        if ( ! MouseMorph::is_configured() ) {
            wp_send_json_error( __( 'Caricature maker is not configured. Please contact the site administrator.', 'mousemorph' ) );
        }

        if ( empty( $_FILES['photo'] ) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( __( 'No file uploaded or upload error occurred.', 'mousemorph' ) );
        }

        $file    = $_FILES['photo'];
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
        $finfo   = finfo_open( FILEINFO_MIME_TYPE );
        $mime    = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        if ( ! in_array( $mime, $allowed, true ) ) {
            wp_send_json_error( __( 'Invalid file type. Please upload a JPG, PNG, or WebP image.', 'mousemorph' ) );
        }
        if ( $file['size'] > 10 * 1024 * 1024 ) {
            wp_send_json_error( __( 'File is too large. Maximum size is 10 MB.', 'mousemorph' ) );
        }

        $ext       = pathinfo( $file['name'], PATHINFO_EXTENSION ) ?: 'jpg';
        $uid       = wp_generate_uuid4();
        $filename  = $uid . '.' . $ext;

        $upload_dir = wp_upload_dir();
        $local_dir  = $upload_dir['basedir'] . '/mousemorph/uploads';
        wp_mkdir_p( $local_dir );
        $local_path = $local_dir . '/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $local_path ) ) {
            wp_send_json_error( __( 'Failed to save uploaded file.', 'mousemorph' ) );
        }

        $dest   = 'mousemorph/uploads/' . $filename;
        $api    = MouseMorph::instance()->api;
        $result = $api->upload_file( $local_path, $dest );

        if ( is_wp_error( $result ) ) {
            @unlink( $local_path );
            wp_send_json_error( $result->get_error_message() );
        }

        $pb_path = $dest;
        $pb_url  = '';

        if ( ! empty( $result['url'] ) ) {
            $pb_url = $result['url'];
        }
        if ( ! empty( $result['filePath'] ) ) {
            $pb_path = ltrim( $result['filePath'], '/' );
        } elseif ( ! empty( $result['path'] ) && ! empty( $result['name'] ) ) {
            $pb_path = ltrim( $result['path'], '/' ) . '/' . $result['name'];
        }

        $preview = $pb_url ?: $api->build_cdn_url( $pb_path, 'original' );

        if ( MouseMorph::get_option( 'nsfw_check', 'yes' ) === 'yes' ) {
            $safe = MouseMorph::instance()->moderator->check_image( $pb_path );
            if ( is_wp_error( $safe ) ) {
                @unlink( $local_path );
                wp_send_json_error( $safe->get_error_message() );
            }
        }

        set_transient( 'mmorph_upload_' . $uid, [
            'pixelbin_path' => $pb_path,
            'pixelbin_url'  => $pb_url,
            'local_path'    => $local_path,
            'filename'      => $filename,
            'uploaded_at'   => current_time( 'mysql' ),
        ], HOUR_IN_SECONDS );

        wp_send_json_success( [
            'upload_id'   => $uid,
            'preview_url' => $preview,
        ] );
    }

    /* ── AJAX: Generate ─────────────────────────── */

    public function ajax_generate(): void {
        check_ajax_referer( 'mmorph_nonce', 'nonce' );

        if ( ! MouseMorph::is_configured() ) {
            wp_send_json_error( __( 'Caricature maker is not configured.', 'mousemorph' ) );
        }

        if ( MouseMorph::get_option( 'require_login', 'no' ) === 'yes' && ! is_user_logged_in() ) {
            wp_send_json_error( __( 'Please log in or create an account to generate caricatures.', 'mousemorph' ) );
        }

        $upload_id = sanitize_text_field( $_POST['upload_id'] ?? '' );
        $scene     = sanitize_textarea_field( $_POST['scene'] ?? '' );

        if ( empty( $upload_id ) ) {
            wp_send_json_error( __( 'Please upload a photo first.', 'mousemorph' ) );
        }

        $upload_data = get_transient( 'mmorph_upload_' . $upload_id );
        if ( ! $upload_data ) {
            wp_send_json_error( __( 'Upload session expired. Please upload your photo again.', 'mousemorph' ) );
        }

        $vid   = $this->visitor_id();
        $limit = $this->daily_limit();
        $used  = $this->today_count( $vid );

        if ( $used >= $limit ) {
            $msg = sprintf( __( 'Daily limit of %d caricature generations reached. Please come back tomorrow!', 'mousemorph' ), $limit );
            if ( ! is_user_logged_in() ) {
                $user_limit = absint( MouseMorph::get_option( 'daily_limit_user', 5 ) );
                if ( $user_limit > $limit ) {
                    $msg .= ' ' . sprintf( __( 'Log in to get %d generations per day.', 'mousemorph' ), $user_limit );
                }
            }
            wp_send_json_error( $msg );
        }

        $prompts_on = MouseMorph::get_option( 'enable_prompts', 'no' ) === 'yes';
        if ( $prompts_on && ! empty( $scene ) ) {
            $mod   = MouseMorph::instance()->moderator;
            $scene = $mod->sanitize_prompt( $scene );
            $check = $mod->check_prompt( $scene );
            if ( is_wp_error( $check ) ) {
                wp_send_json_error( $check->get_error_message() );
            }
        } else {
            $scene = '';
        }

        $prompt = $this->build_prompt( $scene );

        $api    = MouseMorph::instance()->api;
        $method = MouseMorph::get_option( 'transform_method', 'portrait' );
        $result = $api->generate_caricature(
            $upload_data['pixelbin_url'] ?? '',
            $upload_data['pixelbin_path'],
            $prompt
        );

        if ( is_wp_error( $result ) ) {
            $debug = sprintf(
                '[Debug] method=%s, path=%s, error_code=%s',
                $method,
                $upload_data['pixelbin_path'] ?? '(none)',
                $result->get_error_code()
            );
            wp_send_json_error( $result->get_error_message() . ' ' . $debug );
        }

        $this->increment( $vid );
        $remaining = max( 0, $limit - ( $used + 1 ) );

        $poll_mode  = $result['poll_mode'] ?? 'prediction';
        $request_id = $result['request_id'] ?? '';
        $image_url  = $result['url'] ?? '';

        if ( ! empty( $image_url ) && ( $result['status'] ?? '' ) === 'ready' ) {
            $local_url = $this->download_result_image( $image_url );
            if ( $local_url ) {
                $image_url = $local_url;
            }
        }

        set_transient( 'mmorph_result_' . $upload_id, [
            'request_id'    => $request_id,
            'caricature_url' => $image_url,
            'poll_mode'      => $poll_mode,
            'scene'          => $scene,
            'generated_at'   => current_time( 'mysql' ),
        ], 2 * HOUR_IN_SECONDS );

        wp_send_json_success( [
            'caricature_url' => $image_url,
            'request_id'     => $request_id,
            'poll_mode'      => $poll_mode,
            'gen_status'     => $result['status'],
            'upload_id'      => $upload_id,
            'remaining'      => $remaining,
            'limit'          => $limit,
        ] );
    }

    /* ── Download result image to bypass hotlink protection ── */

    /**
     * Download a prediction output image and save it locally.
     *
     * Strategy (in priority order):
     * 1. URL Upload — Tell PixelBin's API to copy the delivery URL into the
     *    user's CDN storage, then download from the CDN (no hotlink protection).
     * 2. Direct download — Attempt to fetch the image bytes directly via cURL /
     *    wp_remote_get with various headers (fallback if URL Upload is unavailable).
     */
    private function download_result_image( string $remote_url ): string {
        if ( empty( $remote_url ) ) {
            return '';
        }

        $upload_dir  = wp_upload_dir();
        $results_dir = $upload_dir['basedir'] . '/mousemorph/results';
        wp_mkdir_p( $results_dir );

        $uuid = wp_generate_uuid4();
        $name = 'caricature-' . $uuid . '.png';

        $cdn_url = $this->copy_via_url_upload( $remote_url, $uuid );

        $download_src = ! empty( $cdn_url ) ? $cdn_url : $remote_url;

        $body = $this->fetch_image_bytes( $download_src );

        if ( empty( $body ) && $download_src !== $remote_url ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[MouseMorph] CDN download failed, trying original URL as fallback' );
            }
            $body = $this->fetch_image_bytes( $remote_url );
        }

        if ( empty( $body ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[MouseMorph] All download methods failed for ' . $remote_url );
            }
            if ( ! empty( $cdn_url ) ) {
                return $cdn_url;
            }
            return '';
        }

        if ( substr( $body, 0, 3 ) === "\xFF\xD8\xFF" ) {
            $name = str_replace( '.png', '.jpg', $name );
        } elseif ( substr( $body, 0, 4 ) === 'RIFF' && substr( $body, 8, 4 ) === 'WEBP' ) {
            $name = str_replace( '.png', '.webp', $name );
        }

        $path    = $results_dir . '/' . $name;
        $written = file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( ! $written ) {
            return ! empty( $cdn_url ) ? $cdn_url : '';
        }

        $local_url = $upload_dir['baseurl'] . '/mousemorph/results/' . $name;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MouseMorph] Image saved locally: ' . $local_url );
        }

        return $local_url;
    }

    /**
     * Use the PixelBin URL Upload API to copy a delivery URL into the user's
     * CDN storage. Returns the CDN URL on success, empty string on failure.
     */
    private function copy_via_url_upload( string $remote_url, string $uuid ): string {
        $api = MouseMorph::instance()->api;

        $ext = 'png';
        if ( preg_match( '/\.(jpe?g|webp|png)$/i', $remote_url, $m ) ) {
            $ext = strtolower( $m[1] );
            if ( $ext === 'jpeg' ) {
                $ext = 'jpg';
            }
        }

        $file_name = 'caricature-' . $uuid . '.' . $ext;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MouseMorph] Attempting URL Upload: ' . $remote_url . ' → /mousemorph/results/' . $file_name );
        }

        $result = $api->url_upload( $remote_url, '/mousemorph/results', $file_name );

        if ( is_wp_error( $result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[MouseMorph] URL Upload failed: ' . $result->get_error_message() );
            }
            return '';
        }

        $cdn_url = $result['url'] ?? '';

        if ( empty( $cdn_url ) && ! empty( $result['filePath'] ) ) {
            $cdn_url = $api->build_cdn_url( ltrim( $result['filePath'], '/' ), 'original' );
        }

        if ( empty( $cdn_url ) && ! empty( $result['path'] ) && ! empty( $result['name'] ) ) {
            $path = ltrim( $result['path'], '/' ) . '/' . $result['name'];
            $cdn_url = $api->build_cdn_url( $path, 'original' );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MouseMorph] URL Upload result: ' . ( $cdn_url ?: '(no URL)' ) . ' keys=' . implode( ',', array_keys( $result ) ) );
        }

        return $cdn_url;
    }

    /**
     * Fetch raw image bytes from a URL using multiple strategies.
     */
    private function fetch_image_bytes( string $url ): string {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MouseMorph] fetch_image_bytes: ' . $url );
        }

        $body = $this->wp_download( $url, '' );
        if ( ! empty( $body ) && strlen( $body ) > 1000 ) {
            return $body;
        }

        if ( function_exists( 'curl_init' ) ) {
            $body = $this->curl_download( $url );
            if ( ! empty( $body ) && strlen( $body ) > 1000 ) {
                return $body;
            }
        }

        return '';
    }

    private function curl_download( string $url ): string {
        $ch = curl_init(); // phpcs:ignore WordPress.WP.AlternativeFunctions
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Accept: image/png,image/webp,image/jpeg,image/*,*/*;q=0.8',
            ],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_ENCODING       => '',
        ] );

        $body = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $err  = curl_error( $ch );
        curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( $err ) {
                error_log( '[MouseMorph] cURL error: ' . $err );
            }
            if ( $code !== 200 ) {
                error_log( '[MouseMorph] cURL HTTP ' . $code . ' for ' . $url );
            }
        }

        return ( $code === 200 && $body ) ? $body : '';
    }

    private function wp_download( string $url, string $referer ): string {
        $headers = [ 'Accept' => 'image/*,*/*' ];
        if ( $referer ) {
            $headers['Referer'] = $referer;
        }

        $response = wp_remote_get( $url, [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => $headers,
        ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[MouseMorph] wp_download HTTP ' . $code . ' for ' . $url );
            }
            return '';
        }

        return wp_remote_retrieve_body( $response );
    }

    /* ── AJAX: Poll Status ──────────────────────── */

    public function ajax_poll(): void {
        check_ajax_referer( 'mmorph_nonce', 'nonce' );

        $poll_mode  = sanitize_text_field( $_POST['poll_mode'] ?? 'prediction' );
        $request_id = sanitize_text_field( $_POST['request_id'] ?? '' );
        $url        = esc_url_raw( $_POST['url'] ?? '' );
        $upload_id  = sanitize_text_field( $_POST['upload_id'] ?? '' );

        $api = MouseMorph::instance()->api;

        if ( $poll_mode === 'prediction' && ! empty( $request_id ) ) {
            $result = $api->check_prediction_status( $request_id );

            if ( $result['status'] === 'ready' && ! empty( $result['url'] ) ) {
                $local_url = $this->download_result_image( $result['url'] );
                if ( $local_url ) {
                    $result['url'] = $local_url;
                    $this->update_result_transient( $upload_id, $local_url );
                }
            }

            wp_send_json_success( $result );
        }

        if ( ! empty( $url ) ) {
            $status = $api->check_url_status( $url );

            if ( $status === 'ready' ) {
                $local_url = $this->download_result_image( $url );
                if ( $local_url ) {
                    $url = $local_url;
                    $this->update_result_transient( $upload_id, $local_url );
                }
            }

            wp_send_json_success( [ 'status' => $status, 'url' => $url ] );
        }

        wp_send_json_error( __( 'Missing polling parameters.', 'mousemorph' ) );
    }

    private function update_result_transient( string $upload_id, string $local_url ): void {
        if ( empty( $upload_id ) ) {
            return;
        }
        $transient = get_transient( 'mmorph_result_' . $upload_id );
        if ( ! is_array( $transient ) ) {
            return;
        }
        $transient['caricature_url'] = $local_url;
        set_transient( 'mmorph_result_' . $upload_id, $transient, 2 * HOUR_IN_SECONDS );
    }
}
