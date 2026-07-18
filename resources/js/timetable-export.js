const FONT_STACK = 'Inter, "PingFang SC", "Microsoft YaHei", Arial, sans-serif';

const THEMES = {
    ocean: {
        key: 'ocean',
        label: '云杉蓝',
        accent: '#2563eb',
        accentSoft: '#eaf1ff',
        background: '#f3f6fb',
        header: '#102a43',
        weekend: '#f7f9fc',
    },
    violet: {
        key: 'violet',
        label: '暮光紫',
        accent: '#6d5bd0',
        accentSoft: '#f0edff',
        background: '#f6f4fb',
        header: '#302657',
        weekend: '#f8f6fc',
    },
    amber: {
        key: 'amber',
        label: '暖阳橙',
        accent: '#c76b24',
        accentSoft: '#fff0e4',
        background: '#faf6f1',
        header: '#4a2e1d',
        weekend: '#fcf8f4',
    },
};

const COURSE_TONES = [
    { accent: '#2f67c7', border: '#b7cdf3', surface: '#edf4ff', text: '#1f4f91' },
    { accent: '#138a7b', border: '#a7ddd3', surface: '#eaf8f5', text: '#155b52' },
    { accent: '#7257cf', border: '#cbbef2', surface: '#f3f0ff', text: '#4b3696' },
    { accent: '#bd4f76', border: '#efbfd0', surface: '#fff0f5', text: '#7b2c49' },
    { accent: '#247ba0', border: '#b4d8e7', surface: '#edf8fc', text: '#225c75' },
    { accent: '#c06135', border: '#edc6b2', surface: '#fff3ec', text: '#7f3c20' },
];

export const exportThemeOptions = Object.values(THEMES).map(({ key, label, accent, accentSoft }) => ({
    key,
    label,
    accent,
    accentSoft,
}));

const roundedPath = (ctx, x, y, width, height, radius = 18) => {
    const safeRadius = Math.min(radius, width / 2, height / 2);

    ctx.beginPath();
    ctx.roundRect(x, y, width, height, safeRadius);
};

const fillRoundRect = (ctx, x, y, width, height, radius, fill) => {
    roundedPath(ctx, x, y, width, height, radius);
    ctx.fillStyle = fill;
    ctx.fill();
};

const strokeRoundRect = (ctx, x, y, width, height, radius, stroke, lineWidth = 2) => {
    roundedPath(ctx, x, y, width, height, radius);
    ctx.strokeStyle = stroke;
    ctx.lineWidth = lineWidth;
    ctx.stroke();
};

const setFont = (ctx, size, weight = 400) => {
    ctx.font = `${weight} ${size}px ${FONT_STACK}`;
};

const fitText = (ctx, value, maxWidth) => {
    const text = String(value || '');

    if (ctx.measureText(text).width <= maxWidth) return text;

    let shortened = text;
    while (shortened.length > 1 && ctx.measureText(`${shortened}…`).width > maxWidth) {
        shortened = shortened.slice(0, -1);
    }

    return `${shortened}…`;
};

const wrapText = (ctx, value, maxWidth, maxLines = 2) => {
    const text = String(value || '').trim();
    if (!text) return [];

    const lines = [];
    let current = '';

    for (const character of text) {
        const candidate = `${current}${character}`;
        if (current && ctx.measureText(candidate).width > maxWidth) {
            lines.push(current);
            current = character;
            if (lines.length === maxLines - 1) break;
        } else {
            current = candidate;
        }
    }

    const consumed = lines.join('').length + current.length;
    if (current && lines.length < maxLines) {
        lines.push(consumed < text.length ? fitText(ctx, text.slice(lines.join('').length), maxWidth) : current);
    }

    return lines.slice(0, maxLines);
};

