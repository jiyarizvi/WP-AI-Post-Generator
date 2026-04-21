<?php
/**
 * Plugin Name: CG AI Post Generator
 * Description: Generate AI-powered posts (with tone, bulk, categories, tags, and featured images) for any post type.
 * Version: 1.1.0
 * Author: Coin Gazette
 * Text Domain: cg-ai-post-generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CG_AI_Post_Generator {

    const OPTION_API_KEY   = 'cg_ai_post_generator_api_key';
    const OPTION_API_URL   = 'cg_ai_post_generator_api_url';
    const NONCE_ACTION     = 'cg_ai_post_generator_nonce_action';
    const NONCE_NAME       = 'cg_ai_post_generator_nonce';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_cg_ai_generate_post', [ $this, 'handle_generate_post' ] );
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php',
            __( 'AI Post Generator', 'cg-ai-post-generator' ),
            __( 'AI Post Generator', 'cg-ai-post-generator' ),
            'edit_posts',
            'cg-ai-post-generator',
            [ $this, 'render_admin_page' ]
        );
    }

    public function register_settings() {
        register_setting(
            'cg_ai_post_generator_settings',
            self::OPTION_API_KEY,
            [ 'sanitize_callback' => 'sanitize_text_field' ]
        );

        register_setting(
            'cg_ai_post_generator_settings',
            self::OPTION_API_URL,
            [ 'sanitize_callback' => 'esc_url_raw' ]
        );

        add_settings_section(
            'cg_ai_post_generator_main_section',
            __( 'AI API Settings', 'cg-ai-post-generator' ),
            function () {
                echo '<p>' . esc_html__( 'Use your OpenAI API key. Chat endpoint is used for content; Images endpoint is used internally for featured images.', 'cg-ai-post-generator' ) . '</p>';
            },
            'cg_ai_post_generator_settings_page'
        );

        add_settings_field(
            self::OPTION_API_URL,
            __( 'Chat API Endpoint URL', 'cg-ai-post-generator' ),
            [ $this, 'render_field_api_url' ],
            'cg_ai_post_generator_settings_page',
            'cg_ai_post_generator_main_section'
        );

        add_settings_field(
            self::OPTION_API_KEY,
            __( 'OpenAI API Key', 'cg-ai-post-generator' ),
            [ $this, 'render_field_api_key' ],
            'cg_ai_post_generator_settings_page',
            'cg_ai_post_generator_main_section'
        );
    }

    public function render_field_api_url() {
        $value = esc_url( get_option( self::OPTION_API_URL, 'https://api.openai.com/v1/chat/completions' ) );
        echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_API_URL ) . '" value="' . esc_attr( $value ) . '" />';
    }

    public function render_field_api_key() {
        $value = esc_attr( get_option( self::OPTION_API_KEY, '' ) );
        echo '<input type="password" class="regular-text" name="' . esc_attr( self::OPTION_API_KEY ) . '" value="' . $value . '" />';
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'posts_page_cg-ai-post-generator' ) {
            return;
        }

        wp_enqueue_style(
            'cg-ai-post-generator-admin',
            plugin_dir_url( __FILE__ ) . 'assets/admin.css',
            [],
            '1.1.0'
        );

        wp_enqueue_script(
            'cg-ai-post-generator-admin',
            plugin_dir_url( __FILE__ ) . 'assets/admin.js',
            [ 'jquery' ],
            '1.1.0',
            true
        );

        wp_localize_script(
            'cg-ai-post-generator-admin',
            'CG_Ai_Post_Generator',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
            ]
        );
    }

    public function render_admin_page() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        ?>
        <div class="wrap cg-ai-post-generator-wrap">
            <h1><?php esc_html_e( 'AI Post Generator', 'cg-ai-post-generator' ); ?></h1>

            <div class="cg-ai-post-generator-layout">
                <div class="cg-ai-post-generator-left">
                    <h2><?php esc_html_e( 'Generate New Content', 'cg-ai-post-generator' ); ?></h2>

                    <form id="cg-ai-post-generator-form">
                        <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="cg_ai_title"><?php esc_html_e( 'Post Title', 'cg-ai-post-generator' ); ?></label></th>
                                <td><input type="text" id="cg_ai_title" name="title" class="regular-text" required /></td>
                            </tr>

                            <tr>
                                <th><label for="cg_ai_instructions"><?php esc_html_e( 'Brief / Instructions', 'cg-ai-post-generator' ); ?></label></th>
                                <td>
                                    <textarea id="cg_ai_instructions" name="instructions" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Describe topic, angle, audience, key points, etc.', 'cg-ai-post-generator' ); ?>"></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="cg_ai_tone"><?php esc_html_e( 'Tone', 'cg-ai-post-generator' ); ?></label></th>
                                <td>
                                    <select id="cg_ai_tone" name="tone">
                                        <option value="news"><?php esc_html_e( 'News / Neutral', 'cg-ai-post-generator' ); ?></option>
                                        <option value="formal"><?php esc_html_e( 'Formal / Analytical', 'cg-ai-post-generator' ); ?></option>
                                        <option value="crypto_native"><?php esc_html_e( 'Crypto-native / DeFi-savvy', 'cg-ai-post-generator' ); ?></option>
                                        <option value="beginner"><?php esc_html_e( 'Beginner-friendly / Educational', 'cg-ai-post-generator' ); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="cg_ai_post_type"><?php esc_html_e( 'Post Type', 'cg-ai-post-generator' ); ?></label></th>
                                <td>
                                    <select id="cg_ai_post_type" name="post_type">
                                        <?php foreach ( $post_types as $type ) : ?>
                                            <option value="<?php echo esc_attr( $type->name ); ?>">
                                                <?php echo esc_html( $type->labels->singular_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="cg_ai_count"><?php esc_html_e( 'Number of Posts', 'cg-ai-post-generator' ); ?></label></th>
                                <td>
                                    <input type="number" id="cg_ai_count" name="count" min="1" max="10" value="1" />
                                    <p class="description"><?php esc_html_e( 'Generate multiple drafts in one go (up to 10).', 'cg-ai-post-generator' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e( 'Extras', 'cg-ai-post-generator' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="generate_featured_image" value="1" checked />
                                        <?php esc_html_e( 'Generate featured image (OpenAI Images API)', 'cg-ai-post-generator' ); ?>
                                    </label><br />
                                    <label>
                                        <input type="checkbox" name="auto_taxonomy" value="1" checked />
                                        <?php esc_html_e( 'Auto-detect categories & tags', 'cg-ai-post-generator' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" class="button button-primary" id="cg_ai_generate_btn">
                                <?php esc_html_e( 'Generate Draft(s)', 'cg-ai-post-generator' ); ?>
                            </button>
                            <span class="cg-ai-status"></span>
                        </p>
                    </form>

                    <div id="cg_ai_preview" class="cg-ai-preview" style="display:none;">
                        <h2><?php esc_html_e( 'Generated Preview (first draft)', 'cg-ai-post-generator' ); ?></h2>
                        <div class="cg-ai-preview-content"></div>
                    </div>
                </div>

                <div class="cg-ai-post-generator-right">
                    <h2><?php esc_html_e( 'AI Settings', 'cg-ai-post-generator' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'cg_ai_post_generator_settings' );
                        do_settings_sections( 'cg_ai_post_generator_settings_page' );
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_generate_post() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'cg-ai-post-generator' ) ], 403 );
        }

        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'cg-ai-post-generator' ) ], 400 );
        }

        $title        = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $instructions = wp_kses_post( wp_unslash( $_POST['instructions'] ?? '' ) );
        $post_type    = sanitize_key( wp_unslash( $_POST['post_type'] ?? 'post' ) );
        $tone         = sanitize_key( wp_unslash( $_POST['tone'] ?? 'news' ) );
        $count        = (int) ( $_POST['count'] ?? 1 );
        $count        = max( 1, min( 10, $count ) );

        $generate_featured_image = ! empty( $_POST['generate_featured_image'] );
        $auto_taxonomy           = ! empty( $_POST['auto_taxonomy'] );

        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => __( 'Title is required.', 'cg-ai-post-generator' ) ], 400 );
        }

        $api_url = get_option( self::OPTION_API_URL );
        $api_key = get_option( self::OPTION_API_KEY );

        if ( empty( $api_url ) || empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( 'AI API settings are missing.', 'cg-ai-post-generator' ) ], 400 );
        }

        $results = [];
        for ( $i = 0; $i < $count; $i++ ) {
            $single_title = $count > 1 ? $title . ' #' . ( $i + 1 ) : $title;

            $result = $this->generate_single_post(
                $single_title,
                $instructions,
                $post_type,
                $tone,
                $api_url,
                $api_key,
                $generate_featured_image,
                $auto_taxonomy
            );

            if ( is_wp_error( $result ) ) {
                if ( $i === 0 ) {
                    wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
                } else {
                    $results[] = [
                        'error'   => true,
                        'message' => $result->get_error_message(),
                    ];
                    continue;
                }
            } else {
                $results[] = $result;
            }
        }

        $first = $results[0] ?? null;

        wp_send_json_success(
            [
                'message'   => sprintf(
                    __( 'Created %d draft(s) successfully.', 'cg-ai-post-generator' ),
                    count( $results )
                ),
                'created'   => $results,
                'post_id'   => $first['post_id'] ?? 0,
                'edit_link' => $first['edit_link'] ?? '',
                'content'   => $first['content'] ?? '',
            ]
        );
    }

    protected function generate_single_post( $title, $instructions, $post_type, $tone, $api_url, $api_key, $generate_featured_image, $auto_taxonomy ) {
        $tone_description = $this->map_tone_to_description( $tone );

        $user_prompt = "You are writing for a crypto and finance publication.\n\n"
            . "Title: {$title}\n\n"
            . "Tone: {$tone_description}\n\n"
            . "Instructions / Brief:\n{$instructions}\n\n"
            . "Write a complete, well-structured article.\n"
            . "- Use H2/H3 headings.\n"
            . "- Short paragraphs.\n"
            . "- Strong, direct intro.\n\n"
            . "Return ONLY a valid JSON object with this exact shape:\n"
            . "{\n"
            . "  \"content\": \"<full HTML or Markdown article>\",\n"
            . "  \"categories\": [\"Category 1\", \"Category 2\"],\n"
            . "  \"tags\": [\"tag1\", \"tag2\", \"tag3\"]\n"
            . "}\n";

        $body = [
            'model'       => 'gpt-4.1-mini',
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are a professional content writer for a crypto and finance publication. Always respond with valid JSON only, no extra text.',
                ],
                [
                    'role'    => 'user',
                    'content' => $user_prompt,
                ],
            ],
            'temperature' => 0.7,
        ];

        $response = wp_remote_post(
            $api_url,
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => 90,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $data ) ) {
            return new WP_Error( 'cg_ai_api_error', __( 'AI API error or empty response.', 'cg-ai-post-generator' ) );
        }

        $raw_content = $data['choices'][0]['message']['content'] ?? '';
        $raw_content = trim( (string) $raw_content );

        if ( empty( $raw_content ) ) {
            return new WP_Error( 'cg_ai_empty', __( 'AI returned empty content.', 'cg-ai-post-generator' ) );
        }

        $parsed = json_decode( $raw_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $parsed ) ) {
            $parsed = [
                'content'    => $raw_content,
                'categories' => [],
                'tags'       => [],
            ];
        }

        $article_content = (string) ( $parsed['content'] ?? $raw_content );
        $categories      = is_array( $parsed['categories'] ?? null ) ? $parsed['categories'] : [];
        $tags            = is_array( $parsed['tags'] ?? null ) ? $parsed['tags'] : [];

        $postarr = [
            'post_title'   => $title,
            'post_content' => wp_kses_post( $article_content ),
            'post_status'  => 'draft',
            'post_type'    => $post_type,
        ];

        $cat_ids = [];
        if ( $auto_taxonomy && ! empty( $categories ) ) {
            foreach ( $categories as $cat_name ) {
                $cat_name = trim( wp_strip_all_tags( $cat_name ) );
                if ( $cat_name === '' ) {
                    continue;
                }

                $existing = get_term_by( 'name', $cat_name, 'category' );
                if ( ! $existing ) {
                    $new = wp_insert_term( $cat_name, 'category' );
                    if ( ! is_wp_error( $new ) && ! empty( $new['term_id'] ) ) {
                        $cat_ids[] = (int) $new['term_id'];
                    }
                } else {
                    $cat_ids[] = (int) $existing->term_id;
                }
            }

            if ( ! empty( $cat_ids ) && ( post_type_supports( $post_type, 'category' ) || $post_type === 'post' ) ) {
                $postarr['post_category'] = $cat_ids;
            }
        }

        $post_id = wp_insert_post( $postarr, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        if ( $auto_taxonomy && ! empty( $tags ) ) {
            $clean_tags = [];
            foreach ( $tags as $tag ) {
                $tag = trim( wp_strip_all_tags( $tag ) );
                if ( $tag !== '' ) {
                    $clean_tags[] = $tag;
                }
            }
            if ( ! empty( $clean_tags ) ) {
                wp_set_post_tags( $post_id, $clean_tags, false );
            }
        }

        if ( $generate_featured_image ) {
            $this->maybe_generate_featured_image( $post_id, $title, $instructions, $tone, $api_key );
        }

        return [
            'post_id'    => $post_id,
            'edit_link'  => get_edit_post_link( $post_id, 'raw' ),
            'content'    => wpautop( $article_content ),
            'categories' => $categories,
            'tags'       => $tags,
        ];
    }

    protected function map_tone_to_description( $tone ) {
        switch ( $tone ) {
            case 'formal':
                return 'Formal, analytical, institutional tone, suitable for professional investors and analysts.';
            case 'crypto_native':
                return 'Crypto-native, DeFi-savvy, assumes reader understands on-chain concepts and jargon.';
            case 'beginner':
                return 'Beginner-friendly, educational, explains concepts clearly with minimal jargon.';
            case 'news':
            default:
                return 'Neutral, news-style, concise and factual with light context.';
        }
    }

    protected function maybe_generate_featured_image( $post_id, $title, $instructions, $tone, $api_key ) {
        if ( empty( $api_key ) ) {
            return;
        }

        $prompt = sprintf(
            'High-quality editorial illustration for a crypto/finance article. Title: "%s". Tone: %s. Style: clean, modern, suitable for Coin Gazette.',
            $title,
            $this->map_tone_to_description( $tone )
        );

        $body = [
            'model'  => 'gpt-image-1',
            'prompt' => $prompt,
            'size'   => '1024x1024',
        ];

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => 90,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $data['data'][0]['url'] ) ) {
            return;
        }

        $image_url = esc_url_raw( $data['data'][0]['url'] );

        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url( $image_url );
        if ( is_wp_error( $tmp ) ) {
            return;
        }

        $file_array = [
            'name'     => sanitize_file_name( $title ) . '-featured.jpg',
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return;
        }

        set_post_thumbnail( $post_id, $attachment_id );
    }
}

new CG_AI_Post_Generator();
