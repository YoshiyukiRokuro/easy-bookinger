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

### プラグインで使用されるテーブル

プラグインは以下のデータベーステーブルを作成・使用します：

#### easy_bookinger_bookings
予約データを管理するメインテーブル
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY) - 予約ID
- `booking_date` (date, NOT NULL) - 予約日
- `booking_time` (varchar(20)) - 予約時間
- `user_name` (varchar(255), NOT NULL) - 予約者氏名
- `email` (varchar(255), NOT NULL) - 予約者メールアドレス
- `phone` (varchar(50)) - 電話番号
- `comment` (text) - コメント
- `form_data` (longtext) - フォームデータ（シリアル化）
- `status` (varchar(20), DEFAULT 'active') - ステータス（active/inactive）
- `created_at` (datetime, DEFAULT CURRENT_TIMESTAMP) - 作成日時
- `updated_at` (datetime, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) - 更新日時

#### easy_bookinger_settings  
プラグイン設定を保存
- WordPress optionsテーブルに `easy_bookinger_settings` として保存

#### easy_bookinger_date_restrictions
日付制限情報を管理
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY) - 制限ID
- `restricted_date` (date, NOT NULL) - 制限日
- `restriction_type` (varchar(50), NOT NULL) - 制限タイプ（holiday/maintenance等）
- `reason` (varchar(255)) - 制限理由
- `created_at` (datetime, DEFAULT CURRENT_TIMESTAMP) - 作成日時

#### easy_bookinger_booking_quotas
日別予約枠数を管理
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY) - 枠設定ID
- `quota_date` (date, NOT NULL) - 対象日
- `max_bookings` (int, NOT NULL) - 最大予約数
- `created_at` (datetime, DEFAULT CURRENT_TIMESTAMP) - 作成日時

#### easy_bookinger_time_slots
時間帯設定を管理
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY) - 時間帯ID
- `start_time` (time, NOT NULL) - 開始時間
- `slot_name` (varchar(100), NOT NULL) - 時間帯名
- `max_bookings` (int, DEFAULT 1) - 最大予約数
- `is_active` (boolean, DEFAULT true) - 有効フラグ
- `created_at` (datetime, DEFAULT CURRENT_TIMESTAMP) - 作成日時

#### easy_bookinger_special_availability
臨時予約可能日を管理
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY) - 設定ID
- `special_date` (date, NOT NULL) - 臨時開放日
- `reason` (varchar(255)) - 理由
- `max_bookings` (int, DEFAULT 1) - 最大予約数
- `created_at` (datetime, DEFAULT CURRENT_TIMESTAMP) - 作成日時

#### easy_bookinger_admin_emails
管理者メールアドレスを管理
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY) - メールID
- `email_address` (varchar(255), NOT NULL) - メールアドレス
- `notification_types` (text) - 通知タイプ（シリアル化配列）
- `is_active` (boolean, DEFAULT true) - 有効フラグ
- `created_at` (datetime, DEFAULT CURRENT_TIMESTAMP) - 作成日時

## プラグインが使用するディレクトリ

### ファイル・ディレクトリ構造詳細

#### /wp-content/easy-bookinger/
プラグインが動的に作成・使用するディレクトリ
- **exports/** - エクスポート・バックアップファイル保存先
  - `.htaccess` - 直接アクセス制限ファイル
  - `index.php` - ディレクトリリスティング防止ファイル
  - 各種エクスポート・バックアップファイル（CSV、JSON）

#### プラグインディレクトリ構造
```
/wp-content/plugins/easy-bookinger/
├── easy-bookinger.php          # メインプラグインファイル（エントリーポイント）
├── includes/                   # コア機能クラス
│   ├── class-database.php     # データベース操作・テーブル管理
│   ├── class-shortcode.php    # ショートコード処理
│   ├── class-ajax.php         # AJAX リクエスト処理
│   ├── class-email.php        # メール送信機能
│   └── class-file-manager.php # ファイル管理（エクスポート・バックアップ）
├── admin/                      # 管理画面機能
│   ├── class-admin.php        # 管理画面メインクラス
│   ├── class-settings.php     # 設定画面
│   ├── class-export.php       # エクスポート機能
│   └── class-backup.php       # バックアップ・復元機能
├── public/                     # フロントエンド機能
│   └── class-public.php       # 公開画面処理
├── assets/                     # 静的リソース
│   ├── css/                   # スタイルシート
│   ├── js/                    # JavaScript
│   │   ├── easy-bookinger.js      # メインカレンダーJS
│   │   └── easy-bookinger-admin.js # 管理画面JS
│   └── images/                # 画像ファイル
├── languages/                  # 多言語対応
│   └── easy-bookinger-ja.po   # 日本語翻訳ファイル
├── examples/                   # 実装例・サンプルコード
│   ├── functions.php          # functions.php への記述例
│   └── page-booking.php       # カスタムページテンプレート例
└── vendor/                     # 外部ライブラリ（必要に応じて）
    └── tcpdf/                 # PDF生成ライブラリ（オプション）
```

### ディレクトリの用途

- **includes/**: プラグインのコア機能を提供するクラスファイル
- **admin/**: WordPress管理画面で使用される機能
- **public/**: フロントエンド（サイト訪問者向け）で使用される機能
- **assets/**: CSS、JavaScript、画像などの静的ファイル
- **languages/**: 多言語対応のための翻訳ファイル
- **examples/**: 開発者向けの実装例とサンプルコード

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