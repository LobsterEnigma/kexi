export function registerAcademic(Alpine) {
    Alpine.data('academicWorkspace', (timetableUrl) => ({
        sidebarOpen: false,
        openDialog(dialog) {
            const url = new URL(timetableUrl, window.location.origin);
            url.searchParams.set('dialog', dialog);
            window.location.assign(url.href);
        },
        openSidebar() { this.sidebarOpen = true; this.$nextTick(() => this.$refs.navDrawer.querySelector('button').focus()); },
        closeSidebar() { if (!this.sidebarOpen) return; this.sidebarOpen = false; this.$refs.navToggle.focus(); },
        trapNavigation(event) {
            const items = [...this.$refs.navDrawer.querySelectorAll('a[href], button, select')].filter(el => el.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        },
    }));
    Alpine.data('academicReminders', (config) => ({
        open: false, items: [], loading: false, loaded: false, error: '', busy: null, timer: null, controller: null,
        init() {
            this.refresh();
            this.timer = window.setInterval(() => { if (!document.hidden) this.refresh(); }, 60000);
        },
        destroy() { window.clearInterval(this.timer); this.controller?.abort(); },
        async refresh() {
            if (this.loading) return;
            this.loading = true;
            this.controller = new AbortController();
            const timeout = window.setTimeout(() => this.controller?.abort(), 15000);
            try {
                const response = await fetch(config.list, {headers: {Accept: 'application/json'}, signal: this.controller.signal});
                if (!response.ok) throw new Error();
                const data = await response.json();
                this.items = data.items;
                this.loaded = true;
                this.error = '';
            } catch { this.error = '暂时无法检查提醒，请稍后重新打开。'; }
            finally { window.clearTimeout(timeout); this.loading = false; }
        },
        async acknowledge(key) {
            if (this.busy) return;
            this.busy = key;
            try {
                const response = await fetch(config.acknowledge, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify({key})});
                if (!response.ok) throw new Error();
                await this.refresh();
            } catch { this.error = '未能保存已读状态，请重试。'; }
            finally { this.busy = null; }
        },
    }));
}
