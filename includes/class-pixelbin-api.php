<?php
/**
 * PixelBin API — handles authentication, file upload, CDN URL building,
 * and transformation URL generation.
 *
 * Auth: HMAC-SHA256 signature (3 headers).
 * Ref:  https://pixelbin.io/docs/api/signature-generation
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PixelBin_API {

    const CDN_BASE = 'https://cdn.pixelbin.io';
    const API_HOST = 'api.pixelbin.io';

    public function __construct() {
        add_action( 'wp_ajax_mmorph_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    /* ── Authentication ─────────────────────────── */

    private function build_auth_headers( string $method, string $path, string $query_string = '', string $body = '', bool $is_multipart = false ): array {
        $token     = MouseMorph::get_option( 'api_token' );
        $timestamp = gmdate( 'Ymd\THis\Z' );

        $body_hash = hash( 'sha256', $is_multipart ? '' : $body );

        $signed_headers = [
            'host'        => self::API_HOST,
            'x-ebg-param' => $timestamp,
        ];

        $header_pairs = '';
        foreach ( $signed_headers as $k => $v ) {
            $header_pairs .= $k . ':' . $v . "\n";
        }
        $header_keys = implode( ';', array_keys( $signed_headers ) );

        $signing_string = implode( "\n", [
            strtoupper( $method ),
            $path,
            $query_string,
            $header_pairs,
            $header_keys,
            $body_hash,
        ] );

        $hashed    = hash( 'sha256', $signing_string );
        $to_sign   = $timestamp . "\n" . $hashed;
        $signature = 'v1:' . hash_hmac( 'sha256', $to_sign, $token );

        return [
            'Authorization'   => 'Bearer ' . base64_encode( $token ),
            'x-ebg-param'     => base64_encode( $timestamp ),
            'x-ebg-signature' => $signature,
            'Accept'          => 'application/json',
        ];
    }

    /* ── Generic Request ────────────────────────── */

    public function request( string $endpoint, string $method = 'GET', array $query = [], $body = null ) {
        ksort( $query );
        $qs = ! empty( $query ) ? http_build_query( $query ) : '';

        $body_str     = '';
        $is_multipart = false;
        if ( is_array( $body ) ) {
            $body_str = wp_json_encode( $body );
        } elseif ( is_string( $body ) ) {
            $body_str = $body;
        }

        $headers = $this->build_auth_headers( $method, $endpoint, $qs, $body_str, $is_multipart );

        if ( ! empty( $body_str ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $headers['Content-Type'] = 'application/json';
        }

        $url = 'https://' . self::API_HOST . $endpoint;
        if ( $qs ) {
            $url .= '?' . $qs;
        }

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];
        if ( ! empty( $body_str ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = $body_str;
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            return new WP_Error(
                'pixelbin_api_error',
                $data['message'] ?? sprintf( __( 'API error (HTTP %d).', 'mousemorph' ), $status ),
                [ 'status' => $status ]
            );
        }
        return $data ?? [];
    }

    /* ── CDN URL Builder ────────────────────────── */

    public function build_cdn_url( string $file_path, string $pattern = 'original', array $query = [] ): string {
        $cloud  = MouseMorph::get_option( 'cloud_name' );
        $zone   = MouseMorph::get_option( 'zone_slug' );
        $custom = MouseMorph::get_option( 'custom_domain' );
        $base   = ! empty( $custom ) ? rtrim( $custom, '/' ) : self::CDN_BASE;

        $parts = [ 'v2', $cloud ];
        if ( ! empty( $zone ) ) {
            $parts[] = $zone;
        }
        $parts[] = $pattern;
        $parts[] = ltrim( $file_path, '/' );

        $url = $base . '/' . implode( '/', $parts );
        if ( ! empty( $query ) ) {
            $url .= '?' . http_build_query( $query );
        }
        return $url;
    }

    /**
     * Replace /original/ in a CDN URL with a transformation pattern.
     */
    public function transform_url( string $original_url, string $pattern ) {
        $result = preg_replace( '#/original/#', '/' . $pattern . '/', $original_url, 1 );
        return ( $result !== $original_url ) ? $result : false;
    }

    /* ── File Upload ────────────────────────────── */

    public function upload_file( string $local_path, string $dest_path ) {
        if ( ! file_exists( $local_path ) ) {
            return new WP_Error( 'file_missing', __( 'Local file not found.', 'mousemorph' ) );
        }

        $mime      = wp_check_filetype( $local_path )['type'] ?? 'image/jpeg';
        $file_data = file_get_contents( $local_path ); // phpcs:ignore
        $boundary  = wp_generate_password( 24, false );

        $dir_path = dirname( $dest_path );
        if ( strpos( $dir_path, '/' ) !== 0 ) {
            $dir_path = '/' . $dir_path;
        }

        $file_basename = pathinfo( $dest_path, PATHINFO_FILENAME );

        $fields = [
            'path'      => $dir_path,
            'name'      => $file_basename,
            'access'    => 'public-read',
            'overwrite' => 'true',
        ];

        $body = '';
        foreach ( $fields as $name => $value ) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $dest_path ) . "\"\r\n";
        $body .= "Content-Type: {$mime}\r\n\r\n";
        $body .= $file_data . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $upload_path = '/service/platform/assets/v1.0/upload/direct';
        $headers     = $this->build_auth_headers( 'POST', $upload_path, '', '', true );
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $response = wp_remote_post( 'https://' . self::API_HOST . $upload_path, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            return new WP_Error(
                'upload_failed',
                $data['message'] ?? __( 'Upload failed.', 'mousemorph' ),
                [ 'status' => $status ]
            );
        }
        return $data ?? [];
    }

    /* ── URL Upload (copy remote file to CDN storage) ── */

    /**
     * Copy a remote image to PixelBin CDN storage via the URL Upload API.
     *
     * This lets PixelBin's own servers fetch the file (bypassing CDN hotlink
     * protection) and store it in the user's cloud namespace where it can be
     * served via cdn.pixelbin.io without restrictions.
     *
     * Endpoint: POST /service/platform/assets/v1.0/upload/url
     *
     * @param string $source_url  The remote URL to fetch (e.g. delivery.pixelbin.io).
     * @param string $dest_path   Storage path inside PixelBin (leading slash).
     * @param string $name        Desired file name (will be slugified by API).
     * @return array|WP_Error     Asset data on success, including 'url' field.
     */
    public function url_upload( string $source_url, string $dest_path = '/mousemorph/results', string $name = '' ) {
        $body = [
            'url'              => $source_url,
            'path'             => $dest_path,
            'access'           => 'public-read',
            'overwrite'        => true,
            'filenameOverride' => true,
        ];
        if ( ! empty( $name ) ) {
            $body['name'] = $name;
        }

        return $this->request( '/service/platform/assets/v1.0/upload/url', 'POST', [], $body );
    }

    /* ── Predictions API ────────────────────────── */

    /**
     * Create a prediction job via the PixelBin Predictions API.
     *
     * Endpoint: POST /service/platform/transformation/v1.0/predictions/{plugin}/{operation}
     * Body: multipart/form-data with input.{key} fields.
     *
     * @param string $plugin    Plugin identifier (e.g. 'img', 'portrait', 'vg').
     * @param string $operation Operation name (e.g. 'edit', 'generate').
     * @param array  $inputs    Key-value pairs sent as input.{key} form fields.
     * @return array|WP_Error   Job data on success (contains '_id'), WP_Error on failure.
     */
    public function create_prediction( string $plugin, string $operation, array $inputs = [] ) {
        $endpoint = '/service/platform/transformation/v1.0/predictions/' . $plugin . '/' . $operation;
        $boundary = wp_generate_password( 24, false );

        $body = '';
        foreach ( $inputs as $key => $value ) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"input.{$key}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $headers = $this->build_auth_headers( 'POST', $endpoint, '', '', true );
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $response = wp_remote_post( 'https://' . self::API_HOST . $endpoint, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            $raw  = wp_remote_retrieve_body( $response );
            $msg  = $data['message'] ?? '';
            if ( empty( $msg ) && ! empty( $data['error'] ) ) {
                $msg = is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
            }
            if ( empty( $msg ) ) {
                $msg = sprintf( __( 'Prediction API error (HTTP %d).', 'mousemorph' ), $status );
            }
            $debug = sprintf( ' [HTTP %d → %s/%s] raw=%s', $status, $plugin, $operation, substr( $raw, 0, 300 ) );
            return new WP_Error( 'prediction_failed', $msg . $debug, [ 'status' => $status ] );
        }

        return $data ?? [];
    }

    /**
     * Get prediction status/result by request ID.
     *
     * @param string $request_id  The prediction _id returned by create_prediction().
     * @return array|WP_Error     Prediction data with 'status' field (SUCCESS/FAILURE/PENDING).
     */
    public function get_prediction( string $request_id ) {
        $endpoint = '/service/platform/transformation/v1.0/predictions/' . rawurlencode( $request_id );
        return $this->request( $endpoint, 'GET' );
    }

    /**
     * Extract the output image URL from a completed prediction response.
     *
     * The Predictions API can return URLs in many nested structures:
     *   { output: { url: "..." } }
     *   { output: { images: ["url", ...] } }
     *   { output: ["url", ...] }
     *   { output: [{ url: "..." }] }
     *   { url: "..." }
     */
    public function extract_prediction_url( array $prediction ): string {
        $output = $prediction['output'] ?? null;

        if ( is_array( $output ) ) {
            if ( ! empty( $output['url'] ) && is_string( $output['url'] ) ) {
                return $output['url'];
            }

            if ( ! empty( $output['images'] ) && is_array( $output['images'] ) ) {
                foreach ( $output['images'] as $img ) {
                    if ( is_string( $img ) && preg_match( '#^https?://#', $img ) ) {
                        return $img;
                    }
                    if ( is_array( $img ) && ! empty( $img['url'] ) ) {
                        return $img['url'];
                    }
                }
            }

            foreach ( $output as $val ) {
                if ( is_string( $val ) && preg_match( '#^https?://#', $val ) ) {
                    return $val;
                }
                if ( is_array( $val ) && ! empty( $val['url'] ) ) {
                    return $val['url'];
                }
                if ( is_array( $val ) ) {
                    foreach ( $val as $inner ) {
                        if ( is_string( $inner ) && preg_match( '#^https?://#', $inner ) ) {
                            return $inner;
                        }
                        if ( is_array( $inner ) && ! empty( $inner['url'] ) ) {
                            return $inner['url'];
                        }
                    }
                }
            }
        }

        if ( ! empty( $prediction['url'] ) && is_string( $prediction['url'] ) ) {
            return $prediction['url'];
        }

        $deep = $this->find_url_deep( $prediction );
        if ( $deep ) {
            return $deep;
        }

        return '';
    }

    /**
     * Recursively search an array for the first pixelbin delivery URL.
     */
    private function find_url_deep( $data, int $depth = 0 ): string {
        if ( $depth > 8 ) {
            return '';
        }
        if ( is_string( $data ) && preg_match( '#^https?://.*pixelbin\.io/.+\.(png|jpg|jpeg|webp)#i', $data ) ) {
            return $data;
        }
        if ( is_array( $data ) ) {
            foreach ( $data as $val ) {
                $found = $this->find_url_deep( $val, $depth + 1 );
                if ( $found ) {
                    return $found;
                }
            }
        }
        return '';
    }

    /* ── Caricature Generation ──────────────────── */

    /**
     * Start caricature generation via the Predictions API.
     *
     * Methods (from pixelbin.io/api):
     *   img      → img/edit   — AI Image Editor, prompt-driven transformation.
     *   portrait → portrait/generate — automatic portrait generation.
     *   vg       → CDN URL fallback  — design variation via CDN URL (basic only).
     *
     * @return array|WP_Error  Contains 'request_id' + 'status' on success.
     */
    public function generate_caricature( string $original_cdn_url, string $pixelbin_path, string $prompt ) {
        $image_url = $original_cdn_url;
        if ( empty( $image_url ) || strpos( $image_url, 'cdn.pixelbin.io' ) === false ) {
            $custom = MouseMorph::get_option( 'custom_domain' );
            if ( empty( $custom ) || empty( $image_url ) || strpos( $image_url, $custom ) === false ) {
                $image_url = $this->build_cdn_url( $pixelbin_path, 'original' );
            }
        }

        $method = MouseMorph::get_option( 'transform_method', 'portrait' );

        switch ( $method ) {
            case 'img':
                $inputs = [
                    'prompt' => $prompt,
                    'images' => $image_url,
                ];
                $result = $this->create_prediction( 'img', 'edit', $inputs );
                break;

            case 'vg':
                return $this->generate_caricature_cdn( $image_url, $pixelbin_path, $prompt );

            case 'portrait':
            default:
                $inputs = [ 'image' => $image_url ];
                $result = $this->create_prediction( 'portrait', 'generate', $inputs );
                break;
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $request_id = $result['_id'] ?? '';
        if ( empty( $request_id ) ) {
            return new WP_Error( 'no_request_id', __( 'PixelBin did not return a prediction ID.', 'mousemorph' ) );
        }

        $status = $result['status'] ?? 'PENDING';

        $output_url = '';
        if ( $status === 'SUCCESS' ) {
            $output_url = $this->extract_prediction_url( $result );
        }

        return [
            'request_id' => $request_id,
            'status'     => $status === 'SUCCESS' ? 'ready' : 'processing',
            'url'        => $output_url,
        ];
    }

    /**
     * CDN URL fallback for vg.generate (Design Variation Generator).
     * Kept as fallback; produces subtle variations only.
     */
    private function generate_caricature_cdn( string $base_url, string $pixelbin_path, string $prompt ) {
        $encoded = rtrim( base64_encode( $prompt ), '=' );
        $pattern = 'vg.generate(p:' . $encoded . ',v:1,s:0,auto:true)';

        $url = $this->transform_url( $base_url, $pattern );
        if ( ! $url ) {
            $url = $this->build_cdn_url( $pixelbin_path, $pattern );
        }

        $response = wp_remote_get( $url, [
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => [ 'Accept' => 'image/*,*/*' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'request_id' => '', 'url' => $url, 'status' => 'processing', 'poll_mode' => 'cdn' ];
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( $status === 200 ) {
            return [ 'request_id' => '', 'url' => $url, 'status' => 'ready', 'poll_mode' => 'cdn' ];
        }
        if ( $status === 202 ) {
            return [ 'request_id' => '', 'url' => $url, 'status' => 'processing', 'poll_mode' => 'cdn' ];
        }

        $error_map = [
            403 => [ 'generation_forbidden', __( 'Access denied. Check that the transformation is available on your PixelBin plan.', 'mousemorph' ) ],
            404 => [ 'transform_not_found', __( 'Transformation not found. Verify it is enabled in your PixelBin Playground.', 'mousemorph' ) ],
            400 => [ 'bad_request', __( 'PixelBin could not process this request. Try a different photo or simpler description.', 'mousemorph' ) ],
            422 => [ 'bad_request', __( 'PixelBin could not process this request. Try a different photo or simpler description.', 'mousemorph' ) ],
            500 => [ 'server_error', __( 'PixelBin server error. Please try again later.', 'mousemorph' ) ],
        ];

        if ( isset( $error_map[ $status ] ) ) {
            return new WP_Error( $error_map[ $status ][0], $error_map[ $status ][1] );
        }

        return [ 'request_id' => '', 'url' => $url, 'status' => 'processing', 'poll_mode' => 'cdn' ];
    }

    /* ── Prediction Status Check (polling) ──────── */

    /**
     * Poll a prediction by request_id and return normalized status.
     *
     * @return array  [ 'status' => 'ready'|'processing'|'error', 'url' => string ]
     */
    public function check_prediction_status( string $request_id ): array {
        $result = $this->get_prediction( $request_id );

        if ( is_wp_error( $result ) ) {
            return [ 'status' => 'error', 'url' => '', 'error' => $result->get_error_message() ];
        }

        $status = $result['status'] ?? '';

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MouseMorph] Prediction poll: id=' . $request_id . ' status=' . $status );
            error_log( '[MouseMorph] Prediction response keys: ' . implode( ', ', array_keys( $result ) ) );
            if ( isset( $result['output'] ) ) {
                error_log( '[MouseMorph] Prediction output: ' . wp_json_encode( $result['output'] ) );
            }
        }

        if ( $status === 'SUCCESS' ) {
            $url = $this->extract_prediction_url( $result );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[MouseMorph] Extracted URL: ' . ( $url ?: '(empty)' ) );
            }
            return [ 'status' => 'ready', 'url' => $url ];
        }
        if ( $status === 'FAILURE' ) {
            $msg = $result['error'] ?? $result['message'] ?? __( 'Generation failed.', 'mousemorph' );
            return [ 'status' => 'error', 'url' => '', 'error' => $msg ];
        }

        return [ 'status' => 'processing', 'url' => '' ];
    }

    /* ── CDN URL Status Check (legacy/vg polling) ── */

    public function check_url_status( string $url ): string {
        if ( strpos( $url, 'cdn.pixelbin.io' ) === false ) {
            $custom = MouseMorph::get_option( 'custom_domain' );
            if ( empty( $custom ) || strpos( $url, $custom ) === false ) {
                return 'error';
            }
        }

        $response = wp_remote_head( $url, [ 'timeout' => 8, 'sslverify' => true ] );
        if ( is_wp_error( $response ) ) {
            return 'processing';
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status === 200 ) {
            return 'ready';
        }
        if ( $status === 202 ) {
            return 'processing';
        }
        return 'error';
    }

    /* ── NSFW Detection ─────────────────────────── */

    public function check_nsfw( string $pixelbin_path ) {
        $url      = $this->build_cdn_url( $pixelbin_path, 'nsfw.detect()' );
        $response = wp_remote_get( $url, [
            'timeout' => 30,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return true; // fail-open for UX
        }

        $ctx = wp_remote_retrieve_header( $response, 'x-pixb-context' );
        if ( $ctx ) {
            $data = json_decode( $ctx, true );
            if ( isset( $data['nsfw'] ) && $data['nsfw'] === true ) {
                return new WP_Error( 'nsfw_detected', __( 'The uploaded image contains inappropriate content. Please upload a different photo.', 'mousemorph' ) );
            }
        }
        return true;
    }

    /* ── Connection Test ────────────────────────── */

    public function test_connection() {
        $cloud = MouseMorph::get_option( 'cloud_name' );
        $token = MouseMorph::get_option( 'api_token' );

        if ( empty( $cloud ) ) {
            return new WP_Error( 'no_cloud', __( 'Cloud Name is not configured.', 'mousemorph' ) );
        }
        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', __( 'API Token is not configured.', 'mousemorph' ) );
        }

        $cdn_url = self::CDN_BASE . '/v2/' . rawurlencode( $cloud ) . '/original/__connection_test';
        $cdn_res = wp_remote_head( $cdn_url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $cdn_res ) ) {
            return new WP_Error( 'cdn_unreachable', __( 'Could not reach PixelBin CDN.', 'mousemorph' ) );
        }
        if ( wp_remote_retrieve_response_code( $cdn_res ) === 403 ) {
            return new WP_Error( 'invalid_cloud', __( 'Invalid Cloud Name. Check Settings → Details in your PixelBin dashboard.', 'mousemorph' ) );
        }

        $result = $this->request( '/service/platform/assets/v1.0/listFiles', 'GET', [ 'pageSize' => '1' ] );
        if ( is_wp_error( $result ) ) {
            $msg = $result->get_error_message();
            if ( stripos( $msg, 'invalid' ) !== false || stripos( $msg, 'signature' ) !== false || stripos( $msg, 'authorization' ) !== false ) {
                return new WP_Error( 'invalid_token', __( 'API Token is invalid. Generate a new one at Settings → API Tokens in PixelBin.', 'mousemorph' ) );
            }
            return $result;
        }
        return true;
    }

    /* ── AJAX ───────────────────────────────────── */

    public function ajax_test_connection(): void {
        check_ajax_referer( 'mmorph_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'mousemorph' ) );
        }
        $result = $this->test_connection();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        wp_send_json_success( __( 'Connected to PixelBin successfully! Cloud Name and API Token are valid.', 'mousemorph' ) );
    }
}
