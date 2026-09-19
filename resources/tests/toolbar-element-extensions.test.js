// @vitest-environment jsdom
import assert from "node:assert/strict";
import { afterEach, test } from "vitest";

import {
  installLocalStorage,
  installMatchMedia,
  renderToolbar,
  teardownToolbars,
  toolbarPayload,
} from "./toolbar-element-harness.js";

installLocalStorage({ "yii-debug-toolbar-expanded": "1" });
installMatchMedia();

/** Detaches the fixtures a thrown assertion would leave connected. */
afterEach(teardownToolbars);

function payload(overrides) {
  return toolbarPayload(
    Object.assign(
      {
        items: [
          {
            icon: "request",
            id: "request",
            items: [{ label: "Status", value: "200" }],
            title: "Request",
            url: "/debug/request",
          },
          {
            extension: true,
            icon: "inertia",
            id: "inertia",
            items: [{ label: "Version", value: "1" }],
            title: "Inertia",
            url: "/debug/inertia",
          },
          {
            extension: true,
            icon: "queue",
            id: "queue",
            items: [{ label: "Failed", status: "danger", value: "2" }],
            title: "Queue",
            url: "/debug/queue",
          },
        ],
      },
      overrides || {},
    ),
  );
}

function click(node) {
  node.dispatchEvent(
    new window.MouseEvent("click", { bubbles: true, cancelable: true }),
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

function pointerdown(node) {
  node.dispatchEvent(
    new window.MouseEvent("pointerdown", {
      bubbles: true,
      cancelable: true,
      composed: true,
    }),
  );
}

test("provider panels are grouped behind a single Extensions chip", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  assert.equal(
    root.querySelectorAll(".panels > .panel").length,
    1,
    "Only built-ins may stay in the inline strip.",
  );
  assert.equal(
    root.querySelectorAll(".extensions-menu > .panel").length,
    2,
    "Both provider panels must move into the menu.",
  );
  assert.equal(
    root.querySelector(".extensions-toggle .metric-value").textContent,
    "2",
    "Counter must report the grouped panels.",
  );
  assert.equal(
    root.querySelector(".extensions-toggle .metric-value").className,
    "metric-value badge-danger",
    "A failure hidden in the menu must surface on the chip.",
  );
  assert.equal(
    root.querySelector(".extensions-toggle").getAttribute("data-menu"),
    "extensions",
    "Chip must name its menu.",
  );
  assert.equal(
    root.querySelector(".extensions-menu").getAttribute("aria-label"),
    "Extensions",
    "Menu must be labelled.",
  );
  assert.equal(
    root.querySelector(".bar > .extensions").previousElementSibling.className,
    "ajax menu",
    "Menu must follow the AJAX menu.",
  );
  assert.equal(
    root.querySelector(".bar > .extensions").nextElementSibling.className,
    "controls",
    "Menu must precede the controls.",
  );

  element.remove();
});

test("a healthy extension set keeps the neutral badge", () => {
  var element = renderToolbar(
    payload({
      items: [
        {
          extension: true,
          id: "inertia",
          items: [{ label: "Version", value: "1" }],
          title: "Inertia",
        },
      ],
    }),
  );

  assert.equal(
    element.shadowRoot.querySelector(".extensions-toggle .metric-value")
      .className,
    "metric-value badge-default",
    "Badge: default.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".extensions-toggle").className,
    "panel menu-toggle extensions-toggle",
    "Chip must stay inactive.",
  );

  element.remove();
});

test("a payload without provider panels renders no menu", () => {
  var element = renderToolbar(
    payload({
      items: [{ id: "request", title: "Request", url: "/debug/request" }],
    }),
  );

  assert.equal(
    element.shadowRoot.querySelector(".extensions"),
    null,
    "Menu must be absent.",
  );

  element.remove();
});

test("the chip opens the menu, moves focus into it, and closes it again", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;
  var toggle = root.querySelector(".extensions-toggle");

  click(toggle);

  assert.equal(element.openMenu, "extensions", "Menu must be open.");
  assert.ok(
    root.querySelector(".extensions").classList.contains("is-open"),
    "Wrapper must carry the open modifier.",
  );
  assert.equal(
    toggle.getAttribute("aria-expanded"),
    "true",
    "Chip must announce the open menu.",
  );
  assert.equal(
    root.activeElement,
    root.querySelector(".extensions-menu [data-debug-url]"),
    "Focus must land on the first menu entry.",
  );

  click(toggle);

  assert.equal(element.openMenu, null, "Menu must be closed.");
  assert.equal(
    toggle.getAttribute("aria-expanded"),
    "false",
    "Chip must announce the closed menu.",
  );

  element.remove();
});

