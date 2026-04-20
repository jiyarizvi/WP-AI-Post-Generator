<?php
/**
 * Plugin Name: CG AI Post Generator
 * Description: Generate blog posts with AI and save them as drafts in any post type.
 * Version: 1.0.1
 * Author: Coin Gazette
 * Text Domain: cg-ai-post-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_AI_Post_Generator
{
    const OPTION_API_KEY   = 'cg_ai_post_generator_api_key';
    const OPTION_API_URL   = 'cg_ai_post_generator_api_url';
    const NONCE_ACTION     = 'cg_ai_post_generator_nonce_action';
    const NONCE_NAME       = 'cg_ai_post_generator_nonce';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_cg_ai_generate_post', [$this, 'handle_generate_post']);
    }

    public function register_admin_page()
    {
        add_submenu_page(
            'edit.php',
            __('AI Post Generator', 'cg-ai-post-generator'),
            __('AI Post Generator', 'cg-ai-post-generator'),
            'edit_posts',
            'cg-ai-post-generator',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings()
    {
        register_setting(
            'cg_ai_post_generator_settings',
            self::OPTION_API_KEY,
            ['sanitize_callback' => 'sanitize_text_field']
        );

        register_setting(
            'cg_ai_post_generator_settings',
            self::OPTION_API_URL,
            ['sanitize_callback' => 'esc_url_raw']
        );

        add_settings_section(
            'cg_ai_post_generator_main_section',
            __('AI API Settings', 'cg-ai-post-generator'),
            function () {
                echo '<p>Enter your OpenAI API endpoint and API key.</p>';
            },
            'cg_ai_post_generator_settings_page'
        );

        add_settings_field(
            self::OPTION_API_URL,
            __('API Endpoint URL', 'cg-ai-post-generator'),
            [$this, 'render_field_api_url'],
            'cg_ai_post_generator_settings_page',
            'cg_ai_post_generator_main_section'
        );

        add_settings_field(
            self::OPTION_API_KEY,
            __('API Key', 'cg-ai-post-generator'),
            [$this, 'render_field_api_key'],
            'cg_ai_post_generator_settings_page',
            'cg_ai_post_generator_main_section'
        );
    }

    public function render_field_api_url()
    {
        $value = esc_url(get_option(self::OPTION_API_URL, 'https://api.openai.com/v1/chat/completions'));
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_API_URL) . '" value="' . esc_attr($value) . '" />';
    }

    public function render_field_api_key()
    {
        $value = esc_attr(get_option(self::OPTION_API_KEY, ''));
        echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_API_KEY) . '" value="' . $value . '" />';
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'posts_page_cg-ai-post-generator') {
            return;
        }

        wp_enqueue_style(
            'cg-ai-post-generator-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'cg-ai-post-generator-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script(
            'cg-ai-post-generator-admin',
            'CG_Ai_Post_Generator',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            ]
        );
    }

    public function render_admin_page()
    {
        $post_types = get_post_types(['public' => true], 'objects');
        ?>
        <div class="wrap cg-ai-post-generator-wrap">
            <h1>AI Post Generator</h1>

            <div class="cg-ai-post-generator-layout">
                <div class="cg-ai-post-generator-left">
                    <h2>Generate New Content</h2>

                    <form id="cg-ai-post-generator-form">
                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="cg_ai_title">Post Title</label></th>
                                <td><input type="text" id="cg_ai_title" name="title" class="regular-text" required /></td>
                            </tr>

                            <tr>
                                <th><label for="cg_ai_instructions">Brief / Instructions</label></th>
                                <td>
                                    <textarea id="cg_ai_instructions" name="instructions" rows="6" class="large-text"></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="cg_ai_post_type">Post Type</label></th>
                                <td>
                                    <select id="cg_ai_post_type" name="post_type">
                                        <?php foreach ($post_types as $type) : ?>
                                            <option value="<?php echo esc_attr($type->name); ?>">
                                                <?php echo esc_html($type->labels->singular_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" class="button button-primary" id="cg_ai_generate_btn">Generate Draft</button>
                            <span class="cg-ai-status"></span>
                        </p>
                    </form>

                    <div id="cg_ai_preview" class="cg-ai-preview" style="display:none;">
                        <h2>Generated Preview</h2>
                        <div class="cg-ai-preview-content"></div>
                    </div>
                </div>

                <div class="cg-ai-post-generator-right">
                    <h2>AI Settings</h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('cg_ai_post_generator_settings');
                        do_settings_sections('cg_ai_post_generator_settings_page');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * ---------------------------------------------------------
     *  OPENAI‑POWERED POST GENERATION
     * ---------------------------------------------------------
     */
    public function handle_generate_post()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 400);
        }

        $title        = sanitize_text_field($_POST['title'] ?? '');
        $instructions = wp_kses_post($_POST['instructions'] ?? '');
        $post_type    = sanitize_key($_POST['post_type'] ?? 'post');

        if (empty($title)) {
            wp_send_json_error(['message' => 'Title is required.'], 400);
        }

        $api_url = get_option(self::OPTION_API_URL);
        $api_key = get_option(self::OPTION_API_KEY);

        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(['message' => 'AI API settings are missing.'], 400);
        }

        $user_prompt = "Write a complete, well-structured blog post.\n\n"
            . "Title: {$title}\n\n"
            . "Instructions:\n{$instructions}\n\n"
            . "Requirements:\n"
            . "- Use H2/H3 headings\n"
            . "- Short paragraphs\n"
            . "- Strong intro\n";

        $body = [
            'model' => 'gpt-4.1-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional content writer for a crypto and finance publication.'
                ],
                [
                    'role' => 'user',
                    'content' => $user_prompt
                ]
            ],
            'temperature' => 0.7
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        $generated_content = $data['choices'][0]['message']['content'] ?? '';

        if (empty($generated_content)) {
            wp_send_json_error(['message' => 'AI returned empty content.'], 500);
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => wp_kses_post($generated_content),
            'post_status'  => 'draft',
            'post_type'    => $post_type,
        ]);

        wp_send_json_success([
            'message'   => 'Draft created successfully.',
            'post_id'   => $post_id,
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'content'   => wpautop($generated_content),
        ]);
    }
}

new CG_AI_Post_Generator();
