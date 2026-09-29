# BookShelf 書籍レビューアプリ

書籍の登録・閲覧、レビュー、お気に入り、ジャンル管理、評価ランキングを扱うLaravel製Webアプリケーションです。

現在はWeb画面の基本機能、書籍API、SanctumによるAPIトークン認証、および自動テストを実装しています。Web画面の検索・並び替え・ISBN検索にも対応しています。読書計画・通知などは今後実装予定です。

## 実装済み機能

- 会員登録、ログイン、ログアウト
- 書籍の一覧・詳細表示
- 認証ユーザーによる書籍の登録・編集・削除
- 書籍と複数ジャンルの紐づけ
- レビューの投稿・編集・削除
- 1ユーザーにつき1書籍1件までのレビュー制御
- お気に入りの追加・解除と一覧表示
- レビューへのいいねの追加・解除
- ジャンルの一覧・詳細・登録・編集・削除
- レビュー平均評価による上位10冊のランキング表示
- 所有者だけが書籍・レビューを編集・削除できる認可
- FormRequestによる入力検証と日本語エラーメッセージ
- SanctumのBearerトークンによるAPI認証・トークン発行と失効
- PHPUnitによる基本機能と応用API認証の自動テスト

- Web書籍一覧のタイトル・著者検索、ジャンル絞り込み、4種類の並び替え
- Google Books APIによるISBN-13検索と登録フォームへの自動入力
- 本人のレビューを集計するマイ読書レポート

## 未実装

- 読書計画・通知・日次バッチ処理

## 使用技術

| 分類 | 技術 |
|---|---|
| バックエンド | PHP 8.5 / Laravel 10.50.3 |
| 認証 | Laravel Fortify / Laravel Sanctum |
| データベース | MySQL 8.4 |
| フロントエンド | Blade / Vite 5 / Tailwind CSS 3.4 / Alpine.js |
| 開発環境 | Docker / Docker Compose / Laravel Sail |
| DB管理 | phpMyAdmin |
| コード整形 | Laravel Pint |

## 応用フェーズ移行準備

以下の応用ER図は設計上の構成であり、追加テーブルを実装済みという意味ではありません。

## 基本機能のER図

```mermaid
erDiagram
    users ||--o{ books : creates
    users ||--o{ reviews : posts
    users ||--o{ favorites : adds
    users ||--o{ review_likes : adds
    books ||--o{ reviews : receives
    books ||--o{ favorites : receives
    books ||--o{ book_genre : categorized_as
    genres ||--o{ book_genre : contains
    reviews ||--o{ review_likes : receives

    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        timestamp created_at
        timestamp updated_at
    }

    books {
        bigint id PK
        bigint user_id FK
        varchar title
        varchar author
        varchar isbn UK
        date published_date
        text description
        varchar image_url
        timestamp created_at
        timestamp updated_at
    }

    genres {
        bigint id PK
        varchar name UK
        timestamp created_at
        timestamp updated_at
    }

    book_genre {
        bigint id PK
        bigint book_id FK
        bigint genre_id FK
        timestamp created_at
        timestamp updated_at
    }

    reviews {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        tinyint rating
        text comment
        timestamp created_at
        timestamp updated_at
    }

    favorites {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        timestamp created_at
        timestamp updated_at
    }

    review_likes {
        bigint id PK
        bigint user_id FK
        bigint review_id FK
        timestamp created_at
        timestamp updated_at
    }
```

複合ユニーク制約は次のとおりです。

- `book_genre`: `book_id`, `genre_id`
- `reviews`: `user_id`, `book_id`
- `favorites`: `user_id`, `book_id`
- `review_likes`: `user_id`, `review_id`

## 応用機能のER図（設計）

既存の基本テーブルに以下の関連とテーブルを追加します。books.isbn・published_dateはNULL許可へ変更します。

