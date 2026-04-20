<?php
/**
 * Plugin Name: CG AI Post Generator
 * Description: Generate blog posts with AI and save them as drafts in any post type.
 * Version: 1.0.0
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
                echo '<p>' . esc_html__( 'Configure your AI provider endpoint and API key.', 'cg-ai-post-generator' ) . '</p>';
            },
            'cg_ai_post_generator_settings_page'
        );

        add_settings_field(
            self::OPTION_API_URL,
            __( 'API Endpoint URL', 'cg-ai-post-generator' ),
            [ $this, 'render_field_api_url' ],
            'cg_ai_post_generator_settings_page',
            'cg_ai_post_generator_main_section'
        );

        add_settings_field(
            self::OPTION_API_KEY,
            __( 'API Key', 'cg-ai-post-generator' ),
            [ $this, 'render_field_api_key' ],
            'cg_ai_post_generator_settings_page',
            'cg_ai_post_generator_main_section'
        );
    }

    public function render_field_api_url() {
        $value = esc_url( get_option( self::OPTION_API_URL, '' ) );
        echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_API_URL ) . '" value="' . esc_attr( $value ) . '" placeholder="https://api.your-ai-provider.com/v1/generate" />';
    }

    public function render_field_api_key() {
        $value = esc_attr( get_option( self::OPTION_API_KEY, '' ) );
        echo '<input type="password" class="regular-text" name="' . esc_attr( self::OPTION_API_KEY ) . '" value="' . $value . '" placeholder="sk-********" />';
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'posts_page_cg-ai-post-generator' ) {
            return;
        }

        wp_enqueue_style(
            'cg-ai-post-generator-admin',
            plugin_dir_url( __FILE__ ) . 'assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'cg-ai-post-generator-admin',
            plugin_dir_url( __FILE__ ) . 'assets/admin.js',
            [ 'jquery' ],
            '1.0.0',
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
                                <th scope="row">
                                    <label for="cg_ai_title"><?php esc_html_e( 'Post Title', 'cg-ai-post-generator' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="cg_ai_title" name="title" class="regular-text" required />
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="cg_ai_instructions"><?php esc_html_e( 'Brief / Instructions', 'cg-ai-post-generator' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="cg_ai_instructions" name="instructions" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Describe the topic, angle, tone, headings, etc.', 'cg-ai-post-generator' ); ?>"></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="cg_ai_post_type"><?php esc_html_e( 'Post Type', 'cg-ai-post-generator' ); ?></label>
                                </th>
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
                        </table>

                        <p>
                            <button type="submit" class="button button-primary" id="cg_ai_generate_btn">
                                <?php esc_html_e( 'Generate Draft', 'cg-ai-post-generator' ); ?>
                            </button>
                            <span class="cg-ai-status"></span>
                        </p>
                    </form>

                    <div id="cg_ai_preview" class="cg-ai-preview" style="display:none;">
                        <h2><?php esc_html_e( 'Generated Preview', 'cg-ai-post-generator' ); ?></h2>
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

        $title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $instructions = isset( $_POST['instructions'] ) ? wp_kses_post( wp_unslash( $_POST['instructions'] ) ) : '';
        $post_type    = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post';

        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => __( 'Title is required.', 'cg-ai-post-generator' ) ], 400 );
        }

        $api_url = get_option( self::OPTION_API_URL );
        $api_key = get_option( self::OPTION_API_KEY );

        if ( empty( $api_url ) || empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( 'AI API settings are missing.', 'cg-ai-post-generator' ) ], 400 );
        }

        // Build prompt for your AI provider
        $prompt = "You are a professional content writer. Write a complete, well-structured blog post.\n\n"
                . "Title: {$title}\n\n"
                . "Instructions / Brief:\n{$instructions}\n\n"
                . "Requirements:\n"
                . "- Use clear headings (H2/H3).\n"
                . "- Use short paragraphs.\n"
                . "- No intro apologizing, just start strong.\n";

        // Example: generic JSON body – adjust to your provider (OpenAI, Groq, etc.)
        $body = [
            'model'  => 'gpt-4.1-mini', // change to your model
            'input'  => $prompt,
        ];

        $response = wp_remote_post(
            $api_url,
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => 60,
            ]
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ], 500 );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'AI API error or empty response.', 'cg-ai-post-generator' ) ], 500 );
        }

        // Adjust this depending on your provider’s response shape
        // Example for OpenAI responses: $data['output'][0]['content'][0]['text']
        $generated_content = '';
        if ( isset( $data['output'][0]['content'][0]['text'] ) ) {
            $generated_content = $data['output'][0]['content'][0]['text'];
        } elseif ( isset( $data['choices'][0]['message']['content'] ) ) {
            $generated_content = $data['choices'][0]['message']['content'];
        }

        $generated_content = trim( (string) $generated_content );

        if ( empty( $generated_content ) ) {
            wp_send_json_error( [ 'message' => __( 'AI returned empty content.', 'cg-ai-post-generator' ) ], 500 );
        }

        $post_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_content' => wp_kses_post( $generated_content ),
                'post_status'  => 'draft',
                'post_type'    => $post_type,
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => $post_id->get_error_message() ], 500 );
        }

        wp_send_json_success(
            [
                'message'   => __( 'Draft created successfully.', 'cg-ai-post-generator' ),
                'post_id'   => $post_id,
                'edit_link' => get_edit_post_link( $post_id, 'raw' ),
                'content'   => wpautop( $generated_content ),
            ]
        );
    }
}

new CG_AI_Post_Generator();
