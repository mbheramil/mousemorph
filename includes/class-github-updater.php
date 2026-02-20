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

    private string $file;
    private string $plugin_slug;
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
        $this->repo        = $repo;
        $this->version     = $version;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
    }

    /**
     * Fetch the latest release data from GitHub (cached for 6 hours).
     */
    private function get_github_release(): array {
        if ( null !== $this->github_data ) {
            return $this->github_data;
        }

        $transient_key = 'mmorph_gh_release';
        $cached        = get_transient( $transient_key );

        if ( false !== $cached && is_array( $cached ) ) {
            $this->github_data = $cached;
            return $this->github_data;
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

        set_transient( $transient_key, $data, 6 * HOUR_IN_SECONDS );
        $this->github_data = $data;

        return $this->github_data;
    }

    /**
     * Extract the clean version number from a tag like "v1.2.3".
     */
    private function remote_version(): string {
        $release = $this->get_github_release();
        $tag     = $release['tag_name'] ?? '';
        return ltrim( $tag, 'vV' );
    }

    /**
     * Get the zipball URL for the latest release.
     */
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

        $remote_ver  = $this->remote_version();
        $download    = $this->download_url();

        if ( empty( $remote_ver ) || empty( $download ) ) {
            return $transient;
        }

        if ( version_compare( $remote_ver, $this->version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'slug'        => dirname( $this->plugin_slug ),
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

        if ( ( $args->slug ?? '' ) !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release = $this->get_github_release();
        if ( empty( $release ) ) {
            return $result;
        }

        $info               = new stdClass();
        $info->name         = 'MouseMorph — AI Caricature Maker for WooCommerce';
        $info->slug         = dirname( $this->plugin_slug );
        $info->version      = $this->remote_version();
        $info->author       = '<a href="https://pixelbin.io">PixelBin</a>';
        $info->homepage     = 'https://github.com/' . $this->repo;
        $info->requires     = '5.8';
        $info->tested       = '6.7';
        $info->requires_php = '7.4';
        $info->download_link = $this->download_url();
        $info->sections     = [
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

        $proper_dest = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );
        $wp_filesystem->move( $result['destination'], $proper_dest );
        $result['destination'] = $proper_dest;

        activate_plugin( $this->plugin_slug );

        return $result;
    }
}