const drawBrandMark = (ctx, x, y, size, theme) => {
    fillRoundRect(ctx, x, y, size, size, 16, theme.accent);
    ctx.save();
    ctx.translate(x + size * 0.22, y + size * 0.22);
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = Math.max(4, size * 0.055);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.roundRect(0, size * 0.08, size * 0.56, size * 0.54, size * 0.06);
    ctx.moveTo(size * 0.14, 0);
    ctx.lineTo(size * 0.14, size * 0.18);
    ctx.moveTo(size * 0.42, 0);
    ctx.lineTo(size * 0.42, size * 0.18);
    ctx.moveTo(0, size * 0.24);
    ctx.lineTo(size * 0.56, size * 0.24);
    ctx.moveTo(size * 0.13, size * 0.38);
    ctx.lineTo(size * 0.24, size * 0.38);
    ctx.moveTo(size * 0.32, size * 0.38);
    ctx.lineTo(size * 0.43, size * 0.38);
    ctx.stroke();
    ctx.restore();
};

const drawBadge = (ctx, text, x, y, options = {}) => {
    const fontSize = options.fontSize || 18;
    const paddingX = options.paddingX || 14;
    const height = options.height || 36;

    setFont(ctx, fontSize, 700);
    const width = ctx.measureText(text).width + paddingX * 2;
    fillRoundRect(ctx, x, y, width, height, height / 2, options.background || '#eef2f6');
    ctx.fillStyle = options.color || '#475467';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, x + paddingX, y + height / 2 + 1);

    return width;
};

const statusAppearance = (item) => {
    if (item.status === 'conflict') {
        return { accent: '#d92d20', border: '#f2b8b4', surface: '#fff4f3', text: '#7a271a', label: '冲突' };
    }
    if (item.status === 'canceled') {
        return { accent: '#7b8794', border: '#b8c0ca', surface: '#f2f4f7', text: '#5d6875', label: '已取消' };
    }

    const tone = COURSE_TONES[Math.abs(Number(item.tone || 0)) % COURSE_TONES.length];
    return {
        ...tone,
        label: item.status === 'near' ? '临近' : '',
    };
};

const drawHeader = (ctx, data, theme, width, margin, height) => {
    const cardY = 54;
    const cardHeight = height - 70;

    fillRoundRect(ctx, margin, cardY, width - margin * 2, cardHeight, 28, '#ffffff');
    ctx.fillStyle = theme.accent;
    ctx.fillRect(margin, cardY, 14, cardHeight);
    drawBrandMark(ctx, margin + 48, cardY + 42, 82, theme);

    const titleX = margin + 158;
    ctx.fillStyle = theme.header;
    ctx.textBaseline = 'alphabetic';
    setFont(ctx, 52, 800);
    ctx.fillText(fitText(ctx, data.timetableName || '我的课表', 1120), titleX, cardY + 91);

    ctx.fillStyle = '#667085';
    setFont(ctx, 25, 500);
    const subtitle = [data.termName, data.label].filter(Boolean).join(' · ');
    ctx.fillText(fitText(ctx, subtitle || data.siteName, 1160), titleX, cardY + 139);

    const rightX = width - margin - 520;
    ctx.fillStyle = theme.header;
    ctx.textAlign = 'right';
    setFont(ctx, 30, 700);
    ctx.fillText(data.dateRange || data.label || '', width - margin - 48, cardY + 83);
    ctx.fillStyle = '#7a8594';
    setFont(ctx, 20, 500);
    ctx.fillText(data.view === 'month' ? 'MONTHLY CALENDAR' : 'WEEKLY SCHEDULE', width - margin - 48, cardY + 126);
    ctx.textAlign = 'left';

    if (data.view === 'week') {
        const chipY = cardY + 162;
        let chipX = rightX;
        const chips = [
            `${data.summary?.courseCount || 0} 节课程`,
            data.summary?.conflicts ? `${data.summary.conflicts} 处冲突` : '无冲突',
            data.summary?.canceled ? `${data.summary.canceled} 次取消` : null,
        ].filter(Boolean);

        for (const chip of chips) {
            chipX += drawBadge(ctx, chip, chipX, chipY, {
                background: theme.accentSoft,
                color: theme.header,
                fontSize: 18,
                height: 36,
            }) + 10;
        }
    }
};

