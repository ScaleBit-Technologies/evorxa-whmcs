/*!
 * Evorxa Cloud client panel. No dependencies.
 * Talks to the module's JSON endpoint (POST + CSRF token); all upstream text is inserted with textContent.
 */
(function () {
    'use strict';

    var root = document.getElementById('evx');
    var bootEl = document.getElementById('evx-boot');
    if (!root || !bootEl || root.getAttribute('data-evx-ready')) {
        return;
    }
    root.setAttribute('data-evx-ready', '1');

    var boot = {};
    try {
        boot = JSON.parse(bootEl.textContent || '{}');
    } catch (e) {
        return;
    }
    var T = boot.t || {};
    var state = boot.state;
    var status = boot.status || {};

    // ------------------------------------------------------------------ helpers

    function t(key, vars) {
        var text = Object.prototype.hasOwnProperty.call(T, key) ? T[key] : key;
        if (vars) {
            Object.keys(vars).forEach(function (k) {
                text = text.split(':' + k).join(String(vars[k]));
            });
        }
        return text;
    }

    function hook(name) {
        return root.querySelector('[data-evx="' + name + '"]');
    }

    function all(selector, ctx) {
        return Array.prototype.slice.call((ctx || root).querySelectorAll(selector));
    }

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'className') {
                    node.className = attrs[k];
                } else if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) {
                    node.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
                }
            });
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    function clear(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function ApiError(message, code) {
        this.message = message;
        this.code = code;
    }

    function api(action, data) {
        var body = new URLSearchParams();
        body.set('token', boot.token || '');
        body.set('do', action);
        Object.keys(data || {}).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) {
                body.set(k, data[k]);
            }
        });
        return fetch(boot.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': boot.token || '' },
            body: body
        }).then(function (res) {
            return res.text();
        }).then(function (text) {
            var json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                throw new ApiError(t('js_session'), 'session');
            }
            if (!json || typeof json.ok === 'undefined') {
                // Not our endpoint's answer (login page, CSRF 419, proxy error page).
                throw new ApiError(t('js_session'), 'session');
            }
            if (!json.ok) {
                throw new ApiError(json.error || t('js_error'), json.code);
            }
            return json.data || {};
        }, function () {
            throw new ApiError(t('js_offline'), 'network');
        });
    }

    function fail(err) {
        toast((err && err.message) || t('js_error'), 'bad');
    }

    // ------------------------------------------------------------------ cache (stale-while-revalidate)

    // Per tab session and service; the asset version in the key drops everything after an update.
    var memo = {};
    var cachePrefix = 'evx:' + boot.sid + ':' + boot.v + ':';

    function cacheKey(action, params) {
        return cachePrefix + action + ':' + JSON.stringify(params || {});
    }

    function cacheGet(action, params) {
        var key = cacheKey(action, params);
        if (memo[key]) {
            return memo[key];
        }
        try {
            var raw = window.sessionStorage.getItem(key);
            if (raw) {
                memo[key] = JSON.parse(raw);
                return memo[key];
            }
        } catch (e) {
            // Private mode or storage full: memory cache only.
        }
        return null;
    }

    function cacheSet(action, params, data) {
        var key = cacheKey(action, params);
        memo[key] = { at: Date.now(), data: data };
        try {
            window.sessionStorage.setItem(key, JSON.stringify(memo[key]));
        } catch (e) {
        }
    }

    function cacheDrop(action) {
        var start = cachePrefix + action + ':';
        Object.keys(memo).forEach(function (k) {
            if (k.indexOf(start) === 0) {
                delete memo[k];
            }
        });
        try {
            for (var i = window.sessionStorage.length - 1; i >= 0; i--) {
                var k = window.sessionStorage.key(i);
                if (k && k.indexOf(start) === 0) {
                    window.sessionStorage.removeItem(k);
                }
            }
        } catch (e) {
        }
    }

    /**
     * Render cached data immediately (if any), then refresh from the server unless the cache is
     * younger than ttl. render(data, fromCache) can therefore run twice.
     */
    function load(action, params, ttl, render, opts) {
        opts = opts || {};
        var hit = opts.force ? null : cacheGet(action, params);
        // A rendering bug must never take the whole panel down: surface it as a normal error.
        var safeRender = function (data, fromCache) {
            try {
                render(data, fromCache);
            } catch (e) {
                if (window.console) {
                    console.error('evorxa panel:', e);
                }
                throw new ApiError(t('js_error'), 'render');
            }
        };
        if (hit) {
            try {
                safeRender(hit.data, true);
            } catch (e) {
                hit = null; // bad cached copy: ignore it and fetch fresh data
            }
            if (hit && Date.now() - hit.at < ttl) {
                return Promise.resolve(hit.data);
            }
        }
        return api(action, params).then(function (d) {
            cacheSet(action, params, d);
            if (!hit || JSON.stringify(hit.data) !== JSON.stringify(d)) {
                safeRender(d, false); // unchanged data: no flicker, no lost selections
            }
            return d;
        }, function (err) {
            if (!hit) {
                throw err;
            }
            return hit.data; // keep showing the cached copy
        });
    }

    function toast(message, tone) {
        var box = hook('toasts');
        if (!box || !message) {
            return;
        }
        var node = el('div', { className: 'evx-toast evx-toast-' + (tone || 'info'), role: 'status' }, message);
        box.appendChild(node);
        setTimeout(function () {
            node.classList.add('is-leaving');
            setTimeout(function () {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }, 350);
        }, 4200);
    }

    /** Accessible confirm dialog; resolves true/false. */
    function confirmBox(opts) {
        return new Promise(function (resolve) {
            var previous = document.activeElement;
            var backdrop = el('div', { className: 'evx-modal-backdrop' });
            var dialog = el('div', { className: 'evx-modal', role: 'dialog', 'aria-modal': 'true' });
            if (boot.rtl) {
                dialog.setAttribute('dir', 'rtl');
            }
            dialog.appendChild(el('h4', null, opts.title));
            if (opts.body) {
                dialog.appendChild(el('p', null, opts.body));
            }
            var actions = el('div', { className: 'evx-modal-actions' });
            var cancel = el('button', { type: 'button', className: 'btn btn-default' }, t('js_cancel'));
            var ok = el('button', { type: 'button', className: 'btn ' + (opts.danger ? 'btn-danger' : 'btn-primary') }, opts.confirm || t('js_confirm'));
            actions.appendChild(cancel);
            actions.appendChild(ok);
            dialog.appendChild(actions);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            ok.focus();

            function close(result) {
                document.removeEventListener('keydown', onKey, true);
                if (backdrop.parentNode) {
                    backdrop.parentNode.removeChild(backdrop);
                }
                if (previous && previous.focus) {
                    previous.focus();
                }
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    close(false);
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    (document.activeElement === ok ? cancel : ok).focus();
                }
            }
            document.addEventListener('keydown', onKey, true);
            cancel.addEventListener('click', function () { close(false); });
            ok.addEventListener('click', function () { close(true); });
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) {
                    close(false);
                }
            });
        });
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var area = el('textarea', { readonly: true, style: 'position:fixed;top:-1000px;opacity:0' });
            area.value = text;
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy') ? resolve() : reject();
            } catch (e) {
                reject(e);
            }
            document.body.removeChild(area);
        });
    }

    function setBusy(button, busy) {
        if (!button) {
            return;
        }
        button.disabled = !!busy;
        button.classList.toggle('is-busy', !!busy);
    }

    // ------------------------------------------------------------------ formatting

    function fmtPct(v) {
        return v === null || v === undefined ? '–' : (Math.round(v * 10) / 10) + '%';
    }

    function fmtBits(v) {
        if (v === null || v === undefined) {
            return '–';
        }
        var units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        var i = 0;
        while (Math.abs(v) >= 1000 && i < units.length - 1) {
            v /= 1000;
            i++;
        }
        return (i === 0 ? Math.round(v) : v.toFixed(v < 10 ? 1 : 0)) + ' ' + units[i];
    }

    function fmtTime(ms, span) {
        var d = new Date(ms);
        var hh = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
        if (span > 36 * 3600 * 1000) {
            return (d.getMonth() + 1) + '/' + d.getDate() + ' ' + hh;
        }
        return hh;
    }

    function niceMax(v) {
        if (!v || v <= 0) {
            return 1;
        }
        var exp = Math.pow(10, Math.floor(Math.log(v) / Math.LN10));
        var f = v / exp;
        var nice = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
        return nice * exp;
    }

    // ------------------------------------------------------------------ SVG line chart

    function Chart(box, opts) {
        this.box = box;
        this.opts = opts;
        this.series = [];
    }

    Chart.prototype.set = function (series) {
        this.series = series || [];
        this.draw();
    };

    Chart.prototype.draw = function () {
        var box = this.box;
        var opts = this.opts;
        var series = this.series;
        var ns = 'http://www.w3.org/2000/svg';
        clear(box);
        var points = [];
        series.forEach(function (s) {
            s.points.forEach(function (p) {
                if (p[1] !== null && p[1] !== undefined) {
                    points.push(p);
                }
            });
        });
        if (points.length < 2) {
            box.appendChild(el('div', { className: 'evx-chart-empty' }, t('js_no_data')));
            return;
        }
        var W = Math.max(260, box.clientWidth || 600);
        var H = box.clientHeight || 170;
        var pad = { l: 52, r: 10, t: 10, b: 22 };
        var t0 = Infinity;
        var t1 = -Infinity;
        var vmax = 0;
        points.forEach(function (p) {
            t0 = Math.min(t0, p[0]);
            t1 = Math.max(t1, p[0]);
            vmax = Math.max(vmax, p[1]);
        });
        vmax = opts.max ? Math.max(opts.max, vmax) : niceMax(vmax * 1.1);
        var span = Math.max(1, t1 - t0);
        var x = function (tt) { return pad.l + (tt - t0) / span * (W - pad.l - pad.r); };
        var y = function (v) { return pad.t + (1 - v / vmax) * (H - pad.t - pad.b); };

        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', H);
        svg.setAttribute('role', 'img');

        function add(tag, attrs, text) {
            var node = document.createElementNS(ns, tag);
            Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
            if (text !== undefined) {
                node.textContent = text;
            }
            svg.appendChild(node);
            return node;
        }

        for (var i = 0; i <= 4; i++) {
            var v = vmax * i / 4;
            var yy = Math.round(y(v)) + 0.5;
            add('line', { x1: pad.l, x2: W - pad.r, y1: yy, y2: yy, 'class': 'evx-gridline' });
            add('text', { x: pad.l - 8, y: yy + 3.5, 'text-anchor': 'end', 'class': 'evx-axis' }, opts.fmt(v));
        }
        for (var k = 0; k <= 3; k++) {
            var tt = t0 + span * k / 3;
            add('text', { x: x(tt), y: H - 5, 'text-anchor': k === 0 ? 'start' : k === 3 ? 'end' : 'middle', 'class': 'evx-axis' }, fmtTime(tt, span));
        }

        series.forEach(function (s) {
            var segments = [];
            var current = [];
            s.points.forEach(function (p) {
                if (p[1] === null || p[1] === undefined) {
                    if (current.length) {
                        segments.push(current);
                    }
                    current = [];
                } else {
                    current.push(p);
                }
            });
            if (current.length) {
                segments.push(current);
            }
            segments.forEach(function (seg) {
                var line = seg.map(function (p, idx) {
                    return (idx ? 'L' : 'M') + x(p[0]).toFixed(1) + ' ' + y(p[1]).toFixed(1);
                }).join(' ');
                var area = line + ' L' + x(seg[seg.length - 1][0]).toFixed(1) + ' ' + y(0) + ' L' + x(seg[0][0]).toFixed(1) + ' ' + y(0) + ' Z';
                add('path', { d: area, fill: s.color, 'fill-opacity': 0.12, stroke: 'none' });
                add('path', { d: line, fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' });
            });
        });

        var cursor = add('line', { x1: 0, x2: 0, y1: pad.t, y2: H - pad.b, 'class': 'evx-cursor', visibility: 'hidden' });
        var dots = series.map(function (s) {
            return add('circle', { r: 3.5, fill: s.color, stroke: '#fff', 'stroke-width': 1.5, visibility: 'hidden' });
        });
        box.appendChild(svg);

        var tip = el('div', { className: 'evx-tip', hidden: true });
        box.appendChild(tip);

        var legend = el('div', { className: 'evx-legend' });
        series.forEach(function (s) {
            var item = el('span');
            var sw = el('i');
            sw.style.background = s.color;
            item.appendChild(sw);
            item.appendChild(document.createTextNode(s.label));
            legend.appendChild(item);
        });
        box.parentNode.querySelectorAll('.evx-legend').forEach(function (n) { n.parentNode.removeChild(n); });
        if (series.length > 1) {
            box.parentNode.appendChild(legend);
        }

        var ref = series[0].points;
        function move(e) {
            var rect = svg.getBoundingClientRect();
            var px = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
            var tx = t0 + (px - pad.l) / (W - pad.l - pad.r) * span;
            var best = 0;
            for (var j = 1; j < ref.length; j++) {
                if (Math.abs(ref[j][0] - tx) < Math.abs(ref[best][0] - tx)) {
                    best = j;
                }
            }
            var at = ref[best][0];
            var cx = x(at);
            cursor.setAttribute('x1', cx);
            cursor.setAttribute('x2', cx);
            cursor.setAttribute('visibility', 'visible');
            clear(tip);
            tip.appendChild(el('b', null, fmtTime(at, 0)));
            series.forEach(function (s, si) {
                var p = s.points[best];
                var val = p ? p[1] : null;
                tip.appendChild(el('div', null, s.label + ': ' + opts.fmt(val)));
                if (val === null || val === undefined) {
                    dots[si].setAttribute('visibility', 'hidden');
                } else {
                    dots[si].setAttribute('cx', cx);
                    dots[si].setAttribute('cy', y(val));
                    dots[si].setAttribute('visibility', 'visible');
                }
            });
            tip.hidden = false;
            var left = cx + 12;
            if (left + tip.offsetWidth > W) {
                left = cx - tip.offsetWidth - 12;
            }
            tip.style.left = Math.max(0, left) + 'px';
        }
        function leave() {
            tip.hidden = true;
            cursor.setAttribute('visibility', 'hidden');
            dots.forEach(function (d) { d.setAttribute('visibility', 'hidden'); });
        }
        svg.addEventListener('mousemove', move);
        svg.addEventListener('touchmove', move, { passive: true });
        svg.addEventListener('mouseleave', leave);
        svg.addEventListener('touchend', leave);
    };

    var charts = {};
    function chart(name, opts) {
        if (!charts[name]) {
            var box = root.querySelector('[data-chart="' + name + '"]');
            if (!box) {
                return null;
            }
            charts[name] = new Chart(box, opts);
        }
        return charts[name];
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            Object.keys(charts).forEach(function (k) {
                if (charts[k].box.offsetParent) {
                    charts[k].draw();
                }
            });
        }, 150);
    });

    // ------------------------------------------------------------------ copy buttons

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy],[data-copy-from]');
        if (!btn) {
            return;
        }
        var text = btn.getAttribute('data-copy');
        if (text === null) {
            var src = hook(btn.getAttribute('data-copy-from'));
            text = src ? src.textContent : '';
        }
        if (!text) {
            return;
        }
        copyText(text).then(function () { toast(t('js_copied'), 'ok'); }, function () { toast(t('js_copy_failed'), 'bad'); });
    });

    // ------------------------------------------------------------------ status & power

    function renderStatus(s) {
        status = s || status;
        var pill = hook('status');
        if (pill) {
            pill.className = 'evx-pill evx-tone-' + (status.tone || 'off');
            hook('status-label').textContent = status.label || '';
        }
        all('[data-power]').forEach(function (b) {
            var when = b.getAttribute('data-when');
            var allowed = !status.busy && (when === 'on' ? status.running : !status.running);
            b.disabled = !allowed;
        });
    }

    /** Pending view stepper: order -> build -> install -> ready. */
    function renderPhase(phase) {
        var steps = hook('steps');
        if (!steps || !phase) {
            return;
        }
        var order = ['order', 'build', 'install', 'ready'];
        var current = order.indexOf(phase);
        all('li', steps).forEach(function (li) {
            var i = order.indexOf(li.getAttribute('data-step'));
            li.classList.toggle('is-done', i < current);
            li.classList.toggle('is-active', i === current);
        });
    }

    var pollTimer = null;
    var sessionLost = false;
    function schedule(delay) {
        clearTimeout(pollTimer);
        // Also stop once the panel is gone (single-page navigation away from the service).
        if (document.hidden || sessionLost || !document.body.contains(root) || (state !== 'active' && state !== 'provisioning')) {
            return;
        }
        if (delay === undefined) {
            delay = state === 'provisioning' ? 6000 : (status.busy ? 4000 : 30000);
        }
        pollTimer = setTimeout(poll, delay);
    }

    function poll() {
        api('status').then(function (d) {
            var before = state;
            state = d.state;
            renderStatus(d.status);
            if (d.ip && hook('ip')) {
                hook('ip').textContent = d.ip;
            }
            renderPhase(d.phase);
            if (before !== state && (before === 'provisioning' || state === 'provisioning' || state === 'suspended')) {
                window.location.reload();
                return;
            }
            schedule();
        }, function (err) {
            if (err.code === 'session') {
                sessionLost = true;
                toast(err.message, 'bad');
                return;
            }
            schedule(15000);
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(pollTimer);
        } else {
            schedule(300);
        }
    });

    var powerConfirm = { restart: true, shutdown: true, poweroff: true };
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-power]');
        if (!btn || btn.disabled) {
            return;
        }
        var op = btn.getAttribute('data-power');
        var go = powerConfirm[op]
            ? confirmBox({
                title: t('js_' + op + '_title'),
                body: t('js_' + op + '_body'),
                confirm: t('js_' + op + '_btn'),
                danger: op === 'poweroff'
            })
            : Promise.resolve(true);
        go.then(function (yes) {
            if (!yes) {
                return;
            }
            all('[data-power]').forEach(function (b) { b.disabled = true; });
            api('power', { op: op }).then(function (d) {
                toast(d.message || t('js_power_sent'), 'ok');
                renderStatus({ key: 'working', label: t('js_working'), tone: 'busy', running: status.running, busy: true });
                schedule(3000);
            }, function (err) {
                fail(err);
                renderStatus(status);
            });
        });
    });

    // ------------------------------------------------------------------ root password

    var password = null;
    var passwordShown = false;
    var mask = '••••••••••••';

    function getPassword() {
        if (password !== null) {
            return Promise.resolve(password);
        }
        return api('password_reveal').then(function (d) {
            password = d.password;
            return password;
        });
    }

    function showPassword(show) {
        passwordShown = show;
        var node = hook('password');
        if (node) {
            node.textContent = show && password ? password : mask;
        }
        var toggle = root.querySelector('[data-action="password-toggle"] i');
        if (toggle) {
            toggle.className = show ? 'far fa-eye-slash' : 'far fa-eye';
        }
    }

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) {
            return;
        }
        var action = btn.getAttribute('data-action');
        if (action === 'password-toggle') {
            if (passwordShown) {
                showPassword(false);
                return;
            }
            setBusy(btn, true);
            getPassword().then(function () { showPassword(true); }, fail).then(function () { setBusy(btn, false); });
        } else if (action === 'password-copy') {
            getPassword().then(function (pw) {
                return copyText(pw).then(function () { toast(t('js_copied'), 'ok'); });
            }, fail);
        } else if (action === 'password-reset') {
            confirmBox({ title: t('js_pwreset_title'), body: t('js_pwreset_body'), confirm: t('js_pwreset_btn'), danger: true }).then(function (yes) {
                if (!yes) {
                    return;
                }
                setBusy(btn, true);
                api('password_reset').then(function (d) {
                    password = d.password;
                    showPassword(true);
                    toast(d.message || t('js_pwreset_done'), 'ok');
                }, fail).then(function () { setBusy(btn, false); });
            });
        } else if (action === 'app-reveal') {
            setBusy(btn, true);
            api('app_reveal').then(function (d) {
                var app = d.app;
                var details = hook('app-details');
                if (!app || !details) {
                    toast(t('js_app_pending'), 'info');
                    return;
                }
                var link = hook('app-url');
                if (app.url) {
                    link.textContent = app.url;
                    link.setAttribute('href', app.url);
                } else {
                    link.textContent = t('js_app_no_url');
                    link.removeAttribute('href');
                }
                hook('app-user').textContent = app.username || '–';
                hook('app-pass').textContent = app.password || '–';
                var note = hook('app-note');
                note.textContent = app.note || '';
                note.hidden = !app.note;
                details.hidden = false;
                btn.hidden = true;
            }, fail).then(function () { setBusy(btn, false); });
        }
    });

    // ------------------------------------------------------------------ tabs

    var loaders = {};
    var loaded = {};
    var activeTab = null;

    function activate(name) {
        activeTab = name;
        all('.evx-tab').forEach(function (b) {
            var on = b.getAttribute('data-tab') === name;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        all('[data-panel]').forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-panel') === name);
        });
        if (!loaded[name] && loaders[name]) {
            loaded[name] = true;
            loaders[name]();
        } else {
            Object.keys(charts).forEach(function (k) {
                if (charts[k].box.offsetParent) {
                    charts[k].draw();
                }
            });
        }
    }

    root.addEventListener('click', function (e) {
        var tab = e.target.closest('.evx-tab');
        if (tab) {
            activate(tab.getAttribute('data-tab'));
        }
    });

    function segment(name, onChange) {
        var seg = hook(name);
        if (!seg) {
            return;
        }
        seg.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b || b.classList.contains('is-active')) {
                return;
            }
            all('button', seg).forEach(function (x) { x.classList.toggle('is-active', x === b); });
            onChange(b.getAttribute('data-period') || b.getAttribute('data-mode'));
        });
    }

    /**
     * A tab failed to load: replace its skeletons with a message and a Retry button
     * (instead of leaving placeholders shimmering forever).
     */
    function panelError(name, err) {
        var panel = root.querySelector('[data-panel="' + name + '"]');
        if (!panel) {
            return;
        }
        loaded[name] = false;
        var box = panel.querySelector('.evx-panel-error');
        if (!box) {
            box = el('div', { className: 'evx-panel-error', role: 'alert' });
            panel.insertBefore(box, panel.firstChild);
        }
        clear(box);
        box.appendChild(el('i', { className: 'fas fa-exclamation-circle', 'aria-hidden': 'true' }));
        box.appendChild(el('span', null, (err && err.message) || t('js_error')));
        var retry = el('button', { type: 'button', className: 'btn btn-default btn-sm' }, t('js_retry'));
        retry.addEventListener('click', function () {
            box.parentNode.removeChild(box);
            panel.classList.remove('has-error');
            loaded[name] = true;
            loaders[name](true);
        });
        box.appendChild(retry);
        panel.classList.add('has-error');
    }

    function panelLoading(name, on) {
        var p = root.querySelector('[data-panel="' + name + '"]');
        if (p) {
            p.classList.toggle('is-loading', on);
        }
    }

    // ------------------------------------------------------------------ usage

    var usagePeriod = '1h';
    var usageTimer = null;

    function bar(name, pct) {
        var node = hook('bar-' + name);
        if (!node) {
            return;
        }
        var v = Math.max(0, Math.min(100, pct || 0));
        node.style.width = v + '%';
        node.classList.toggle('is-high', v >= 75 && v < 90);
        node.classList.toggle('is-full', v >= 90);
    }

    var rendered = {};

    /** Swap charts back to shimmer placeholders while a new period loads. */
    function skeletonCharts(names) {
        names.forEach(function (name) {
            var box = root.querySelector('[data-chart="' + name + '"]');
            if (!box) {
                return;
            }
            clear(box);
            box.appendChild(el('div', { className: 'evx-skel evx-skel-chart' }));
            var legend = box.parentNode.querySelector('.evx-legend');
            if (legend) {
                legend.parentNode.removeChild(legend);
            }
        });
    }

    loaders.usage = function loadUsage(force) {
        clearTimeout(usageTimer);
        var period = usagePeriod;
        if (rendered.usage && !cacheGet('metrics', { period: period })) {
            skeletonCharts(['cpu', 'mem', 'net']); // period switch without cached data
        }
        load('metrics', { period: period }, 55000, function (d) {
            if (period === usagePeriod) {
                renderUsage(d);
            }
        }, { force: force }).catch(function (err) { panelError('usage', err); }).then(function () {
            panelLoading('usage', false);
            usageTimer = setTimeout(function () {
                if (activeTab === 'usage' && !document.hidden) {
                    loaders.usage(true);
                } else {
                    loaded.usage = false;
                }
            }, 60000);
        });
    };

    function renderUsage(d) {
            rendered.usage = true;
            var c = d.current || {};
            hook('stat-cpu').textContent = fmtPct(c.cpu);
            hook('stat-mem').textContent = fmtPct(c.memPct);
            hook('stat-mem-text').textContent = c.memText || '';
            hook('stat-disk').textContent = fmtPct(c.diskPct);
            hook('stat-disk-text').textContent = c.diskText || '';
            hook('stat-rx').textContent = c.rxText || '–';
            hook('stat-tx').textContent = c.txText || '–';
            bar('cpu', c.cpu);
            bar('mem', c.memPct);
            bar('disk', c.diskPct);
            var pts = d.points || [];
            var accent = getComputedStyle(root).getPropertyValue('--evx-accent').trim() || '#2563eb';
            chart('cpu', { fmt: fmtPct, max: 100 }).set([{ label: t('js_cpu'), color: accent, points: pts.map(function (p) { return [p[0], p[1]]; }) }]);
            chart('mem', { fmt: fmtPct, max: 100 }).set([{ label: t('js_memory'), color: '#8b5cf6', points: pts.map(function (p) { return [p[0], p[2]]; }) }]);
            chart('net', { fmt: fmtBits }).set([
                { label: t('js_net_in'), color: '#0ea5e9', points: pts.map(function (p) { return [p[0], p[4]]; }) },
                { label: t('js_net_out'), color: '#f59e0b', points: pts.map(function (p) { return [p[0], p[5]]; }) }
            ]);
    }
    segment('usage-period', function (p) {
        usagePeriod = p;
        loaders.usage();
    });

    // ------------------------------------------------------------------ DDoS

    var ddosPeriod = 'live';
    var ddosTimer = null;
    var ddosSettings = { email: false };

    loaders.ddos = function loadDdos(force) {
        clearTimeout(ddosTimer);
        var period = ddosPeriod;
        if (rendered.ddos && !cacheGet('ddos', { period: period })) {
            skeletonCharts(['ddos']);
        }
        load('ddos', { period: period }, period === 'live' ? 15000 : 110000, function (d) {
            if (period === ddosPeriod) {
                renderDdos(d);
            }
        }, { force: force }).catch(function (err) { panelError('ddos', err); }).then(function () {
            panelLoading('ddos', false);
            if (ddosPeriod === 'live') {
                ddosTimer = setTimeout(function () {
                    if (activeTab === 'ddos' && !document.hidden) {
                        loaders.ddos(true);
                    } else {
                        loaded.ddos = false;
                    }
                }, 20000);
            }
        });
    };

    function renderDdos(d) {
            rendered.ddos = true;
            hook('ddos-unsupported').hidden = d.supported;
            hook('ddos-body').hidden = !d.supported;
            if (!d.supported) {
                return;
            }
            var pts = d.points || [];
            chart('ddos', { fmt: fmtBits }).set([
                { label: t('js_ddos_dropped'), color: '#dc2626', points: pts.map(function (p) { return [p[0], p[1]]; }) },
                { label: t('js_ddos_passed'), color: '#16a34a', points: pts.map(function (p) { return [p[0], p[2]]; }) }
            ]);
            var body = hook('ddos-incidents');
            clear(body);
            if (!d.incidents || !d.incidents.length) {
                var row = el('tr');
                row.appendChild(el('td', { colspan: 4, className: 'evx-empty' }, t('js_ddos_none')));
                body.appendChild(row);
            } else {
                d.incidents.forEach(function (i) {
                    var tr = el('tr');
                    tr.appendChild(el('td', null, i.start));
                    tr.appendChild(el('td', null, i.duration));
                    tr.appendChild(el('td', null, i.vectors));
                    tr.appendChild(el('td', null, i.peak));
                    body.appendChild(tr);
                });
            }
            ddosSettings = d.settings || ddosSettings;
            renderDdosSettings();
    }
    segment('ddos-period', function (p) {
        ddosPeriod = p;
        loaders.ddos();
    });

    function renderDdosSettings() {
        var form = hook('ddos-form');
        if (form) {
            form.elements.email.checked = !!ddosSettings.email;
        }
    }

    var ddosForm = hook('ddos-form');
    if (ddosForm) {
        ddosForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = ddosForm.querySelector('[type="submit"]');
            setBusy(btn, true);
            api('ddos_save', { email: ddosForm.elements.email.checked ? 1 : 0 }).then(function (d) {
                ddosSettings.email = ddosForm.elements.email.checked;
                cacheDrop('ddos');
                toast(d.message || t('js_saved'), 'ok');
            }, fail).then(function () { setBusy(btn, false); });
        });
    }

    // ------------------------------------------------------------------ firewall

    var fwRules = [];
    var fwEnabled = false;

    function renderFirewall(d) {
        hook('fw-unavailable').hidden = !!d.available;
        hook('fw-body').hidden = !d.available;
        if (!d.available) {
            return;
        }
        fwEnabled = !!d.enabled;
        var tag = hook('fw-state');
        tag.textContent = d.enabled ? t('js_fw_active') : t('js_fw_inactive');
        tag.className = 'evx-tag ' + (d.enabled ? 'evx-tag-ok' : '');
        var policy = hook('fw-policy');
        policy.elements.enabled.checked = !!d.enabled;
        policy.elements['in'].value = d.policy && d.policy['in'] === 'block' ? 'block' : 'allow';
        policy.elements.out.value = d.policy && d.policy.out === 'block' ? 'block' : 'allow';
        fwRules = d.rules || [];
        var body = hook('fw-rules');
        clear(body);
        root.querySelector('.evx-order-hint').hidden = fwRules.length < 2;
        if (!fwRules.length) {
            var row = el('tr');
            row.appendChild(el('td', { colspan: 7, className: 'evx-empty' }, t('js_fw_no_rules')));
            body.appendChild(row);
            return;
        }
        fwRules.forEach(function (r, index) {
            var tr = el('tr', { 'data-id': r.id });
            var gripCell = el('td');
            if (fwRules.length > 1) {
                var grip = el('button', { type: 'button', className: 'evx-grip', title: t('js_fw_drag'), 'aria-label': t('js_fw_drag') + ' (' + (index + 1) + ')' });
                grip.appendChild(el('i', { className: 'fas fa-grip-vertical', 'aria-hidden': 'true' }));
                gripCell.appendChild(grip);
            }
            tr.appendChild(gripCell);
            var action = el('td');
            action.appendChild(el('span', { className: 'evx-tag ' + (r.action === 'block' ? 'evx-tag-bad' : 'evx-tag-ok') }, r.action === 'block' ? t('js_fw_block') : t('js_fw_allow')));
            tr.appendChild(action);
            tr.appendChild(el('td', null, r.direction === 'out' ? t('js_fw_out') : t('js_fw_in')));
            tr.appendChild(el('td', null, r.protocol === 'any' ? t('js_fw_any') : String(r.protocol).toUpperCase()));
            tr.appendChild(el('td', { className: 'evx-mono' }, r.port === '' || r.port === null ? t('js_fw_all_ports') : r.port));
            tr.appendChild(el('td', { className: 'evx-mono' }, r.anywhere ? t('js_fw_anywhere') : r.source));
            var cell = el('td');
            var del = el('button', { type: 'button', className: 'evx-icon-btn', 'data-rule': r.id, title: t('js_remove') });
            del.appendChild(el('i', { className: 'far fa-trash-alt', 'aria-hidden': 'true' }));
            cell.appendChild(del);
            tr.appendChild(cell);
            body.appendChild(tr);
        });
    }

    loaders.firewall = function loadFirewall(force) {
        load('firewall', {}, 25000, renderFirewall, { force: force }).catch(function (err) { panelError('firewall', err); });
    };

    /** Result of a firewall change: show it and keep it as the cached state. */
    function applyFirewall(d) {
        cacheSet('firewall', {}, d);
        renderFirewall(d);
    }

    // Drag rows by the grip (mouse, pen or touch) to change the order; arrow keys work on a focused grip.
    var drag = null;
    var keyTimer = null;

    function rowOrder() {
        return all('tr[data-id]', hook('fw-rules')).map(function (tr) { return tr.getAttribute('data-id'); });
    }

    function saveOrder(order, previous) {
        if (order.join(',') === previous.join(',')) {
            return;
        }
        api('firewall_order', { order: order.join(',') }).then(function (d) {
            applyFirewall(d);
            toast(d.message || t('js_order_saved'), 'ok');
        }, function (err) {
            fail(err);
            loaders.firewall(); // put the real order back
        });
    }

    root.addEventListener('pointerdown', function (e) {
        var grip = e.target.closest('.evx-grip');
        if (!grip || (e.pointerType === 'mouse' && e.button !== 0)) {
            return;
        }
        e.preventDefault();
        var row = grip.closest('tr');
        drag = { row: row, body: row.parentNode, before: rowOrder(), id: e.pointerId };
        row.classList.add('is-dragging');
        root.classList.add('is-sorting');
        try {
            grip.setPointerCapture(e.pointerId);
        } catch (err) {
        }
    });

    root.addEventListener('pointermove', function (e) {
        if (!drag || e.pointerId !== drag.id) {
            return;
        }
        var rows = all('tr[data-id]', drag.body).filter(function (r) { return r !== drag.row; });
        var target = null;
        for (var i = 0; i < rows.length; i++) {
            var rect = rows[i].getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                target = rows[i];
                break;
            }
        }
        if (target) {
            if (drag.row.nextSibling !== target) {
                drag.body.insertBefore(drag.row, target);
            }
        } else if (drag.body.lastChild !== drag.row) {
            drag.body.appendChild(drag.row);
        }
    });

    function endDrag(e) {
        if (!drag || (e && e.pointerId !== drag.id)) {
            return;
        }
        var d = drag;
        drag = null;
        d.row.classList.remove('is-dragging');
        root.classList.remove('is-sorting');
        saveOrder(rowOrder(), d.before);
    }
    root.addEventListener('pointerup', endDrag);
    root.addEventListener('pointercancel', endDrag);

    root.addEventListener('keydown', function (e) {
        var grip = e.target.closest ? e.target.closest('.evx-grip') : null;
        if (!grip || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) {
            return;
        }
        e.preventDefault();
        var row = grip.closest('tr');
        var body = row.parentNode;
        if (!keyTimer) {
            body.setAttribute('data-before', rowOrder().join(','));
        }
        if (e.key === 'ArrowUp' && row.previousElementSibling) {
            body.insertBefore(row, row.previousElementSibling);
        } else if (e.key === 'ArrowDown' && row.nextElementSibling) {
            body.insertBefore(row.nextElementSibling, row);
        }
        grip.focus();
        clearTimeout(keyTimer);
        keyTimer = setTimeout(function () {
            keyTimer = null;
            saveOrder(rowOrder(), (body.getAttribute('data-before') || '').split(','));
        }, 700);
    });

    var fwPolicy = hook('fw-policy');
    if (fwPolicy) {
        fwPolicy.addEventListener('submit', function (e) {
            e.preventDefault();
            var inbound = fwPolicy.elements['in'].value;
            var outbound = fwPolicy.elements.out.value;
            var enabled = fwPolicy.elements.enabled.checked;
            var adminPort = boot.windows ? 3389 : 22;
            var hasAccess = fwRules.some(function (r) {
                if (r.action !== 'allow' || r.direction === 'out') {
                    return false;
                }
                if (r.port === '' || r.port === null) {
                    return true;
                }
                var range = String(r.port).split('-');
                return adminPort >= +range[0] && adminPort <= +(range[1] || range[0]);
            });
            var go = enabled && inbound === 'block' && !hasAccess
                ? confirmBox({ title: t('js_fw_lockout_title'), body: t('js_fw_lockout_body', { port: adminPort }), confirm: t('js_fw_lockout_btn'), danger: true })
                : Promise.resolve(true);
            go.then(function (yes) {
                if (!yes) {
                    return;
                }
                var btn = fwPolicy.querySelector('[type="submit"]');
                setBusy(btn, true);
                api('firewall_policy', { 'in': inbound, out: outbound, enabled: enabled ? 1 : 0 }).then(function (d) {
                    applyFirewall(d);
                    toast(d.message || t('js_saved'), 'ok');
                }, fail).then(function () { setBusy(btn, false); });
            });
        });
    }

    var fwAdd = hook('fw-add');
    if (fwAdd) {
        fwAdd.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = fwAdd.querySelector('[type="submit"]');
            setBusy(btn, true);
            api('firewall_add', {
                rule_action: fwAdd.elements.rule_action.value,
                direction: fwAdd.elements.direction.value,
                protocol: fwAdd.elements.protocol.value,
                port: fwAdd.elements.port.value.trim(),
                source: fwAdd.elements.source.value.trim()
            }).then(function (d) {
                applyFirewall(d);
                fwAdd.elements.port.value = '';
                fwAdd.elements.source.value = '';
                toast(d.message || t('js_saved'), 'ok');
                if (!d.enabled) {
                    toast(t('js_fw_off_hint'), 'info');
                }
            }, fail).then(function () { setBusy(btn, false); });
        });
        root.addEventListener('click', function (e) {
            var preset = e.target.closest('[data-preset]');
            if (!preset) {
                return;
            }
            var parts = preset.getAttribute('data-preset').split(',');
            fwAdd.elements.direction.value = 'in';
            fwAdd.elements.rule_action.value = parts[0];
            fwAdd.elements.protocol.value = parts[1];
            fwAdd.elements.port.value = parts[2];
            fwAdd.elements.source.focus();
        });
    }

    root.addEventListener('click', function (e) {
        var del = e.target.closest('[data-rule]');
        if (!del) {
            return;
        }
        confirmBox({ title: t('js_fw_delete_title'), body: t('js_fw_delete_body'), confirm: t('js_remove'), danger: true }).then(function (yes) {
            if (!yes) {
                return;
            }
            setBusy(del, true);
            api('firewall_delete', { rule_id: del.getAttribute('data-rule') }).then(function (d) {
                applyFirewall(d);
                toast(d.message || t('js_saved'), 'ok');
            }, function (err) {
                fail(err);
                setBusy(del, false);
            });
        });
    });

    // ------------------------------------------------------------------ reinstall

    var choice = null;
    var reinstallMode = 'os';

    /** Logo image when one is bundled, otherwise a coloured letter badge. */
    function badge(logo, letter, family, extraClass) {
        if (logo) {
            var box = el('span', { className: 'evx-os evx-os-img ' + (extraClass || ''), 'aria-hidden': 'true' });
            box.appendChild(el('img', { src: logo, alt: '', loading: 'lazy' }));
            return box;
        }
        return el('span', { className: 'evx-os evx-os-' + (family || 'linux') + ' ' + (extraClass || ''), 'aria-hidden': 'true' }, letter || '?');
    }

    function optionCard(name, value, title, subtitle, logo, letter, family) {
        var label = el('label', { className: 'evx-option' });
        var input = el('input', { type: 'radio', name: name, value: value });
        label.appendChild(input);
        if (logo || letter) {
            label.appendChild(badge(logo, letter, family, 'evx-os-sm'));
        }
        var text = el('span', { className: 'evx-option-text' }, title);
        if (subtitle) {
            text.appendChild(el('small', null, subtitle));
        }
        label.appendChild(text);
        return label;
    }

    function updateReinstallButton() {
        var form = hook('reinstall-form');
        if (form) {
            form.querySelector('[type="submit"]').disabled = !(choice && form.elements.confirm.checked);
        }
    }

    loaders.reinstall = function loadReinstall(force) {
        load('reinstall_options', {}, 3600000, function (d) {
            var list = hook('os-list');
            clear(list);
            (d.groups || []).forEach(function (g) {
                var head = el('div', { className: 'evx-picker-group' });
                head.appendChild(badge(g.logo, (g.family || '?').charAt(0).toUpperCase(), g.icon, 'evx-os-xs'));
                head.appendChild(document.createTextNode(g.family));
                list.appendChild(head);
                g.items.forEach(function (os) {
                    list.appendChild(optionCard('evx-os', os.id, os.label, os.eol ? t('js_eol') : '', g.logo, (g.family || '?').charAt(0).toUpperCase(), g.icon));
                });
            });
            var apps = d.apps || [];
            var appList = hook('app-list');
            clear(appList);
            apps.forEach(function (a) {
                appList.appendChild(optionCard('evx-app', a.slug, a.name, a.tagline, a.logo, a.name.charAt(0).toUpperCase(), 'linux'));
            });
            hook('reinstall-mode').hidden = !apps.length;
        }).catch(function (err) { panelError('reinstall', err); });
    };

    segment('reinstall-mode', function (mode) {
        reinstallMode = mode;
        hook('os-list').hidden = mode !== 'os';
        hook('app-list').hidden = mode !== 'app';
        choice = null;
        all('.evx-option').forEach(function (o) {
            o.classList.remove('is-selected');
            o.querySelector('input').checked = false;
        });
        updateReinstallButton();
    });

    root.addEventListener('change', function (e) {
        var input = e.target;
        if (input.name === 'evx-os' || input.name === 'evx-app') {
            all('.evx-option').forEach(function (o) {
                o.classList.toggle('is-selected', o.querySelector('input') === input);
            });
            choice = input.name === 'evx-os' ? { os_id: input.value } : { app: input.value };
            updateReinstallButton();
        } else if (input.name === 'confirm') {
            updateReinstallButton();
        }
    });

    var reinstallForm = hook('reinstall-form');
    if (reinstallForm) {
        reinstallForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!choice || !reinstallForm.elements.confirm.checked) {
                return;
            }
            var btn = reinstallForm.querySelector('[type="submit"]');
            setBusy(btn, true);
            api('reinstall', choice).then(function (d) {
                toast(d.message || t('js_reinstall_started'), 'ok');
                setTimeout(function () { window.location.reload(); }, 1500);
            }, function (err) {
                fail(err);
                setBusy(btn, false);
            });
        });
    }

    // ------------------------------------------------------------------ start

    // ------------------------------------------------------------------ reverse DNS

    function rdnsForm(address) {
        return all('[data-rdns-form]').filter(function (f) { return f.getAttribute('data-rdns-form') === address; })[0];
    }

    function rdnsRow(address) {
        return all('[data-rdns-row]').filter(function (r) { return r.getAttribute('data-rdns-row') === address; })[0];
    }

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="rdns-edit"], [data-action="rdns-cancel"]');
        if (!btn) {
            return;
        }
        var address = btn.getAttribute('data-action') === 'rdns-edit'
            ? btn.closest('[data-rdns-row]').getAttribute('data-rdns-row')
            : btn.closest('[data-rdns-form]').getAttribute('data-rdns-form');
        var form = rdnsForm(address);
        var row = rdnsRow(address);
        var editing = btn.getAttribute('data-action') === 'rdns-edit';
        form.hidden = !editing;
        row.hidden = editing;
        if (editing) {
            form.elements.rdns.focus();
            form.elements.rdns.select();
        }
    });

    root.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-rdns-form]');
        if (!form) {
            return;
        }
        e.preventDefault();
        var address = form.getAttribute('data-rdns-form');
        var btn = form.querySelector('[type="submit"]');
        setBusy(btn, true);
        api('rdns_save', { address: address, rdns: form.elements.rdns.value.trim() }).then(function (d) {
            var row = rdnsRow(address);
            row.querySelector('[data-rdns-value]').textContent = d.rdns;
            form.elements.rdns.value = d.rdns;
            form.hidden = true;
            row.hidden = false;
            toast(d.message || t('js_saved'), 'ok');
        }, fail).then(function () { setBusy(btn, false); });
    });

    /** Start loading a tab's data before it is opened (hover, touch or keyboard focus on the tab). */
    function prefetch(name) {
        if (name && !loaded[name] && loaders[name]) {
            loaded[name] = true;
            loaders[name]();
        }
    }
    ['mouseover', 'focusin', 'touchstart'].forEach(function (type) {
        root.addEventListener(type, function (e) {
            var tab = e.target.closest ? e.target.closest('.evx-tab') : null;
            if (tab) {
                prefetch(tab.getAttribute('data-tab'));
            }
        }, { passive: true });
    });

    renderStatus(status);
    if (state === 'active') {
        schedule(400);
        var first = root.querySelector('.evx-tab.is-active');
        if (first) {
            activate(first.getAttribute('data-tab'));
        }
        // The OS/app catalogue is cached server-side per plan, so fetching it early is nearly free.
        var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 2500); };
        idle(function () { prefetch('reinstall'); });
    } else if (state === 'provisioning') {
        schedule(4000);
    }
})();
