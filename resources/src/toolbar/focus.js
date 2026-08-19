export function focusToolbarElement(root, selector) {
  if (!root) {
    return false;
  }

  var element = root.querySelector(selector);

  if (!element || typeof element.focus !== "function") {
    return false;
  }

  element.focus();

  return true;
}

export function focusToolbarTrigger(root, url) {
  if (!root || !url) {
    return false;
  }

  var triggers = root.querySelectorAll("[data-debug-url]");

  for (var i = 0; i < triggers.length; i++) {
    if (triggers[i].getAttribute("data-debug-url") === url) {
      triggers[i].focus();

      return true;
    }
  }

  return false;
}

export function isToolbarDrawerCloseMessage(event, origin, frameWindow) {
  var data = event && event.data;

  return Boolean(
    event &&
    event.origin === origin &&
    frameWindow &&
    event.source === frameWindow &&
    data &&
    typeof data === "object" &&
    data.source === "yii-debug-toolbar" &&
    data.type === "close-drawer",
  );
}

export function requestParentToolbarDrawerClose(
  event,
  browserWindow,
  dropdownWasOpen,
) {
  if (
    !event ||
    event.key !== "Escape" ||
    event.defaultPrevented ||
    dropdownWasOpen ||
    !browserWindow.parent ||
    browserWindow.parent === browserWindow
  ) {
    return false;
  }

  browserWindow.parent.postMessage(
    { source: "yii-debug-toolbar", type: "close-drawer" },
    browserWindow.location.origin,
  );

  return true;
}

export function shouldCloseToolbarDrawer(event, drawerOpen) {
  return Boolean(
    drawerOpen && event && event.key === "Escape" && !event.defaultPrevented,
  );
}
