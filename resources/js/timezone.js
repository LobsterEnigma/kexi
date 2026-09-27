function deviceTimezone() {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch { return ''; }
}

export function registerTimezone(Alpine) {
    Alpine.data('timezonePicker', config => ({
        value: config.value || 'UTC',
        device: deviceTimezone(),
        notice: '',
        init() { if (config.detect) this.useDevice(); },
        useDevice() {
            if (!this.device) { this.notice = '未能识别设备时区，请手动选择。'; return; }
            this.value = this.device;
            this.notice = '已使用当前设备时区，保存后生效。';
        },
    }));
    const preference = document.querySelector('meta[name="user-timezone"]');
    const url = document.querySelector('meta[name="user-timezone-url"]')?.content;
    const timezone = deviceTimezone();
    if (preference && !preference.content && url && timezone) {
        fetch(url, {
            method: 'PATCH', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ timezone, initialize: true }),
        }).then(response => response.ok ? response.json() : null).then(data => {
            if (data?.timezone) preference.content = data.timezone;
        }).catch(() => { /* The visible account picker remains available if detection cannot be saved. */ });
    }
}
