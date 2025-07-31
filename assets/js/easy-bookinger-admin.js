/**
 * Easy Bookinger Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Field management
        initFieldManagement();
        
        // Export functionality
        initExportFunctionality();
        
        // Confirmation dialogs
        initConfirmationDialogs();
    });
    
    function initFieldManagement() {
        var fieldIndex = $('.booking-field-row').length;
        
        // Add new field
        $('#add-field').on('click', function() {
            var fieldHtml = createFieldHtml(fieldIndex);
            $('#booking-fields-container').append(fieldHtml);
            fieldIndex++;
        });
        
        // Remove field
        $(document).on('click', '.remove-field', function() {
            $(this).closest('.booking-field-row').remove();
        });
    }
    
    function createFieldHtml(index) {
        return `
        <div class="booking-field-row" data-index="${index}">
            <table class="form-table">
                <tr>
                    <th>項目名</th>
                    <td>
                        <input type="text" name="booking_fields[${index}][name]" value="" placeholder="例: phone" />
                        <p class="description">半角英数字とアンダースコアのみ使用可能です</p>
                    </td>
                </tr>
                <tr>
                    <th>ラベル</th>
                    <td>
                        <input type="text" name="booking_fields[${index}][label]" value="" placeholder="例: 電話番号" />
                        <p class="description">フォームに表示される項目名です</p>
                    </td>
                </tr>
                <tr>
                    <th>タイプ</th>
                    <td>
                        <select name="booking_fields[${index}][type]">
                            <option value="text">テキスト</option>
                            <option value="email">メール</option>
                            <option value="tel">電話番号</option>
                            <option value="textarea">テキストエリア</option>
                            <option value="select">セレクト</option>
                            <option value="radio">ラジオボタン</option>
                            <option value="checkbox">チェックボックス</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>必須</th>
                    <td>
                        <label>
                            <input type="checkbox" name="booking_fields[${index}][required]" value="1" />
                            必須項目にする
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>最大文字数</th>
                    <td>
                        <input type="number" name="booking_fields[${index}][maxlength]" value="" min="1" placeholder="200" />
                        <p class="description">テキスト・テキストエリア項目の最大文字数（空の場合は制限なし）</p>
                    </td>
                </tr>
            </table>
            <button type="button" class="remove-field">この項目を削除</button>
            <hr>
        </div>`;
    }
    
    function initExportFunctionality() {
        // Toggle custom date range
        $('input[name="date_range"]').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#custom-date-range').show();
            } else {
                $('#custom-date-range').hide();
            }
        });
        
        // Validate export form
        $('form').on('submit', function(e) {
            if ($(this).find('input[name="export_excel"]').length > 0) {
                var dateRange = $('input[name="date_range"]:checked').val();
                
                if (dateRange === 'custom') {
                    var dateFrom = $('input[name="date_from"]').val();
                    var dateTo = $('input[name="date_to"]').val();
                    
                    if (!dateFrom || !dateTo) {
                        alert('期間を指定してください。');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (dateFrom > dateTo) {
                        alert('開始日は終了日より前の日付を指定してください。');
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    }
    
    function initConfirmationDialogs() {
        // Delete confirmation
        $('.button-link-delete').on('click', function(e) {
            if (!confirm('本当に削除しますか？この操作は取り消せません。')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Status change confirmation
        $('a[href*="action=deactivate"], a[href*="action=activate"]').on('click', function(e) {
            var action = $(this).text();
            if (!confirm(`予約を${action}しますか？`)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // AJAX functions for admin operations
    window.EasyBookingerAdmin = {
        deleteBooking: function(bookingId, callback) {
            $.ajax({
                url: easyBookingerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eb_admin_delete_booking',
                    nonce: easyBookingerAdmin.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (callback) callback(response);
                },
                error: function() {
                    alert('エラーが発生しました。');
                }
            });
        },
        
        updateBookingStatus: function(bookingId, status, callback) {
            $.ajax({
                url: easyBookingerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eb_admin_update_booking_status',
                    nonce: easyBookingerAdmin.nonce,
                    booking_id: bookingId,
                    status: status
                },
                success: function(response) {
                    if (callback) callback(response);
                },
                error: function() {
                    alert('エラーが発生しました。');
                }
            });
        },
        
        refreshBookingList: function() {
            location.reload();
        }
    };
    
})(jQuery);