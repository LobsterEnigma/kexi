import './bootstrap';
import {
    downloadTimetablePng,
    exportThemeOptions,
    renderTimetableExport,
} from './timetable-export';

import Alpine from 'alpinejs';
import {
    BookOpen,
    CalendarDays,
    CalendarRange,
    CalendarX2,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Circle,
    CircleCheckBig,
    Clock3,
    Copy,
    Download,
    ExternalLink,
    Eye,
    EyeOff,
    FileText,
    Link2,
    LogOut,
    MapPin,
    Menu,
    MoreHorizontal,
    PanelLeftOpen,
    PanelRightOpen,
    Palette,
    Pencil,
    Plus,
    RefreshCw,
    RotateCcw,
    Settings,
    Share2,
    SlidersHorizontal,
    Trash2,
    TriangleAlert,
    UserCog,
    UserRound,
    X,
    createIcons,
} from 'lucide';

const lucideIcons = {
    BookOpen,
    CalendarDays,
    CalendarRange,
    CalendarX2,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Circle,
    CircleCheckBig,
    Clock3,
    Copy,
    Download,
    ExternalLink,
    Eye,
    EyeOff,
    FileText,
    Link2,
    LogOut,
    MapPin,
    Menu,
    MoreHorizontal,
    PanelLeftOpen,
    PanelRightOpen,
    Palette,
    Pencil,
    Plus,
    RefreshCw,
    RotateCcw,
    Settings,
    Share2,
    SlidersHorizontal,
    Trash2,
    TriangleAlert,
    UserCog,
    UserRound,
    X,
};

const refreshIcons = (root = document) => {
    createIcons({
        icons: lucideIcons,
        root,
        inTemplates: true,
        attrs: {
            'stroke-width': 1.8,
        },
    });
};

const blankMeeting = (weekCount = 18) => ({
    _key: `${Date.now()}-${Math.random()}`,
    _expanded: true,
    label: '',
    teacher: '',
    weekday: 1,
    starts_at: '08:00',
    ends_at: '09:45',
    location: '',
    week_mode: 'all',
    start_week: 1,
    end_week: weekCount,
    specific_weeks: '',
});

const parseIsoDateAsUtc = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

    if (!match) return null;

    return Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
};

const formatUtcDate = (timestamp) => {
    const date = new Date(timestamp);

    return [
        date.getUTCFullYear(),
        String(date.getUTCMonth() + 1).padStart(2, '0'),
        String(date.getUTCDate()).padStart(2, '0'),
    ].join('-');
};

window.refreshIcons = refreshIcons;

window.Alpine = Alpine;

Alpine.data('timetableTermRange', (config = {}) => ({
    startDate: config.startDate || '',
    endDate: config.endDate || '',
    weekCount: Number(config.weekCount || 18),

    init() {
        if (!this.endDate && this.startDate && this.weekCount) {
            this.syncEndFromWeeks();
        }
    },

    startChanged() {
        const start = parseIsoDateAsUtc(this.startDate);
        const end = parseIsoDateAsUtc(this.endDate);

        if (start !== null && end !== null && end >= start) {
            this.syncWeeksFromEnd();
        } else {
            this.syncEndFromWeeks();
        }
    },

    syncWeeksFromEnd() {
        const start = parseIsoDateAsUtc(this.startDate);
        const end = parseIsoDateAsUtc(this.endDate);

        if (start === null || end === null || end < start) return;

        const days = Math.floor((end - start) / 86400000) + 1;
        this.weekCount = Math.min(30, Math.max(1, Math.ceil(days / 7)));
    },

    syncEndFromWeeks() {
        const start = parseIsoDateAsUtc(this.startDate);
        const weeks = Math.min(30, Math.max(1, Number(this.weekCount || 1)));

        if (start === null) return;

        this.weekCount = weeks;
        this.endDate = formatUtcDate(start + ((weeks * 7) - 1) * 86400000);
    },
}));

