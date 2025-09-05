<?php

session_start();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>サービス一覧 - ABC Corporation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
        .header { background: #2c3e50; color: white; padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .nav h1 { font-size: 1.8rem; }
        .nav ul { display: flex; list-style: none; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px; transition: background 0.3s; }
        .nav a:hover { background: #34495e; }
        .main { padding: 40px 0; background: #f8f9fa; min-height: 80vh; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin-top: 40px; }
        .service-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .service-icon { font-size: 3rem; margin-bottom: 20px; }
        .service-title { color: #2c3e50; margin-bottom: 15px; font-size: 1.5rem; }
        .service-desc { color: #666; line-height: 1.6; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 0; text-align: center; }
        .hero h2 { font-size: 2.5rem; margin-bottom: 20px; }
        .hero p { font-size: 1.2rem; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <h1>ABC Corporation</h1>
                <ul>
                    <li><a href="index.php">ホーム</a></li>
                    <li><a href="services.php">サービス</a></li>
                    <li><a href="contact.php">お問い合わせ</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php">ダッシュボード</a></li>
                        <li><a href="logout.php">ログアウト</a></li>
                    <?php else: ?>
                        <li><a href="login.php">ログイン</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h2>私たちのサービス</h2>
            <p>包括的なビジネスソリューションをご提供します</p>
        </div>
    </section>

    <main class="main">
        <div class="container">
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">🏢</div>
                    <h3 class="service-title">企業管理システム</h3>
                    <p class="service-desc">従業員の管理、プロジェクトの追跡、財務データの分析など、企業運営に必要な全ての機能を統合したシステムです。</p>
                </div>

                <div class="service-card">
                    <div class="service-icon">🔐</div>
                    <h3 class="service-title">セキュリティソリューション</h3>
                    <p class="service-desc">最新のセキュリティ技術により、お客様の大切なデータを保護します。多層防御によりサイバー攻撃から企業を守ります。</p>
                </div>

                <div class="service-card">
                    <div class="service-icon">📊</div>
                    <h3 class="service-title">データ分析</h3>
                    <p class="service-desc">ビッグデータ解析により、ビジネスインサイトを提供します。意思決定をデータに基づいて行うことができます。</p>
                </div>

                <div class="service-card">
                    <div class="service-icon">☁️</div>
                    <h3 class="service-title">クラウドサービス</h3>
                    <p class="service-desc">スケーラブルなクラウドインフラストラクチャにより、必要に応じてリソースを調整できます。</p>
                </div>

                <div class="service-card">
                    <div class="service-icon">📱</div>
                    <h3 class="service-title">モバイルアプリ開発</h3>
                    <p class="service-desc">iOS、Android対応のネイティブアプリから、クロスプラットフォームアプリまで幅広く対応します。</p>
                </div>

                <div class="service-card">
                    <div class="service-icon">🎯</div>
                    <h3 class="service-title">コンサルティング</h3>
                    <p class="service-desc">IT戦略の立案から実行まで、経験豊富なコンサルタントがお客様のビジネス成長をサポートします。</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>