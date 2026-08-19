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

export function toolbarDrawerHeightForKey(
  position,
  key,
  currentHeight,
  viewportHeight,
  step = 24,
) {
  var minimum = 120;
  var maximum = Math.max(minimum, viewportHeight - 48);

  if (key === "Home") {
    return minimum;
  }

  if (key === "End") {
    return maximum;
  }

  if (key !== "ArrowUp" && key !== "ArrowDown") {
    return null;
  }

  var top = normalizeToolbarPosition(position) === "top";
  var grows = (top && key === "ArrowDown") || (!top && key === "ArrowUp");
  var height = currentHeight + (grows ? step : -step);

  return Math.max(minimum, Math.min(maximum, height));
}