Alpine.data('timetableWorkbench', (config = {}) => ({
    sidebarOpen: false,
    diagnosticsOpen: false,
    mobileActionsOpen: false,
    modal: null,
    diagnosticFilter: 'all',
    expandedDiagnostic: null,
    shareCopied: false,
    exportData: config.exportData || null,
    exportTheme: 'ocean',
    exportThemes: exportThemeOptions,
    exportReady: false,
    exportingImage: false,
    weekCount: Number(config.weekCount || 18),
    termStartDate: config.termStartDate || null,
    timetableUrl: config.timetableUrl || window.location.pathname,
    createMeetings: (config.createMeetings?.length
        ? config.createMeetings
        : [blankMeeting(Number(config.weekCount || 18))]
    ).map((meeting, index) => ({
        ...blankMeeting(Number(config.weekCount || 18)),
        ...meeting,
        _expanded: index === 0,
    })),
    courseEditor: {
        action: '',
        destroyAction: '',
        archiveAction: '',
        name: '',
        code: '',
        notes: '',
        occurrence: null,
        meetings: [blankMeeting(Number(config.weekCount || 18))],
    },
    cancellationEditor: {
        syncAction: '',
        summary: '',
        weeks: [],
        options: [],
    },

    init() {
        const syncBodyLock = () => {
            document.body.classList.toggle(
                'overflow-hidden',
                Boolean(this.modal || this.sidebarOpen || this.diagnosticsOpen),
            );
        };

        this.$watch('modal', syncBodyLock);
        this.$watch('sidebarOpen', syncBodyLock);
        this.$watch('diagnosticsOpen', syncBodyLock);

        this.$nextTick(() => {
            refreshIcons();

            if (config.initialModal) {
                this.openDialog(config.initialModal);
            }
        });
    },

    refreshIcons() {
        this.$nextTick(() => refreshIcons());
    },

    openDialog(name) {
        this.mobileActionsOpen = false;
        this.modal = name;
        this.refreshIcons();

        this.$nextTick(() => {
            const dialog = document.querySelector(`[data-workbench-dialog="${name}"]`);
            dialog?.querySelector('.wb-modal-body')?.scrollTo({ top: 0 });
            dialog?.querySelector('[autofocus], input, select, textarea, button')?.focus();
        });
    },

    closeDialog() {
        this.modal = null;
        this.shareCopied = false;
    },

    closeOverlays() {
        this.sidebarOpen = false;
        this.diagnosticsOpen = false;
        this.mobileActionsOpen = false;
    },

    newMeeting() {
        return blankMeeting(this.weekCount);
    },

    addMeeting(target = 'create') {
        const meetings = target === 'course'
            ? this.courseEditor.meetings
            : this.createMeetings;

        meetings.forEach((meeting) => {
            meeting._expanded = false;
        });

        if (target === 'course') {
            this.courseEditor.meetings.push(this.newMeeting());
        } else {
            this.createMeetings.push(this.newMeeting());
        }

        this.$nextTick(() => {
            refreshIcons();

            const dialogName = target === 'course' ? 'course-editor' : 'course-create';
            const dialog = document.querySelector(`[data-workbench-dialog="${dialogName}"]`);
            const editors = dialog?.querySelectorAll('[data-meeting-editor]');
            const editor = editors?.[editors.length - 1];

            editor?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            editor?.querySelector('input, select, textarea')?.focus({ preventScroll: true });
        });
    },

    removeMeeting(target, index) {
        const meetings = target === 'course'
            ? this.courseEditor.meetings
            : this.createMeetings;

        if (meetings.length > 1) {
            meetings.splice(index, 1);

            if (!meetings.some((meeting) => meeting._expanded)) {
                meetings[Math.min(index, meetings.length - 1)]._expanded = true;
            }
        }
    },

    toggleMeeting(target, index) {
        const meetings = target === 'course'
            ? this.courseEditor.meetings
            : this.createMeetings;
        const shouldExpand = !meetings[index]._expanded;

        meetings.forEach((meeting) => {
            meeting._expanded = false;
        });
        meetings[index]._expanded = shouldExpand;
        this.refreshIcons();

        if (shouldExpand) {
            this.$nextTick(() => {
                const dialogName = target === 'course' ? 'course-editor' : 'course-create';

                document
                    .querySelector(`[data-workbench-dialog="${dialogName}"] [data-meeting-editor][data-meeting-index="${index}"]`)
                    ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }
    },

    meetingSummary(meeting) {
        const weekday = ['', '周一', '周二', '周三', '周四', '周五', '周六', '周日'][Number(meeting.weekday)] || '未选星期';
        const weekMode = {
            all: '每周',
            odd: '单周',
            even: '双周',
            specific: '指定周',
        }[meeting.week_mode] || '';

        return [
            weekday,
            `${meeting.starts_at || '--:--'}–${meeting.ends_at || '--:--'}`,
            meeting.location,
            weekMode,
        ].filter(Boolean).join(' · ');
    },

    openCourse(payload) {
        const meetings = payload.meetings?.length ? payload.meetings : [this.newMeeting()];
        const expandSingleMeeting = meetings.length === 1;

        this.courseEditor = {
            action: payload.action,
            destroyAction: payload.destroyAction,
            archiveAction: payload.archiveAction,
            name: payload.name || '',
            code: payload.code || '',
            notes: payload.notes || '',
            occurrence: payload.occurrence || null,
            meetings: meetings.map((meeting, index) => ({
                ...this.newMeeting(),
                ...meeting,
                _expanded: expandSingleMeeting && index === 0,
            })),
        };

        this.openDialog('course-editor');
    },

    openCancellationManager() {
        const occurrence = this.courseEditor.occurrence;

        if (!occurrence) return;

        this.cancellationEditor = {
            syncAction: occurrence.syncAction,
            summary: occurrence.summary,
            weeks: [...(occurrence.canceledWeeks || [])].map(String),
            options: occurrence.occurringWeeks || [],
        };
        this.openDialog('occurrence-cancellations');
    },

    prepareExport() {
        if (!this.exportData) return;

        this.exportReady = false;
        this.openDialog('export-image');
        this.$nextTick(() => this.renderExportPreview());
    },

    async renderExportPreview() {
        const canvas = this.$refs.exportCanvas;
        if (!canvas || !this.exportData) return;

        this.exportReady = false;
        await renderTimetableExport(canvas, this.exportData, this.exportTheme);
        this.exportReady = true;
    },

    setExportTheme(theme) {
        if (!this.exportThemes.some((option) => option.key === theme)) return;

        this.exportTheme = theme;
        this.renderExportPreview();
    },

    async downloadExportImage() {
        const canvas = this.$refs.exportCanvas;
        if (!canvas || !this.exportData || this.exportingImage) return;

        this.exportingImage = true;
        try {
            await renderTimetableExport(canvas, this.exportData, this.exportTheme);
            await downloadTimetablePng(canvas, this.exportData.filename);
        } finally {
            this.exportingImage = false;
        }
    },

    selectDiagnosticFilter(filter, trigger = null, count = 1) {
        if (filter !== 'all' && Number(count) < 1) return;

        this.diagnosticFilter = this.diagnosticFilter === filter && filter !== 'all'
            ? 'all'
            : filter;
        this.expandedDiagnostic = null;

        this.$nextTick(() => {
            trigger
                ?.closest('[data-diagnostics-panel]')
                ?.querySelector('[data-diagnostic-list]')
                ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            refreshIcons();
        });
    },

    toggleDiagnostic(id) {
        this.expandedDiagnostic = this.expandedDiagnostic === id ? null : id;
    },

    setDiagnosticFilter(filter) {
        this.diagnosticFilter = filter;
        this.diagnosticsOpen = true;
        this.refreshIcons();
    },

    goToDate(value) {
        const selectedDate = parseIsoDateAsUtc(value);
        const termStartDate = parseIsoDateAsUtc(this.termStartDate);

        if (selectedDate === null || termStartDate === null) return;

        const dayOffset = Math.floor((selectedDate - termStartDate) / 86400000);
        const week = Math.min(this.weekCount, Math.max(1, Math.floor(dayOffset / 7) + 1));
        const url = new URL(this.timetableUrl, window.location.origin);

        url.searchParams.set('view', 'week');
        url.searchParams.set('week', String(week));
        window.location.assign(url.toString());
    },

    goToMonth(value) {
        if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(value || '')) return;

        const url = new URL(this.timetableUrl, window.location.origin);

        url.searchParams.set('view', 'month');
        url.searchParams.set('month', value);
        window.location.assign(url.toString());
    },

    async copyShare(url) {
        if (!url) return;

        try {
            await navigator.clipboard.writeText(url);
        } catch (error) {
            const input = document.createElement('textarea');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        }

        this.shareCopied = true;
        window.setTimeout(() => {
            this.shareCopied = false;
        }, 1800);
    },
}));

Alpine.start();

window.requestAnimationFrame(() => refreshIcons());
