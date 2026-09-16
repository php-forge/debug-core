// @vitest-environment jsdom
import assert from "node:assert/strict";
import { afterEach, test } from "vitest";

import {
  installLocalStorage,
  installMatchMedia,
  renderToolbar,
  stubBoundingHeight,
  stubViewportHeight,
  teardownToolbars,
  toolbarPayload,
} from "./toolbar-element-harness.js";

var storage = installLocalStorage({ "yii-debug-toolbar-expanded": "1" });

installMatchMedia();

/**
 * Detaches the fixtures and restores the expanded flag, which the collapse
 * control persists as part of the behaviour under test.
 */
afterEach(() => {
  teardownToolbars();
  storage.set("yii-debug-toolbar-expanded", "1");
});

function payload(overrides) {
  return toolbarPayload(
    Object.assign(
      {
        items: [
          {
            icon: "db",
            id: "db",
            items: [{ label: "Queries", value: "3" }],
            title: "Database",
            url: "/debug/db",
          },
          {
            icon: "logs",
            id: "logs",
            items: [{ label: "Errors", value: "0" }],
            title: "Logs",
            url: "/debug/logs",
          },
        ],
      },
      overrides || {},
    ),
  );
}

function click(node, options) {
  node.dispatchEvent(
    new window.MouseEvent(
      "click",
      Object.assign({ bubbles: true, cancelable: true }, options),
    ),
  );
}

function keydown(node, key) {
  node.dispatchEvent(
    new window.KeyboardEvent("keydown", {
      bubbles: true,
      cancelable: true,
      key: key,
    }),
  );
}

function drawerHeight(element) {
  return element.style.getPropertyValue("--yii-debug-toolbar-drawer-height");
}

test("clicking a panel opens the drawer and focuses the close control", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  var frame = element.shadowRoot.querySelector(".drawer iframe");

  assert.equal(element.drawerOpen, true, "Drawer must be open.");
  assert.equal(element.activeUrl, "/debug/db", "Active URL must be recorded.");
  assert.equal(
    frame.getAttribute("src"),
    "http://localhost:3000/debug/db?yii_debug_theme=light",
    "Frame must load the stamped URL.",
  );
  assert.ok(
    element.shadowRoot
      .querySelector(".toolbar")
      .classList.contains("drawer-open"),
    "Open drawer must be reflected in the class list.",
  );
  assert.equal(
    element.shadowRoot.activeElement,
    element.shadowRoot.querySelector(".close-drawer"),
    "Focus must land on the close control.",
  );
  assert.equal(
    storage.get("yii-debug-toolbar-expanded"),
    "1",
    "Opening a panel must pin the expanded state.",
  );

  element.remove();
});

test("a modified click and a click beside a trigger leave the drawer closed", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'), {
    ctrlKey: true,
  });

  assert.equal(
    element.drawerOpen,
    false,
    "A modified click must not be captured.",
  );

  click(element.shadowRoot.querySelector(".bar"));

  assert.equal(
    element.drawerOpen,
    false,
    "A click on inert chrome must be ignored.",
  );

  element.remove();
});

test("an unsafe panel URL never opens the drawer", () => {
  var element = renderToolbar(payload());

  element.openPanel("//evil.test/debug/db");

  assert.equal(
    element.drawerOpen,
    false,
    "Protocol-relative URLs must be rejected.",
  );
  assert.equal(element.activeUrl, "", "No URL may be recorded.");

  element.remove();
});

test("Escape closes the drawer and restores focus to the trigger", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));
  keydown(element.shadowRoot.querySelector(".close-drawer"), "Escape");

  assert.equal(element.drawerOpen, false, "Drawer must be closed.");
  assert.equal(
    element.shadowRoot.activeElement.getAttribute("title"),
    "Database",
    "Focus must return to the chip that opened it.",
  );
  assert.equal(
    element.restoreFocusUrl,
    null,
    "Restore target must be cleared.",
  );

  keydown(element.shadowRoot.querySelector(".bar"), "Escape");

  assert.equal(
    element.drawerOpen,
    false,
    "A closed drawer must ignore Escape.",
  );

  element.remove();
});

