# mozcheck

WordPress の Site Health 結果を、読みやすい定期メールで通知するプラグインです。

## 機能

- Site Health の critical / recommended / good 件数と問題内容をHTMLメールで通知
- WordPress本体・プラグイン・テーマの更新、停止中プラグイン、未使用テーマを通知
- 週次または月次、曜日・日付・時刻をサイトタイムゾーンで設定
- 複数の通知先へアドレスを公開せず個別送信
- critical件数、recommended件数、更新、前回からの悪化による条件通知
- 更新、PHP/DB、REST API、Loopback、HTTPS、Cronなどのカテゴリ絞り込み
- 設定画面からの手動診断・送信

設定は「設定 → MozCheck」から変更できます。通常の有効化では、管理者メール宛てに
毎週月曜9時の通知が有効になります。ネットワーク有効化では各サイトの通知は既定で
無効です。

通知にはWP-Cronを使用するため、アクセスがないサイトでは指定時刻より遅れる場合が
あります。

## 必要な環境

- PHP 8.0 以上 / Composer
- Node.js 22 / npm
- Docker（`wp-env` 用）

## セットアップ

```sh
composer install
npm install
npx playwright install chromium
npm run wp-env:start
```

開発サイトは <http://localhost:8888>、テストサイトは
<http://localhost:8889> です。どちらもユーザー名 `admin`、パスワード
`password` でログインできます。

```sh
npm run lint              # PHP / JavaScript / Markdown / package.json
npm run audit             # npm / Composer依存関係の脆弱性監査
npm run test:unit         # WordPress を起動しない PHPUnit
npm run test:integration  # wp-env 内の WordPress + DB
npm run test:e2e          # Playwright smoke test
npm test                  # unit / integration / E2Eテスト
npm run build:zip         # dist/mozcheck.zip の作成と検証
npm run wp-env:stop
```

## リリース

`package.json`、`mozcheck.php`、`readme.txt` のバージョンを揃え、
`vX.Y.Z` タグを push すると GitHub Actions が検証済みの
`mozcheck.zip` を GitHub Release に添付します。
