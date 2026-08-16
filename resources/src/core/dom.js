export const AJAX_TIMEOUT = 30000;

export function on(element, event, handler) {
  if (element === null || typeof element === "undefined") {
    return;
  }

  var elements =
    (typeof NodeList !== "undefined" && element instanceof NodeList) ||
    Array.isArray(element)
      ? element
      : [element];

  for (var i = 0; i < elements.length; i++) {
    if (typeof elements[i].addEventListener === "function") {
      elements[i].addEventListener(event, handler, false);
    }
  }
}

export function ajax(url, settings) {
  if (
    url !== null &&
    typeof url === "object" &&
    Object.prototype.hasOwnProperty.call(url, "url")
  ) {
    settings = url;
    url = settings.url;
  }

  settings = settings || {};

  var xhr = new XMLHttpRequest();
  var method = settings.method || "GET";
  var completed = false;
  var succeed = function () {
    if (completed) {
      return;
    }

    completed = true;

    if (settings.success) {
      settings.success(xhr);
    }
  };
  var fail = function () {
    if (completed) {
      return;
    }

    completed = true;

    if (settings.error) {
      settings.error(xhr);
    }
  };

  xhr.open(method, url, true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
  xhr.setRequestHeader("Accept", "text/html");

  if (method.toLowerCase() === "post") {
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  }

  xhr.timeout =
    typeof settings.timeout === "number" && settings.timeout >= 0
      ? settings.timeout
      : AJAX_TIMEOUT;
  xhr.onreadystatechange = function () {
    if (xhr.readyState !== 4) {
      return;
    }

    if (xhr.status >= 200 && xhr.status < 300) {
      succeed();
    } else {
      fail();
    }
  };
  xhr.ontimeout = fail;
  xhr.send(settings.data || "");
}
