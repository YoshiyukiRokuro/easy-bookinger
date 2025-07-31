<?php
/**
 * Example WordPress page template showing Easy Bookinger integration
 * 
 * This is an example of how Easy Bookinger can be used in a WordPress theme.
 * Copy this to your theme directory as page-booking.php to create a dedicated booking page.
 */

get_header(); ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <article class="booking-page">
                <header class="page-header">
                    <h1 class="page-title">オンライン予約</h1>
                    <p class="page-description">
                        ご希望の日程を選択して、必要事項をご入力ください。<br>
                        予約完了後、確認メールとPDF確認書をお送りいたします。
                    </p>
                </header>
                
                <div class="booking-content">
                    <!-- Easy Bookinger Shortcode -->
                    <?php echo do_shortcode('[easy_bookinger months="3" theme="default"]'); ?>
                </div>
                
                <div class="booking-info">
                    <h3>ご予約について</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>📅 予約可能日</h4>
                            <p>平日（月〜金）のみご予約可能です。土日祝日は休業日となります。</p>
                        </div>
                        
                        <div class="info-item">
                            <h4>⏰ 受付時間</h4>
                            <p>9:00〜17:00（12:00〜13:00除く）</p>
                        </div>
                        
                        <div class="info-item">
                            <h4>📧 確認メール</h4>
                            <p>予約完了後、入力いただいたメールアドレスに確認メールをお送りします。</p>
                        </div>
                        
                        <div class="info-item">
                            <h4>📄 PDF確認書</h4>
                            <p>予約確認書をPDF形式でダウンロードできます。パスワードは確認メールに記載されています。</p>
                        </div>
                    </div>
                </div>
                
                <div class="booking-notes">
                    <h3>注意事項</h3>
                    <ul>
                        <li>予約の変更・キャンセルは前日までにご連絡ください</li>
                        <li>当日のキャンセルはキャンセル料が発生する場合があります</li>
                        <li>確認メールが届かない場合は、迷惑メールフォルダをご確認ください</li>
                        <li>ご不明な点がございましたら、お気軽にお問い合わせください</li>
                    </ul>
                </div>
                
                <div class="contact-info">
                    <h3>お問い合わせ</h3>
                    <p>
                        <strong>電話:</strong> 03-1234-5678<br>
                        <strong>メール:</strong> info@example.com<br>
                        <strong>受付時間:</strong> 平日 9:00〜17:00
                    </p>
                </div>
            </article>
        </div>
    </div>
</div>

<style>
/* カスタムスタイル例 */
.booking-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #007cba;
}

.page-title {
    color: #333;
    margin-bottom: 15px;
}

.page-description {
    color: #666;
    font-size: 16px;
    line-height: 1.6;
}

.booking-content {
    margin-bottom: 50px;
}

.booking-info {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.info-item {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #007cba;
}

.info-item h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.info-item p {
    margin: 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.booking-notes {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 30px;
}

.booking-notes h3 {
    margin-top: 0;
    color: #856404;
}

.booking-notes ul {
    margin: 15px 0 0 20px;
    color: #856404;
}

.contact-info {
    text-align: center;
    background: #e8f4fd;
    padding: 20px;
    border-radius: 5px;
}

.contact-info h3 {
    margin-top: 0;
    color: #007cba;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .booking-page {
        padding: 15px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
}

/* Easy Bookinger テーマカスタマイズ例 */
.easy-bookinger-container .eb-calendar-day.selectable {
    background-color: #e8f5e8;
}

.easy-bookinger-container .eb-calendar-day.selected {
    background-color: #007cba;
    color: white;
}

.easy-bookinger-container .eb-button.eb-primary {
    background-color: #007cba;
    font-weight: bold;
}

.easy-bookinger-container .eb-button.eb-primary:hover {
    background-color: #005a87;
}
</style>

<?php get_footer(); ?>