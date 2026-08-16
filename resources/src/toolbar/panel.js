function hasToolbarItemLink(items) {
  return (items || []).some(function (item) {
    return Boolean(item && item.url);
  });
}

export function toolbarPanelContainerTag(panel) {
  return panel && panel.url && !hasToolbarItemLink(panel.items) ? "a" : "div";
}

export function toolbarItemTag(item) {
  return item && item.url ? "a" : "span";
}
