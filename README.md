# mozcheck

機能実装前の WordPress プラグイン開発用スケルトンです。

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
npm run test:unit         # WordPress を起動しない PHPUnit
npm run test:integration  # wp-env 内の WordPress + DB
npm run test:e2e          # Playwright smoke test
npm test                  # すべてのテスト
npm run build:zip         # dist/mozcheck.zip の作成と検証
npm run wp-env:stop
```

## リリース

`package.json`、`mozcheck.php`、`readme.txt` のバージョンを揃え、
`vX.Y.Z` タグを push すると GitHub Actions が検証済みの
`mozcheck.zip` を GitHub Release に添付します。