const drawFooter = (ctx, data, theme, width, height, margin) => {
    ctx.fillStyle = '#7a8594';
    ctx.textBaseline = 'middle';
    setFont(ctx, 19, 500);
    ctx.fillText(`由 ${data.siteName || '课隙'} 生成`, margin, height - 48);

    const generatedAt = new Intl.DateTimeFormat('zh-CN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date());
    ctx.fillStyle = theme.accent;
    ctx.textAlign = 'right';
    ctx.fillText(generatedAt, width - margin, height - 48);
    ctx.textAlign = 'left';
};

const drawWeekEvent = (ctx, item, geometry) => {
    const { x, y, width, height } = geometry;
    const appearance = statusAppearance(item);
    const radius = Math.min(16, height / 4);

    ctx.save();
    if (item.status === 'canceled') ctx.setLineDash([11, 8]);
    fillRoundRect(ctx, x, y, width, height, radius, appearance.surface);
    strokeRoundRect(ctx, x, y, width, height, radius, appearance.border, 2.5);
    ctx.setLineDash([]);
    ctx.fillStyle = appearance.accent;
    ctx.fillRect(x, y + radius, 8, Math.max(0, height - radius * 2));

    roundedPath(ctx, x, y, width, height, radius);
    ctx.clip();

    const paddingX = 22;
    const textX = x + paddingX;
    const textWidth = Math.max(20, width - paddingX * 2);
    const compact = height < 76;
    const title = compact && item.code ? item.code : item.name;

    ctx.fillStyle = appearance.text;
    ctx.textBaseline = 'top';
    setFont(ctx, compact ? 22 : 25, 800);
    const titleLines = wrapText(ctx, title, textWidth - (appearance.label ? 80 : 0), compact ? 1 : 2);
    titleLines.forEach((line, index) => ctx.fillText(line, textX, y + 15 + index * 31));

    if (appearance.label && width > 150) {
        setFont(ctx, 15, 700);
        const badgeWidth = ctx.measureText(appearance.label).width + 20;
        fillRoundRect(ctx, x + width - badgeWidth - 12, y + 12, badgeWidth, 28, 14, '#ffffffcc');
        ctx.fillStyle = appearance.accent;
        ctx.textBaseline = 'middle';
        ctx.fillText(appearance.label, x + width - badgeWidth - 2, y + 26);
    }

    if (height >= 62) {
        ctx.fillStyle = appearance.text;
        ctx.globalAlpha = 0.78;
        setFont(ctx, 18, 600);
        ctx.textBaseline = 'bottom';
        ctx.fillText(fitText(ctx, item.time, textWidth), textX, y + height - 13);
    }

    if (height >= 118 && (item.location || item.teacher)) {
        ctx.globalAlpha = 0.68;
        setFont(ctx, 17, 500);
        ctx.fillText(fitText(ctx, item.location || item.teacher, textWidth), textX, y + height - 39);
    }

    ctx.restore();
};

const drawWeekExport = (canvas, data, theme) => {
    const width = 2400;
    const height = 1600;
    const margin = 84;
    const headerHeight = 270;
    const gridY = 300;
    const gridHeight = 1210;
    const gridWidth = width - margin * 2;
    const dayHeaderHeight = 78;
    const timeWidth = 152;
    const dayWidth = (gridWidth - timeWidth) / 7;
    const contentY = gridY + dayHeaderHeight;
    const contentHeight = gridHeight - dayHeaderHeight;
    const dayStart = Number(data.dayStart || 480);
    const dayEnd = Math.max(dayStart + 60, Number(data.dayEnd || 1320));
    const minuteScale = contentHeight / (dayEnd - dayStart);
    const ctx = canvas.getContext('2d');

    canvas.width = width;
    canvas.height = height;
    ctx.fillStyle = theme.background;
    ctx.fillRect(0, 0, width, height);
    drawHeader(ctx, data, theme, width, margin, headerHeight);

    fillRoundRect(ctx, margin, gridY, gridWidth, gridHeight, 24, '#ffffff');
    ctx.save();
    roundedPath(ctx, margin, gridY, gridWidth, gridHeight, 24);
    ctx.clip();

    for (let day = 0; day < 7; day += 1) {
        const x = margin + timeWidth + day * dayWidth;
        if (day >= 5) {
            ctx.fillStyle = theme.weekend;
            ctx.fillRect(x, gridY, dayWidth, gridHeight);
        }

        ctx.fillStyle = day === 0 ? theme.accentSoft : '#f8fafc';
        ctx.fillRect(x, gridY, dayWidth, dayHeaderHeight);
        ctx.strokeStyle = '#d9e1ea';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x, gridY);
        ctx.lineTo(x, gridY + gridHeight);
        ctx.stroke();

        ctx.fillStyle = day === 0 ? theme.accent : '#344054';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        setFont(ctx, 24, 800);
        ctx.fillText(data.weekdays?.[day] || '', x + dayWidth / 2, gridY + dayHeaderHeight / 2 + 1);
    }
    ctx.textAlign = 'left';

    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(margin, gridY, timeWidth, dayHeaderHeight);
    ctx.fillStyle = '#667085';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    setFont(ctx, 20, 700);
    ctx.fillText('时间', margin + timeWidth / 2, gridY + dayHeaderHeight / 2 + 1);
    ctx.textAlign = 'left';

    for (let minute = dayStart; minute <= dayEnd; minute += 30) {
        const y = contentY + (minute - dayStart) * minuteScale;
        const isHour = minute % 60 === 0;
        ctx.strokeStyle = isHour ? '#d5dde7' : '#edf0f4';
        ctx.lineWidth = isHour ? 2 : 1;
        ctx.beginPath();
        ctx.moveTo(margin, y);
        ctx.lineTo(margin + gridWidth, y);
        ctx.stroke();

        if (isHour && minute < dayEnd) {
            ctx.fillStyle = '#667085';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'top';
            setFont(ctx, 19, 500);
            const label = `${String(Math.floor(minute / 60)).padStart(2, '0')}:00`;
            ctx.fillText(label, margin + timeWidth - 22, y + 10);
        }
    }
    ctx.textAlign = 'left';

    for (const item of data.items || []) {
        const laneCount = Math.max(1, Number(item.laneCount || 1));
        const lane = Math.max(0, Number(item.lane || 0));
        const dayX = margin + timeWidth + (Number(item.weekday) - 1) * dayWidth;
        const availableWidth = dayWidth - 20;
        const laneGap = laneCount > 1 ? 7 : 0;
        const eventWidth = (availableWidth - laneGap * (laneCount - 1)) / laneCount;
        const x = dayX + 10 + lane * (eventWidth + laneGap);
        const y = contentY + (Number(item.startMinute) - dayStart) * minuteScale + 5;
        const eventHeight = Math.max(42, (Number(item.endMinute) - Number(item.startMinute)) * minuteScale - 10);

        drawWeekEvent(ctx, item, { x, y, width: eventWidth, height: eventHeight });
    }

    ctx.restore();
    strokeRoundRect(ctx, margin, gridY, gridWidth, gridHeight, 24, '#cfd8e3', 2);
    drawFooter(ctx, data, theme, width, height, margin);
};

