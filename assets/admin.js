jQuery(document).ready(function ($) {
    const $form   = $('#cg-ai-post-generator-form');
    const $btn    = $('#cg_ai_generate_btn');
    const $status = $('.cg-ai-status');
    const $previewWrap = $('#cg_ai_preview');
    const $previewContent = $('.cg-ai-preview-content');

    $form.on('submit', function (e) {
        e.preventDefault();

        $status.text('Generating…');
        $btn.prop('disabled', true);
        $previewWrap.hide();
        $previewContent.empty();

        const formData = {
            action: 'cg_ai_generate_post',
            title: $('#cg_ai_title').val(),
            instructions: $('#cg_ai_instructions').val(),
            post_type: $('#cg_ai_post_type').val()
        };

        formData[CG_Ai_Post_Generator.nonce_name || 'cg_ai_post_generator_nonce'] = CG_Ai_Post_Generator.nonce;

        $.post(CG_Ai_Post_Generator.ajax_url, formData)
            .done(function (response) {
                if (response.success) {
                    $status.text(response.data.message);
                    if (response.data.content) {
                        $previewContent.html(response.data.content);
                        $previewWrap.show();
                    }
                    if (response.data.edit_link) {
                        $status.append(
                            ' – <a href="' + response.data.edit_link + '">Edit draft</a>'
                        );
                    }
                } else {
                    $status.text(response.data && response.data.message ? response.data.message : 'Error generating content.');
                }
            })
            .fail(function () {
                $status.text('Request failed.');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });
});
