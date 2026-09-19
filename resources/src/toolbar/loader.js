import { toolbarRetryDelay } from "./loading.js";

/**
 * Shown whenever the transport or the body gives the toolbar nothing it can
 * report: a network error, a host-configured timeout and an opaque HTTP error
 * all resolve to it instead of leaking raw HTML/JSON.
 */
var unavailableMessage = "Unable to load debug toolbar data.";

/**
 * Returns the JSON value carried by `body`, or `null` when it carries none.
 */
function parseBody(body) {
  try {
    return JSON.parse(body);
  } catch {
    return null;
  }
}

/**
 * Returns the message for a response the toolbar cannot render.
 */
function responseMessage(xhr) {
  if (xhr.status === 404) {
    /**
     * Request was profiled but its tag has rotated out of the debug history
     * (or never made it to the manifest). Don't dump the raw JSON body at the
     * user.
     */
    return "Debug data is no longer available for this request.";
  }

  /**
   * Try to read a structured `{error: "..."}` payload first, then fall back to
   * a generic message.
   */
  var parsed = parseBody(xhr.responseText);

  return (parsed && parsed.error) || unavailableMessage;
}

/**
 * Owns one toolbar load lifecycle: the request in flight, the scheduled 404
 * retry and the generation that supersedes older completions.
 *
 * `connectedCallback()` starts a controller and `disconnectedCallback()`
 * disposes it, so a response the network delivers after the element left the
 * document can no longer render or announce an attachment.
 *
 * Usage example:
 *
 * ```js
 * var loader = createToolbarLoader(element);
 *
 * loader.load();
 * loader.dispose();
 * ```
 */
export function createToolbarLoader(toolbar) {
  var disposed = false;
  var generation = 0;
  var request = null;
  var timer = null;

  /**
   * Reports whether a completion still belongs to the live load: disposal, a
   * newer load and an already settled completion all fail here.
   */
  function isCurrent(requestGeneration) {
    return !disposed && generation === requestGeneration;
  }

  /** Cancels a scheduled retry and aborts the request in flight. */
  function stopPending() {
    var xhr = request;

    if (timer !== null) {
      window.clearTimeout(timer);
      timer = null;
    }

    if (xhr !== null) {
      request = null;
      xhr.abort();
    }
  }

  function send(attempt, requestGeneration) {
    var url = toolbar.normalizeUrl(toolbar.getAttribute("data-url"));

    /**
     * Closes the load and reports `message` on the bar when one is given.
     *
     * Settling advances the generation, so the second transport event a
     * browser raises for the same failure cannot report it twice.
     */
    var settle = function (message) {
      if (!isCurrent(requestGeneration)) {
        return;
      }

      generation += 1;
      request = null;

      if (message) {
        toolbar.renderError(message);
      }
    };

    if (!url) {
      settle("Debug toolbar data URL is missing or unsafe.");

      return;
    }

    var xhr = new XMLHttpRequest();

    request = xhr;
    xhr.open("GET", url, true);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.setRequestHeader("Accept", "application/json");

    /**
     * Disposal and a superseding load abort the request themselves, and both
     * already invalidated this generation; an abort carries no message either
     * way, so it never paints an error over the toolbar.
     */
    xhr.onabort = function () {
      settle();
    };

    /**
     * No `xhr.timeout` is set here, so `ontimeout` only fires for a host that
     * configures one. Neither event carries a response to report.
     */
    xhr.onerror = xhr.ontimeout = function () {
      settle(unavailableMessage);
    };

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4 || !isCurrent(requestGeneration)) {
        return;
      }

      if (xhr.status !== 200) {
        var retryDelay = toolbarRetryDelay(xhr.status, attempt);

        /**
         * Yii3 persists the snapshot after the response is flushed, so a 404
         * is retried on a bounded backoff before it is reported.
         */
        if (retryDelay !== null) {
          timer = window.setTimeout(function () {
            timer = null;
            send(attempt + 1, requestGeneration);
          }, retryDelay);

          return;
        }

        settle(responseMessage(xhr));

        return;
      }

      var data = parseBody(xhr.responseText);

      /**
       * The adapter contract is a JSON object carrying the `items` list; `tag`
       * stays optional, so a tag-less snapshot renders like any other.
       */
      if (!data || !Array.isArray(data.items)) {
        settle("Invalid debug toolbar data response.");

        return;
      }

      toolbar.data = data;
      toolbar.render();
      toolbar.dispatchAttachedEvent();
      settle();
    };
    xhr.send();
  }

  return {
    /**
     * Ends the lifecycle: pending completions are invalidated, a scheduled
     * retry is cancelled and the request in flight is aborted.
     */
    dispose: function () {
      disposed = true;
      stopPending();
    },
    /**
     * Fetches the snapshot at `data-url`, superseding whatever the controller
     * still had open.
     */
    load: function () {
      if (disposed) {
        return;
      }

      generation += 1;
      stopPending();
      send(0, generation);
    },
  };
}
