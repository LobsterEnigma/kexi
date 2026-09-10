export function registerPersonal(Alpine) {
    Alpine.data('personalEventForm', (config) => ({
        ...config,
        zoneNotice: '',
        previousStart: config.startLocal,
        get startDate() { return this.startLocal.slice(0, 10); },
        set startDate(value) { this.startLocal = value ? `${value}T${this.startLocal.slice(11) || '09:00'}` : ''; },
        get endDate() { return this.endLocal.slice(0, 10); },
        set endDate(value) { this.endLocal = value ? `${value}T${this.endLocal.slice(11) || '10:00'}` : ''; },
        init() {
            if (!this.detectTimezone) return;
            try {
                const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
                const zones = [...this.$refs.timezone.options].map(option => option.value);
                const zone = zones.find(zone => zone === detected) || zones.find(zone =>
                    new Intl.DateTimeFormat('en', { timeZone: zone }).resolvedOptions().timeZone === detected);
                if (!zone) throw new Error('Unsupported device timezone');
                this.timezone = zone;
                this.zoneNotice = '已自动使用设备时区，可按活动所在地修改。';
                const start = new Date();
                start.setMinutes(0, 0, 0);
                start.setHours(start.getHours() + 1);
                const end = new Date(start);
                end.setHours(end.getHours() + 1);
                const localValue = date => {
                    const pad = value => String(value).padStart(2, '0');
                    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                };
                this.startLocal = localValue(start);
                this.endLocal = localValue(end);
                this.previousStart = this.startLocal;
                this.$root.querySelectorAll('[name="weekdays[]"]').forEach(input => {
                    input.checked = Number(input.value) === (start.getDay() || 7);
                });
            } catch {
                this.zoneNotice = '未能识别设备时区，已使用课表默认时区，请确认。';
            }
        },
        startChanged() {
            if (this.startLocal && this.endLocal && this.endLocal < this.startLocal) {
                // Compare wall-clock values without introducing the device timezone or DST.
                const duration = Date.parse(`${this.endLocal}Z`) - Date.parse(`${this.previousStart}Z`);
                const offset = this.allDay ? Math.max(0, duration) : (duration > 0 && duration <= 604800000 ? duration : 3600000);
                const end = new Date(Date.parse(`${this.startLocal}Z`) + offset);
                if (!Number.isNaN(end.getTime())) this.endLocal = end.toISOString().slice(0, 16);
            }
            this.previousStart = this.startLocal;
        },
    }));
}
