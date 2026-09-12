export const GRID_FILTER_INPUT_SELECTOR = ".yii-debug-grid .filters input";

export function shouldClearGridFilter(event) {
  return Boolean(
    event &&
    event.key === "Escape" &&
    event.target &&
    event.target.matches &&
    event.target.matches(GRID_FILTER_INPUT_SELECTOR) &&
    event.target.value !== "",
  );
}

export function clearGridFilter(target) {
  target.value = "";
  target.dispatchEvent(new Event("change", { bubbles: true }));
}
