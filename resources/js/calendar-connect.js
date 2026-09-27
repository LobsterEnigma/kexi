export function registerCalendarConnections(Alpine) {
    Alpine.data('calendarUpload', () => ({
        fileName: '', fileSize: '', error: '', dragging: false,
        init() {
            try { this.$refs.fallbackTimezone.value = document.querySelector('meta[name="user-timezone"]')?.content || Intl.DateTimeFormat().resolvedOptions().timeZone || this.$refs.fallbackTimezone.value; } catch { /* Keep timetable timezone. */ }
        },
        select(file) {
            this.error = ''; this.fileName = ''; this.fileSize = '';
            if (!file) return;
            if (!/\.ics$/i.test(file.name) || file.size > 1048576 || !file.size) {
                this.error = '请选择不超过 1 MB 的非空 .ics 日历文件。';
                this.$refs.file.value = '';
                return;
            }
            this.fileName = file.name;
            this.fileSize = file.size < 1024 ? `${file.size} B` : `${Math.ceil(file.size / 1024)} KB`;
        },
        drop(event) {
            this.dragging = false;
            const files = event.dataTransfer?.files;
            if (!files?.length) return;
            if (files.length !== 1) { this.error = '请一次选择一个日历文件。'; return; }
            this.$refs.file.files = files;
            this.select(files[0]);
        },
    }));
    Alpine.data('calendarImport', () => ({
        count: 0,
        notice: '',
        init() { this.$nextTick(() => this.refresh()); },
        refresh() { this.count = this.$root.querySelectorAll('input[name="selected[]"]:checked').length; },
        selectAll(value) {
            this.$root.querySelectorAll('input[name="selected[]"]:not(:disabled)').forEach(input => { input.checked = value; });
            this.refresh();
        },
        setKind(kind) {
            if (!kind) return;
            let changed = 0;
            this.$root.querySelectorAll('input[name="selected[]"]:checked').forEach(input => {
                const select = input.closest('.calendar-preview-item').querySelector('select');
                const option = Array.from(select.options).find(item => item.value === kind);
                if (option && !option.disabled) { select.value = kind; changed++; }
            });
            this.notice = `已调整 ${changed} 项；不符合此类型的安排保留原选择。`;
        },
    }));
}
