function NoJQueryPostMessageMixin(e, t) {
    var n, o, i, r, s, c = 1;
    return window.postMessage ? (window.addEventListener ? (n = function(e) { window.addEventListener("message", e, !1) }, o = function(e) { window.removeEventListener("message", e, !1) }) : (n = function(e) { window.attachEvent("onmessage", e) }, o = function(e) { window.detachEvent("onmessage", e) }), this[e] = function(e, t, n) { t && n.postMessage(e, t.replace(/([^:]+:\/\/[^\/]+).*/, "$1")) }, this[t] = function(e, t, r) {
        return i && (o(i), i = null), !!e && void(i = n(function(n) {
            switch (Object.prototype.toString.call(t)) {
                case "[object String]":
                    if (t !== n.origin) return !1;
                    break;
                case "[object Function]":
                    if (t(n.origin)) return !1
            }
            e(n)
        }))
    }) : (this[e] = function(e, t, n) { t && (n.location = t.replace(/#.*$/, "") + "#" + +new Date + c++ + "&" + e) }, this[t] = function(e, t, n) {
        r && (clearInterval(r), r = null), e && (n = "number" == typeof t ? t : "number" == typeof n ? n : 100, r = setInterval(function() {
            var t = document.location.hash,
                n = /^#?\d+&/;
            t !== s && n.test(t) && (s = t, e({ data: t.replace(n, "") }))
        }, n))
    }), this
}! function(e) {
    var t = this;
    t.FS = t.FS || {}, e === t.FS.Logger && (t.FS.Logger = function() {
        for (var t = {}, n = ["log", "debug", "error"], o = 0; o < n.length; o++) {
            var i = n[o],
                r = i.charAt(0).toUpperCase() + i.slice(1);
            e === console[i] && (i = "log"),
                function(e) { t[r] = function() { Function.prototype.apply.call(console[e], console, arguments) } }(i)
        }
        return t
    }())
}(),
function(e, t) {
    var n = this;
    n.FS = n.FS || {}, null == n.FS.PostMessage && (n.FS.PostMessage = function() {
        var n, o, i, r = !1,
            s = !1,
            c = new NoJQueryPostMessageMixin("postMessage", "receiveMessage"),
            a = {},
            u = function(e) { o = e, i = e.substring(0, e.indexOf("/", "https://" === e.substring(0, "https://".length) ? 8 : 7)), l = "" !== e },
            g = function() {
                c.receiveMessage(function(e) {
                    var t;
                    try {
                        if (null != e && e.origin && (e.origin.indexOf("js.stripe.com") > 0 || e.origin.indexOf("www.paypal.com") > 0)) return;
                        if (e && e.data && "string" == typeof e.data && "{" === e.data.charAt(0) && (t = JSON.parse(e.data), a[t.type]))
                            for (var n = 0; n < a[t.type].length; n++) a[t.type][n](t.data)
                    } catch (o) { FS.Logger.Error("FS.PostMessage.receiveMessage", o.message), FS.Logger.Log(e.data) }
                }, n)
            },
            d = -1,
            l = "" !== o,
            f = e(window),
            p = e("html"),
            h = !0;
        try { h = window.self !== window.top } catch (v) {}
        return h && u(decodeURIComponent(document.location.hash.replace(/^#/, ""))), { init: function(e, t) { n = e, g(), FS.PostMessage.receiveOnce("forward", function(e) { window.location = e.url }), t = t || [], t.length > 0 && f.on("scroll", function() { for (var e = 0; e < t.length; e++) FS.PostMessage.postScroll(t[e]) }) }, init_child: function(t) { t && u(t), this.init(i), r = !0, s = !0, e(window).bind("load", function() { FS.PostMessage.postHeight(), FS.PostMessage.post("loaded") }), e(window).resize(function() { FS.PostMessage.postHeight(), FS.PostMessage.post("resize") }) }, hasParent: function() { return l }, postHeight: function(t, n) { t = t || 0, n = n || "#wrap_section"; var o = t + e(n).outerHeight(!0); return o != d && (this.post("height", { height: o }), d = o, !0) }, postScroll: function(e) { this.post("scroll", { top: f.scrollTop(), height: f.height() - parseFloat(p.css("paddingTop")) - parseFloat(p.css("marginTop")) }, e) }, post: function(e, t, n) { FS.Logger.Debug("PostMessage.post", e), n ? c.postMessage(JSON.stringify({ type: e, data: t }), n.src, n.contentWindow) : c.postMessage(JSON.stringify({ type: e, data: t }), o, window.parent) }, receive: function(e, t) { FS.Logger.Debug("PostMessage.receive", e), null == a[e] && (a[e] = []), a[e].push(t) }, receiveOnce: function(e, n, o) { o = t !== o && o, o && this.unset(e), this.is_set(e) || this.receive(e, n) }, is_set: function(e) { return null != a[e] }, unset: function(e) { a[e] = null }, parent_url: function() { return o }, parent_subdomain: function() { return i }, isChildInitialized: function() { return s } }
    }())
}(jQuery),
function(e, t) {
    var n = this;
    n.FS = n.FS || {}, n.FS.Checkout = function() {
        var o = !1,
            i = navigator.userAgent.toLowerCase();
        /edge\/|trident\/|msie /.test(i) ? (o = !0, FS.Logger.Log("browser", "ie")) : i.indexOf("safari") != -1 ? i.indexOf("chrome") > -1 ? FS.Logger.Log("browser", "chrome") : (o = !0, FS.Logger.Log("browser", "safari")) : FS.Logger.Log("browser", "other");
        var r, s, c = !1,
            a = function() { return null == location.port || "" === location.port || 80 == location.port },
            u = function() {
                for (var e = document.getElementsByTagName("script"), t = 0; t < e.length; t++)
                    if (-1 !== e[t].src.indexOf("//checkout.freemius")) {
                        var n = null,
                            o = e[t].src.substr("https://".length).indexOf("/");
                        return n = -1 === o ? e[t].src : e[t].src.substr(0, o + "https://".length), 0 === n.indexOf("http:") && a() && (n = n.replace("http:", "https:"), e[t].src = n), n
                    }
                return "https://checkout.freemius.com"
            },
            g = u(),
            d = function() { return Math.floor(65536 * (1 + Math.random())).toString(16).substring(1) },
            l = function() { return d() + d() + "-" + d() + "-" + d() + "-" + d() + "-" + d() + d() + d() },
            f = { mode: "dialog", guid: l() },
            p = 2147483647,
            h = function(t) { return t = e.extend({}, f, t), null == t.plugin_id && FS.Logger.Error("plugin_id must be configured."), null == t.public_key && FS.Logger.Error("public_key must be configured."), _(t, "timestamp", "s_ctx_ts"), _(t, "sandbox_token", "sandbox"), t },
            v = function(t) {
                var n = g + "/?" + y(t) + "#" + encodeURIComponent(document.location.href);
                s = e('<iframe id="' + t.guid + '" src="' + n + '" width="100%" height="100%" allowtransparency="true" frameborder="0" style="z-index: ' + (p - 1) + "; background: rgba(0,0,0,0.003); border: 0 none transparent; visibility: " + (o ? "hidden" : "visible") + '; margin: 0; padding: 0; position: fixed; left: 0px; top: 0px; width: 100%; height: 100%; -webkit-tap-highlight-color: transparent; overflow: hidden;"></iframe>').appendTo("body"), e('<div id="exit_intent_' + t.guid + '" style="z-index: ' + p + '; border: 0; background: transparent; position: fixed; padding: 0; margin: 0; height: 10px; left: 0; right: 0; width: 100%; top: 0;"></div>').appendTo("body"), c || (FS.PostMessage.init(g), m(), c = !0)
            },
            m = function() {
                var e, t = document.documentElement,
                    n = 300,
                    o = function(e) { return !(e.pageY > 20) },
                    i = function(t) { o(t) && (e = setTimeout(c, n)) },
                    r = function() { e && (clearTimeout(e), e = null) },
                    c = function() { s && FS.PostMessage.post("exit_intent", null, s[0]) };
                t.addEventListener("mouseleave", i), t.addEventListener("mouseenter", r)
            },
            b = function(t) { if (e(document.body).css("overflowX", r.x), e(document.body).css("overflowY", r.y), e("#exit_intent_" + t.guid).remove(), e("#" + t.guid).remove(), s = null, t.afterClose) try { t.afterClose() } catch (n) { FS.Logger.Log(n) } },
            w = function(t) {
                e("#loader_" + t.guid).show(), r = { x: e(document.body).css("overflowX"), y: e(document.body).css("overflowY") }, e(document.body).css("overflow", "hidden"), v(t), FS.PostMessage.receiveOnce("upgraded", function(e) {
                    if (t.success) try { t.success(e) } catch (n) { FS.Logger.Log(n) }
                    b(t)
                }, !0), FS.PostMessage.receiveOnce("purchaseCompleted", function(e) { if (t.purchaseCompleted) try { t.purchaseCompleted(e) } catch (n) { FS.Logger.Log(n) } }, !0), FS.PostMessage.receiveOnce("canceled", function(e) {
                    if (t.cancel) try { t.cancel(e) } catch (n) { FS.Logger.Log(n) }
                    b(t)
                }, !0), FS.PostMessage.receiveOnce("track", function(e) { if (t.track) try { t.track(e.event, e) } catch (n) { FS.Logger.Log(n) } }, !0), FS.PostMessage.receiveOnce("loaded", function(n) { e("#loader_" + t.guid).hide(), o && e("#" + t.guid).ready(function() { e("#" + t.guid).css("visibility", "visible") }) }, !0)
            },
            y = function(e) { var t = ""; for (var n in e) !e.hasOwnProperty(n) || "function" == typeof e[n] || "object" == typeof e[n] && null !== e[n] || (t += "&" + n + "=" + S(e[n])); return t.length > 0 && (t = t.substr(1)), t },
            S = function(e) { return null === e ? "null" : !0 === e ? "1" : !1 === e ? "0" : encodeURIComponent(e).replace(/!/g, "%21").replace(/'/g, "%27").replace(/\(/g, "%28").replace(/\)/g, "%29").replace(/\*/g, "%2A").replace(/%20/g, "+") },
            _ = function(e, n, o) { t !== e[n] && (e[o] = e[n], delete e[n]) },
            F = function() { e(document.body).append('<div id="loader_' + f.guid + '" style="                display: none;                position: fixed;                z-index: ' + (p - 1) + ';                width: 100%;                height: 100%;                top: 0;                left: 0;                right: 0;                bottom: 0;                text-align: left;                background: rgba(0,0,0,0.6);                ">                <img src="' + g + '/assets/img/spinner.svg" alt="Loading animation" style="                position: absolute;                top: 40%;                left: 50%;                width: auto;                height: auto;                margin-left: -25px;                background: #fff;                padding: 10px;                border-radius: 50%;                box-shadow: 2px 2px 2px rgba(0,0,0,0.1);                ">                </div>') },
            x = function() {
                var e = window.location.toString(),
                    t = e.indexOf("__fs_authorization="),
                    n = e.indexOf("?");
                if (0 < n && n < t) {
                    var o = e.indexOf("#");
                    0 < o && (e = e.substr(0, o));
                    var i, r = e.split("?")[1],
                        s = {},
                        c = 0,
                        a = new RegExp("\\+", "g");
                    r = r.split("&");
                    for (var u = 0; u < r.length; u++) i = r[u].split("="), i[0].length > 5 && "__fs_" == i[0].substr(0, 5) && (s[i[0]] = decodeURIComponent(i[1].replace(a, " ")), c++);
                    if (c > 1) return s
                }
                return !1
            };
        return { configure: function(e) { f = h(e || {}), F(); var o = x(); return !1 !== o && (t !== o.__fs_plugin_id && _(o, "__fs_plugin_id", "plugin_id"), t !== o.__fs_plugin_public_key && _(o, "__fs_plugin_public_key", "public_key"), this.open(o)), n.FS.Checkout }, open: function(e) { w(h(e)) }, close: function() { FS.PostMessage.post("close", null, s[0]) }, clearOptions: function() { f = { mode: f.mode, guid: f.guid } } }
    }()
}(jQuery);