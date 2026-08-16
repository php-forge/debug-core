const retryDelays = Object.freeze([75, 150, 300, 600, 900]);

/**
 * Returns the delay for a toolbar snapshot that Yii3 has not persisted yet.
 */
export function toolbarRetryDelay(status, attempt) {
  if (status !== 404 || attempt < 0 || attempt >= retryDelays.length) {
    return null;
  }

  return retryDelays[attempt];
}

export function resolveToolbarLoadGeneration(
  activeGeneration,
  requestGeneration,
) {
  return typeof requestGeneration === "number"
    ? requestGeneration
    : activeGeneration + 1;
}

export function isToolbarLoadCurrent(activeGeneration, requestGeneration) {
  return activeGeneration === requestGeneration;
}

export function resolveToolbarLoadRollback(
  lastLoadedUrl,
  lastLoadedTag,
  previousUrl,
  previousTag,
) {
  var hasLoadedSnapshot = Boolean(lastLoadedUrl);

  return {
    url: hasLoadedSnapshot ? lastLoadedUrl : previousUrl,
    tag: hasLoadedSnapshot ? lastLoadedTag : previousTag,
    reload: !hasLoadedSnapshot,
  };
}

export function toolbarDataUrlForTag(url, currentTag, nextTag, baseUrl) {
  if (!url || !nextTag) {
    return null;
  }

  var parsed;

  try {
    parsed = new URL(url, baseUrl);
  } catch {
    return null;
  }

  if (parsed.searchParams.has("tag")) {
    parsed.searchParams.set("tag", nextTag);

    return parsed.href;
  }

  var segments = parsed.pathname.split("/");
  var currentSegment = currentTag ? encodeURIComponent(currentTag) : "";
  var currentIndex = currentSegment ? segments.lastIndexOf(currentSegment) : -1;

  if (currentIndex !== -1) {
    segments[currentIndex] = encodeURIComponent(nextTag);
    parsed.pathname = segments.join("/");
  } else {
    parsed.searchParams.set("tag", nextTag);
  }

  return parsed.href;
}