const drawMonthEvent = (ctx, event, x, y, width, height) => {
    const appearance = statusAppearance(event);

    ctx.save();
    if (event.status === 'canceled') ctx.setLineDash([8, 6]);
    fillRoundRect(ctx, x, y, width, height, 8, appearance.surface);
    strokeRoundRect(ctx, x, y, width, height, 8, appearance.border, 1.5);
    ctx.setLineDash([]);
    ctx.fillStyle = appearance.accent;
    ctx.fillRect(x, y + 8, 5, height - 16);
    ctx.fillStyle = appearance.text;
    ctx.textBaseline = 'middle';
    setFont(ctx, 16, 700);
    ctx.fillText(fitText(ctx, event.time || '', 74), x + 12, y + height / 2 + 1);
    setFont(ctx, 17, 700);
    ctx.fillText(fitText(ctx, event.code || event.name, width - 118), x + 88, y + height / 2 + 1);
    ctx.restore();
};

const drawMonthExport = (canvas, data, theme) => {
    const width = 2400;
    const height = 1800;
    const margin = 84;
    const headerHeight = 270;
    const gridY = 300;
    const gridHeight = 1410;
    const gridWidth = width - margin * 2;
    const weekdayHeight = 72;
    const cells = data.cells || [];
    const rows = Math.max(5, Math.ceil(cells.length / 7));
    const columnWidth = gridWidth / 7;
    const rowHeight = (gridHeight - weekdayHeight) / rows;
    const ctx = canvas.getContext('2d');

    canvas.width = width;
    canvas.height = height;
    ctx.fillStyle = theme.background;
    ctx.fillRect(0, 0, width, height);
    drawHeader(ctx, data, theme, width, margin, headerHeight);

    fillRoundRect(ctx, margin, gridY, gridWidth, gridHeight, 24, '#ffffff');
    ctx.save();
    roundedPath(ctx, margin, gridY, gridWidth, gridHeight, 24);
    ctx.clip();

    for (let day = 0; day < 7; day += 1) {
        const x = margin + day * columnWidth;
        ctx.fillStyle = day >= 5 ? theme.weekend : '#f8fafc';
        ctx.fillRect(x, gridY, columnWidth, weekdayHeight);
        ctx.fillStyle = day === 0 ? theme.accent : '#344054';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        setFont(ctx, 23, 800);
        ctx.fillText(data.weekdays?.[day] || '', x + columnWidth / 2, gridY + weekdayHeight / 2 + 1);
    }
    ctx.textAlign = 'left';

    cells.forEach((cell, index) => {
        const column = index % 7;
        const row = Math.floor(index / 7);
        const x = margin + column * columnWidth;
        const y = gridY + weekdayHeight + row * rowHeight;

        ctx.fillStyle = !cell.inMonth ? '#f6f7f9' : (column >= 5 ? theme.weekend : '#ffffff');
        ctx.fillRect(x, y, columnWidth, rowHeight);
        if (!cell.inTerm) {
            ctx.fillStyle = '#ffffff99';
            ctx.fillRect(x, y, columnWidth, rowHeight);
        }

        ctx.strokeStyle = '#dce3eb';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(x, y, columnWidth, rowHeight);

        if (cell.isToday) {
            fillRoundRect(ctx, x + 16, y + 13, 42, 38, 12, theme.accent);
        }
        ctx.fillStyle = cell.isToday ? '#ffffff' : (cell.inMonth ? '#27313b' : '#98a2b3');
        ctx.textBaseline = 'middle';
        setFont(ctx, 20, 800);
        ctx.fillText(String(cell.day || ''), x + 27, y + 32);

        if (cell.week && cell.weekday === 1) {
            ctx.fillStyle = theme.accent;
            ctx.textAlign = 'right';
            setFont(ctx, 15, 700);
            ctx.fillText(`第 ${cell.week} 周`, x + columnWidth - 16, y + 32);
            ctx.textAlign = 'left';
        }

        const eventX = x + 14;
        const eventWidth = columnWidth - 28;
        const eventHeight = 34;
        let eventY = y + 60;
        const maxEvents = Math.max(2, Math.floor((rowHeight - 88) / (eventHeight + 7)));

        (cell.events || []).slice(0, maxEvents).forEach((event) => {
            drawMonthEvent(ctx, event, eventX, eventY, eventWidth, eventHeight);
            eventY += eventHeight + 7;
        });

        const remaining = Math.max(0, Number(cell.overflowCount || 0) + Math.max(0, (cell.events || []).length - maxEvents));
        if (remaining > 0) {
            ctx.fillStyle = '#667085';
            setFont(ctx, 15, 600);
            ctx.fillText(`另有 ${remaining} 节`, eventX + 4, Math.min(y + rowHeight - 19, eventY + 9));
        }
    });

    ctx.restore();
    strokeRoundRect(ctx, margin, gridY, gridWidth, gridHeight, 24, '#cfd8e3', 2);
    drawFooter(ctx, data, theme, width, height, margin);
};

export const renderTimetableExport = async (canvas, data, themeKey = 'ocean') => {
    if (!canvas || !data) return;

    if (document.fonts?.ready) await document.fonts.ready;

    const theme = THEMES[themeKey] || THEMES.ocean;
    if (data.view === 'month') {
        drawMonthExport(canvas, data, theme);
    } else {
        drawWeekExport(canvas, data, theme);
    }
};

const safeFilename = (value) => String(value || '课表')
    .replace(/[\\/:*?"<>|]+/g, '-')
    .replace(/\s+/g, ' ')
    .trim();

export const downloadTimetablePng = async (canvas, filename) => {
    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((result) => {
            if (result) resolve(result);
            else reject(new Error('无法生成课表图片。'));
        }, 'image/png', 1);
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `${safeFilename(filename)}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
};