test("closing a drawer whose trigger disappeared focuses the collapse control", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));
  element.data.items = [];
  element.closeDrawer();

  assert.equal(
    element.shadowRoot.activeElement,
    element.shadowRoot.querySelector(".toggle-toolbar"),
    "Focus must fall back to the collapse control.",
  );

  element.remove();
});

test("the close control closes the drawer", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));
  click(element.shadowRoot.querySelector(".close-drawer"));

  assert.equal(element.drawerOpen, false, "Drawer must be closed.");
  assert.equal(
    element.drawerRoot.innerHTML,
    "",
    "Drawer host must be emptied.",
  );

  element.render();

  assert.equal(
    element.drawerRoot.innerHTML,
    "",
    "An already empty host must not be rewritten.",
  );

  element.remove();
});

test("the collapse control folds the bar and drops the drawer", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));
  click(element.shadowRoot.querySelector(".toggle-toolbar"));

  assert.equal(element.expanded, false, "Bar must collapse.");
  assert.equal(element.drawerOpen, false, "Drawer must close with the bar.");
  assert.equal(
    storage.get("yii-debug-toolbar-expanded"),
    "0",
    "Collapsed state must be persisted.",
  );

  click(element.shadowRoot.querySelector(".toggle-toolbar"));

  assert.equal(element.expanded, true, "Bar must expand again.");
  assert.equal(
    storage.get("yii-debug-toolbar-expanded"),
    "1",
    "Expanded state must be persisted.",
  );

  element.remove();
});

test("the drawer keeps its browsing context while the bar is refreshed", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  var frame = element.shadowRoot.querySelector(".drawer iframe");

  element.render();

  assert.equal(
    element.shadowRoot.querySelector(".drawer iframe"),
    frame,
    "Frame node must survive a refresh.",
  );
  assert.equal(
    frame.getAttribute("src"),
    "http://localhost:3000/debug/db?yii_debug_theme=light",
    "Unchanged URL must not renavigate the frame.",
  );

  element.openPanel("/debug/logs");

  assert.equal(
    element.shadowRoot.querySelector(".drawer iframe"),
    frame,
    "Navigating must reuse the same frame.",
  );
  assert.equal(
    frame.getAttribute("src"),
    "http://localhost:3000/debug/logs?yii_debug_theme=light",
    "Frame must follow the new panel.",
  );

  element.remove();
});

test("a drawer missing its resize handle is rebuilt", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));
  element.drawerRoot.querySelector(".resize-handle").remove();
  element.render();

  assert.ok(
    element.drawerRoot.querySelector(".resize-handle"),
    "Handle must be restored.",
  );
  assert.ok(
    element.drawerRoot.querySelector(".drawer"),
    "Drawer must be restored.",
  );

  element.remove();
});

test("switching the docking position reorders the handle and the drawer", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  assert.deepEqual(
    Array.prototype.map.call(element.drawerRoot.children, function (node) {
      return node.className;
    }),
    ["resize-handle", "drawer"],
    "Bottom dock: handle above the drawer.",
  );

  element.setAttribute("data-position", "top");
  element.render();

  assert.deepEqual(
    Array.prototype.map.call(element.drawerRoot.children, function (node) {
      return node.className;
    }),
    ["drawer", "resize-handle"],
    "Top dock: drawer above the handle.",
  );

  element.render();

  assert.equal(
    element.drawerPosition,
    "top",
    "An unchanged position must not reorder again.",
  );

  element.setAttribute("data-position", "bottom");
  element.render();

  assert.deepEqual(
    Array.prototype.map.call(element.drawerRoot.children, function (node) {
      return node.className;
    }),
    ["resize-handle", "drawer"],
    "Docking back at the bottom must restore the original order.",
  );

  element.remove();
});

test("a drawer rebuilt while docked at the top keeps the drawer first", () => {
  var element = renderToolbar(payload({ position: "top" }));

  click(element.shadowRoot.querySelector('[title="Database"]'));

  assert.deepEqual(
    Array.prototype.map.call(element.drawerRoot.children, function (node) {
      return node.className;
    }),
    ["drawer", "resize-handle"],
    "Top dock: drawer above the handle.",
  );

  element.remove();
});

