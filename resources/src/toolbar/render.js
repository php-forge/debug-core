import { escapeHtml, sameUrl } from "./state.js";
import { builtinIconUrl } from "./icons.js";
import { extensionsBadgeStatus } from "./extensions.js";
import { renderPhpBrand, renderYiiBrand } from "./brand.js";
import {
  renderToolbarItemIdentifier,
  renderToolbarLinkAttributes,
  toolbarItemTag,
  toolbarPanelContainerTag,
} from "./panel.js";
import { normalizeToolbarUrl } from "./url.js";

/**
 * Stateless HTML builders for the toolbar shadow DOM.
 *
 * Every builder takes a view — the snapshot of element state the markup reads —
 * and returns a string, so the element only composes fragments and writes them
 * to its shadow roots.
 *
 * Usage example:
 *
 * ```js
 * var view = createToolbarView(element);
 *
 * element.barRoot.innerHTML = renderBrand(view) + renderControls(view);
 * ```
 */

/** Class carried by every icon rendered inside a toolbar control. */
var controlIconClass = "control-icon";

/** Rows the AJAX menu lists; older entries stay counted on the chip. */
var ajaxMenuLimit = 20;

/**
 * Snapshots the element state the builders read, including the theme stamping
 * they apply to every navigable URL.
 */
export function createToolbarView(toolbar) {
  return {
    activeUrl: toolbar.activeUrl,
    ajaxRequests: toolbar.ajaxRequests,
    data: toolbar.data,
    drawerOpen: toolbar.drawerOpen,
    expanded: toolbar.expanded,
    openMenu: toolbar.openMenu,
    theme: toolbar.theme,
    withTheme: function (url) {
      return toolbar.themeController.withTheme(url);
    },
  };
}

/**
 * Returns the mask-image span for a glyph, resolved from the builtin inventory
 * first and from the adapter-provided icon directory second.
 */
