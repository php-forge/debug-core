import {
  closest,
  escapeHtml,
  readStorageItem,
  requestStack,
  sameUrl,
  storageKey,
  themeAttributeFilter,
  themeStorageKey,
  toolbars,
  writeStorageItem,
} from "./state.js";
import {
  addThemeToUrl,
  delegateThemeToHost,
  getComputedTheme,
  getElementTheme,
  getStorageTheme,
  hostHasThemeControl,
  normalizeTheme,
  resetHostThemeControlCache,
  writeThemeCookie,
} from "./theme.js";
import { builtinIconUrl } from "./icons.js";
import {
  focusToolbarElement,
  focusToolbarTrigger,
  isToolbarDrawerCloseMessage,
  isToolbarDrawerThemeMessage,
  shouldCloseToolbarDrawer,
} from "./focus.js";
import {
  isToolbarLoadCurrent,
  resolveToolbarLoadGeneration,
  resolveToolbarLoadRollback,
  toolbarDataUrlForTag,
  toolbarRetryDelay,
} from "./loading.js";
import { renderPhpBrand, renderYiiBrand } from "./brand.js";
import {
  renderAjaxProfileLink,
  renderToolbarLinkAttributes,
  shouldOpenToolbarDrawer,
  toolbarItemTag,
  toolbarPanelContainerTag,
} from "./panel.js";
import {
  normalizeToolbarPosition,
  toolbarDrawerHeight,
  toolbarDrawerHeightForKey,
} from "./position.js";

/**
 * Styles injected into the toolbar's open shadow DOM. Authored in
 * toolbar-shadow.css (shared design tokens via tokens.css) and inlined into
 * this bundle at build time so the Web Component remains self-contained and
 * isolated from the host page's CSS.
 */
import toolbarStyles from "./toolbar-shadow.css?inline";

export function YiiDebugToolbar() {
  var self = Reflect.construct(HTMLElement, [], YiiDebugToolbar);
  self.attachShadow({ mode: "open" });
  self.data = null;
  self.ajaxRequests = requestStack;
  self.activeUrl = "";
  self.expanded = readStorageItem(storageKey) === "1";
  self.drawerOpen = false;
  self.restoreFocusUrl = null;
  self.resizing = false;
  self.currentTag = null;
  self.lastLoadedTag = null;
  self.lastLoadedUrl = null;
  self.loadGeneration = 0;
  self.boundPointerMove = self.onPointerMove.bind(self);
  self.boundPointerUp = self.onPointerUp.bind(self);
  self.boundHostThemeRefresh = self.refreshHostThemeControl.bind(self);
  self.boundThemeRefresh = self.refreshTheme.bind(self);
  self.theme = null;
  self.themeObserver = null;
  self.themeRefreshTimer = null;
  self.systemThemeQuery = null;
  /* Decided lazily in connectedCallback once the host DOM is available. */
  self.ownsTheme = false;

  return self;
}

YiiDebugToolbar.prototype = Object.create(HTMLElement.prototype);
YiiDebugToolbar.prototype.constructor = YiiDebugToolbar;
Object.setPrototypeOf(YiiDebugToolbar, HTMLElement);

YiiDebugToolbar.prototype.connectedCallback = function () {
  if (toolbars.indexOf(this) === -1) {
    toolbars.push(this);
  }

  resetHostThemeControlCache();
  this.ownsTheme = !hostHasThemeControl();
  this.refreshTheme();
  this.watchTheme();

  this.load();

  /**
   * SPA hosts (Vue/Inertia/React) mount their theme switcher AFTER this
   * callback runs, so the initial `hostHasThemeControl()` sweep can miss it.
   * Re-evaluate once the page settles. These discrete checkpoints invalidate
   * the host-control cache before refreshing the active theme.
   */
  window.addEventListener("load", this.boundHostThemeRefresh, false);
  this.themeRefreshTimer = window.setTimeout(this.boundHostThemeRefresh, 1500);
};

