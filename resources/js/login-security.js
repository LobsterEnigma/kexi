let turnstileLoader;
function loadTurnstile() {
    if (window.turnstile) return Promise.resolve(window.turnstile);
    if (!turnstileLoader) turnstileLoader = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.onload = () => window.turnstile ? resolve(window.turnstile) : reject(new Error('Unavailable'));
        script.onerror = () => { script.remove(); turnstileLoader = null; reject(new Error('Unavailable')); };
        document.head.append(script);
    });
    return turnstileLoader;
}
const encode = buffer => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)).buffer;
async function post(url, data) {
    const response = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {
        'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    }, body: JSON.stringify(data)});
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || (response.status === 419 ? '页面已过期，请刷新后再试。' : response.status === 429 ? '操作太频繁，请稍后再试。' : '操作未完成，请刷新页面重试。'));
    return result;
}
export function registerLoginSecurity(Alpine) {
    Alpine.data('turnstileWidget', config => ({
        token: '', message: '正在加载人机验证…', widget: null,
        async init() { await this.render(); },
        async render() {
            this.token = '';
            try {
                const api = await loadTurnstile();
                if (this.widget !== null) { api.reset(this.widget); this.message = '请完成人机验证。'; return; }
                this.widget = api.render(this.$refs.widget, {
                    sitekey: config.siteKey, action: config.action, theme: 'light', language: 'zh-cn',
                    size: this.$el.clientWidth < 300 ? 'compact' : 'flexible', 'response-field': false,
                    callback: token => { this.token = token; this.message = '验证已完成'; },
                    'expired-callback': () => { this.token = ''; this.message = '验证已过期，请重新验证。'; },
                    'error-callback': () => { this.token = ''; this.message = '验证暂不可用，请检查网络后重试。'; },
                });
                this.message = '请完成人机验证。';
            } catch { this.message = '验证组件未能加载，请检查网络后重试。'; }
        },
        destroy() { if (this.widget !== null && window.turnstile) window.turnstile.remove(this.widget); },
    }));
    Alpine.data('passkeyAction', config => ({
        busy: false, error: '', supported: !!(window.isSecureContext && window.PublicKeyCredential && navigator.credentials),
        async run() {
            if (this.busy) return;
            this.error = ''; this.busy = true;
            try {
                if (!this.supported) throw new Error('此浏览器或连接不支持通行密钥，请使用 HTTPS 和支持的浏览器。');
                const creating = config.mode === 'register';
                const payload = creating ? {name: this.$refs.keyName.value, password: this.$refs.keyPassword.value} : {
                    'cf-turnstile-response': this.$el.querySelector('[name="cf-turnstile-response"]')?.value || '',
                    remember: this.$el.querySelector('[name="remember"]')?.checked || false,
                };
                const options = await post(config.options, payload);
                if (creating) this.$refs.keyPassword.value = '';
                const publicKey = options.publicKey;
                publicKey.challenge = decode(publicKey.challenge);
                if (creating) {
                    publicKey.user.id = decode(publicKey.user.id);
                    publicKey.excludeCredentials = (publicKey.excludeCredentials || []).map(item => ({...item, id: decode(item.id)}));
                } else if (publicKey.allowCredentials) {
                    publicKey.allowCredentials = publicKey.allowCredentials.map(item => ({...item, id: decode(item.id)}));
                }
                const credential = await navigator.credentials[creating ? 'create' : 'get']({publicKey});
                if (!credential) throw new Error('未取得通行密钥，请重新尝试。');
                const data = {challenge_id: options.challenge_id, rawId: encode(credential.rawId), clientDataJSON: encode(credential.response.clientDataJSON)};
                if (creating) data.attestationObject = encode(credential.response.attestationObject);
                else Object.assign(data, {authenticatorData: encode(credential.response.authenticatorData), signature: encode(credential.response.signature), userHandle: credential.response.userHandle ? encode(credential.response.userHandle) : ''});
                const result = await post(config.finish, data);
                if (creating) window.location.assign(config.done);
                else window.location.assign(result.redirect);
            } catch (error) {
                this.error = ['NotAllowedError', 'AbortError'].includes(error.name) ? '操作已取消或超时，你可以重试或使用密码登录。'
                    : error.name === 'InvalidStateError' ? '这个通行密钥已添加，请使用其他设备或密钥。'
                    : error.name === 'SecurityError' ? '通行密钥域名不匹配，请从站点的正式网址打开。' : error.message;
            } finally {
                this.busy = false;
                window.dispatchEvent(new CustomEvent('turnstile-reset'));
            }
        },
    }));
}
