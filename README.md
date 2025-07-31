# Easy Bookinger - WordPress予約日登録・集計システム

WordPress booking system plugin with calendar interface, PDF generation, and admin management.

## 概要

Easy Bookingerは、WordPressサイトに予約機能を追加するプラグインです。利用者は直感的なカレンダーUIで予約日を選択し、管理者は柔軟な設定と効率的なデータ管理が可能です。

## 主な機能

### 利用者機能
- **カレンダーUI**: 直感的な日付選択インターface
- **予約フォーム**: 管理者が設定可能な入力項目
- **PDF確認書**: 自動生成されるパスワード付きPDF
- **メール通知**: 予約完了時の自動メール送信

### 管理者機能
- **設定画面**: 表示月数、曜日制限、選択日数上限の設定
- **フォーム管理**: カスタマイズ可能な入力項目
- **データ管理**: 予約データの一覧表示・編集
- **エクスポート**: Excel形式でのデータダウンロード

## 技術仕様

- **対応PHP**: 8.0+
- **対応WordPress**: 5.0+
- **データベース**: MySQL 5.7+ / MariaDB 5.5.68+
- **PDFライブラリ**: TCPDF
- **フロントエンド**: HTML5, CSS3, JavaScript (jQuery)
- **レスポンシブ対応**: iOS/Android/PC

## インストール

1. プラグインファイルをWordPressの`/wp-content/plugins/`ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからEasy Bookingerを有効化
3. 管理画面に「Easy Bookinger」メニューが追加されます

## 使用方法

### 基本的な使用方法

投稿やページに以下のショートコードを追加：

```
[easy_bookinger]
```

### オプション

```
[easy_bookinger months="3"]          // 表示月数を指定
[easy_bookinger theme="minimal"]     // テーマを指定
[easy_bookinger months="6" theme="dark"]  // 複数オプション
```

### 設定

1. 管理画面の「Easy Bookinger」→「設定」で基本設定を行う
2. 表示月数、選択可能日数、予約可能曜日を設定
3. フォーム項目を追加・編集
4. メール通知の設定

## ファイル構成

```
easy-bookinger/
├── easy-bookinger.php          # メインプラグインファイル
├── includes/                   # コア機能
│   ├── class-database.php     # データベース管理
│   ├── class-shortcode.php    # ショートコード
│   ├── class-ajax.php         # AJAX処理
│   ├── class-email.php        # メール機能
│   └── class-pdf.php          # PDF生成
├── admin/                      # 管理画面
│   ├── class-admin.php        # 管理画面メイン
│   ├── class-settings.php     # 設定画面
│   └── class-export.php       # エクスポート機能
├── public/                     # フロントエンド
│   └── class-public.php       # 公開画面
├── assets/                     # 静的ファイル
│   ├── css/                   # スタイルシート
│   ├── js/                    # JavaScript
│   └── images/                # 画像
├── languages/                  # 言語ファイル
│   └── easy-bookinger-ja.po   # 日本語翻訳
└── vendor/                     # 外部ライブラリ
    └── tcpdf/                 # PDF生成ライブラリ
```

## データベース構造

### easy_bookinger_bookings
予約データを管理するメインテーブル

### easy_bookinger_settings  
プラグイン設定を保存

### easy_bookinger_pdf_links
PDFダウンロードリンクを管理

## カスタマイズ

### CSS カスタマイズ
テーマの`style.css`でスタイルをカスタマイズ可能：

```css
.easy-bookinger-container .eb-calendar-day.selectable {
    background-color: #your-color;
}
```

### フィルターフック
```php
// 予約フォームの項目をカスタマイズ
add_filter('easy_bookinger_form_fields', 'custom_form_fields');

// メールテンプレートをカスタマイズ  
add_filter('easy_bookinger_email_template', 'custom_email_template');
```

## トラブルシューティング

### PDFが生成されない
- TCPDFライブラリが正しくインストールされているか確認
- サーバーのPHP拡張機能が有効になっているか確認

### カレンダーが表示されない
- JavaScript エラーがないかブラウザの開発者ツールで確認
- jQuery が読み込まれているか確認

### メールが送信されない
- WordPressのメール設定を確認
- SMTPプラグインの使用を検討

## ライセンス

GPL v2 or later

## サポート

バグ報告や機能要求は GitHub Issues をご利用ください。

## 開発者向け情報

### 開発環境セットアップ
1. WordPressローカル環境の準備
2. プラグインディレクトリにクローン
3. 開発用設定でWP_DEBUGを有効化

### コーディング規約
WordPress Coding Standards に準拠

### テスト
PHPUnit を使用したユニットテスト（実装予定）