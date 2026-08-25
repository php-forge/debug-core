export const DENSITY_STORAGE_KEY = "yii-debug-density";

export function normalizeDensity(value) {
  return value === "compact" || value === "cozy" ? value : null;
}

export function readStoredDensity(storage) {
  try {
    return normalizeDensity(storage && storage.getItem(DENSITY_STORAGE_KEY));
  } catch {
    return null;
  }
}

export function writeStoredDensity(density, storage) {
  try {
    if (storage) {
      storage.setItem(DENSITY_STORAGE_KEY, density);
    }
  } catch {
    // A blocked storage backend must not make the density control unusable.
  }
}

export function applyDensity(root, density) {
  var normalized = normalizeDensity(density) || "cozy";

  if (root) {
    root.setAttribute("data-yii-debug-density", normalized);
  }

  return normalized;
}

export function updateDensityButton(button, density) {
  if (!button) {
    return;
  }

  var compact = density === "compact";
  var nextLabel = compact
    ? "Switch to cozy density"
    : "Switch to compact density";
  var label = button.querySelector("[data-yii-debug-density-label]");

  button.setAttribute("aria-label", nextLabel);
  button.setAttribute("aria-pressed", compact ? "true" : "false");
  button.setAttribute("title", nextLabel);

  if (label) {
    label.textContent = compact ? "Compact" : "Cozy";
  }
}

export function bindDensityToggle(button, root, storage) {
  var density = applyDensity(
    root,
    readStoredDensity(storage) ||
      (root && root.getAttribute("data-yii-debug-density")),
  );

  updateDensityButton(button, density);

  if (!button) {
    return density;
  }

  button.addEventListener("click", function () {
    var next = density === "compact" ? "cozy" : "compact";

    density = applyDensity(root, next);
    writeStoredDensity(next, storage);
    updateDensityButton(button, next);
  });

  return density;
}
