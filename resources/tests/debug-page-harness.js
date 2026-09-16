/**
 * Shared jsdom scaffolding for the debugger page bootstrap (`core/debug.js`).
 *
 * `debug.js` is a side-effect module: importing it runs the whole page
 * bootstrap against the ambient `window` and `document` and leaves its
 * listeners on that document forever. Every scenario therefore needs a pristine
 * page, which `bootDebugPage()` builds from a fresh jsdom instance, publishes as
 * the ambient globals and then re-imports the module against.
 *
 * Facilities the source assumes but jsdom does not provide are installed from
 * here — never from the source — through a window facade that inherits from the
 * real jsdom window: `matchMedia`, a `location` that records assignments instead
 * of refusing to navigate, `parent`/`frameElement` for the drawer iframe, and a
 * `navigator.clipboard`. Node's experimental `localStorage` accessor shadows the
 * jsdom one, so an in-memory store is redefined over both globals.
 */
import { JSDOM } from "jsdom";
import { vi } from "vitest";

export const FILTER_FOCUS_KEY = "yii-debug-grid-filter-focus";

const DEFAULT_URL = "https://debug.test/debug/index";
const LOCATION_KEYS = [
  "hash",
  "host",
  "hostname",
  "href",
  "origin",
  "pathname",
  "port",
  "protocol",
  "search",
];

var openDom = null;

function define(target, name, value) {
  Object.defineProperty(target, name, {
    configurable: true,
    enumerable: true,
    value: value,
    writable: true,
  });
}

/** Mirrors the real location for reads and records every assignment. */
function createLocation(win, navigations, href) {
  var location = {};

  LOCATION_KEYS.forEach(function (key) {
    Object.defineProperty(location, key, {
      configurable: true,
      enumerable: true,
      get: function () {
        return key === "href" && typeof href === "string"
          ? href
          : win.location[key];
      },
      set: function (value) {
        navigations.push(String(value));
      },
    });
  });

  return location;
}

function createStorage(initial) {
  var items = new Map(Object.entries(initial || {}));

  return {
    items: items,
    getItem(key) {
      return items.has(key) ? items.get(key) : null;
    },
    removeItem(key) {
      items.delete(key);
    },
    setItem(key, value) {
      items.set(key, String(value));
    },
  };
}

function installSessionStorage(facade, options) {
  if (options.session === "throws") {
    Object.defineProperty(facade, "sessionStorage", {
      configurable: true,
      enumerable: true,
      get() {
        throw new Error("Session storage is blocked.");
      },
    });

    return null;
  }

  var storage = options.session || createStorage(options.sessionData);

  define(facade, "sessionStorage", storage);

  return storage;
}

function installMatchMedia(facade, preference) {
  if (preference === undefined) {
    return;
  }

  define(facade, "matchMedia", function (media) {
    return { matches: preference === "dark", media: media };
  });
}

function installFrameElement(facade, documentValue, frameElement) {
  if (frameElement === "throws") {
    Object.defineProperty(facade, "frameElement", {
      configurable: true,
      enumerable: true,
      get() {
        throw new Error("Cross-origin frame access is denied.");
      },
    });

    return;
  }

  define(
    facade,
    "frameElement",
    typeof frameElement === "function" ? frameElement(documentValue) : null,
  );
}

/**
 * Builds the drawer iframe the toolbar renders: a frame living inside a shadow
 * root whose host carries the toolbar theme.
 */
export function shadowHostFrame(theme) {
  return function (documentValue) {
    var host = documentValue.createElement("div");
    var frame = documentValue.createElement("iframe");

    if (theme !== undefined) {
      host.setAttribute("data-theme", theme);
    }

    documentValue.body.appendChild(host);
    host.attachShadow({ mode: "open" }).appendChild(frame);

    return frame;
  };
}

/** Builds a frame element that reports no root node, as a detached one would. */
export function rootlessFrame() {
  return function () {
    return {};
  };
}

