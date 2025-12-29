# Laravel Advanced API Architecture & Performance Study (Laravel 12)

本リポジトリは、Laravel における 「高負荷環境下でのデータ整合性」 と 「クエリパフォーマンスの最適化」 を検証・学習するための実装サンドボックスです。

一般的な予約システムの全機能を網羅するのではなく、「オーバーブッキングの防止（排他制御）」 や 「二重決済の防止（冪等性）」、「大量データの高速処理」 といった、バックエンドエンジニアリングにおける特定の技術的課題にフォーカスして実装を行いました。

## 🎯 プロジェクトの目的 (Objectives)

-   Race Condition の再現と解決: 高並行アクセス時におけるデータの不整合（オーバーブッキング）を、データベースのロック機構を用いて防ぐアーキテクチャの実証。
-   API の信頼性向上: ネットワーク不安定時などのリトライに耐えうる「冪等性（Idempotency）」の独自実装と検証。
-   パフォーマンスチューニング: 数万件〜数百万件規模のデータを扱う際のメモリ効率と処理速度の比較検証（Hydration Skipping / Cursor Pagination）。
-   堅牢な設計パターンの実践: Action パターンや Policy、Enums を活用した、変更に強くテスト容易性の高いコードベースの構築。

## 🚀 技術的なハイライト (Key Technical Highlights)

このプロジェクトでは、以下の技術的課題に対する解決策を実装しています。

### 1. 厳密な排他制御によるオーバーブッキング防止

高負荷時に複数のリクエストが同時に来た場合でも、座席数を超えて予約されないように設計しています。

-   **手法:** 悲観的ロック (`SELECT ... FOR UPDATE`) を採用。
-   **実装:** コントローラーで受け取った Model をそのまま使わず、Action クラス内で必ず `lockForUpdate()` を用いて最新データを取得し直すことで、Race Condition（競合状態）を完全に防いでいます。

### 2. 冪等性（Idempotency）の担保

ネットワーク不安定時のリトライや、ユーザーのボタン連打による「二重予約」「二重課金」を防ぐ仕組みを自作しました。

-   **実装:** `IdempotencyMiddleware` を実装。
-   **仕組み:** クライアントから送られる `Idempotency-Key` ヘッダーをキャッシュ（Redis/File）に記録し、同一キーのリクエストには処理を行わず、キャッシュされた前回のレスポンスを返却します。また、アトミックロックを用いて同時実行も防いでいます。

### 3. 多層防御 (Defense in Depth) によるセキュリティ

権限管理を複数のレイヤーで実施し、誤操作や不正アクセスを防止しています。

1.  **Controller 層:** `Gate::authorize()` による入り口でのチェック（403 Forbidden）。
2.  **Domain 層 (Action):** ビジネスロジック内でも `isOwnedBy()` チェックを行い、コントローラーを経由しない実行（Job や Command）であっても整合性を保つ設計。

### 4. Action パターンによる責務の分離

「Fat Controller」を防ぎ、テスト容易性と再利用性を高めるために Action パターンを採用しています。

-   **Controller:** リクエストの受付、バリデーション、レスポンス整形のみ。
-   **Action:** ビジネスロジック、DB トランザクション管理。
-   **Policy:** 認可ロジック。
-   **Resource:** API レスポンスの整形（JSON 構造の統一）。

### 5. 大量データ処理の最適化 (Read Optimization)

CSV エクスポート等を想定した、読み取り処理のパフォーマンス検証を行いました。

-   Hydration Skipping (toBase): Eloquent モデルのインスタンス化を回避し、メモリ消費を削減。
-   Cursor Pagination (lazyById): LazyCollection を活用し、メモリ使用量を一定に保ちながらストリーミング処理を実現。

---

## 🛠 使用技術 (Tech Stack)

-   **Framework:** Laravel 11
-   **Language:** PHP 8.4
-   **Database:** MySQL 8.0
-   **Testing:** Pest (Feature Tests, Unit Tests)
-   **Documentation:** Scribe (API Documentation Generator)
-   **Architecture:** Layered Architecture (Action Pattern)

---

## 📂 ディレクトリ構造 (Key Structure)

ドメインロジックを中心とした設計を行っています。

```text
app/
├── Actions/
│   └── Event/
│       ├── ReservePendingAction.php   # 仮予約（在庫確保）ロジック
│       ├── ConfirmReservationAction.php # 予約確定（決済完了後）ロジック
│       └── CancelReservationAction.php  # キャンセルロジック
├── Http/
│   ├── Controllers/Api/TicketController.php
│   ├── Middleware/IdempotencyMiddleware.php # 冪等性担保
│   ├── Requests/                      # バリデーション
│   └── Resources/                     # レスポンス整形
├── Models/                            # Eloquent Model (Fat Model回避)
└── Policies/                          # 認可ロジック
```

---

## 🛡 Error Handling Design (エラーハンドリング設計)

ビジネスロジック由来のエラーを、PHP 8.1 Enums と Attributes を用いて管理する設計を採用しています。 ReservationError Enum にエラーコードと HTTP ステータスを集約し、型安全なエラーハンドリングを実現しています。

### アーキテクチャの特徴

1.  **定義の集約 (Centralized Definition):**
    全てのエラーコード、メッセージ、対応する HTTP ステータスコードを `ReservationError` Enum に集約しています。コードのあちこちにマジックナンバーや文字列リテラルが散らばるのを防ぎます。
2.  **属性の活用 (Attributes):**
    カスタム属性 `#[ErrorDetails]` を使用し、Enum のケースに対してメタデータ（メッセージとステータス）を宣言的に記述しています。
3.  **レスポンスの統一:**
    例外クラス `ReservationException` が Enum を受け取り、自動的に統一された JSON フォーマットでレスポンスを生成します。

### 実装例 (Code Example)

**1. Error Enum Definition:**

```php
enum ReservationError: string
{
    // メッセージとHTTPステータスをAttributeで紐付け
    #[ErrorDetails('満席です', 409)]
    case SEATS_FULL = 'seats_full';

    #[ErrorDetails('この予約を確定する権限がありません', 403)]
    case UNAUTHORIZED = 'unauthorized';
}
```

📚 API Documentation
Scribe を使用して API ドキュメントを自動生成しています。 エンドポイントの詳細、リクエスト/レスポンスのサンプル、エラーコード一覧を確認できます。

```bash
# ドキュメントの生成
php artisan scribe:generate
```

### 📊 Performance Benchmark (Evidence)

実際のデータ（10,000 件）を用いたエクスポート処理のベンチマーク結果です。
Eloquent のモデル生成（Hydration）を回避し、ストリーミング処理を行うことで、処理速度が **約 25 倍** 高速化しました。

**Run Benchmark:**

```bash
php artisan benchmark:export 10000
```

| Method                | Time (sec)   | Records | Performance          |
| :-------------------- | :----------- | :------ | :------------------- |
| **Normal (Eloquent)** | 0.2866 s     | 10,000  | 1.0x (Baseline)      |
| **Optimized (Query)** | **0.0116 s** | 10,000  | ⚡️ **24.8x Faster** |

### 🛠 Usage (Development)

このプロジェクトは Laravel Sail (Docker) 環境で動作します。

```bash
# コンテナの起動
./vendor/bin/sail up -d

# マイグレーション & シーディング
./vendor/bin/sail artisan migrate --seed

# アプリケーションへのアクセス
http://localhost
```