```mermaid
erDiagram
    users ||--o{ books : creates
    users ||--o{ reviews : posts
    users ||--o{ favorites : adds
    users ||--o{ review_likes : adds
    books ||--o{ reviews : receives
    books ||--o{ favorites : receives
    books ||--o{ book_genre : categorized_as
    genres ||--o{ book_genre : contains
    reviews ||--o{ review_likes : receives
    users ||--o{ reading_plans : owns
    books ||--o{ reading_plans : planned_for
    users ||..o{ notifications : polymorphic_recipient
    reading_plans ||..o{ notifications : referenced_in_json
    users ||..o{ personal_access_tokens : polymorphic_owner

    reading_plans {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        date target_date
        enum status "default in_progress"
        timestamp completed_at "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    notifications {
        uuid id PK
        varchar type
        varchar notifiable_type
        bigint notifiable_id
        text data "JSON"
        timestamp read_at "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    personal_access_tokens {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        varchar name
        varchar token UK
        text abilities "nullable"
        timestamp last_used_at "nullable"
        timestamp expires_at "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
```

破線は通常の外部キーを設定しない関連を表します。notificationsはLaravelのポリモーフィック関連、計画IDはdata内の論理参照です。計画と関連通知の削除はアプリケーションの同一トランザクションで処理します。personal_access_tokensは既存テーブルです。

## 環境構築

Docker、Docker Composeが利用できる環境を前提とします。

```bash
git clone <repository-url>
cd bookshelf-app
cp .env.example .env
```

`.env` のデータベース設定を次のように変更します。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Docker経由で依存パッケージを準備した後、Sailを起動します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

別のターミナルで、テーブル作成と初期データ投入を実行します。

```bash
./vendor/bin/sail artisan migrate --seed
```

既存のデータベースを初期化して作り直す場合は、次のコマンドを使用します。この操作は既存データを削除します。

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

## URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost |
| phpMyAdmin | http://localhost:8080 |

## 初期ユーザー

`./vendor/bin/sail artisan migrate --seed` の実行後、以下のユーザーでログインできます。パスワードは全ユーザー共通で `password` です。

| 名前 | メールアドレス |
|---|---|
| 山田太郎 | yamada@example.com |
| 鈴木花子 | suzuki@example.com |
| 田中一郎 | tanaka@example.com |
| 佐藤美咲 | sato@example.com |
| 高橋健太 | takahashi@example.com |

## Webルート概要

| 機能 | メソッド・パス |
|---|---|
| 書籍一覧 | `GET /`, `GET /books` |
| 書籍詳細 | `GET /books/{book}` |
| 書籍登録 | `GET /books/create`, `POST /books` |
| 書籍編集・削除 | `GET /books/{book}/edit`, `PUT /books/{book}`, `DELETE /books/{book}` |
| レビュー投稿 | `POST /books/{book}/reviews` |
| レビュー編集・削除 | `GET /reviews/{review}/edit`, `PUT /reviews/{review}`, `DELETE /reviews/{review}` |
| お気に入り | `GET /favorites`, `POST /books/{book}/favorites` |
| レビューいいね | `POST /reviews/{review}/like` |
| ジャンル管理 | `/genres` 以下のリソースルート |
| ランキング | `GET /ranking` |
| マイ読書レポート（認証必須） | `GET /reports` |

## マイ読書レポート（Issue #12）

ログイン後、`/reports`で本人が投稿した全期間のレビューを確認できます。未ログインの場合はログイン画面へ移動します。

- 基本統計：総レビュー数、レビューした書籍のユニーク件数、平均評価（小数第1位）。レビューがない場合は件数0、平均評価は`null`として画面では`-`を表示します。
- 評価分布：1〜5の各件数を横棒で表示します。該当するレビューがない評価も0件で表示します。
- 高評価書籍TOP5：本人の評価が4以上の書籍を評価の高い順に最大5件表示します。同評価ではレビュー投稿日が新しい順とし、書籍詳細へ移動できます。
- ジャンル別評価傾向TOP5：本人のレビューの平均評価が高い順、同平均ではレビュー件数が多い順に最大5件表示します。複数ジャンルの書籍は各ジャンルで集計し、ジャンル詳細へ移動できます。

読了冊数はレビューした書籍を基準とし、読書計画の完了件数は含めません。月別集計は対象外です。既存のレポート用Bladeを利用し、集計クエリと書籍のEager Loadingでデータを取得します。

