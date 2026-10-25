const API_BASE = '../api';
let csrfToken = '';

const $ = (id) => document.getElementById(id);
const authPanel = $('authPanel');
const playerPanel = $('playerPanel');
const loginForm = $('loginForm');
const registerForm = $('registerForm');

function showMessage(text, target = 'message', isError = true) {
    const el = $(target);
    el.textContent = text;
    el.style.color = isError ? '#a43d2c' : '#26734d';
}
async function api(path, method = 'GET', data = null) {
    const options = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (data !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-Token'] = csrfToken;
        options.body = JSON.stringify(data);
    }
    const response = await fetch(`${API_BASE}/${path}`, options);
    const result = await response.json().catch(() => ({ok:false,message:'Máy chủ trả về dữ liệu không hợp lệ.'}));
    if (!response.ok || !result.ok) throw new Error(result.message || 'Có lỗi xảy ra.');
    if (result.csrf_token) csrfToken = result.csrf_token;
    return result;
}
function setAuthMode(mode) {
    const isLogin = mode === 'login';
    loginForm.classList.toggle('hidden', !isLogin);
    registerForm.classList.toggle('hidden', isLogin);
    $('showLogin').classList.toggle('active', isLogin);
    $('showRegister').classList.toggle('active', !isLogin);
    showMessage('');
}
$('showLogin').addEventListener('click', () => setAuthMode('login'));
$('showRegister').addEventListener('click', () => setAuthMode('register'));

async function loadProfile() {
    const result = await api('me.php');
    const u = result.user;
    authPanel.classList.add('hidden');
    playerPanel.classList.remove('hidden');
    $('displayName').textContent = u.display_name;
    $('usernameText').textContent = '@' + u.username;
    $('money').textContent = Number(u.money).toLocaleString('vi-VN') + ' đ';
    $('level').textContent = u.level;
    $('reputation').textContent = u.reputation;
    $('profileForm').elements.display_name.value = u.display_name;
    $('profileForm').elements.avatar.value = u.avatar;
    const icons = {barista:'☕',cat:'🐱',fox:'🦊',bear:'🐻'};
    $('avatarIcon').textContent = icons[u.avatar] || '☕';
}
loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(loginForm);
    try {
        const result = await api('login.php', 'POST', {username:fd.get('username'),password:fd.get('password')});
        showMessage(result.message, 'message', false);
        await loadProfile();
    } catch (e) { showMessage(e.message); }
});
registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(registerForm);
    try {
        const result = await api('register.php', 'POST', {
            username:fd.get('username'), email:fd.get('email'),
            display_name:fd.get('display_name'), password:fd.get('password')
        });
        csrfToken = result.csrf_token || csrfToken;
        await loadProfile();
    } catch (e) { showMessage(e.message); }
});
$('profileForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.currentTarget);
    try {
        await api('update_profile.php', 'POST', {display_name:fd.get('display_name'),avatar:fd.get('avatar')});
        await loadProfile();
        showMessage('Đã lưu hồ sơ thành công.', 'profileMessage', false);
    } catch (e) { showMessage(e.message, 'profileMessage'); }
});
$('logoutButton').addEventListener('click', async () => {
    try {
        await api('logout.php', 'POST', {});
        csrfToken = '';
        playerPanel.classList.add('hidden');
        authPanel.classList.remove('hidden');
        loginForm.reset();
        registerForm.reset();
        setAuthMode('login');
        showMessage('Bạn đã đăng xuất.', 'message', false);
    } catch (e) { showMessage(e.message, 'profileMessage'); }
});

(async function init() {
    // First request establishes a CSRF token even when the visitor is not logged in.
    try {
        const r = await fetch(`${API_BASE}/csrf.php`, {credentials:'same-origin'});
        const data = await r.json();
        csrfToken = data.csrf_token || '';
    } catch (_) {}
    try { await loadProfile(); }
    catch (_) { authPanel.classList.remove('hidden'); playerPanel.classList.add('hidden'); }
})();
