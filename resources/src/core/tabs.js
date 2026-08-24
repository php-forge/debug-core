function closest(element, selector) {
  if (element && element.nodeType !== 1) {
    element = element.parentElement;
  }

  return element && typeof element.closest === "function"
    ? element.closest(selector)
    : null;
}

function fragmentId(locationValue) {
  var hash =
    typeof locationValue === "string"
      ? new URL(locationValue).hash
      : locationValue && locationValue.hash
        ? locationValue.hash
        : "";

  if (!hash || hash === "#") {
    return "";
  }

  try {
    return decodeURIComponent(hash.slice(1));
  } catch {
    return "";
  }
}

function controlledPanel(link, root) {
  var targetId = link.getAttribute("aria-controls");

  if (!targetId) {
    var href = link.getAttribute("href") || "";
    targetId = href.charAt(0) === "#" ? href.slice(1) : "";
  }

  return targetId && root.getElementById ? root.getElementById(targetId) : null;
}

/**
 * Activates one tab while keeping the WAI-ARIA selected/hidden state in sync.
 */
export function activateTab(link, root) {
  var target = controlledPanel(link, root);

  if (!target) {
    return false;
  }

  var list = closest(link, ".yii-debug-tabs");
  var content = target.parentElement;
  var links = list
    ? list.querySelectorAll('[data-yii-debug-toggle="tab"]')
    : [];
  var panes = content ? content.children : [];
  var i;

  for (i = 0; i < links.length; i++) {
    links[i].classList.remove("is-active");
    links[i].setAttribute("aria-selected", "false");
    links[i].setAttribute("tabindex", "-1");
  }

  for (i = 0; i < panes.length; i++) {
    if (
      panes[i].classList &&
      panes[i].classList.contains("yii-debug-tab-panel")
    ) {
      panes[i].classList.remove("is-active");
      panes[i].hidden = true;
    }
  }

  link.classList.add("is-active");
  link.setAttribute("aria-selected", "true");
  link.setAttribute("tabindex", "0");
  target.classList.add("is-active");
  target.hidden = false;

  return true;
}

/**
 * Returns the URL for a selected tab without dropping request/filter state.
 */
export function tabUrl(currentHref, panelId) {
  var url = new URL(currentHref);
  url.hash = panelId;

  return url.href;
}

/**
 * Reveals the tab panel that owns the current fragment (or an element nested
 * inside it). This is used on initial load and history traversal.
 */
export function activateTabForFragment(root, locationValue) {
  var id = fragmentId(locationValue);
  var target = id && root.getElementById ? root.getElementById(id) : null;
  var panel = target
    ? target.classList && target.classList.contains("yii-debug-tab-panel")
      ? target
      : closest(target, ".yii-debug-tab-panel")
    : null;

  if (!panel) {
    return false;
  }

  var links = root.querySelectorAll('[data-yii-debug-toggle="tab"]');

  for (var i = 0; i < links.length; i++) {
    if (links[i].getAttribute("aria-controls") === panel.id) {
      return activateTab(links[i], root);
    }
  }

  return false;
}

/**
 * Initializes hash-aware tabs. User selection pushes a history entry, while
 * `hashchange`/`popstate` restore the matching panel for browser navigation.
 */
export function initTabs(root, windowValue) {
  function selectFromHistory() {
    activateTabForFragment(root, windowValue.location);
  }

  function select(link, focus) {
    if (!activateTab(link, root)) {
      return false;
    }

    var panelId = link.getAttribute("aria-controls");

    if (panelId && windowValue.history) {
      windowValue.history.pushState(
        null,
        "",
        tabUrl(windowValue.location.href, panelId),
      );
    }

    if (focus && typeof link.focus === "function") {
      link.focus();
    }

    return true;
  }

  root.addEventListener("click", function (event) {
    var tab = closest(event.target, '[data-yii-debug-toggle="tab"]');

    if (!tab) {
      return;
    }

    event.preventDefault();
    select(tab, false);
  });

  root.addEventListener("keydown", function (event) {
    var tab = closest(event.target, '[data-yii-debug-toggle="tab"]');

    if (
      !tab ||
      ["ArrowLeft", "ArrowRight", "Home", "End"].indexOf(event.key) === -1
    ) {
      return;
    }

    var tabList = closest(tab, '[role="tablist"]');
    var tabs = tabList
      ? Array.from(tabList.querySelectorAll('[data-yii-debug-toggle="tab"]'))
      : [];
    var current = tabs.indexOf(tab);
    var next = current;

    if (event.key === "Home") {
      next = 0;
    } else if (event.key === "End") {
      next = tabs.length - 1;
    } else if (event.key === "ArrowLeft") {
      next = (current - 1 + tabs.length) % tabs.length;
    } else if (event.key === "ArrowRight") {
      next = (current + 1) % tabs.length;
    }

    if (tabs[next]) {
      event.preventDefault();
      select(tabs[next], true);
    }
  });

  windowValue.addEventListener("hashchange", selectFromHistory);
  windowValue.addEventListener("popstate", selectFromHistory);
  selectFromHistory();
}