test("an unsafe active URL tears the drawer down", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));
  element.activeUrl = "//evil.test/debug/db";
  element.render();

  assert.equal(element.drawerOpen, false, "Drawer must be closed.");
  assert.equal(element.activeUrl, "", "Active URL must be dropped.");
  assert.equal(
    element.drawerRoot.innerHTML,
    "",
    "Drawer host must be emptied.",
  );
  assert.equal(
    element.renderDrawer("bottom"),
    "",
    "A closed drawer must render nothing.",
  );

  element.remove();
});

test("the drawer height comes from the attribute, then the payload, then the default", () => {
  var element = renderToolbar(payload({ defaultHeight: 40 }), {
    "data-height": "70",
  });

  click(element.shadowRoot.querySelector('[title="Database"]'));

  assert.equal(drawerHeight(element), "70vh", "Attribute must win.");

  element.style.removeProperty("--yii-debug-toolbar-drawer-height");
  element.removeAttribute("data-height");
  element.applyDrawerHeight();

  assert.equal(
    drawerHeight(element),
    "40vh",
    "Payload default must be used next.",
  );

  element.style.removeProperty("--yii-debug-toolbar-drawer-height");
  element.data.defaultHeight = undefined;
  element.applyDrawerHeight();

  assert.equal(
    drawerHeight(element),
    "50vh",
    "Built-in default must be the last resort.",
  );

  element.applyDrawerHeight();

  assert.equal(
    drawerHeight(element),
    "50vh",
    "An explicit height must not be recomputed.",
  );

  element.remove();
});

test("the drawer height is clamped to the supported range", () => {
  var low = renderToolbar(payload(), { "data-height": "5" });

  click(low.shadowRoot.querySelector('[title="Database"]'));

  assert.equal(drawerHeight(low), "20vh", "Lower bound: 20vh.");

  low.remove();

  var high = renderToolbar(payload(), { "data-height": "99" });

  click(high.shadowRoot.querySelector('[title="Database"]'));

  assert.equal(drawerHeight(high), "90vh", "Upper bound: 90vh.");

  high.remove();
});

test("a closed toolbar exposes no drawer height to apply", () => {
  var element = renderToolbar(payload());

  element.applyDrawerHeight();

  assert.equal(drawerHeight(element), "", "No drawer means no height.");

  element.remove();
});

test("dragging the handle resizes the drawer until the pointer is released", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  var handle = element.shadowRoot.querySelector(".resize-handle");

  stubBoundingHeight(element.shadowRoot.querySelector(".drawer"), {
    bottom: 700,
    height: 300,
    top: 400,
  });
  handle.dispatchEvent(
    new window.MouseEvent("pointerdown", { bubbles: true, cancelable: true }),
  );

  assert.equal(element.resizing, true, "Pointer down must start the drag.");

  document.dispatchEvent(
    new window.MouseEvent("pointermove", { clientY: 400 }),
  );

  assert.equal(
    drawerHeight(element),
    "300px",
    "Height must follow the cursor.",
  );
  assert.equal(
    handle.getAttribute("aria-valuenow"),
    "300",
    "Handle must publish the measured height.",
  );
  assert.equal(
    handle.getAttribute("aria-valuemin"),
    "120",
    "Range floor: 120.",
  );
  assert.equal(
    handle.getAttribute("aria-valuemax"),
    "720",
    "Range ceiling: viewport - 48.",
  );

  document.dispatchEvent(new window.MouseEvent("pointerup", {}));

  assert.equal(element.resizing, false, "Pointer up must end the drag.");

  document.dispatchEvent(
    new window.MouseEvent("pointermove", { clientY: 200 }),
  );
  element.onPointerMove({ clientY: 200 });

  assert.equal(
    drawerHeight(element),
    "300px",
    "A released pointer must not resize.",
  );

  element.remove();
});