YiiDebugToolbar.prototype.disconnectedCallback = function () {
  var index = toolbars.indexOf(this);
  if (index !== -1) {
    toolbars.splice(index, 1);
  }

  if (this.themeObserver) {
    this.themeObserver.disconnect();
    this.themeObserver = null;
  }

  if (this.systemThemeQuery) {
    if (this.systemThemeQuery.removeEventListener) {
      this.systemThemeQuery.removeEventListener(
        "change",
        this.boundThemeRefresh,
      );
    } else if (this.systemThemeQuery.removeListener) {
      this.systemThemeQuery.removeListener(this.boundThemeRefresh);
    }
    this.systemThemeQuery = null;
  }

  window.removeEventListener("storage", this.boundThemeRefresh, false);
  window.removeEventListener("load", this.boundHostThemeRefresh, false);

  if (this.themeRefreshTimer !== null) {
    window.clearTimeout(this.themeRefreshTimer);
    this.themeRefreshTimer = null;
  }

  if (this.boundThemeMessage) {
    window.removeEventListener("message", this.boundThemeMessage, false);
    this.boundThemeMessage = null;
  }

  this.resizing = false;
  document.removeEventListener("pointermove", this.boundPointerMove, false);
  document.removeEventListener("pointerup", this.boundPointerUp, false);
};

YiiDebugToolbar.prototype.setAjaxRequests = function (requests) {
  this.ajaxRequests = requests;
  this.render();
};

YiiDebugToolbar.prototype.detectTheme = function () {
  /**
   * The current DOM state (`<html>`/`<body>` class or `data-theme` attr) is
   * the most authoritative signal — if the page IS rendering with a Tailwind
   * `dark` class then any stale `localStorage[yii-debug-toolbar-theme]` from
   * a previous session must lose.
   */
  var domTheme =
    getElementTheme(document.documentElement) || getElementTheme(document.body);

  if (domTheme) {
    return domTheme;
  }

  /**
   * The host manages its own theme and the document carries no marker: every
   * mainstream dark-mode convention (Tailwind class, `data-bs-theme`, Pico)
   * marks DARK explicitly, so an unmarked document is the host's light
   * state. Stale storage must not outvote what the page actually renders.
   */
  if (!this.ownsTheme) {
    return getComputedTheme() || "light";
  }

  return (
    normalizeTheme(this.getAttribute("data-yii-debug-theme")) ||
    normalizeTheme(readStorageItem(themeStorageKey)) ||
    getStorageTheme() ||
    getComputedTheme() ||
    (window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light")
  );
};

YiiDebugToolbar.prototype.toggleTheme = function () {
  var next = this.theme === "dark" ? "light" : "dark";

  if (delegateThemeToHost(next, this.detectTheme())) {
    this.refreshTheme();
    focusToolbarElement(this.shadowRoot, ".toggle-theme");

    return;
  }

  this.theme = next;
  this.setAttribute("data-theme", next);
  /* Pin the explicit choice so `detectTheme()` keeps honoring it. */
  this.setAttribute("data-yii-debug-theme", next);

  writeStorageItem(themeStorageKey, next);

  /**
   * Cookie is the backend's source of truth — write it so the next panel
   * navigation renders the matching theme even when the URL is bare.
   */
  writeThemeCookie(next);

  /**
   * Fan the change out to the surrounding page — covers Tailwind's `dark`
   * class on <html>, `data-theme`/`data-bs-theme` (Pico/Bootstrap), and the
   * common storage keys host apps read on boot when no native switcher can
   * own the application state.
   */
  this.propagateThemeToHost(next);
  this.render();
  focusToolbarElement(this.shadowRoot, ".toggle-theme");
};

/**
 * When the dev flips the theme via our own toggle (i.e. the host app does
 * NOT ship a switcher of its own) we best-effort fan the change out to the
 * signals most front-end stacks read so the surrounding page also flips.
 * None of these writes is destructive: if a token isn't recognized by the
 * host, it's simply ignored.
 */
YiiDebugToolbar.prototype.propagateThemeToHost = function (theme) {
  var html = document.documentElement;
  var opposite = theme === "dark" ? "light" : "dark";
  var storageKeys = [
    "theme",
    "color-theme",
    "color-scheme",
    "vueuse-color-scheme",
    "vite-ui-theme",
  ];
  var i;

  if (html) {
    /**
     * Tailwind-style modifier class (`<html class="dark">`) is the most
     * common convention; we keep `light`/`dark` mutually exclusive.
     */
    if (html.classList) {
      html.classList.add(theme);
      html.classList.remove(opposite);
    }
    /* Bootstrap 5 / Pico / generic CSS-token convention. */
    html.setAttribute("data-theme", theme);
    html.setAttribute("data-bs-theme", theme);
    html.style.colorScheme = theme;
  }

  for (i = 0; i < storageKeys.length; i++) {
    writeStorageItem(storageKeys[i], theme);
  }
};

YiiDebugToolbar.prototype.refreshHostThemeControl = function () {
  resetHostThemeControlCache();
  this.refreshTheme();
};

YiiDebugToolbar.prototype.refreshTheme = function () {
  /* Reuse the host-control result between explicit lifecycle checkpoints. */
  this.ownsTheme = !hostHasThemeControl();

  var theme = this.detectTheme();
  var previousTheme = this.theme;

  if (previousTheme === theme) {
    return;
  }

  this.theme = theme;
  this.setAttribute("data-theme", theme);

  writeStorageItem(themeStorageKey, theme);

  /**
   * Cookie is what the backend reads on the next debug request, so the panel
   * page renders with the correct theme even when the toolbar followed a
   * host change via the MutationObserver and the URL didn't carry
   * `yii_debug_theme`.
   */
  writeThemeCookie(theme);

  if (previousTheme && this.data) {
    this.render();
  }
};

YiiDebugToolbar.prototype.watchTheme = function () {
  var self = this;

  if (window.MutationObserver && !this.themeObserver) {
    this.themeObserver = new MutationObserver(function () {
      self.refreshTheme();
    });

    this.themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: themeAttributeFilter,
    });
    if (document.body) {
      this.themeObserver.observe(document.body, {
        attributes: true,
        attributeFilter: themeAttributeFilter,
      });
    }
  }

  if (window.matchMedia && !this.systemThemeQuery) {
    this.systemThemeQuery = window.matchMedia("(prefers-color-scheme: dark)");
    if (this.systemThemeQuery.addEventListener) {
      this.systemThemeQuery.addEventListener("change", this.boundThemeRefresh);
    } else if (this.systemThemeQuery.addListener) {
      this.systemThemeQuery.addListener(this.boundThemeRefresh);
    }
  }

  window.addEventListener("storage", this.boundThemeRefresh, false);

  /**
   * Receive theme flips from inside the panel iframe (the chip in the panel
   * header postMessages us) and apply them on the host instantly, without
   * waiting for the storage event.
   */
  if (!this.boundThemeMessage) {
    this.boundThemeMessage = function (event) {
      if (!event || event.origin !== window.location.origin) {
        return;
      }

      var data = event.data;
      var drawerFrame = self.shadowRoot.querySelector(".drawer iframe");

      if (
        isToolbarDrawerCloseMessage(
          event,
          window.location.origin,
          drawerFrame ? drawerFrame.contentWindow : null,
        )
      ) {
        self.closeDrawer();

        return;
      }

      if (
        !isToolbarDrawerThemeMessage(
          event,
          window.location.origin,
          drawerFrame ? drawerFrame.contentWindow : null,
        )
      ) {
        return;
      }

      var nextTheme = normalizeTheme(data.theme);

      if (!nextTheme || nextTheme === self.theme) {
        return;
      }

      if (delegateThemeToHost(nextTheme, self.detectTheme())) {
        self.refreshTheme();

        return;
      }

      self.theme = nextTheme;
      self.setAttribute("data-theme", nextTheme);
      /* Pin the explicit choice so `detectTheme()` keeps honoring it. */
      self.setAttribute("data-yii-debug-theme", nextTheme);

      writeStorageItem(themeStorageKey, nextTheme);

      /**
       * The flip originated inside the panel iframe; carry it to the cookie
       * so a fresh panel navigation (or a hard reload) lands on the same
       * theme.
       */
      writeThemeCookie(nextTheme);

      self.propagateThemeToHost(nextTheme);

      if (self.data) {
        self.render();
      }
    };
    window.addEventListener("message", this.boundThemeMessage, false);
  }
};

