jQuery(document).ready(function ($) {
    const $form = $('#cg-ai-post-generator-form');
    const $btn = $('#cg_ai_generate_btn');
    const $status = $('.cg-ai-status');
    const $previewWrap = $('#cg_ai_preview');
    const $previewContent = $('.cg-ai-preview-content');

    if (!$form.length) return;

    $form.on('submit', function (e) {
        e.preventDefault();

        $status.text('');
        $previewWrap.hide();
        $previewContent.html('');

        const formData = $form.serializeArray();
        const data = {
            action: 'cg_ai_generate_post',
        };

        formData.forEach(function (field) {
            data[field.name] = field.value;
        });

        data['cg_ai_post_generator_nonce'] = CG_Ai_Post_Generator.nonce;

        $btn.prop('disabled', true).text('Generating...');
        $status.text('Talking to OpenAI…');

        $.post(CG_Ai_Post_Generator.ajax_url, data)
            .done(function (response) {
                if (!response || !response.success) {
                    $status.text(response && response.data && response.data.message ? response.data.message : 'Error generating content.');
                    return;
                }

                $status.text(response.data.message || 'Draft(s) created.');

                if (response.data.content) {
                    $previewContent.html(response.data.content);
                    $previewWrap.show();
                }

                if (response.data.edit_link) {
                    const link = $('<a>')
                        .attr('href', response.data.edit_link)
                        .addClass('button button-secondary')
                        .css('margin-left', '8px')
                        .text('Open first draft');
                    $status.append(link);
                }
            })
            .fail(function () {
                $status.text('Request failed. Check console or server logs.');
            })
            .always(function () {
                $btn.prop('disabled', false).text('Generate Draft(s)');
            });
    });
});
