export function normalizeToolbarPosition(position) {
  return position === "top" || position === "upper" ? "top" : "bottom";
}

export function toolbarDrawerHeight(
  position,
  clientY,
  viewportHeight,
  drawerRect,
) {
  var top = normalizeToolbarPosition(position) === "top";

  if (drawerRect === null) {
    return top ? clientY : viewportHeight - clientY;
  }

  return top ? clientY - drawerRect.top : drawerRect.bottom - clientY;
}
