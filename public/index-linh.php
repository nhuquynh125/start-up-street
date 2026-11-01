<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Good Coffee — Player Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="shell">
    <header class="brand">
        <div class="bean">☕</div>
        <p class="eyebrow">PLAYER & ACCOUNT SYSTEM</p>
        <h1>GOOD COFFEE,<br><span>GREAT COFFEE</span></h1>
        <p class="sub">Đăng nhập để tiếp tục hành trình quán cà phê của bạn.</p>
    </header>

    <section id="authPanel" class="panel">
        <div class="tabs">
            <button class="tab active" id="showLogin">Đăng nhập</button>
            <button class="tab" id="showRegister">Đăng ký</button>
        </div>
        <form id="loginForm" class="form">
            <label>Tên đăng nhập<input name="username" required autocomplete="username" minlength="3"></label>
            <label>Mật khẩu<input name="password" type="password" required autocomplete="current-password"></label>
            <button class="primary" type="submit">Vào game</button>
        </form>
        <form id="registerForm" class="form hidden">
            <label>Tên đăng nhập<input name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" autocomplete="username"></label>
            <label>Email<input name="email" type="email" required autocomplete="email"></label>
            <label>Tên người chơi<input name="display_name" required maxlength="40"></label>
            <label>Mật khẩu (ít nhất 8 ký tự)<input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
            <button class="primary" type="submit">Tạo tài khoản</button>
        </form>
        <p id="message" class="message" role="status"></p>
    </section>

    <section id="playerPanel" class="panel hidden">
        <div class="player-top">
            <div class="avatar" id="avatarIcon">☕</div>
            <div><p class="eyebrow">HỒ SƠ NGƯỜI CHƠI</p><h2 id="displayName">Player</h2><p id="usernameText" class="muted"></p></div>
        </div>
        <div class="stats">
            <div><span>💰 Vốn</span><strong id="money">0</strong></div>
            <div><span>⭐ Level</span><strong id="level">1</strong></div>
            <div><span>💗 Uy tín</span><strong id="reputation">0</strong></div>
        </div>
        <form id="profileForm" class="form">
            <label>Tên hiển thị<input name="display_name" maxlength="40" required></label>
            <label>Avatar
                <select name="avatar"><option value="barista">☕ Barista</option><option value="cat">🐱 Mèo</option><option value="fox">🦊 Cáo</option><option value="bear">🐻 Gấu</option></select>
            </label>
            <button class="primary" type="submit">Lưu hồ sơ</button>
        </form>
        <button id="logoutButton" class="secondary">Đăng xuất</button>
        <p id="profileMessage" class="message" role="status"></p>
    </section>
    <footer>Demo học tập · Dữ liệu tài khoản lưu trong MySQL</footer>
</main>
<script src="app.js"></script>
</body>
</html>
