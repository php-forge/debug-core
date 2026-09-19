import {
  absoluteUrl,
  getPrimaryToolbar,
  originalFetch,
  originalXhrOpen,
  parseJsonAttribute,
  requestStack,
  requestStackLimit,
  sameUrl,
  toolbars,
} from "./state.js";
import { normalizeToolbarUrl } from "./url.js";

/**
 * AJAX request tracking — installs replacements for `XMLHttpRequest.open` and
 * `window.fetch` that record matching debug-eligible requests into the shared
 * `requestStack`, then forwards each to the captured original primitives.
 *
 * Tracked requests skip the toolbar's own data fetch and any URL listed in
 * `data-skip-urls` on the host element. Each completed request gets stamped
 * with the headers Yii's debug middleware adds (`X-Debug-Tag`, `-Duration`,
 * `-Link`) so the AJAX menu can open the capture of every listed request. The
 * bar itself keeps showing the page request: an AJAX call never replaces it.
 */

var xhrTrackers = new WeakMap();

/**
 * Events a request that never produced a response finalizes on.
 *
 * `readystatechange` already reaches DONE for most transport failures, so the
 * listeners are a safety net rather than the primary path — whichever fires
 * first detaches the others, which is what makes an entry finalize once.
 */
var xhrFailureEvents = ["abort", "error", "timeout"];

/**
 * Detaches the listeners an instance is tracked with and forgets its tracker.
 *
 * @returns {object|null} The detached tracker, or `null` when the instance
 * carries none — which is how a caller learns the tracker it held has already
 * finalized its entry.
 */
function detachXhrTracker(xhr) {
  var tracker = xhrTrackers.get(xhr);
  var i;

  if (!tracker) {
    return null;
  }

  xhr.removeEventListener("readystatechange", tracker.readyStateChange, false);

  for (i = 0; i < xhrFailureEvents.length; i++) {
    xhr.removeEventListener(xhrFailureEvents[i], tracker.failure, false);
  }

  xhrTrackers.delete(xhr);

  return tracker;
}

/**
 * Records a request the toolbar tracks and keeps the stack bounded.
 *
 * Trimming from the front keeps the most recent `requestStackLimit` entries
 * while the array binding itself stays shared with every other module.
 */
function startRequest(url, method) {
  var item = {
    loading: true,
    error: false,
    url: url,
    method: method,
    start: new Date(),
  };

  requestStack.push(item);

  if (requestStack.length > requestStackLimit) {
    requestStack.splice(0, requestStack.length - requestStackLimit);
  }

  return item;
}

/**
 * Stamps an entry with the metadata Yii's debug middleware returns.
 *
 * `readHeader` abstracts the adapter difference between `getResponseHeader()`
 * and `Headers.get()`; nothing here reads a response body.
 */
function completeRequest(item, readHeader, statusCode) {
  item.duration = readHeader("X-Debug-Duration") || new Date() - item.start;
  item.loading = false;
  item.statusCode = statusCode;
  item.error = statusCode < 200 || statusCode >= 400;
  item.profile = readHeader("X-Debug-Tag");
  item.profilerUrl = normalizeToolbarUrl(readHeader("X-Debug-Link"));
}

/**
 * Finalizes an entry whose request never produced a response.
 *
 * `statusCode` is omitted for `fetch`, which exposes no transport status on a
 * rejected promise; `XMLHttpRequest` reports `0` and that value is recorded.
 */
function failRequest(item, statusCode) {
  item.loading = false;
  item.error = true;

  if (typeof statusCode === "number") {
    item.statusCode = statusCode;
  }
}

function shouldTrackRequest(requestUrl) {
  if (!requestUrl) {
    return false;
  }

  var toolbar = getPrimaryToolbar();
  if (!toolbar) {
    return false;
  }

  var url = absoluteUrl(requestUrl);
  if (url === null || url.host !== window.location.host) {
    return false;
  }

  if (sameUrl(requestUrl, toolbar.getAttribute("data-url"))) {
    return false;
  }

  var skipUrls = parseJsonAttribute(toolbar, "data-skip-urls", []);
  for (var i = 0; i < skipUrls.length; i++) {
    if (sameUrl(requestUrl, skipUrls[i])) {
      return false;
    }
  }

  return true;
}

function notifyAjaxChange() {
  toolbars.forEach(function (toolbar) {
    toolbar.setAjaxRequests(requestStack);
  });
}

function trackXhr() {
  XMLHttpRequest.prototype.open = function (method, url) {
    var xhr = this;
    var trackRequest = shouldTrackRequest(url);
    var previousTracker = xhrTrackers.get(xhr);

    /* The tracker survives the native call, which may throw and keep the request. */
    originalXhrOpen.apply(xhr, Array.prototype.slice.call(arguments));

    /**
     * Reopening an active instance aborts the request underneath it without a
     * final `readystatechange`, so the entry it leaves behind is finalized
     * here — unless its own listeners already did, which keeps an entry
     * finalized exactly once.
     */
    if (previousTracker && detachXhrTracker(xhr) === previousTracker) {
      failRequest(previousTracker.item, 0);
      notifyAjaxChange();
    }

    if (trackRequest) {
      var item = startRequest(url, method);
      var readHeader = function (name) {
        return xhr.getResponseHeader(name);
      };
      var handleReadyStateChange = function () {
        if (xhr.readyState !== 4) {
          return;
        }

        detachXhrTracker(xhr);
        completeRequest(item, readHeader, xhr.status);
        notifyAjaxChange();
      };
      var handleFailure = function () {
        detachXhrTracker(xhr);
        failRequest(item, xhr.status);
        notifyAjaxChange();
      };
      var i;

      xhr.addEventListener("readystatechange", handleReadyStateChange, false);

      for (i = 0; i < xhrFailureEvents.length; i++) {
        xhr.addEventListener(xhrFailureEvents[i], handleFailure, false);
      }

      xhrTrackers.set(xhr, {
        failure: handleFailure,
        item: item,
        readyStateChange: handleReadyStateChange,
      });
      notifyAjaxChange();
    }
  };
}

function trackFetch() {
  if (!originalFetch) {
    return;
  }

  window.fetch = function (input, init) {
    var method;
    var url;

    if (typeof input === "string") {
      method = (init && init.method) || "GET";
      url = input;
    } else if (window.URL && input instanceof URL) {
      method = (init && init.method) || "GET";
      url = input.href;
    } else if (window.Request && input instanceof Request) {
      method = (init && init.method) || input.method;
      url = input.url;
    } else if (input) {
      method = (init && init.method) || input.method || "GET";
      url = input.url || String(input);
    }

    var promise = originalFetch(input, init);

    if (shouldTrackRequest(url)) {
      var item = startRequest(url, method);

      promise
        .then(function (response) {
          completeRequest(
            item,
            function (name) {
              return response.headers.get(name);
            },
            response.status,
          );
          notifyAjaxChange();

          return response;
        })
        .catch(function () {
          failRequest(item);
          notifyAjaxChange();
        });
      notifyAjaxChange();
    }

    return promise;
  };
}

export function trackRequests() {
  if (window.__yiiDebugToolbarTracking) {
    return;
  }

  window.__yiiDebugToolbarTracking = true;
  trackXhr();
  trackFetch();
}