YiiDebugToolbar.prototype.withTheme = function (url) {
  var theme = this.theme || this.detectTheme();

  /**
   * Keep the cookie in lockstep with what the drawer is about to show, so
   * bare in-panel navigation (which resolves via the cookie) stays on the
   * same theme as the stamped entry URL.
   */
  writeThemeCookie(theme);

  return addThemeToUrl(url, theme);
};

YiiDebugToolbar.prototype.followTag = function (tag) {
  if (!tag || this.currentTag === tag) {
    return;
  }

  var url = this.getAttribute("data-url");

  if (!url) {
    return;
  }

  var previousUrl = url;
  var previousTag = this.currentTag;
  var nextUrl = toolbarDataUrlForTag(url, tag, window.location.href);

  if (!nextUrl || sameUrl(url, nextUrl)) {
    return;
  }

  this.currentTag = tag;
  this.setAttribute("data-url", nextUrl);
  this.load(function (ok) {
    if (ok) {
      return;
    }

    /**
     * The tag we tried to follow was rejected (404 — rotated out of history,
     * 500, etc.). Roll back so the toolbar keeps showing the last good data
     * instead of leaving the user with a broken state.
     */
    var rollback = resolveToolbarLoadRollback(
      this.lastLoadedUrl,
      this.lastLoadedTag,
      previousUrl,
      previousTag,
    );

    this.currentTag = rollback.tag;
    this.setAttribute("data-url", rollback.url);

    if (rollback.reload) {
      this.load();
    } else {
      this.render();
    }
  });
};