/** Builds a frame whose root node is a plain document, so it has no host. */
export function hostlessFrame() {
  return function (documentValue) {
    var frame = documentValue.createElement("iframe");

    documentValue.body.appendChild(frame);

    return frame;
  };
}

/**
 * Renders a page and runs the `core/debug.js` bootstrap against it.
 *
 * Every option describes the browser state the bootstrap reads: `body` (or the
 * whole `html` document) is the server-rendered markup, `url` the address bar,
 * `cookie`/`storage` the client theme memory, and `frameElement`/`parent`
 * whether the page is the toolbar drawer or a standalone tab.
 */
export async function bootDebugPage(options) {
  var settings = options || {};
  var navigations = [];
  var posts = [];
  var dom = new JSDOM(
    settings.html ||
      "<!doctype html><html><head></head><body>" +
        (settings.body || "") +
        "</body></html>",
    { url: settings.url || DEFAULT_URL },
  );
  var win = dom.window;
  var documentValue = win.document;
  var facade = Object.create(win);
  var storage = createStorage(settings.storage);

  if (openDom) {
    openDom.window.close();
  }

  openDom = dom;

  if (settings.documentTheme !== undefined) {
    documentValue.documentElement.setAttribute(
      "data-yii-debug-theme",
      settings.documentTheme,
    );
  }

  if (settings.cookie) {
    documentValue.cookie = settings.cookie;
  }

  if (settings.prepare) {
    settings.prepare(documentValue);
  }

  /**
   * jsdom's EventTarget methods reject an inheriting receiver, so the facade
   * forwards them to the window they were taken from.
   */
  ["addEventListener", "dispatchEvent", "removeEventListener"].forEach(
    function (name) {
      define(facade, name, win[name].bind(win));
    },
  );

  define(facade, "localStorage", storage);
  define(facade, "location", createLocation(win, navigations, settings.href));
  define(
    facade,
    "parent",
    settings.parent === "none"
      ? undefined
      : settings.parent === "frame"
        ? {
            postMessage(data, origin) {
              posts.push({ data: data, origin: origin });
            },
          }
        : facade,
  );

  if (settings.clipboard !== undefined) {
    define(facade, "navigator", { clipboard: settings.clipboard });
  } else if (settings.navigator === "none") {
    define(facade, "navigator", undefined);
  }

  var session = installSessionStorage(facade, settings);

  installMatchMedia(facade, settings.matchMedia);
  installFrameElement(facade, documentValue, settings.frameElement);

  Object.defineProperty(globalThis, "window", {
    configurable: true,
    value: facade,
    writable: true,
  });
  Object.defineProperty(globalThis, "document", {
    configurable: true,
    value: documentValue,
    writable: true,
  });
  Object.defineProperty(globalThis, "localStorage", {
    configurable: true,
    value: storage,
    writable: true,
  });

  vi.resetModules();

  await import("../src/core/debug.js");

  return {
    document: documentValue,
    navigations: navigations,
    posts: posts,
    session: session,
    storage: storage,
    window: facade,
    realWindow: win,
  };
}

/** Dispatches a bubbling UI event from `element`, as the browser would. */
export function fire(element, type, init) {
  var documentValue = element.ownerDocument || element;
  var view = documentValue.defaultView;
  var event =
    type === "keydown"
      ? new view.KeyboardEvent(type, Object.assign({ bubbles: true }, init))
      : new view.Event(
          type,
          Object.assign({ bubbles: true, cancelable: true }, init),
        );

  element.dispatchEvent(event);

  return event;
}

/** Presses a key on `element` and reports the dispatched event. */
export function press(element, key) {
  return fire(element, "keydown", { cancelable: true, key: key });
}

/** Lets pending zero-delay timers and microtasks run. */
export function settle(windowValue) {
  return new Promise(function (resolve) {
    windowValue.setTimeout(resolve, 0);
  });
}