test("opening one menu closes the other", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  click(root.querySelector(".extensions-toggle"));
  click(root.querySelector(".ajax-toggle"));

  assert.equal(element.openMenu, "ajax", "The AJAX menu must take over.");
  assert.equal(
    root.querySelector(".extensions").classList.contains("is-open"),
    false,
    "Extensions wrapper must be closed.",
  );
  assert.equal(
    root.querySelector(".extensions-toggle").getAttribute("aria-expanded"),
    "false",
    "Extensions chip must announce the closed menu.",
  );
  assert.ok(
    root.querySelector(".ajax").classList.contains("is-open"),
    "AJAX wrapper must be open.",
  );

  click(root.querySelector(".extensions-toggle"));

  assert.equal(
    element.openMenu,
    "extensions",
    "The Extensions menu must take over again.",
  );
  assert.equal(
    root.querySelector(".ajax").classList.contains("is-open"),
    false,
    "AJAX wrapper must be closed.",
  );

  element.remove();
});

test("Escape closes the menu before the drawer and restores focus to the chip", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  click(root.querySelector('[title="Request"]'));
  click(root.querySelector(".extensions-toggle"));
  keydown(root.querySelector(".extensions-toggle"), "Escape");

  assert.equal(element.openMenu, null, "Menu must close first.");
  assert.equal(
    element.drawerOpen,
    true,
    "Drawer must survive the first Escape.",
  );
  assert.equal(
    root.activeElement,
    root.querySelector(".extensions-toggle"),
    "Focus must return to the chip.",
  );

  keydown(root.querySelector(".extensions-toggle"), "Escape");

  assert.equal(
    element.drawerOpen,
    false,
    "A second Escape must close the drawer.",
  );

  element.remove();
});

test("opening a grouped panel closes the menu", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector(".extensions-toggle"));
  click(element.shadowRoot.querySelector('.extensions-menu [title="Queue"]'));

  assert.equal(
    element.openMenu,
    null,
    "Menu must close with the drawer opening.",
  );
  assert.equal(
    element.activeUrl,
    "/debug/queue",
    "Grouped panel must reach the drawer.",
  );
  assert.ok(
    element.shadowRoot
      .querySelector(".extensions-toggle")
      .classList.contains("panel-active"),
    "Chip must mark the open provider panel.",
  );

  element.remove();
});

test("a pointer outside the menu dismisses it and one inside does not", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  click(root.querySelector(".extensions-toggle"));
  pointerdown(root.querySelector('.extensions-menu [title="Inertia"]'));

  assert.equal(
    element.openMenu,
    "extensions",
    "A pointer inside must keep the menu open.",
  );

  pointerdown(root.querySelector(".bar"));

  assert.equal(
    element.openMenu,
    null,
    "A pointer outside must dismiss the menu.",
  );

  element.remove();
});

test("a pointer event without a composed path falls back to its target", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  click(root.querySelector(".extensions-toggle"));
  element.drawer.menuPointerDown({
    target: root.querySelector('.extensions-menu [title="Inertia"]'),
  });

  assert.equal(
    element.openMenu,
    "extensions",
    "A target inside must keep the menu open.",
  );

  element.drawer.menuPointerDown({ target: root.querySelector(".bar") });

  assert.equal(
    element.openMenu,
    null,
    "A target outside must dismiss the menu.",
  );

  element.drawer.menuPointerDown({ target: root.querySelector(".bar") });

  assert.equal(
    element.openMenu,
    null,
    "A closed menu must ignore further pointers.",
  );

  element.remove();
});

test("a bar redrawn while the menu is open keeps it open", () => {
  var element = renderToolbar(payload());

  click(element.shadowRoot.querySelector(".extensions-toggle"));
  element.render();

  assert.ok(
    element.shadowRoot
      .querySelector(".extensions")
      .classList.contains("is-open"),
    "Wrapper must be rendered open.",
  );
  assert.equal(
    element.shadowRoot
      .querySelector(".extensions-toggle")
      .getAttribute("aria-expanded"),
    "true",
    "Chip must be rendered as expanded.",
  );

  element.remove();
});

test("an unrendered menu still tracks its open state", () => {
  var element = renderToolbar(
    payload({
      items: [{ id: "request", title: "Request", url: "/debug/request" }],
    }),
  );

  element.drawer.toggleMenu("extensions");

  assert.equal(
    element.openMenu,
    "extensions",
    "State must flip without a menu to sync.",
  );

  element.drawer.closeMenu(false);

  assert.equal(element.openMenu, null, "State must flip back.");
  assert.equal(
    element.shadowRoot.activeElement,
    null,
    "Nothing may be focused when no chip exists.",
  );

  element.drawer.closeMenu(true);

  assert.equal(element.openMenu, null, "A closed menu must stay closed.");

  element.remove();
});

test("an empty menu leaves focus where it is", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  root.querySelector(".extensions-menu").innerHTML = "";
  root.querySelector(".extensions-toggle").focus();
  element.drawer.toggleMenu("extensions");

  assert.equal(
    root.activeElement,
    root.querySelector(".extensions-toggle"),
    "Focus must stay on the chip.",
  );

  element.remove();
});

test("a wrapper without its chip still records the open state", () => {
  var element = renderToolbar(payload());
  var root = element.shadowRoot;

  root.querySelector(".extensions-toggle").remove();
  element.drawer.toggleMenu("extensions");

  assert.ok(
    root.querySelector(".extensions").classList.contains("is-open"),
    "Wrapper must still be marked open.",
  );

  element.remove();
});