レポートのテストは`./vendor/bin/sail artisan test --filter=ReadingReportTest`で実行できます。本人以外のレビューの除外、全期間集計、空の状態、順位・同率時の順序、最大5件、複数ジャンル、未認証時のリダイレクトを検証します。

2026-09-29のIssue #12実装後、PHPテスト全64件・439アサーションが成功しました（SQLiteインメモリDB）。レポート専用テストは5件・26アサーションです。

## 書籍の検索・ソート・ISBN検索（Issue #11）

`GET /books`（トップ画面 `/` も同様）はゲストでも利用できます。

- `keyword`：前後の空白を除去し、タイトルまたは著者を部分一致で検索します。空の場合は検索条件を追加しません。
- `genre_id`：既存ジャンルIDで絞り込みます。未指定は全ジャンル、不正なIDは日本語の入力エラーとして元の画面へ戻します。
- `sort`：`latest`（登録の新しい順）、`oldest`（古い順）、`title`（タイトル昇順）、`rating`（平均評価の高い順）。未指定・不正値は`latest`です。
- 評価が同じ場合は登録の新しい順、レビューなしの書籍は評価順の最後に表示します。
- 条件は組み合わせ可能で、1ページ10件。ページ移動後も`keyword`・`genre_id`・`sort`を保持します。

ログイン後、書籍登録画面の「ISBN から書籍情報を自動入力」で13桁の半角数字を入力します。`GET /books/isbn/{isbn}`がGoogle Books APIへ問い合わせ、タイトル・著者・出版日・説明・画像URLをフォームに反映します。通信処理は`GoogleBooksService`に分離しています。

検索だけではDBに保存されません。ISBNは検索に使った値を設定し、取得できなかった項目は入力済みの値を保持します。出版日が年だけ・年月だけの場合は日付を補わず、現在の入力を保持します。取得後も全項目を編集してから通常の登録ができます。Web登録・編集でもISBN・出版日は任意入力です。

| 結果 | HTTPステータス |
|---|---|
| 取得成功 | 200 |
| 該当なし | 404 |
| ISBN形式不正 | 422 |
| Google BooksのHTTPエラー・タイムアウト・不正レスポンス | 502 |

