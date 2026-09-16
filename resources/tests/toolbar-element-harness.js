/**
 * Shared jsdom scaffolding for the `<yii-debug-toolbar>` element tests.
 *
 * jsdom ships custom elements, shadow DOM, cookies and `XMLHttpRequest`, but it
 * has no `matchMedia`, and Node's experimental `localStorage` accessor shadows
 * the jsdom one. Both are installed from here — never from the source — so the
 * element keeps its browser assumptions untouched.
 */
import { YiiDebugToolbar } from "../src/toolbar/element.js";
import { tagName } from "../src/toolbar/state.js";

/**
 * Installs a synchronous in-memory `localStorage` and returns its backing map,
 * so a test can seed it and read back what the element persisted.
 */
export function installLocalStorage(initial) {
  var items = new Map(Object.entries(initial || {}));

  Object.defineProperty(window, "localStorage", {
    configurable: true,
    writable: true,
    value: {
      getItem(key) {
        return items.has(key) ? items.get(key) : null;
      },
      removeItem(key) {
        items.delete(key);
      },
      setItem(key, value) {
        items.set(key, String(value));
      },
    },
  });

  return items;
}

/**
 * Installs a `matchMedia` stub. `legacy` exposes only the deprecated
 * `addListener`/`removeListener` pair and `inert` exposes neither, which is how
 * the element's two fallback paths are reached.
 */
export function installMatchMedia(options) {
  var settings = options || {};
  var listeners = [];
  var query = {
    listeners: listeners,
    matches: settings.matches === true,
    media: "(prefers-color-scheme: dark)",
  };

  if (settings.legacy === true) {
    query.addListener = function (listener) {
      listeners.push(listener);
    };
    query.removeListener = function (listener) {
      listeners.splice(listeners.indexOf(listener), 1);
    };
  } else if (settings.inert !== true) {
    query.addEventListener = function (type, listener) {
      listeners.push(listener);
    };
    query.removeEventListener = function (type, listener) {
      listeners.splice(listeners.indexOf(listener), 1);
    };
  }

  window.matchMedia = function () {
    return query;
  };

  return query;
}

export function removeMatchMedia() {
  delete window.matchMedia;
}

/**
 * Replaces `XMLHttpRequest` with a fake whose responses the test drives, so
 * `load()` never reaches the network.
 */
export function installXmlHttpRequest() {
  var original = window.XMLHttpRequest;
  var requests = [];

  function FakeXmlHttpRequest() {
    this.headers = {};
    this.readyState = 0;
    this.responseText = "";
    this.sent = false;
    this.status = 0;
    requests.push(this);
  }

  FakeXmlHttpRequest.prototype.open = function (method, url, async) {
    this.async = async;
    this.method = method;
    this.url = url;
  };

  FakeXmlHttpRequest.prototype.setRequestHeader = function (name, value) {
    this.headers[name] = value;
  };

  FakeXmlHttpRequest.prototype.send = function () {
    this.sent = true;
  };

  /** Drives the element's `onreadystatechange` with a completed response. */
  FakeXmlHttpRequest.prototype.respond = function (status, body) {
    this.readyState = 4;
    this.responseText = typeof body === "string" ? body : "";
    this.status = status;
    this.onreadystatechange();
  };

  /** Drives an intermediate ready state the element must ignore. */
  FakeXmlHttpRequest.prototype.progress = function (readyState) {
    this.readyState = readyState;
    this.onreadystatechange();
  };

  window.XMLHttpRequest = FakeXmlHttpRequest;

  return {
    requests: requests,
    last() {
      return requests[requests.length - 1];
    },
    restore() {
      window.XMLHttpRequest = original;
    },
  };
}

export function defineToolbar() {
  if (!window.customElements.get(tagName)) {
    window.customElements.define(tagName, YiiDebugToolbar);
  }
}

/** Creates an upgraded, detached toolbar carrying the given attributes. */
export function createToolbar(attributes) {
  defineToolbar();

  var element = document.createElement(tagName);
  var names = Object.keys(attributes || {});

  for (var i = 0; i < names.length; i++) {
    element.setAttribute(names[i], attributes[names[i]]);
  }

  return element;
}

/** Creates a toolbar and attaches it, so `connectedCallback()` runs. */
export function connectToolbar(attributes) {
  var element = createToolbar(attributes);

  document.body.appendChild(element);

  return element;
}

/** Renders `payload` through an attached toolbar and returns the element. */
export function renderToolbar(payload, attributes) {
  var element = connectToolbar(attributes);

  element.data = payload;
  element.render();

  return element;
}

/** Minimal toolbar payload; `overrides` replaces any top-level key. */
export function toolbarPayload(overrides) {
  return Object.assign(
    {
      items: [],
      title: "Yii Debugger",
      yiiVersion: "3.0.0",
    },
    overrides || {},
  );
}

/** Forces a laid-out height on an element jsdom would report as zero. */
export function stubBoundingHeight(element, rect) {
  element.getBoundingClientRect = function () {
    return Object.assign(
      { bottom: 0, height: 0, left: 0, right: 0, top: 0, width: 0, x: 0, y: 0 },
      rect,
    );
  };
}

/** Overrides `window.innerHeight`, which jsdom exposes as a getter. */
export function stubViewportHeight(height) {
  Object.defineProperty(window, "innerHeight", {
    configurable: true,
    value: height,
    writable: true,
  });
}

/**
 * Lends the drawer frame a browsing context: jsdom creates none for an iframe
 * living inside a shadow root, so `contentWindow` would stay `null`.
 */
export function stubFrameWindow(frame) {
  var donor = document.createElement("iframe");

  document.body.appendChild(donor);
  Object.defineProperty(frame, "contentWindow", {
    configurable: true,
    value: donor.contentWindow,
  });

  return donor.contentWindow;
}

export function shadowHtml(element) {
  return element.shadowRoot.innerHTML;
}