export function iconHtml(view, iconName, cls) {
  if (!iconName || !view.data) {
    return "";
  }
  var url = builtinIconUrl(iconName);

  if (!url && view.data.iconBaseUrl) {
    url = view.data.iconBaseUrl + iconName + ".svg";
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
}

export function renderLogo(view) {
  var src = view.data.logo || view.data.logoFallback;
  return src
    ? '<img src="' + escapeHtml(src) + '" alt="" width="18" height="18">'
    : '<span class="brand-mark">Y</span>';
}

export function renderCollapsedOpener(view) {
  var title = escapeHtml(view.data.title || "Yii Debugger");

  return (
    '<button type="button" class="brand brand-opener toggle-toolbar" title="Expand debug toolbar" aria-label="Expand debug toolbar">' +
    renderLogo(view) +
    '<span class="brand-text">' +
    title +
    '</span><span class="opener-icon">›</span></button>'
  );
}

export function renderBrand(view) {
  var configUrl = view.data.configUrl || view.data.indexUrl;
  var yiiLink = renderYiiBrand(
    view.data.yiiVersion,
    configUrl ? view.withTheme(configUrl) : null,
    renderLogo(view),
    escapeHtml,
  );

  var phpLink = "";
  if (view.data.phpVersion) {
    phpLink = renderPhpBrand(
      view.data.phpVersion,
      view.data.phpInfoUrl ? view.withTheme(view.data.phpInfoUrl) : null,
      iconHtml(view, "php-alt", "panel-icon panel-icon-php"),
      escapeHtml,
    );
  }

  var divider = phpLink
    ? '<span class="brand-divider" aria-hidden="true"></span>'
    : "";

  return '<div class="brand">' + yiiLink + divider + phpLink + "</div>";
}

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

/**
 * Returns the badge status of the AJAX counter: a request in flight shows as
 * loading, and a failure among the last three raises the chip.
 */
function ajaxBadgeStatus(requests) {
  var status = "success";

  requests.forEach(function (request, index) {
    if (request.loading) {
      status = "loading";
    } else if (request.error && index > requests.length - 4) {
      status = "danger";
    }
  });

  return status;
}

/**
 * Renders one AJAX menu row. A profiled request links to its own capture and
 * doubles as a drawer trigger; a request the debugger did not capture stays
 * inert.
 *
 * @returns {{active: boolean, html: string}} The row and whether the drawer
 * shows its capture.
 */
function renderAjaxRequest(view, request) {
  var url = request.profilerUrl;
  var attributes = renderToolbarLinkAttributes(
    url,
    url ? view.withTheme(url) : "",
    escapeHtml,
  );
  var active = Boolean(url && view.activeUrl) && sameUrl(url, view.activeUrl);
  var element = attributes === "" ? "span" : "a";

  return {
    active: active,
    html:
      "<" +
      element +
      ' class="ajax-request' +
      (active ? " is-active" : "") +
      '"' +
      attributes +
      ' title="' +
      escapeHtml(request.url) +
      '">' +
      '<span class="ajax-verb ' +
      ajaxVerbClass(request.method) +
      '">' +
      escapeHtml(request.method || "GET") +
      "</span>" +
      '<span class="badge ' +
      ajaxStatusBadgeClass(request) +
      '">' +
      escapeHtml(request.statusCode || "-") +
      "</span>" +
      '<span class="ajax-url">' +
      escapeHtml(request.url) +
      "</span>" +
      '<span class="ajax-time">' +
      escapeHtml(request.duration ? request.duration + " ms" : "-") +
      "</span></" +
      element +
      ">",
  };
}

/**
 * Renders the AJAX chip and the menu listing the tracked requests.
 *
 * The chip sits with the Extensions group at the end of the bar: the stack is
 * runtime state of the page, not a panel of its captured request. Each row
 * opens the capture of its own request in the drawer, so the chips keep
 * reporting the page request whatever the page fetches afterwards.
 */
export function renderAjaxMenu(view) {
  var requests = view.ajaxRequests || [];
  var recent = requests.slice(Math.max(0, requests.length - ajaxMenuLimit));
  var open = view.openMenu === "ajax";
  var active = false;
  var rows = "";

  recent.forEach(function (request) {
    var row = renderAjaxRequest(view, request);

    active = active || row.active;
    rows += row.html;
  });

  if (rows === "") {
    rows = '<span class="empty">No AJAX requests tracked yet.</span>';
  }

  return (
    '<div class="ajax menu' +
    (open ? " is-open" : "") +
    '"><button type="button" class="panel menu-toggle ajax-toggle' +
    (active ? " panel-active" : "") +
    '" data-menu="ajax" aria-expanded="' +
    (open ? "true" : "false") +
    '" aria-controls="ajax-menu" aria-label="AJAX requests: ' +
    requests.length +
    '" title="AJAX requests">' +
    iconHtml(view, "ajax", "panel-icon") +
    '<span class="panel-title">AJAX</span>' +
    '<span class="metric"><span class="metric-value badge-' +
    ajaxBadgeStatus(requests) +
    '">' +
    requests.length +
    "</span></span></button>" +
    '<div class="menu-list ajax-menu" id="ajax-menu" role="group" aria-label="AJAX requests">' +
    rows +
    "</div></div>"
  );
}

export function renderPanels(view, panels) {
  var html = "";

  panels.forEach(function (panel) {
    html += renderPanel(view, panel);
  });

  return '<div class="panels">' + html + "</div>";
}

/**
 * Groups the provider-owned panels under a single chip at the end of the bar.
 *
 * The menu is rendered OUTSIDE `.panels` on purpose: that strip scrolls
 * horizontally (`overflow: auto hidden`) and would clip a popup anchored to
 * one of its children.
 */
export function renderExtensions(view, extensions) {
  if (extensions.length === 0) {
    return "";
  }

  var html = "";
  var active = false;
  var open = view.openMenu === "extensions";

  extensions.forEach(function (panel) {
    if (isPanelActive(view, panel)) {
      active = true;
    }

    html += renderPanel(view, panel);
  });

  return (
    '<div class="extensions menu' +
    (open ? " is-open" : "") +
    '"><button type="button" class="panel menu-toggle extensions-toggle' +
    (active ? " panel-active" : "") +
    '" data-menu="extensions" aria-expanded="' +
    (open ? "true" : "false") +
    '" aria-controls="extensions-menu" title="Extensions">' +
    iconHtml(view, "dots", "panel-icon") +
    '<span class="panel-title">Extensions</span>' +
    '<span class="metric"><span class="metric-value badge-' +
    escapeHtml(extensionsBadgeStatus(extensions)) +
    '">' +
    escapeHtml(extensions.length) +
    "</span></span></button>" +
    '<div class="menu-list extensions-menu" id="extensions-menu" role="group" aria-label="Extensions">' +
    html +
    "</div></div>"
  );
}

/** Reports whether a panel, or one of its metrics, points at the open panel. */
export function isPanelActive(view, panel) {
  var items = panel.items || [];
  var panelUrl = normalizeToolbarUrl(panel.url);

  if (!view.activeUrl) {
    return false;
  }
  if (panelUrl && sameUrl(panelUrl, view.activeUrl)) {
    return true;
  }

  for (var i = 0; i < items.length; i++) {
    var itemUrl = normalizeToolbarUrl(items[i].url);

    if (itemUrl && sameUrl(itemUrl, view.activeUrl)) {
      return true;
    }
  }

  return false;
}

export function renderPanel(view, panel) {
  var metrics = "";
  var items = panel.items || [];
  var panelUrl = normalizeToolbarUrl(panel.url);
  var panelElement = toolbarPanelContainerTag(panel);
  var rawTitle =
    typeof panel.title === "string" ? panel.title : panel.id || "Panel";
  var hasTitle = rawTitle !== "";
  var attrTitle = hasTitle ? rawTitle : panel.id || "Panel";
  var panelClass = isPanelActive(view, panel) ? " panel-active" : "";

  items.forEach(function (item) {
    var status = item.status || "default";
    var normalizedItemUrl = normalizeToolbarUrl(item.url);
    var itemUrl = renderToolbarLinkAttributes(
      normalizedItemUrl,
      normalizedItemUrl ? view.withTheme(normalizedItemUrl) : "",
      escapeHtml,
    );
    var itemElement = toolbarItemTag(item);
    var itemTitle = item.title ? ' title="' + escapeHtml(item.title) + '"' : "";
    var itemIdentifier = renderToolbarItemIdentifier(item, escapeHtml);
    var metricClass =
      normalizedItemUrl && sameUrl(normalizedItemUrl, view.activeUrl)
        ? " metric-active"
        : "";

    metrics +=
      "<" +
      itemElement +
      ' class="metric' +
      metricClass +
      '"' +
      itemIdentifier +
      itemUrl +
      itemTitle +
      ">";
    if (item.icon) {
      metrics += iconHtml(view, item.icon, "metric-icon");
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
  });

  var panelIcon = panel.icon ? iconHtml(view, panel.icon, "panel-icon") : "";
  var panelLabel =
    panelIcon +
    (hasTitle
      ? '<span class="panel-title">' + escapeHtml(rawTitle) + "</span>"
      : "");
  var panelLinkAttributes = renderToolbarLinkAttributes(
    panelUrl,
    panelUrl ? view.withTheme(panelUrl) : "",
    escapeHtml,
  );

  if (panelUrl && panelElement !== "a") {
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
}

export function renderControls(view) {
  var externalIcon = iconHtml(view, "external-link", controlIconClass);
  var external = view.activeUrl
    ? '<a class="control" href="' +
      escapeHtml(view.withTheme(view.activeUrl)) +
      '" target="_blank" rel="noopener" title="Open panel in a new tab" aria-label="Open panel in a new tab">' +
      externalIcon +
      "</a>"
    : '<button type="button" class="control disabled" title="Open a panel first" aria-label="Open a panel first" disabled>' +
      externalIcon +
      "</button>";
  var drawer = view.drawerOpen
    ? '<button type="button" class="control close-drawer" title="Close panel" aria-label="Close panel">' +
      iconHtml(view, "close", controlIconClass) +
      "</button>"
    : "";
  var toggleTitle = view.expanded ? "Collapse toolbar" : "Expand toolbar";
  var toggleIcon = iconHtml(
    view,
    view.expanded ? "chevron-right" : "chevron-left",
    controlIconClass,
  );

  var nextTheme = view.theme === "dark" ? "light" : "dark";
  var themeLabel = "Switch to " + nextTheme + " theme";
  /**
   * Show the icon that represents the *next* theme — click moves you toward
   * what you see. Reuses the same `mask-image` pipeline as the panel chips
   * so the glyph picks up `currentColor`.
   */
  var themeIcon = iconHtml(
    view,
    view.theme === "dark" ? "sun" : "moon",
    controlIconClass,
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
}

export function renderDrawer(view, position) {
  if (!view.drawerOpen || !view.activeUrl) {
    return "";
  }

  var handle =
    '<div class="resize-handle" role="separator" aria-label="Resize debug panel" aria-orientation="horizontal" tabindex="0"></div>';
  var drawer =
    '<div class="drawer" role="region" aria-label="Yii debug panel"><iframe src="' +
    escapeHtml(view.withTheme(view.activeUrl)) +
    '" title="Yii debug panel"></iframe></div>';

  return position === "top" ? drawer + handle : handle + drawer;
}

/** Returns the bar content shown when a snapshot could not be rendered. */
export function renderErrorMessage(message) {
  return (
    "<strong>Yii Debugger</strong>" +
    '<span class="error-message">' +
    escapeHtml(message) +
    "</span>"
  );
}
