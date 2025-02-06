jQuery(document).ready(function($) {
    $('#eam-refresh-links').on('click', function() {
        // 投稿編集画面では hidden フィールド post_ID から現在の投稿IDを取得
        var post_id = $('#post_ID').val();
        $.post(ajaxurl, {
            action: 'eam_fetch_internal_links',
            post_id: post_id
        }, function(response) {
            if(response.success) {
                var suggestionsDiv = $('#eam-internal-links-suggestions');
                suggestionsDiv.empty();
                if(response.data.length > 0) {
                    suggestionsDiv.append('<ul>');
                    $.each(response.data, function(i, item) {
                        suggestionsDiv.append('<li>' + item.title + ' (スコア：' + item.score + ')</li>');
                    });
                    suggestionsDiv.append('</ul>');
                } else {
                    suggestionsDiv.append('<p>候補が見つかりませんでした。</p>');
                }
            } else {
                alert('内部リンク候補の取得中にエラーが発生しました。');
            }
        });
    });
});

