# Easy Bookinger - インストール・使用ガイド

## インストール手順

### 1. プラグインのアップロード
1. プラグインファイル一式をWordPressの `/wp-content/plugins/easy-bookinger/` ディレクトリにアップロードします
2. FTPまたはWordPress管理画面のプラグインアップロード機能を使用してください

### 2. プラグインの有効化
1. WordPress管理画面にログイン
2. 「プラグイン」メニューから「インストール済みプラグイン」を選択
3. 「Easy Bookinger」を見つけて「有効化」をクリック

### 3. 初期設定
1. 管理画面に「Easy Bookinger」メニューが追加されます
2. 「Easy Bookinger」→「設定」で基本設定を行います

## 基本設定

### 表示設定
- **表示月数**: カレンダーに表示する月数（1-12）
- **最大選択可能日数**: 一度に選択できる日数の上限
- **予約可能曜日**: 予約を受け付ける曜日を選択

### フォーム設定
以下のフィールドタイプが利用可能です：
- テキスト
- メール
- 電話番号
- テキストエリア
- セレクト
- ラジオボタン
- チェックボックス

### メール設定
- 管理者への通知メール
- 利用者への確認メール

## 使用方法

### ショートコードの基本使用
投稿やページに以下のショートコードを追加：

```
[easy_bookinger]
```

### ショートコードオプション

#### 表示月数の指定
```
[easy_bookinger months="6"]
```

#### テーマの指定
```
[easy_bookinger theme="minimal"]
[easy_bookinger theme="dark"]
```

#### 複数オプション
```
[easy_bookinger months="3" theme="minimal"]
```

## 予約の流れ

### 利用者側
1. カレンダーで希望日を選択
2. 「予約」ボタンをクリック
3. 必要事項を入力
4. 「登録」ボタンで予約完了
5. 確認メールとPDFリンクを受信

### 管理者側
1. 新規予約の通知メールを受信
2. 管理画面で予約内容を確認
3. 必要に応じて予約の有効/無効を切り替え
4. データをExcel形式でエクスポート可能

## 管理機能

### 予約管理
- 予約一覧の表示
- 予約の詳細確認
- 予約の有効化/無効化
- 予約の削除

### エクスポート機能
- 期間指定でのデータ抽出
- ステータス別フィルタ
- Excel/CSV形式での出力

### 統計情報
- 総予約数
- 有効/無効予約数
- 今月の予約数
- 今後の予約数

## PDF機能

### 自動生成
- 予約完了時に自動でPDF確認書を生成
- 12桁のランダムパスワードで保護
- ダウンロードリンクの有効期限設定（デフォルト180日）

### セキュリティ
- パスワード保護
- 一意のトークンによるアクセス制御
- 有効期限管理

## トラブルシューティング

### カレンダーが表示されない
1. JavaScriptエラーをブラウザの開発者ツールで確認
2. jQueryが正しく読み込まれているか確認
3. テーマとの競合がないか確認

### PDFが生成されない
1. TCPDFライブラリが正しく配置されているか確認
2. サーバーの権限設定を確認
3. PHP拡張機能（mb_string等）が有効か確認

### メールが送信されない
1. WordPressのメール設定を確認
2. SMTPプラグインの導入を検討
3. サーバーのメール送信機能を確認

### データベースエラー
1. WordPressのデータベース接続を確認
2. テーブルが正しく作成されているか確認
3. 権限設定を確認

## カスタマイズ

### CSSカスタマイズ
テーマの `style.css` や子テーマで以下のクラスをカスタマイズ可能：

```css
/* カレンダーのスタイル */
.easy-bookinger-container .eb-calendar-day.selectable {
    background-color: #your-color;
}

/* 選択された日付のスタイル */
.easy-bookinger-container .eb-calendar-day.selected {
    background-color: #your-selected-color;
}

/* ボタンのスタイル */
.easy-bookinger-container .eb-button.eb-primary {
    background-color: #your-button-color;
}
```

### PHPカスタマイズ
functions.php でフィルターフックを使用：

```php
// フォーム項目のカスタマイズ
add_filter('easy_bookinger_form_fields', 'custom_form_fields');
function custom_form_fields($fields) {
    // カスタム処理
    return $fields;
}

// メールテンプレートのカスタマイズ
add_filter('easy_bookinger_email_template', 'custom_email_template');
function custom_email_template($template) {
    // カスタム処理
    return $template;
}
```

## サポート

### 技術サポート
- GitHub Issues: バグ報告や機能要求
- ドキュメント: README.md を参照

### アップデート
プラグインの更新は WordPress 管理画面から自動で通知されます。

## セキュリティ

### 推奨事項
- 定期的なWordPressとプラグインの更新
- 強力な管理者パスワードの使用
- セキュリティプラグインの導入
- 定期的なバックアップの実施

### データ保護
- 個人情報は適切に暗号化されて保存
- GDPR準拠のデータ管理
- 定期的な不要データの削除