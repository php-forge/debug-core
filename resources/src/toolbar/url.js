/**
 * Returns the current page URL when a browser location is available.
 */
function currentLocation() {
  if (typeof window === "undefined" || !window.location) {
    return null;
  }

  return window.location.href || String(window.location);
}

/**
 * Validates a toolbar URL without changing its adapter-provided representation.
 *
 * Debug module IDs and pretty-URL prefixes are configurable, so the portable
 * frontend cannot safely hard-code `/debug/`. The enforceable boundary is that
 * navigable toolbar URLs use HTTP(S), stay on the host origin and do not carry
 * credentials. Protocol-relative URLs are rejected explicitly rather than
 * inheriting the host scheme implicitly.
 */
export function normalizeToolbarUrl(value, locationValue) {
  if (typeof value !== "string") {
    return null;
  }

  var candidate = value.trim();

  if (
    candidate === "" ||
    candidate.indexOf("//") === 0 ||
    candidate.charAt(0) === "\\"
  ) {
    return null;
  }

  var base = locationValue || currentLocation();

  if (!base) {
    // Pure unit-test and server-rendering contexts have no origin to compare.
    // Only root-relative paths can be proven safe there.
    return candidate.charAt(0) === "/" ? candidate : null;
  }

  try {
    var origin = new URL(base);
    var parsed = new URL(candidate, origin);

    if (
      (parsed.protocol !== "http:" && parsed.protocol !== "https:") ||
      parsed.origin !== origin.origin ||
      parsed.username !== "" ||
      parsed.password !== ""
    ) {
      return null;
    }

    return candidate;
  } catch {
    return null;
  }
}

/**
 * Compares two validated toolbar URLs using their absolute browser form.
 */
export function sameToolbarUrl(left, right, locationValue) {
  var base = locationValue || currentLocation();
  var normalizedLeft = normalizeToolbarUrl(left, base);
  var normalizedRight = normalizeToolbarUrl(right, base);

  if (normalizedLeft === null || normalizedRight === null) {
    return false;
  }

  if (!base) {
    return normalizedLeft === normalizedRight;
  }

  return (
    new URL(normalizedLeft, base).href === new URL(normalizedRight, base).href
  );
}