test("dragging without a drawer rectangle measures against the viewport", () => {
  var element = renderToolbar(payload({ position: "top" }));

  click(element.shadowRoot.querySelector('[title="Database"]'));

  element.shadowRoot
    .querySelector(".resize-handle")
    .dispatchEvent(
      new window.MouseEvent("pointerdown", { bubbles: true, cancelable: true }),
    );
  element.shadowRoot.querySelector(".drawer").remove();
  document.dispatchEvent(
    new window.MouseEvent("pointermove", { clientY: 250 }),
  );

  assert.equal(
    drawerHeight(element),
    "250px",
    "Top dock must measure from the cursor down.",
  );

  element.onPointerUp();
  element.remove();
});

test("a viewport without an inner height falls back to the document height", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  element.resizing = true;
  stubViewportHeight(0);

  try {
    element.onPointerMove({ clientY: 10 });

    assert.equal(
      drawerHeight(element),
      "120px",
      "Collapsed viewport must clamp to the floor.",
    );

    element.onResizeKeyDown({ key: "End", preventDefault() {} });

    assert.equal(
      drawerHeight(element),
      "120px",
      "Bounds must collapse onto the floor.",
    );
    assert.equal(
      element.shadowRoot
        .querySelector(".resize-handle")
        .getAttribute("aria-valuemax"),
      "120",
      "Range ceiling must collapse onto the floor.",
    );
  } finally {
    stubViewportHeight(768);
  }

  element.onPointerUp();
  element.remove();
});

test("the keyboard grows, shrinks and jumps the drawer to its bounds", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  var handle = element.shadowRoot.querySelector(".resize-handle");

  stubBoundingHeight(element.shadowRoot.querySelector(".drawer"), {
    height: 300,
  });

  keydown(handle, "ArrowUp");

  assert.equal(
    drawerHeight(element),
    "324px",
    "Arrow up must grow a bottom drawer.",
  );

  keydown(handle, "ArrowDown");

  assert.equal(
    drawerHeight(element),
    "276px",
    "Arrow down must shrink a bottom drawer.",
  );

  keydown(handle, "Home");

  assert.equal(drawerHeight(element), "120px", "Home must jump to the floor.");

  keydown(handle, "End");

  assert.equal(drawerHeight(element), "720px", "End must jump to the ceiling.");

  keydown(handle, "Tab");

  assert.equal(
    drawerHeight(element),
    "720px",
    "An unrelated key must be left alone.",
  );

  element.remove();
});

test("resize keys and range updates are inert without a drawer", () => {
  var element = renderToolbar(payload());

  element.onResizeKeyDown({ key: "Home", preventDefault() {} });

  assert.equal(drawerHeight(element), "", "No drawer means no height.");

  click(element.shadowRoot.querySelector('[title="Database"]'));

  var handle = element.shadowRoot.querySelector(".resize-handle");

  handle.removeAttribute("aria-valuenow");
  element.shadowRoot.querySelector(".drawer").remove();
  element.updateResizeHandleAccessibility();

  assert.equal(
    handle.getAttribute("aria-valuenow"),
    null,
    "An incomplete drawer must not be measured.",
  );

  element.remove();
});

test("the resize handle is wired exactly once", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector('[title="Database"]'));

  var handle = element.shadowRoot.querySelector(".resize-handle");

  element.render();
  stubBoundingHeight(element.shadowRoot.querySelector(".drawer"), {
    height: 300,
  });
  keydown(handle, "Home");

  assert.equal(
    element.shadowRoot.querySelector(".resize-handle"),
    handle,
    "Handle node must be reused.",
  );
  assert.equal(
    drawerHeight(element),
    "120px",
    "Handle must stay wired after a refresh.",
  );

  element.remove();
});

test("an error bar exposes no controls to wire", () => {
  var element = renderToolbar(payload());

  element.renderError("Unable to load debug toolbar data.");
  element.bindEvents();

  assert.equal(
    element.shadowRoot.querySelector(".toggle-toolbar"),
    null,
    "Error bar must expose no controls.",
  );

  element.remove();
});