Google Books APIを有効にしたプロジェクトのAPIキーを、サーバーの`.env`へ設定してください。キーはブラウザーに渡しません。[Google Books公式の認証・APIキー説明](https://developers.google.com/books/docs/v1/using#auth)

```dotenv
GOOGLE_BOOKS_API_KEY=取得したAPIキー
```

設定をキャッシュしている場合は`./vendor/bin/sail artisan config:clear`を実行してください。通信の接続タイムアウトは3秒、全体のタイムアウトは10秒です。

## 公開API

GETの書籍一覧・詳細は認証不要です。書き込みAPIには `Authorization: Bearer <token>` を指定します。更新・削除はBookPolicyにより所有者だけに許可します。更新時は入力検証より先に所有者を確認します。

| メソッド | パス | 概要 | 認証 | 成功時 |
|---|---|---|---|---|
| GET | `/api/v1/books` | 書籍一覧を取得 | 不要 | 200 |
| GET | `/api/v1/books/{book}` | 書籍詳細を取得 | 不要 | 200 |
| POST | `/api/v1/books` | 書籍を登録 | Bearerトークン | 201 |
| PUT | `/api/v1/books/{book}` | 書籍を更新 | Bearerトークン・所有者 | 200 |
| DELETE | `/api/v1/books/{book}` | 書籍を削除 | Bearerトークン・所有者 | 204（本文なし） |
| POST | `/api/v1/tokens` | トークンを発行 | メールアドレス・パスワード | 200 |
| DELETE | `/api/v1/tokens/current` | 使用中のトークンを失効 | Bearerトークン | 204（本文なし） |

トークン発行には `email`・`password`・`device_name` が必須です。成功時は `{"token":"..."}` を返します。認証情報の不一致は401、入力不備は422です。失効したトークンで認証必須APIにアクセスすると401になります。他の端末のトークンは失効しません。

一覧APIでは、`keyword`（タイトル・著者・ISBNの部分一致）、`genre_id`、`page`、`per_page`を指定できます。登録日時の新しい順に返し、`per_page`は初期値20件、1〜100件です。API Resourceでジャンル・平均評価・レビュー件数を返し、詳細にはレビューも含めます。

登録・更新は `title`・`author`・`genre_ids` が必須です。`genre_ids`は既存ジャンルIDを1件以上含む、重複のない配列です。`isbn`・`published_date`・`description`・`image_url` は任意入力で、`null`を許可します。ISBNは入力時13桁・一意（更新対象自身を除外）、出版日は有効な日付、画像URLは有効なURLとして検証します。更新時に任意項目を空にする場合は明示的に`null`を送信します。

登録者は認証ユーザーに固定され、送信された`user_id`は使用しません。更新時も登録者は変更しません。書籍の保存とジャンル同期はトランザクション内で実行します。書籍削除時はレビュー・いいね・お気に入り・ジャンルとの紐づけを削除し、ジャンル自体は残します。

APIのエラーは`Accept`ヘッダーの有無にかかわらずJSONで返します。

| ステータス | 条件 |
|---|---|
| 401 | 未認証・無効または失効済みトークン・ログイン情報不一致 |
| 403 | 所有者以外による更新・削除 |
| 404 | 対象の書籍が存在しない |
| 422 | 入力検証エラー（日本語メッセージ） |

書籍が存在しない場合、GET・PUT・DELETEともに `{"error":"書籍が見つかりませんでした。"}` を返します。認証必須APIでは未認証の401を先に判定します。

ISBN・出版日のnullable化には追加マイグレーションを使用します。環境更新時は`composer install`と`php artisan migrate`を実行してください（Sail環境ではそれぞれ`./vendor/bin/sail composer install`・`./vendor/bin/sail artisan migrate`）。SQLiteでのカラム変更に必要なDoctrine DBALも依存関係に含めています。

## テスト

テストではSQLiteのインメモリデータベースと`RefreshDatabase`を使用します。開発用のMySQLデータベースとは分離され、テストごとに独立した状態で実行されます。

基本機能と応用APIについて、次の機能を検証しています。

- モデルとリレーション
- 会員登録、ログイン、ログアウト
- ゲストと認証ユーザーの画面アクセス制御
- 書籍のCRUD、入力検証、所有者認可、関連データ削除
- レビューのCRUD、入力検証、重複投稿防止、投稿者認可
- ジャンルのCRUD、入力検証、書籍との紐づけ、削除制限
- お気に入りとレビューいいねの追加・解除・重複防止
- 平均評価とレビュー件数によるランキング
- 公開APIのCRUD、検索、絞り込み、ページネーション、JSONレスポンス
- トークン発行・失効、認証必須、所有者認可、登録者の改ざん防止、任意入力
- AcceptヘッダーなしでのJSONエラー返却
- HTTPステータス`200`、`201`、`204`、`401`、`403`、`404`、`422`

すべてのテストを実行するには、次のコマンドを使用します。

```bash
./vendor/bin/sail artisan test
```

ISBNフォームのJavaScriptテストは次のコマンドで実行します。

```bash
./vendor/bin/sail node --test tests/js/isbn-form.test.cjs
```

カバレッジを確認する場合は、次のコマンドを使用します。

```bash
./vendor/bin/sail artisan test --coverage
```

2026-09-29のIssue #11実装後、基本機能・応用API・Web検索・ISBN検索を含むPHPテスト全59件・413アサーションが成功しました（SQLiteインメモリDB）。Google Books APIはHTTPモックで正常応答・該当なし・通信失敗を検証しています。フォーム反映のJavaScriptテスト3件、Pintによる変更PHPファイルの整形確認、Viteビルドも成功しました。実APIとの疎通、開発用MySQLの変更、カバレッジ測定は今回行っていません。

## コード整形

```bash
./vendor/bin/sail pint --test
```

自動整形を行う場合は次を実行します。

```bash
./vendor/bin/sail pint
```

## 作成者

南 雄大