YiiDebugToolbar.prototype.load = function (done, attempt, generation) {
  var self = this;
  var url = this.getAttribute("data-url");
  var retryAttempt = typeof attempt === "number" ? attempt : 0;
  var requestGeneration = resolveToolbarLoadGeneration(
    this.loadGeneration,
    generation,
  );

  if (typeof generation !== "number") {
    this.loadGeneration = requestGeneration;
  }

  if (!isToolbarLoadCurrent(this.loadGeneration, requestGeneration)) {
    return;
  }

  var notify = function (ok) {
    if (typeof done === "function") {
      done.call(self, ok);
    }
  };

  if (!url) {
    this.renderError("Debug toolbar data URL is missing.");
    notify(false);
    return;
  }

  var xhr = new XMLHttpRequest();
  xhr.open("GET", url, true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
  xhr.setRequestHeader("Accept", "application/json");
  xhr.onreadystatechange = function () {
    if (xhr.readyState !== 4) {
      return;
    }

    if (!isToolbarLoadCurrent(self.loadGeneration, requestGeneration)) {
      return;
    }

    if (xhr.status !== 200) {
      var retryDelay = toolbarRetryDelay(xhr.status, retryAttempt);

      if (retryDelay !== null) {
        window.setTimeout(function () {
          self.load(done, retryAttempt + 1, requestGeneration);
        }, retryDelay);
        return;
      }

      /**
       * Don't render an error for stale-tag fetches that the caller is ready
       * to recover from; just signal failure.
       */
      if (typeof done !== "function") {
        var message;
        if (xhr.status === 404) {
          /**
           * Request was profiled but its tag has rotated out of the debug
           * history (or never made it to the manifest). Don't dump the raw
           * JSON body at the user.
           */
          message = "Debug data is no longer available for this request.";
        } else {
          /**
           * Try to read a structured `{error: "..."}` payload first, then
           * fall back to a generic message instead of leaking raw HTML/JSON.
           */
          try {
            var parsed = JSON.parse(xhr.responseText);
            message =
              (parsed && parsed.error) || "Unable to load debug toolbar data.";
          } catch {
            message = "Unable to load debug toolbar data.";
          }
        }
        self.renderError(message);
      }
      notify(false);
      return;
    }

    try {
      self.data = JSON.parse(xhr.responseText);
    } catch {
      self.renderError("Invalid debug toolbar data response.");
      notify(false);
      return;
    }

    self.currentTag = self.data && self.data.tag ? self.data.tag : null;
    self.lastLoadedTag = self.currentTag;
    self.lastLoadedUrl = url;

    self.render();
    self.dispatchAttachedEvent();
    notify(true);
  };
  xhr.send();
};

YiiDebugToolbar.prototype.dispatchAttachedEvent = function () {
  var event;

  if (typeof Event === "function") {
    event = new Event("yii.debug.toolbar_attached", { bubbles: true });
  } else {
    event = document.createEvent("Event");
    event.initEvent("yii.debug.toolbar_attached", true, true);
  }

  this.dispatchEvent(event);
};

YiiDebugToolbar.prototype.ensureShadowSkeleton = function () {
  if (this.contentRoot) {
    return;
  }

  var style = document.createElement("style");
  style.textContent = toolbarStyles;
  this.shadowRoot.appendChild(style);

  this.contentRoot = document.createElement("div");
  this.contentRoot.style.display = "contents";
  this.shadowRoot.appendChild(this.contentRoot);

  this.bindDelegatedEvents();
};

YiiDebugToolbar.prototype.renderError = function (message) {
  this.ensureShadowSkeleton();
  this.contentRoot.innerHTML =
    '<div class="toolbar expanded"><div class="bar"><strong>Yii Debugger</strong>' +
    '<span class="error-message">' +
    escapeHtml(message) +
    "</span></div></div>";
};

YiiDebugToolbar.prototype.getPosition = function () {
  return normalizeToolbarPosition(
    this.getAttribute("data-position") || (this.data && this.data.position),
  );
};

YiiDebugToolbar.prototype.render = function () {
  if (!this.data) {
    return;
  }

  this.ensureShadowSkeleton();

  var position = this.getPosition();
  this.setAttribute("data-position", position);
  var classes = ["toolbar", "position-" + position];

  if (this.expanded) {
    classes.push("expanded");
  }
  if (this.drawerOpen) {
    classes.push("drawer-open");
  }

  var profilingPanel = (this.data.items || []).find(function (p) {
    return p && p.id === "profiling";
  });
  var profilingChip = profilingPanel ? this.renderPanel(profilingPanel) : "";

  this.contentRoot.innerHTML =
    '<div class="' +
    classes.join(" ") +
    '">' +
    '<div class="bar">' +
    (this.expanded
      ? this.renderBrand() +
        profilingChip +
        this.renderAjaxPanel() +
        this.renderPanels(["profiling"]) +
        this.renderControls()
      : this.renderCollapsedOpener()) +
    "</div>" +
    this.renderDrawer(position) +
    "</div>";

  this.bindEvents();
  this.applyDrawerHeight();
};

YiiDebugToolbar.prototype.renderLogo = function () {
  var src = this.data.logo || this.data.logoFallback;
  return src
    ? '<img src="' + escapeHtml(src) + '" alt="" width="18" height="18">'
    : '<span class="brand-mark">Y</span>';
};

YiiDebugToolbar.prototype.renderCollapsedOpener = function () {
  var title = escapeHtml(this.data.title || "Yii Debugger");

  return (
    '<button type="button" class="brand brand-opener toggle-toolbar" title="Expand debug toolbar" aria-label="Expand debug toolbar">' +
    this.renderLogo() +
    '<span class="brand-text">' +
    title +
    '</span><span class="opener-icon">›</span></button>'
  );
};

YiiDebugToolbar.prototype.renderBrand = function () {
  var configUrl = this.data.configUrl || this.data.indexUrl;
  var yiiLink = renderYiiBrand(
    this.data.yiiVersion,
    configUrl ? this.withTheme(configUrl) : null,
    this.renderLogo(),
    escapeHtml,
  );

  var phpLink = "";
  if (this.data.phpVersion) {
    phpLink = renderPhpBrand(
      this.data.phpVersion,
      this.data.phpInfoUrl ? this.withTheme(this.data.phpInfoUrl) : null,
      this.iconHtml("php-alt", "panel-icon panel-icon-php"),
      escapeHtml,
    );
  }

  var divider = phpLink
    ? '<span class="brand-divider" aria-hidden="true"></span>'
    : "";

  return '<div class="brand">' + yiiLink + divider + phpLink + "</div>";
};

/*
 * Mirrors `Vocabulary::verb()` in `src/Helper/Vocabulary.php` — keep both sides
 * in sync.
 */
function ajaxStatusBadgeClass(request) {
  if (request.loading) {
    return "badge-loading";
  }
  var code = parseInt(request.statusCode, 10);
  if (code >= 500) return "badge-status-5xx";
  if (code >= 400) return "badge-status-4xx";
  if (code >= 300) return "badge-status-3xx";
  if (code >= 200) return "badge-status-2xx";
  return "badge-danger";
}

function ajaxVerbClass(method) {
  var verb = (method || "").toUpperCase();
  if (verb === "GET" || verb === "HEAD") return "verb-get";
  if (verb === "POST") return "verb-post";
  if (verb === "PUT" || verb === "PATCH") return "verb-put";
  if (verb === "DELETE") return "verb-delete";
  return "verb-other";
}

YiiDebugToolbar.prototype.renderAjaxPanel = function () {
  var status = "success";
  var requests = this.ajaxRequests || [];
  var recent = requests.slice(Math.max(0, requests.length - 20));
  var rows = "";
  var icon = this.iconHtml("ajax", "panel-icon");

  requests.forEach(function (request, index) {
    if (request.loading) {
      status = "loading";
    } else if (request.error && index > requests.length - 4) {
      status = "danger";
    }
  });

  recent.forEach(function (request) {
    var profileUrl = request.profilerUrl;
    var profile = renderAjaxProfileLink(
      request.profile,
      profileUrl,
      profileUrl ? this.withTheme(profileUrl) : null,
      escapeHtml,
    );

    rows +=
      '<tr><td><span class="' +
      ajaxVerbClass(request.method) +
      '">' +
      escapeHtml(request.method || "GET") +
      "</span></td>" +
      '<td><span class="badge ' +
      ajaxStatusBadgeClass(request) +
      '">' +
      escapeHtml(request.statusCode || "-") +
      "</span></td>" +
      '<td class="ajax-url" title="' +
      escapeHtml(request.url) +
      '">' +
      escapeHtml(request.url) +
      "</td>" +
      "<td>" +
      escapeHtml(request.duration ? request.duration + " ms" : "-") +
      "</td>" +
      "<td>" +
      profile +
      "</td></tr>";
  }, this);

  if (rows === "") {
    rows =
      '<tr><td colspan="5" class="empty">No AJAX requests tracked yet.</td></tr>';
  }

  return (
    '<div class="panel ajax-panel" role="group" tabindex="0" aria-label="AJAX requests: ' +
    requests.length +
    '">' +
    icon +
    '<span class="panel-title">AJAX</span>' +
    '<span class="metric"><span class="metric-value badge-' +
    status +
    '">' +
    requests.length +
    "</span></span>" +
    '<div class="ajax-popover"><table><thead><tr><th scope="col">Method</th><th scope="col">Status</th><th scope="col">URL</th><th scope="col">Time</th><th scope="col">Profile</th></tr></thead>' +
    "<tbody>" +
    rows +
    "</tbody></table></div></div>"
  );
};

YiiDebugToolbar.prototype.renderPanels = function (excludeIds) {
  var html = "";
  var items = this.data.items || [];
  var exclude = excludeIds || [];

  items.forEach(function (panel) {
    if (!panel || exclude.indexOf(panel.id) !== -1) {
      return;
    }
    html += this.renderPanel(panel);
  }, this);

  return '<div class="panels">' + html + "</div>";
};

YiiDebugToolbar.prototype.isPanelActive = function (panel) {
  var items = panel.items || [];

  if (!this.activeUrl) {
    return false;
  }
  if (panel.url && sameUrl(panel.url, this.activeUrl)) {
    return true;
  }

  for (var i = 0; i < items.length; i++) {
    if (items[i].url && sameUrl(items[i].url, this.activeUrl)) {
      return true;
    }
  }

  return false;
};

YiiDebugToolbar.prototype.iconHtml = function (iconName, cls) {
  if (!iconName || !this.data) {
    return "";
  }
  var url = builtinIconUrl(iconName);

  if (!url && this.data.iconBaseUrl) {
    url = this.data.iconBaseUrl + iconName + ".svg";
  }
  if (!url) {
    return "";
  }

  var escaped = escapeHtml(url);
  return (
    '<span class="' +
    cls +
    '" aria-hidden="true" style="-webkit-mask-image:url(' +
    escaped +
    ");mask-image:url(" +
    escaped +
    ')"></span>'
  );
};

YiiDebugToolbar.prototype.renderPanel = function (panel) {
  var metrics = "";
  var items = panel.items || [];
  var panelElement = toolbarPanelContainerTag(panel);
  var rawTitle =
    typeof panel.title === "string" ? panel.title : panel.id || "Panel";
  var hasTitle = rawTitle !== "";
  var attrTitle = hasTitle ? rawTitle : panel.id || "Panel";
  var panelClass = this.isPanelActive(panel) ? " panel-active" : "";
  var self = this;

  items.forEach(function (item) {
    var status = item.status || "default";
    var itemElement = toolbarItemTag(item);
    var itemUrl = renderToolbarLinkAttributes(
      item.url,
      item.url ? this.withTheme(item.url) : "",
      escapeHtml,
    );
    var itemTitle = item.title ? ' title="' + escapeHtml(item.title) + '"' : "";
    var metricClass =
      item.url && sameUrl(item.url, this.activeUrl) ? " metric-active" : "";

    metrics +=
      "<" +
      itemElement +
      ' class="metric' +
      metricClass +
      '"' +
      itemUrl +
      itemTitle +
      ">";
    if (item.icon) {
      metrics += self.iconHtml(item.icon, "metric-icon");
    } else if (item.label) {
      metrics +=
        '<span class="metric-label">' + escapeHtml(item.label) + "</span>";
    }
    metrics +=
      '<span class="metric-value badge-' +
      escapeHtml(status) +
      '">' +
      escapeHtml(item.value) +
      "</span></" +
      itemElement +
      ">";
  }, this);

  var iconHtml = panel.icon ? this.iconHtml(panel.icon, "panel-icon") : "";
  var panelLabel =
    iconHtml +
    (hasTitle
      ? '<span class="panel-title">' + escapeHtml(rawTitle) + "</span>"
      : "");
  var panelLinkAttributes = renderToolbarLinkAttributes(
    panel.url,
    panel.url ? this.withTheme(panel.url) : "",
    escapeHtml,
  );

  if (panel.url && panelElement !== "a") {
    panelLabel =
      '<a class="panel-link"' +
      panelLinkAttributes +
      ' aria-label="' +
      escapeHtml(attrTitle) +
      '">' +
      panelLabel +
      "</a>";
  }

  var panelAttributes =
    panelElement === "a"
      ? panelLinkAttributes
      : ' role="group" aria-label="' + escapeHtml(attrTitle) + '"';

  return (
    "<" +
    panelElement +
    ' class="panel' +
    panelClass +
    '" title="' +
    escapeHtml(attrTitle) +
    '"' +
    panelAttributes +
    ">" +
    panelLabel +
    metrics +
    "</" +
    panelElement +
    ">"
  );
};

YiiDebugToolbar.prototype.renderControls = function () {
  var externalIcon = this.iconHtml("external-link", "control-icon");
  var external = this.activeUrl
    ? '<a class="control" href="' +
      escapeHtml(this.withTheme(this.activeUrl)) +
      '" target="_blank" rel="noopener" title="Open panel in a new tab" aria-label="Open panel in a new tab">' +
      externalIcon +
      "</a>"
    : '<button type="button" class="control disabled" title="Open a panel first" aria-label="Open a panel first" disabled>' +
      externalIcon +
      "</button>";
  var drawer = this.drawerOpen
    ? '<button type="button" class="control close-drawer" title="Close panel" aria-label="Close panel">' +
      this.iconHtml("close", "control-icon") +
      "</button>"
    : "";
  var toggleTitle = this.expanded ? "Collapse toolbar" : "Expand toolbar";
  var toggleIcon = this.iconHtml(
    this.expanded ? "chevron-right" : "chevron-left",
    "control-icon",
  );

  var nextTheme = this.theme === "dark" ? "light" : "dark";
  var themeLabel = "Switch to " + nextTheme + " theme";
  /**
   * Show the icon that represents the *next* theme — click moves you toward
   * what you see. Reuses the same `mask-image` pipeline as the panel chips
   * so the glyph picks up `currentColor`.
   */
  var themeIcon = this.iconHtml(
    this.theme === "dark" ? "sun" : "moon",
    "control-icon",
  );
  var themeControl =
    '<button type="button" class="control toggle-theme" title="' +
    themeLabel +
    '" aria-label="' +
    themeLabel +
    '">' +
    themeIcon +
    "</button>";

  return (
    '<div class="controls">' +
    themeControl +
    external +
    drawer +
    '<button type="button" class="control toggle-toolbar" title="' +
    toggleTitle +
    '" aria-label="' +
    toggleTitle +
    '">' +
    toggleIcon +
    "</button></div>"
  );
};

YiiDebugToolbar.prototype.renderDrawer = function (position) {
  if (!this.drawerOpen || !this.activeUrl) {
    return "";
  }

  var handle =
    '<div class="resize-handle" role="separator" aria-label="Resize debug panel" aria-orientation="horizontal" tabindex="0"></div>';
  var drawer =
    '<div class="drawer" role="region" aria-label="Yii debug panel"><iframe src="' +
    escapeHtml(this.withTheme(this.activeUrl)) +
    '" title="Yii debug panel"></iframe></div>';

  return position === "top" ? drawer + handle : handle + drawer;
};

YiiDebugToolbar.prototype.bindDelegatedEvents = function () {
  var root = this.shadowRoot;
  var self = this;

  root.addEventListener("click", function (event) {
    var target = closest(event.target, "[data-debug-url]");
    var url = target ? target.getAttribute("data-debug-url") : null;

    if (!shouldOpenToolbarDrawer(event, url)) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    self.openPanel(url);
  });

  root.addEventListener("keydown", function (event) {
    if (!shouldCloseToolbarDrawer(event, self.drawerOpen)) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    self.closeDrawer();
  });
};

YiiDebugToolbar.prototype.bindEvents = function () {
  var root = this.shadowRoot;
  var toggle = root.querySelector(".toggle-toolbar");
  var toggleTheme = root.querySelector(".toggle-theme");
  var closeDrawer = root.querySelector(".close-drawer");
  var resizeHandle = root.querySelector(".resize-handle");
  var self = this;

  if (toggle) {
    toggle.addEventListener("click", function () {
      self.toggleExpanded();
    });
  }

  if (toggleTheme) {
    toggleTheme.addEventListener("click", function () {
      self.toggleTheme();
    });
  }

  if (closeDrawer) {
    closeDrawer.addEventListener("click", function () {
      self.closeDrawer();
    });
  }

  if (resizeHandle) {
    resizeHandle.addEventListener("keydown", function (event) {
      self.onResizeKeyDown(event);
    });
    resizeHandle.addEventListener(
      "pointerdown",
      function (event) {
        self.resizing = true;
        event.preventDefault();
        document.addEventListener("pointermove", self.boundPointerMove, false);
        document.addEventListener("pointerup", self.boundPointerUp, false);
      },
      false,
    );
  }
};

YiiDebugToolbar.prototype.toggleExpanded = function () {
  this.expanded = !this.expanded;
  writeStorageItem(storageKey, this.expanded ? "1" : "0");
  if (!this.expanded) {
    this.drawerOpen = false;
  }
  this.render();
  focusToolbarElement(this.shadowRoot, ".toggle-toolbar");
};

YiiDebugToolbar.prototype.openPanel = function (url) {
  if (!url) {
    return;
  }

  this.expanded = true;
  this.drawerOpen = true;
  this.activeUrl = url;
  this.restoreFocusUrl = url;
  writeStorageItem(storageKey, "1");
  this.render();
  focusToolbarElement(this.shadowRoot, ".close-drawer");
};

YiiDebugToolbar.prototype.closeDrawer = function () {
  var restoreFocusUrl = this.restoreFocusUrl;

  this.drawerOpen = false;
  this.restoreFocusUrl = null;
  this.render();

  if (!focusToolbarTrigger(this.shadowRoot, restoreFocusUrl)) {
    focusToolbarElement(this.shadowRoot, ".toggle-toolbar");
  }
};

YiiDebugToolbar.prototype.applyDrawerHeight = function () {
  var drawer = this.shadowRoot.querySelector(".drawer");

  if (!drawer) {
    return;
  }

  if (!this.style.getPropertyValue("--yii-debug-toolbar-drawer-height")) {
    var height = parseInt(
      this.getAttribute("data-height") || this.data.defaultHeight || 50,
      10,
    );
    this.style.setProperty(
      "--yii-debug-toolbar-drawer-height",
      Math.max(20, Math.min(90, height)) + "vh",
    );
  }

  this.updateResizeHandleAccessibility();
};

YiiDebugToolbar.prototype.onPointerMove = function (event) {
  if (!this.resizing) {
    return;
  }

  var position = this.getPosition();
  var drawer = this.shadowRoot.querySelector(".drawer");
  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight;
  var drawerRect = drawer ? drawer.getBoundingClientRect() : null;
  var height = toolbarDrawerHeight(
    position,
    event.clientY,
    viewportHeight,
    drawerRect,
  );

  this.style.setProperty(
    "--yii-debug-toolbar-drawer-height",
    Math.max(120, Math.min(viewportHeight - 48, height)) + "px",
  );
  this.updateResizeHandleAccessibility();
};

YiiDebugToolbar.prototype.onResizeKeyDown = function (event) {
  var drawer = this.shadowRoot.querySelector(".drawer");

  if (!drawer) {
    return;
  }

  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight;
  var height = toolbarDrawerHeightForKey(
    this.getPosition(),
    event.key,
    drawer.getBoundingClientRect().height,
    viewportHeight,
  );

  if (height === null) {
    return;
  }

  event.preventDefault();
  this.style.setProperty("--yii-debug-toolbar-drawer-height", height + "px");
  this.updateResizeHandleAccessibility();
};

YiiDebugToolbar.prototype.updateResizeHandleAccessibility = function () {
  var drawer = this.shadowRoot.querySelector(".drawer");
  var handle = this.shadowRoot.querySelector(".resize-handle");

  if (!drawer || !handle) {
    return;
  }

  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight;
  var minimum = 120;
  var maximum = Math.max(minimum, viewportHeight - 48);
  var current = Math.max(
    minimum,
    Math.min(maximum, Math.round(drawer.getBoundingClientRect().height)),
  );

  handle.setAttribute("aria-valuemin", String(minimum));
  handle.setAttribute("aria-valuemax", String(maximum));
  handle.setAttribute("aria-valuenow", String(current));
};

YiiDebugToolbar.prototype.onPointerUp = function () {
  this.resizing = false;
  document.removeEventListener("pointermove", this.boundPointerMove, false);
  document.removeEventListener("pointerup", this.boundPointerUp, false);
};

YiiDebugToolbar.prototype.getStyles = function () {
  return toolbarStyles;
};
