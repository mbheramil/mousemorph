<?php
/**
 * GitHub-based plugin self-updater.
 *
 * Checks a GitHub repository's releases for new versions and hooks into
 * WordPress's native update system so the plugin can be updated with one
 * click from the Plugins page — just like a wordpress.org plugin.
 *
 * @package MouseMorph
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GitHub_Updater {

    private const CACHE_KEY = 'mmorph_gh_release';
    private const CACHE_TTL = 2 * HOUR_IN_SECONDS;

    private string $file;
    private string $plugin_slug;
    private string $plugin_dir;
    private string $repo;
    private string $version;
    private ?array $github_data = null;

    /**
     * @param string $file    Full path to the main plugin file (__FILE__).
     * @param string $repo    GitHub repo in "owner/repo" format.
     * @param string $version Current installed version.
     */
    public function __construct( string $file, string $repo, string $version ) {
        $this->file        = $file;
        $this->plugin_slug = plugin_basename( $file );
        $this->plugin_dir  = dirname( $this->plugin_slug );
        $this->repo        = $repo;
        $this->version     = $version;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
        add_filter( 'plugin_action_links_' . $this->plugin_slug, [ $this, 'add_check_link' ] );
        add_action( 'admin_init', [ $this, 'handle_force_check' ] );
    }

    /**
     * Fetch the latest release data from GitHub (cached).
     */
    private function get_github_release( bool $force = false ): array {
        if ( ! $force && null !== $this->github_data ) {
            return $this->github_data;
        }

        if ( ! $force ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached && is_array( $cached ) ) {
                $this->github_data = $cached;
                return $this->github_data;
            }
        }

        $url      = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'MouseMorph/' . $this->version,
            ],
        ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->github_data = [];
            return $this->github_data;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            $data = [];
        }

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        $this->github_data = $data;

        return $this->github_data;
    }

    private function remote_version(): string {
        $release = $this->get_github_release();
        $tag     = $release['tag_name'] ?? '';
        return ltrim( $tag, 'vV' );
    }

    private function download_url(): string {
        $release = $this->get_github_release();
        return $release['zipball_url'] ?? '';
    }

    /**
     * Hook: tell WordPress a new version is available.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        delete_transient( self::CACHE_KEY );
        $this->github_data = null;

        $remote_ver = $this->remote_version();
        $download   = $this->download_url();

        if ( empty( $remote_ver ) || empty( $download ) ) {
            return $transient;
        }

        if ( version_compare( $remote_ver, $this->version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'slug'        => $this->plugin_dir,
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote_ver,
                'url'         => 'https://github.com/' . $this->repo,
                'package'     => $download,
            ];
        }

        return $transient;
    }

    /**
     * Hook: provide plugin info for the "View details" popup.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ( $args->slug ?? '' ) !== $this->plugin_dir ) {
            return $result;
        }

        $release = $this->get_github_release();
        if ( empty( $release ) ) {
            return $result;
        }

        $info                = new stdClass();
        $info->name          = 'MouseMorph — AI Caricature Maker for WooCommerce';
        $info->slug          = $this->plugin_dir;
        $info->version       = $this->remote_version();
        $info->author        = '<a href="https://pixelbin.io">PixelBin</a>';
        $info->homepage      = 'https://github.com/' . $this->repo;
        $info->requires      = '5.8';
        $info->tested        = '6.7';
        $info->requires_php  = '7.4';
        $info->download_link = $this->download_url();
        $info->sections      = [
            'description' => 'Turn your customers into fun mouse caricatures powered by PixelBin AI.',
            'changelog'   => nl2br( esc_html( $release['body'] ?? '' ) ),
        ];

        return $info;
    }

    /**
     * Hook: after the zip is extracted, rename the folder to match the expected
     * plugin directory name (GitHub zips use "owner-repo-hash" as folder name).
     */
    public function post_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $result;
        }

        global $wp_filesystem;

        $proper_dest = WP_PLUGIN_DIR . '/' . $this->plugin_dir;
        $wp_filesystem->move( $result['destination'], $proper_dest );
        $result['destination'] = $proper_dest;

        activate_plugin( $this->plugin_slug );

        return $result;
    }

    /**
     * Add a "Check for updates" link on the Plugins page.
     */
    public function add_check_link( array $links ): array {
        $url  = wp_nonce_url( admin_url( 'plugins.php?mmorph_force_check=1' ), 'mmorph_force_check' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'mousemorph' ) . '</a>';
        return $links;
    }

    /**
     * Handle the force-check action: clear cache and trigger WP update check.
     */
    public function handle_force_check(): void {
        if ( empty( $_GET['mmorph_force_check'] ) || ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        check_admin_referer( 'mmorph_force_check' );

        delete_transient( self::CACHE_KEY );
        $this->github_data = null;

        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }
}
