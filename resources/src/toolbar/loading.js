const retryDelays = Object.freeze([75, 150, 300, 600, 900]);

/**
 * Returns the delay for a toolbar snapshot that Yii3 has not persisted yet.
 */
export function toolbarRetryDelay(status, attempt) {
  if (
    status !== 404 ||
    !Number.isInteger(attempt) ||
    attempt < 0 ||
    attempt >= retryDelays.length
  ) {
    return null;
  }

  return retryDelays[attempt];
}
