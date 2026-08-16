function hasToolbarItemLink(items) {
  return (items || []).some(function (item) {
    return Boolean(item && item.url);
  });
}

export function renderAjaxProfileLink(profile, profileUrl, escape) {
  if (!profileUrl) {
    return "n/a";
  }

  var url = escape(profileUrl);

  return (
    '<a class="ajax-link" href="' +
    url +
    '" data-debug-url="' +
    url +
    '">' +
    escape(profile || "profile") +
    "</a>"
  );
}

export function renderToolbarLinkAttributes(url, nativeUrl, escape) {
  if (!url) {
    return "";
  }

  return (
    ' href="' + escape(nativeUrl) + '" data-debug-url="' + escape(url) + '"'
  );
}

export function toolbarPanelContainerTag(panel) {
  return panel && panel.url && !hasToolbarItemLink(panel.items) ? "a" : "div";
}

export function toolbarItemTag(item) {
  return item && item.url ? "a" : "span";
}

export function shouldOpenToolbarDrawer(event, url) {
  return Boolean(
    url &&
    event.button !== 1 &&
    !event.ctrlKey &&
    !event.metaKey &&
    !event.shiftKey,
  );
}
