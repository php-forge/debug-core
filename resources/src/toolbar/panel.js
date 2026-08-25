import { normalizeToolbarUrl } from "./url.js";

function hasToolbarItemLink(items, locationValue) {
  if (!items) {
    return false;
  }

  return items.some(function (item) {
    return normalizeToolbarUrl(item.url, locationValue) !== null;
  });
}

export function renderAjaxProfileLink(
  profile,
  profileUrl,
  nativeUrl,
  escape,
  locationValue,
) {
  var attributes = renderToolbarLinkAttributes(
    profileUrl,
    nativeUrl,
    escape,
    locationValue,
  );

  if (attributes === "") {
    return "n/a";
  }

  return '<a class="ajax-link"' + attributes + ">" + escape(profile) + "</a>";
}

export function renderToolbarLinkAttributes(
  url,
  nativeUrl,
  escape,
  locationValue,
) {
  var drawerUrl = normalizeToolbarUrl(url, locationValue);
  var linkUrl = nativeUrl
    ? normalizeToolbarUrl(nativeUrl, locationValue)
    : drawerUrl;

  if (drawerUrl === null || linkUrl === null) {
    return "";
  }

  return (
    ' href="' + escape(linkUrl) + '" data-debug-url="' + escape(drawerUrl) + '"'
  );
}

export function toolbarPanelContainerTag(panel, locationValue) {
  return normalizeToolbarUrl(panel.url, locationValue) !== null &&
    !hasToolbarItemLink(panel.items, locationValue)
    ? "a"
    : "div";
}

export function toolbarItemTag(item, locationValue) {
  return normalizeToolbarUrl(item.url, locationValue) !== null ? "a" : "span";
}

export function shouldOpenToolbarDrawer(event, url, locationValue) {
  return Boolean(
    normalizeToolbarUrl(url, locationValue) &&
    event.button !== 1 &&
    !event.ctrlKey &&
    !event.metaKey &&
    !event.shiftKey,
  );
}
