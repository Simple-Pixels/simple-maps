<?php
/*
Plugin Name: Simple Maps
Description: Simple Google Maps for a CPT with an ACF location field.
Version: 1.1.0
Author: Simple Pixels
Text Domain: sp-map
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SP_Map_Simple_Maps {
    const OPTION_KEY = 'sp_map_options';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
        add_action( 'acf/init', [ $this, 'inject_acf_api_key' ] );
        add_action( 'save_post', [ $this, 'maybe_invalidate_cache_on_save' ], 10, 2 );
        add_action( 'update_option_' . self::OPTION_KEY, [ $this, 'invalidate_cache_on_settings_change' ], 10, 3 );
        add_shortcode( 'sp_map', [ $this, 'shortcode' ] );
        add_shortcode( 'simple_map', [ $this, 'shortcode' ] );
    }

    public static function get_options() {
        $defaults = [
            'api_key'        => '',
            'cpt'            => 'impact_pin',
            'location_field' => 'location',
            'show_image'     => 1,
            'show_heading'   => 1,
            'show_content'   => 1,
            'show_readmore'  => 1,
            'pin_color'      => '#92ACA0',
        ];

        $opts = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $opts, $defaults );
    }

    public function inject_acf_api_key() {
        if ( ! function_exists('acf_update_setting') ) {
            return;
        }

        $opts = self::get_options();

        if ( ! empty( $opts['api_key'] ) ) {
            acf_update_setting( 'google_api_key', $opts['api_key'] );
        }
    }

    public function register_settings() {
        register_setting(
            'sp_map_options_group',
            self::OPTION_KEY,
            [ $this, 'sanitize_options' ]
        );
    }

    public function sanitize_options( $input ) {
        $output = [];

        $output['api_key']        = sanitize_text_field( $input['api_key'] ?? '' );
        $output['cpt']            = sanitize_key( $input['cpt'] ?? '' );
        $output['location_field'] = sanitize_key( $input['location_field'] ?? '' );

        $output['show_image']    = ! empty( $input['show_image'] ) ? 1 : 0;
        $output['show_heading']  = ! empty( $input['show_heading'] ) ? 1 : 0;
        $output['show_content']  = ! empty( $input['show_content'] ) ? 1 : 0;
        $output['show_readmore'] = ! empty( $input['show_readmore'] ) ? 1 : 0;

        $pin_color = $input['pin_color'] ?? '';
        $output['pin_color'] = preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $pin_color )
            ? $pin_color
            : '#92ACA0';

        return $output;
    }

    public function add_settings_page() {
        add_options_page(
            __( 'Simple Maps', 'sp-map' ),
            __( 'Simple Maps', 'sp-map' ),
            'manage_options',
            'sp-map',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'settings_page_sp-map' ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script(
            'sp-map-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/sp-map-admin.js',
            [ 'wp-color-picker', 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $opts = self::get_options();
        ?>
        <div class="wrap sp-map-admin">
            <h1><?php esc_html_e( 'Simple Maps', 'sp-map' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'sp_map_options_group' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="sp_map_api_key"><?php esc_html_e( 'Google Maps API Key', 'sp-map' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sp_map_api_key"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
                                   value="<?php echo esc_attr( $opts['api_key'] ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sp_map_cpt"><?php esc_html_e( 'CPT Post Type Slug', 'sp-map' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sp_map_cpt"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cpt]"
                                   value="<?php echo esc_attr( $opts['cpt'] ); ?>"
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Example: impact_pin', 'sp-map' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sp_map_location_field"><?php esc_html_e( 'ACF Location Field Name', 'sp-map' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sp_map_location_field"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[location_field]"
                                   value="<?php echo esc_attr( $opts['location_field'] ); ?>"
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e( 'ACF Google Map field name (e.g. "location").', 'sp-map' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Elements', 'sp-map' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_image]"
                                       value="1" <?php checked( $opts['show_image'] ); ?> />
                                <?php esc_html_e( 'Featured Image', 'sp-map' ); ?>
                            </label><br />

                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_heading]"
                                       value="1" <?php checked( $opts['show_heading'] ); ?> />
                                <?php esc_html_e( 'Heading', 'sp-map' ); ?>
                            </label><br />

                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_content]"
                                       value="1" <?php checked( $opts['show_content'] ); ?> />
                                <?php esc_html_e( 'Content', 'sp-map' ); ?>
                            </label><br />

                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_readmore]"
                                       value="1" <?php checked( $opts['show_readmore'] ); ?> />
                                <?php esc_html_e( '"Read More" Button', 'sp-map' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sp_map_pin_color"><?php esc_html_e( 'Pin Colour', 'sp-map' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sp_map_pin_color"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pin_color]"
                                   value="<?php echo esc_attr( $opts['pin_color'] ); ?>"
                                   class="sp-map-color-field"
                                   data-default-color="#92ACA0" />
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e( 'Reqs', 'sp-map' ); ?></h2>
            <p><?php esc_html_e( 'Advanced Custom Fields Plugin + PHP > 8.0', 'sp-map' ); ?></p>

            <h2><?php esc_html_e( 'Usage', 'sp-map' ); ?></h2>
            <p><?php esc_html_e( '1. Add the domain to the Google Maps API Key Whitelist', 'sp-map' ); ?></p>
            <p><?php esc_html_e( '2. Add your API key above, and save (to set the ACF API)', 'sp-map' ); ?></p>
            <p><?php esc_html_e( '3. Create a Custom Post Type (or, use Posts/Pages)', 'sp-map' ); ?></p>
            <p><?php esc_html_e( '4. Create a Google Maps ACF Field. Attach it to your Custom Post Type from Step 4', 'sp-map' ); ?></p>
            <p><?php esc_html_e( '5. Add the post type slug, and the ACF field slug above', 'sp-map' ); ?></p>
            <p><?php esc_html_e( '6. Add the shortcode [simple_map] where you want the map to appear.', 'sp-map' ); ?></p>
        </div>
        <?php
    }

    public function enqueue_frontend() {
        if ( is_admin() ) {
            return;
        }

        $opts = self::get_options();

        if ( empty( $opts['api_key'] ) ) {
            return;
        }

        wp_register_script(
            'sp-map',
            plugin_dir_url( __FILE__ ) . 'assets/js/sp-map.js',
            [],
            '1.0.0',
            true
        );
        wp_enqueue_script( 'sp-map' );

        $google_src = add_query_arg(
            [
                'key'      => $opts['api_key'],
                'libraries'=> '',
                'callback' => '__initSpMaps',
            ],
            'https://maps.googleapis.com/maps/api/js'
        );

        wp_register_script( 'sp-map-google-maps', $google_src, [], null, true );
        wp_enqueue_script( 'sp-map-google-maps' );
    }


    public function shortcode( $atts ) {
        if ( ! function_exists( 'get_field' ) ) {
            return '<div class="sp-map-empty">ACF plugin is required for Simple Maps.</div>';
        }

        $opts = self::get_options();

        $atts = shortcode_atts(
            [
                'height' => '420px',
                'class'  => '',
            ],
            $atts,
            'sp_map'
        );

        $locations = $this->get_locations();

        if ( empty( $locations ) ) {
            return '<div class="sp-map-empty">No locations found.</div>';
        }

        $map_id = 'sp-map-' . wp_generate_uuid4();

        wp_enqueue_script( 'sp-map-google-maps' );
        wp_enqueue_script( 'sp-map' );

        $config = [
            'showImage'    => (bool) $opts['show_image'],
            'showHeading'  => (bool) $opts['show_heading'],
            'showContent'  => (bool) $opts['show_content'],
            'showReadMore' => (bool) $opts['show_readmore'],
            'pinColor'     => $opts['pin_color'],
        ];

        wp_add_inline_script(
            'sp-map',
            'window.__SP_MAPS__ = window.__SP_MAPS__ || []; window.__SP_MAPS__.push(' .
            wp_json_encode(
                [
                    'mapId'     => $map_id,
                    'locations' => $locations,
                    'config'    => $config,
                ]
            ) .
            ');',
            'before'
        );

        ob_start();
        ?>
        <div class="sp-map-wrapper <?php echo esc_attr( $atts['class'] ); ?>">
            <div id="<?php echo esc_attr( $map_id ); ?>"
                 class="sp-map-canvas"
                 style="width:100%;height:<?php echo esc_attr( $atts['height'] ); ?>"></div>
        </div>
        <?php
        return ob_get_clean();
    }


    protected function get_locations_cache_key() {
        $opts = self::get_options();
        $key  = sprintf(
            'sp_map_locations_%s',
            md5( $opts['cpt'] . '|' . $opts['location_field'] )
        );
        return $key;
    }


    protected function delete_locations_cache() {
        delete_transient( $this->get_locations_cache_key() );
    }


    protected function get_locations() {
        $cache_key = $this->get_locations_cache_key();
        $cached    = get_transient( $cache_key );

        if ( $cached !== false && is_array( $cached ) ) {
            return $cached;
        }

        $opts = self::get_options();

        $posts = get_posts(
            [
                'post_type'      => $opts['cpt'],
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => $opts['location_field'],
                        'compare' => 'EXISTS',
                    ],
                ],
                'fields'         => 'ids',
            ]
        );

        $locations = [];

        if ( $posts ) {
            foreach ( $posts as $post_id ) {
                $loc = get_field( $opts['location_field'], $post_id );

                if ( ! is_array( $loc ) || ! isset( $loc['lat'], $loc['lng'] ) ) {
                    continue;
                }

                $thumb   = get_the_post_thumbnail_url( $post_id, 'large' );
                $content = get_post_field( 'post_content', $post_id );
                $content = wp_strip_all_tags( $content );
                $content = wp_trim_words( $content, 30, '…' );

                $locations[] = [
                    'title'   => get_the_title( $post_id ),
                    'lat'     => (float) $loc['lat'],
                    'lng'     => (float) $loc['lng'],
                    'url'     => get_permalink( $post_id ),
                    'thumb'   => $thumb ?: '',
                    'content' => $content ?: '',
                ];
            }
        }

   
        set_transient( $cache_key, $locations, 2 * HOUR_IN_SECONDS );

        return $locations;
    }

    public function maybe_invalidate_cache_on_save( $post_id, $post ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $opts = self::get_options();

        if ( $post->post_type !== $opts['cpt'] ) {
            return;
        }

        $this->delete_locations_cache();
    }


    public function invalidate_cache_on_settings_change( $old_value, $value, $option ) {
        $this->delete_locations_cache();
    }
}

new SP_Map_Simple_Maps();
