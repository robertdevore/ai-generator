jQuery(document).ready(function($) {
    $('#ai_generate_button').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $('#ai_generate_button');
        // Store the original button content to restore later.
        var originalText = $btn.html();
        // Disable the button and change its content to show a spinner.
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Generating...');
        
        var prompt = $('#ai_generate_prompt').val();
        $('#ai_generate_status').text('Generating content, please wait...');

        $.ajax({
            url: aiPlugin.ajaxurl,
            method: 'POST',
            data: {
                action: 'generate_ai_content',
                nonce: aiPlugin.nonce,
                prompt: prompt
            },
            success: function(response) {
                if (response.success) {
                    var generatedContent = response.data.content;
                    
                    // For Classic Editor: update the textarea.
                    if ($('#content').length) {
                        $('#content').val(generatedContent);
                    } 
                    // For Gutenberg:
                    else if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
                        // Convert the Markdown text to HTML.
                        var htmlContent = marked.parse(generatedContent);
                        
                        var blocks = null;
                        // Try using the rawHandler if available.
                        if ( typeof wp.blocks.rawHandler === 'function' ) {
                            blocks = wp.blocks.rawHandler({ HTML: htmlContent });
                        } 
                        // Fallback to the unstable parse method.
                        else if ( typeof wp.blocks.__unstableParse === 'function' ) {
                            blocks = wp.blocks.__unstableParse(htmlContent);
                        } 
                        // Last resort, use wp.blocks.parse.
                        else if ( typeof wp.blocks.parse === 'function' ) {
                            blocks = wp.blocks.parse(htmlContent);
                        }
                        
                        if ( blocks && blocks.length > 0 ) {
                            // Replace the current blocks with the parsed blocks.
                            wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                        } else {
                            // If no blocks were generated, insert as a paragraph block.
                            wp.data.dispatch('core/block-editor').insertBlocks(
                                wp.blocks.createBlock('core/paragraph', { content: htmlContent })
                            );
                        }
                    }
                    
                    $('#ai_generate_status').text('Content generated successfully.');
                } else {
                    $('#ai_generate_status').text('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#ai_generate_status').text('AJAX error: ' + error);
            },
            complete: function() {
                // Re-enable the button and restore its original content.
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
