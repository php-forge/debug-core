/**
 * Resolves keyboard navigation inside a dropdown menu.
 *
 * @returns {number} The target item index, or `-1` when the menu is empty.
 */
export function dropdownNavigationIndex(
  itemCount,
  currentIndex,
  key,
  fromTrigger,
) {
  if (!Number.isInteger(itemCount) || itemCount <= 0) {
    return -1;
  }

  if (key === "Home") {
    return 0;
  }

  if (key === "End") {
    return itemCount - 1;
  }

  if (fromTrigger) {
    return key === "ArrowUp" ? itemCount - 1 : 0;
  }

  if (key === "ArrowUp") {
    return currentIndex < 0
      ? itemCount - 1
      : (currentIndex - 1 + itemCount) % itemCount;
  }

  return currentIndex < 0 ? 0 : (currentIndex + 1) % itemCount;
}
